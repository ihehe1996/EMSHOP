<?php

/**
 * 真正的业务逻辑（发货、超时、轮询、同步）。
 *
 * Worker 只负责「多久调用一次」；具体怎么处理订单/商品都在本类。
 * 插件钩子名仍用旧的 swoole_*，避免已有插件失效。
 */
final class CliServerTasks
{
    /**
     * 发货队列：取 1 条待处理任务 → 抢占 → 执行插件发货钩子 → 更新成功/失败。
     */
    public static function processQueue(): void
    {
        Config::reload();

        $prefix = Database::prefix();

        $task = Database::fetchOne(
            "SELECT * FROM {$prefix}delivery_queue
             WHERE status IN ('pending','retry') AND (next_retry_at IS NULL OR next_retry_at <= NOW())
             ORDER BY id ASC LIMIT 1"
        );

        if (!$task) {
            return;
        }

        $taskId = (int) $task['id'];

        CliServer::log('开始执行队列消费 >>> ' . $taskId);

        // 抢占任务（乐观锁）：仅当仍为 pending/retry 时标记为 processing
        $affected = Database::execute(
            "UPDATE {$prefix}delivery_queue SET status='processing', attempts=attempts+1 WHERE id=? AND status IN ('pending','retry')",
            [$taskId]
        );
        if ($affected === 0) {
            CliServer::log('抢占任务失败 >>> ' . $taskId);
            return;
        }

        CliServer::log('抢占任务成功 >>> ' . $taskId);

        try {
            $orderId = (int) $task['order_id'];
            $orderGoodsId = (int) $task['order_goods_id'];
            $goodsType = $task['goods_type'];
            $payload = json_decode($task['payload'] ?? '{}', true) ?: [];

            CliServer::log('开始执行任务 >>> ' . $orderId . ' >>> ' . "goods_type_{$goodsType}_order_paid");

            doAction("goods_type_{$goodsType}_order_paid", $orderId, $orderGoodsId, json_encode($payload));

            CliServer::log('执行任务成功 >>> ' . $orderId . ' >>> ' . "goods_type_{$goodsType}_order_paid");

            Database::execute(
                "UPDATE {$prefix}delivery_queue SET status='success', completed_at=NOW() WHERE id=?",
                [$taskId]
            );
            CliServer::log('更新任务状态成功 >>> ' . $taskId);

            OrderModel::notifyDeliveryCallback($orderGoodsId);

            try {
                doAction('order_goods_delivery_queued_success', $orderId, $orderGoodsId, $taskId, $task);
            } catch (Throwable $hookErr) {
            }

            OrderModel::checkDeliveryComplete($orderId);
        } catch (Throwable $e) {
            $attempts = (int) $task['attempts'] + 1;
            $maxAttempts = (int) $task['max_attempts'];
            $isPermanent = $e instanceof PermanentDeliveryException;

            if ($isPermanent || $attempts >= $maxAttempts) {
                Database::execute(
                    "UPDATE {$prefix}delivery_queue SET status='failed', last_error=? WHERE id=?",
                    [($isPermanent ? '[永久失败] ' : '') . $e->getMessage(), $taskId]
                );
            } else {
                $delay = min(300, 30 * pow(2, $attempts - 1));
                Database::execute(
                    "UPDATE {$prefix}delivery_queue SET status='retry', last_error=?, next_retry_at=DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id=?",
                    [$e->getMessage(), $delay, $taskId]
                );
            }
        }
    }

