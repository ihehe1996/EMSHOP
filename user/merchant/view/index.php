<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
/** @var array<string, mixed> $frontUser */
/** @var array<string, mixed> $currentMerchant */
/** @var array<string, mixed>|null $merchantLevel */
/** @var array<string, mixed> $uc */

$csrfToken = Csrf::token();
$lv = $merchantLevel ?? [];

// 菜单项可见性按等级开关
$showSelfGoods = (int) ($lv['allow_self_goods'] ?? 0) === 1;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商户后台 - <?= htmlspecialchars($uc['siteName'] ?? 'EMSHOP') ?></title>
    <link rel="stylesheet" href="/content/static/lib/font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="/content/static/lib/layui-v2.13.5/layui/css/layui.css">
    <link rel="stylesheet" href="/user/static/css/user.css">
    <link rel="stylesheet" href="/user/merchant/static/css/merchant.css?v=<?= @filemtime(EM_ROOT . '/user/merchant/static/css/merchant.css') ?: time() ?>">
    <script src="/content/static/lib/jquery.min.3.5.1.js"></script>
    <script src="/content/static/lib/jquery.pjax.js"></script>
    <script src="/content/static/lib/layui-v2.13.5/layui/layui.js"></script>
</head>
<body>

<!-- 顶部栏（对齐 /user/view/index.php 的 v2 样式：紫色 logo mark + 右侧用户下拉） -->
<header class="uc-header uc-header--v2">
    <div class="uc-header-inner">
        <?php
        // 站点 Logo：与 test 模板 / 个人中心同款，图片优先文字兜底
        $siteLogoType = (string) (Config::get('site_logo_type') ?? 'text');
        $siteLogo     = (string) (Config::get('site_logo') ?? '');
        ?>
        <a href="/" class="uc-header-logo">
            <?php if ($siteLogoType === 'image' && $siteLogo !== ''): ?>
            <img src="<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($uc['siteName']) ?>" class="uc-header-logo__img">
            <?php else: ?>
            <span class="uc-header-logo__text"><?= htmlspecialchars($uc['siteName']) ?></span>
            <?php endif; ?>
        </a>
        <span class="uc-header-title">
            <i class="fa fa-sitemap"></i> 商户后台
        </span>
        <div class="uc-header-right">
            <?php $storefrontUrl = MerchantContext::storefrontUrl($currentMerchant); ?>
            <?php if ($storefrontUrl !== ''): ?>
            <a href="<?= htmlspecialchars($storefrontUrl) ?>" class="uc-header-btn" target="_blank">
                <i class="fa fa-external-link"></i> 店铺前台
            </a>
            <?php endif; ?>
            <a href="/" class="uc-header-btn"><i class="fa fa-home"></i> 返回首页</a>

            <!-- 用户下拉（和个人中心一致） -->
            <div class="uc-header-user" id="ucHeaderUser">
                <div class="uc-header-user__trigger">
                    <div class="uc-header-user__avatar">
                        <?php if (!empty($frontUser['avatar'])): ?>
                        <img src="<?= htmlspecialchars($frontUser['avatar']) ?>" alt="">
                        <?php else: ?>
                        <i class="fa fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="uc-header-user__name"><?= htmlspecialchars($frontUser['nickname'] ?? $frontUser['username'] ?? '') ?></div>
                    <i class="fa fa-angle-down uc-header-user__caret"></i>
                </div>
                <div class="uc-header-user__dropdown">
                    <a href="/user/home.php" class="uc-header-user__item">
                        <i class="fa fa-user-circle-o"></i> 个人中心
                    </a>
                    <a href="/user/wallet.php" class="uc-header-user__item">
                        <i class="fa fa-credit-card"></i> 我的钱包
                    </a>
                    <a href="/user/order.php" class="uc-header-user__item">
                        <i class="fa fa-list-alt"></i> 我的订单
                    </a>
                    <div class="uc-header-user__divider"></div>
                    <a href="/?c=login&a=logout" class="uc-header-user__item uc-header-user__item--danger">
                        <i class="fa fa-sign-out"></i> 退出登录
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<div class="uc-container">
    <!-- 侧栏 -->
    <aside class="uc-sidebar">
        <!-- 店铺卡片 -->
        <div class="mc-shop-card">
            <div class="mc-shop-card__name"><?= htmlspecialchars($currentMerchant['name']) ?></div>
            <?php $storefrontUrl = MerchantContext::storefrontUrl($currentMerchant); ?>
            <?php if ($storefrontUrl !== ''): ?>
            <div class="mc-shop-card__slug"><?= htmlspecialchars(preg_replace('#^https?://#', '', rtrim($storefrontUrl, '/'))) ?></div>
            <?php else: ?>
            <div class="mc-shop-card__slug" style="color:#ef4444;">未绑定域名</div>
            <?php endif; ?>
            <?php if (!empty($merchantLevel)): ?>
            <div class="mc-shop-card__level"><?= htmlspecialchars($merchantLevel['name']) ?></div>
            <?php endif; ?>
            <div class="mc-shop-card__balance">
                <span class="mc-shop-card__balance-label">店铺余额</span>
                <span class="mc-shop-card__balance-value"><?= htmlspecialchars((string) $uc['shopBalance']) ?></span>
            </div>
        </div>

        <nav class="uc-nav">
            <div class="uc-nav-title">经营</div>
            <a href="/user/merchant/home.php" data-pjax="#merchantContent" class="uc-nav-item">
                <i class="fa fa-dashboard"></i><span>概览</span>
            </a>
            <a href="/user/merchant/goods.php" data-pjax="#merchantContent" class="uc-nav-item">
                <i class="fa fa-shopping-bag"></i><span>商品管理</span>
            </a>
            <a href="/user/merchant/category.php" data-pjax="#merchantContent" class="uc-nav-item">
                <i class="fa fa-folder"></i><span>分类管理</span>
            </a>
            <a href="/user/merchant/order.php" data-pjax="#merchantContent" class="uc-nav-item">
                <i class="fa fa-file-text-o"></i><span>订单管理</span>
            </a>

            <div class="uc-nav-title">内容</div>
            <a href="/user/merchant/navi.php" data-pjax="#merchantContent" class="uc-nav-item">
                <i class="fa fa-compass"></i><span>导航管理</span>
            </a>
            <a href="/user/merchant/blog_category.php" data-pjax="#merchantContent" class="uc-nav-item">
                <i class="fa fa-folder-open"></i><span>文章分类</span>
            </a>
            <a href="/user/merchant/blog.php" data-pjax="#merchantContent" class="uc-nav-item">
                <i class="fa fa-file-text"></i><span>文章管理</span>
            </a>
            <a href="/user/merchant/page.php" data-pjax="#merchantContent" class="uc-nav-item">
                <i class="fa fa-file-o"></i><span>页面管理</span>
            </a>

            <div class="uc-nav-title">财务</div>
            <a href="/user/merchant/finance.php" data-pjax="#merchantContent" class="uc-nav-item">
                <i class="fa fa-list-alt"></i><span>余额明细</span>
            </a>
            <a href="/user/merchant/withdraw.php" data-pjax="#merchantContent" class="uc-nav-item">
                <i class="fa fa-credit-card"></i><span>店铺余额</span>
            </a>

            <div class="uc-nav-title">扩展</div>
            <a href="/user/merchant/appstore.php" data-pjax="#merchantContent" class="uc-nav-item">
                <i class="fa fa-shopping-basket"></i><span>应用商店</span>
            </a>
            <a href="/user/merchant/plugin.php" data-pjax="#merchantContent" class="uc-nav-item">
                <i class="fa fa-plug"></i><span>插件管理</span>
            </a>
            <a href="/user/merchant/template.php" data-pjax="#merchantContent" class="uc-nav-item">
                <i class="fa fa-paint-brush"></i><span>模板管理</span>
            </a>

            <div class="uc-nav-title">设置</div>
            <a href="/user/merchant/settings.php" data-pjax="#merchantContent" class="uc-nav-item">
                <i class="fa fa-cog"></i><span>店铺设置</span>
            </a>
        </nav>
    </aside>

    <!-- 内容区 -->
    <div id="merchantContent" class="uc-content mc-content">
        <?php include $merchantContentView; ?>
    </div>
