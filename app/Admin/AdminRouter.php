<?php

declare(strict_types=1);

namespace App\Admin;

use App\Admin\Controller\AppOrderController;
use App\Admin\Controller\AppstoreBuyController;
use App\Admin\Controller\AppstoreController;
use App\Admin\Controller\AttachmentController;
use App\Admin\Controller\BlogCategoryController;
use App\Admin\Controller\BlogCommentController;
use App\Admin\Controller\BlogEditController;
use App\Admin\Controller\BlogController;
use App\Admin\Controller\BlogTagController;
use App\Admin\Controller\CacheController;
use App\Admin\Controller\CommissionController;
use App\Admin\Controller\CouponController;
use App\Admin\Controller\CurrencyController;
use App\Admin\Controller\CurrencyPopupController;
use App\Admin\Controller\DashboardController;
use App\Admin\Controller\DisclaimerController;
use App\Admin\Controller\EditorDemoController;
use App\Admin\Controller\FriendLinkController;
use App\Admin\Controller\GoodsCategoryController;
use App\Admin\Controller\GoodsController;
use App\Admin\Controller\GoodsEditController;
use App\Admin\Controller\GoodsTagController;
use App\Admin\Controller\LangController;
use App\Admin\Controller\LanguageController;
use App\Admin\Controller\LicenseController;
use App\Admin\Controller\LogoutController;
use App\Admin\Controller\LogsController;
use App\Admin\Controller\MerchantController;
use App\Admin\Controller\MerchantLevelController;
use App\Admin\Controller\MediaController;
use App\Admin\Controller\MerchantMarketController;
use App\Admin\Controller\MerchantOrderController;
use App\Admin\Controller\NaviController;
use App\Admin\Controller\NotFoundController;
use App\Admin\Controller\OrderController;
use App\Admin\Controller\PageController;
use App\Admin\Controller\PageEditController;
use App\Admin\Controller\PluginActionController;
use App\Admin\Controller\PluginController;
use App\Admin\Controller\SettingsController;
use App\Admin\Controller\PasswordController;
use App\Admin\Controller\ProfileController;
use App\Admin\Controller\RechargeController;
use App\Admin\Controller\SignController;
use App\Admin\Controller\SystemLogController;
use App\Admin\Controller\TemplateController;
use App\Admin\Controller\UpdateController;
use App\Admin\Controller\UploadController;
use App\Admin\Controller\UserLevelController;
use App\Admin\Controller\UserListController;
use App\Admin\Controller\WithdrawController;

/**
 * 后台路由分发器（新 OOP 后台）。
 *
 * 只接管后台入口路径下**不带扩展名**的路径。入口默认 /admin，
 * 可用 .env 的 EM_APP_ADMIN_ENTRY 改成 /xadmin 等（见 pathPrefix()）：
 *   {入口}          → DashboardController::index()
 *   {入口}/home     → DashboardController::index()
 *
 * 带点/带扩展名的路径不会进入本路由，交给前台 Dispatcher 或 Web 服务器静态文件。
 *
 * 为什么限制扩展名：标准 Nginx 配置把 .php 请求直接交给 PHP-FPM，
 * 不经过 index.php 的 try_files 兜底；带扩展名的路径即使到达这里也不该由框架接管。
 */
final class AdminRouter
{
    /** 默认后台路径前缀 */
    public const DEFAULT_PREFIX = '/admin';

    /** 不能用作后台入口的路径段（与固定路由 / 目录冲突） */
    private const RESERVED_ENTRIES = [
        'user', 'content', 'install', 'include', 'app', 'tools', 'server',
        'index', 'index.php', 'notify', 'return', 'test',
    ];

    /**
     * 后台路径前缀，如 /admin；可用 .env 的 EM_APP_ADMIN_ENTRY 改成 /xadmin 等。
     */
    public static function pathPrefix(): string
    {
        return defined('EM_ADMIN_PATH') ? (string) EM_ADMIN_PATH : self::DEFAULT_PREFIX;
    }

    /**
     * 把配置值规范成合法的路径段；非法值或保留值回退 admin。
     */
    public static function normalizeEntry(string $entry): string
    {
        $entry = strtolower(trim($entry, " \t/"));

        if ($entry === '' || !preg_match('/^[a-z0-9][a-z0-9_-]*$/', $entry)) {
            return 'admin';
        }

        if (in_array($entry, self::RESERVED_ENTRIES, true)) {
            return 'admin';
        }

        return $entry;
    }

