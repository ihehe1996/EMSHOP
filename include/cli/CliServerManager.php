<?php

/**
 * CLI 主进程（管家）
 *
 * 职责只有三件事：
 *   1. 按配置拉起多个 worker 子进程
 *   2. 子进程挂了就自动再拉起
 *   3. 收到停止/重载请求时，停掉或重启子进程
 *
 * 宝塔 Supervisor 配置示例：
 *   php /path/to/server start
 * （必须前台运行：本类 start 后不会主动退出，正好给 Supervisor 盯着）
 */
final class CliServerManager
{
    /** 收到 SIGTERM/SIGINT 或 stop 后置为 true，主循环据此退出 */
    private static $stopRequested = false;

    /** 收到 reload 标记 / SIGUSR1 后置为 true，主循环据此重启全部 worker */
    private static $reloadRequested = false;

    /** 周期检查间隔（秒）：代码热更新检测 + 插件变化检测。两者都很便宜，5 秒足够灵敏 */
    private const CHECK_INTERVAL = 5;

    /** 周期检查里「读配置失败」是否已告警过（避免每 5 秒刷一条重复日志） */
    private static $configReadWarned = false;

    /**
     * 主进程入口：根据命令分发。
     *
     * @param list<string> $argv
     * @return int 进程退出码（0=成功，非 0=失败；给 Supervisor 看）
     */
    public static function run(array $argv): int
    {
        CliServer::ensureRuntimeDir(); // 创建运行目录

        // 控制台编码适配（Windows 转 GBK）：必须在任何 echo 之前，
        // 否则宝塔的 Supervisor 会因为解不了 UTF-8 汉字而把服务判成「已停止」。
        CliServer::adaptConsoleEncoding();

        $command = strtolower(trim((string) ($argv[1] ?? 'start')));

        switch ($command) {
            case 'start':
                return self::cmdStart();
            case 'stop':
                return self::cmdStop();
            case 'status':
                return self::cmdStatus();
            case 'reload':
                return self::cmdReload();
            case 'restart':
                return self::cmdStart();
            case 'help':
            case '-h':
            case '--help':
                self::printHelp();
                return 0;
            default:
                echo "未知命令：{$command}\n";
                self::printHelp();
                return 1;
        }
    }

    

    // -------------------------------------------------------------------------
    // start：启动管家 + 拉起 worker + 进入监护循环（核心）
    // -------------------------------------------------------------------------

