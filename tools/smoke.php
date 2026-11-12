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

// 新内核基础设施
smoke_check('新类可加载：App\\Core\\Application', class_exists('App\\Core\\Application'));
smoke_check('新类可加载：App\\Core\\Container', class_exists('App\\Core\\Container'));

$app = \App\Core\Application::instance();
$container = $app->container();
smoke_check('容器可解析 Application', $container->make(\App\Core\Application::class) === $app);
smoke_check('容器可解析 Container', $container->make(\App\Core\Container::class) === $container);

// 后台路由（app/Admin）：/admin 与无扩展名路径由框架接管
smoke_check('新类可加载：App\\Admin\\AdminRouter', class_exists('App\\Admin\\AdminRouter'));
smoke_check('新类可加载：App\\Admin\\Controller\\AbstractAdminController', class_exists('App\\Admin\\Controller\\AbstractAdminController'));
smoke_check('新类可加载：App\\Admin\\Controller\\BaseAdminController', class_exists('App\\Admin\\Controller\\BaseAdminController'));
smoke_check('新类可加载：App\\Admin\\Controller\\DashboardController', class_exists('App\\Admin\\Controller\\DashboardController'));
smoke_check('新类可加载：App\\Admin\\Controller\\LicenseController', class_exists('App\\Admin\\Controller\\LicenseController'));
smoke_check('新类可加载：App\\Admin\\Controller\\NotFoundController', class_exists('App\\Admin\\Controller\\NotFoundController'));
smoke_check('新类可加载：App\\Admin\\Controller\\SignController', class_exists('App\\Admin\\Controller\\SignController'));
smoke_check('新类可加载：App\\Admin\\Controller\\NaviController', class_exists('App\\Admin\\Controller\\NaviController'));
smoke_check('新类可加载：App\\Admin\\Controller\\FriendLinkController', class_exists('App\\Admin\\Controller\\FriendLinkController'));
smoke_check('新类可加载：App\\Admin\\Controller\\GoodsTagController', class_exists('App\\Admin\\Controller\\GoodsTagController'));
smoke_check('新类可加载：App\\Admin\\Controller\\BlogTagController', class_exists('App\\Admin\\Controller\\BlogTagController'));
smoke_check('新类可加载：App\\Admin\\Controller\\BlogCategoryController', class_exists('App\\Admin\\Controller\\BlogCategoryController'));
smoke_check('新类可加载：App\\Admin\\Controller\\GoodsCategoryController', class_exists('App\\Admin\\Controller\\GoodsCategoryController'));
smoke_check('新类可加载：App\\Admin\\Controller\\PageController', class_exists('App\\Admin\\Controller\\PageController'));
smoke_check('新类可加载：App\\Admin\\Controller\\BlogController', class_exists('App\\Admin\\Controller\\BlogController'));
smoke_check('新类可加载：App\\Admin\\Controller\\BlogCommentController', class_exists('App\\Admin\\Controller\\BlogCommentController'));
smoke_check('新类可加载：App\\Admin\\Controller\\CommissionController', class_exists('App\\Admin\\Controller\\CommissionController'));
smoke_check('新类可加载：App\\Admin\\Controller\\WithdrawController', class_exists('App\\Admin\\Controller\\WithdrawController'));
smoke_check('新类可加载：App\\Admin\\Controller\\RechargeController', class_exists('App\\Admin\\Controller\\RechargeController'));
smoke_check('新类可加载：App\\Admin\\Controller\\UserLevelController', class_exists('App\\Admin\\Controller\\UserLevelController'));
smoke_check('新类可加载：App\\Admin\\Controller\\MerchantLevelController', class_exists('App\\Admin\\Controller\\MerchantLevelController'));
smoke_check('新类可加载：App\\Admin\\Controller\\AttachmentController', class_exists('App\\Admin\\Controller\\AttachmentController'));
smoke_check('新类可加载：App\\Admin\\Controller\\MerchantController', class_exists('App\\Admin\\Controller\\MerchantController'));
smoke_check('新类可加载：App\\Admin\\Controller\\MerchantOrderController', class_exists('App\\Admin\\Controller\\MerchantOrderController'));
smoke_check('新类可加载：App\\Admin\\Controller\\LanguageController', class_exists('App\\Admin\\Controller\\LanguageController'));
smoke_check('新类可加载：App\\Admin\\Controller\\LangController', class_exists('App\\Admin\\Controller\\LangController'));
smoke_check('新类可加载：App\\Admin\\Controller\\ProfileController', class_exists('App\\Admin\\Controller\\ProfileController'));
smoke_check('新类可加载：App\\Admin\\Controller\\PasswordController', class_exists('App\\Admin\\Controller\\PasswordController'));
smoke_check('新类可加载：App\\Admin\\Controller\\SystemLogController', class_exists('App\\Admin\\Controller\\SystemLogController'));
smoke_check('新类可加载：App\\Admin\\Controller\\LogsController', class_exists('App\\Admin\\Controller\\LogsController'));
smoke_check('新类可加载：App\\Admin\\Controller\\UserListController', class_exists('App\\Admin\\Controller\\UserListController'));
smoke_check('新类可加载：App\\Admin\\Controller\\GoodsController', class_exists('App\\Admin\\Controller\\GoodsController'));
smoke_check('新类可加载：App\\Admin\\Controller\\CurrencyController', class_exists('App\\Admin\\Controller\\CurrencyController'));
smoke_check('新类可加载：App\\Admin\\Controller\\CurrencyPopupController', class_exists('App\\Admin\\Controller\\CurrencyPopupController'));
smoke_check('新类可加载：App\\Admin\\Controller\\DisclaimerController', class_exists('App\\Admin\\Controller\\DisclaimerController'));
smoke_check('新类可加载：App\\Admin\\Controller\\EditorDemoController', class_exists('App\\Admin\\Controller\\EditorDemoController'));
smoke_check('新类可加载：App\\Admin\\Controller\\TemplateController', class_exists('App\\Admin\\Controller\\TemplateController'));
smoke_check('新类可加载：App\\Admin\\Controller\\PluginController', class_exists('App\\Admin\\Controller\\PluginController'));
smoke_check('新类可加载：App\\Admin\\Controller\\SettingsController', class_exists('App\\Admin\\Controller\\SettingsController'));
smoke_check('新类可加载：App\\Admin\\Controller\\AppOrderController', class_exists('App\\Admin\\Controller\\AppOrderController'));
smoke_check('新类可加载：App\\Admin\\Controller\\MerchantMarketController', class_exists('App\\Admin\\Controller\\MerchantMarketController'));
smoke_check('新类可加载：App\\Admin\\Controller\\AppstoreBuyController', class_exists('App\\Admin\\Controller\\AppstoreBuyController'));
smoke_check('新类可加载：App\\Admin\\Controller\\CouponController', class_exists('App\\Admin\\Controller\\CouponController'));
smoke_check('新类可加载：App\\Admin\\Controller\\OrderController', class_exists('App\\Admin\\Controller\\OrderController'));
smoke_check('新类可加载：App\\Admin\\Controller\\MediaController', class_exists('App\\Admin\\Controller\\MediaController'));
smoke_check('新类可加载：App\\Admin\\Controller\\UploadController', class_exists('App\\Admin\\Controller\\UploadController'));
smoke_check('新类可加载：App\\Admin\\Controller\\UpdateController', class_exists('App\\Admin\\Controller\\UpdateController'));
smoke_check('新类可加载：App\\Admin\\Controller\\BlogEditController', class_exists('App\\Admin\\Controller\\BlogEditController'));
smoke_check('新类可加载：App\\Admin\\Controller\\PageEditController', class_exists('App\\Admin\\Controller\\PageEditController'));
smoke_check('新类可加载：App\\Admin\\Controller\\AppstoreController', class_exists('App\\Admin\\Controller\\AppstoreController'));
smoke_check('新类可加载：App\\Admin\\Controller\\GoodsEditController', class_exists('App\\Admin\\Controller\\GoodsEditController'));
smoke_check('新类可加载：App\\Admin\\Controller\\LogoutController', class_exists('App\\Admin\\Controller\\LogoutController'));
smoke_check('新类可加载：App\\Admin\\Controller\\PluginActionController', class_exists('App\\Admin\\Controller\\PluginActionController'));
smoke_check('新类可加载：App\\Admin\\Controller\\CacheController', class_exists('App\\Admin\\Controller\\CacheController'));
smoke_check('新类可加载：App\\Admin\\AdminShell', class_exists('App\\Admin\\AdminShell'));
smoke_check('新类可加载：App\\User\\UserRouter', class_exists('App\\User\\UserRouter'));
smoke_check('新类可加载：App\\User\\UserShell', class_exists('App\\User\\UserShell'));
smoke_check('新类可加载：App\\Merchant\\MerchantRouter', class_exists('App\\Merchant\\MerchantRouter'));
smoke_check('新类可加载：App\\Merchant\\MerchantShell', class_exists('App\\Merchant\\MerchantShell'));

