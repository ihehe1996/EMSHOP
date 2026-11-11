<?php

declare(strict_types=1);

/**
 * 轻量冒烟检查（不依赖 Composer / PHPUnit）。
 *
 * 用法：
 *   php tools/smoke.php          # CLI 基础设施检查
 *   php tools/smoke.php --http   # 额外用 php -S 检查首页 / 后台登录页
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/init.php';

// CLI 下 init.php 不启动会话；Csrf 测试需要 session，必须在任何输出之前启动
if (PHP_SAPI === 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}

$failed = 0;

function smoke_check(string $label, bool $ok): void
{
    global $failed;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

// 旧代码路径必须保持不变
smoke_check('旧类可加载：Database', class_exists('Database'));
smoke_check('旧类可加载：Config', class_exists('Config'));
smoke_check('旧类可加载：AuthService', class_exists('AuthService'));
smoke_check('旧类可加载：Csrf', class_exists('Csrf'));
smoke_check('旧常量仍存在：EM_CONFIG', defined('EM_CONFIG') && is_array(EM_CONFIG) && isset(EM_CONFIG['db']));

// 新内核基础设施
smoke_check('新类可加载：App\\Core\\Application', class_exists('App\\Core\\Application'));
smoke_check('新类可加载：App\\Core\\Container', class_exists('App\\Core\\Container'));

$app = \App\Core\Application::instance();
$container = $app->container();
smoke_check('容器可解析 Application', $container->make(\App\Core\Application::class) === $app);
smoke_check('容器可解析 Container', $container->make(\App\Core\Container::class) === $container);

// 容器行为
$container->singleton('smoke.singleton', static function () {
    return new stdClass();
});
smoke_check('singleton 只解析一次', $container->make('smoke.singleton') === $container->make('smoke.singleton'));

$container->bind('smoke.bind', static function () {
    return new stdClass();
});
smoke_check('bind 每次新建', $container->make('smoke.bind') !== $container->make('smoke.bind'));

// Cache：静态门面和容器必须拿到同一个实例
$cache = Cache::instance();
$cacheKey = 'smoke_cache_' . getmypid();

Cache::set($cacheKey, ['v' => 1], 60);
smoke_check('Cache 门面 set/get', Cache::get($cacheKey) === ['v' => 1]);
smoke_check('Cache 容器实例与门面一致', $container->make(\App\Support\Cache::class) === $cache);

$container->make(\App\Support\Cache::class)->set($cacheKey . '_svc', 'x', 60);
smoke_check('Cache 服务实例可写', Cache::get($cacheKey . '_svc') === 'x');

Cache::delete($cacheKey);
Cache::delete($cacheKey . '_svc');
smoke_check('Cache delete 生效', Cache::get($cacheKey) === null);

$hits = 0;
$rememberKey = $cacheKey . '_remember';
$first = Cache::remember($rememberKey, 'smoke', static function () use (&$hits) {
    $hits++;
    return 'first';
}, 60);
$second = Cache::remember($rememberKey, 'smoke', static function () use (&$hits) {
    $hits++;
    return 'second';
}, 60);
smoke_check('Cache remember 只执行一次', $hits === 1 && $first === 'first' && $second === 'first');

Cache::deleteGroup('smoke');
smoke_check('Cache deleteGroup 生效', Cache::get($rememberKey) === null);

// Input：门面与容器一致，去空白/数组/默认值行为不变
$oldGet = $_GET;
$oldPost = $_POST;
$_GET = [
    'g1' => '  hello  ',
    'g2' => [' a ', 'b '],
    'g3' => 5,
];
$_POST = [
    'p1' => ' world ',
    'p2' => '42',
];

smoke_check('Input GET 去空白', Input::get('g1') === 'hello');
smoke_check('Input GET 数组递归去空白', Input::get('g2') === ['a', 'b']);
smoke_check('Input GET 默认值', Input::get('missing', 'def') === 'def');
smoke_check('Input POST Int', Input::postInt('p2') === 42);
smoke_check('Input GET 字符串', Input::getStr('g1') === 'hello');
smoke_check('Input allGet', Input::allGet()['g1'] === 'hello');
smoke_check('Input 兼容别名', Input::getStrVar('g1') === 'hello' && Input::postStrVar('p1') === 'world');
smoke_check('Input 容器实例与门面一致', $container->make(\App\Support\Input::class) === Input::instance());

$_GET = $oldGet;
$_POST = $oldPost;

// Request：门面与容器一致，方法判断与 baseUrl 行为不变
$oldServer = $_SERVER;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_PJAX'] = 'true';
$_SERVER['HTTPS'] = 'on';
$_SERVER['HTTP_HOST'] = 'example.com';
$_SERVER['SCRIPT_NAME'] = '/shop/index.php';

smoke_check('Request method', Request::method() === 'POST');
smoke_check('Request isPost', Request::isPost() === true);
smoke_check('Request isGet', Request::isGet() === false);
smoke_check('Request isPjax', Request::isPjax() === true);
smoke_check('Request baseUrl', Request::baseUrl() === 'https://example.com/shop');
smoke_check('Request 容器实例与门面一致', $container->make(\App\Http\Request::class) === Request::instance());

$_SERVER['HTTP_HOST'] = "evil.com\r\nX-Test: 1";
smoke_check('Request Host 注入被拦截', Request::baseUrl() === 'https://localhost/shop');

$_SERVER = $oldServer;

// Money：门面与容器一致，严格解析与格式化行为不变
smoke_check('Money 解析整数', Money::parse('12.34') === 12340000);
smoke_check('Money 解析零值', Money::parse('0', true) === 0);
smoke_check('Money 常量', Money::SCALE === 1000000 && Money::MAX_DECIMALS === 6);
smoke_check('Money 拒绝科学计数法', Money::tryParse('1e5') === null);
smoke_check('Money 拒绝负数', Money::tryParse('-1') === null);
smoke_check('Money isValid', Money::isValid('0.000001') === true && Money::isValid('abc') === false);
smoke_check('Money format', Money::format(12340000) === '12.34' && Money::format(-5) === '-0.00');
smoke_check('Money 容器实例与门面一致', $container->make(\App\Support\Money::class) === Money::instance());

$zeroRejected = false;
try {
    Money::parse('0');
} catch (InvalidArgumentException $e) {
    $zeroRejected = true;
}
smoke_check('Money 默认拒绝 0', $zeroRejected);

// Response：服务方法会 exit，输出放到子进程验证
/**
 * 在子进程里执行一段 PHP 代码，返回 [输出, 退出码]。
 *
 * @return array{0: string, 1: int}
 */