    /**
     * 路由表：第一段路径 => [控制器类, 动作方法]。
     *
     * 空字符串代表 /admin 本身。
     */
    private const ROUTES = [
        ''     => [DashboardController::class, 'index'],
        'home' => [DashboardController::class, 'index'],
        'sign' => [SignController::class, 'index'],
        'navi' => [NaviController::class, 'index'],
        'friend_link' => [FriendLinkController::class, 'index'],
        'goods_tag' => [GoodsTagController::class, 'index'],
        'blog_tag' => [BlogTagController::class, 'index'],
        'blog_category' => [BlogCategoryController::class, 'index'],
        'goods_category' => [GoodsCategoryController::class, 'index'],
        'page' => [PageController::class, 'index'],
        'blog' => [BlogController::class, 'index'],
        'blog_comment' => [BlogCommentController::class, 'index'],
        'commission' => [CommissionController::class, 'index'],
        'withdraw' => [WithdrawController::class, 'index'],
        'recharge' => [RechargeController::class, 'index'],
        'user_level' => [UserLevelController::class, 'index'],
        'merchant_level' => [MerchantLevelController::class, 'index'],
        'attachment' => [AttachmentController::class, 'index'],
        'merchant' => [MerchantController::class, 'index'],
        'merchant_order' => [MerchantOrderController::class, 'index'],
        'language' => [LanguageController::class, 'index'],
        'lang' => [LangController::class, 'index'],
        'profile' => [ProfileController::class, 'index'],
        'password' => [PasswordController::class, 'index'],
        'system_log' => [SystemLogController::class, 'index'],
        'logs' => [LogsController::class, 'index'],
        'user_list' => [UserListController::class, 'index'],
        'goods' => [GoodsController::class, 'index'],
        'goods_edit' => [GoodsEditController::class, 'index'],
        'currency' => [CurrencyController::class, 'index'],
        'currency_popup' => [CurrencyPopupController::class, 'index'],
        'disclaimer' => [DisclaimerController::class, 'index'],
        'editor_demo' => [EditorDemoController::class, 'index'],
        'template' => [TemplateController::class, 'index'],
        'plugin' => [PluginController::class, 'index'],
        'settings' => [SettingsController::class, 'index'],
        'app_order' => [AppOrderController::class, 'index'],
        'merchant_market' => [MerchantMarketController::class, 'index'],
        'appstore' => [AppstoreController::class, 'index'],
        'appstore_buy' => [AppstoreBuyController::class, 'index'],
        'coupon' => [CouponController::class, 'index'],
        'order' => [OrderController::class, 'index'],
        'blog_edit' => [BlogEditController::class, 'index'],
        'page_edit' => [PageEditController::class, 'index'],
        'media' => [MediaController::class, 'index'],
        'upload' => [UploadController::class, 'index'],
        'update' => [UpdateController::class, 'index'],
        'license' => [LicenseController::class, 'index'],
        'logout' => [LogoutController::class, 'index'],
        'plugin_action' => [PluginActionController::class, 'index'],
        'cache' => [CacheController::class, 'index'],
    ];

    /** @var AdminRouter|null */
    private static $instance;

    private function __construct()
    {
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 当前请求命中后台路由时执行并返回 true；否则返回 false。
     *
     * 调用方（index.php）在返回 true 后直接 exit，不再进入前台 Dispatcher。
     */
    public function handleCurrentRequest(): bool
    {
        $path = self::currentPath();
        if ($path === null || !self::isAdminPath($path)) {
            return false;
        }

        $this->dispatch($path);
        return true;
    }

    /**
     * 判断站点内路径是否属于后台路由。
     *
     * 带点的路径段（.php / .css / 文件名）一律不接管，交给 Web 服务器或前台路由。
     */
    public static function isAdminPath(string $path): bool
    {
        $prefix = self::pathPrefix();
        $path = '/' . trim($path, '/');
        if ($path !== $prefix && strpos($path, $prefix . '/') !== 0) {
            return false;
        }

        $rest = trim(substr($path, strlen($prefix)), '/');
        if ($rest === '') {
            return true;
        }

        foreach (explode('/', $rest) as $segment) {
            if ($segment === '' || strpos($segment, '.') !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * 解析后台路径，未登记的路由返回 null。
     *
     * @return array{class: class-string, action: string}|null
     */
    public function resolve(string $path): ?array
    {
        $path = '/' . trim($path, '/');
        if (!self::isAdminPath($path)) {
            return null;
        }

        $rest = trim(substr($path, strlen(self::pathPrefix())), '/');
        $segments = $rest === '' ? [] : explode('/', $rest);
        $key = $segments[0] ?? '';
        if (!isset(self::ROUTES[$key])) {
            return null;
        }

        return [
            'class'  => self::ROUTES[$key][0],
            'action' => self::ROUTES[$key][1],
        ];
    }

    /**
     * 执行路由；未登记的路径交给 404 控制器（同样经过登录守卫）。
     */
    public function dispatch(string $path): void
    {
        $route = $this->resolve($path);
        if ($route === null) {
            $route = [
                'class'  => NotFoundController::class,
                'action' => 'index',
            ];
        }

        $class = $route['class'];
        $action = $route['action'];
        /** @var object $controller */
        $controller = \App\Core\Application::instance()->container()->make($class);
        $controller->{$action}();
    }

    /**
     * 从 REQUEST_URI 还原站点内路径，并剥离子目录安装前缀。
     *
     * 规则与前台 Dispatcher 的 pathInfoFromRequestUri() 保持一致。
     */
    public static function currentPath(): ?string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = (string) parse_url($uri, PHP_URL_PATH);
        if ($path === '') {
            return null;
        }

        $basePath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
        if ($basePath !== '' && $basePath !== '/') {
            if ($path === $basePath) {
                $path = '/';
            } elseif (strpos($path, $basePath . '/') === 0) {
                $path = substr($path, strlen($basePath));
            } else {
                return null;
            }
        }

        return $path;
    }
}
