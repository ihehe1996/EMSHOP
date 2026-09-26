<?php

/**
 * 通用发货 worker —— **核心提供，插件不用自己写**。
 *
 * 插件只需要在 `server_worker_types` 里注册一条定义，说清楚两件事：
 *
 *   'claim' => ['goods_type' => 'virtual_card'],          // 我只领哪些类型的任务
 *   'hook'  => 'goods_type_virtual_card_order_paid',       // 领到之后执行哪个钩子
 *
 * 本类负责把这两件事串起来，并复用 DeliveryQueueService 的可靠性机制
 * （乐观锁抢占 / 指数退避重试 / 永久失败 / 僵尸回收 / 订单状态收敛）。
 *
 * ## 为什么每个商品类型要有自己的 worker
 *
 * 以前所有商品类型共用一个 queue worker：`claimNext()` 取最老的待处理任务、
 * 不分类型、同步执行。于是 **ycy_shared 打上游卡 30 秒，virtual_card 的卡密也发不出去**，
 * 而且更新任何一个商品类型插件都要重启那个共享进程。
 *
 * 拆开之后：每个类型只领自己的任务，慢的类型拖不住快的，插件更新也只重启自己的 worker。
 *
 * ## 隔离的前提
 *
 * `doAction($hook, ...)` 调的是**同一个插件**注册的钩子 —— 插件的代码只在这个
 * worker 自己的进程里执行。**插件的 worker 里不要 doAction 别人的钩子**，
 * 那会破坏这个隔离（别人的代码会住进你的进程）。
 */
final class DeliveryTaskWorker implements ServerWorkerInterface
{
    /** 两次领任务之间的间隔（秒） */
    private const CLAIM_INTERVAL = 2;

    /** 僵尸任务回收的间隔（秒） */
    private const RECOVER_INTERVAL = 60;

    /** @var array<string, mixed> 本 worker 在 server_worker_types 里的定义 */
    private $def;

    /** @var int 下次回收僵尸任务的时间戳 */
    private $nextRecoverAt = 0;

    /**
     * @param array<string, mixed> $def 由 CliServerWorker 从 worker 定义里传入
     */
    public function __construct(array $def = [])
    {
        $this->def = $def;
    }

    public function interval(): int
    {
        return self::CLAIM_INTERVAL;
    }

    public function tick(): void
    {
        $this->maybeRecoverStaleTasks();

        $claim = $this->def['claim'] ?? [];
        if (!is_array($claim)) {
            $claim = [];
        }

        try {
            $task = DeliveryQueueService::claimFor($claim);
        } catch (Throwable $e) {
            CliServer::log('异常：领取发货任务失败，' . $e->getMessage());
            return;
        }

        if ($task === null) {
            return; // 没有自己类型的任务，正常
        }

        $hook = trim((string) ($this->def['hook'] ?? ''));

        DeliveryQueueService::runTask($task, function (array $t) use ($hook): void {
            if ($hook === '') {
                // 插件注册有误（声明了 claim 却没给 hook）。
                // 用永久失败收尾，别让任务卡在 processing 里没人管。
                throw new PermanentDeliveryException(
                    '发货 worker 未配置 hook，请检查插件的 server_worker_types 注册'
                );
            }

            $payload = json_decode((string) ($t['payload'] ?? '{}'), true) ?: [];

            doAction(
                $hook,
                (int) $t['order_id'],
                (int) $t['order_goods_id'],
                json_encode($payload)
            );
        }, true);
    }

    /**
     * 回收被中断的任务（消费进程在 processing 期间被杀时留下的）。
     *
     * 放在这里是因为它属于「队列基础设施」—— 谁在用队列谁顺手清一下。
     * 操作是幂等的，多个发货 worker 各跑一遍不会互相影响。
     */
    private function maybeRecoverStaleTasks(): void
    {
        $now = time();
        if ($now < $this->nextRecoverAt) {
            return;
        }
        $this->nextRecoverAt = $now + self::RECOVER_INTERVAL;

        try {
            $recovered = DeliveryQueueService::recoverStaleTasks();
            if ($recovered > 0) {
                CliServer::log("发货队列：回收了 {$recovered} 条被中断的任务，已重新排队");
            }
        } catch (Throwable $e) {
            CliServer::log('异常：回收僵尸发货任务失败，' . $e->getMessage());
        }
    }
}
