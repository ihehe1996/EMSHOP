<?php

/**
 * 发货队列消费服务。
 *
 * 从原 CliServerTasks::processQueue() 抽出来，因为队列的**消费者**现在有两个：
 *
 *   1. 守护进程插件注册的 queue worker —— 异步消费，失败按 max_attempts 退避重试；
 *   2. 纯 FPM 部署（没装守护进程插件）—— 支付回调里对本单**同步**发货，失败不重试。
 *
 * 两条路径共用同一份「抢占 + 执行 + 落状态」逻辑，保证：
 *   - 队列行永远是发货历史的唯一真相（checkDeliveryComplete 依赖它判人工发货）；
 *   - 双消费者不会重复发货（乐观锁抢占，谁先抢到谁做）。
 *
 * 注意：本类不做定时，也不 reload 配置 —— 那是调用方（worker 循环）的事。
 */
final class DeliveryQueueService
{
    /**
     * 按条件取一条待处理任务并原子抢占。
     *
     * **过滤条件是这个方法的核心**：现在每个商品类型插件都有自己的发货 worker，
     * 各自只领自己类型的任务 —— 这样一个类型卡在上游不会拖住别的类型。
     *
     *   claimFor(['goods_type' => 'virtual_card'])     只领虚拟卡密
     *   claimFor(['goods_type' => ['a', 'b']])         领多个类型
     *   claimFor()                                     不限类型（FPM 同步路径 / 兼容旧 queue worker）
     *
     * ⚠️ 给了过滤条件但值是空的时候**返回 null**，绝不退化成"领任意任务" ——
     *    那会让一个 worker 领走本该属于别人的任务。
     *
     * 抢占用乐观锁：只有仍处于 pending/retry 的行才能被改成 processing，
     * 影响行数为 0 说明已被别的消费者抢走，直接放弃。
     *
     * @param array<string, mixed> $filter 支持的键：goods_type（字符串或字符串数组）
     * @return array<string, mixed>|null 抢占成功返回任务行，无任务返回 null
     */
    public static function claimFor(array $filter = []): ?array
    {
        $prefix = Database::prefix();

        $where = [
            "status IN ('pending','retry')",
            '(next_retry_at IS NULL OR next_retry_at <= NOW())',
        ];
        $params = [];

        if (array_key_exists('goods_type', $filter)) {
            $types = [];
            foreach ((array) $filter['goods_type'] as $t) {
                $t = trim((string) $t);
                if ($t !== '') {
                    $types[$t] = true;
                }
            }
            $types = array_keys($types);
            if ($types === []) {
                return null; // 过滤条件为空 = 不领任何任务
            }
            $where[] = 'goods_type IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
            $params = array_merge($params, $types);
        }

        $task = Database::fetchOne(
            "SELECT * FROM {$prefix}delivery_queue
             WHERE " . implode(' AND ', $where) . "
             ORDER BY id ASC LIMIT 1",
            $params
        );

        if (!$task) {
            return null;
        }

        $taskId = (int) $task['id'];
        $claimed = self::claim($taskId, $task);
        if ($claimed === null) {
            return null; // 被别人抢走了，正常现象，不必记日志
        }

        CliServer::log('抢占任务成功 >>> ' . $taskId);
        return $claimed;
    }

    /**
     * 取一条待处理任务（不限类型）。
     *
     * @return array<string, mixed>|null
     */
    public static function claimNext(): ?array
    {
        return self::claimFor();
    }

    /**
     * 抢占指定的队列行。
     *
     * @param array<string, mixed> $task 抢占前读到的任务行（用于返回原值）
     * @return array<string, mixed>|null 抢占失败返回 null
     */
    private static function claim(int $taskId, array $task): ?array
    {
        $prefix = Database::prefix();

        $affected = Database::execute(
            "UPDATE {$prefix}delivery_queue SET status='processing', attempts=attempts+1
             WHERE id=? AND status IN ('pending','retry')",
            [$taskId]
        );
        if ($affected === 0) {
            return null;
        }

        return $task;
    }

