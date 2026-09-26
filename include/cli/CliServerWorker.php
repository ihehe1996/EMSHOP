<?php

/**
 * CLI Worker（子进程）：按 type 找到插件注册的实现，循环调用它的 tick()。
 *
 * 仅由 CliServerManager 通过 proc_open 自动拉起，例如：
 *   php server worker --type=queue
 * 日常运维不要手动执行上述命令；宝塔只配 php server start 即可。
 *
 * **核心不再内置任何业务 worker**。有哪些 worker 由插件通过
 * `server_worker_types` 过滤器注册（见 ServerWorkerInterface）。
 * 没装这类插件时，`php server start` 会正常启动但没有任何业务子进程。
 *
 * 各 type 互不影响：发货卡住也不会影响商品同步。
 */
final class CliServerWorker
{
    /** 收到 SIGTERM 后置 true，循环下一轮退出 */
    private static $stopRequested = false;

    /**
     * 子进程入口。
     *
     * @param list<string> $argv
     * @return int 退出码
     */
    public static function run(array $argv): int
    {
        $type = self::parseType($argv);
        if ($type === '') {
            fwrite(STDERR, "请指定任务类型：--type=<类型>\n");
            return 1;
        }

        // init.php 开了 ob_start；子进程 stdout 又是管道 → echo 会堆在缓冲里，主进程看不到。
        // 这里关掉缓冲并强制刷新。
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        if (function_exists('ob_implicit_flush')) {
            ob_implicit_flush(1);
        }

        self::installSignalHandlers();

        if (defined('STDOUT') && is_resource(STDOUT)) {
            fflush(STDOUT);
        }

        // 找到该 type 的实现类。找不到说明插件没装/没启用/被停用 —— 记录清楚后退出，
        // 不要在这里死循环空转，否则日志会被刷爆。
        $def = self::resolveDefinition($type);
        if ($def === null) {
            $msg = "worker 退出：{$type} —— 没有任何插件注册这个任务类型，"
                 . '请确认提供该任务的插件已安装并启用（管家会在 5 秒内自动调整 worker）';
            CliServer::log($msg);
            fwrite(STDERR, $msg . "\n");
            return 1;
        }

        $className = (string) $def['class'];
        if (!class_exists($className)) {
            $msg = "worker 退出：{$type} —— 实现类不存在 {$className}";
            CliServer::log($msg);
            fwrite(STDERR, $msg . "\n");
            return 1;
        }

        /** @var ServerWorkerInterface $worker */
        // 把定义本身传给 worker：核心提供的通用 worker（如 DeliveryTaskWorker）
        // 需要从定义里读 claim / hook。
        //
        // 插件自己的 worker 类通常没有构造函数，多传一个参数 PHP 不会报错
        // （userland 函数允许多余实参），所以这是向后兼容的。
        $worker = new $className($def);
        if (!$worker instanceof ServerWorkerInterface) {
            $msg = "worker 退出：{$type} —— {$className} 未实现 ServerWorkerInterface";
            CliServer::log($msg);
            fwrite(STDERR, $msg . "\n");
            return 1;
        }

        $label = (string) ($def['label'] ?? $type);
        CliServer::log("worker 启动：{$type}（{$label}），PID " . getmypid());

        return self::runLoop($type, $worker);
    }

