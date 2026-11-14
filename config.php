<?php

if (!defined('EM_ROOT')) {
    http_response_code(404);
    exit;
}

// 配置值优先取环境变量；开发时可在项目根目录 .env 覆盖（见 .env.example）

// 应用配置
define('EM_APP_NAME', Env::get('EM_APP_NAME', 'EMSHOP'));   // 应用名
define('EM_APP_ADMIN_ENTRY', Env::get('EM_APP_ADMIN_ENTRY', 'admin'));  // 后台入口路径段（admin → /admin，xadmin → /xadmin）

// 数据库连接
define('EM_DB_HOST', Env::get('EM_DB_HOST', '127.0.0.1'));
define('EM_DB_PORT', Env::get('EM_DB_PORT', 3306));
define('EM_DB_NAME', Env::get('EM_DB_NAME', 'emshop'));
define('EM_DB_USER', Env::get('EM_DB_USER', 'emshop'));
define('EM_DB_PASSWORD', Env::get('EM_DB_PASSWORD', '123456'));
define('EM_DB_CHARSET', Env::get('EM_DB_CHARSET', 'utf8mb4'));
define('EM_DB_PREFIX', Env::get('EM_DB_PREFIX', 'em_'));

// 后台认证
define('EM_AUTH_SESSION_KEY', 'em_admin_auth');
define('EM_AUTH_SECRET', '750dea11594b8d8f9f3095ada3b4b76f95306149674747ef51cf826920fad2eb');
define('EM_AUTH_REMEMBER_COOKIE', 'em_admin_remember');
define('EM_AUTH_REMEMBER_DAYS_DEFAULT', 7);
define('EM_AUTH_REMEMBER_DAYS_CHECKED', 365);
define('EM_AUTH_CSRF_KEY', 'em_admin_csrf');

// 默认图片
define('EM_AVATAR', '/content/static/img/default-admin-avatar.jpg');
define('EM_PLACEHOLDER_IMG', '/content/static/img/img-1.png');
