<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';

/**
 * 用户中心公共文件（不强制登录）。
 * 用于订单详情等游客也能访问的页面。
 */

/** @var array<string, mixed>|null 当前登录的前台用户（可能为 null） */
$frontUser = null;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['em_front_user'])) {
    $frontUser = $_SESSION['em_front_user'];

    // 从数据库刷新实时数据
    $userModel = new UserListModel();
    $fresh = $userModel->findById((int) $frontUser['id']);
    if ($fresh !== null) {
        $frontUser['money']    = (int) ($fresh['money'] ?? 0);
        $frontUser['nickname'] = (string) ($fresh['nickname'] ?: $fresh['username']);
        $frontUser['avatar']   = (string) $fresh['avatar'];
        $frontUser['email']    = (string) $fresh['email'];
        $frontUser['mobile']   = (string) ($fresh['mobile'] ?? '');
        $frontUser['secret']   = $fresh['secret'] ?? null;
        $_SESSION['em_front_user'] = $frontUser;
    }
}
