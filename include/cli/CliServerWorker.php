<?php

/**
 * CLI Worker（子进程）：只干一类事，内部 while + sleep 循环。
 *
 * 仅由 CliServerManager 通过 proc_open 自动拉起，例如：
 *   php server worker --type=heartbeat|queue|order_poll|goods_sync
 * 日常运维不要手动执行上述命令；宝塔只配 php server start 即可。
 *
 * 各 type 互不影响：发货卡住也不会影响心跳刷新。
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
            fwrite(STDERR, "请指定任务类型：--type=heartbeat|queue|order_poll|goods_sync\n");
            return 1;
        }

        // init.php 开了 ob_start；子进程 stdout 又是管道 → echo 会堆在缓冲里，主进程看不到。
        // heartbeat 不加载 init，所以只有它会立刻出现启动日志。这里关掉缓冲并强制刷新。
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
        CliServer::log("worker 启动：{$type}，PID " . getmypid());

        // 按类型进入对应的死循环（直到被主进程终止）
        switch ($type) {
            case CliServer::WORKER_HEARTBEAT:
                return self::runHeartbeatWorker();
            case CliServer::WORKER_QUEUE:
                return self::runQueueWorker();
            case CliServer::WORKER_ORDER_POLL:
                return self::runOrderPollWorker();
            case CliServer::WORKER_GOODS_SYNC:
                return self::runGoodsSyncWorker();
            default:
                fwrite(STDERR, "未知任务类型：{$type}\n");
                return 1;
        }
    }

    /**
     * heartbeat：单独进程刷新心跳文件（约每 5 秒）
     * 后台首页靠 content/server/server.heartbeat 的 mtime 判断「运行中」
     */
    private static function runHeartbeatWorker(): int
    {
        @touch(CliServer::heartbeatFile());

        while (!self::$stopRequested) {
            @touch(CliServer::heartbeatFile());
            self::idleSleep(5);
        }

        CliServer::log('worker 退出：heartbeat，PID ' . getmypid());
        return 0;
    }

    /**
     * queue：发货队列 + 订单超时 + 版本重载检测
     */
    private static function runQueueWorker(): int
    {
        // 兼容旧插件钩子 swoole_worker_start；第二个参数原为 Server，现传 null
        try {
            doAction('swoole_worker_start', null, 0);
        } catch (Throwable $e) {
        }

        // 用「下次执行时间戳」模拟以前的 Timer::tick
        $nextQueueAt = 0;     // 每 2 秒：消费发货队列
        $nextTimeoutAt = 0;   // 每 60 秒：待支付订单超时
        $nextVersionAt = 0;   // 每 6 秒：检查是否需要 reload worker

        while (!self::$stopRequested) {
            $now = time();

            if ($now >= $nextQueueAt) {
                try {
                    CliServerTasks::processQueue();
                } catch (Throwable $e) {
                    CliServer::log('异常：发货队列 processQueue，' . $e->getMessage());
                }
                $nextQueueAt = time() + 2;
            }

            if ($now >= $nextTimeoutAt) {
                try {
                    CliServerTasks::runOrderTimeoutChecks();
                } catch (Throwable $e) {
                    CliServer::log('异常：订单超时检查 runOrderTimeoutChecks，' . $e->getMessage());
                }
                // 给插件用的分钟级调度钩子
                try {
                    doAction('server_schedule_tick');
                } catch (Throwable $e) {
                    CliServer::log('异常：server_schedule_tick，' . $e->getMessage());
                }
                $nextTimeoutAt = time() + 60;
            }

            if ($now >= $nextVersionAt) {
                try {
                    Config::reload();
                    // 若配置里 new 版本 > local 版本，会写 reload.flag，让主进程重启 worker
                    CliServerTasks::checkFileVersionAndRequestReload();
                } catch (Throwable $e) {
                    echo '代码热更新检查失败，' . $e->getMessage() . "\n";
                }
                $nextVersionAt = time() + 6;
            }

            self::idleSleep(1); // 每秒醒一次，既省 CPU，又能较快响应 stop
        }

        CliServer::log('worker 退出：queue，PID ' . getmypid());
        return 0;
    }

    /**
     * order_poll：订单轮询（默认 60 秒一次）
     */
    private static function runOrderPollWorker(): int
    {
        while (!self::$stopRequested) {
            try {
                CliServerTasks::runOrderPollingTasks();
            } catch (Throwable $e) {
                CliServer::log('异常：订单轮询 runOrderPollingTasks，' . $e->getMessage());
            }

            self::idleSleep(60);
        }

        CliServer::log('worker 退出：order_poll，PID ' . getmypid());
        return 0;
    }

    /**
     * goods_sync：商品同步（默认 6 秒一次）
     */
    private static function runGoodsSyncWorker(): int
    {
        while (!self::$stopRequested) {
            try {
                CliServerTasks::runGoodsSyncTasks();
            } catch (Throwable $e) {
                CliServer::log('异常：商品同步 runGoodsSyncTasks，' . $e->getMessage());
            }

            self::idleSleep(6);
        }

        CliServer::log('worker 退出：goods_sync，PID ' . getmypid());
        return 0;
    }

    /**
     * 分段 sleep：每秒检查一次是否要停，避免 sleep(60) 卡太久才退出。
     */
    private static function idleSleep(int $seconds): void
    {
        $seconds = max(1, $seconds);
        for ($i = 0; $i < $seconds; $i++) {
            if (self::$stopRequested) {
                return;
            }
            sleep(1);
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
