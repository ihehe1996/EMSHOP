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