function smoke_run_php(string $code, string $root): array
{
    $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $proc = proc_open(
        [PHP_BINARY, '-r', $code],
        [0 => ['file', $null, 'r'], 1 => ['pipe', 'w'], 2 => ['file', $null, 'w']],
        $pipes,
        $root
    );

    if (!is_resource($proc)) {
        return ['', -1];
    }

    $out = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    return [$out, proc_close($proc)];
}

$initFile = var_export($root . '/init.php', true);

[$out, $exit] = smoke_run_php('require ' . $initFile . '; Response::success("ok", ["a" => 1]);', $root);
smoke_check('Response success 输出', $out === '{"code":200,"msg":"ok","data":{"a":1}}' && $exit === 0);

[$out, $exit] = smoke_run_php('require ' . $initFile . '; Response::error("bad");', $root);
smoke_check('Response error 输出', $out === '{"code":400,"msg":"bad","data":[]}' && $exit === 0);

[$out, $exit] = smoke_run_php('require ' . $initFile . '; Response::redirect("/x");', $root);
smoke_check('Response redirect 退出', $out === '' && $exit === 0);

smoke_check('Response 容器实例与门面一致', $container->make(\App\Http\Response::class) === Response::instance());

// GuestToken：签名 Cookie 行为、篡改拒绝与容器一致性
$oldCookie = $_COOKIE;
$_COOKIE = [];

smoke_check('GuestToken 无 Cookie 时不通过验证', GuestToken::hasValidCookie() === false);

$token = GuestToken::get();
smoke_check('GuestToken 签发 32 位十六进制', preg_match('/^[0-9a-f]{32}$/', $token) === 1);
smoke_check('GuestToken 签发后验证通过', GuestToken::hasValidCookie() === true);
smoke_check('GuestToken 重复获取稳定', GuestToken::get() === $token);

$cookieValue = (string) ($_COOKIE['em_guest_token_v2'] ?? '');
$tampered = $cookieValue;
if ($tampered !== '') {
    $tampered[strlen($tampered) - 1] = $tampered[strlen($tampered) - 1] === '0' ? '1' : '0';
    $_COOKIE['em_guest_token_v2'] = $tampered;
}
smoke_check('GuestToken 篡改签名被拒绝', $tampered !== $cookieValue && GuestToken::hasValidCookie() === false);

$_COOKIE['em_guest_token_v2'] = $cookieValue;
smoke_check('GuestToken 还原后验证通过', GuestToken::hasValidCookie() === true);

$_COOKIE = ['em_guest_token' => str_repeat('a', 32)];
smoke_check('GuestToken 旧版 Cookie 不再接受', GuestToken::hasValidCookie() === false);

$_COOKIE = $oldCookie;

smoke_check('GuestToken 容器实例与门面一致', $container->make(\App\Support\GuestToken::class) === GuestToken::instance());

// Config：读写、reload 与容器一致性（用临时键，测试后清理）
$configKey = '__smoke_config_' . getmypid();
$configOk = Config::set($configKey, 'v1');
smoke_check('Config set 后当前请求可读', $configOk && Config::get($configKey) === 'v1');
smoke_check('Config 缺失键回落默认值', Config::get($configKey . '_missing', 'def') === 'def');

Config::set($configKey, 'v2');
smoke_check('Config 更新后缓存同步', Config::get($configKey) === 'v2');