    /**
     * 执行一条已抢占的任务 —— **执行体由调用方注入**。
     *
     * 为什么要把 runner 做成参数：核心不该知道"这个商品类型该调哪个钩子"。
     * 现在每个商品类型插件有自己的 worker，由它自己决定执行什么：
     *
     *   DeliveryTaskWorker  → doAction('goods_type_x_order_paid', ...)   （插件自己的钩子）
     *   核心的 execute()     → 同上，供 FPM 同步路径与旧的 queue worker 复用
     *
     * 这样核心只负责「抢占 / 落状态 / 重试 / 订单状态收敛」这套可靠性逻辑，
     * 跟具体插件完全解耦。
     *
     * @param array<string, mixed> $task       已抢占的任务行
     * @param callable             $runner     function(array $task): void，失败请抛异常
     * @param bool                 $allowRetry true=失败进 retry 队列等下次（守护进程模式）
     *                                         false=失败直接终态、订单转 delivery_failed（FPM 同步模式）
     * @return bool 是否发货成功
     */
    public static function runTask(array $task, callable $runner, bool $allowRetry): bool
    {
        $prefix = Database::prefix();

        $taskId       = (int) $task['id'];
        $orderId      = (int) $task['order_id'];
        $orderGoodsId = (int) $task['order_goods_id'];

        try {
            $runner($task);

            Database::execute(
                "UPDATE {$prefix}delivery_queue SET status='success', completed_at=NOW() WHERE id=?",
                [$taskId]
            );

            OrderModel::notifyDeliveryCallback($orderGoodsId);

            // 「发货完成」通知。两条路径的处理方式**刻意不同**：
            //
            //   $allowRetry = true  → 跑在常驻 worker 进程里。
            //       直接 doAction 的话，订阅方的闭包就住进这个长驻进程 ——
            //       更新任何一个订阅方插件，都得重启发货 worker。
            //       所以落库成事件，让订阅方各自的 worker 按游标取回去，在自己进程里处理。
            //
            //   $allowRetry = false → 跑在 FPM 请求进程里（没启用后台进程时的同步发货）。
            //       请求进程用完即弃，**不存在代码陈旧问题**，直接调钩子是安全的。
            //       而且这种方式不需要任何常驻进程 —— 落库反而会让通知没人消费（没订阅 worker）。
            //
            // 参数是**位置数组**，与订阅方原有的钩子签名一一对应，所以插件代码不用改。
            if ($allowRetry) {
                PluginEvent::publish('order_goods_delivery_queued_success', [$orderId, $orderGoodsId, $taskId, $task]);
            } else {
                try {
                    doAction('order_goods_delivery_queued_success', $orderId, $orderGoodsId, $taskId, $task);
                } catch (Throwable $hookErr) {
                    // 钩子失败不影响发货结果
                }
            }

            OrderModel::checkDeliveryComplete($orderId);
            return true;

        } catch (Throwable $e) {
            $attempts    = (int) $task['attempts'] + 1;
            $maxAttempts = (int) $task['max_attempts'];
            $isPermanent = $e instanceof PermanentDeliveryException;

            // FPM 同步模式（$allowRetry=false）没有「下次」，一律直接终态。
            $shouldGiveUp = !$allowRetry || $isPermanent || $attempts >= $maxAttempts;

            if ($shouldGiveUp) {
                Database::execute(
                    "UPDATE {$prefix}delivery_queue SET status='failed', last_error=? WHERE id=?",
                    [($isPermanent ? '[永久失败] ' : '') . $e->getMessage(), $taskId]
                );
                // FPM 同步模式下这条日志是「发货失败」的唯一主动信号
                // （异步模式下站长还能在后台看到 retry 行），别去掉。
                CliServer::log(
                    "发货失败，不再重试 >>> 任务 {$taskId} / 订单 {$orderId} >>> " . $e->getMessage()
                );
                self::markOrderDeliveryFailed($orderId);
            } else {
                $delay = (int) min(300, 30 * pow(2, $attempts - 1));
                Database::execute(
                    "UPDATE {$prefix}delivery_queue SET status='retry', last_error=?,
                     next_retry_at=DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id=?",
                    [$e->getMessage(), $delay, $taskId]
                );
            }

            return false;
        }
    }

