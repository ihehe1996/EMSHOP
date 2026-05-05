<?php

declare(strict_types=1);

// 在线安装向导入口：不依赖 init.php / config.php

if (php_sapi_name() === 'cli') {
    http_response_code(400);
    echo "installer is web-only\n";
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('EM_ROOT', dirname(__DIR__));

require_once EM_ROOT . '/include/lib/Autoloader.php';
Autoloader::register([
    EM_ROOT . '/include/lib',
    EM_ROOT . '/include/model',
    EM_ROOT . '/include/service',
    EM_ROOT . '/include/controller',
]);

/**
 * 读取系统版本（不加载 init.php，避免依赖 config.php/数据库）。
 */
function installer_system_version(): string
{
    $initFile = EM_ROOT . '/init.php';
    if (!is_file($initFile) || !is_readable($initFile)) {
        return 'unknown';
    }
    $content = file_get_contents($initFile);
    if ($content === false) {
        return 'unknown';
    }
    if (preg_match("/define\\(\\s*'EM_VERSION'\\s*,\\s*'([^']+)'\\s*\\)\\s*;/", $content, $m)) {
        return (string) $m[1];
    }
    return 'unknown';
}

/**
 * 安装锁（存在则视为已安装）。
 */
const EM_INSTALL_LOCK = EM_ROOT . '/install/install.lock';

function installer_is_installed(): bool
{
    return is_file(EM_INSTALL_LOCK);
}

function installer_config_writable(): bool
{
    $configPath = EM_ROOT . '/config.php';
    if (is_file($configPath)) {
        return is_writable($configPath);
    }
    return is_writable(EM_ROOT);
}

/**
 * 生成/读取安装向导 CSRF token。
 */
function installer_csrf_token(): string
{
    if (!isset($_SESSION['__em_installer_csrf']) || !is_string($_SESSION['__em_installer_csrf']) || $_SESSION['__em_installer_csrf'] === '') {
        $_SESSION['__em_installer_csrf'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['__em_installer_csrf'];
}

function installer_require_csrf(): void
{
    $posted = $_POST['csrf'] ?? '';
    $expected = $_SESSION['__em_installer_csrf'] ?? '';
    if (!is_string($posted) || !is_string($expected) || $posted === '' || $expected === '' || !hash_equals($expected, $posted)) {
        Response::error('请求校验失败，请刷新页面重试');
    }
}

function installer_is_ajax_request(): bool
{
    $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return is_string($xrw) && strtolower($xrw) === 'xmlhttprequest';
}

function installer_admin_url(): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host . '/admin/';
}

function installer_messages_text(array $messages): string
{
    $texts = [];
    foreach ($messages as $message) {
        if (is_array($message) && isset($message['text']) && is_string($message['text']) && $message['text'] !== '') {
            $texts[] = $message['text'];
        }
    }
    if ($texts === []) {
        return '安装失败，请检查环境后重试';
    }
    return implode("\n", $texts);
}

function installer_fail_response(string $action, array $messages, array $defaults): void
{
    if ($action === 'install' && installer_is_ajax_request()) {
        Response::error(installer_messages_text($messages), ['messages' => $messages]);
    }
    installer_render($messages, $defaults);
}

function installer_success_response(string $action, string $message, array $defaults, array $data = []): void
{
    if ($action === 'install' && installer_is_ajax_request()) {
        Response::success($message, $data);
    }
    installer_render([['type' => 'ok', 'text' => $message]], $defaults);
}

/**
 * 不依赖 Database 类的连接测试（避免 Database 连接失败时触发 Emmsg::error 的致命参数错误）。
 *
 * @param array<string, mixed> $db
 * @return array{ok:bool, driver:string, message:string}
 */
function installer_test_db_connection(array $db): array
{
    $host = (string) ($db['host'] ?? '127.0.0.1');
    $port = (int) ($db['port'] ?? 3306);
    $user = (string) ($db['username'] ?? '');
    $pass = (string) ($db['password'] ?? '');

    // 先优先 mysqli
    if (extension_loaded('mysqli')) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $mysqli = @mysqli_connect($host, $user, $pass, null, $port);
        if ($mysqli === false) {
            return [
                'ok' => false,
                'driver' => 'mysqli',
                'message' => 'mysqli 连接失败：' . (string) mysqli_connect_error(),
            ];
        }
        @mysqli_close($mysqli);
        return ['ok' => true, 'driver' => 'mysqli', 'message' => 'mysqli 连接成功'];
    }

    if (extension_loaded('pdo_mysql')) {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);
            new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            return ['ok' => true, 'driver' => 'pdo', 'message' => 'PDO 连接成功'];
        } catch (Throwable $e) {
            return ['ok' => false, 'driver' => 'pdo', 'message' => 'PDO 连接失败：' . $e->getMessage()];
        }
    }

    return ['ok' => false, 'driver' => 'none', 'message' => '环境缺少 mysqli 或 pdo_mysql 扩展'];
}

