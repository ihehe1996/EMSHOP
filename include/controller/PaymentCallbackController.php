<?php

declare(strict_types=1);

/**
 * 支付回调控制器（同步跳回 + 异步通知）。
 */
final class PaymentCallbackController
{
    /**
     * 支付同步跳回处理。
     */
    public static function handleReturn(): void
    {
        $data = array_merge($_GET, $_POST);
        $plugin = self::resolvePlugin($data);
        if ($plugin === '') {
            Response::redirect(self::buildReturnRedirectUrl());
        }

        // 商户站子域名回调需切到主站 scope，让插件读取主站凭证。
        PaymentService::dispatchReturn($plugin, $data);

        // 兜底：插件未跳转时按身份回跳。
        Response::redirect(self::buildReturnRedirectUrl());
    }

    /**
     * 支付异步通知处理。
     */
    public static function handleNotify(): void
    {
        // POST 优先覆盖 GET，兼容网关混传参数。
        $data = array_merge($_GET, $_POST);
        $plugin = self::resolvePlugin($data);
        if ($plugin === '') {
            http_response_code(400);
            exit('fail: missing or unresolved plugin');
        }

        // 插件内应输出 success/fail 并 exit。
        PaymentService::dispatchNotify($plugin, $data);

        // 兜底：插件未输出时判失败。
        http_response_code(500);
        echo 'fail: no plugin handler';
    }

    /**
     * 构造同步回跳目标：
     * - 登录用户：订单详情
     * - 游客：查单页
     */
    public static function buildReturnRedirectUrl(string $orderNo = ''): string
    {
        $isLogged = session_status() !== PHP_SESSION_NONE
            ? !empty($_SESSION['em_front_user']['id'])
            : false;
        if (!$isLogged) {
            if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
                @session_start();
            }
            $isLogged = !empty($_SESSION['em_front_user']['id']);
        }

        if ($isLogged) {
            return $orderNo !== ''
                ? ('/user/order_detail.php?order_no=' . urlencode($orderNo))
                : '/user/order_detail.php';
        }

        return '/user/find_order.php';
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function resolvePlugin(array $data): string
    {
        $plugin = trim((string) Input::get('plugin', ''));
        if ($plugin === '') {
            $plugin = PaymentService::detectPluginFromCallback($data);
        }
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $plugin)) {
            return '';
        }
        return $plugin;
    }
}
