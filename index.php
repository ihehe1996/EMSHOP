<?php

/**
 * 前台入口文件。
 *
 * 站点首页类型（商城 / 博客）：
 *   HOMEPAGE_MODE = 'mall'  → 站点首页为商城首页
 *   HOMEPAGE_MODE = 'blog'  → 站点首页为博客首页
 * 优先读取后台配置（homepage_mode），未配置时默认 mall。
 *
 * 导航对应关系：
 *   mall 模式：商城→goods_list、博客→blog_index
 *   blog 模式：商城→goods_index、博客→blog_list
 *
 * @package EMSHOP
 * @link https://www.ihehe.me
 */

// 加载系统初始化文件（必须先加载，以获取 Config 等基础类）
require_once __DIR__ . '/init.php';

// 支付回调直连入口：由 /notify 和 /return 命中，不走前台 Dispatcher/模板渲染。
$requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if ($requestPath === '/notify') {
    PaymentCallbackController::handleNotify();
    exit;
}
if ($requestPath === '/return') {
    PaymentCallbackController::handleReturn();
    exit;
}

// 设置首页入口模式：优先读后台配置 homepage_mode，无配置时默认 mall
define('HOMEPAGE_MODE', Config::get('homepage_mode', 'mall'));

// 路由分发：解析 URL 参数，加载模板文件，注入 body 内容
Dispatcher::getInstance()->dispatch();

// 页面渲染：根据请求类型（普通/PJAX/AJAX）输出完整页面
View::getInstance()->output();