    /**
     * 通用 worker 循环。
     *
     * 每轮：tick() → 刷新自己的类型心跳 → 间隔 sleep。
     *
     * **只刷 $type 自己的心跳，一律不碰能力心跳。** 能力心跳
     * （content/server/worker.{capability}.heartbeat）由核心自有的 HeartbeatWorker
     * 独家维护：它是后台「运行模式」卡片和 OrderModel::triggerDelivery() 的判据，
     * 绑在业务执行流上的话，发货卡住多久、判据就跟着错多久。
     *
     * 类型心跳刻意放在 tick() **之后**：tick 卡死时它不刷新，
     * 上层据此就能把「这个 worker 卡住了」如实识别出来。
     */
    private static function runLoop(string $type, ServerWorkerInterface $worker): int
    {
        $interval = max(1, $worker->interval());

        // 定期重载后台配置。
        //
        // worker 是长驻进程，配置在启动时就快照进内存了 —— 后台改了 SMTP、限额之类的
        // 设置，不重启任务服务就永远不生效（管理员会以为「改了没用」）。
        // 限频到 15 秒一次：queue worker 每秒醒一次，每轮都读一次库没必要。
        $lastConfigReload = 0;

        while (!self::$stopRequested) {
            if (time() - $lastConfigReload >= 15) {
                try {
                    Config::reload();
                } catch (Throwable $e) {
                    CliServer::log("异常：{$type} 重载配置失败，" . $e->getMessage());
                }
                $lastConfigReload = time();
            }

            try {
                $worker->tick();
            } catch (Throwable $e) {
                CliServer::log("异常：{$type} 执行失败，" . $e->getMessage());
            }

            WorkerHeartbeat::touchWorker($type);

            self::idleSleep($interval, $type);
        }

        WorkerHeartbeat::clearAll([$type]);
        CliServer::log("worker 退出：{$type}，PID " . getmypid());
        return 0;
    }

    /**
     * 从插件注册表里找出 type 对应的定义。
     *
     * @return array{type: string, label: string, class: string}|null
     */
    private static function resolveDefinition(string $type): ?array
    {
        foreach (CliServer::workerDefinitions() as $def) {
            if ((string) ($def['type'] ?? '') === $type) {
                return $def;
            }
        }
        return null;
    }

    /**
     * 分段 sleep：每秒检查一次是否要停，避免 sleep(60) 卡太久才退出。
     *
     * 间隔比心跳 TTL 还长的 worker（例如订单超时 60 秒），必须在等待期间
     * 也定期刷一次心跳 —— 否则 WorkerHeartbeat 会在两次 tick 之间把它判成「已死」，
     * 后台首页就会一直误报「任务卡住」。（实测 60 秒周期的 worker 有 75% 的时间被判死。）
     *
     * 刷的仍然是「这个 worker 进程还在正常循环」这个信号：tick() 卡死时根本
     * 进不到这里，心跳照样会停，卡死依然能被发现。
     */
    private static function idleSleep(int $seconds, string $type = ''): void
    {
        $seconds = max(1, $seconds);

        // 只在「间隔明显长于 TTL」时才需要补刷，短周期 worker 本来每次 tick 都会刷
        $refreshEvery = ($seconds > WorkerHeartbeat::TTL && $type !== '') ? 10 : 0;

        for ($i = 1; $i <= $seconds; $i++) {
            if (self::$stopRequested) {
                return;
            }
            sleep(1);
            if ($refreshEvery > 0 && $i % $refreshEvery === 0) {
                WorkerHeartbeat::touchWorker($type);
            }
        }
    }

    /** 主进程 proc_terminate 发来的 SIGTERM 会走到这里 */
    private static function installSignalHandlers(): void
    {
        if (!function_exists('pcntl_async_signals') || !function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);
        $handler = static function (): void {
            self::$stopRequested = true;
        };
        pcntl_signal(SIGINT, $handler);
        pcntl_signal(SIGTERM, $handler);
    }

    /**
     * 从 argv 里取出 --type=xxx
     *
     * @param list<string> $argv
     */
    private static function parseType(array $argv): string
    {
        foreach (array_slice($argv, 2) as $arg) {
            if (!is_string($arg)) {
                continue;
            }
            if (strpos($arg, '--type=') === 0) {
                return trim(substr($arg, 7));
            }
        }
        return '';
    }

    private static function typeLabel(string $type): string
    {
        foreach (CliServer::workerDefinitions() as $def) {
            if ($def['type'] === $type) {
                return $def['label'];
            }
        }
        return $type;
    }
}
