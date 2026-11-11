<?php

if (!defined('EM_ROOT')) {
    http_response_code(404);
    exit;
}

// 配置值优先取环境变量；开发时可在项目根目录 .env 覆盖（见 .env.example）。
// Env 类由 init.php / server 在加载本文件之前引入。

// 应用配置（当前版本仅预留，功能后续接入）
define('EM_APP_NAME', Env::get('EM_APP_NAME', 'EMSHOP'));   // 应用名
define('EM_APP_ADMIN_ENTRY', Env::get('EM_APP_ADMIN_ENTRY', 'admin'));  // 后台安全入口 key，空 = 不限制

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
define('EM_AUTH_SECRET', 'f480abf5acef0c4a1cf642931ba939ce34e4d7e920e9622476ce4d35d425005d');
define('EM_AUTH_REMEMBER_COOKIE', 'em_admin_remember');
define('EM_AUTH_REMEMBER_DAYS_DEFAULT', 7);
define('EM_AUTH_REMEMBER_DAYS_CHECKED', 365);
define('EM_AUTH_CSRF_KEY', 'em_admin_csrf');

// 默认图片
define('EM_AVATAR', '/content/static/img/default-admin-avatar.jpg');
define('EM_PLACEHOLDER_IMG', '/content/static/img/img-1.png');
