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
define('SW_GOODS_SYNC_BATCH_SIZE', 30);
define('SW_GOODS_SYNC_STATE_KEY', 'swoole_goods_rr_state');
define('SW_GOODS_SYNC_LOCK_STALE_SECONDS', 1800);

/**
 * 服务生命周期与异常日志（中文），追加写入 swoole.log。
 */
function swooleServiceLog(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    @file_put_contents(SW_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

switch ($command) {
    case 'stop':
        $pid = getPid(SW_PID_FILE);
        if ($pid && posix_kill($pid, 0)) {
            posix_kill($pid, SIGTERM);
            swooleServiceLog("停止：已向主进程发送退出信号（PID {$pid}）");
            echo "已向主进程发送停止信号（PID {$pid}），服务即将退出。\n";
        } else {
            swooleServiceLog('停止：当前没有正在运行的 Swoole 主进程');
            echo "当前没有正在运行的 Swoole 服务。\n";
        }
        exit(0);

    case 'status':
        $pid = getPid(SW_PID_FILE);
        if ($pid && posix_kill($pid, 0)) {
            echo "Swoole 服务正在运行（主进程 PID：{$pid}）\n";
        } else {
            echo "Swoole 服务未在运行。\n";
        }
        exit(0);

    case 'reload':
        $pid = getPid(SW_PID_FILE);
        if ($pid && posix_kill($pid, 0)) {
            posix_kill($pid, SIGUSR1);
            swooleServiceLog("重载：已向主进程发送平滑重载信号（SIGUSR1，PID {$pid}）");
            echo "已发送平滑重载信号（PID {$pid}）。\n";
        } else {
            swooleServiceLog('重载失败：没有正在运行的主进程，无法重载');
            echo "服务未在运行，无法重载。\n";
            exit(1);
        }
        exit(0);

    case 'start':
        break;

    default:
        echo "用法：php server.php {start|stop|status|reload}\n";
        exit(1);
}

try {
    $server = new Swoole\Http\Server(SW_HOST, SW_PORT);
} catch (Throwable $e) {
    swooleServiceLog('启动失败：无法创建监听，' . $e->getMessage());
    fwrite(STDERR, '启动失败：' . $e->getMessage() . "\n");
    exit(1);
}

$server->set([
    'worker_num'      => 2,
    'daemonize'       => false,
    'pid_file'        => SW_PID_FILE,
    'log_file'        => SW_LOG_FILE,
    'log_level'       => SWOOLE_LOG_ERROR,
    'reload_async'    => true,
    'max_wait_time'   => 20,
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
    swooleServiceLog("启动：服务已开始监听 " . SW_HOST . ':' . SW_PORT . '，主进程 PID ' . $server->master_pid);
    echo "Swoole 已启动，监听 " . SW_HOST . ':' . SW_PORT . "，主进程 PID {$server->master_pid}\n";
    $startTime = time();
});

$server->on('Shutdown', function () {
    swooleServiceLog('关闭：Swoole 主进程已退出');
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
        swooleServiceLog('异常：Worker 启动钩子 swoole_worker_start 执行失败，' . $e->getMessage());
    }

    Swoole\Timer::tick(SW_QUEUE_INTERVAL * 1000, function () use (&$stats) {
        try {
            processQueue($stats);
        } catch (Throwable $e) {
            swooleServiceLog('异常：发货队列处理失败，' . $e->getMessage());
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
                swooleServiceLog("重载：检测到代码版本变更（自 {$bootCodeVersion} 变更为 {$current}），正在触发 Worker 平滑重载");
                if (!$server->reload()) {
                    swooleServiceLog("异常：代码版本变更后触发重载失败（自 {$bootCodeVersion} 变更为 {$current}）");
                    return;
                }
                swooleServiceLog("重载：主进程已接受平滑重载请求（代码版本 {$bootCodeVersion}→{$current}），旧 Worker 退出后新 Worker 将接替");
                return;
            }
            runOrderTimeoutChecks($stats);
            runOrderPollingTasks($stats);
            $stats['timers_run']++;
        } catch (Throwable $e) {
            swooleServiceLog('异常：主定时任务执行失败，' . $e->getMessage());
        }
    });

    // 商品同步任务独立定时器，避免与其它任务串行阻塞
    Swoole\Timer::tick(SW_TIMER_INTERVAL * 1000, function () use (&$stats) {
        try {
            runGoodsSyncTasks($stats);
        } catch (Throwable $e) {
            swooleServiceLog('异常：商品同步定时任务失败，' . $e->getMessage());
        }
    });

    Swoole\Timer::tick(SW_HEARTBEAT_INTERVAL * 1000, function () {
        @touch(SW_HEARTBEAT_FILE);
    });

    swooleServiceLog('运行：Worker#0 初始化完成（PID ' . getmypid() . '），定时器与队列轮询已注册');
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

try {
    $server->start();
} catch (Throwable $e) {
    swooleServiceLog('启动失败：服务运行异常退出，' . $e->getMessage());
    fwrite(STDERR, '启动失败：' . $e->getMessage() . "\n");
    exit(1);
}

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

        // 统一队列成功钩子：供通知类插件在"发货动作真正完成"后接入
        // 参数：
        // 1) order_id
        // 2) order_goods_id
        // 3) queue_task_id
        // 4) 原始队列任务行
        try {
            doAction('order_goods_delivery_queued_success', $orderId, $orderGoodsId, $taskId, $task);
        } catch (Throwable $hookErr) {
            swooleServiceLog('异常：队列成功后置钩子 order_goods_delivery_queued_success 执行失败，' . $hookErr->getMessage());
        }

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

    $stats['order_timeout_runs']++;
}

/**
 * 定时任务：商品同步。
 */
function runGoodsSyncTasks(array &$stats): void
{
    $lockToken = swooleGoodsSyncAcquireRunLock();
    if ($lockToken === '') {
        swooleServiceLog('跳过：商品同步任务仍在运行，本次 tick 不重复进入');
        return;
    }

    try {
        $state = swooleGoodsSyncLoadState();
        $batchIds = $state['batch_ids'];
        $batchIndex = $state['batch_index'];
        $cursorId = $state['cursor_id'];

        if ($batchIds === [] || $batchIndex >= count($batchIds)) {
            $batchIds = swooleGoodsSyncFetchBatchIds($cursorId, SW_GOODS_SYNC_BATCH_SIZE);
            if ($batchIds === [] && $cursorId > 0) {
                $cursorId = 0;
                $batchIds = swooleGoodsSyncFetchBatchIds($cursorId, SW_GOODS_SYNC_BATCH_SIZE);
            }

            $state['cursor_id'] = $cursorId;
            $state['batch_ids'] = $batchIds;
            $state['batch_index'] = 0;
            swooleGoodsSyncSaveState($state);
            $batchIndex = 0;
        }

        if ($batchIds === []) {
            $stats['goods_sync_runs']++;
            return;
        }

        $batchCount = count($batchIds);
        for ($i = $batchIndex; $i < $batchCount; $i++) {
            $goodsId = (int) $batchIds[$i];
            $goodsRow = swooleGoodsSyncLoadGoodsRow($goodsId);

            if ($goodsRow === null) {
                // 商品已删除或不存在：仍推进批次，避免阻塞后续轮转。
            } else {
                try {
                    doAction('swoole_goods_sync_one', $goodsRow);
                } catch (Throwable $e) {
                    // 单商品失败不阻塞批次，错误由插件内部记录。
                }
            }

            $state['cursor_id'] = $goodsId;
            $state['batch_index'] = $i + 1;
            swooleGoodsSyncSaveState($state);
        }

        $state['batch_ids'] = [];
        $state['batch_index'] = 0;
        swooleGoodsSyncSaveState($state);
    } finally {
        swooleGoodsSyncReleaseRunLock($lockToken);
    }
    $stats['goods_sync_runs']++;
}

/**
 * 读取商品轮转同步状态（持久化在 config 表）。
 *
 * @return array{cursor_id:int,batch_ids:array<int,int>,batch_index:int,running_lock:string}
 */
function swooleGoodsSyncLoadState(): array
{
    $raw = trim((string) (Config::get(SW_GOODS_SYNC_STATE_KEY, '') ?? ''));
    $state = [];
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $state = $decoded;
        }
    }

    $cursorId = (int) ($state['cursor_id'] ?? 0);
    if ($cursorId < 0) {
        $cursorId = 0;
    }

    $batchIds = [];
    $rawBatchIds = $state['batch_ids'] ?? [];
    if (is_array($rawBatchIds)) {
        foreach ($rawBatchIds as $id) {
            $goodsId = (int) $id;
            if ($goodsId > 0) {
                $batchIds[] = $goodsId;
            }
        }
    }
    $batchIds = array_values(array_unique($batchIds));

    $batchIndex = (int) ($state['batch_index'] ?? 0);
    if ($batchIndex < 0) {
        $batchIndex = 0;
    }
    if ($batchIndex > count($batchIds)) {
        $batchIndex = count($batchIds);
    }

    $runningLock = trim((string) ($state['running_lock'] ?? ''));

    return [
        'cursor_id' => $cursorId,
        'batch_ids' => $batchIds,
        'batch_index' => $batchIndex,
        'running_lock' => $runningLock,
    ];
}

