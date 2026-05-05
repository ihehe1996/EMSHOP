<?php

declare(strict_types=1);

if (defined('EM_INITIALIZED')) {
    return;
}

/**
 * 系统初始化文件。
 *
 * 负责加载配置、注册自动加载器，并初始化常用基础能力。
 */

// 启用输出缓冲，防止插件或钩子在 session/setcookie 之前产生输出
if (ob_get_level() === 0) {
    ob_start();
}
if (!defined('EM_ROOT')) {
    define('EM_ROOT', __DIR__);
}
define('EM_INITIALIZED', true);
define('EM_VERSION', '1.2.80');
define('EM_VERSION_TIMESTAMP', '1280');

require EM_ROOT . '/base.php';

// 加载站点配置（包含 db / auth / license_urls 等；内部会解析 .env 覆盖）
$emFileConfig = require EM_ROOT . '/config.php';
define('EM_CONFIG', $emFileConfig);

// PHP 8.0 字符串函数的 polyfill —— 项目需支持 PHP 7.4+
// 避免在低版本环境下代码里直接用 str_starts_with / str_contains 等抛 undefined function
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle === '' || ($needle !== '' && substr_compare($haystack, $needle, -strlen($needle)) === 0);
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

// 尽早启动 session（Csrf 验证和登录状态依赖 session，尽早启动可避免 CSRF 验证失败）
// CLI/Swoole 环境下跳过 session
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once EM_ROOT . '/include/lib/Autoloader.php';

Autoloader::register([
    EM_ROOT . '/include/lib',
    EM_ROOT . '/include/model',
    EM_ROOT . '/include/service',
    EM_ROOT . '/include/controller',
]);

Hooks::boot();

Config::load();

// 应用后台设置的时区，让 php 的 date() 与 mysql NOW() 保持一致；
// 不然 web 环境 php.ini 没有 date.timezone 时 date() 返回 UTC，
// 会导致 CommissionLogModel::createFrozen 等地方写入的时间比本地时间晚 8 小时。
$emTimezone = (string) Config::get('site_timezone', 'Asia/Shanghai');
if ($emTimezone !== '' && @date_default_timezone_set($emTimezone) === false) {
    date_default_timezone_set('Asia/Shanghai');
}

Cache::init();

// 加载模板辅助函数（提供 url_goods()、url_blog() 等模板函数）
require_once EM_ROOT . '/include/lib/TemplateHelpers.php';

// 商户（分站）上下文识别：提前到插件/模板加载之前，让后面能按商户 scope 隔离
//   - 命中 /s/{slug}/ 模式会同步改写 $_SERVER['REQUEST_URI']/PATH_INFO，让 Dispatcher 按正常路径解析
//   - 识别失败时按主站处理，不阻断请求
try {
    MerchantContext::resolve();
} catch (Throwable $e) {
    // 识别失败时按主站渲染，不阻断请求
}

// 当前请求作用域：商户命中就用 merchant_{id}，否则走主站 main
//   该 scope 决定"加载哪些插件/模板"—— 商户有自己独立的启用列表
//   同步挂到 $GLOBALS，让 TemplateStorage / Storage 等组件读到统一值
//   （后台请求还会在 admin/merchant global.php 里再覆盖一次；前台请求只靠这里）
$emCurrentScope = MerchantContext::currentId() > 0
    ? 'merchant_' . MerchantContext::currentId()
    : 'main';
$GLOBALS['__em_current_scope'] = $emCurrentScope;

// 加载插件系统：按当前 scope 读已启用插件，执行其 addAction 注册钩子
// $emHooks 由插件中的 addAction() 函数填充，供 doAction() 使用
//
// scope 解析（getRuntimeNames）：
//   - 主站 scope：加载 main 启用的所有插件
//   - 商户 scope：SYSTEM_PLUGINS（支付插件 / 商品类型 / 对接商品）强制从 main 取，
//     其它分类按商户自身 scope 取 —— 这三类属于商城底层能力，由主站统一管控；其它分类
//     给商户保留差异化扩展空间。
//
// 加载是严格按 em_plugin.is_enabled 驱动的，禁用=不加载（语义纯净）。
// 禁用商品类型的副作用（已有该类型商品在前台没类型配置、无法下单）由 OrderModel 统一报错拦截。
$emHooks = [];
try {
    $pluginModel = new PluginModel();
    $activePlugins = $pluginModel->getRuntimeNames($emCurrentScope);

    if (is_array($activePlugins) && $activePlugins !== []) {
        foreach ($activePlugins as $pluginFile) {
            $pluginPath = EM_ROOT . '/content/plugin/' . ltrim($pluginFile, '/') . '/' . ltrim($pluginFile, '/') . '.php';
            if (is_file($pluginPath)) {
                include_once $pluginPath;
            }
        }
    }
} catch (Throwable $e) {
    // 插件加载失败不影响系统启动
}

// 加载当前启用模板的系统挂载文件（按当前 scope）
try {
    $templateModel = new TemplateModel();
    $activeTemplates = array_filter([
        $templateModel->getEffectiveTheme('pc', $emCurrentScope),
        $templateModel->getEffectiveTheme('mobile', $emCurrentScope),
    ]);

    foreach (array_unique($activeTemplates) as $templateName) {
        $templatePluginFile = $templateModel->getPluginFilePath((string) $templateName);
        if (is_file($templatePluginFile)) {
            include_once $templatePluginFile;
        }
    }
} catch (Throwable $e) {
    // 模板加载失败不影响系统启动
}

// 注册商品类型：插件/模板加载完后，统一收集所有已注册的商品类型
// 此钩子的回调声明了引用参数 function(&$types)，需要直接调用以保留引用语义
// doAction 的 ...$args 展开运算符不保留引用，会触发 Warning
$types = [];
$callbacks = Hooks::getCallbacks('goods_type_register');
foreach ($callbacks as $fn) {
    call_user_func_array($fn, [&$types]);
}
foreach ($types as $type => $config) {
    GoodsTypeManager::registerType($type, $config);
}

// 访客 ?r=XXX 归因：首次进入时写 10 年 Cookie，用于注册/下单时绑定上级
// 注意：仅主站做归因。商户站不再支持推广返佣，访问 1022.em.cc/?r=XXX 时不写 cookie，
// 也避免商户子域 cookie 污染主站会话。
if (php_sapi_name() !== 'cli' && MerchantContext::currentId() === 0) {
    try {
        InviteToken::captureFromQuery();
    } catch (Throwable $e) {
        // 归因失败不影响正常请求
    }
}

// 注册关闭函数，确保 dirty 数据落库
register_shutdown_function(function () {
    try {
        Option::flush();
    } catch (Throwable $e) {
        // ignore
    }
});


// usleep(500000); // 模拟慢速环境，方便测试加载中的loading特效等，不要删除