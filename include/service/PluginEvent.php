<?php

/**
 * 插件事件（跨插件通知的落库载体）。
 *
 * ## 为什么需要它
 *
 * 后台任务里的跨插件通知，原本是用 doAction() 在内存里直接调用订阅方的闭包。
 * 这要求**订阅方的代码必须存在于通知方的进程里** —— 于是：
 *
 *   - 更新订阅方插件 → 通知方进程里那份代码过期，必须重启通知方的 worker；
 *   - 而且因为算不清"谁订阅了谁"，只能全部 worker 一起重载。
 *
 * 改成事件落库后：
 *
 *   通知方：publish() 写一行，不调用任何人的代码
 *   订阅方：自己的 worker 按游标 since() 取走，在**自己进程里**处理
 *
 * 每个插件的代码只在自己的进程里跑 —— 更新/启停只影响它自己的 worker。
 *
 * ## 参数形态：位置参数数组
 *
 * `args` 存的是**位置参数数组**，与订阅方钩子的签名一一对应：
 *
 *   publish('order_goods_delivery_queued_success', [$orderId, $orderGoodsId, $taskId, $task]);
 *   // 订阅方的钩子签名保持不变：function ($orderId, $orderGoodsId, $taskId, $task)
 *
 * 这样**订阅方的代码一行都不用改**。
 *
 * ## 语义：至少一次
 *
 * 订阅方的游标由它自己维护（核心不规定存哪，建议用 Storage）。
 * worker 崩在"处理完但还没更新游标"之间时，那条事件会被**重新处理** ——
 * 所以订阅方的处理必须**幂等**（重复发一条通知是可接受的代价）。
 *
 * 事件按时间清理（默认保留 7 天）。游标落后于清理范围时，那些事件就永久错过了 ——
 * 订阅方如果在意，应该在启用时把游标跳到 latestId()。
 */
final class PluginEvent
{
    /** 清理时保留多少天的事件 */
    public const RETAIN_DAYS = 7;

    /**
     * 发布一个事件。
     *
     * @param string             $event 事件名（同时是订阅方注册的钩子名）
     * @param array<int, mixed>  $args  位置参数数组，顺序与订阅方钩子签名一致
     * @return int 事件 id（写失败返回 0）
     */
    public static function publish(string $event, array $args = []): int
    {
        $event = trim($event);
        if ($event === '') {
            return 0;
        }

        $json = json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            $json = '[]';
        }

        try {
            return (int) Database::insert('plugin_event', [
                'event'      => $event,
                'args'       => $json,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            // 发布失败不能拖垮主流程（比如发货）。订阅方会少收到一次通知，
            // 但发货本身必须成功 —— 这里记一条日志就够了。
            CliServer::log("异常：发布插件事件 {$event} 失败，" . $e->getMessage());
            return 0;
        }
    }

    /**
     * 取某个事件在游标之后的一批。
     *
     * @param string $event   事件名
     * @param int    $afterId 订阅方游标（只取 id > 它的）
     * @param int    $limit   一批最多取多少条
     * @return list<array{id: int, args: array<int, mixed>}>
     */
    public static function since(string $event, int $afterId, int $limit = 50): array
    {
        $event = trim($event);
        if ($event === '') {
            return [];
        }
        $limit = max(1, min(500, $limit));

        try {
            $rows = Database::query(
                'SELECT `id`, `args` FROM `' . Database::prefix() . 'plugin_event`
                 WHERE `event` = ? AND `id` > ?
                 ORDER BY `id` ASC LIMIT ?',
                [$event, max(0, $afterId), $limit]
            );
        } catch (Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $args = json_decode((string) ($row['args'] ?? '[]'), true);
            $out[] = [
                'id'   => (int) ($row['id'] ?? 0),
                'args' => is_array($args) ? array_values($args) : [],
            ];
        }

        return $out;
    }

    /**
     * 某个事件当前的最新 id（订阅方用它把游标跳到"现在"，跳过历史积压）。
     *
     * @param string $event 事件名；传空字符串则取全表最新
     */
    public static function latestId(string $event = ''): int
    {
        $prefix = Database::prefix();

        try {
            if (trim($event) === '') {
                $row = Database::fetchOne("SELECT COALESCE(MAX(`id`), 0) AS `m` FROM `{$prefix}plugin_event`");
            } else {
                $row = Database::fetchOne(
                    "SELECT COALESCE(MAX(`id`), 0) AS `m` FROM `{$prefix}plugin_event` WHERE `event` = ?",
                    [trim($event)]
                );
            }
        } catch (Throwable $e) {
            return 0;
        }

        return (int) ($row['m'] ?? 0);
    }

    /**
     * 清理过期事件。
     *
     * 订阅方游标落后于保留期时，那些事件就永久错过了 —— 这是有意为之：
     * 无限保留会让表持续膨胀，而"补发几天前的通知"通常没有意义。
     *
     * @return int 删除的行数
     */
    public static function cleanup(int $retainDays = self::RETAIN_DAYS): int
    {
        $retainDays = max(1, $retainDays);

        try {
            return (int) Database::execute(
                'DELETE FROM `' . Database::prefix() . 'plugin_event`
                 WHERE `created_at` < DATE_SUB(NOW(), INTERVAL ? DAY)',
                [$retainDays]
            );
        } catch (Throwable $e) {
            return 0;
        }
    }
}