</div>

<div class="uc-loading" id="ucLoading">
    <div class="uc-loading-spinner"></div>
</div>

<script>
window.merchantCsrfToken = <?= json_encode($csrfToken) ?>;

(function () {
    $(document).pjax(
        '.uc-nav-item[data-pjax]',
        '#merchantContent',
        { fragment: '#merchantContent', timeout: 8000, scrollTo: false }
    );

    $(document).on('click', '#merchantContent a[data-pjax]', function (e) {
        $.pjax.click(e, {
            url: this.href,
            container: '#merchantContent',
            fragment: '#merchantContent',
            timeout: 8000,
            scrollTo: false
        });
    });

    $(document).on('submit', '#merchantContent form[data-pjax]', function (e) {
        $.pjax.submit(e, {
            container: '#merchantContent',
            fragment: '#merchantContent',
            timeout: 8000
        });
    });

    var $loading = $('#ucLoading');
    $(document).on('pjax:send', function () { $loading.addClass('is-active'); });
    $(document).on('pjax:complete pjax:error', function () { $loading.removeClass('is-active'); });

    // 侧栏高亮
    function updateNavActive(url) {
        var path = (url || location.href).replace(location.origin, '').split('?')[0];
        $('.uc-nav-item').removeClass('is-active');
        $('.uc-nav-item[href]').each(function () {
            var href = $(this).attr('href').split('?')[0];
            if (href === path) $(this).addClass('is-active');
        });
    }
    $(document).on('pjax:success', function (e, data, status, xhr, options) {
        updateNavActive(options.url);
    });
    updateNavActive(location.href);

    // 顶部用户下拉开合（和个人中心一致）
    var $userMenu = $('#ucHeaderUser');
    $userMenu.on('click', '.uc-header-user__trigger', function (e) {
        e.stopPropagation();
        $userMenu.toggleClass('is-open');
    });
    $(document).on('click', function () { $userMenu.removeClass('is-open'); });
    $userMenu.on('click', '.uc-header-user__item', function () { $userMenu.removeClass('is-open'); });
})();
</script>
</body>
</html>
