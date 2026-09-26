<?php

/**
 * 通用事件订阅 worker —— **核心提供，插件不用自己写**。
 *
 * 插件只需要在 `server_worker_types` 里注册一条定义，说清楚订阅哪些事件：
 *
 *   'subscribe' => ['order_goods_delivery_queued_success'],
 *
 * 本类按游标取事件，然后用 `doAction($event, ...args)` 调起**订阅方自己的钩子**
 * —— 钩子签名不用改，跟它以前被通知方直接调用时一模一样。
 *
 * ## 为什么绕一圈落库
 *
 * 以前是"通知方在内存里直接调用订阅方的闭包"—— 那要求订阅方的代码
 * 存在于通知方的进程里，于是更新订阅方就得重启通知方的 worker。
 *
 * 现在订阅方的代码只在**它自己这个进程**里，通知方只写一行数据。
 *
 * ## 游标
 *
 * 游标由**订阅方自己维护**，核心不规定存哪。本类默认用 `Storage`
 * （插件配置存储，存在 em_options 表，跟插件的其它配置放一起）：
 *
 *   key = event_cursor_{事件名}，值 = 已处理到的事件 id
 *
 * 想换存储的插件可以自己写 worker 类，核心不拦。
 *
 * ## 语义：至少一次
 *
 * 处理完一批才更新游标，所以 worker 崩在中间会**重新处理**那批事件。
 * 订阅方的处理必须幂等 —— 重复发一条通知是可接受的代价。
 */
final class EventSubscriberWorker implements ServerWorkerInterface
{
    /** 订阅方的轮询周期（秒）。通知类场景几秒的延迟无所谓 */
    private const POLL_INTERVAL = 5;

    /** 一轮最多取多少条事件 */
    private const BATCH = 50;

    /** 事件清理的间隔（秒） */
    private const CLEANUP_INTERVAL = 3600;

    /** @var array<string, mixed> 本 worker 在 server_worker_types 里的定义 */
    private $def;

    /** @var int 下次清理过期事件的时间戳 */
    private $nextCleanupAt = 0;

    /**
     * @param array<string, mixed> $def 由 CliServerWorker 从 worker 定义里传入
     */
    public function __construct(array $def = [])
    {
        $this->def = $def;
    }

    public function interval(): int
    {
        return self::POLL_INTERVAL;
    }

    public function tick(): void
    {
        $this->maybeCleanup();

        $plugin = trim((string) ($this->def['plugin'] ?? ''));
        $events = $this->def['subscribe'] ?? [];
        if ($plugin === '' || !is_array($events) || $events === []) {
            return;
        }

        try {
            $storage = Storage::getInstance($plugin);
        } catch (Throwable $e) {
            CliServer::log('异常：事件订阅 worker 取不到插件存储，' . $e->getMessage());
            return;
        }

        foreach ($events as $event) {
            $event = trim((string) $event);
            if ($event === '') {
                continue;
            }

            $key = 'event_cursor_' . $event;
            $cursor = (int) $storage->getValue($key, 0);

            $rows = PluginEvent::since($event, $cursor, self::BATCH);
            if ($rows === []) {
                continue;
            }

            $last = $cursor;
            foreach ($rows as $row) {
                try {
                    // 调订阅方自己的钩子 —— 它的代码就在这个进程里
                    $this->dispatch($event, $row['args']);
                } catch (Throwable $e) {
                    // 单条失败不阻塞后面的，但要记下来；游标仍然前进，
                    // 所以这条事件不会重试（通知场景可接受，避免一条坏数据卡死整个订阅）
                    CliServer::log("异常：处理事件 {$event} #{$row['id']} 失败，" . $e->getMessage());
                }
                $last = (int) $row['id'];
            }

            if ($last !== $cursor) {
                $storage->setValue($key, $last);
            }
        }
    }

    /**
     * 把事件参数按位置展开，调起钩子。
     *
     * 用 call_user_func_array 而不是 doAction：参数个数是动态的
     * （取决于发布方当初怎么写的），而 doAction 只吃固定形参。
     *
     * 一个钩子可能被多个插件注册，逐个调。
     *
     * @param array<int, mixed> $args 位置参数数组
     */
    private function dispatch(string $event, array $args): void
    {
        // 只调**本插件自己**注册的回调。
        //
        // 此前用的是 Hooks::getCallbacks($event) —— 那是本进程内该事件的全部回调。
        // 每个订阅插件都有自己的 worker、各维护一份独立游标，于是同一事件有 N 个
        // 订阅方时，每个 worker 都会把 N 个回调全调一遍 ⇒ 每个插件被重复执行 N 次
        // （用户收到 N 份重复邮件/推送），也违背了「每个插件的代码只在自己进程里跑」。
        $plugin = trim((string) ($this->def['plugin'] ?? ''));
        $callbacks = $plugin !== ''
            ? Hooks::getCallbacksForPlugin($event, $plugin)
            : Hooks::getCallbacks($event);
        if ($callbacks === []) {
            return;
        }

        foreach ($callbacks as $fn) {
            call_user_func_array($fn, $args);
        }
    }

    /**
     * 清理过期事件。
     *
     * 放在订阅 worker 里是因为它是这个机制的日常维护 ——
     * 没有订阅者时事件表也不会有人写，自然不需要清理。
     */
    private function maybeCleanup(): void
    {
        $now = time();
        if ($now < $this->nextCleanupAt) {
            return;
        }
        $this->nextCleanupAt = $now + self::CLEANUP_INTERVAL;

        try {
            $deleted = PluginEvent::cleanup();
            if ($deleted > 0) {
                CliServer::log("插件事件：清理了 {$deleted} 条过期事件");
            }
        } catch (Throwable $e) {
            CliServer::log('异常：清理插件事件失败，' . $e->getMessage());
        }
    }
}
