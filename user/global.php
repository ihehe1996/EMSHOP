<?php

declare(strict_types=1);

require dirname(__DIR__) . '/init.php';

/**
 * 用户中心公共文件。
 *
 * 提供登录校验、当前用户信息获取等能力。
 */

/** @var array<string, mixed>|null 当前登录的前台用户 */
$frontUser = null;

/**
 * 前台用户登录校验。
 *
 * 未登录时重定向到登录页。
 */
function userRequireLogin(): void
{
    global $frontUser;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['em_front_user'])) {
        // PJAX / AJAX 请求返回 JSON
        if (Request::isPjax() || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            Response::error('请先登录');
        }
        header('Location: /?c=login');
        exit;
    }

    $frontUser = $_SESSION['em_front_user'];

    // 从数据库刷新余额等实时数据
    $userModel = new UserListModel();
    $fresh = $userModel->findById((int) $frontUser['id']);
    if ($fresh !== null) {
        $frontUser['money']        = (int) ($fresh['money'] ?? 0);
        $frontUser['nickname']     = (string) ($fresh['nickname'] ?: $fresh['username']);
        $frontUser['avatar']       = (string) $fresh['avatar'];
        $frontUser['email']        = (string) $fresh['email'];
        $frontUser['mobile']       = (string) ($fresh['mobile'] ?? '');
        $frontUser['secret']       = $fresh['secret'] ?? null;
        $frontUser['merchant_id']  = (int) ($fresh['merchant_id'] ?? 0);
        $frontUser['shop_balance'] = (int) ($fresh['shop_balance'] ?? 0);
        $_SESSION['em_front_user'] = $frontUser;
    }
}
