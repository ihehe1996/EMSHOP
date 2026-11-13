<?php

declare(strict_types=1);

namespace App\User;

use App\Http\PrefixRouter;
use App\User\Controller\AddressController;
use App\User\Controller\ApiController;
use App\User\Controller\BalanceLogController;
use App\User\Controller\CouponController;
use App\User\Controller\CurrencySwitchController;
use App\User\Controller\FindOrderController;
use App\User\Controller\HomeController;
use App\User\Controller\NotFoundController;
use App\User\Controller\OrderController;
use App\User\Controller\OrderDetailController;
use App\User\Controller\OrderPollController;
use App\User\Controller\ProfileController;
use App\User\Controller\RebateController;
use App\User\Controller\WalletController;

/**
 * 用户中心路由分发器。
 *
 * 接管 /user 下**不带扩展名**的路径（/user/xxx），/user/merchant/... 由 MerchantRouter 负责。
 * 带点/带扩展名的路径（静态资源）不接管，交给 Web 服务器或前台路由。
 *
 * 解析 / 分发 / REQUEST_URI 还原等公共逻辑在 App\Http\PrefixRouter。
 */
final class UserRouter extends PrefixRouter
{
    public const PREFIX = '/user';

    /** 商户后台路径前缀：不属于用户中心路由 */
    private const MERCHANT_PREFIX = '/user/merchant';

    /**
     * 路由表：第一段路径 => [控制器类, 动作方法]。
     *
     * 空字符串代表 /user 本身。
     */
    private const ROUTES = [
        ''            => [HomeController::class, 'index'],
        'home'        => [HomeController::class, 'index'],
        'profile'     => [ProfileController::class, 'index'],
        'address'     => [AddressController::class, 'index'],
        'wallet'      => [WalletController::class, 'index'],
        'balance_log' => [BalanceLogController::class, 'index'],
        'coupon'      => [CouponController::class, 'index'],
        'rebate'      => [RebateController::class, 'index'],
        'api'         => [ApiController::class, 'index'],
        'order'       => [OrderController::class, 'index'],
        'order_detail' => [OrderDetailController::class, 'index'],
        'order_poll'   => [OrderPollController::class, 'index'],
        'find_order'   => [FindOrderController::class, 'index'],
        'currency_switch' => [CurrencySwitchController::class, 'index'],
    ];

    /** @var UserRouter|null */
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
     * 是否属于用户中心路由：/user 开头、不带扩展名，且不属于商户后台。
     */
    public static function isUserPath(string $path): bool
    {
        return self::instance()->isPath($path);
    }

    protected function prefix(): string
    {
        return self::PREFIX;
    }

    protected function excludedPrefixes(): array
    {
        return [self::MERCHANT_PREFIX];
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
