<?php

declare(strict_types=1);

require_once __DIR__ . '/global_public.php';

/**
 * 用户中心主入口（公开版，不强制登录）。
 * 用于订单详情等游客也能访问的页面。
 */

$siteName = Config::get('sitename', 'EMSHOP');
$csrfToken = Csrf::token();

// 游客场景也要支持访客币种，$frontUser 可能不存在
$displayMoney = Currency::displayAmount((int) ($frontUser['money'] ?? 0), null, false);
$currencySymbol = Currency::visitorSymbol();

if (empty($userContentView)) {
    $userContentView = __DIR__ . '/view/home.php';
}

include __DIR__ . '/view/index.php';