    private static function cmdStart(): int
    {
        // 0) 进程管理能力预检：缺任何一个函数都无法拉起/停止 worker。
        //
        //    必须提前失败退出，别起一个半死不活的进程 —— 那样后台会显示「运行中」，
        //    但 worker 起不来、stop 也停不掉，比直接报错难排查得多。
        //
        //    检测的是「实际能不能调用」而不是去解析 disable_functions 字符串：
        //    function_exists() 对禁用函数返回 false，且不受 php.ini 写法差异影响
        //    （有的环境写 shell_exec，有的写 shell_exec ，还可能带空格）。
        $missingFunctions = CliServer::missingProcessFunctions();

        if ($missingFunctions !== []) {
            $disabled = (string) ini_get('disable_functions');
            $missingNames = array_keys($missingFunctions);
            $msg = 'EMShop 任务服务依赖的 PHP 函数被禁用：' . implode('、', $missingNames);
            $fix = '请在 宝塔 - 软件商店 - PHP - 设置 - 禁用函数 中删除后重新启动';
            CliServer::log('启动失败：' . $msg . '；' . $fix);

            $c = self::consoleColors();

            // 先算出所有要打印的纯文本行（不含颜色码），据此定边框宽度
            // 用 × (U+00D7) 而不是 ✖ (U+2716)：后者 GBK 表达不了，
            // Windows 下转码会变成 "?"，反而更难认。
            $plain = ['  × 启动失败：' . $msg];
            foreach ($missingFunctions as $fn => $purpose) {
                $plain[] = '    · ' . $fn . '  —— ' . $purpose;
            }
            $plain[] = '';
            $plain[] = '  ' . $fix;
            if ($disabled !== '') {
                $plain[] = '  当前禁用函数：' . $disabled;
            }

            $width = 0;
            foreach ($plain as $l) {
                $width = max($width, function_exists('mb_strwidth') ? mb_strwidth($l) : strlen($l));
            }
            $border = str_repeat('=', $width);

            echo PHP_EOL;
            echo $c['red'] . $border . $c['reset'] . PHP_EOL;
            echo $c['bold'] . $c['red'] . '  × 启动失败：' . $c['reset'] . $msg . PHP_EOL;
            foreach ($missingFunctions as $fn => $purpose) {
                echo '    · ' . $c['yellow'] . $fn . $c['reset'] . '  —— ' . $purpose . PHP_EOL;
            }
            echo PHP_EOL;
            echo '  ' . $fix . PHP_EOL;
            if ($disabled !== '') {
                echo '  当前禁用函数：' . $c['yellow'] . $disabled . $c['reset'] . PHP_EOL;
            }
            echo $c['red'] . $border . $c['reset'] . PHP_EOL;
            echo PHP_EOL;
            return 1;
        }

        // 1) 已有实例在跑则先停旧再起新
        $runningPid = CliServer::readPid();
        if ($runningPid > 0 && CliServer::isProcessAlive($runningPid)) {
            echo "检测到 EMSHOP 任务服务（PID {$runningPid}）已在运行，正在停止旧进程…\n";
            CliServer::stopProcess($runningPid);
            if (!CliServer::waitForProcessExit($runningPid, 15)) {
                CliServer::log("启动失败：旧主进程 PID {$runningPid} 停止超时");
                echo "旧进程（PID {$runningPid}）未能及时退出，启动中止。\n";
                return 1;
            }
            CliServer::clearPid();
            echo "旧进程已停止，正在启动新实例…\n";
            CliServer::log("启动：已停止旧主进程 PID {$runningPid}，准备拉起新实例");
        }

        // 2) 启动前探一下数据库；连不上就失败退出，交给 Supervisor 稍后重试
        try {
            CliServer::probeMysql();
            CliServer::log('数据库连接成功，准备启动 CLI 任务服务');
        } catch (Throwable $e) {
            CliServer::log('启动失败：数据库未就绪，' . $e->getMessage());
            echo "数据库未就绪，退出进程，等待重试\n";
            sleep(6);
            return 1;
        }

        // 3) 升级包若放了空文件 `.server`（提示硬重启），启动时清掉
        $dotServer = EM_ROOT . '/.server';
        if (is_file($dotServer)) {
            @unlink($dotServer);
            CliServer::log('已移除升级标记文件 .server');
        }

        // 4) 写下主进程 PID，供 stop/status/reload 找到我们
        $masterPid = (int) getmypid();
        CliServer::writePid($masterPid);
        self::installSignalHandlers();

        // 5) 立即写下宿主心跳：管家自己就是「服务在跑」的标志。
        //    能力心跳（后台首页运行模式卡片读的那份）由核心自有的 HeartbeatWorker
        //    子进程维护，见该类注释。
        CliServer::touchHeartbeat();

        // 加载站点上下文：管家自己只需要读配置（判断插件是否变化），
        // worker 定义一律走探测子进程 —— 与运行中变更时同一条路径，行为必然一致。
        CliServer::loadSiteContext();

        $defs = self::probeDesiredWorkers();
        if ($defs === null) {
            // 探测失败：退回进程内直接读（管家刚启动，插件是新鲜的，这次读取可信）
            $defs = CliServer::workerDefinitions();
            CliServer::log('提示：worker 探测失败，本次退回进程内读取；运行中变更将依赖探测');
        }

        echo "========================================\n";
        echo "EMSHOP CLI 任务服务已启动！\n";
        echo "主进程 PID：{$masterPid}；PHP 版本：" . PHP_VERSION . "\n";
        echo "由本进程监护 " . count($defs) . " 个 工作任务子进程\n";
        echo "========================================\n";
        CliServer::log("启动：主进程 PID {$masterPid}，worker 数量 " . count($defs));

        // 没有任何插件注册业务 worker（只剩核心自有的 heartbeat）。
        // 不退出 —— Supervisor 会把它当成崩溃反复重拉，日志刷屏。
        // 这里保持驻留，让 Supervisor 认为服务「在运行」，并明确提示怎么启用。
        if (!self::hasBusinessWorkerDefs($defs)) {
            echo "\n";
            echo "提示：当前没有任何插件注册后台任务，本进程不会执行任何业务任务。\n";
            echo "      网站功能不受影响：订单发货会走 FPM 同步路径（失败不重试）。\n";
            echo "      需要异步发货/自动重试/订单超时关闭，请到后台应用商店启用对应插件。\n";
            echo "\n";
            CliServer::log('启动完成：无任何插件注册 worker，进入空转驻留模式');
        }

        // 6) 按定义逐个拉起子进程（每个都是：php server worker --type=xxx）
        /** @var array<string, array{proc: resource, type: string, label: string, pid: int, stdout: resource|null, stderr: resource|null, stdout_buf: string, stderr_buf: string}> $children */
        $children = [];

        foreach ($defs as $def) {
            $child = self::spawnWorker($def['type'], $def['label'], (string) ($def['capability'] ?? ''));
            if ($child === null) {
                echo "worker {$def['type']} 启动失败\n";
                CliServer::log("异常：worker {$def['type']} 启动失败");
                continue;
            }
            $children[$def['type']] = $child;
            echo "已启动 worker {$def['type']}（{$def['label']}），PID {$child['pid']}\n";
            CliServer::log("worker 已拉起：{$def['type']}，PID {$child['pid']}");
        }

        // 一个子进程都没拉起来。期望集合里还有业务 worker 时说明是 proc_open 之外的
        // 运行时问题 → 退出交给 Supervisor 重试；只有核心 worker 时交给主循环空转驻留
        //（$defs 永远非空，不能再用 $defs !== [] 判断）。
        if ($children === [] && self::hasBusinessWorkerDefs($defs)) {
            echo "没有 worker 启动成功，主进程退出。\n";
            CliServer::log('启动失败：worker 定义存在但全部拉起失败');
            WorkerHeartbeat::clearAll(self::heartbeatKeysOf($defs));
            CliServer::clearPid();
            return 1;
        }

        self::saveWorkersState($children);

        // 变更检测基准：主站启用插件名单（便宜，只读配置）。
        // 名单一变才去跑探测子进程 —— 不然每 5 秒起一个进程太重。
        $enabledAtLastProbe = CliServer::enabledPluginsSignature();
        $nextCheckAt = time() + self::CHECK_INTERVAL;

        // 7) 主循环：只要没要求停止，就一直待在这里（所以 Supervisor 觉得服务「在运行」）
        while (!self::$stopRequested) {
            // 7) 先把上一轮攒下的控制台输出吐出去并转码。
            //    Windows 下控制台输出走的是带回调的输出缓冲（转 GBK），
            //    而回调缓冲不会自动 flush —— 不显式刷就会一直攒着看不到。
            CliServer::flushConsole();

            // 7a) 刷新宿主心跳（管家活着 = 服务在跑）
            CliServer::touchHeartbeat();

            // 7a-2) 每 5 秒一档的两件事：代码热更新检测、插件变化 → 增删 worker。
            if (time() >= $nextCheckAt) {
                $nextCheckAt = time() + self::CHECK_INTERVAL;

                try {
                    // **必须 reload**：管家是常驻进程，不清缓存就会一直读启动时的快照，
                    // 站长在后台做的任何改动都看不见。
                    Config::reload();

                    self::checkFileVersionAndReload();
                    self::checkPluginReloadRequests($children, $defs);

                    $enabledNow = CliServer::enabledPluginsSignature();
                    if ($enabledNow !== $enabledAtLastProbe) {
                        $enabledAtLastProbe = $enabledNow;
                        $desired = self::probeDesiredWorkers();
                        if ($desired === null) {
                            // 探测失败：保持现状，下一轮再试。绝不能因为读不到就把 worker 全停了。
                            CliServer::log('插件变化探测失败，本轮不调整 worker，稍后重试');
                        } else {
                            self::applyWorkerSet($children, $desired);
                            // **必须同步更新「期望集合」**：$defs 下面要用来判断
                            // 「是该空转驻留，还是该退出」。不同步的话，站长把最后一个
                            // worker 插件停用时，$defs 还是启动时的非空值 ——
                            // 管家会误判成「worker 全都拉不起来」直接退出，
                            // 而正确行为是停掉 worker 后空转驻留。
                            $defs = $desired;
                        }
                    }

                    self::$configReadWarned = false;
                } catch (Throwable $e) {
                    // 数据库抖动导致读不到配置 —— **绝不能当成「插件全被停用」**，
                    // 否则一次网络抖动就会把正在干活的 worker 全停掉。跳过本轮即可。
                    //
                    // 只在故障开始时记一条：这个检查每 5 秒跑一次，数据库挂一小时
                    // 就是 720 行重复日志，会把真正有用的信息淹掉。
                    if (!self::$configReadWarned) {
                        self::$configReadWarned = true;
                        CliServer::log('周期检查：读取配置失败，本轮跳过：' . $e->getMessage());
                    }
                }
            }

            // 7b) 有人写了 reload.flag（或发了 SIGUSR1）→ 重启全部 worker，主进程自己不退
            if (CliServer::consumeReloadRequest()) {
                self::$reloadRequested = true;
            }

            if (self::$reloadRequested) {
                self::$reloadRequested = false;
                echo "收到 reload，正在重启全部 worker…\n";
                CliServer::log('重载：主进程开始重启全部 worker');
                self::stopAllWorkers($children);
                $children = [];

                // **重载前必须重新探测**，不能用 CliServer::workerDefinitions() ——
                // 那是进程内缓存，永远反映不了插件的新增/移除。插件更新（bump 版本号）
                // 触发的重载正是"插件代码变了"的场合，此时新注册的 worker 就在旧缓存里
                // 找不到，于是永远不会被拉起。
                $freshDefs = self::probeDesiredWorkers();
                if ($freshDefs !== null) {
                    $defs = $freshDefs;
                    $enabledAtLastProbe = CliServer::enabledPluginsSignature();
                } else {
                    CliServer::log('重载：探测 worker 失败，沿用当前列表');
                }

                foreach ($defs as $def) {
                    $child = self::spawnWorker($def['type'], $def['label'], (string) ($def['capability'] ?? ''));
                    if ($child === null) {
                        echo "worker {$def['type']} 重载启动失败\n";
                        continue;
                    }
                    $children[$def['type']] = $child;
                    echo "已重载 worker {$def['type']}，PID {$child['pid']}\n";
                    CliServer::log("worker 已重载：{$def['type']}，PID {$child['pid']}");
                }
                self::saveWorkersState($children);
            }

            // 7c) 把子进程的 echo 转发到主进程终端（宝塔日志里能看到 [queue] xxx）
            self::relayChildrenOutput($children);

            // 7d) 发现某个 worker 死了 → 自动再拉起
            self::reapAndRespawn($children, $defs);
            self::saveWorkersState($children);

            // 期望有 worker 却一个都没跑起来，分两种情况。
            // 注意 $defs 永远非空（核心自有的 heartbeat 一直在），所以要按「有没有插件
            // 注册的业务 worker」来区分，不能再看 $defs === []。
            if ($children === []) {
                // 只有核心自有的 worker：这个站本来就不需要后台业务任务，空转驻留。
                // 不退进程 —— 退了 Supervisor 会反复重拉、日志刷屏，后台首页也会把服务显示成异常。
                if (!self::hasBusinessWorkerDefs($defs)) {
                    usleep(200000);
                    continue;
                }
                // 期望有业务 worker 却一个都没起来 → 拉起环节出了问题，退出交给 Supervisor 重试
                echo "期望的 worker 一个都没能启动，主进程退出（交给 Supervisor 重试）。\n";
                break;
            }

            // 7d) 最多等 1 秒：有子进程输出就立刻醒，没有就超时继续下一轮循环
            $read = [];
            foreach ($children as $child) {
                if (is_resource($child['stdout'])) {
                    $read[] = $child['stdout'];
                }
                if (is_resource($child['stderr'])) {
                    $read[] = $child['stderr'];
                }
            }

            if ($read === []) {
                usleep(200000);
                continue;
            }

            $write = null;
            $except = null;
            @stream_select($read, $write, $except, 1);
        }

        // 8) 离开主循环 = 要停服：先杀光 worker，再清 PID 与心跳
        echo "正在停止全部 worker…\n";
        self::stopAllWorkers($children);
        CliServer::clearPid();
        @unlink(CliServer::workersStateFile());
        // 清掉宿主心跳、每个 worker 的类型心跳，以及所有能力心跳：进程已经不在了，
        // 留着文件会让后台首页在 TTL 窗口内继续显示「运行中」，也会让发货继续走异步路径。
        WorkerHeartbeat::clearAll(self::heartbeatKeysOf($defs));
        @unlink(CliServer::heartbeatFile());
        CliServer::log('关闭：CLI 主进程已退出');
        echo "EMSHOP CLI 任务服务已停止。\n";

        return 0;
    }