    /**
     * 按核心内置的钩子约定执行一条任务：`goods_type_{类型}_order_paid`。
     *
     * 保留这个方法是因为两条路径还在用它：
     *   - FPM 同步发货（`drainOrderSync()`，没装任何发货 worker 时）
     *   - 兼容仍在用旧 `queue` worker 的部署
     *
     * 各商品类型插件自己的发货 worker 不走这里 —— 它们用 `runTask()` 直接注入
     * 自己那一段执行逻辑（见 DeliveryTaskWorker）。
     *
     * @param array<string, mixed> $task 已抢占的任务行
     * @return bool 是否发货成功
     */
    public static function execute(array $task, bool $allowRetry): bool
    {
        $goodsType = (string) ($task['goods_type'] ?? '');

        return self::runTask($task, static function (array $t) use ($goodsType): void {
            $payload = json_decode((string) ($t['payload'] ?? '{}'), true) ?: [];
            doAction(
                "goods_type_{$goodsType}_order_paid",
                (int) $t['order_id'],
                (int) $t['order_goods_id'],
                json_encode($payload)
            );
        }, $allowRetry);
    }

    /**
     * 回收「占了坑却没干完」的任务。
     *
     * 任务被抢占后就变成 processing，而 claimNext() 只取 pending/retry ——
     * 于是**消费进程在 processing 期间被杀（服务重启、崩溃、OOM），这条任务
     * 就永远卡死，再也没人会拾取**，买家付了钱却永远收不到货。
     *
     * 判据用 updated_at：它有 ON UPDATE CURRENT_TIMESTAMP，抢占的那条
     * UPDATE 会把它刷成当前时间，所以「processing 且 updated_at 很久没动」
     * 就等价于「消费者已经消失了」。
     *
     * 重置成 retry（而不是 failed）是因为中断多半是运维动作，不是业务错误；
     * 次数上限交给 execute() 判断 —— 它发现 attempts >= max_attempts 时
     * 会自动落到 failed，不需要这里额外处理。
     *
     * @param int $staleSeconds 多久没动静算中断。默认 300 秒，远大于单次发货耗时
     * @return int 被回收的任务数
     */
    public static function recoverStaleTasks(int $staleSeconds = 300): int
    {
        $staleSeconds = max(60, $staleSeconds);
        $prefix = Database::prefix();

        return (int) Database::execute(
            "UPDATE {$prefix}delivery_queue
             SET status='retry', next_retry_at=NOW(),
                 last_error=?
             WHERE status='processing'
               AND updated_at < DATE_SUB(NOW(), INTERVAL ? SECOND)",
            ['[系统] 上次消费被中断（进程重启或崩溃），已自动回收重试', $staleSeconds]
        );
    }

    /**
     * FPM 同步发货：对本单刚写入的队列行立即逐条执行，不重试。
     *
     * 只在「没有发货队列消费者在线」时被 OrderModel::triggerDelivery() 调用。
     * 只处理本单自己的行 —— 这不是机会式消费，不会去碰别的订单积压的任务。
     *
     * 同步模式下失败的订单会流转到 delivery_failed，由站长人工发货或退款；
     * 这是刻意的：FPM 部署没有常驻进程，没人能在退避时间到了之后重试。
     */
    public static function drainOrderSync(int $orderId): void
    {
        $prefix = Database::prefix();

        $rows = Database::query(
            "SELECT * FROM {$prefix}delivery_queue
             WHERE order_id = ? AND status IN ('pending','retry')
             ORDER BY id ASC",
            [$orderId]
        );

        foreach ($rows as $row) {
            $task = self::claim((int) $row['id'], $row);
            if ($task === null) {
                // 已被别的消费者抢走（并发下单/守护进程刚好在线），交给它
                continue;
            }
            self::execute($task, false);
        }
    }

    /**
     * 把订单标记为发货失败。
     *
     * changeStatus 会校验流转合法性：订单可能已经因为别的行成功而离开 delivering，
     * 也可能人工发货已经把它推到 delivered —— 这些情况直接跳过，不报错。
     */
    private static function markOrderDeliveryFailed(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        try {
            OrderModel::changeStatus($orderId, 'delivery_failed');
        } catch (Throwable $e) {
            // 状态已不是 delivering（或已是终态）时不强推，避免把正常订单改成失败
        }
    }
}
