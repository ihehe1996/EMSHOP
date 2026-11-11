<?php
if (!defined('EM_ROOT')) { exit('Access Denied'); }
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>查询订单 - <?= $esc($siteName) ?></title>
    <link rel="icon" href="<?= $esc(site_favicon_href()) ?>">
    <link rel="stylesheet" href="/content/static/lib/font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="/content/static/lib/layui-v2.13.5/layui/css/layui.css">
    <!-- 独立页专属样式：自给自足，不依赖 user.css / 模板 style.css -->
    <link rel="stylesheet" href="/content/static/css/find_order.css">
    <script src="/content/static/lib/jquery.min.3.5.1.js"></script>
    <script src="/content/static/lib/jquery.pjax.js"></script>
    <script src="/content/static/lib/layui-v2.13.5/layui/layui.js"></script>
    <script src="/user/static/js/order_delivery_poll.js"></script>
    <script src="/content/static/js/guest_find.js?v=<?= (int) @filemtime(EM_ROOT . '/content/static/js/guest_find.js') ?>"></script>
</head>
<body>

<!-- 顶部栏（参考个人中心 uc-header v2 样式） -->
<header class="fo-header">
    <div class="fo-header-inner">
        <a href="/" class="fo-header-logo">
            <?php if ($siteLogoType === 'image' && $siteLogo !== ''): ?>
            <img src="<?= $esc($siteLogo) ?>" alt="<?= $esc($siteName) ?>" class="fo-header-logo__img">
            <?php else: ?>
            <span class="fo-header-logo__text"><?= $esc($siteName) ?></span>
            <?php endif; ?>
        </a>
        <span class="fo-header-title"><i class="fa fa-search"></i> 订单查询</span>
        <div class="fo-header-right">
            <a href="/" class="fo-header-btn"><i class="fa fa-home"></i> 返回首页</a>
        </div>
    </div>
</header>

<main id="foContent" class="fo-wrap">
<?php if ($detailOrderNo !== ''): ?>
    <?php include __DIR__ . '/guest_order_detail.php'; ?>
<?php else: ?>
    <!-- 查单列表（默认）：hero 标题 + 三 tab 查询面板 -->
    <div class="fo-card">
        <div class="fo-hero">
            <div class="fo-hero__title">订单查询</div>
            <div class="fo-hero__sub">输入订单号或联系方式查看订单状态</div>
        </div>
        <?php include __DIR__ . '/find_order.php'; ?>
    </div>
<?php endif; ?>
</main>

<!-- PJAX Loading -->
<div class="fo-loading" id="foLoading"><div class="fo-loading-spinner"></div></div>

<script>
(function(){
    // 详情链接 + 返回查单链接 走 PJAX，仅替换 #foContent
    $(document).pjax('a[data-pjax]', '#foContent', {
        fragment: '#foContent', timeout: 8000, scrollTo: 0
    });
    var $loading = $('#foLoading');
    $(document).on('pjax:send', function () {
        $loading.addClass('is-active');
        if (window.EMSOrderPoll) {
            window.EMSOrderPoll.stopDetail();
            window.EMSOrderPoll.stopFindSnapshot();
        }
    });
    $(document).on('pjax:complete pjax:error', function(){ $loading.removeClass('is-active'); });
})();
</script>

</body>
</html>