/**
 * 写回商品轮转同步状态。
 *
 * @param array{cursor_id:int,batch_ids:array<int,int>,batch_index:int,running_lock:string} $state
 */
function swooleGoodsSyncSaveState(array $state): void
{
    $payload = [
        'cursor_id' => max(0, (int) ($state['cursor_id'] ?? 0)),
        'batch_ids' => array_values(array_map('intval', array_filter((array) ($state['batch_ids'] ?? []), static function ($id): bool {
            return (int) $id > 0;
        }))),
        'batch_index' => max(0, (int) ($state['batch_index'] ?? 0)),
        'running_lock' => trim((string) ($state['running_lock'] ?? '')),
    ];
    if ($payload['batch_index'] > count($payload['batch_ids'])) {
        $payload['batch_index'] = count($payload['batch_ids']);
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || $json === '') {
        $json = '{"cursor_id":0,"batch_ids":[],"batch_index":0,"running_lock":""}';
    }
    Config::set(SW_GOODS_SYNC_STATE_KEY, $json);
}

/**
 * 获取商品同步全局运行锁，避免重入。
 */
function swooleGoodsSyncAcquireRunLock(): string
{
    $state = swooleGoodsSyncLoadState();
    $existingLock = (string) ($state['running_lock'] ?? '');
    if ($existingLock !== '') {
        $parts = explode(':', $existingLock, 2);
        $lockAt = isset($parts[1]) ? (int) $parts[1] : 0;
        if ($lockAt > 0 && (time() - $lockAt) <= SW_GOODS_SYNC_LOCK_STALE_SECONDS) {
            return '';
        }
        swooleServiceLog('警告：检测到商品同步运行锁过期，已自动回收');
    }

    try {
        $token = bin2hex(random_bytes(8));
    } catch (Throwable $e) {
        $token = md5(uniqid((string) mt_rand(), true));
    }
    $lockToken = $token . ':' . time();
    $state['running_lock'] = $lockToken;
    swooleGoodsSyncSaveState($state);

    $verify = swooleGoodsSyncLoadState();
    return (string) ($verify['running_lock'] ?? '') === $lockToken ? $lockToken : '';
}

