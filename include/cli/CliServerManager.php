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

    /**
     * 主进程入口：根据命令分发。
     *
     * @param list<string> $argv
     * @return int 进程退出码（0=成功，非 0=失败；给 Supervisor 看）
     */
    public static function run(array $argv): int
    {
        CliServer::ensureRuntimeDir(); // 创建运行目录

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
        // 0) proc_open 被禁用时服务无法拉起 worker，提前失败退出
        $disabled = (string) ini_get('disable_functions');

        
        if (stripos(',' . str_replace(' ', '', $disabled) . ',', ',proc_open,') !== false) {
            $msg = 'EMShop 任务服务依赖 proc_open 函数拉起 worker 子进程';
            $fix = '请在 宝塔 - 软件商店 - PHP - 设置 - 禁用函数 中删除 proc_open 后重新启动';
            CliServer::log('启动失败：' . $msg . '；' . $fix);

            $c = self::consoleColors();

            // 按内容最宽一行确定 = 边框长度（颜色码不计入宽度）
            $plain = ['  ✖ 启动失败：' . $msg, '  ' . $fix];
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
            echo $c['bold'] . $c['red'] . '  ✖ 启动失败：' . $c['reset'] . $msg . PHP_EOL;
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

        echo "========================================\n";
        echo "EMSHOP CLI 任务服务已启动！\n";
        echo "主进程 PID：{$masterPid}；PHP 版本：" . PHP_VERSION . "\n";
        echo "由本进程监护 " . count(CliServer::workerDefinitions()) . " 个 工作任务子进程\n";
        echo "========================================\n";
        CliServer::log("启动：主进程 PID {$masterPid}");

        // 6) 按定义逐个拉起子进程（每个都是：php server worker --type=xxx）
        /** @var array<string, array{proc: resource, type: string, label: string, pid: int, stdout: resource|null, stderr: resource|null, stdout_buf: string, stderr_buf: string}> $children */
        $children = [];

        foreach (CliServer::workerDefinitions() as $def) {
            $child = self::spawnWorker($def['type'], $def['label']);
            if ($child === null) {
                echo "worker {$def['type']} 启动失败\n";
                CliServer::log("异常：worker {$def['type']} 启动失败");
                continue;
            }
            $children[$def['type']] = $child;
            echo "已启动 worker {$def['type']}（{$def['label']}），PID {$child['pid']}\n";
            CliServer::log("worker 已拉起：{$def['type']}，PID {$child['pid']}");
        }

        if ($children === []) {
            echo "没有 worker 启动成功，主进程退出。\n";
            CliServer::clearPid();
            return 1;
        }

        self::saveWorkersState($children);

        // 7) 主循环：只要没要求停止，就一直待在这里（所以 Supervisor 觉得服务「在运行」）
        while (!self::$stopRequested) {
            // 7a) 有人写了 reload.flag（或发了 SIGUSR1）→ 重启全部 worker，主进程自己不退
            if (CliServer::consumeReloadRequest()) {
                self::$reloadRequested = true;
            }

            if (self::$reloadRequested) {
                self::$reloadRequested = false;
                echo "收到 reload，正在重启全部 worker…\n";
                CliServer::log('重载：主进程开始重启全部 worker');
                self::stopAllWorkers($children);
                $children = [];
                foreach (CliServer::workerDefinitions() as $def) {
                    $child = self::spawnWorker($def['type'], $def['label']);
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

            // 7b) 把子进程的 echo 转发到主进程终端（宝塔日志里能看到 [queue] xxx）
            self::relayChildrenOutput($children);

            // 7c) 发现某个 worker 死了 → 自动再拉起
            self::reapAndRespawn($children);
            self::saveWorkersState($children);

            if ($children === []) {
                echo "全部 worker 已退出且无法拉起，主进程退出。\n";
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

        // 8) 离开主循环 = 要停服：先杀光 worker，再清 PID
        echo "正在停止全部 worker…\n";
        self::stopAllWorkers($children);
        CliServer::clearPid();
        @unlink(CliServer::workersStateFile());
        CliServer::log('关闭：CLI 主进程已退出');
        echo "EMSHOP CLI 任务服务已停止。\n";

        return 0;
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
            return 0;
        }

        CliServer::log('停止：当前没有正在运行的主进程');
        echo "当前没有正在运行的任务服务。\n";
        CliServer::clearPid();
        return 0;
    }

    private static function cmdStatus(): int
    {
        $pid = CliServer::readPid();
        if ($pid > 0 && CliServer::isProcessAlive($pid)) {
            echo "任务服务正在运行（主进程 PID：{$pid}）\n";
            $state = self::loadWorkersState();
            if (is_array($state) && !empty($state['workers']) && is_array($state['workers'])) {
                foreach ($state['workers'] as $row) {
                    $type = (string) ($row['type'] ?? '');
                    $wPid = (int) ($row['pid'] ?? 0);
                    $alive = $wPid > 0 && CliServer::isProcessAlive($wPid) ? '运行中' : '已退出';
                    echo "  worker {$type}  PID {$wPid}  {$alive}\n";
                }
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
    private static function spawnWorker(string $type, string $label): ?array
    {
        $cmd = [
            CliServer::phpBinary(),
            EM_ROOT . DIRECTORY_SEPARATOR . 'server',
            'worker',
            '--type=' . $type,
        ];

        // print_r($cmd);
        // echo "正在拉起 worker {$type}（{$label}）…\n";


        // pipes：0=stdin，1=stdout，2=stderr；主进程读 1/2 用来打日志
        $proc = @proc_open(
            $cmd,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            EM_ROOT
        );

        if (!is_resource($proc)) {
            return null;
        }

        fclose($pipes[0]); // 不需要给子进程写输入
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $status = proc_get_status($proc);

        return [
            'proc' => $proc,
            'type' => $type,
            'label' => $label,
            'pid' => (int) ($status['pid'] ?? 0),
            'stdout' => $pipes[1],
            'stderr' => $pipes[2],
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
    private static function reapAndRespawn(array &$children): void
    {
        foreach ($children as $type => $child) {
            if (!is_resource($child['proc'])) {
                unset($children[$type]);
                continue;
            }

            $status = proc_get_status($child['proc']);
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
            proc_close($child['proc']);

            $code = (int) ($status['exitcode'] ?? 0);
            echo "[{$type}] 已退出，code {$code}\n";
            CliServer::log("worker 退出：{$type}，code {$code}");
            unset($children[$type]);

            // 正在整体停止或 reload 时，不要在这里偷偷再拉起
            if (self::$stopRequested || self::$reloadRequested) {
                continue;
            }

            sleep(1); // 避免崩溃后疯狂重启把 CPU 打满
            $label = (string) ($child['label'] ?? $type);
            $newChild = self::spawnWorker($type, $label);
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
            if (is_resource($child['proc'])) {
                @proc_terminate($child['proc']); // 默认 SIGTERM
            }
        }

        usleep(400000); // 给子进程一点时间自己收尾

        foreach ($children as $type => $child) {
            if (is_resource($child['proc'])) {
                $status = proc_get_status($child['proc']);
                if (!empty($status['running'])) {
                    @proc_terminate($child['proc'], 9); // SIGKILL
                }
                if (is_resource($child['stdout'])) {
                    fclose($child['stdout']);
                }
                if (is_resource($child['stderr'])) {
                    fclose($child['stderr']);
                }
                @proc_close($child['proc']);
            }
            unset($children[$type]);
        }

        $children = [];
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
