<?php

declare(strict_types=1);

namespace App\User;

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
 * 物理文件（user/*.php、/user/static/ 静态资源）仍由 Web 服务器直接命中，过渡期两套并存。
 */
final class UserRouter
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
     * 当前请求命中用户中心路由时执行并返回 true。
     */
    public function handleCurrentRequest(): bool
    {
        $path = self::currentPath();
        if ($path === null || !self::isUserPath($path)) {
            return false;
        }

        $this->dispatch($path);
        return true;
    }

    /**
     * 是否属于用户中心路由：/user 开头、不带扩展名，且不属于商户后台。
     */
    public static function isUserPath(string $path): bool
    {
        $path = '/' . trim($path, '/');
        if ($path !== self::PREFIX && strpos($path, self::PREFIX . '/') !== 0) {
            return false;
        }
        if ($path === self::MERCHANT_PREFIX || strpos($path, self::MERCHANT_PREFIX . '/') === 0) {
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
     * 解析路径；未登记返回 null。
     *
     * @return array{class: class-string, action: string}|null
     */
    public function resolve(string $path): ?array
    {
        $path = '/' . trim($path, '/');
        if (!self::isUserPath($path)) {
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

    /**
     * 从 REQUEST_URI 还原站点内路径，并剥离子目录安装前缀（与前台 Dispatcher 同规则）。
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
