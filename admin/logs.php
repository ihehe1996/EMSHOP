<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * 后台系统日志页。
 *
 * 当前先做页面占位，后续再接真实日志数据。
 */
adminRequireLogin();
$user = $adminUser;
$siteName = Config::get('sitename', 'EMSHOP');




if (Request::isPjax()) {
    // Pjax 局部加载：返回带 #adminContent 容器的片段
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/logs.php';
    echo '</div>';
} else {
    // 直接访问：加载完整后台框架
    $adminContentView = __DIR__ . '/view/logs.php';
    require __DIR__ . '/index.php';
}
