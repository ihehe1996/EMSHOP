<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') : ''; ?></title>
    <link rel="stylesheet" href="/content/static/lib/layui-v2.13.5/layui/css/layui.css">
    <link rel="stylesheet" href="/content/static/lib/font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="/content/static/lib/cropper.min.css">
    <link rel="stylesheet" href="/content/static/admin/css/popup.css">
    <link rel="stylesheet" href="/content/static/admin/css/reset-layui.css">
    <link rel="stylesheet" href="/content/static/admin/css/style.css">
    <script src="/content/static/lib/jquery.min.3.5.1.js"></script>
    <script src="/content/static/admin/js/em-editor-upload.js"></script>
    <script src="/content/static/lib/layui-v2.13.5/layui/layui.js"></script>
    <script src="/content/static/lib/cropper.min.js"></script>
    <script src="/content/static/lib/sortable.min.js"></script>
    <script src="/content/static/admin/js/admin.js?v=<?= EM_VERSION; ?>"></script>
    <script>
    // 弹窗里的模板 / 插件 setting.php 保存 URL 应读 parent.TEMPLATE_SAVE_URL / parent.PLUGIN_SAVE_URL，
    // 而不是硬编码 /admin/template /admin/plugin —— 这样同一份 setting.php 既能在主站后台弹窗里用，
    // 也能在商户后台弹窗里用（商户侧在 include 本文件前把这两个变量改成 /user/merchant/ 路径即可）。
    window.TEMPLATE_SAVE_URL = <?php echo json_encode($popupTemplateSaveUrl ?? '/admin/template', JSON_UNESCAPED_SLASHES); ?>;
    window.TEMPLATE_MEDIA_URL = <?php echo json_encode($popupTemplateMediaUrl ?? '/admin/media', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    window.TEMPLATE_UPLOAD_URL = <?php echo json_encode($popupTemplateUploadUrl ?? '/admin/upload', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    window.PLUGIN_SAVE_URL   = <?php echo json_encode($popupPluginSaveUrl   ?? '/admin/plugin',   JSON_UNESCAPED_SLASHES); ?>;
    // 库存保存 URL（商品类型插件在 stock_form.php 里使用；主站默认指向 /admin/goods_edit，商户端覆盖到自己的控制器）
    window.STOCK_SAVE_URL    = <?php echo json_encode($popupStockSaveUrl    ?? '/admin/goods_edit?_action=save_stock', JSON_UNESCAPED_SLASHES); ?>;
    // 卡密库存相关 action 入口（virtual_card 插件 inventory / import 页使用；商户端覆盖到 /user/merchant/goods.php）
    window.CARD_ACTION_BASE  = <?php echo json_encode($popupCardActionBase  ?? '/admin/plugin_action', JSON_UNESCAPED_SLASHES); ?>;
    </script>

<script src="/content/static/lib/tinymce/tinymce.min.js"></script>
    <script src="/content/static/admin/js/em-tinymce.js"></script> 
    <?php if (isset($extraHead)) echo $extraHead; ?>
</head> 
<body class="popup-body">
<div class="popup-wrap" id="popupWrap">
    <div class="popup-content" id="popupContent">