function installer_fetch_tables(array $db): array
{
    $host = (string) ($db['host'] ?? '127.0.0.1');
    $port = (int) ($db['port'] ?? 3306);
    $name = (string) ($db['dbname'] ?? '');
    $user = (string) ($db['username'] ?? '');
    $pass = (string) ($db['password'] ?? '');
    if ($name === '') {
        return ['ok' => false, 'driver' => 'none', 'message' => '数据库名为空，无法获取表快照', 'tables' => []];
    }

    if (extension_loaded('mysqli')) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $mysqli = @mysqli_connect($host, $user, $pass, $name, $port);
        if ($mysqli === false) {
            return [
                'ok' => false,
                'driver' => 'mysqli',
                'message' => 'mysqli 连接失败：' . (string) mysqli_connect_error(),
                'tables' => [],
            ];
        }
        @mysqli_set_charset($mysqli, 'utf8mb4');
        $result = @mysqli_query($mysqli, 'SHOW TABLES');
        if ($result === false) {
            $error = (string) mysqli_error($mysqli);
            @mysqli_close($mysqli);
            return ['ok' => false, 'driver' => 'mysqli', 'message' => '读取表结构失败：' . $error, 'tables' => []];
        }
        $tables = [];
        while ($row = mysqli_fetch_row($result)) {
            if (isset($row[0])) {
                $tables[] = (string) $row[0];
            }
        }
        mysqli_free_result($result);
        @mysqli_close($mysqli);
        return ['ok' => true, 'driver' => 'mysqli', 'message' => 'ok', 'tables' => $tables];
    }

    if (extension_loaded('pdo_mysql')) {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $rows = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN, 0);
            $tables = [];
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $tables[] = (string) $row;
                }
            }
            return ['ok' => true, 'driver' => 'pdo', 'message' => 'ok', 'tables' => $tables];
        } catch (Throwable $e) {
            return ['ok' => false, 'driver' => 'pdo', 'message' => '读取表结构失败：' . $e->getMessage(), 'tables' => []];
        }
    }

    return ['ok' => false, 'driver' => 'none', 'message' => '环境缺少 mysqli 或 pdo_mysql 扩展', 'tables' => []];
}

function installer_drop_tables(array $db, array $tables): array
{
    $host = (string) ($db['host'] ?? '127.0.0.1');
    $port = (int) ($db['port'] ?? 3306);
    $name = (string) ($db['dbname'] ?? '');
    $user = (string) ($db['username'] ?? '');
    $pass = (string) ($db['password'] ?? '');
    if ($name === '') {
        return ['ok' => false, 'driver' => 'none', 'message' => '数据库名为空，无法回滚表', 'dropped' => 0, 'failed' => []];
    }

    $validTables = [];
    foreach ($tables as $table) {
        $table = (string) $table;
        if (preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            $validTables[] = $table;
        }
    }
    if ($validTables === []) {
        return ['ok' => true, 'driver' => 'none', 'message' => '无需回滚', 'dropped' => 0, 'failed' => []];
    }

    if (extension_loaded('mysqli')) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $mysqli = @mysqli_connect($host, $user, $pass, $name, $port);
        if ($mysqli === false) {
            return [
                'ok' => false,
                'driver' => 'mysqli',
                'message' => 'mysqli 连接失败：' . (string) mysqli_connect_error(),
                'dropped' => 0,
                'failed' => $validTables,
            ];
        }
        @mysqli_query($mysqli, 'SET FOREIGN_KEY_CHECKS=0');
        $failed = [];
        $dropped = 0;
        for ($i = count($validTables) - 1; $i >= 0; $i--) {
            $table = $validTables[$i];
            $sql = 'DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`';
            if (@mysqli_query($mysqli, $sql) === false) {
                $failed[] = $table;
                continue;
            }
            $dropped++;
        }
        @mysqli_query($mysqli, 'SET FOREIGN_KEY_CHECKS=1');
        @mysqli_close($mysqli);
        return ['ok' => $failed === [], 'driver' => 'mysqli', 'message' => $failed === [] ? 'ok' : '部分回滚失败', 'dropped' => $dropped, 'failed' => $failed];
    }

    if (extension_loaded('pdo_mysql')) {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $failed = [];
            $dropped = 0;
            for ($i = count($validTables) - 1; $i >= 0; $i--) {
                $table = $validTables[$i];
                try {
                    $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
                    $dropped++;
                } catch (Throwable $e) {
                    $failed[] = $table;
                }
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            return ['ok' => $failed === [], 'driver' => 'pdo', 'message' => $failed === [] ? 'ok' : '部分回滚失败', 'dropped' => $dropped, 'failed' => $failed];
        } catch (Throwable $e) {
            return ['ok' => false, 'driver' => 'pdo', 'message' => '回滚连接失败：' . $e->getMessage(), 'dropped' => 0, 'failed' => $validTables];
        }
    }

    return ['ok' => false, 'driver' => 'none', 'message' => '环境缺少 mysqli 或 pdo_mysql 扩展', 'dropped' => 0, 'failed' => $validTables];
}