    /**
     * 插件级重载：某个插件的代码变了，**只重启它自己的 worker**。
     *
     * 跟 checkFileVersionAndReload()（全局信号 → 重启全部）的区别就在粒度上。
     * 插件更新走这条，核心升级走那条。
     *
     * 之所以能这么精确：这轮改造之后，插件的代码只在它自己的 worker 进程里跑
     * （发货按商品类型拆了 worker，通知改走事件由订阅方自己消费），
     * 所以更新一个插件不影响任何别的 worker。
     *
     * @param array<string, array<string, mixed>> $children
     * @param list<array<string, mixed>>           $defs
     */
    private static function checkPluginReloadRequests(array &$children, array $defs): void
    {
        if ($children === []) {
            return;
        }

        // worker 定义里的 plugin 字段是归属插件；按它归组
        $byPlugin = [];
        foreach ($defs as $def) {
            $slug = trim((string) ($def['plugin'] ?? ''));
            if ($slug === '' || (string) ($def['type'] ?? '') === '') {
                continue;
            }
            $byPlugin[$slug][] = $def;
        }
        if ($byPlugin === []) {
            return; // 没有插件声明归属，无从判断该重启谁
        }

        foreach ($byPlugin as $slug => $pluginDefs) {
            try {
                if ((string) Config::get(CliServer::pluginReloadKey($slug), '') === '') {
                    continue;
                }
                // 先清标记：即使下面重启失败，也不会下一轮再触发一遍
                Config::set(CliServer::pluginReloadKey($slug), '');
            } catch (Throwable $e) {
                continue; // 读不到配置就跳过，下一轮再说
            }

            foreach ($pluginDefs as $def) {
                $type = (string) $def['type'];
                if (!isset($children[$type])) {
                    continue; // 没在跑的（比如插件刚被停用）不用管
                }

                echo "插件更新：重启 worker {$type}\n";
                CliServer::log("插件更新：重启 worker {$type}（插件 {$slug}）");

                self::stopOneWorker($children, $type);
                $child = self::spawnWorker($type, (string) ($def['label'] ?? $type), (string) ($def['capability'] ?? ''));
                if ($child === null) {
                    CliServer::log("插件更新：worker {$type} 重启失败");
                    continue;
                }
                $children[$type] = $child;
            }

            self::saveWorkersState($children);
        }
    }

