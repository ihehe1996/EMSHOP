<?php

declare(strict_types=1);

namespace App\User\Controller;

/**
 * 用户中心公开页（/user/currency_switch）。
 */
final class CurrencySwitchController extends UserPublicBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        $code = strtoupper(trim((string) ($_POST['code'] ?? $_GET['code'] ?? '')));
        $currencyModel = $this->currency;

        // 校验：必须是已启用的货币；否则清掉 cookie（回退主货币）
        $clear = false;
        if ($code === '' || !preg_match('/^[A-Z]{3}$/', $code)) {
            $clear = true;
        } else {
            $row = $currencyModel->getByCode($code);
            if ($row === null || (int) ($row['enabled'] ?? 1) !== 1) {
                $clear = true;
            }
        }

        $expire = $clear ? (time() - 3600) : (time() + 86400 * 365);
        $cookieValue = $clear ? '' : $code;

        // PHP 7.3+ 支持数组形式，老版本退化到 5 参数写法
        if (PHP_VERSION_ID >= 70300) {
            setcookie('em_currency', $cookieValue, [
                'expires'  => $expire,
                'path'     => '/',
                'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => false,          // 前端 JS 也能读，方便 switcher 回显
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie('em_currency', $cookieValue, $expire, '/');
        }

        // 立刻回填到 $_COOKIE 供当前请求链路使用（理论上本次就重定向，保险起见）
        if ($clear) {
            unset($_COOKIE['em_currency']);
        } else {
            $_COOKIE['em_currency'] = $cookieValue;
        }

        $back = (string) ($_SERVER['HTTP_REFERER'] ?? '/');
        // 防开放重定向：只允许同站 referer，否则回首页
        if ($back !== '' && !preg_match('#^https?://#i', $back)) {
            $back = '/';
        } else {
            $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
            if ($host !== '' && $back !== '/' && strpos(parse_url($back, PHP_URL_HOST) ?? '', $host) === false) {
                $back = '/';
            }
        }

        header('Location: ' . $back, true, 302);
        exit;
    }
}
