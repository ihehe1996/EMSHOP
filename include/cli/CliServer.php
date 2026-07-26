<?php

/**
 * CLI 公共工具：路径、日志、PID、进程探测等。
 *
 * 运行目录 content/server/：
 *   server.pid        主进程 PID
 *   server.log        服务日志
 *   server.heartbeat  心跳文件（后台首页据此显示「运行中」）
 *   workers.json      当前各 worker PID
 *   reload.flag       存在则主进程重启全部 worker
 */
final class CliServer
{
    /** worker 类型名（对应 --type=） */
    public const WORKER_HEARTBEAT = 'heartbeat';
    public const WORKER_QUEUE = 'queue';
    public const WORKER_ORDER_POLL = 'order_poll';
    public const WORKER_GOODS_SYNC = 'goods_sync';

    public const GOODS_SYNC_BATCH_SIZE = 30;
    /** 商品同步进度存在 config 表里的 key（沿用旧名，后续可再迁） */
    public const GOODS_SYNC_STATE_KEY = 'swoole_goods_rr_state';

    /** 任务服务文件版本（推荐命名） */
    public const CONFIG_FILE_VERSION_APPLIED = 'server_file_version_applied';
    public const CONFIG_FILE_VERSION_PENDING = 'server_file_version_pending';
    /** 旧命名（兼容期内双写双读，后续淘汰） */
    public const CONFIG_FILE_VERSION_APPLIED_LEGACY = 'local_swoole_file_version';
    public const CONFIG_FILE_VERSION_PENDING_LEGACY = 'new_swoole_file_version';

    /** @var string|null */
    private static $runtimeDir;

    public static function runtimeDir(): string
    {
        if (self::$runtimeDir === null) {
            self::$runtimeDir = EM_ROOT . '/content/server';
        }
        return self::$runtimeDir;
    }

    public static function pidFile(): string
    {
        return self::runtimeDir() . '/server.pid';
    }

    public static function logFile(): string
    {
        return self::runtimeDir() . '/server.log';
    }

    public static function heartbeatFile(): string
    {
        return self::runtimeDir() . '/server.heartbeat';
    }

    public static function startLockFile(): string
    {
        return self::runtimeDir() . '/server.start.lock';
    }

    public static function workersStateFile(): string
    {
        return self::runtimeDir() . '/workers.json';
    }

    public static function reloadFlagFile(): string
    {
        return self::runtimeDir() . '/reload.flag';
    }

    /** 写 reload.flag，让主进程下一轮循环重启 worker */
    public static function requestReload(): bool
    {
        self::ensureRuntimeDir();
        return @file_put_contents(self::reloadFlagFile(), (string) time(), LOCK_EX) !== false;
    }

    /** 主进程调用：若有 reload.flag 则删掉并返回 true */
    public static function consumeReloadRequest(): bool
    {
        $file = self::reloadFlagFile();
        if (!is_file($file)) {
            return false;
        }
        @unlink($file);
        return true;
    }

    /**
     * 读取已生效（applied）文件版本：优先新 key，否则回退旧 key。
     */
    public static function getAppliedFileVersion(): string
    {
        return self::readConfigPrefer(
            self::CONFIG_FILE_VERSION_APPLIED,
            self::CONFIG_FILE_VERSION_APPLIED_LEGACY,
            '0.0.0'
        );
    }

    /**
     * 读取待生效（pending）文件版本；多 key 都有值时取较大者（避免漏掉只 bump 旧 key 的插件）。
     */
    public static function getPendingFileVersion(): string
    {
        $primary = trim((string) (Config::get(self::CONFIG_FILE_VERSION_PENDING, '') ?? ''));
        $legacy = trim((string) (Config::get(self::CONFIG_FILE_VERSION_PENDING_LEGACY, '') ?? ''));
        if ($primary === '') {
            return $legacy;
        }
        if ($legacy === '') {
            return $primary;
        }
        return @version_compare($primary, $legacy, '>=') ? $primary : $legacy;
    }

    /**
     * 写入已生效版本（applied + 旧 key 双写）。
     */
    public static function setAppliedFileVersion(string $version): void
    {
        $version = trim($version);
        Config::set(self::CONFIG_FILE_VERSION_APPLIED, $version);
        Config::set(self::CONFIG_FILE_VERSION_APPLIED_LEGACY, $version);
    }

    /**
     * bump 待生效版本（pending + 旧 key 双写）。
     * 新代码 / 插件迁移后请调用本方法，替代直接 Config::set('new_swoole_file_version', …)。
     */
    public static function bumpPendingFileVersion(?string $version = null): void
    {
        $version = trim((string) ($version !== null ? $version : time()));
        if ($version === '') {
            $version = (string) time();
        }
        Config::set(self::CONFIG_FILE_VERSION_PENDING, $version);
        Config::set(self::CONFIG_FILE_VERSION_PENDING_LEGACY, $version);
    }

