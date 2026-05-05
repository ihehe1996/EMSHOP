<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') : ''; ?></title>
    <link rel="stylesheet" href="/content/static/lib/layui-v2.13.5/layui/css/layui.css">
    <link rel="stylesheet" href="/content/static/lib/font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="/content/static/lib/cropper.min.css">
    <link rel="stylesheet" href="/admin/static/css/popup.css">
    <link rel="stylesheet" href="/admin/static/css/reset-layui.css">
    <link rel="stylesheet" href="/admin/static/css/style.css">
    <script src="/content/static/lib/jquery.min.3.5.1.js"></script>
    <script src="/content/static/lib/layui-v2.13.5/layui/layui.js"></script>
    <script src="/content/static/lib/cropper.min.js"></script>
    <script src="/content/static/lib/sortable.min.js"></script>
    <script src="/admin/static/js/admin.js"></script>
    <script>
    // 弹窗里的模板 / 插件 setting.php 保存 URL 应读 parent.TEMPLATE_SAVE_URL / parent.PLUGIN_SAVE_URL，
    // 而不是硬编码 /admin/template.php /admin/plugin.php —— 这样同一份 setting.php 既能在主站后台弹窗里用，
    // 也能在商户后台弹窗里用（商户侧在 include 本文件前把这两个变量改成 /user/merchant/ 路径即可）。
    window.TEMPLATE_SAVE_URL = <?php echo json_encode($popupTemplateSaveUrl ?? '/admin/template.php', JSON_UNESCAPED_SLASHES); ?>;
    window.PLUGIN_SAVE_URL   = <?php echo json_encode($popupPluginSaveUrl   ?? '/admin/plugin.php',   JSON_UNESCAPED_SLASHES); ?>;
    // 库存保存 URL（商品类型插件在 stock_form.php 里使用；主站默认指向 /admin/goods_edit.php，商户端覆盖到自己的控制器）
    window.STOCK_SAVE_URL    = <?php echo json_encode($popupStockSaveUrl    ?? '/admin/goods_edit.php?_action=save_stock', JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body class="popup-body">
<div class="popup-wrap" id="popupWrap">
    <div class="popup-content" id="popupContent">