/**
 * 释放商品同步全局运行锁。
 */
function swooleGoodsSyncReleaseRunLock(string $lockToken): void
{
    if ($lockToken === '') {
        return;
    }
    $state = swooleGoodsSyncLoadState();
    if ((string) ($state['running_lock'] ?? '') !== $lockToken) {
        return;
    }
    $state['running_lock'] = '';
    swooleGoodsSyncSaveState($state);
}

/**
 * 按 ID 游标查询下一批可同步商品。
 *
 * @return array<int,int>
 */
function swooleGoodsSyncFetchBatchIds(int $cursorId, int $limit): array
{
    $sourceTypes = swooleGoodsSyncResolveSourceTypes();
    if ($sourceTypes === []) {
        return [];
    }

    $prefix = Database::prefix();
    $typePlaceholders = implode(',', array_fill(0, count($sourceTypes), '?'));
    $params = array_merge(
        $sourceTypes,
        [max(0, $cursorId), max(1, $limit)]
    );
    $rows = Database::query(
        "SELECT `id`
         FROM `{$prefix}goods`
         WHERE `deleted_at` IS NULL
           AND `source_type` IN ({$typePlaceholders})
           AND `id` > ?
         ORDER BY `id` ASC
         LIMIT ?",
        $params
    );

    $ids = [];
    foreach ($rows as $row) {
        $goodsId = (int) ($row['id'] ?? 0);
        if ($goodsId > 0) {
            $ids[] = $goodsId;
        }
    }

    return $ids;
}

/**
 * 由插件声明可参与统一轮转的 source_type 列表。
 *
 * @return array<int,string>
 */
function swooleGoodsSyncResolveSourceTypes(): array
{
    $types = applyFilter('swoole_goods_sync_source_types', []);
    if (!is_array($types)) {
        return [];
    }

    $out = [];
    foreach ($types as $type) {
        $name = trim((string) $type);
        if ($name === '') {
            continue;
        }
        if (!preg_match('/^[a-z0-9_]+$/i', $name)) {
            continue;
        }
        $out[$name] = true;
    }

    return array_keys($out);
}

/**
 * 读取单个商品基础信息，供插件按 source_type 路由处理。
 *
 * @return array<string,mixed>|null
 */
function swooleGoodsSyncLoadGoodsRow(int $goodsId): ?array
{
    if ($goodsId <= 0) {
        return null;
    }

    $prefix = Database::prefix();
    $row = Database::fetchOne(
        "SELECT `id`, `source_type`, `source_id`, `title`, `updated_at`
         FROM `{$prefix}goods`
         WHERE `id` = ? AND `deleted_at` IS NULL
         LIMIT 1",
        [$goodsId]
    );
    if ($row === null || !is_array($row)) {
        return null;
    }
    return $row;
}

/**
 * 定时任务：订单轮询。
 */
function runOrderPollingTasks(array &$stats): void
{
    try {
        doAction('swoole_order_poll_tick');
    } catch (Throwable $e) {
        swooleServiceLog('异常：订单轮询钩子 swoole_order_poll_tick，' . $e->getMessage());
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
        swooleServiceLog("重载：检测到文件版本升级（自 {$local} 变更为 {$new}），正在触发 Worker 平滑重载");
        $reloaded = $server->reload();
        if (!$reloaded) {
            swooleServiceLog("异常：文件版本升级后触发重载失败（自 {$local} 变更为 {$new}）");
            return false;
        }
        swooleServiceLog("重载：主进程已接受平滑重载请求（文件版本 {$local}→{$new}），旧 Worker 退出后新 Worker 将接替");
        Config::set('local_swoole_file_version', $new);
        return true;
    }
    return false;
}