    /**
     * @param string $primary 新 key
     * @param string $legacy  旧 key
     */
    private static function readConfigPrefer(string $primary, string $legacy, string $default = ''): string
    {
        $value = trim((string) (Config::get($primary, '') ?? ''));
        if ($value !== '') {
            return $value;
        }
        $value = trim((string) (Config::get($legacy, '') ?? ''));
        if ($value !== '') {
            return $value;
        }
        return $default;
    }

    /**
     * 要拉起哪些 worker（改这里就等于改「启几类任务」）。
     *
     * @return list<array{type: string, label: string}>
     */
    public static function workerDefinitions(): array
    {
        return [
            ['type' => self::WORKER_HEARTBEAT, 'label' => '心跳'],
            ['type' => self::WORKER_QUEUE, 'label' => '发货队列/超时'],
            ['type' => self::WORKER_ORDER_POLL, 'label' => '订单轮询'],
            ['type' => self::WORKER_GOODS_SYNC, 'label' => '商品同步'],
        ];
    }

    public static function ensureRuntimeDir(): void
    {
        $dir = self::runtimeDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    public static function log(string $message): void
    {
        self::ensureRuntimeDir();
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
        @file_put_contents(self::logFile(), $line, FILE_APPEND | LOCK_EX);
    }

    public static function phpBinary(): string
    {
        return PHP_BINARY !== '' ? PHP_BINARY : 'php';
    }

    public static function readPid(string $pidFile = ''): int
    {
        $file = $pidFile !== '' ? $pidFile : self::pidFile();
        if (!is_file($file)) {
            return 0;
        }
        return (int) trim((string) file_get_contents($file));
    }

    public static function writePid(int $pid): void
    {
        self::ensureRuntimeDir();
        @file_put_contents(self::pidFile(), (string) $pid, LOCK_EX);
    }

    public static function clearPid(): void
    {
        $file = self::pidFile();
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * 进程是否仍存活。
     */
    public static function isProcessAlive(int $pid): bool
    {
        if ($pid < 1) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = 'tasklist /FI "PID eq ' . $pid . '" /NH 2>nul';
            $out = (string) shell_exec($cmd);
            return strpos($out, (string) $pid) !== false;
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        return file_exists('/proc/' . $pid);
    }

    /**
     * 向进程发送停止信号（Windows 使用 taskkill）。
     */
    public static function stopProcess(int $pid): bool
    {
        if ($pid < 1) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            exec('taskkill /PID ' . $pid . ' /T /F 2>nul', $out, $code);
            return $code === 0;
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, defined('SIGTERM') ? SIGTERM : 15);
        }

        exec('kill -TERM ' . $pid . ' 2>/dev/null', $out, $code);
        return $code === 0;
    }

    /**
     * 启动前探测数据库：临时连接，成功后立即关闭。
     */
    public static function probeMysql(): void
    {
        $cfg = require EM_ROOT . '/config.php';
        $db = (array) ($cfg['db'] ?? []);

        $host = (string) ($db['host'] ?? '127.0.0.1');
        $port = (int) ($db['port'] ?? 3306);
        $dbname = (string) ($db['dbname'] ?? '');
        $username = (string) ($db['username'] ?? '');
        $password = (string) ($db['password'] ?? '');
        $charset = (string) ($db['charset'] ?? 'utf8mb4');

        if ($dbname === '' || $username === '') {
            throw new RuntimeException('数据库配置不完整');
        }

        if (extension_loaded('mysqli')) {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $mysqli = mysqli_init();
            $mysqli->real_connect($host, $username, $password, $dbname, $port);
            $mysqli->set_charset($charset);
            $rs = $mysqli->query('SELECT 1');
            if ($rs instanceof mysqli_result) {
                $rs->free();
            }
            $mysqli->close();
            return;
        }

        if (extension_loaded('pdo_mysql')) {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $dbname, $charset);
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->query('SELECT 1');
            $pdo = null;
            return;
        }

        throw new RuntimeException('当前 PHP 环境既不支持 mysqli，也不支持 pdo_mysql，无法探测数据库连接');
    }

    /**
     * 获取 start 互斥锁，避免并发重复启动。
     *
     * @return resource
     */
    public static function acquireStartLock()
    {
        self::ensureRuntimeDir();
        $fp = @fopen(self::startLockFile(), 'c+');
        if ($fp === false) {
            echo '无法打开启动锁文件：' . self::startLockFile() . "\n";
            exit(1);
        }

        if (!@flock($fp, LOCK_EX | LOCK_NB)) {
            echo "检测到另一个启动进程正在执行，已阻止重复启动。（如果刚刚关闭进程，请等待片刻后再试）\n";
            exit(1);
        }

        @ftruncate($fp, 0);
        @fwrite($fp, (string) getmypid());
        @fflush($fp);
        return $fp;
    }
}
