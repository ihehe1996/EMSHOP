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

// Database：连接、CRUD、事务与容器一致性（临时表测试后清理）
smoke_check('Database 驱动可用', in_array(Database::driver(), ['mysqli', 'pdo'], true));
smoke_check('Database 前缀非空', Database::prefix() !== '');
smoke_check('Database 配置完整', isset(Database::config()['dbname']) && Database::config()['dbname'] !== '');
smoke_check('Database 服务端版本', Database::serverVersion() !== '');
smoke_check('Database 版本满足建表', Database::mysqlVersionOk() === true);
smoke_check('Database 原始连接可用', is_object(Database::rawConnection()));

$dbTable = 'smoke_db_' . getmypid();
$dbFullTable = Database::prefix() . $dbTable;
Database::statement('DROP TABLE IF EXISTS `' . $dbFullTable . '`');
Database::statement('CREATE TABLE `' . $dbFullTable . '` (`id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(32) NOT NULL, `uniq` VARCHAR(32) NOT NULL, UNIQUE KEY `uniq` (`uniq`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

try {
    $dbId = Database::insert($dbTable, ['name' => 'a', 'uniq' => 'u1']);
    smoke_check('Database insert 返回自增 ID', $dbId > 0);

    $dbRow = Database::find($dbTable, $dbId);
    smoke_check('Database find 按主键读取', $dbRow !== null && $dbRow['name'] === 'a');

    $dbRow = Database::fetchOne('SELECT `name` FROM `' . $dbFullTable . '` WHERE `id` = ?', [$dbId]);
    smoke_check('Database fetchOne', $dbRow !== null && $dbRow['name'] === 'a');

    $dbRows = Database::query('SELECT `id` FROM `' . $dbFullTable . '`');
    smoke_check('Database query 返回列表', count($dbRows) === 1);

    $dbAffected = Database::update($dbTable, ['name' => 'b'], $dbId);
    smoke_check('Database update 按 ID', $dbAffected === 1 && Database::find($dbTable, $dbId)['name'] === 'b');

    $dbAffected = Database::update($dbTable, ['name' => 'c'], '`id` = ?', [$dbId]);
    smoke_check('Database update 按条件', $dbAffected === 1 && Database::find($dbTable, $dbId)['name'] === 'c');

    $dbRejected = false;
    try {
        Database::update($dbTable, ['name' => 'x'], '0 OR 1=1');
    } catch (InvalidArgumentException $e) {
        $dbRejected = true;
    }
    smoke_check('Database 拒绝拼接式 WHERE', $dbRejected);

    $dbDuplicate = false;
    try {
        Database::insert($dbTable, ['name' => 'd', 'uniq' => 'u1']);
    } catch (Throwable $e) {
        $dbDuplicate = Database::isDuplicateKeyError($e);
    }
    smoke_check('Database 识别唯一键冲突', $dbDuplicate);

    smoke_check('Database lastSqlContext', isset(Database::lastSqlContext()['sql']));

    smoke_check('Database columnExists', Database::columnExists($dbFullTable, 'name') === true
        && Database::columnExists($dbFullTable, 'missing') === false);

    // 事务回滚
    Database::begin();
    smoke_check('Database 事务内标记', Database::inTransaction() === true);
    Database::insert($dbTable, ['name' => 'rollback', 'uniq' => 'u2']);
    Database::rollBack();
    smoke_check('Database 回滚生效', Database::inTransaction() === false
        && Database::fetchOne('SELECT `id` FROM `' . $dbFullTable . '` WHERE `uniq` = ?', ['u2']) === null);

    // 事务提交
    Database::begin();
    $dbCommitId = Database::insert($dbTable, ['name' => 'commit', 'uniq' => 'u3']);
    Database::commit();
    smoke_check('Database 提交生效', Database::find($dbTable, $dbCommitId) !== null);

    // 嵌套事务：内层回滚不影响外层提交
    Database::begin();
    $dbOuterId = Database::insert($dbTable, ['name' => 'outer', 'uniq' => 'u4']);
    Database::begin();
    Database::insert($dbTable, ['name' => 'inner', 'uniq' => 'u5']);
    Database::rollBack();
    Database::commit();
    smoke_check('Database 嵌套事务内层回滚', Database::find($dbTable, $dbOuterId) !== null
        && Database::fetchOne('SELECT `id` FROM `' . $dbFullTable . '` WHERE `uniq` = ?', ['u5']) === null);
} finally {
    Database::statement('DROP TABLE IF EXISTS `' . $dbFullTable . '`');
}

smoke_check('Database 容器实例与门面一致', $container->make(\App\Support\Database::class) === Database::instance());

// Hooks：注册、执行顺序、过滤器、归属与容器一致性
$hookName = '__smoke_hook_' . getmypid();
$hookTrace = [];
$hookA = static function (...$args) use (&$hookTrace) {
    $hookTrace[] = ['a', $args];
};
$hookB = static function (...$args) use (&$hookTrace) {
    $hookTrace[] = ['b', $args];
};

Hooks::setActingPlugin('smoke_plugin');
smoke_check('Hooks add 返回 true', Hooks::add($hookName, $hookA) === true);
Hooks::add($hookName, $hookA);
Hooks::setActingPlugin(null);
Hooks::add($hookName, $hookB);

smoke_check('Hooks 回调去重', count(Hooks::getCallbacks($hookName)) === 2);
smoke_check('Hooks 按插件过滤', Hooks::getCallbacksForPlugin($hookName, 'smoke_plugin') === [$hookA]
    && Hooks::getCallbacksForPlugin($hookName, 'other') === []);

Hooks::run($hookName, [1, 2]);
smoke_check('Hooks run 按顺序执行', $hookTrace === [['a', [1, 2]], ['b', [1, 2]]]);

$hookTrace = [];
doAction($hookName, 'x');
smoke_check('Hooks doAction 全局函数', $hookTrace === [['a', ['x']], ['b', ['x']]]);

// 单次接管：只执行第一个回调，引用结果可被修改
$onceHook = $hookName . '_once';
$onceSeen = [];
Hooks::add($onceHook, static function ($input, &$ret) use (&$onceSeen) {
    $onceSeen[] = $input;
    $ret = 'first';
});
Hooks::add($onceHook, static function ($input, &$ret) use (&$onceSeen) {
    $onceSeen[] = $input;
    $ret = 'second';
});
$onceRet = null;
doOnceAction($onceHook, 'in', $onceRet);
smoke_check('Hooks doOnceAction 只执行第一个', $onceSeen === ['in'] && $onceRet === 'first');

// 多插件协作：所有回调共享同一个结果
$multiHook = $hookName . '_multi';
$multiSeen = [];
Hooks::add($multiHook, static function ($input, &$ret) use (&$multiSeen) {
    $multiSeen[] = 'a';
    $ret .= 'a';
});
Hooks::add($multiHook, static function ($input, &$ret) use (&$multiSeen) {
    $multiSeen[] = 'b';
    $ret .= 'b';
});
$multiRet = '';
doMultiAction($multiHook, 'in', $multiRet);
smoke_check('Hooks doMultiAction 共享结果', $multiSeen === ['a', 'b'] && $multiRet === 'ab');

// 过滤器链
$filterName = $hookName . '_filter';
Hooks::addFilterHook($filterName, static function ($v) {
    return $v . 'a';
});
addFilter($filterName, static function ($v) {
    return $v . 'b';
});
smoke_check('Hooks runFilter 链式处理', Hooks::runFilter($filterName, 'x') === 'xab');
smoke_check('Hooks applyFilter 全局函数', applyFilter($filterName, 'y') === 'yab');

smoke_check('Hooks 容器实例与门面一致', $container->make(\App\Support\Hooks::class) === Hooks::instance());

// MerchantContext：主站/商户上下文、商品可见性、店铺地址与容器一致性
MerchantContext::setCurrent(null);
smoke_check('MerchantContext 主站上下文', MerchantContext::currentId() === 0
    && MerchantContext::currentOwnerId() === 0
    && MerchantContext::current() === null
    && MerchantContext::isMaster() === true
    && MerchantContext::blockedReason() === '');
smoke_check('MerchantContext 主站商品可见性', MerchantContext::isGoodsVisibleToCurrentScope(['owner_id' => 0]) === true
    && MerchantContext::isGoodsVisibleToCurrentScope(['owner_id' => 9]) === false);

MerchantContext::setCurrent(['id' => 7, 'user_id' => 9, 'name' => '测试店铺']);
smoke_check('MerchantContext 商户上下文', MerchantContext::currentId() === 7
    && MerchantContext::currentOwnerId() === 9
    && MerchantContext::isMaster() === false
    && (MerchantContext::current()['name'] ?? '') === '测试店铺');
smoke_check('MerchantContext 商户商品可见性', MerchantContext::isGoodsVisibleToCurrentScope(['owner_id' => 0]) === true
    && MerchantContext::isGoodsVisibleToCurrentScope(['owner_id' => 9]) === true
    && MerchantContext::isGoodsVisibleToCurrentScope(['owner_id' => 5]) === false);

smoke_check('MerchantContext 自定义域名店铺地址', MerchantContext::storefrontUrl(['custom_domain' => 'shop.example.com']) === 'http://shop.example.com/');
$mainDomain = MerchantDomainGuard::normalizeHost((string) Config::get('main_domain', ''));
$expectedStore = $mainDomain === '' ? '' : 'http://sub.' . $mainDomain . '/';
smoke_check('MerchantContext 二级域名店铺地址', MerchantContext::storefrontUrl(['subdomain' => 'sub']) === $expectedStore);
smoke_check('MerchantContext 无域名店铺地址为空', MerchantContext::storefrontUrl([]) === '');
smoke_check('MerchantContext CLI 无最近店铺', MerchantContext::lastMerchant() === null);

MerchantContext::setCurrent(null);
smoke_check('MerchantContext 容器实例与门面一致', $container->make(\App\Support\MerchantContext::class) === MerchantContext::instance());

// Currency：实例/静态 API、访客币种与容器一致性
$currency = Currency::getInstance();
$curPrimary = $currency->getPrimary();
smoke_check('Currency 主货币存在', $curPrimary !== null);
smoke_check('Currency 列表非空', count($currency->all()) > 0);
smoke_check('Currency 容器实例与门面一致', $container->make(\App\Support\Currency::class) === Currency::service());

if ($curPrimary !== null) {
    $curCode = (string) $curPrimary['code'];
    $curSymbol = (string) ($curPrimary['symbol'] ?? '');
    $curEnabled = (int) ($curPrimary['enabled'] ?? 1) === 1;

    smoke_check('Currency 按代码查找（忽略大小写）', $currency->getByCode(strtolower($curCode)) !== null);
    smoke_check('Currency 按 ID 查找', $currency->getById((int) $curPrimary['id']) !== null);

    smoke_check('Currency displayAmount 主货币', Currency::displayAmount(12340000, '') === $curSymbol . '12.34');
    smoke_check('Currency displayMain 主货币', Currency::displayMain(12.34, '') === $curSymbol . '12.34');
    smoke_check('Currency displaySnapshot 空快照', Currency::displaySnapshot(12340000, '', 0) === $curSymbol . '12.34');
    smoke_check('Currency displaySnapshot 冻结汇率', Currency::displaySnapshot(10000000, $curCode, 1000000) === $curSymbol . '10.00');

    if ($curEnabled) {
        $oldCurrencyCookie = $_COOKIE['em_currency'] ?? null;
        $_COOKIE['em_currency'] = strtolower($curCode);
        smoke_check('Currency 访客币种 Cookie 优先', Currency::visitorCode() === $curCode);
        smoke_check('Currency 访客符号', Currency::visitorSymbol() === $curSymbol);
        smoke_check('Currency 主货币换算因子为 1', Currency::visitorFactor() === 1.0);
        smoke_check('Currency 主货币无快照', Currency::visitorSnapshot() === ['', 0]);
        if ($oldCurrencyCookie === null) {
            unset($_COOKIE['em_currency']);
        } else {
            $_COOKIE['em_currency'] = $oldCurrencyCookie;
        }

        smoke_check('Currency 切换器含主货币', strpos(Currency::switcherHtml(), 'value="' . $curCode . '"') !== false);
    }
}

// View：数据/标题/主题状态、渲染与容器一致性
$view = View::getInstance();
$view->setData(['a' => 1]);
$view->setData('b', 2);
$view->assign(['c' => 3]);
smoke_check('View 数据合并', $view->getData() === ['a' => 1, 'b' => 2, 'c' => 3]);

$view->setTitle('测试标题');
smoke_check('View 标题', $view->getTitle() === '测试标题');

$smokeThemeName = '__smoke_theme_' . getmypid();
$smokeThemeDir = $root . '/content/template/' . $smokeThemeName;
$view->setTheme($smokeThemeName);
smoke_check('View 主题', $view->getTheme() === $smokeThemeName);

@mkdir($smokeThemeDir, 0777, true);
try {
    file_put_contents(
        $smokeThemeDir . '/__smoke_body.php',
        '<div>SMOKE:<?= htmlspecialchars((string) $smoke_value) ?>:<?= htmlspecialchars((string) $extra) ?></div>'
    );
    $view->setData(['smoke_value' => 'ok']);

    ob_start();
    $view->renderStandalone('__smoke_body', ['extra' => 'x']);
    $smokeHtml = (string) ob_get_clean();
    smoke_check('View 渲染模板与数据', strpos($smokeHtml, 'SMOKE:ok:x') !== false);

    ob_start();
    $view->renderStandalone('__smoke_body', ['extra' => 'y']);
    $smokeHtml2 = (string) ob_get_clean();
    smoke_check('View 重复渲染被忽略', $smokeHtml2 === '');
} finally {
    @unlink($smokeThemeDir . '/__smoke_body.php');
    @rmdir($smokeThemeDir);
}

// View::jsonSuccess/jsonError 会 exit，放子进程验证
[$out, $exit] = smoke_run_php('require ' . $initFile . '; View::jsonSuccess("ok", ["a" => 1]);', $root);
smoke_check('View jsonSuccess 输出', $out === '{"code":200,"msg":"ok","data":{"a":1}}' && $exit === 0);

[$out, $exit] = smoke_run_php('require ' . $initFile . '; View::jsonError("bad");', $root);
smoke_check('View jsonError 输出', $out === '{"code":400,"msg":"bad","data":[]}' && $exit === 0);

smoke_check('View 容器实例与门面一致', $container->make(\App\Support\View::class) === View::service());

// Dispatcher：初始状态、访问器与容器一致性（路由解析由 HTTP 回归覆盖）
$dispatcher = Dispatcher::getInstance();
smoke_check('Dispatcher 初始动作', $dispatcher->getAction() === '_list');
smoke_check('Dispatcher 初始控制器为空', $dispatcher->getController() === '');
smoke_check('Dispatcher 初始路径参数为空', $dispatcher->getPathArgs() === []);
smoke_check('Dispatcher getArg 默认值', $dispatcher->getArg('missing', 'def') === 'def');
smoke_check('Dispatcher 初始主题为空', $dispatcher->getTheme() === '');
smoke_check('Dispatcher getView 与 View 单例一致', $dispatcher->getView() === View::getInstance());
smoke_check('Dispatcher 容器实例与门面一致', $container->make(\App\Http\Dispatcher::class) === Dispatcher::service());

// GoodsTypeManager：注册、钩子分发、切换警告与容器一致性
$gtType = 'smoketest' . getmypid();
smoke_check('GoodsTypeManager 注册类型', GoodsTypeManager::registerType($gtType, ['name' => '冒烟类型']) === true);
$gtConfig = GoodsTypeManager::getTypeConfig($gtType);
smoke_check('GoodsTypeManager 默认配置合并', $gtConfig !== null
    && $gtConfig['name'] === '冒烟类型'
    && $gtConfig['description'] === ''
    && $gtConfig['default'] === false);
smoke_check('GoodsTypeManager 非法类型名拒绝', GoodsTypeManager::registerType('Bad-Type', []) === false
    && GoodsTypeManager::getTypeConfig('Bad-Type') === null);
smoke_check('GoodsTypeManager 类型列表包含注册项', isset(GoodsTypeManager::getTypes()[$gtType]));

$gtHook = '__smoke_gt_' . getmypid();
$gtSeen = [];
GoodsTypeManager::addHook($gtHook, static function (...$args) use (&$gtSeen) {
    $gtSeen[] = $args;
});
GoodsTypeManager::doAction($gtHook, [1, 2]);
smoke_check('GoodsTypeManager 钩子执行', $gtSeen === [[1, 2]]);

addFilter($gtHook . '_f', static function ($v) {
    return $v . 'x';
});
smoke_check('GoodsTypeManager 过滤器', GoodsTypeManager::applyFilter($gtHook . '_f', 'a') === 'ax');

$gtTypeSeen = [];
GoodsTypeManager::addHook('goods_type_' . $gtType . '_render', static function (...$args) use (&$gtTypeSeen) {
    $gtTypeSeen[] = $args;
});
GoodsTypeManager::doTypeAction($gtType, 'render', ['p']);
smoke_check('GoodsTypeManager 类型钩子', $gtTypeSeen === [['p']]);

addFilter('goods_type_' . $gtType . '_xf', static function ($v) {
    return $v . 'y';
});
smoke_check('GoodsTypeManager 类型过滤器', GoodsTypeManager::applyTypeFilter($gtType, 'xf', 'b') === 'by');

Hooks::add('goods_type_' . $gtType . '_switch_warning', static function (&$warnings, $goodsId, $oldType, $newType) {
    $warnings[] = 'w';
});
smoke_check('GoodsTypeManager 切换警告收集', GoodsTypeManager::getSwitchWarnings(1, $gtType, 'other') === ['w']);

smoke_check('GoodsTypeManager 同类型切换', GoodsTypeManager::switchType(1, $gtType, $gtType) === ['success' => true, 'message' => '类型未变化']);
smoke_check('GoodsTypeManager 目标类型不存在', GoodsTypeManager::switchType(1, $gtType, 'notexisttype') === ['success' => false, 'message' => '目标类型不存在']);

smoke_check('GoodsTypeManager 容器实例与门面一致', $container->make(\App\Support\GoodsTypeManager::class) === GoodsTypeManager::instance());

// RateLimit：计数、窗口、IP 识别与容器一致性
$rlKey = '__smoke_rl_' . getmypid();
RateLimit::clear($rlKey);
smoke_check('RateLimit 初始未限流', RateLimit::attempts($rlKey) === 0
    && RateLimit::tooManyAttempts($rlKey, 3) === false
    && RateLimit::availableIn($rlKey) === 0);
smoke_check('RateLimit hit 累加', RateLimit::hit($rlKey, 60) === 1
    && RateLimit::hit($rlKey, 60) === 2
    && RateLimit::hit($rlKey, 60) === 3);
smoke_check('RateLimit attempts', RateLimit::attempts($rlKey) === 3);
smoke_check('RateLimit 超限判断', RateLimit::tooManyAttempts($rlKey, 3) === true);
smoke_check('RateLimit 窗口剩余时间', RateLimit::availableIn($rlKey) > 0);
RateLimit::clear($rlKey);
smoke_check('RateLimit clear', RateLimit::attempts($rlKey) === 0 && RateLimit::availableIn($rlKey) === 0);

// clientIp：默认只认 REMOTE_ADDR，忽略伪造的转发头；可信代理才采信
$oldServerForRl = $_SERVER;
$oldTrustedExists = Database::fetchOne(
    'SELECT 1 AS ok FROM `' . Database::prefix() . 'config` WHERE `config_name` = ?',
    ['trusted_proxies']
) !== null;
$oldTrusted = Config::get('trusted_proxies', '');
try {
    $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
    smoke_check('RateLimit 忽略不可信代理头', RateLimit::clientIp() === '203.0.113.9');

    Config::set('trusted_proxies', '203.0.113.9');
    smoke_check('RateLimit 可信代理采信转发头', RateLimit::clientIp() === '1.2.3.4');

    Config::set('trusted_proxies', '203.0.113.0/24');
    smoke_check('RateLimit 可信 CIDR 采信转发头', RateLimit::clientIp() === '1.2.3.4');

    Config::set('trusted_proxies', '2001:db8::/32');
    $_SERVER['REMOTE_ADDR'] = '2001:db8::1';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '2001:db8::99';
    smoke_check('RateLimit 可信 CIDR（IPv6）', RateLimit::clientIp() === '2001:db8::99');

    $_SERVER['REMOTE_ADDR'] = 'not-an-ip';
    smoke_check('RateLimit 非法 REMOTE_ADDR 兜底', RateLimit::clientIp() === '0.0.0.0');
} finally {
    if ($oldTrustedExists) {
        Config::set('trusted_proxies', $oldTrusted);
    } else {
        Database::execute(
            'DELETE FROM `' . Database::prefix() . 'config` WHERE `config_name` = ?',
            ['trusted_proxies']
        );
        Config::reload();
    }
    $_SERVER = $oldServerForRl;
}

smoke_check('RateLimit 容器实例与门面一致', $container->make(\App\Support\RateLimit::class) === RateLimit::instance());

// InviteToken：常量、邀请码生成、Cookie 读写与容器一致性
smoke_check('InviteToken 常量', InviteToken::COOKIE_NAME === 'em_inviter'
    && InviteToken::QUERY_PARAM === 'r'
    && InviteToken::COOKIE_LIFETIME === 86400 * 365 * 10);

$inviteCode = InviteToken::generateUniqueCode();
smoke_check('InviteToken 生成邀请码格式', preg_match('/^[A-HJ-KM-NP-Z2-9]{8}$/', $inviteCode) === 1);
smoke_check('InviteToken 生成的邀请码未被占用', Database::fetchOne(
    'SELECT `id` FROM `' . Database::prefix() . 'user` WHERE `invite_code` = ?',
    [$inviteCode]
) === null);

$oldCookieForInvite = $_COOKIE;
$_COOKIE = [];
smoke_check('InviteToken 无 Cookie 返回空串', InviteToken::getCode() === '');
InviteToken::setCookie('SMOOKE42');
smoke_check('InviteToken 写 Cookie', InviteToken::getCode() === 'SMOOKE42'
    && ($_COOKIE[InviteToken::COOKIE_NAME] ?? '') === 'SMOOKE42');
$_COOKIE = $oldCookieForInvite;

smoke_check('InviteToken 空邀请码解析', InviteToken::resolveInviterId('') === 0);
smoke_check('InviteToken 不存在邀请码解析', InviteToken::resolveInviterId('NO_SUCH_CODE_XYZ') === 0);

$invRow = Database::fetchOne(
    'SELECT `id`, `invite_code` FROM `' . Database::prefix() . 'user` WHERE `status` = 1 AND `invite_code` <> ? LIMIT 1',
    ['']
);
if ($invRow !== null) {
    $validInviteCode = (string) $invRow['invite_code'];
    smoke_check('InviteToken 解析有效邀请人', InviteToken::resolveInviterId($validInviteCode) === (int) $invRow['id']);

    $oldCookieForInvite = $_COOKIE;
    $_COOKIE = [InviteToken::COOKIE_NAME => $validInviteCode];
    smoke_check('InviteToken 当前邀请人', InviteToken::currentInviterId() === (int) $invRow['id']);
    $_COOKIE = $oldCookieForInvite;
}

// CLI 下 captureFromQuery 不写 Cookie
$oldGetForInvite = $_GET;
$oldCookieForInvite = $_COOKIE;
$_GET = ['r' => 'whatever'];
$_COOKIE = [];
InviteToken::captureFromQuery();
smoke_check('InviteToken CLI 不写 Cookie', InviteToken::getCode() === '');
$_GET = $oldGetForInvite;
$_COOKIE = $oldCookieForInvite;

smoke_check('InviteToken 容器实例与门面一致', $container->make(\App\Support\InviteToken::class) === InviteToken::instance());

// WorkerHeartbeat：常量、路径、只读状态与容器一致性（不触碰真实心跳文件）
smoke_check('WorkerHeartbeat 常量', WorkerHeartbeat::TTL === 15
    && WorkerHeartbeat::CAPABILITY_DELIVERY === 'delivery'
    && WorkerHeartbeat::WORKER_QUEUE === 'queue');

$hbDir = WorkerHeartbeat::runtimeDir();
smoke_check('WorkerHeartbeat 运行目录', substr($hbDir, -15) === '/content/server');
smoke_check('WorkerHeartbeat 宿主心跳路径', WorkerHeartbeat::hostFile() === $hbDir . '/server.heartbeat');
smoke_check('WorkerHeartbeat worker 路径', WorkerHeartbeat::workerFile('delivery') === $hbDir . '/worker.delivery.heartbeat');
smoke_check('WorkerHeartbeat 类型名过滤', WorkerHeartbeat::workerFile('../evil') === $hbDir . '/worker.evil.heartbeat'
    && WorkerHeartbeat::workerFile('!!!') === $hbDir . '/worker.unknown.heartbeat');

$hbMissing = '__smoke_worker_' . getmypid();
smoke_check('WorkerHeartbeat 不存在的 worker', WorkerHeartbeat::isAlive($hbMissing) === false
    && WorkerHeartbeat::age($hbMissing) === null);
smoke_check('WorkerHeartbeat 批量状态', WorkerHeartbeat::status([$hbMissing]) === [$hbMissing => ['alive' => false, 'age' => null]]);

smoke_check('WorkerHeartbeat 容器实例与门面一致', $container->make(\App\Support\WorkerHeartbeat::class) === WorkerHeartbeat::instance());

// MerchantDomainGuard：归一化、主站域名与冲突检查
smoke_check('MerchantDomainGuard 归一化', MerchantDomainGuard::normalizeHost('https://Example.COM:8443/a/b?x=1') === 'example.com'
    && MerchantDomainGuard::normalizeHost('*.Foo.Example.') === 'foo.example'
    && MerchantDomainGuard::normalizeHost('   ') === '');

$mdMain = MerchantDomainGuard::normalizeHost((string) Config::get('main_domain', ''));
if ($mdMain !== '') {
    $mdHosts = MerchantDomainGuard::mainHosts();
    smoke_check('MerchantDomainGuard 主站域名列表', in_array($mdMain, $mdHosts, true)
        && in_array('www.' . $mdMain, $mdHosts, true));
    smoke_check('MerchantDomainGuard 主站域名判断', MerchantDomainGuard::isMainHost($mdMain) === true
        && MerchantDomainGuard::isMainHost('www.' . $mdMain) === true
        && MerchantDomainGuard::isMainHost('sub.' . $mdMain) === false);
    smoke_check('MerchantDomainGuard 二级域名冲突主站', MerchantDomainGuard::subdomainError('www') === '该二级域名与主站域名冲突');
}

smoke_check('MerchantDomainGuard 自定义域名空值', MerchantDomainGuard::customDomainError('') === '自定义域名格式不合法');
if ($mdMain !== '') {
    smoke_check('MerchantDomainGuard 自定义域名占用主站', MerchantDomainGuard::customDomainError($mdMain) === '不能使用主站域名作为自定义域名');
}
smoke_check('MerchantDomainGuard 自定义域名可用', MerchantDomainGuard::customDomainError('smoke' . getmypid() . '.example.com') === '');
smoke_check('MerchantDomainGuard 二级域名空值', MerchantDomainGuard::subdomainError('') === '');
smoke_check('MerchantDomainGuard 二级域名可用', MerchantDomainGuard::subdomainError('smoke' . getmypid()) === '');

smoke_check('MerchantDomainGuard 容器实例与门面一致', $container->make(\App\Support\MerchantDomainGuard::class) === MerchantDomainGuard::instance());

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
