<?php

/**
 * 应用商店 —— 购买弹窗（iframe 页面）。
 *
 * 仅负责渲染独立弹窗框架 HTML；页面初始化后由 JS 调一次接口：
 *   /admin/appstore?_action=app_detail&id={id}
 *
 * @var int    $appId
 * @var string $tab
 * @var string $assetHost
 * @var string $csrfToken
 */
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>应用购买</title>
    <link rel="stylesheet" href="/content/static/lib/layui-v2.13.5/layui/css/layui.css">
    <link rel="stylesheet" href="/content/static/lib/font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="/content/static/admin/css/reset-layui.css">
    <link rel="stylesheet" href="/content/static/admin/css/popup.css">
    <script src="/content/static/lib/jquery.min.3.5.1.js"></script>
    <script src="/content/static/lib/layui-v2.13.5/layui/layui.js"></script>
    <script>
        // 当前应用 id（供 JS 调 app_detail 接口时带上）
        window.APPSTORE_BUY_ID = <?= (int) $appId ?>;
        // 当前 tab(main/merchant):JS 调 app_detail / app_buy 时透传,后端按此分发
        window.APPSTORE_BUY_TAB = <?= json_encode($tab) ?>;
        // 资源 host（应用封面拼接基地址，与列表页保持一致，取授权服务器线路）
        window.APPSTORE_ASSET_HOST = <?= json_encode($assetHost, JSON_UNESCAPED_SLASHES) ?>;
        window.adminCsrfToken = <?= json_encode($csrfToken) ?>;
    </script>
</head>
<body class="popup-body">
<?php include __DIR__ . '/popup/appstore_buy.php'; ?>
</body>
</html>