    /**
     * 起一个短命子进程去问「现在该跑哪些 worker」，读它写的结果文件。
     *
     * 为什么不能在本进程里算：管家已经加载过插件，而 PHP 无法卸载已加载的代码
     * （Hooks 没有 remove、init.php 有 EM_INITIALIZED 守卫），新启用的插件也加载不进来。
     * 只有全新进程拿到的注册结果才是准的。
     *
     * @return list<array{type: string, label: string, class: string}>|null 失败返回 null
     */
    private static function probeDesiredWorkers(): ?array
    {
        $defsFile = CliServer::workerDefsFile();
        @unlink($defsFile); // 先删掉旧结果，避免把上一轮的文件当成这一轮的

        $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $proc = @proc_open(
            [CliServer::phpBinary(), EM_ROOT . DIRECTORY_SEPARATOR . 'server', 'probe-workers'],
            [
                0 => ['file', $nullDevice, 'r'],
                1 => ['file', $nullDevice, 'w'],                              // 结果走文件，stdout 丢弃
                2 => ['file', self::workerLogFile('probe', 'err'), 'a'],
            ],
            $pipes,
            EM_ROOT
        );
        if (!is_resource($proc)) {
            return null;
        }

        // 等它退出，最多 10 秒（正常一两百毫秒）
        $deadline = time() + 10;
        $stillRunning = true;
        while (time() < $deadline) {
            $status = CliServer::procCall('proc_get_status', static function () use ($proc) {
                return proc_get_status($proc);
            }, null);
            if (!is_array($status) || empty($status['running'])) {
                $stillRunning = false;
                break;
            }
            usleep(100000);
        }
        if ($stillRunning) {
            // 卡住了就杀掉 —— 否则下面的 proc_close 会一直等它，把管家自己冻住
            CliServer::procCall('proc_terminate', static function () use ($proc) {
                return @proc_terminate($proc, 9);
            });
        }
        CliServer::procCall('proc_close', static function () use ($proc) {
            return @proc_close($proc);
        });

        if (!is_file($defsFile)) {
            return null;
        }
        $decoded = json_decode((string) @file_get_contents($defsFile), true);
        if (!is_array($decoded)) {
            return null;
        }

        $out = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = (string) ($item['type'] ?? '');
            // 与 CliServer::workerDefinitions() 同一套校验：type 会进 --type= 和心跳文件名
            if ($type === '' || !preg_match('/^[a-z0-9_]+$/i', $type) || isset($out[$type])) {
                continue;
            }
            $out[$type] = [
                'type'   => $type,
                'label'  => trim((string) ($item['label'] ?? '')) !== '' ? (string) $item['label'] : $type,
                'class'  => (string) ($item['class'] ?? ''),
                // 归属插件：插件级重载靠它归组
                'plugin' => trim((string) ($item['plugin'] ?? '')),
                // 能力名：spawn 时传给子进程，停它时好连能力心跳一起清
                'capability' => trim((string) ($item['capability'] ?? '')),
            ];
        }

