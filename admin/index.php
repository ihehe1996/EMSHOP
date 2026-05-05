<?php

declare(strict_types=1);

require_once __DIR__ . '/global.php';

/**
 * 后台首页入口。
 *
 * 负责渲染后台框架，并承载 Pjax 内容区。
 */
if ((string) Input::get('action', '') === 'logout') {
    $auth->logout();
    Response::redirect(adminSignUrl());
}

// 清除缓存
if ((string) Input::post('_action', '') === 'clear_cache') {
    adminRequireLogin();
    $csrf = (string) Input::post('csrf_token', '');
    
    Cache::clear();
    Response::success('缓存已清空');
}

// 插件动作分发：允许插件通过钩子处理自定义后台 action，避免在核心代码中写插件逻辑
$action = (string) Input::get('_action', '');
if ($action !== '' && $action !== 'clear_cache') {
    adminRequireLogin();
    // 触发钩子 admin_plugin_action_{action}，由插件自行处理并 exit
    doAction('admin_plugin_action_' . $action);
}

adminRequireLogin();
$user = $adminUser;

// 刷新 session，确保获取最新头像等资料
$userModel = new UserModel();
$freshUser = $userModel->findById((int) $user['id']);
if ($freshUser) {
    $auth->refreshSession($freshUser);
    $user = $freshUser;
}

$siteName = Config::get('sitename', 'EMSHOP');

// 获取语言列表供顶部导航渲染
$langModel = new LanguageModel();
$languages = $langModel->getEnabled();

// 子页面可设置 $adminContentView 指定内容区视图，默认为控制台首页
if (empty($adminContentView)) {
    $adminContentView = __DIR__ . '/view/home.php';
    // 默认进入控制台时跟服务端核对一次授权状态（其他子页面由各自 controller 触发）
    LicenseService::revalidateCurrent();
}

$viewFile = __DIR__ . '/view/index.php';
require $viewFile;
