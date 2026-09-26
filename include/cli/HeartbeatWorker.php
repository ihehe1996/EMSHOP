<?php

/**
 * 能力心跳维护 worker —— **核心自有，不由插件注册**。
 *
 * ## 为什么单独给它一个进程
 *
 * 这些心跳文件是「任务服务以什么形态在跑」的判据，后台首页的运行模式卡片在读它。
 * 它**绝不能和具体业务绑在同一条执行流里**：发货 worker 卡在一次上游请求上 30 秒，
 * 心跳就跟着失鲜 30 秒，判据随之翻转。所以交给一个只刷心跳、不干任何业务的独立进程：
 *
 *   - 不连数据库、不领队列、不跑插件钩子 → tick() 只 touch 几个文件，
 *     耗时以微秒计，任何业务 worker 都阻塞不到它；
 *   - 是这些文件的**唯一写入者** → 其他 worker 一律不再碰，不会互相污染。
 *
 * ## 语义
 *
 * **只要 `php server start` 这个常驻服务在跑，这里维护的心跳就是新鲜的**
 * —— 不按「有没有插件注册了对应的 worker」推导。也就是说这个心跳回答的是
 * 「服务在不在跑」，不是「某类活此刻有没有人消费」。
 *
 * 因此它不会因为某个业务 worker 卡住、崩溃、或者压根没装插件而失鲜。
 * 代价写在 `OrderModel::triggerDelivery()` 那边：它拿这个心跳判断发货走同步还是异步，
 * 所以「服务在跑」等价于「新订单按异步入队」。改这里之前先看那边。
 */
final class HeartbeatWorker implements ServerWorkerInterface
{
    /**
     * 两轮之间的间隔（秒）。
     *
     * 远小于 `WorkerHeartbeat::TTL`（15 秒），留足容错：即使某一轮被系统调度
     * 拖慢几秒，也不会越过 TTL 造成判据翻转。
     */
    private const INTERVAL = 3;

    /**
     * 由核心维护的能力心跳名。
     *
     * 写死在这里而不是从 worker 定义推导：这份清单表达的是「常驻服务能提供哪些能力」，
     * 是核心对自己形态的声明，不该取决于某个插件此刻有没有被启用。
     * 新增能力时在这里加一项，并同步 `WorkerHeartbeat` 里的常量。
     */
    private const CAPABILITIES = [
        WorkerHeartbeat::CAPABILITY_DELIVERY,
    ];

    /**
     * 核心维护的能力心跳名。
     *
     * 整站停机时要把这些文件一并清掉，否则 TTL 窗口内后台还显示「CLI 模式」。
     *
     * @return list<string>
     */
    public static function capabilities(): array
    {
        return self::CAPABILITIES;
    }

    public function interval(): int
    {
        return self::INTERVAL;
    }

    public function tick(): void
    {
        foreach (self::CAPABILITIES as $capability) {
            WorkerHeartbeat::touchWorker($capability);
        }
    }
}