        return array_values($out);
    }

    /**
     * 把当前在跑的 worker 调整成 $desired：缺的拉起、多的停掉、不变的碰都不碰。
     *
     * **只动差异部分**，这是这个方案的核心价值 —— 启停一个邮件通知插件
     * 不会碰到发货队列 worker，正在进行的发货不会被无谓打断。
     *
     * @param array<string, array<string, mixed>> $children
     * @param list<array{type: string, label: string, class: string}> $desired
     */
    private static function applyWorkerSet(array &$children, array $desired): void
    {
        $desiredByType = [];
        foreach ($desired as $def) {
            $desiredByType[(string) $def['type']] = $def;
        }

        // 1) 多余的停掉
        foreach (array_keys($children) as $type) {
            if (isset($desiredByType[$type])) {
                continue;
            }
            echo "已停止 worker {$type}  / (动态监控)\n";
            CliServer::log("已停止 worker {$type} / (动态监控)");
            self::stopOneWorker($children, $type);
        }

        // 2) 缺的拉起
        foreach ($desiredByType as $type => $def) {
            if (isset($children[$type])) {
                continue; // 已在跑，不动它
            }
            $child = self::spawnWorker($type, (string) $def['label'], (string) ($def['capability'] ?? ''));
            if ($child === null) {
                echo "插件变化：worker {$type} 启动失败\n";
                CliServer::log("插件变化：worker {$type} 启动失败");
                continue;
            }
            $children[$type] = $child;
            echo "已启动 worker {$type}（{$def['label']}），PID {$child['pid']} / (动态监控)\n";
            CliServer::log("已启动 worker {$type}，PID {$child['pid']} / (动态监控)");
        }

        self::saveWorkersState($children);
    }

    /**
     * 期望集合里有没有「插件注册的业务 worker」。
     *
     * 核心自有的 worker（plugin 为空）不算 —— 它们一直存在，不能用来判断
     * 「这个站到底需不需要后台任务」。
     *
     * @param array<string, array<string, mixed>> $defs
     */
    private static function hasBusinessWorkerDefs(array $defs): bool
    {
        foreach ($defs as $def) {
            if (trim((string) ($def['plugin'] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * 整站停机时要清掉的心跳文件键名：每个 worker 的 type，加上核心维护的能力心跳名。
     *
     * 单独一个 worker 退出时只清新自己的 type（见 stopOneWorker）—— 能力心跳是
     * 核心的 HeartbeatWorker 在维护的，只有整个服务不在了才该连它一起清。
     * 漏清能力心跳的后果：TTL 窗口内后台还显示「CLI 模式」，发货继续按异步处理，
     * 而消费进程已经没了。
     *
     * @param array<string, array<string, mixed>> $defs
     * @return list<string>
     */
    private static function heartbeatKeysOf(array $defs): array
    {
        $keys = [];
        foreach ($defs as $def) {
            $keys[] = (string) $def['type'];
        }
        return array_merge($keys, HeartbeatWorker::capabilities());
    }

    /**
     * 停掉单个 worker（不复用 stopAllWorkers 是因为后者会把整张表清空）。
     *
     * @param array<string, array<string, mixed>> $children
     */
    private static function stopOneWorker(array &$children, string $type): void
    {
        if (!isset($children[$type])) {
            return;
        }

        self::terminateWorker($children[$type]);
        usleep(400000); // 给子进程一点时间自己收尾
        self::finishStoppedWorker($children[$type]);
        unset($children[$type]);

        // 立刻清掉它自己的类型心跳。不清的话，TTL 窗口（15 秒）内 isAlive() 还是 true，
        // 后台首页会显示「运行中」。子进程正常退出时会自己清，被 SIGKILL 时轮不到它，这里补一刀。
        //
        // **不碰能力心跳**：它是同一能力下所有 worker 共用的（四种发货 worker 都提供
        // delivery），停掉其中一个时别的还在干活；而且它现在由核心的 HeartbeatWorker
        // 独家维护，停一个业务 worker 不该去动它。
        WorkerHeartbeat::clearAll([$type]);
    }

    /**
     * 检查代码/插件文件版本，需要时写 reload.flag 让主循环重启全部 worker。
     *
     * 原本在 queue worker 里每 6 秒跑一次；挪到管家后有两个好处：
     *   1. 没有任何 worker 时（插件全停用）也能感知到升级；
     *   2. 不必为了它单独保留一个 worker。
     */
    private static function checkFileVersionAndReload(): void
    {
        try {
            $applied = CliServer::getAppliedFileVersion();
            $pending = CliServer::getPendingFileVersion();
            if ($pending === '' || !@version_compare($pending, $applied, '>')) {
                return;
            }

            CliServer::log("重载：检测到文件版本升级（自 {$applied} 变更为 {$pending}），正在通知主进程重启 worker");
            if (!CliServer::requestReload()) {
                CliServer::log("异常：文件版本升级后写入重载标记失败（自 {$applied} 变更为 {$pending}）");
                return;
            }
            // applied + 旧 key 双写，过渡期内任一侧 bump 都能对齐
            CliServer::setAppliedFileVersion($pending);
        } catch (Throwable $e) {
            CliServer::log('代码热更新检查失败，' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // stop / status / reload（短命令，执行完就结束）
    // -------------------------------------------------------------------------

    /** 向正在运行的主进程发停止信号；主进程自己会收尾并退出 */
    private static function cmdStop(): int
    {
        $pid = CliServer::readPid();
        if ($pid > 0 && CliServer::isProcessAlive($pid)) {
            CliServer::stopProcess($pid);
            CliServer::log("停止：已向主进程发送退出信号（PID {$pid}）");
            echo "已向主进程发送停止信号（PID {$pid}），服务即将退出。\n";

            // 等它真的退出，再清理残留的运行文件。
            //
            // Linux 下主进程收到 SIGTERM 会自己收尾（杀 worker、清 PID、删 workers.json），
            // 这段只是幂等的保险；但 **Windows 下 stopProcess 走的是 `taskkill /F`，
            // 主进程被强杀，收尾代码根本轮不到执行** —— 没有这段，
            // server.pid 和 workers.json 会一直留着死进程的信息。
            if (CliServer::waitForProcessExit($pid, 10)) {
                CliServer::clearPid();
                @unlink(CliServer::workersStateFile());
                echo "服务已停止。\n";
            } else {
                echo "进程未在 10 秒内退出，请稍后再确认；如仍残留可手动结束 PID {$pid}。\n";
            }
            return 0;
        }

        CliServer::log('停止：当前没有正在运行的主进程');
        echo "当前没有正在运行的任务服务。\n";
        CliServer::clearPid();
        @unlink(CliServer::workersStateFile());
        return 0;
    }

    private static function cmdStatus(): int
    {
        $pid = CliServer::readPid();
        if ($pid > 0 && CliServer::isProcessAlive($pid)) {
            $hostAge = WorkerHeartbeat::hostAge();
            $hostDesc = $hostAge === null ? '心跳缺失' : "心跳 {$hostAge}s 前";
            echo "任务服务正在运行（主进程 PID：{$pid}，{$hostDesc}）\n";

            $state = self::loadWorkersState();
            $workers = (is_array($state) && !empty($state['workers']) && is_array($state['workers']))
                ? $state['workers'] : [];

            if ($workers === []) {
                echo "  （没有任何插件注册后台任务，服务处于空转驻留状态）\n";
                return 0;
            }

            foreach ($workers as $row) {
                $type = (string) ($row['type'] ?? '');
                $wPid = (int) ($row['pid'] ?? 0);
                $alive = $wPid > 0 && CliServer::isProcessAlive($wPid) ? '运行中' : '已退出';
                // 能力心跳：进程在 ≠ 任务在跑（可能卡死在一次上游请求里）
                $hbAge = WorkerHeartbeat::age($type);
                $hbDesc = $hbAge === null ? '心跳缺失' : ($hbAge <= WorkerHeartbeat::TTL ? "心跳 {$hbAge}s 前" : "心跳停滞 {$hbAge}s");
                echo "  worker {$type}  PID {$wPid}  {$alive}  {$hbDesc}\n";
            }
            return 0;
        }

        echo "任务服务未在运行。\n";
        return 0;
    }

    /** 不杀主进程，只让主进程重启全部 worker（读新代码） */
    private static function cmdReload(): int
    {
        $pid = CliServer::readPid();
        if ($pid > 0 && CliServer::isProcessAlive($pid)) {
            if (!CliServer::requestReload()) {
                echo "写入重载标记失败。\n";
                return 1;
            }
            // Linux 下额外发 SIGUSR1，主进程可立刻响应；没有 pcntl 时仍靠 reload.flag 轮询
            if (PHP_OS_FAMILY !== 'Windows' && function_exists('posix_kill') && defined('SIGUSR1')) {
                @posix_kill($pid, SIGUSR1);
            }
            CliServer::log("重载：已请求主进程重启 worker（PID {$pid}）");
            echo "已请求重载（PID {$pid}）。\n";
            return 0;
        }

        CliServer::log('重载失败：没有正在运行的主进程，无法重载');
        echo "服务未在运行，无法重载。\n";
        return 1;
    }

    // -------------------------------------------------------------------------
    // 子进程：拉起 / 输出转发 / 回收拉起 / 全部停止
    // -------------------------------------------------------------------------

    /**
     * 再开一个 PHP 进程：php server worker --type=xxx
     *
     * @return array{proc: resource, type: string, label: string, pid: int, stdout: resource|null, stderr: resource|null, stdout_buf: string, stderr_buf: string}|null
     */
    /**
     * 子进程输出落地的文件（仅 Windows 用文件重定向时）。
     *
     * 放在 runtime 目录下：worker 的 stdout/stderr 主要用来抓 PHP 致命错误，
     * 子进程自己的业务日志走 CliServer::log() 进 server.log。
     */
    private static function workerLogFile(string $type, string $suffix): string
    {
        CliServer::ensureRuntimeDir();
        $safe = preg_replace('/[^A-Za-z0-9_]/', '', $type);
        return CliServer::runtimeDir() . '/worker.' . ($safe === '' ? 'unknown' : $safe) . '.' . $suffix . '.log';
    }

    private static function spawnWorker(string $type, string $label, string $capability = ''): ?array
    {
        // 先确认 proc_* 齐全再 proc_open。
        // 拉起来却拿不到 PID 的话，这个子进程就没人监护、没人回收，
        // 会变成一个孤儿 worker 一直跑下去 —— 宁可不拉。
        $missingProc = CliServer::missingProcFunctions();
        if ($missingProc !== []) {
            CliServer::log(
                "拉起 worker {$type} 失败：缺少函数 " . implode('、', array_keys($missingProc))
            );
            return null;
        }

        $cmd = [
            CliServer::phpBinary(),
            EM_ROOT . DIRECTORY_SEPARATOR . 'server',
            'worker',
            '--type=' . $type,
        ];

        // 子进程输出怎么接。
        //
        // **Windows 必须用文件重定向，不能用管道。** Windows 上 proc_open 的管道
        // 不理会 stream_set_blocking(false)：`stream_get_meta_data()['blocked']`
        // 仍是 true，fread 会一直阻塞到子进程写出数据或退出为止
        // （实测挂起 8 秒，等于卡死）。管家一旦卡在读管道上，整个监护循环就停了 ——
        // reload.flag、信号、插件启停检测全部失效，而子进程还在跑，表面看不出问题。
        //
        // stream_select 也救不了：Windows 上它对管道恒返回「可读」（假阳性），
        // 无数据时照样 fread 阻塞。
        //
        // 用文件重定向后没有管道可读，循环自然走「无子进程输出」那条路，
        // 每 0.2 秒转一圈。子进程自己的日志本来就是走 CliServer::log() 进
        // server.log 的，PHP 致命错误则落到 worker.*.log，信息不丢。
        $usePipe = (PHP_OS_FAMILY !== 'Windows');
        if ($usePipe) {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
        } else {
            $nullDevice = 'NUL';
            $descriptors = [
                0 => ['file', $nullDevice, 'r'],
                1 => ['file', self::workerLogFile($type, 'out'), 'a'],
                2 => ['file', self::workerLogFile($type, 'err'), 'a'],
            ];
        }

        $proc = @proc_open($cmd, $descriptors, $pipes, EM_ROOT);

        if (!is_resource($proc)) {
            return null;
        }

        $stdout = null;
        $stderr = null;
        if ($usePipe) {
            fclose($pipes[0]); // 不需要给子进程写输入
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $stdout = $pipes[1];
            $stderr = $pipes[2];
        }

        $status = CliServer::procCall('proc_get_status', static function () use ($proc) {
            return proc_get_status($proc);
        }, null);

        return [
            'proc' => $proc,
            'type' => $type,
            'label' => $label,
            'pid' => is_array($status) ? (int) ($status['pid'] ?? 0) : 0,
            // 本 worker 提供的能力名（可为空）。停它时要连能力心跳一起清，
            // 否则 TTL 窗口内核心会误以为「还有人在发货」。
            'capability' => $capability,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'stdout_buf' => '',
            'stderr_buf' => '',
        ];
    }

    /**
     * 把每个 worker 的输出贴上 [queue] 之类前缀，打到主进程 stdout。
     *
     * @param array<string, array{proc: resource, type: string, label: string, pid: int, stdout: resource|null, stderr: resource|null, stdout_buf: string, stderr_buf: string}> $children
     */
    private static function relayChildrenOutput(array &$children): void
    {
        foreach ($children as $type => $child) {
            if (is_resource($child['stdout'])) {
                self::relayStream($child['stdout'], $type, false, $children[$type]['stdout_buf']);
            }
            if (is_resource($child['stderr'])) {
                self::relayStream($child['stderr'], $type, true, $children[$type]['stderr_buf']);
            }
        }
    }

    /**
     * @param resource $stream
     */
    private static function relayStream($stream, string $tag, bool $isStderr, string &$buffer, bool $flush = false): void
    {
        while (($chunk = fread($stream, 8192)) !== false && $chunk !== '') {
            $buffer .= str_replace(["\r\n", "\r"], "\n", $chunk);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                if ($line === '') {
                    continue;
                }
                $prefix = $isStderr ? "[{$tag}][stderr] " : "[{$tag}] ";
                echo $prefix . $line . PHP_EOL;
            }
        }

        if ($flush && $buffer !== '') {
            $prefix = $isStderr ? "[{$tag}][stderr] " : "[{$tag}] ";
            echo $prefix . $buffer . PHP_EOL;
            $buffer = '';
        }
    }

    /**
     * 检查子进程是否还活着；死了就关掉管道并（在非 stop/reload 时）重新拉起。
     *
     * @param array<string, array{proc: resource, type: string, label: string, pid: int, stdout: resource|null, stderr: resource|null, stdout_buf: string, stderr_buf: string}> $children
     */
    private static function reapAndRespawn(array &$children, array $desired): void
    {
        // 期望集合（type => def）：决定一个死掉的 worker 该不该被重新拉起
        $desiredByType = [];
        foreach ($desired as $def) {
            $desiredByType[(string) $def['type']] = $def;
        }

        foreach ($children as $type => $child) {
            if (!is_resource($child['proc'])) {
                unset($children[$type]);
                continue;
            }

            $status = CliServer::procCall('proc_get_status', static function () use ($child) {
                return proc_get_status($child['proc']);
            }, null);
            if (!is_array($status)) {
                // 探测不可用：保守当作「还活着」。误判成「已退出」会导致
                // 每轮循环都杀死者又拉起一个新的，把进程数刷爆。
                continue;
            }
            if (!empty($status['running'])) {
                if (!empty($status['pid'])) {
                    $children[$type]['pid'] = (int) $status['pid'];
                }
                continue; // 还活着，跳过
            }

            // 已退出：读完剩余日志，关闭句柄
            if (is_resource($child['stdout'])) {
                self::relayStream($child['stdout'], $type, false, $children[$type]['stdout_buf'], true);
                fclose($child['stdout']);
            }
            if (is_resource($child['stderr'])) {
                self::relayStream($child['stderr'], $type, true, $children[$type]['stderr_buf'], true);
                fclose($child['stderr']);
            }
            CliServer::procCall('proc_close', static function () use ($child) {
                return @proc_close($child['proc']);
            });

            $code = (int) ($status['exitcode'] ?? 0);
            echo "[{$type}] 已退出，code {$code}\n";
            CliServer::log("worker 退出：{$type}，code {$code}");
            unset($children[$type]);

            // 正在整体停止或 reload 时，不要在这里偷偷再拉起
            if (self::$stopRequested || self::$reloadRequested) {
                continue;
            }

            // 这个 worker 是不是已经不在期望集合里了（插件被停用）？
            // **必须查**：不查的话，一个刚被 applyWorkerSet 停掉的 worker
            // 只要还残留在 $children 里被判定为「已退出」，就会在这里被重新拉起来 ——
            // 子进程加载时插件已经没了，于是又立刻退出，白白刷一串日志。
            if (!isset($desiredByType[$type])) {
                CliServer::log("worker {$type} 已不在期望集合中，不再自动拉起");
                continue;
            }

            sleep(1); // 避免崩溃后疯狂重启把 CPU 打满
            $label = (string) ($child['label'] ?? $type);
            $newChild = self::spawnWorker($type, $label, (string) ($child['capability'] ?? ''));
            if ($newChild === null) {
                echo "[{$type}] 自动拉起失败\n";
                CliServer::log("异常：worker {$type} 自动拉起失败");
                continue;
            }
            $children[$type] = $newChild;
            echo "[{$type}] 已自动拉起，PID {$newChild['pid']}\n";
            CliServer::log("worker 自动拉起：{$type}，PID {$newChild['pid']}");
        }
    }

    /**
     * 先温和终止，再必要时强杀。
     *
     * @param array<string, array{proc: resource, type: string, label: string, pid: int, stdout: resource|null, stderr: resource|null}> $children
     */
    private static function stopAllWorkers(array &$children): void
    {
        foreach ($children as $child) {
            self::terminateWorker($child);
        }

        usleep(400000); // 给子进程一点时间自己收尾

        foreach ($children as $type => $child) {
            self::finishStoppedWorker($child);
            unset($children[$type]);
        }

        $children = [];
    }

    /**
     * 第一步：温和终止（默认 SIGTERM）。
     *
     * @param array<string, mixed> $child
     */
    private static function terminateWorker(array $child): void
    {
        if (is_resource($child['proc'])) {
            CliServer::procCall('proc_terminate', static function () use ($child) {
                return @proc_terminate($child['proc']);
            });
        }
    }

    /**
     * 第二步：确认退出（必要时强杀）并回收句柄。
     *
     * @param array<string, mixed> $child
     */
    private static function finishStoppedWorker(array $child): void
    {
        if (!is_resource($child['proc'])) {
            return;
        }

        $status = CliServer::procCall('proc_get_status', static function () use ($child) {
            return proc_get_status($child['proc']);
        }, null);
        // 探测不可用时保守强杀，免得留下停不掉的子进程
        if (!is_array($status) || !empty($status['running'])) {
            CliServer::procCall('proc_terminate', static function () use ($child) {
                return @proc_terminate($child['proc'], 9); // SIGKILL
            });
        }
        if (is_resource($child['stdout'])) {
            fclose($child['stdout']);
        }
        if (is_resource($child['stderr'])) {
            fclose($child['stderr']);
        }
        CliServer::procCall('proc_close', static function () use ($child) {
            return @proc_close($child['proc']);
        });
    }

    /**
     * 把当前 worker PID 列表写到 workers.json，给 status 命令展示用。
     *
     * @param array<string, array{type: string, pid: int}> $children
     */
    private static function saveWorkersState(array $children): void
    {
        $workers = [];
        foreach ($children as $child) {
            $workers[] = [
                'type' => $child['type'],
                'pid' => (int) $child['pid'],
                'label' => (string) ($child['label'] ?? ''),
            ];
        }

        @file_put_contents(
            CliServer::workersStateFile(),
            json_encode([
                'master_pid' => (int) getmypid(),
                'updated_at' => date('Y-m-d H:i:s'),
                'workers' => $workers,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function loadWorkersState(): ?array
    {
        $file = CliServer::workersStateFile();
        if (!is_file($file)) {
            return null;
        }
        $raw = file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * 注册系统信号（Linux/宝塔常用）：
     *   SIGTERM/SIGINT → 停止（Supervisor 点「停止」通常发 SIGTERM）
     *   SIGUSR1        → 重载 worker
     */
    private static function installSignalHandlers(): void
    {
        if (!function_exists('pcntl_async_signals') || !function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, static function (): void {
            self::$stopRequested = true;
        });
        pcntl_signal(SIGTERM, static function (): void {
            self::$stopRequested = true;
        });
        if (defined('SIGUSR1')) {
            pcntl_signal(SIGUSR1, static function (): void {
                self::$reloadRequested = true;
            });
        }
    }

    /**
     * 终端输出是否支持 ANSI 颜色（自动检测）。
     *
     * 终端（TTY）或设置 FORCE_COLOR 时启用；输出被重定向到文件、落到 Supervisor
     * 日志，或设置 NO_COLOR 时全部返回空串，避免日志里混入转义符乱码。
     *
     * @return array{red: string, bold: string, yellow: string, reset: string}
     */
    private static function consoleColors(): array
    {
        $useColor = (string) getenv('FORCE_COLOR') !== ''
            || (defined('STDOUT') && function_exists('stream_isatty') && stream_isatty(STDOUT));

        if ((string) getenv('NO_COLOR') !== '') {
            $useColor = false;
        }

        if (!$useColor) {
            return ['red' => '', 'bold' => '', 'yellow' => '', 'reset' => ''];
        }

        return [
            'red'    => "\033[31m",
            'bold'   => "\033[1m",
            'yellow' => "\033[33m",
            'reset'  => "\033[0m",
        ];
    }

    private static function printHelp(): void
    {
        echo <<<TXT
EMSHOP CLI 后台任务服务（多进程）

  php server start     前台启动主进程（若已有实例则先停止旧进程再启动）
  php server stop      停止主进程及全部 worker
  php server status    查看运行状态
  php server reload    重启全部 worker（主进程不退出）
  php server restart   停止旧主进程并重新启动（等同 start）

  说明：worker 子进程只由主进程自动拉起，无需也不能当作日常命令手动执行。

TXT;
    }
}