/**
 * 生成 config.php 内容。
 *
 * @param array<string, mixed> $db
 */
function installer_generate_config_php(array $db): string
{
    $lockGuard = <<<'PHP'
// 未安装（缺少安装锁）时：自动跳转到在线安装向导
if (!is_file(__DIR__ . '/install/install.lock')) {
    header('Location: /install/');
    exit;
}
PHP;

    $config = [
        'db' => [
            'host' => (string) ($db['host'] ?? '127.0.0.1'),
            'port' => (int) ($db['port'] ?? 3306),
            'dbname' => (string) ($db['dbname'] ?? ''),
            'username' => (string) ($db['username'] ?? ''),
            'password' => (string) ($db['password'] ?? ''),
            // 安装固定使用 utf8mb4，避免用户误选导致表/索引不兼容
            'charset' => 'utf8mb4',
            'prefix' => (string) ($db['prefix'] ?? 'em_'),
        ],
        'auth' => [
            'session_key' => 'em_admin_auth',
            'remember_cookie' => 'em_admin_remember',
            'remember_days_default' => 7,
            'remember_days_checked' => 365,
            'csrf_key' => 'em_admin_csrf',
            'throttle_key' => 'em_admin_login_throttle',
            'max_attempts' => 5,
            'lock_minutes' => 5,
        ],
        'avatar' => '/content/static/img/default-admin-avatar.jpg',
        'placeholder_img' => '/content/static/img/img-1.png',
        'license_urls' => [
            ['url' => 'https://emshop.ihehe.me/', 'name' => '官方线路'],
            ['url' => 'http://154.44.8.63:10000/', 'name' => '备用线路'],
        ],
    ];

    $export = var_export($config, true);
    $export = preg_replace('/^(\s*)array\s*\(/m', '$1[', (string) $export);
    $export = preg_replace('/\)(,?)$/m', ']$1', (string) $export);

    return "<?php\n\n" . $lockGuard . "\n\nreturn " . $export . ";\n";
}

/**
 * 输出一个简单的安装页 UI（不复用前台模板，避免依赖 init.php）。
 *
 * @param array<int, array{type:string, text:string}> $messages
 * @param array<string, mixed> $defaults
 */
