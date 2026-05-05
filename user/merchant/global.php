<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/global.php';
require_once EM_ROOT . '/include/model/MerchantModel.php';
require_once EM_ROOT . '/include/model/MerchantLevelModel.php';

/**
 * 商户后台公共文件。
 *
 * 使用约定：
 *   require __DIR__ . '/global.php';
 *   merchantRequireLogin();
 *
 * 返回后可从以下全局变量拿数据：
 *   $frontUser       登录用户
 *   $currentMerchant 当前商户行（含内嵌 level_* 字段）
 *   $merchantLevel   商户等级行（独立的一份）
 *   $uc              视图辅助数组：siteName/currencySymbol/displayMoney/shopBalance
 */

/** @var array<string, mixed>|null */
$currentMerchant = null;
/** @var array<string, mixed>|null */
$merchantLevel = null;
/** @var array<string, mixed> */
$uc = [];

/**
 * 要求当前请求来自一个已开通商户的登录用户。
 *
 * - 未登录：走 userRequireLogin 的逻辑重定向
 * - 已登录但 merchant_id = 0：重定向到开通申请页 /user/merchant/apply.php
 * - 已登录且 merchant_id > 0 但商户被禁用 / 被删：跳回用户中心并提示
 */
function merchantRequireLogin(): void
{
    global $frontUser, $currentMerchant, $merchantLevel, $uc;

    userRequireLogin();

    $merchantId = (int) ($frontUser['merchant_id'] ?? 0);

    // 还没开店：先加载实时字段看一下（登录时 session 可能没刷 merchant_id）
    if ($merchantId <= 0) {
        $userListModel = new UserListModel();
        $fresh = $userListModel->findById((int) $frontUser['id']);
        if ($fresh !== null) {
            $merchantId = (int) ($fresh['merchant_id'] ?? 0);
            if ($merchantId > 0) {
                $frontUser['merchant_id'] = $merchantId;
                $frontUser['shop_balance'] = (int) ($fresh['shop_balance'] ?? 0);
                $_SESSION['em_front_user'] = $frontUser;
            }
        }
    }

    if ($merchantId <= 0) {
        // 跳申请页；PJAX 请求统一返回 302 让前端跟随
        if (Request::isPjax() || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            Response::error('尚未开通商户', ['redirect' => '/user/merchant/apply.php']);
        }
        header('Location: /user/merchant/apply.php');
        exit;
    }

    $merchantModel = new MerchantModel();
    $m = $merchantModel->findById($merchantId);
    if ($m === null || (int) $m['status'] !== 1) {
        // 商户被禁用 / 软删：跳回用户中心
        if (Request::isPjax() || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            Response::error('商户已停用', ['redirect' => '/user/home.php']);
        }
        header('Location: /user/home.php');
        exit;
    }
    $currentMerchant = $m;

    // 当前请求作用域：商户后台按 merchant_{id} 隔离
    // TemplateStorage / Storage / PluginModel 等"按 scope 存取"的组件会读这个全局
    $GLOBALS['__em_current_scope'] = 'merchant_' . $merchantId;

    // 进商户后台也算"最近访问过这个店铺"，供个人中心显示"返回 xxx 店铺"按钮
    MerchantContext::rememberLastMerchant($m);

    $levelModel = new MerchantLevelModel();
    $merchantLevel = $levelModel->findById((int) $m['level_id']);

    // 通用视图变量：符号 / 余额都按当前访客（店主）选择的展示币种；数据库都是主货币 BIGINT
    $siteName = (string) (Config::get('sitename') ?? 'EMSHOP');
    $currencySymbol = Currency::visitorSymbol();

    $userListModel = new UserListModel();
    $fresh = $userListModel->findById((int) $frontUser['id']);
    $shopBalanceRaw = (int) ($fresh['shop_balance'] ?? 0);
    // 带符号完整字符串（和其他 *_view 字段语义一致，前端直接输出不再拼符号）
    $shopBalance  = Currency::displayAmount($shopBalanceRaw);
    $displayMoney = Currency::displayAmount((int) ($frontUser['money'] ?? 0));

    $uc = [
        'siteName' => $siteName,
        'currencySymbol' => $currencySymbol,
        'displayMoney' => $displayMoney,
        'shopBalance' => $shopBalance,
        'shopBalanceRaw' => $shopBalanceRaw,
    ];
}

/**
 * 渲染商户后台页：PJAX 请求返回 #merchantContent 片段；否则包整个 layout。
 */
function merchantRenderPage(string $viewFile, array $extra = []): void
{
    global $frontUser, $currentMerchant, $merchantLevel, $uc;
    extract($extra, EXTR_SKIP);

    if (Request::isPjax()) {
        echo '<div id="merchantContent" class="mc-content">';
        include $viewFile;
        echo '</div>';
        return;
    }

    $merchantContentView = $viewFile;
    $merchantExtra = $extra;
    require __DIR__ . '/view/index.php';
}