// 后台翻译函数（原内联在 admin/global.php，新后台复用旧框架壳时需要）
require_once $root . '/include/lib/AdminLang.php';
$langKey = 'smoke_lang_' . getmypid();
smoke_check('后台翻译函数可用', function_exists('t') && t($langKey) === $langKey);

$adminRouter = \App\Admin\AdminRouter::instance();
smoke_check('后台路由容器实例与单例一致', $container->make(\App\Admin\AdminRouter::class) === $adminRouter);

smoke_check('后台路径识别 ' . EM_ADMIN_PATH, \App\Admin\AdminRouter::isAdminPath(EM_ADMIN_PATH));
smoke_check('后台路径识别 /admin/', \App\Admin\AdminRouter::isAdminPath(EM_ADMIN_PATH . '/'));
smoke_check('后台路径识别 /admin/home', \App\Admin\AdminRouter::isAdminPath(EM_ADMIN_PATH . '/home'));
smoke_check('后台路径忽略 .php', !\App\Admin\AdminRouter::isAdminPath(EM_ADMIN_PATH . '/goods.php'));
smoke_check('后台路径忽略静态资源', !\App\Admin\AdminRouter::isAdminPath(EM_ADMIN_PATH . '/static/css/admin.css'));
smoke_check('后台路径忽略 /administrator', !\App\Admin\AdminRouter::isAdminPath('/administrator'));

if (EM_ADMIN_PATH !== \App\Admin\AdminRouter::DEFAULT_PREFIX) {
    smoke_check('自定义入口时旧 /admin 不再接管', !\App\Admin\AdminRouter::isAdminPath(\App\Admin\AdminRouter::DEFAULT_PREFIX)
        && $adminRouter->resolve(\App\Admin\AdminRouter::DEFAULT_PREFIX) === null);
}

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH);
smoke_check(
    '后台路由 ' . EM_ADMIN_PATH . ' → DashboardController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\DashboardController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/home/');
smoke_check(
    '后台路由 /admin/home/ → DashboardController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\DashboardController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/sign');
smoke_check(
    '后台路由 /admin/sign → SignController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\SignController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/navi');
smoke_check(
    '后台路由 /admin/navi → NaviController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\NaviController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/friend_link');
smoke_check(
    '后台路由 /admin/friend_link → FriendLinkController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\FriendLinkController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/goods_tag');
smoke_check(
    '后台路由 /admin/goods_tag → GoodsTagController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\GoodsTagController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/blog_tag');
smoke_check(
    '后台路由 /admin/blog_tag → BlogTagController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\BlogTagController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/blog_category');
smoke_check(
    '后台路由 /admin/blog_category → BlogCategoryController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\BlogCategoryController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/goods_category');
smoke_check(
    '后台路由 /admin/goods_category → GoodsCategoryController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\GoodsCategoryController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/page');
smoke_check(
    '后台路由 /admin/page → PageController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\PageController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/blog');
smoke_check(
    '后台路由 /admin/blog → BlogController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\BlogController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/blog_comment');