Config::reload();
smoke_check('Config reload 后仍可读', Config::get($configKey) === 'v2');

Database::execute('DELETE FROM `' . Database::prefix() . 'config` WHERE `config_name` = ?', [$configKey]);
Config::reload();
smoke_check('Config 删除后回落默认值', Config::get($configKey, 'def') === 'def');

smoke_check('Config 容器实例与门面一致', $container->make(\App\Support\Config::class) === Config::instance());

// Csrf：签发、校验、宽限期、Cookie 兜底与容器一致性
$oldSession = $_SESSION;
$oldCsrfCookie = $_COOKIE['em_csrf_token'] ?? null;
unset($_COOKIE['em_csrf_token']);
$_SESSION = [];

$csrfToken = Csrf::token();
smoke_check('Csrf token 为 64 位十六进制', preg_match('/^[0-9a-f]{64}$/', $csrfToken) === 1);
smoke_check('Csrf 重复获取稳定', Csrf::token() === $csrfToken);
smoke_check('Csrf 合法 token 通过', Csrf::validate($csrfToken) === true);
smoke_check('Csrf 空 token 拒绝', Csrf::validate('') === false);
smoke_check('Csrf 伪造 token 拒绝', Csrf::validate(str_repeat('0', 64)) === false);

$newCsrfToken = Csrf::refresh();
smoke_check('Csrf refresh 生成新 token', $newCsrfToken !== $csrfToken);
smoke_check('Csrf 新 token 通过', Csrf::validate($newCsrfToken) === true);
smoke_check('Csrf 宽限期内旧 token 通过', Csrf::validate($csrfToken) === true);

Csrf::clearGrace();
smoke_check('Csrf 清除宽限期后旧 token 拒绝', Csrf::validate($csrfToken) === false);

// Cookie 双重提交兜底：session 中没有对应 token 时仍可通过
$csrfSessionKey = defined('EM_AUTH_CSRF_KEY') ? EM_AUTH_CSRF_KEY : 'csrf_token';
$csrfSecret = CsrfSecret::get();
if ($csrfSecret !== '') {
    $csrfBase = defined('EM_AUTH_CSRF_KEY') ? EM_AUTH_CSRF_KEY : 'default_csrf_key';
    $csrfDerived = hash('sha256', $csrfBase . $csrfSecret, true);

    $_COOKIE['em_csrf_token'] = $newCsrfToken . '.' . hash_hmac('sha256', $newCsrfToken, $csrfDerived);
    unset($_SESSION[$csrfSessionKey]);
    smoke_check('Csrf Cookie 兜底通过', Csrf::validate($newCsrfToken) === true);

    $_COOKIE['em_csrf_token'] = $newCsrfToken . '.' . str_repeat('0', 64);
    smoke_check('Csrf Cookie 签名篡改拒绝', Csrf::validate($newCsrfToken) === false);
} else {
    smoke_check('Csrf Cookie 兜底通过（无密钥，跳过）', true);
    smoke_check('Csrf Cookie 签名篡改拒绝（无密钥，跳过）', true);
}

// 同源兜底
$_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
smoke_check('Csrf 同源请求无 token 放行', Csrf::validateOrSameOrigin('') === true);

$_SERVER['HTTP_SEC_FETCH_SITE'] = 'cross-site';
smoke_check('Csrf 跨站请求无 token 拒绝', Csrf::validateOrSameOrigin('') === false);

unset($_SERVER['HTTP_SEC_FETCH_SITE']);
smoke_check('Csrf 无来源头无 token 拒绝', Csrf::validateOrSameOrigin('') === false);

$_SESSION = $oldSession;
if ($oldCsrfCookie === null) {
    unset($_COOKIE['em_csrf_token']);
} else {
    $_COOKIE['em_csrf_token'] = $oldCsrfCookie;
}

smoke_check('Csrf 容器实例与门面一致', $container->make(\App\Support\Csrf::class) === Csrf::instance());

// 可选 HTTP 检查
if (in_array('--http', $argv, true)) {
    $port = 18000 + random_int(0, 999);
    $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $proc = @proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root],
        [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
        $pipes,
        $root
    );

    if (!is_resource($proc)) {
        smoke_check('启动 php -S', false);
    } else {
        usleep(800000);

        $adminKey = trim((string) Config::get('admin_entry_key', ''));
        $urls = [
            '/' => 200,
            '/admin/sign.php' . ($adminKey !== '' ? '?s=' . rawurlencode($adminKey) : '') => 200,
        ];

        foreach ($urls as $path => $expect) {
            $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]);
            $body = @file_get_contents('http://127.0.0.1:' . $port . $path, false, $context);
            $status = 0;
            if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
                $status = (int) $m[1];
            }
            smoke_check('HTTP ' . $path . ' -> ' . $status, $body !== false && $status === $expect);
        }

        proc_terminate($proc);
        proc_close($proc);
    }
}

echo $failed === 0 ? "SMOKE OK\n" : "SMOKE FAILED: {$failed}\n";
exit($failed === 0 ? 0 : 1);
