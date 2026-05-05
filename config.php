<?php

// 未安装（缺少安装锁）时：自动跳转到在线安装向导
if (!is_file(__DIR__ . '/install/install.lock')) {
    header('Location: /install/');
    exit;
}

return [
  'db' => 
  [
    'host' => '127.0.0.1',
    'port' => 3306,
    'dbname' => '',
    'username' => '',
    'password' => '',
    'charset' => 'utf8mb4',
    'prefix' => 'em_',
  ],
  'auth' => 
  [
    'session_key' => 'em_admin_auth',
    'remember_cookie' => 'em_admin_remember',
    'remember_days_default' => 7,
    'remember_days_checked' => 365,
    'csrf_key' => 'em_admin_csrf',
    'throttle_key' => 'em_admin_login_throttle',
    'max_attempts' => 5,
    'lock_minutes' => 5,
  ],
  'avatar' => '/content/static/img/default-admin-avatar.jpg',
  'placeholder_img' => '/content/static/img/img-1.png',
  'license_urls' => 
  [
    0 => 
    [
      'url' => 'https://emshop.ihehe.me/',
      'name' => '官方线路',
    ],
    1 => 
    [
      'url' => 'http://154.44.8.63:10000/',
      'name' => '备用线路',
    ],
  ],
];
