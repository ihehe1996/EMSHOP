<?php

declare(strict_types=1);

require_once __DIR__ . '/global.php';

/**
 * 应用商店 —— 购买弹窗（iframe 页面）。
 *
 * 仅负责渲染独立弹窗框架 HTML；页面初始化后由 JS 调一次接口：
 *   /admin/appstore.php?_action=app_detail&id={id}
 *   → 响应同时返回 app 详情 + pay_methods
 *
 * 外部 URL：/admin/appstore_buy.php?id={app_id}
 */
adminRequireLogin();

$csrfToken = Csrf::token();

// 应用 id；<=0 时页面内部会展示错误占位
$appId = (int) Input::get('id', 0);

// tab(main / merchant):决定 app_detail / app_buy 走主站货架还是分站货架
$tab = (string) Input::get('tab', 'main');
if (!in_array($tab, ['main', 'merchant'], true)) $tab = 'main';

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>应用购买</title>
    <link rel="stylesheet" href="/content/static/lib/layui-v2.13.5/layui/css/layui.css">
    <link rel="stylesheet" href="/content/static/lib/font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="/admin/static/css/reset-layui.css">
    <link rel="stylesheet" href="/admin/static/css/popup.css">
    <script src="/content/static/lib/jquery.min.3.5.1.js"></script>
    <script src="/content/static/lib/layui-v2.13.5/layui/layui.js"></script>
    <script>
        // 当前应用 id（供 JS 调 app_detail 接口时带上）
        window.APPSTORE_BUY_ID = <?= (int) $appId ?>;
        // 当前 tab(main/merchant):JS 调 app_detail / app_buy 时透传,后端按此分发
        window.APPSTORE_BUY_TAB = <?= json_encode($tab) ?>;
        // 资源 host（应用封面拼接基地址，与列表页保持一致，永远取 license_urls[0]）
        <?php
            $__buyLines = LicenseClient::lines();
            $__buyAssetHost = $__buyLines ? rtrim($__buyLines[0]['url'], '/') : '';
        ?>
        window.APPSTORE_ASSET_HOST = <?= json_encode($__buyAssetHost, JSON_UNESCAPED_SLASHES) ?>;
        window.adminCsrfToken = <?= json_encode($csrfToken) ?>;
    </script>
</head>
<body class="popup-body">
<?php include __DIR__ . '/view/popup/appstore_buy.php'; ?>
</body>
</html>
