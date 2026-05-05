<?php

declare(strict_types=1);

/**
 * EMSHOP Swoole 入口：
 * - HTTP 状态接口
 * - 发货队列消费
 * - 定时任务调度
 *
 * 用法：
 * php swoole/server.php start|stop|status|reload
 */

define('EM_ROOT', dirname(__DIR__));

$command = $argv[1] ?? 'start';

define('SW_DEFAULT_HOST', '0.0.0.0');
define('SW_DEFAULT_PORT', 9601);

// 监听地址优先级：CLI 参数 > 数据库配置 > 默认值
$listen = ['host' => SW_DEFAULT_HOST, 'port' => SW_DEFAULT_PORT];
if ($command === 'start') {
    $listen = resolveSwooleListenAddress($argv ?? []);
}
define('SW_HOST', $listen['host']);
define('SW_PORT', $listen['port']);
define('SW_PID_FILE', EM_ROOT . '/swoole/swoole.pid');
define('SW_LOG_FILE', EM_ROOT . '/swoole/swoole.log');
define('SW_HEARTBEAT_FILE', EM_ROOT . '/swoole/swoole.heartbeat');
define('SW_QUEUE_INTERVAL', 2);
define('SW_TIMER_INTERVAL', 60);
define('SW_HEARTBEAT_INTERVAL', 5);

switch ($command) {
    case 'stop':
        $pid = getPid(SW_PID_FILE);
        if ($pid && posix_kill($pid, 0)) {
            posix_kill($pid, SIGTERM);
            echo "Swoole server stopped (PID: {$pid})\n";
        } else {
            echo "Swoole server is not running\n";
        }
        exit(0);

    case 'status':
        $pid = getPid(SW_PID_FILE);
        if ($pid && posix_kill($pid, 0)) {
            echo "Swoole server is running (PID: {$pid})\n";
        } else {
            echo "Swoole server is not running\n";
        }
        exit(0);

    case 'reload':
        $pid = getPid(SW_PID_FILE);
        if ($pid && posix_kill($pid, 0)) {
            posix_kill($pid, SIGUSR1);
            echo "Swoole server reload signal sent (PID: {$pid})\n";
        } else {
            echo "Swoole server is not running, cannot reload\n";
            exit(1);
        }
        exit(0);

    case 'start':
        break;

    default:
        echo "Usage: php server.php {start|stop|status|reload}\n";
        exit(1);
}

$server = new Swoole\Http\Server(SW_HOST, SW_PORT);

$server->set([
    'worker_num'      => 2,
    'daemonize'       => false,
    'pid_file'        => SW_PID_FILE,
    'log_file'        => SW_LOG_FILE,
    'log_level'       => SWOOLE_LOG_INFO,
]);

$startTime = time();

$stats = [
    'queue_processed'    => 0,
    'queue_failed'       => 0,
    'timers_run'         => 0,
    'order_timeout_runs' => 0,
    'goods_sync_runs'    => 0,
    'order_poll_runs'    => 0,
];

$server->on('start', function (Swoole\Http\Server $server) use (&$startTime) {
    echo "EMSHOP Swoole Server started at " . SW_HOST . ":" . SW_PORT . "\n";
    echo "PID: {$server->master_pid}\n";
    $startTime = time();
});