function installer_render(array $messages, array $defaults): void
{
    $csrf = installer_csrf_token();
    $phpOk = PHP_VERSION_ID >= 70400;
    $hasMysqli = extension_loaded('mysqli');
    $hasPdo = extension_loaded('pdo_mysql');
    $systemVersion = installer_system_version();

    $lockDir = dirname(EM_INSTALL_LOCK);
    $lockDirWritable = is_dir($lockDir) && is_writable($lockDir);
    $cacheDir = EM_ROOT . '/content/cache';
    $cacheDirWritable = is_dir($cacheDir) && is_writable($cacheDir);
    $rootWritable = is_writable(EM_ROOT);
    $configWritable = installer_config_writable();

    $d = function (string $key, $fallback = '') use ($defaults) {
        return isset($defaults[$key]) ? (string) $defaults[$key] : (string) $fallback;
    };
    $db = $defaults['db'] ?? [];
    $dbHost = is_array($db) ? (string) ($db['host'] ?? '127.0.0.1') : '127.0.0.1';
    $dbPort = is_array($db) ? (string) ($db['port'] ?? '3306') : '3306';
    $dbName = is_array($db) ? (string) ($db['dbname'] ?? '') : '';
    $dbUser = is_array($db) ? (string) ($db['username'] ?? '') : '';
    $dbPass = is_array($db) ? (string) ($db['password'] ?? '') : '';
    $dbPrefix = is_array($db) ? (string) ($db['prefix'] ?? 'em_') : 'em_';

    $adminUsername = (string) (($defaults['admin']['username'] ?? '') ?: 'admin');
    $adminEmail = (string) (($defaults['admin']['email'] ?? '') ?: '');

    ?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>EMSHOP 在线安装</title>
    <link rel="stylesheet" href="/content/static/lib/layui-v2.13.5/layui/css/layui.css">
    <style>
        :root {
            --brand: #0ea5e9;
            --brand-hover: #0284c7;
            --bg: #f8fafc;
            --surface: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
            --radius-lg: 24px;
            --radius-md: 16px;
            --radius-sm: 12px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg);
            background-image: 
                radial-gradient(at 0% 0%, hsla(199, 100%, 74%, 0.12) 0px, transparent 50%),
                radial-gradient(at 100% 0%, hsla(189, 100%, 56%, 0.12) 0px, transparent 50%);
            background-attachment: fixed;
            color: var(--text-main);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }
        .wrap { max-width: 1000px; margin: 0 auto; padding: 40px 20px 60px; }
        .header { text-align: center; margin-bottom: 40px; position: relative; }
        .logo-container {
            display: flex; align-items: center; justify-content: center;
            width: 64px; height: 64px; border-radius: 14px;
            margin: 0 auto 20px; overflow: hidden;
            background: #ffffff;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }
        .logo-container img { width: 100%; height: 100%; object-fit: contain; }
        .hTitle-wrapper {
            position: relative;
            display: inline-block;
        }
        .hTitle { font-size: 28px; font-weight: 800; margin: 0 0 8px; letter-spacing: -0.5px; }
        .version-badge {
            position: absolute;
            top: -2px;
            right: -65px;
            font-size: 12px;
            font-weight: 700;
            color: #0284c7;
            background: #e0f2fe;
            padding: 2px 8px;
            border-radius: 10px;
            border: 1px solid #bae6fd;
            white-space: nowrap;
        }
        .hSub { font-size: 15px; color: var(--text-muted); margin: 0; }
        
        .sys-info {
            display: flex; justify-content: center; gap: 24px;
            margin-top: 20px; font-size: 13px; color: var(--text-muted);
        }
        .sys-info span { display: flex; align-items: center; gap: 6px; }

        .layout { display: grid; grid-template-columns: 1fr; gap: 24px; }
        @media (min-width: 900px) {
            .layout { grid-template-columns: 320px 1fr; align-items: start; }
        }

        .card {
            background: var(--surface); border-radius: var(--radius-lg);
            padding: 32px; box-shadow: var(--shadow-xl);
            border: 1px solid rgba(255,255,255,0.5);
        }
        .card-left { padding: 28px 24px; }
        .cardTitle { font-size: 16px; font-weight: 700; margin: 0 0 24px; color: var(--text-main); }
        
        .steps { position: relative; padding: 0; margin: 0 0 40px; list-style: none; }
        .steps::before {
            content: ''; position: absolute; top: 12px; bottom: 12px; left: 11px;
            width: 2px; background: var(--border); z-index: 0;
        }
        .step { position: relative; display: flex; gap: 16px; margin-bottom: 28px; z-index: 1; }
        .step:last-child { margin-bottom: 0; }
        .dot {
            width: 24px; height: 24px; border-radius: 50%; background: var(--surface);
            border: 2px solid var(--brand); color: var(--brand);
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; flex-shrink: 0;
            box-shadow: 0 0 0 4px var(--surface);
        }
        .step-content b { display: block; font-size: 14px; margin-bottom: 4px; color: var(--text-main); }
        .step-content span { display: block; font-size: 13px; color: var(--text-muted); line-height: 1.5; }

        .checks { display: flex; flex-direction: column; gap: 12px; }
        .checkRow {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 16px; background: var(--bg); border-radius: var(--radius-sm); font-size: 13px;
        }
        .checkRow b { font-weight: 600; color: var(--text-main); }
        .tag { font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
        .tag.ok { background: #dcfce7; color: #166534; }
        .tag.bad { background: #fee2e2; color: #991b1b; }
        .tag.warn { background: #fef3c7; color: #92400e; }

        .section { margin-bottom: 32px; }
        .section:last-child { margin-bottom: 0; }
        .sectionH { margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid var(--border); }
        .sectionH b { font-size: 18px; font-weight: 700; display: block; margin-bottom: 4px; }
        .sectionH span { font-size: 13px; color: var(--text-muted); }
        .row { display: grid; gap: 20px; margin-bottom: 20px; }
        @media (min-width: 600px) { .row.two { grid-template-columns: 1fr 1fr; } }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text-main); }
        input {
            width: 100%; padding: 12px 16px; font-size: 14px;
            border: 1px solid var(--border); border-radius: var(--radius-sm);
            background: var(--bg); color: var(--text-main); transition: all 0.2s;
        }
        input:focus {
            outline: none; border-color: var(--brand); background: var(--surface);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
        }
        input::placeholder { color: #94a3b8; }
        
        .actions {
            display: flex; gap: 16px; margin-top: 40px; padding-top: 24px;
            border-top: 1px solid var(--border);
        }
        button {
            flex: 1; padding: 14px 24px; font-size: 15px; font-weight: 600;
            border-radius: var(--radius-sm); border: none; cursor: pointer;
            transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        button[type="submit"] { background: var(--brand); color: white; box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3); }
        button[type="submit"]:hover { background: var(--brand-hover); transform: translateY(-1px); }
        button.secondary { background: var(--bg); color: var(--text-main); border: 1px solid var(--border); }
        button.secondary:hover { background: #e2e8f0; }
        button:disabled { opacity: 0.6; cursor: not-allowed; transform: none !important; }

        .hint { margin-top: 16px; font-size: 13px; color: var(--text-muted); text-align: center; }

        .alerts { margin-bottom: 24px; }
        .msg {
            display: flex; align-items: center; gap: 12px; padding: 16px;
            border-radius: var(--radius-md); margin-bottom: 12px; font-size: 14px; font-weight: 500;
        }
        .msg.bad { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .msg.ok { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .msg.warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .msg .ico { display: none; } /* Hide old icons */
    </style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <div class="logo-container">
            <img src="/content/static/img/logo.png" alt="EMSHOP Logo">
        </div>
        <div class="hTitle-wrapper">
            <h1 class="hTitle">EMSHOP 系统安装</h1>
            <span class="version-badge">v<?php echo htmlspecialchars($systemVersion); ?></span>
        </div>
        <p class="hSub">欢迎使用 EMSHOP，只需几步即可完成系统初始化</p>

    </div>

    <?php if (!empty($messages)): ?>
        <div class="alerts">
            <?php foreach ($messages as $m): ?>
                <div class="msg <?php echo htmlspecialchars($m['type']); ?>">
                    <p><?php echo htmlspecialchars($m['text']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="layout">
        <div class="card card-left">
            <div class="cardTitle">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color: var(--brand);"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                安装进度
            </div>
            <ul class="steps">
                <li class="step">
                    <div class="dot">1</div>
                    <div class="step-content"><b>环境检查</b><span>检查 PHP 版本、扩展与目录写权限</span></div>
                </li>
                <li class="step">
                    <div class="dot">2</div>
                    <div class="step-content"><b>配置数据库</b><span>填写数据库连接信息</span></div>
                </li>
                <li class="step">
                    <div class="dot">3</div>
                    <div class="step-content"><b>创建管理员</b><span>设置后台管理员账号</span></div>
                </li>
                <li class="step">
                    <div class="dot">4</div>
                    <div class="step-content"><b>完成安装</b><span>初始化数据结构并写入配置</span></div>
                </li>
            </ul>
            
            <div class="cardTitle" style="margin-top: 40px;">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color: var(--brand);"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                环境状态
            </div>
            <div class="checks">
                <div class="checkRow"><b>PHP 版本</b><span class="tag <?php echo $phpOk ? 'ok' : 'bad'; ?>"><?php echo htmlspecialchars(PHP_VERSION); ?></span></div>
                <div class="checkRow"><b>mysqli 扩展</b><span class="tag <?php echo $hasMysqli ? 'ok' : 'warn'; ?>"><?php echo $hasMysqli ? '已启用' : '未启用'; ?></span></div>
                <div class="checkRow"><b>pdo_mysql 扩展</b><span class="tag <?php echo $hasPdo ? 'ok' : 'warn'; ?>"><?php echo $hasPdo ? '已启用' : '未启用'; ?></span></div>
                <div class="checkRow"><b>根目录可写</b><span class="tag <?php echo $rootWritable ? 'ok' : 'bad'; ?>"><?php echo $rootWritable ? '可写' : '不可写'; ?></span></div>
                <div class="checkRow"><b>`config.php` 可写</b><span class="tag <?php echo $configWritable ? 'ok' : 'bad'; ?>"><?php echo $configWritable ? '可写' : '不可写'; ?></span></div>
                <div class="checkRow"><b>`content/cache/`</b><span class="tag <?php echo $cacheDirWritable ? 'ok' : 'bad'; ?>"><?php echo $cacheDirWritable ? '可写' : '不可写'; ?></span></div>
                <div class="checkRow"><b>`install/` 可写</b><span class="tag <?php echo $lockDirWritable ? 'ok' : 'bad'; ?>"><?php echo $lockDirWritable ? '可写' : '不可写'; ?></span></div>
            </div>
            <div class="hint" style="text-align: left; margin-top: 20px;">
                环境不满足时请先手动修复，再重新执行安装。
            </div>
        </div>

        <div class="card">
            <form id="installForm" method="post" action="?action=install">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">

                <div class="section">
                    <div class="sectionH">
                        <b>配置数据库</b>
                    </div>
                    <div class="row two">
                        <div class="form-group">
                            <label>Host</label>
                            <input name="db[host]" value="<?php echo htmlspecialchars($dbHost); ?>" placeholder="127.0.0.1">
                        </div>
                        <div class="form-group">
                            <label>端口</label>
                            <input name="db[port]" value="<?php echo htmlspecialchars($dbPort); ?>" placeholder="3306">
                        </div>
                    </div>
                    <div class="row two">
                        <div class="form-group">
                            <label>数据库名</label>
                            <input name="db[dbname]" value="<?php echo htmlspecialchars($dbName); ?>" placeholder="">
                        </div>
                        <div class="form-group">
                            <label>数据表前缀</label>
                            <input name="db[prefix]" value="<?php echo htmlspecialchars($dbPrefix); ?>" placeholder="em_">
                        </div>
                    </div>
                    <div class="row two">
                        <div class="form-group">
                            <label>数据库用户名</label>
                            <input name="db[username]" value="<?php echo htmlspecialchars($dbUser); ?>" placeholder="root">
                        </div>
                        <div class="form-group">
                            <label>数据库密码</label>
                            <input name="db[password]" value="<?php echo htmlspecialchars($dbPass); ?>" placeholder="">
                        </div>
                    </div>
                </div>

                <div class="section">
                    <div class="sectionH">
                        <b>创建管理员</b>
                    </div>
                    <div class="row two">
                        <div class="form-group">
                            <label>用户名</label>
                            <input name="admin[username]" value="<?php echo htmlspecialchars($adminUsername); ?>" placeholder="admin">
                        </div>
                        <div class="form-group">
                            <label>邮箱</label>
                            <input name="admin[email]" value="<?php echo htmlspecialchars($adminEmail); ?>" placeholder="">
                        </div>
                    </div>
                    <div class="row two">
                        <div class="form-group">
                            <label>密码</label>
                            <input name="admin[password]" value="" placeholder="至少 6 位，建议更长">
                        </div>
                        <div class="form-group">
                            <label>确认密码</label>
                            <input name="admin[password2]" value="" placeholder="">
                        </div>
                    </div>
                </div>

                <div class="actions">
                    <button class="secondary" type="button" id="btnTestDb">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                        测试数据库连接
                    </button>
                    <button type="submit" name="mode" value="install">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        执行安装
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="/content/static/lib/jquery.min.3.5.1.js"></script>
<script src="/content/static/lib/layui-v2.13.5/layui/layui.js"></script>
<script>
layui.use(function () {
  var layer = layui.layer;
  var $form = $('#installForm');
  if ($form.length === 0) return;
  var $btn = $('#btnTestDb');
  var $btnInstall = $form.find('button[type="submit"][name="mode"][value="install"]');
  var testLoadingIndex = null;
  var installLoadingIndex = null;

  function escapeHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  $btn.on('click', function () {
    if ($btn.length === 0) return;

    $btn.prop('disabled', true);
    testLoadingIndex = layer.load(1, { shade: [0.08, '#000'] });
    $.ajax({
      url: '?action=test_db',
      method: 'POST',
      data: $form.serialize(),
      dataType: 'json',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).done(function (res) {
      var ok = res && res.code === 200;
      layer.msg((res && res.msg) ? res.msg : (ok ? '连接成功' : '连接失败'));
    }).fail(function (xhr) {
      var text = xhr && xhr.responseJSON && xhr.responseJSON.msg
        ? xhr.responseJSON.msg
        : '请求失败：网络或服务异常';
      layer.msg(text);
    }).always(function () {
      if (testLoadingIndex !== null) {
        layer.close(testLoadingIndex);
        testLoadingIndex = null;
      }
      $btn.prop('disabled', false);
    });
  });

  $form.on('submit', function (e) {
    e.preventDefault();
    var formData = $form.serializeArray();
    formData.push({ name: 'mode', value: 'install' });

    $btnInstall.prop('disabled', true);
    $btn.prop('disabled', true);
    installLoadingIndex = layer.load(1, { shade: [0.16, '#000'] });
    $.ajax({
      url: '?action=install',
      method: 'POST',
      data: $.param(formData),
      dataType: 'json',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).done(function (res) {
      var ok = res && res.code === 200;
      var message = (res && res.msg) ? res.msg : (ok ? '安装完成' : '安装失败');
      if (ok) {
        var data = (res && res.data) ? res.data : {};
        var adminUrl = data.admin_url || '/admin/';
        var adminUser = data.admin_username || '';
        var adminPass = data.admin_password || '';
        var html = ''
          + '<div style="padding:18px 18px 12px;background:#f8fbfa;">'
          + '  <div style="border-radius:16px;background:#fff;border:1px solid rgba(76,125,113,.18);box-shadow:0 12px 28px rgba(15,23,42,.08);overflow:hidden;">'
          + '    <div style="padding:14px 16px;background:linear-gradient(135deg,rgba(76,125,113,.96),rgba(90,148,134,.88));color:#fff;">'
          + '      <div style="font-size:18px;font-weight:700;letter-spacing:.4px;">安装完成</div>'
          + '      <div style="margin-top:4px;font-size:12px;opacity:.9;">请使用以下信息登录后台</div>'
          + '    </div>'
          + '    <div style="padding:14px 16px 8px;line-height:1.85;color:#0f172a;">'
          + '      <div style="margin-bottom:8px;"><span style="display:inline-block;width:88px;color:#64748b;">后台地址</span><a href="' + escapeHtml(adminUrl) + '" target="_blank" style="color:#0f766e;font-weight:600;text-decoration:none;word-break:break-all;">' + escapeHtml(adminUrl) + '</a></div>'
          + '      <div style="margin-bottom:8px;"><span style="display:inline-block;width:88px;color:#64748b;">管理员账号</span><span style="display:inline-block;padding:2px 10px;border-radius:999px;background:rgba(76,125,113,.12);color:#1f5146;font-weight:700;">' + escapeHtml(adminUser) + '</span></div>'
          + '      <div style="margin-bottom:6px;"><span style="display:inline-block;width:88px;color:#64748b;">管理员密码</span><span style="display:inline-block;padding:2px 10px;border-radius:999px;background:rgba(15,23,42,.08);color:#0f172a;font-weight:700;">' + escapeHtml(adminPass) + '</span></div>'
          + '    </div>'
          + '    <div style="padding:0 16px 14px;color:#94a3b8;font-size:12px;">请妥善保存账号密码，进入后台后建议立即修改默认密码。</div>'
          + '  </div>'
          + '</div>';
        layer.open({
          type: 1,
          title: '安装成功',
          area: ['640px', '420px'],
          content: html,
          closeBtn: 0,
          shade: [0.28, '#0f172a'],
          shadeClose: false,
          btnAlign: 'c',
          btn: ['进入后台'],
          cancel: function () {
            return false;
          },
          yes: function () {
            window.location.href = adminUrl;
          }
        });
      } else {
        layer.msg(message);
      }
    }).fail(function (xhr) {
      var text = xhr && xhr.responseJSON && xhr.responseJSON.msg
        ? xhr.responseJSON.msg
        : '安装请求失败：网络或服务异常';
      layer.msg(text);
    }).always(function () {
      if (installLoadingIndex !== null) {
        layer.close(installLoadingIndex);
        installLoadingIndex = null;
      }
      $btnInstall.prop('disabled', false);
      $btn.prop('disabled', false);
    });
  });
});
</script>
</body>
</html>
    <?php
    exit;
}

$action = isset($_GET['action']) ? (string) $_GET['action'] : '';
if (installer_is_installed()) {
    if ($action === 'install' && installer_is_ajax_request()) {
        Response::error('系统已安装，安装入口已关闭');
    }
    Response::redirect('/');
}
if ($action !== 'install' && $action !== 'test_db') {
    installer_render([], []);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('非法请求');
}

installer_require_csrf();

$mode = (string) ($_POST['mode'] ?? 'test');
$db = $_POST['db'] ?? [];
$admin = $_POST['admin'] ?? [];
if (!is_array($db)) $db = [];
if (!is_array($admin)) $admin = [];

$dbClean = [
    'host' => trim((string) ($db['host'] ?? '127.0.0.1')),
    'port' => (int) ($db['port'] ?? 3306),
    'dbname' => trim((string) ($db['dbname'] ?? '')),
    'username' => trim((string) ($db['username'] ?? '')),
    'password' => (string) ($db['password'] ?? ''),
    // 安装固定使用 utf8mb4（不展示给用户，也不允许覆盖）
    'charset' => 'utf8mb4',
    'prefix' => trim((string) ($db['prefix'] ?? 'em_')),
];

$adminClean = [
    'username' => trim((string) ($admin['username'] ?? 'admin')),
    'email' => trim((string) ($admin['email'] ?? '')),
    'password' => (string) ($admin['password'] ?? ''),
    'password2' => (string) ($admin['password2'] ?? ''),
];

$messages = [];

// 基础校验
if (PHP_VERSION_ID < 70400) {
    $messages[] = ['type' => 'bad', 'text' => 'PHP 版本过低：需要 7.4+'];
}
if (!extension_loaded('mysqli') && !extension_loaded('pdo_mysql')) {
    $messages[] = ['type' => 'bad', 'text' => '缺少 mysqli 或 pdo_mysql 扩展，无法安装'];
}
if ($dbClean['dbname'] === '') {
    $messages[] = ['type' => 'bad', 'text' => '请填写数据库名'];
}
if ($dbClean['username'] === '') {
    $messages[] = ['type' => 'bad', 'text' => '请填写数据库用户名'];
}
if ($dbClean['prefix'] === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $dbClean['prefix'])) {
    $messages[] = ['type' => 'bad', 'text' => '表前缀仅允许字母/数字/下划线'];
}

$generatedConfig = installer_generate_config_php($dbClean);

// AJAX 测试数据库连接：返回 JSON，不刷新页面
if ($action === 'test_db') {
    $test = installer_test_db_connection($dbClean);
    if ($test['ok']) {
        Response::success($test['message'], ['driver' => $test['driver']]);
    }
    Response::error($test['message'], ['driver' => $test['driver']]);
}

if ($mode === 'test') {
    $test = installer_test_db_connection($dbClean);
    $messages[] = ['type' => $test['ok'] ? 'ok' : 'bad', 'text' => $test['message']];
    installer_render($messages, ['db' => $dbClean, 'admin' => $adminClean]);
}

// install 模式：进一步校验管理员信息
if ($adminClean['email'] === '' || !filter_var($adminClean['email'], FILTER_VALIDATE_EMAIL)) {
    $messages[] = ['type' => 'bad', 'text' => '请填写有效的管理员邮箱'];
}
if ($adminClean['password'] === '' || strlen($adminClean['password']) < 6) {
    $messages[] = ['type' => 'bad', 'text' => '管理员密码至少 6 位'];
}
if ($adminClean['password'] !== $adminClean['password2']) {
    $messages[] = ['type' => 'bad', 'text' => '两次输入的管理员密码不一致'];
}
if ($adminClean['username'] === '' || !preg_match('/^[a-zA-Z0-9_]{3,50}$/', $adminClean['username'])) {
    $messages[] = ['type' => 'bad', 'text' => '管理员用户名需为 3-50 位字母/数字/下划线'];
}

// 目录权限
$lockDir = dirname(EM_INSTALL_LOCK);
if (!is_dir($lockDir) || !is_writable($lockDir)) {
    $messages[] = ['type' => 'bad', 'text' => '`install/` 不可写：无法写入安装锁'];
}

$cacheDir = EM_ROOT . '/content/cache';
if (!is_dir($cacheDir) || !is_writable($cacheDir)) {
    $messages[] = ['type' => 'bad', 'text' => '`content/cache/` 不可写：系统运行时无法写缓存，请先修复目录权限'];
}

if (!is_writable(EM_ROOT)) {
    $messages[] = ['type' => 'bad', 'text' => '根目录不可写：请先修复目录权限后再安装'];
}

$configPath = EM_ROOT . '/config.php';
if (!installer_config_writable()) {
    $messages[] = ['type' => 'bad', 'text' => '`config.php` 不可写：无法写入数据库配置，请先修复文件权限'];
}

if ($messages !== []) {
    installer_fail_response($action, $messages, ['db' => $dbClean, 'admin' => $adminClean]);
}

// 连接测试（确保不会触发 Database 失败路径）
$test = installer_test_db_connection($dbClean);
if (!$test['ok']) {
    $messages[] = ['type' => 'bad', 'text' => $test['message']];
    installer_fail_response($action, $messages, ['db' => $dbClean, 'admin' => $adminClean]);
}

$snapshotBefore = installer_fetch_tables($dbClean);
if (!$snapshotBefore['ok']) {
    $messages[] = ['type' => 'bad', 'text' => '安装前读取表快照失败：' . (string) $snapshotBefore['message']];
    installer_fail_response($action, $messages, ['db' => $dbClean, 'admin' => $adminClean]);
}

// 切换到项目 Database：用内存 EM_CONFIG 启动 InstallService
define('EM_CONFIG', [
    'db' => [
        'host' => $dbClean['host'],
        'port' => $dbClean['port'],
        'dbname' => $dbClean['dbname'],
        'username' => $dbClean['username'],
        'password' => $dbClean['password'],
        'charset' => 'utf8mb4',
        'prefix' => $dbClean['prefix'] ?: 'em_',
    ],
]);

try {
    $installer = new InstallService();
    $installer->setup([
        'admin' => [
            'username' => $adminClean['username'],
            'email' => $adminClean['email'],
            'password' => $adminClean['password'],
        ],
    ]);
} catch (Throwable $e) {
    $messages[] = ['type' => 'bad', 'text' => '安装执行失败：' . $e->getMessage()];
    $snapshotAfter = installer_fetch_tables($dbClean);
    if ($snapshotAfter['ok']) {
        $beforeMap = [];
        foreach ((array) ($snapshotBefore['tables'] ?? []) as $tableName) {
            $beforeMap[strtolower((string) $tableName)] = true;
        }
        $prefixLower = strtolower((string) ($dbClean['prefix'] ?? ''));
        $newTables = [];
        foreach ((array) ($snapshotAfter['tables'] ?? []) as $tableName) {
            $tableName = (string) $tableName;
            $tableKey = strtolower($tableName);
            if (isset($beforeMap[$tableKey])) {
                continue;
            }
            if ($prefixLower !== '' && strpos($tableKey, $prefixLower) !== 0) {
                continue;
            }
            $newTables[] = $tableName;
        }
        $rollback = installer_drop_tables($dbClean, $newTables);
        if ($rollback['ok']) {
            $messages[] = ['type' => 'warn', 'text' => '检测到安装中断，已自动清理本次新建表：' . (string) $rollback['dropped'] . ' 张'];
        } else {
            $failed = (array) ($rollback['failed'] ?? []);
            $failedText = $failed === [] ? '无' : implode(', ', $failed);
            $messages[] = ['type' => 'warn', 'text' => '自动清理部分失败，请手动检查表：' . $failedText];
        }
    } else {
        $messages[] = ['type' => 'warn', 'text' => '安装失败后无法读取表快照，请手动检查并清理本次新建表'];
    }
    installer_fail_response($action, $messages, ['db' => $dbClean, 'admin' => $adminClean]);
}

// 写 config.php（失败即中断，提示先修复环境后重试）
$written = @file_put_contents($configPath, $generatedConfig, LOCK_EX);
if ($written === false) {
    $messages[] = ['type' => 'bad', 'text' => '写入 config.php 失败：请先修复目录权限后重试安装'];
    installer_fail_response($action, $messages, ['db' => $dbClean, 'admin' => $adminClean]);
}

// 写安装锁（用于禁用安装入口）
$lockPayload = json_encode([
    'installed_at' => date('c'),
    'db' => [
        'host' => $dbClean['host'],
        'port' => $dbClean['port'],
        'dbname' => $dbClean['dbname'],
        'prefix' => $dbClean['prefix'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@file_put_contents(EM_INSTALL_LOCK, (string) $lockPayload, LOCK_EX);

installer_success_response(
    $action,
    '安装完成：已初始化数据库并写入安装锁。',
    ['db' => $dbClean, 'admin' => $adminClean],
    [
        'admin_url' => installer_admin_url(),
        'admin_username' => $adminClean['username'],
        'admin_password' => $adminClean['password'],
    ]
);

