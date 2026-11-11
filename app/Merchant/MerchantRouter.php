<?php

declare(strict_types=1);

namespace App\Merchant;

use App\Merchant\Controller\AppstoreController;
use App\Merchant\Controller\BlogCategoryController;
use App\Merchant\Controller\BlogController;
use App\Merchant\Controller\CategoryController;
use App\Merchant\Controller\FinanceController;
use App\Merchant\Controller\GoodsController;
use App\Merchant\Controller\HomeController;
use App\Merchant\Controller\MediaController;
use App\Merchant\Controller\NaviController;
use App\Merchant\Controller\NotFoundController;
use App\Merchant\Controller\OrderController;
use App\Merchant\Controller\PageController;
use App\Merchant\Controller\PluginController;
use App\Merchant\Controller\SettingsController;
use App\Merchant\Controller\TemplateController;
use App\Merchant\Controller\UploadController;
use App\Merchant\Controller\WithdrawController;

/**
 * 商户后台路由分发器。
 *
 * 接管 /user/merchant 下**不带扩展名**的路径（/user/merchant/xxx）。
 * 物理文件（user/merchant/*.php、静态资源）仍由 Web 服务器直接命中，过渡期两套并存。
 */
final class MerchantRouter
{
    public const PREFIX = '/user/merchant';

    private const ROUTES = [
        ''              => [HomeController::class, 'index'],
        'home'          => [HomeController::class, 'index'],
        'category'      => [CategoryController::class, 'index'],
        'navi'          => [NaviController::class, 'index'],
        'page'          => [PageController::class, 'index'],
        'blog'          => [BlogController::class, 'index'],
        'blog_category' => [BlogCategoryController::class, 'index'],
        'finance'       => [FinanceController::class, 'index'],
        'withdraw'      => [WithdrawController::class, 'index'],
        'order'         => [OrderController::class, 'index'],
        'settings'      => [SettingsController::class, 'index'],
        'media'         => [MediaController::class, 'index'],
        'upload'        => [UploadController::class, 'index'],
        'template'      => [TemplateController::class, 'index'],
        'plugin'        => [PluginController::class, 'index'],
        'appstore'      => [AppstoreController::class, 'index'],
        'goods'         => [GoodsController::class, 'index'],
    ];

    /** @var MerchantRouter|null */
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

    public function handleCurrentRequest(): bool
    {
        $path = self::currentPath();
        if ($path === null || !self::isMerchantPath($path)) {
            return false;
        }

        $this->dispatch($path);
        return true;
    }

    /**
     * 是否属于商户后台路由：/user/merchant 开头且不带扩展名。
     */
    public static function isMerchantPath(string $path): bool
    {
        $path = '/' . trim($path, '/');
        if ($path !== self::PREFIX && strpos($path, self::PREFIX . '/') !== 0) {
            return false;
        }

        $rest = trim(substr($path, strlen(self::PREFIX)), '/');
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
     * @return array{class: class-string, action: string}|null
     */
    public function resolve(string $path): ?array
    {
        $path = '/' . trim($path, '/');
        if (!self::isMerchantPath($path)) {
            return null;
        }

        $rest = trim(substr($path, strlen(self::PREFIX)), '/');
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

    public function dispatch(string $path): void
    {
        $route = $this->resolve($path);
        if ($route === null) {
            $route = ['class' => NotFoundController::class, 'action' => 'index'];
        }

        $class = $route['class'];
        $action = $route['action'];
        /** @var object $controller */
        $controller = new $class();
        $controller->{$action}();
    }

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