$server->on('workerStart', function (Swoole\Http\Server $server, int $workerId) use (&$stats) {

    // WSL 下可通过网关访问 Windows 主机上的 MySQL
    if (PHP_OS_FAMILY === 'Linux' && is_file('/proc/version') && str_contains(file_get_contents('/proc/version'), 'microsoft')) {
        $gwIp = trim(shell_exec("ip route show default 2>/dev/null | awk '{print \$3}'") ?: '');
        if ($gwIp !== '') {
            putenv("EM_DB_HOST={$gwIp}");
        }
    }

    require EM_ROOT . '/init.php';

    // 仅 worker #0 跑定时器和队列
    if ($workerId !== 0) {
        return;
    }

    @touch(SW_HEARTBEAT_FILE);

    Config::reload();
    $bootCodeVersion = (string) (Config::get('swoole_code_version') ?? '');

    // 插件可在该钩子内注册自定义 timer
    try {
        doAction('swoole_worker_start', $server, $workerId);
    } catch (Throwable $e) {
        error_log('[Swoole Hook] swoole_worker_start: ' . $e->getMessage());
    }

    Swoole\Timer::tick(SW_QUEUE_INTERVAL * 1000, function () use (&$stats) {
        try {
            processQueue($stats);
        } catch (Throwable $e) {
            error_log("[Queue Error] " . $e->getMessage());
        }
    });

    // 每分钟执行主定时任务（订单超时 + 订单轮询）并检查代码版本
    Swoole\Timer::tick(SW_TIMER_INTERVAL * 1000, function () use (&$stats, $server, $bootCodeVersion) {
        try {
            Config::reload();
            if (runSwooleFileVersionReloadCheck($server)) {
                return;
            }
            $current = (string) (Config::get('swoole_code_version') ?? '');
            if ($current !== '' && $current !== $bootCodeVersion) {
                error_log("[Swoole] code version changed ({$bootCodeVersion} -> {$current}), reloading workers");
                $server->reload();
                return;
            }
            runOrderTimeoutChecks($stats);
            runOrderPollingTasks($stats);
            $stats['timers_run']++;
        } catch (Throwable $e) {
            error_log("[Timer Error] " . $e->getMessage());
        }
    });

    // 商品同步任务独立定时器，避免与其它任务串行阻塞
    Swoole\Timer::tick(SW_TIMER_INTERVAL * 1000, function () use (&$stats) {
        try {
            runGoodsSyncTasks($stats);
        } catch (Throwable $e) {
            error_log("[Goods Sync Timer Error] " . $e->getMessage());
        }
    });

    Swoole\Timer::tick(SW_HEARTBEAT_INTERVAL * 1000, function () {
        @touch(SW_HEARTBEAT_FILE);
    });

    echo "Worker #{$workerId} timers started\n";
});

// 监控 API（供后台页面调用）
$server->on('request', function (Swoole\Http\Request $request, Swoole\Http\Response $response) use ($server, &$startTime, &$stats) {
    $response->header('Content-Type', 'application/json; charset=utf-8');
    $response->header('Access-Control-Allow-Origin', '*');

    $path = $request->server['request_uri'] ?? '/';

    switch ($path) {
        case '/status':
            $swooleStats = $server->stats();
            $prefix = Database::prefix();

            $queueStats = Database::fetchOne(
                "SELECT COUNT(*) as total,
                    SUM(status='pending') as pending, SUM(status='processing') as processing,
                    SUM(status='success') as success, SUM(status='failed') as failed
                 FROM {$prefix}delivery_queue"
            );

            $response->end(json_encode([
                'code' => 200,
                'data' => [
                    'running'     => true,
                    'pid'         => $server->master_pid,
                    'uptime'      => time() - $startTime,
                    'workers'     => $swooleStats['worker_num'] ?? 0,
                    'connections' => $swooleStats['connection_num'] ?? 0,
                    'queue'       => [
                        'total'      => (int) ($queueStats['total'] ?? 0),
                        'pending'    => (int) ($queueStats['pending'] ?? 0),
                        'processing' => (int) ($queueStats['processing'] ?? 0),
                        'success'    => (int) ($queueStats['success'] ?? 0),
                        'failed'     => (int) ($queueStats['failed'] ?? 0),
                    ],
                    'stats' => $stats,
                ],
            ], JSON_UNESCAPED_UNICODE));
            break;

        case '/queue/recent':
            $prefix = Database::prefix();
            $rows = Database::query(
                "SELECT id, order_id, task_type, goods_type, status, attempts, max_attempts, last_error, created_at, completed_at
                 FROM {$prefix}delivery_queue ORDER BY id DESC LIMIT 20"
            );
            $response->end(json_encode(['code' => 200, 'data' => $rows], JSON_UNESCAPED_UNICODE));
            break;

        case '/queue/retry':
            if ($request->server['request_method'] !== 'POST') {
                $response->end(json_encode(['code' => 400, 'msg' => 'POST only']));
                break;
            }
            $id = (int) ($request->post['id'] ?? 0);
            if ($id > 0) {
                $prefix = Database::prefix();
                Database::execute(
                    "UPDATE {$prefix}delivery_queue SET status='pending', attempts=0, last_error=NULL, next_retry_at=NULL WHERE id=? AND status='failed'",
                    [$id]
                );
            }
            $response->end(json_encode(['code' => 200, 'msg' => '已重置']));
            break;

        default:
            $response->end(json_encode(['code' => 404, 'msg' => 'Not Found']));
    }
});