smoke_check(
    '后台路由 /admin/blog_comment → BlogCommentController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\BlogCommentController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/commission');
smoke_check(
    '后台路由 /admin/commission → CommissionController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\CommissionController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/withdraw');
smoke_check(
    '后台路由 /admin/withdraw → WithdrawController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\WithdrawController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/recharge');
smoke_check(
    '后台路由 /admin/recharge → RechargeController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\RechargeController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/user_level');
smoke_check(
    '后台路由 /admin/user_level → UserLevelController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\UserLevelController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/merchant_level');
smoke_check(
    '后台路由 /admin/merchant_level → MerchantLevelController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\MerchantLevelController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/attachment');
smoke_check(
    '后台路由 /admin/attachment → AttachmentController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\AttachmentController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/merchant');
smoke_check(
    '后台路由 /admin/merchant → MerchantController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\MerchantController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/merchant_order');
smoke_check(
    '后台路由 /admin/merchant_order → MerchantOrderController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\MerchantOrderController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/language');
smoke_check(
    '后台路由 /admin/language → LanguageController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\LanguageController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/lang');
smoke_check(
    '后台路由 /admin/lang → LangController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\LangController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/profile');
smoke_check(
    '后台路由 /admin/profile → ProfileController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\ProfileController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/password');
smoke_check(
    '后台路由 /admin/password → PasswordController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\PasswordController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/system_log');
smoke_check(
    '后台路由 /admin/system_log → SystemLogController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\SystemLogController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/logs');
smoke_check(
    '后台路由 /admin/logs → LogsController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\LogsController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/user_list');
smoke_check(
    '后台路由 /admin/user_list → UserListController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\UserListController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/goods');
smoke_check(
    '后台路由 /admin/goods → GoodsController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\GoodsController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/goods_edit');
smoke_check(
    '后台路由 /admin/goods_edit → GoodsEditController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\GoodsEditController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/currency');
smoke_check(
    '后台路由 /admin/currency → CurrencyController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\CurrencyController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/currency_popup');
smoke_check(
    '后台路由 /admin/currency_popup → CurrencyPopupController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\CurrencyPopupController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/disclaimer');
smoke_check(
    '后台路由 /admin/disclaimer → DisclaimerController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\DisclaimerController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/editor_demo');
smoke_check(
    '后台路由 /admin/editor_demo → EditorDemoController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\EditorDemoController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/template');
smoke_check(
    '后台路由 /admin/template → TemplateController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\TemplateController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/plugin');
smoke_check(
    '后台路由 /admin/plugin → PluginController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\PluginController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/settings');
smoke_check(
    '后台路由 /admin/settings → SettingsController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\SettingsController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/app_order');
smoke_check(
    '后台路由 /admin/app_order → AppOrderController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\AppOrderController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/merchant_market');
smoke_check(
    '后台路由 /admin/merchant_market → MerchantMarketController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\MerchantMarketController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/appstore_buy');
smoke_check(
    '后台路由 /admin/appstore_buy → AppstoreBuyController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\AppstoreBuyController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/coupon');
smoke_check(
    '后台路由 /admin/coupon → CouponController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\CouponController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/order');
smoke_check(
    '后台路由 /admin/order → OrderController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\OrderController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/media');
smoke_check(
    '后台路由 /admin/media → MediaController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\MediaController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/upload');
smoke_check(
    '后台路由 /admin/upload → UploadController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\UploadController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/update');
smoke_check(
    '后台路由 /admin/update → UpdateController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\UpdateController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/license');
smoke_check(
    '后台路由 /admin/license → LicenseController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\LicenseController::class
        && $adminRoute['action'] === 'index'
);

$userRouter = \App\User\UserRouter::instance();
$merchantRouter = \App\Merchant\MerchantRouter::instance();

smoke_check(
    '用户中心路径识别 /user/home',
    \App\User\UserRouter::isUserPath('/user/home')
        && !\App\User\UserRouter::isUserPath('/user/home.php')
        && !\App\User\UserRouter::isUserPath('/user/static/css/user.css')
        && !\App\User\UserRouter::isUserPath('/user/merchant/home')
);
smoke_check(
    '商户后台路径识别 /user/merchant/home',
    \App\Merchant\MerchantRouter::isMerchantPath('/user/merchant/home')
        && !\App\Merchant\MerchantRouter::isMerchantPath('/user/merchant/home.php')
);
$userPages = [
    'home'            => \App\User\Controller\HomeController::class,
    'profile'         => \App\User\Controller\ProfileController::class,
    'address'         => \App\User\Controller\AddressController::class,
    'wallet'          => \App\User\Controller\WalletController::class,
    'balance_log'     => \App\User\Controller\BalanceLogController::class,
    'coupon'          => \App\User\Controller\CouponController::class,
    'rebate'          => \App\User\Controller\RebateController::class,
    'api'             => \App\User\Controller\ApiController::class,
    'order'           => \App\User\Controller\OrderController::class,
    'order_detail'    => \App\User\Controller\OrderDetailController::class,
    'order_poll'      => \App\User\Controller\OrderPollController::class,
    'find_order'      => \App\User\Controller\FindOrderController::class,
    'currency_switch' => \App\User\Controller\CurrencySwitchController::class,
];
$userControllersOk = true;
foreach ($userPages as $class) {
    $userControllersOk = $userControllersOk && class_exists($class);
}
smoke_check('用户中心控制器全部可加载', $userControllersOk);
$userRouteOk = true;
foreach (array_keys($userPages) as $segment) {
    $route = $userRouter->resolve('/user/' . $segment);
    $userRouteOk = $userRouteOk
        && is_array($route)
        && $route['class'] === $userPages[$segment]
        && $route['action'] === 'index';
}
smoke_check('用户中心路由全部可解析', $userRouteOk);
smoke_check(
    '用户中心目录路径 → 首页',
    ($userRouter->resolve('/user')['class'] ?? '') === \App\User\Controller\HomeController::class
);

