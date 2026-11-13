<?php

declare(strict_types=1);

namespace App\Merchant;

use App\Http\PrefixRouter;
use App\Merchant\Controller\ApplyController;
use App\Merchant\Controller\AppstoreController;
use App\Merchant\Controller\BlogEditController;
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
use App\Merchant\Controller\PageEditController;
use App\Merchant\Controller\PluginController;
use App\Merchant\Controller\SettingsController;
use App\Merchant\Controller\TemplateController;
use App\Merchant\Controller\UploadController;
use App\Merchant\Controller\WithdrawController;

/**
 * 商户后台路由分发器。
 *
 * 接管 /user/merchant 下**不带扩展名**的路径（/user/merchant/xxx）。
 * 带点/带扩展名的路径（静态资源）不接管，交给 Web 服务器或前台路由。
 *
 * 解析 / 分发 / REQUEST_URI 还原等公共逻辑在 App\Http\PrefixRouter。
 */
final class MerchantRouter extends PrefixRouter
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
        'apply'         => [ApplyController::class, 'index'],
        'blog_edit'     => [BlogEditController::class, 'index'],
        'page_edit'     => [PageEditController::class, 'index'],
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

    /**
     * 是否属于商户后台路由：/user/merchant 开头且不带扩展名。
     */
    public static function isMerchantPath(string $path): bool
    {
        return self::instance()->isPath($path);
    }

    protected function prefix(): string
    {
        return self::PREFIX;
    }

    protected function routes(): array
    {
        return self::ROUTES;
    }

    protected function notFoundRoute(): array
    {
        return ['class' => NotFoundController::class, 'action' => 'index'];
    }
}