$server->start();

/**
 * 解析 Swoole 监听地址与端口。
 *
 * @param array<int, string> $argv
 * @return array{host: string, port: int}
 */
function resolveSwooleListenAddress(array $argv): array
{
    $cli = parseCliListenOptions($argv);
    $host = $cli['host'] !== '' ? $cli['host'] : SW_DEFAULT_HOST;
    $port = $cli['port'];
    if ($port <= 0) {
        $port = readSwoolePortFromDbConfig();
    }
    if ($port <= 0) {
        $port = SW_DEFAULT_PORT;
    }
    return ['host' => $host, 'port' => $port];
}

/**
 * 解析命令行监听参数。
 *
 * @param array<int, string> $argv
 * @return array{host: string, port: int}
 */
function parseCliListenOptions(array $argv): array
{
    $host = '';
    $port = 0;

    foreach ($argv as $arg) {
        if (strpos($arg, '--host=') === 0) {
            $hostCandidate = trim(substr($arg, 7));
            if ($hostCandidate !== '' && preg_match('/^[a-zA-Z0-9\.\-\:\[\]_]+$/', $hostCandidate)) {
                $host = $hostCandidate;
            }
            continue;
        }

        if (strpos($arg, '--port=') === 0) {
            $portCandidate = (int) trim(substr($arg, 7));
            if ($portCandidate >= 1 && $portCandidate <= 65535) {
                $port = $portCandidate;
            }
        }
    }

    return ['host' => $host, 'port' => $port];
}

/**
 * 从配置表读取 swoole_api_url 并解析端口。
 */
function readSwoolePortFromDbConfig(): int
{
    $cfgFile = EM_ROOT . '/config.php';
    if (!is_file($cfgFile)) {
        return 0;
    }

    $cfg = require $cfgFile;
    if (!is_array($cfg) || !isset($cfg['db']) || !is_array($cfg['db'])) {
        return 0;
    }

    $db = $cfg['db'];
    $host = (string) ($db['host'] ?? '');
    $port = (int) ($db['port'] ?? 3306);
    $name = (string) ($db['dbname'] ?? '');
    $user = (string) ($db['username'] ?? '');
    $pass = (string) ($db['password'] ?? '');
    $charset = (string) ($db['charset'] ?? 'utf8mb4');
    $prefixRaw = (string) ($db['prefix'] ?? 'em_');
    $prefix = preg_match('/^[a-zA-Z0-9_]+$/', $prefixRaw) ? $prefixRaw : 'em_';
    if ($host === '' || $name === '' || $user === '' || $port < 1 || $port > 65535) {
        return 0;
    }

    $url = '';
    $sql = "SELECT `config_value` FROM `{$prefix}config` WHERE `config_name`='swoole_api_url' LIMIT 1";

    try {
        if (extension_loaded('mysqli')) {
            $conn = @new mysqli($host, $user, $pass, $name, $port);
            if (!$conn->connect_errno) {
                @mysqli_set_charset($conn, $charset);
                $res = @$conn->query($sql);
                if ($res instanceof mysqli_result) {
                    $row = $res->fetch_assoc();
                    $url = trim((string) ($row['config_value'] ?? ''));
                    $res->free();
                }
                @$conn->close();
            }
        } elseif (extension_loaded('pdo_mysql')) {
            $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=' . $charset;
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $row = $pdo->query($sql)->fetch();
            $url = trim((string) ($row['config_value'] ?? ''));
        }
    } catch (Throwable $e) {
        return 0;
    }

    return parsePortFromApiUrl($url);
}

/**
 * 从 API URL 解析端口。
 */
function parsePortFromApiUrl(string $url): int
{
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return 0;
    }
    $parts = @parse_url($url);
    if (!is_array($parts)) {
        return 0;
    }
    $port = (int) ($parts['port'] ?? 0);
    return ($port >= 1 && $port <= 65535) ? $port : 0;
}

/**
 * 读取 PID 文件，返回进程 ID。
 */
function getPid(string $pidFile): int
{
    if (!is_file($pidFile)) {
        return 0;
    }
    return (int) file_get_contents($pidFile);
}