$merchantPages = [
    'home'          => \App\Merchant\Controller\HomeController::class,
    'category'      => \App\Merchant\Controller\CategoryController::class,
    'navi'          => \App\Merchant\Controller\NaviController::class,
    'page'          => \App\Merchant\Controller\PageController::class,
    'blog'          => \App\Merchant\Controller\BlogController::class,
    'blog_category' => \App\Merchant\Controller\BlogCategoryController::class,
    'finance'       => \App\Merchant\Controller\FinanceController::class,
    'withdraw'      => \App\Merchant\Controller\WithdrawController::class,
    'order'         => \App\Merchant\Controller\OrderController::class,
    'settings'      => \App\Merchant\Controller\SettingsController::class,
    'media'         => \App\Merchant\Controller\MediaController::class,
    'upload'        => \App\Merchant\Controller\UploadController::class,
    'template'      => \App\Merchant\Controller\TemplateController::class,
    'plugin'        => \App\Merchant\Controller\PluginController::class,
    'appstore'      => \App\Merchant\Controller\AppstoreController::class,
    'goods'         => \App\Merchant\Controller\GoodsController::class,
    'apply'         => \App\Merchant\Controller\ApplyController::class,
    'blog_edit'     => \App\Merchant\Controller\BlogEditController::class,
    'page_edit'     => \App\Merchant\Controller\PageEditController::class,
];
$merchantControllersOk = true;
foreach ($merchantPages as $class) {
    $merchantControllersOk = $merchantControllersOk && class_exists($class);
}
smoke_check('商户后台控制器全部可加载', $merchantControllersOk);
$merchantRouteOk = true;
foreach (array_keys($merchantPages) as $segment) {
    $route = $merchantRouter->resolve('/user/merchant/' . $segment);
    $merchantRouteOk = $merchantRouteOk
        && is_array($route)
        && $route['class'] === $merchantPages[$segment]
        && $route['action'] === 'index';
}
smoke_check('商户后台路由全部可解析', $merchantRouteOk);
smoke_check(
    '商户后台目录路径 → 首页',
    ($merchantRouter->resolve('/user/merchant')['class'] ?? '') === \App\Merchant\Controller\HomeController::class
);
smoke_check(
    '旧 user 页面路径不再由新路由接管',
    !\App\User\UserRouter::isUserPath('/user/home.php')
        && !\App\Merchant\MerchantRouter::isMerchantPath('/user/merchant/home.php')
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/appstore');
smoke_check(
    '后台路由 /admin/appstore → AppstoreController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\AppstoreController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/logout');
smoke_check(
    '后台路由 /admin/logout → LogoutController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\LogoutController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/plugin_action');
smoke_check(
    '后台路由 /admin/plugin_action → PluginActionController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\PluginActionController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/cache');
smoke_check(
    '后台路由 /admin/cache → CacheController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\CacheController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/blog_edit');
smoke_check(
    '后台路由 /admin/blog_edit → BlogEditController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\BlogEditController::class
        && $adminRoute['action'] === 'index'
);

$adminRoute = $adminRouter->resolve(EM_ADMIN_PATH . '/page_edit');
smoke_check(
    '后台路由 /admin/page_edit → PageEditController::index',
    is_array($adminRoute)
        && $adminRoute['class'] === \App\Admin\Controller\PageEditController::class
        && $adminRoute['action'] === 'index'
);

smoke_check('后台未登记路由返回 null', $adminRouter->resolve(EM_ADMIN_PATH . '/nope') === null);

// 子目录安装：/shop/admin/home 还原成 /admin/home（与前台 Dispatcher 同一套规则）
$adminOldServer = $_SERVER;
$_SERVER['REQUEST_URI'] = '/shop' . EM_ADMIN_PATH . '/home?x=1';
$_SERVER['SCRIPT_NAME'] = '/shop/index.php';
smoke_check('后台路由剥离子目录前缀', \App\Admin\AdminRouter::currentPath() === EM_ADMIN_PATH . '/home');
$_SERVER = $adminOldServer;

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

// Mailer：配置/格式校验与容器一致性（不发起真实 SMTP 连接）
smoke_check('Mailer 配置不完整', Mailer::sendWith([], 'user@example.com', 's', '<p>x</p>') === false
    && Mailer::lastError() === '邮箱配置不完整（需填写 SMTP 服务器 / 发送人邮箱 / 密码 / 端口）');

smoke_check('Mailer 发送人格式校验', Mailer::sendWith(
    ['from_email' => 'bad', 'host' => 'smtp.example.com', 'password' => 'x', 'port' => 465],
    'user@example.com',
    's',
    '<p>x</p>'
) === false && Mailer::lastError() === '发送人邮箱格式不正确');

smoke_check('Mailer 收件人格式校验', Mailer::sendWith(
    ['from_email' => 'sender@example.com', 'host' => 'smtp.example.com', 'password' => 'x', 'port' => 465],
    'bad',
    's',
    '<p>x</p>'
) === false && Mailer::lastError() === '收件人邮箱格式不正确');

smoke_check('Mailer 下次调用重置错误', Mailer::sendWith([], 'user@example.com', 's', '<p>x</p>') === false
    && Mailer::lastError() === '邮箱配置不完整（需填写 SMTP 服务器 / 发送人邮箱 / 密码 / 端口）');

smoke_check('Mailer 容器实例与门面一致', $container->make(\App\Support\Mailer::class) === Mailer::instance());

// CsrfSecret：读取、源码解析/注入与容器一致性（不修改仓库 config.php）
$csrfSecret = CsrfSecret::get();
smoke_check('CsrfSecret 读取现有密钥', $csrfSecret !== '');

$configPath = $root . '/config.php';
clearstatcache(true, $configPath);
$configMtime = @filemtime($configPath);
$ensureSecret = CsrfSecret::ensure();
clearstatcache(true, $configPath);
smoke_check('CsrfSecret ensure 已存在时不写文件', $ensureSecret === $csrfSecret
    && @filemtime($configPath) === $configMtime);

$csrfRef = new ReflectionClass(\App\Support\CsrfSecret::class);
$csrfService = CsrfSecret::instance();
$extractSecret = $csrfRef->getMethod('extractSecret');
$extractSecret->setAccessible(true);
$injectSecret = $csrfRef->getMethod('injectSecret');
$injectSecret->setAccessible(true);
$replaceFile = $csrfRef->getMethod('replaceFile');
$replaceFile->setAccessible(true);

$newFormatSource = "<?php\n\ndefine('EM_APP_NAME', 'x');\ndefine('EM_AUTH_SECRET', 'abc123');\n";
smoke_check('CsrfSecret 解析新格式密钥', $extractSecret->invoke($csrfService, $newFormatSource) === 'abc123');

$injectedNew = $injectSecret->invoke($csrfService, "<?php\n\ndefine('EM_APP_NAME', 'x');\n", 'newsecret');
smoke_check('CsrfSecret 注入新格式', is_string($injectedNew)
    && $extractSecret->invoke($csrfService, $injectedNew) === 'newsecret'
    && strpos($injectedNew, "define('EM_AUTH_SECRET'") !== false);

try {
    token_get_all($injectedNew, TOKEN_PARSE);
    $csrfSyntaxOk = true;
} catch (Throwable $e) {
    $csrfSyntaxOk = false;
}
smoke_check('CsrfSecret 注入结果语法合法', $csrfSyntaxOk);

// 原子替换只在系统临时目录验证
$tmpConfig = sys_get_temp_dir() . '/emshop_csrf_smoke_' . getmypid() . '.php';
file_put_contents($tmpConfig, "<?php\n// old\n");
$csrfReplaced = $replaceFile->invoke($csrfService, $tmpConfig, "<?php\n// new\n");
$tmpConfigContent = (string) @file_get_contents($tmpConfig);
@unlink($tmpConfig);
smoke_check('CsrfSecret 原子替换临时文件', $csrfReplaced === true && $tmpConfigContent === "<?php\n// new\n");

smoke_check('CsrfSecret 容器实例与门面一致', $container->make(\App\Support\CsrfSecret::class) === CsrfSecret::instance());

// Emmsg：error/info 输出与容器一致性（方法会 exit，放子进程验证）
[$out, $exit] = smoke_run_php('require ' . $initFile . '; Emmsg::error("测试错误", "详情内容");', $root);
smoke_check('Emmsg error 输出', $exit === 0
    && strpos($out, '系统遇到了一点问题') !== false
    && strpos($out, '测试错误') !== false
    && strpos($out, '详情内容') !== false);

[$out, $exit] = smoke_run_php('require ' . $initFile . '; Emmsg::error("失败原因", new RuntimeException("boom"));', $root);
smoke_check('Emmsg 异常详情输出', $exit === 0
    && strpos($out, '失败原因') !== false
    && strpos($out, 'boom') !== false
    && strpos($out, '文件：') !== false);

[$out, $exit] = smoke_run_php('require ' . $initFile . '; Emmsg::info("请先登录", "操作提示", "第一步");', $root);
smoke_check('Emmsg info 输出', $exit === 0
    && strpos($out, '操作提示') !== false
    && strpos($out, '请先登录') !== false
    && strpos($out, '第一步') !== false);

[$out, $exit] = smoke_run_php('require ' . $initFile . '; Emmsg::info("去登录", "提示", null, [["label" => "立即登录", "href" => "/login", "primary" => true]]);', $root);
smoke_check('Emmsg 自定义按钮输出', $exit === 0
    && strpos($out, 'href="/login"') !== false
    && strpos($out, '立即登录') !== false);

smoke_check('Emmsg 容器实例与门面一致', $container->make(\App\Support\Emmsg::class) === Emmsg::instance());

// DisplayName：展示名称校验与容器一致性
smoke_check('DisplayName 空值拒绝', DisplayName::validate('') === '名称不能为空'
    && DisplayName::validate("  \t") === '名称不能为空');
smoke_check('DisplayName 自定义标签', DisplayName::validate('', '昵称') === '昵称不能为空');
smoke_check('DisplayName UTF-8 校验', DisplayName::validate("\xC3\x28") === '名称包含非法字符');
smoke_check('DisplayName 长度上限', DisplayName::validate('abcd', '名称', 3) === '名称最多 3 个字符');
smoke_check('DisplayName 尖括号拒绝', DisplayName::validate('<b>') === '名称不能包含 < 或 >');
smoke_check('DisplayName 控制字符拒绝', DisplayName::validate("a\x01b") === '名称包含非法控制字符');
smoke_check('DisplayName 合法名称', DisplayName::validate('正常名称 123') === null);
smoke_check('DisplayName 容器实例与门面一致', $container->make(\App\Support\DisplayName::class) === DisplayName::instance());

// Output：Response 兼容别名（会 exit，放子进程验证）
[$out, $exit] = smoke_run_php('require ' . $initFile . '; Output::ok("ok", ["a" => 1]);', $root);
smoke_check('Output ok 输出', $out === '{"code":200,"msg":"ok","data":{"a":1}}' && $exit === 0);

[$out, $exit] = smoke_run_php('require ' . $initFile . '; Output::fail("bad");', $root);
smoke_check('Output fail 输出', $out === '{"code":400,"msg":"bad","data":[]}' && $exit === 0);

[$out, $exit] = smoke_run_php('require ' . $initFile . '; Output::json(201, "created", ["id" => 5]);', $root);
smoke_check('Output json 输出', $out === '{"code":201,"msg":"created","data":{"id":5}}' && $exit === 0);

smoke_check('Output 容器实例与门面一致', $container->make(\App\Http\Output::class) === Output::instance());

// LogSanitizer：脱敏规则与容器一致性
smoke_check('LogSanitizer 空消息', LogSanitizer::sanitize('') === '');

$lsOrder = LogSanitizer::sanitize('order 20240101123045123456 done');
smoke_check('LogSanitizer 订单号脱敏', strpos($lsOrder, '2024**************56') !== false
    && strpos($lsOrder, '20240101123045123456') === false);

$lsRecharge = LogSanitizer::sanitize('recharge R20240101123045123456');
smoke_check('LogSanitizer 充值单号脱敏', strpos($lsRecharge, 'R2024**************56') !== false);

smoke_check('LogSanitizer 邮箱脱敏', LogSanitizer::sanitize('mail user@example.com') === 'mail u***@example.com');
smoke_check('LogSanitizer 手机号脱敏', LogSanitizer::sanitize('tel 13812345678') === 'tel 138****5678');
smoke_check('LogSanitizer 身份证脱敏', LogSanitizer::sanitize('id 110101199001011234') === 'id 110101********1234');

$lsHash = str_repeat('a', 32);
smoke_check('LogSanitizer 长哈希脱敏', LogSanitizer::sanitize('hash ' . $lsHash) === 'hash ***(32位哈希)');

smoke_check('LogSanitizer 敏感赋值脱敏', LogSanitizer::sanitize('password=abc token: xyz api_key=123') === 'password=*** token: *** api_key=***');
smoke_check('LogSanitizer 普通内容保留', LogSanitizer::sanitize('order_id=12345 status=paid') === 'order_id=12345 status=paid');
smoke_check('LogSanitizer 容器实例与门面一致', $container->make(\App\Support\LogSanitizer::class) === LogSanitizer::instance());

// Captcha：签发、校验、一次一用、scope 隔离与容器一致性
$oldSessionForCaptcha = $_SESSION;
$_SESSION = [];

$captchaExpr = Captcha::issue('smoke_a');
smoke_check('Captcha 题目格式', preg_match('/^\d [+\-] \d$/', $captchaExpr) === 1
    && Captcha::exists('smoke_a') === true);

preg_match('/^(\d) ([+\-]) (\d)$/', $captchaExpr, $captchaMatch);
$captchaAnswer = $captchaMatch[2] === '+'
    ? (int) $captchaMatch[1] + (int) $captchaMatch[3]
    : (int) $captchaMatch[1] - (int) $captchaMatch[3];

smoke_check('Captcha 正确答案通过', Captcha::verify((string) $captchaAnswer, 'smoke_a') === true);
smoke_check('Captcha 一次一用', Captcha::exists('smoke_a') === false);
smoke_check('Captcha 无题目时拒绝', Captcha::verify('1', 'smoke_a') === false);

Captcha::issue('smoke_b');
smoke_check('Captcha 错误答案拒绝', Captcha::verify('999', 'smoke_b') === false
    && Captcha::exists('smoke_b') === false);

Captcha::issue('smoke_c1');
Captcha::issue('smoke_c2');
smoke_check('Captcha scope 隔离', Captcha::exists('smoke_c1') === true
    && Captcha::exists('smoke_c2') === true);

$_SESSION['em_captcha_smoke_exp'] = ['answer' => 3, 'issued_at' => time() - 301];
smoke_check('Captcha 过期拒绝', Captcha::verify('3', 'smoke_exp') === false);

$_SESSION = $oldSessionForCaptcha;
smoke_check('Captcha 容器实例与门面一致', $container->make(\App\Support\Captcha::class) === Captcha::instance());

// DownloadUrlGuard：白名单、SSRF 防护与容器一致性
smoke_check('DownloadUrlGuard 同源 https 放行',
    DownloadUrlGuard::isAllowed('https://license.example.com/x.zip', 'https://license.example.com') === true);
smoke_check('DownloadUrlGuard 默认拒绝 http',
    DownloadUrlGuard::isAllowed('http://license.example.com/x.zip', 'https://license.example.com') === false);
smoke_check('DownloadUrlGuard 显式允许 http',
    DownloadUrlGuard::isAllowed('http://license.example.com/x.zip', 'https://license.example.com', true) === true);
smoke_check('DownloadUrlGuard 拒绝子域名伪装',
    DownloadUrlGuard::isAllowed('https://license.example.com.attacker.com/x.zip', 'https://license.example.com') === false);
smoke_check('DownloadUrlGuard 拒绝 userinfo',
    DownloadUrlGuard::isAllowed('https://license.example.com@attacker.com/x.zip', 'https://license.example.com') === false);
smoke_check('DownloadUrlGuard 端口必须一致',
    DownloadUrlGuard::isAllowed('https://license.example.com/x.zip', 'https://license.example.com:10000') === false
    && DownloadUrlGuard::isAllowed('https://license.example.com:10000/x.zip', 'https://license.example.com:10000') === true);
smoke_check('DownloadUrlGuard 默认端口规则',
    DownloadUrlGuard::isAllowed('https://license.example.com:443/x.zip', 'https://license.example.com') === true
    && DownloadUrlGuard::isAllowed('https://license.example.com:8443/x.zip', 'https://license.example.com') === false);

smoke_check('DownloadUrlGuard 拒绝内网 IP',
    DownloadUrlGuard::isPublicHttpUrl('http://127.0.0.1/') === false
    && DownloadUrlGuard::isPublicHttpUrl('http://169.254.169.254/latest/meta-data/') === false
    && DownloadUrlGuard::isPublicHttpUrl('http://10.0.0.1/') === false);
smoke_check('DownloadUrlGuard 拒绝非 http(s)',
    DownloadUrlGuard::isPublicHttpUrl('ftp://example.com/x') === false);
smoke_check('DownloadUrlGuard 拒绝 userinfo 地址',
    DownloadUrlGuard::isPublicHttpUrl('http://user:pass@example.com/') === false);
smoke_check('DownloadUrlGuard 公网 IP 放行',
    DownloadUrlGuard::isPublicHttpUrl('https://8.8.8.8/') === true);

smoke_check('DownloadUrlGuard 容器实例与门面一致', $container->make(\App\Support\DownloadUrlGuard::class) === DownloadUrlGuard::instance());

// HtmlSanitizer：标签/属性/URL 清洗与容器一致性
smoke_check('HtmlSanitizer 空输入', HtmlSanitizer::clean('') === '' && HtmlSanitizer::clean("   \n") === '');
smoke_check('HtmlSanitizer 纯文本转义', HtmlSanitizer::clean('a & b') === 'a &amp; b');

$hsScript = HtmlSanitizer::clean('<p>hi</p><script>alert(1)</script>');
smoke_check('HtmlSanitizer 删除脚本', strpos($hsScript, '<p>hi</p>') !== false
    && stripos($hsScript, '<script') === false
    && strpos($hsScript, 'alert(1)') === false);

$hsEvent = HtmlSanitizer::clean('<p onclick="alert(1)">hi</p>');
smoke_check('HtmlSanitizer 删除事件属性', strpos($hsEvent, 'onclick') === false
    && strpos($hsEvent, '<p>hi</p>') !== false);

$hsJsUrl = HtmlSanitizer::clean('<a href="javascript:alert(1)">x</a>');
smoke_check('HtmlSanitizer 删除伪协议', strpos($hsJsUrl, 'javascript') === false);

$hsLink = HtmlSanitizer::clean('<a href="https://example.com" target="_blank">x</a>');
smoke_check('HtmlSanitizer 安全链接加 rel', strpos($hsLink, 'href="https://example.com"') !== false
    && strpos($hsLink, 'rel="noopener noreferrer"') !== false);

$hsImg = HtmlSanitizer::clean('<img src="data:image/png;base64,AAAA">');
smoke_check('HtmlSanitizer 删除 data 图片', strpos($hsImg, 'data:') === false);

$hsUnknown = HtmlSanitizer::clean('<custom>text</custom>');
smoke_check('HtmlSanitizer 未知标签保留内容', trim($hsUnknown) === 'text');

smoke_check('HtmlSanitizer 容器实例与门面一致', $container->make(\App\Support\HtmlSanitizer::class) === HtmlSanitizer::instance());

// Option：缓存、延迟落库与容器一致性
$optKey = '__smoke_option_' . getmypid();
Option::delete($optKey);
Option::flush();
smoke_check('Option 初始缺失回落默认值', Option::get($optKey, 'def') === 'def');

smoke_check('Option set/get', Option::set($optKey, 'v1') === true && Option::get($optKey) === 'v1');
Option::flush();
$optRow = Database::fetchOne(
    'SELECT `config_value` FROM `' . Database::prefix() . 'config` WHERE `config_name` = ?',
    [$optKey]
);
smoke_check('Option flush 写入数据库', $optRow !== null && $optRow['config_value'] === 'v1');

Option::set($optKey, ['a' => 1]);
smoke_check('Option 数组 JSON 解码', Option::get($optKey) === ['a' => 1]);
Option::flush();

smoke_check('Option gets 批量', Option::gets([$optKey, $optKey . '_missing'], 'x') === [
    $optKey => ['a' => 1],
    $optKey . '_missing' => 'x',
]);

Option::delete($optKey);
smoke_check('Option delete 后读取默认值', Option::get($optKey, 'gone') === 'gone');
Option::flush();
$optRow = Database::fetchOne(
    'SELECT `config_value` FROM `' . Database::prefix() . 'config` WHERE `config_name` = ?',
    [$optKey]
);
smoke_check('Option flush 删除数据库行', $optRow === null);

Database::execute('DELETE FROM `' . Database::prefix() . 'config` WHERE `config_name` = ?', [$optKey]);
smoke_check('Option 容器实例与门面一致', $container->make(\App\Support\Option::class) === Option::instance());

// LicenseClient：线路常量、当前线路、TLS 与容器一致性（不发网络请求）
smoke_check('LicenseClient 常量保留', LicenseClient::OFFICIAL_BASE_URL === \App\Support\LicenseClient::OFFICIAL_BASE_URL
    && LicenseClient::DEV_BASE_URL === \App\Support\LicenseClient::DEV_BASE_URL);

$lcOfficial = LicenseClient::officialLines();
$lcDev = LicenseClient::devLines();
smoke_check('LicenseClient 线路归一化', count($lcOfficial) > 0
    && substr($lcOfficial[0]['url'], -1) === '/'
    && $lcOfficial[0]['name'] !== ''
    && count($lcDev) > 0
    && substr($lcDev[0]['url'], -1) === '/');

$lcExpected = (string) Config::get('license_dev_mode', '0') === '1' ? $lcDev : $lcOfficial;
smoke_check('LicenseClient 当前模式线路', LicenseClient::lines() === $lcExpected);

$lcLines = LicenseClient::lines();
$lcIndex = LicenseClient::currentLineIndex();
smoke_check('LicenseClient 当前线路', $lcIndex >= 0
    && $lcIndex < count($lcLines)
    && LicenseClient::defaultBaseUrl() === $lcLines[$lcIndex]['url']
    && LicenseClient::currentBaseUrl() === LicenseClient::defaultBaseUrl());

$lcTls = LicenseClient::tlsOptions();
if ((string) Config::get('license_insecure_tls', '0') === '1') {
    smoke_check('LicenseClient TLS 显式降级', $lcTls[CURLOPT_SSL_VERIFYPEER] === false
        && $lcTls[CURLOPT_SSL_VERIFYHOST] === 0);
} else {
    smoke_check('LicenseClient TLS 默认校验', $lcTls[CURLOPT_SSL_VERIFYPEER] === true
        && $lcTls[CURLOPT_SSL_VERIFYHOST] === 2);
}

smoke_check('LicenseClient 上报版本前置校验', LicenseClient::reportUpdateSuccess('') === false
    && LicenseClient::reportUpdateSuccess('bad version!') === false
    && LicenseClient::reportUpdateSuccess(str_repeat('a', 33)) === false);

smoke_check('LicenseClient 空更新清单短路', LicenseClient::appUpdateCheck([], 'example.com', '') === ['license' => [], 'data' => []]);

smoke_check('LicenseClient 容器实例与门面一致', $container->make(\App\Support\LicenseClient::class) === LicenseClient::instance());

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

    // 模拟 Nginx try_files：真实文件交给静态服务器，其余进 index.php。
    // 内置服务器处理不存在的路径时会直接 404，这里强制回退到根入口，
    // 与线上 SCRIPT_NAME=/index.php 的行为保持一致。
    $routerCode = <<<'PHP'
    <?php
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $file = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\') . $path;
    if ($path !== '/' && is_file($file)) {
        return false;
    }
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\') . '/index.php';
    require $_SERVER['SCRIPT_FILENAME'];
    PHP;
    $routerFile = sys_get_temp_dir() . '/emshop_smoke_router_' . getmypid() . '.php';
    file_put_contents($routerFile, $routerCode);

    $proc = @proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root, $routerFile],
        [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
        $pipes,
        $root
    );

    if (!is_resource($proc)) {
        smoke_check('启动 php -S', false);
    } else {
        usleep(800000);

        $urls = [
            '/' => 200,
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

        // 后台新路由：未登录访问 → 302 到旧登录页（过渡期）
        $context = stream_context_create(['http' => [
            'ignore_errors'   => true,
            'timeout'         => 10,
            'follow_location' => 0,
        ]]);
        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/home', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/home 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        // 新后台业务页同样要求登录
        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/navi', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/navi 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/friend_link', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/friend_link 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/goods_tag', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/goods_tag 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/blog_tag', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/blog_tag 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/blog_category', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/blog_category 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/goods_category', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/goods_category 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/page', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/page 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/blog', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/blog 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/blog_comment', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/blog_comment 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/commission', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/commission 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/withdraw', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/withdraw 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/recharge', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/recharge 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/user_level', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/user_level 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/merchant_level', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/merchant_level 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/attachment', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/attachment 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/merchant', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/merchant 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/merchant_order', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/merchant_order 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/language', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/language 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/lang', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/lang 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/profile', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/profile 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/password', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/password 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/system_log', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/system_log 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/logs', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/logs 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/user_list', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/user_list 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/goods', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/goods 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/goods_edit?_popup=1', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/goods_edit 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        foreach ([EM_ADMIN_PATH . '/cache', EM_ADMIN_PATH . '/plugin_action?_action=list'] as $adminPath) {
            @file_get_contents('http://127.0.0.1:' . $port . $adminPath, false, $context);
            $status = 0;
            $location = '';
            foreach (($http_response_header ?? []) as $h) {
                if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                    $status = (int) $m[1];
                }
                if (stripos($h, 'Location:') === 0) {
                    $location = trim(substr($h, 9));
                }
            }
            smoke_check(
                'HTTP ' . $adminPath . ' 未登录跳转登录页',
                $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
            );
        }

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/currency', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/currency 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/currency_popup', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/currency_popup 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/disclaimer', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/disclaimer 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/editor_demo', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/editor_demo 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/template', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/template 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/plugin', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/plugin 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/settings', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/settings 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/app_order', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/app_order 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/merchant_market', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/merchant_market 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/appstore_buy', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/appstore_buy 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/coupon', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/coupon 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/order', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/order 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/media', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/media 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/upload', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/upload 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/update', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/update 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/license', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/license 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/appstore', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/appstore 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/blog_edit?_popup=1', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/blog_edit 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/page_edit?_popup=1', false, $context);
        $status = 0;
        $location = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                $status = (int) $m[1];
            }
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }
        }
        smoke_check(
            'HTTP /admin/page_edit 未登录跳转登录页',
            $status === 302 && rtrim($location, '/') === EM_ADMIN_PATH . '/sign'
        );

        // 带 .php 的路径不归后台路由，仍走前台 Dispatcher（404）
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]);
        @file_get_contents('http://127.0.0.1:' . $port . EM_ADMIN_PATH . '/not-exist.php', false, $context);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
        smoke_check('HTTP /admin/not-exist.php 不被后台路由接管', $status === 404);

        // 用户中心 / 商户后台：未登录统一跳前台登录页
        $context = stream_context_create(['http' => [
            'ignore_errors'   => true,
            'timeout'         => 10,
            'follow_location' => 0,
        ]]);
        foreach (['/user', '/user/home', '/user/merchant', '/user/merchant/home'] as $frontPath) {
            @file_get_contents('http://127.0.0.1:' . $port . $frontPath, false, $context);
            $status = 0;
            $location = '';
            foreach (($http_response_header ?? []) as $h) {
                if (preg_match('/^HTTP\/\S+\s(\d{3})/', $h, $m)) {
                    $status = (int) $m[1];
                }
                if (stripos($h, 'Location:') === 0) {
                    $location = trim(substr($h, 9));
                }
            }
            smoke_check(
                'HTTP ' . $frontPath . ' 未登录跳前台登录',
                $status === 302 && $location === '/?c=login'
            );
        }

        // 新登录页：不依赖旧 admin_entry_key 安全入口
        $signPath = EM_ADMIN_PATH . '/sign';
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]);
        $body = @file_get_contents('http://127.0.0.1:' . $port . $signPath, false, $context);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
        smoke_check(
            'HTTP /admin/sign 登录页 200',
            $status === 200 && is_string($body) && strpos($body, 'csrf_token') !== false
        );

        // POST 带非法 CSRF：不触发登录，回显错误
        $context = stream_context_create(['http' => [
            'method'          => 'POST',
            'header'          => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content'         => http_build_query([
                'account'    => 'smoke',
                'password'   => 'smoke',
                'csrf_token' => 'invalid',
            ]),
            'ignore_errors'   => true,
            'timeout'         => 10,
            'follow_location' => 0,
        ]]);
        $body = @file_get_contents('http://127.0.0.1:' . $port . $signPath, false, $context);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
        smoke_check(
            'HTTP /admin/sign 非法 CSRF 被拒绝',
            $status === 200 && is_string($body) && strpos($body, '请求已失效') !== false
        );

        proc_terminate($proc);
        proc_close($proc);
        @unlink($routerFile);
    }
}

echo $failed === 0 ? "SMOKE OK\n" : "SMOKE FAILED: {$failed}\n";
exit($failed === 0 ? 0 : 1);