    /**
     * 待支付订单超时检查。
     */
    public static function runOrderTimeoutChecks(): void
    {
        Config::reload();

        $prefix = Database::prefix();
        $expireMinutes = (int) (Config::get('shop_order_expire_minutes', '30') ?: 30);

        Database::execute(
            "UPDATE {$prefix}order SET status='expired'
             WHERE status='pending' AND created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$expireMinutes]
        );
    }

    /**
     * 订单轮询（插件钩子 swoole_order_poll_tick）。
     */
    public static function runOrderPollingTasks(): void
    {
        try {
            doAction('swoole_order_poll_tick');
        } catch (Throwable $e) {
            CliServer::log('异常：订单轮询钩子 swoole_order_poll_tick，' . $e->getMessage());
        }
    }

    /**
     * 商品同步（插件钩子 swoole_goods_sync_one）。
     */
    public static function runGoodsSyncTasks(): void
    {
        $lockToken = self::goodsSyncAcquireRunLock();
        if ($lockToken === '') {
            return;
        }

        try {
            $state = self::goodsSyncLoadState();
            $batchIds = $state['batch_ids'];
            $batchIndex = $state['batch_index'];
            $cursorId = $state['cursor_id'];

            if ($batchIds === [] || $batchIndex >= count($batchIds)) {
                $batchIds = self::goodsSyncFetchBatchIds($cursorId, CliServer::GOODS_SYNC_BATCH_SIZE);
                if ($batchIds === [] && $cursorId > 0) {
                    $cursorId = 0;
                    $batchIds = self::goodsSyncFetchBatchIds($cursorId, CliServer::GOODS_SYNC_BATCH_SIZE);
                }

                $state['cursor_id'] = $cursorId;
                $state['batch_ids'] = $batchIds;
                $state['batch_index'] = 0;
                self::goodsSyncSaveState($state);
                $batchIndex = 0;
            }

            if ($batchIds === []) {
                return;
            }

            $batchCount = count($batchIds);
            for ($i = $batchIndex; $i < $batchCount; $i++) {
                $goodsId = (int) $batchIds[$i];
                $goodsRow = self::goodsSyncLoadGoodsRow($goodsId);

                if ($goodsRow !== null) {
                    try {
                        doAction('swoole_goods_sync_one', $goodsRow);
                    } catch (Throwable $e) {
                        // 单商品失败不阻塞批次
                    }
                }

                $state['cursor_id'] = $goodsId;
                $state['batch_index'] = $i + 1;
                self::goodsSyncSaveState($state);
            }

            $state['batch_ids'] = [];
            $state['batch_index'] = 0;
            self::goodsSyncSaveState($state);
        } finally {
            self::goodsSyncReleaseRunLock($lockToken);
        }
    }

    /**
     * 检查文件版本号；若需重载则通知主进程重启全部 worker。
     *
     * @return bool 是否已请求重载
     */
    public static function checkFileVersionAndRequestReload(): bool
    {
        $local = trim((string) (Config::get('local_swoole_file_version', '0.0.0') ?? '0.0.0'));
        $new = trim((string) (Config::get('new_swoole_file_version', '') ?? ''));
        if ($new === '') {
            return false;
        }

        if (!@version_compare($new, $local, '>')) {
            return false;
        }

        CliServer::log("重载：检测到文件版本升级（自 {$local} 变更为 {$new}），正在通知主进程重启 worker");

        if (!CliServer::requestReload()) {
            CliServer::log("异常：文件版本升级后写入重载标记失败（自 {$local} 变更为 {$new}）");
            return false;
        }

        CliServer::log("重载：已写入重载标记（文件版本 {$local}→{$new}），主进程将重启 worker");
        Config::set('local_swoole_file_version', $new);
        return true;
    }

    /**
     * @return array{cursor_id:int,batch_ids:array<int,int>,batch_index:int,running_lock:string}
     */
    private static function goodsSyncLoadState(): array
    {
        $raw = trim((string) (Config::get(CliServer::GOODS_SYNC_STATE_KEY, '') ?? ''));
        $state = [];
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $state = $decoded;
            }
        }

        $cursorId = (int) ($state['cursor_id'] ?? 0);
        if ($cursorId < 0) {
            $cursorId = 0;
        }

        $batchIds = [];
        $rawBatchIds = $state['batch_ids'] ?? [];
        if (is_array($rawBatchIds)) {
            foreach ($rawBatchIds as $id) {
                $goodsId = (int) $id;
                if ($goodsId > 0) {
                    $batchIds[] = $goodsId;
                }
            }
        }
        $batchIds = array_values(array_unique($batchIds));

        $batchIndex = (int) ($state['batch_index'] ?? 0);
        if ($batchIndex < 0) {
            $batchIndex = 0;
        }
        if ($batchIndex > count($batchIds)) {
            $batchIndex = count($batchIds);
        }

        return [
            'cursor_id' => $cursorId,
            'batch_ids' => $batchIds,
            'batch_index' => $batchIndex,
            'running_lock' => trim((string) ($state['running_lock'] ?? '')),
        ];
    }

    /**
     * @param array{cursor_id:int,batch_ids:array<int,int>,batch_index:int,running_lock:string} $state
     */
    private static function goodsSyncSaveState(array $state): void
    {
        $payload = [
            'cursor_id' => max(0, (int) ($state['cursor_id'] ?? 0)),
            'batch_ids' => array_values(array_map('intval', array_filter((array) ($state['batch_ids'] ?? []), static function ($id): bool {
                return (int) $id > 0;
            }))),
            'batch_index' => max(0, (int) ($state['batch_index'] ?? 0)),
            'running_lock' => trim((string) ($state['running_lock'] ?? '')),
        ];
        if ($payload['batch_index'] > count($payload['batch_ids'])) {
            $payload['batch_index'] = count($payload['batch_ids']);
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '') {
            $json = '{"cursor_id":0,"batch_ids":[],"batch_index":0,"running_lock":""}';
        }
        Config::set(CliServer::GOODS_SYNC_STATE_KEY, $json);
    }

    private static function goodsSyncAcquireRunLock(): string
    {
        $state = self::goodsSyncLoadState();
        $existingLock = (string) ($state['running_lock'] ?? '');
        if ($existingLock !== '') {
            $parts = explode(':', $existingLock, 2);
            $lockAt = isset($parts[1]) ? (int) $parts[1] : 0;
            if ($lockAt > 0 && (time() - $lockAt) <= 1800) {
                return '';
            }
            CliServer::log('警告：检测到商品同步运行锁过期，已自动回收');
        }

        try {
            $token = bin2hex(random_bytes(8));
        } catch (Throwable $e) {
            $token = md5(uniqid((string) mt_rand(), true));
        }
        $lockToken = $token . ':' . time();
        $state['running_lock'] = $lockToken;
        self::goodsSyncSaveState($state);

        $verify = self::goodsSyncLoadState();
        return (string) ($verify['running_lock'] ?? '') === $lockToken ? $lockToken : '';
    }

    private static function goodsSyncReleaseRunLock(string $lockToken): void
    {
        if ($lockToken === '') {
            return;
        }
        $state = self::goodsSyncLoadState();
        if ((string) ($state['running_lock'] ?? '') !== $lockToken) {
            return;
        }
        $state['running_lock'] = '';
        self::goodsSyncSaveState($state);
    }

    /**
     * @return array<int,int>
     */
    private static function goodsSyncFetchBatchIds(int $cursorId, int $limit): array
    {
        $sourceTypes = self::goodsSyncResolveSourceTypes();
        if ($sourceTypes === []) {
            return [];
        }

        $prefix = Database::prefix();
        $typePlaceholders = implode(',', array_fill(0, count($sourceTypes), '?'));
        $params = array_merge(
            $sourceTypes,
            [max(0, $cursorId), max(1, $limit)]
        );
        $rows = Database::query(
            "SELECT `id`
             FROM `{$prefix}goods`
             WHERE `deleted_at` IS NULL
               AND `source_type` IN ({$typePlaceholders})
               AND `id` > ?
             ORDER BY `id` ASC
             LIMIT ?",
            $params
        );

        $ids = [];
        foreach ($rows as $row) {
            $goodsId = (int) ($row['id'] ?? 0);
            if ($goodsId > 0) {
                $ids[] = $goodsId;
            }
        }

        return $ids;
    }

    /**
     * @return array<int,string>
     */
    private static function goodsSyncResolveSourceTypes(): array
    {
        $types = applyFilter('swoole_goods_sync_source_types', []);
        if (!is_array($types)) {
            return [];
        }

        $out = [];
        foreach ($types as $type) {
            $name = trim((string) $type);
            if ($name === '') {
                continue;
            }
            if (!preg_match('/^[a-z0-9_]+$/i', $name)) {
                continue;
            }
            $out[$name] = true;
        }

        return array_keys($out);
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function goodsSyncLoadGoodsRow(int $goodsId): ?array
    {
        if ($goodsId <= 0) {
            return null;
        }

        $prefix = Database::prefix();
        $row = Database::fetchOne(
            "SELECT `id`, `source_type`, `source_id`, `title`, `updated_at`
             FROM `{$prefix}goods`
             WHERE `id` = ? AND `deleted_at` IS NULL
             LIMIT 1",
            [$goodsId]
        );
        if ($row === null || !is_array($row)) {
            return null;
        }
        return $row;
    }
}