/**
 * 队列消费：取任务、抢占、执行、更新状态。
 */
function processQueue(array &$stats): void
{
    Config::reload();

    $prefix = Database::prefix();

    $task = Database::fetchOne(
        "SELECT * FROM {$prefix}delivery_queue
         WHERE status IN ('pending','retry') AND (next_retry_at IS NULL OR next_retry_at <= NOW())
         ORDER BY id ASC LIMIT 1"
    );

    if (!$task) {
        return;
    }

    $taskId = (int) $task['id'];

    $affected = Database::execute(
        "UPDATE {$prefix}delivery_queue SET status='processing', attempts=attempts+1 WHERE id=? AND status IN ('pending','retry')",
        [$taskId]
    );
    if ($affected === 0) {
        return;
    }

    try {
        $orderId = (int) $task['order_id'];
        $orderGoodsId = (int) $task['order_goods_id'];
        $goodsType = $task['goods_type'];
        $payload = json_decode($task['payload'] ?? '{}', true) ?: [];

        doAction("goods_type_{$goodsType}_order_paid", $orderId, $orderGoodsId, json_encode($payload));

        Database::execute(
            "UPDATE {$prefix}delivery_queue SET status='success', completed_at=NOW() WHERE id=?",
            [$taskId]
        );
        $stats['queue_processed']++;

        OrderModel::notifyDeliveryCallback($orderGoodsId);

        OrderModel::checkDeliveryComplete($orderId);

    } catch (Throwable $e) {
        $attempts = (int) $task['attempts'] + 1;
        $maxAttempts = (int) $task['max_attempts'];

        $isPermanent = $e instanceof PermanentDeliveryException;

        if ($isPermanent || $attempts >= $maxAttempts) {
            Database::execute(
                "UPDATE {$prefix}delivery_queue SET status='failed', last_error=? WHERE id=?",
                [($isPermanent ? '[永久失败] ' : '') . $e->getMessage(), $taskId]
            );
            $stats['queue_failed']++;
        } else {
            $delay = min(300, 30 * pow(2, $attempts - 1));
            Database::execute(
                "UPDATE {$prefix}delivery_queue SET status='retry', last_error=?, next_retry_at=DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id=?",
                [$e->getMessage(), $delay, $taskId]
            );
        }
    }
}

/**
 * 定时任务：订单超时检查。
 */
function runOrderTimeoutChecks(array &$stats): void
{
    Config::reload();

    $prefix = Database::prefix();

    $expireMinutes = (int) (Config::get('shop_order_expire_minutes', '30') ?: 30);

    $expired = Database::execute(
        "UPDATE {$prefix}order SET status='expired'
         WHERE status='pending' AND created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)",
        [$expireMinutes]
    );

    if ($expired > 0) {
        error_log("[Timer] Expired {$expired} orders");
    }
    $stats['order_timeout_runs']++;
}

/**
 * 定时任务：商品同步。
 */
function runGoodsSyncTasks(array &$stats): void
{
    try {
        doAction('swoole_goods_sync_tick');
    } catch (Throwable $e) {
        error_log('[Timer Hook][goods_sync] ' . $e->getMessage());
    }
    $stats['goods_sync_runs']++;
}

/**
 * 定时任务：订单轮询。
 */
function runOrderPollingTasks(array &$stats): void
{
    try {
        doAction('swoole_order_poll_tick');
    } catch (Throwable $e) {
        error_log('[Timer Hook][order_poll] ' . $e->getMessage());
    }
    $stats['order_poll_runs']++;
}

/**
 * 检查 swoole 文件版本号，必要时触发 reload。
 *
 * @return bool 是否已触发 reload
 */
function runSwooleFileVersionReloadCheck($server): bool
{
    $local = trim((string) (Config::get('local_swoole_file_version', '0.0.0') ?? '0.0.0'));
    $new = trim((string) (Config::get('new_swoole_file_version', '') ?? ''));
    if ($new === '') {
        return false;
    }

    if (@version_compare($new, $local, '>')) {
        Config::set('local_swoole_file_version', $new);
        error_log("[Swoole] file version upgrade {$local} -> {$new}, reloading workers");
        $server->reload();
        return true;
    }
    return false;
}

