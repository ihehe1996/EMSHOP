<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
// 站点 Logo：跟主站前台 test 模板同款（image 模式有图就显示图，否则文字）
$siteLogoType = (string) (Config::get('site_logo_type') ?? 'text');
$siteLogo     = (string) (Config::get('site_logo') ?? '');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人中心 - <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="/content/static/lib/font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="/content/static/lib/layui-v2.13.5/layui/css/layui.css">
    <link rel="stylesheet" href="/user/static/css/user.css">
    <script src="/content/static/lib/jquery.min.3.5.1.js"></script>
    <script src="/content/static/lib/jquery.pjax.js"></script>
    <script src="/content/static/lib/layui-v2.13.5/layui/layui.js"></script>
</head>
<body>

<!-- 顶部栏（v2：紫色 accent logo + 右侧用户下拉菜单） -->
<header class="uc-header uc-header--v2">
    <div class="uc-header-inner">
        <a href="/" class="uc-header-logo">
            <?php if ($siteLogoType === 'image' && $siteLogo !== ''): ?>
            <img src="<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($siteName) ?>" class="uc-header-logo__img">
            <?php else: ?>
            <span class="uc-header-logo__text"><?= htmlspecialchars($siteName) ?></span>
            <?php endif; ?>
        </a>
        <span class="uc-header-title">
            <i class="fa fa-user-circle-o"></i> 个人中心
        </span>
        <div class="uc-header-right">
            <?php
            // 如果用户刚从某个店铺来（MerchantContext 在访问商户站点 / 商户后台时写 session），
            // 这里显示返回按钮；只有店铺绑定了二级域名或自定义域名（url 非空）才能跳
            $lastMerchant = class_exists('MerchantContext') ? MerchantContext::lastMerchant() : null;
            if ($lastMerchant !== null && $lastMerchant['url'] !== ''):
                $mName = $lastMerchant['name'] ?: $lastMerchant['slug'];
                if (mb_strlen($mName, 'UTF-8') > 10) $mName = mb_substr($mName, 0, 10, 'UTF-8') . '…';
            ?>
            <a href="<?= htmlspecialchars($lastMerchant['url']) ?>" class="uc-header-btn uc-header-btn--shop" title="返回 <?= htmlspecialchars($lastMerchant['name']) ?>">
                <i class="fa fa-sitemap"></i> 返回 <?= htmlspecialchars($mName) ?>
            </a>
            <?php endif; ?>
            <a href="/" class="uc-header-btn"><i class="fa fa-home"></i> 返回首页</a>

            <!-- 用户下拉 -->
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
                    <a href="/user/profile.php" data-pjax="#userContent" class="uc-header-user__item">
                        <i class="fa fa-user-circle-o"></i> 个人资料
                    </a>
                    <a href="/user/wallet.php" data-pjax="#userContent" class="uc-header-user__item">
                        <i class="fa fa-credit-card"></i> 我的钱包
                    </a>
                    <a href="/user/order.php" data-pjax="#userContent" class="uc-header-user__item">
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
    <!-- 左侧边栏 -->
    <aside class="uc-sidebar">
        <!-- 用户卡片（紫色渐变 banner + 重叠头像） -->
        <div class="uc-user-card uc-user-card--v2">
            <div class="uc-user-card__banner"></div>
            <div class="uc-user-avatar">
                <?php if (!empty($frontUser['avatar'])): ?>
                <img src="<?= htmlspecialchars($frontUser['avatar']) ?>" alt="">
                <?php else: ?>
                <span class="uc-user-avatar--default"><i class="fa fa-user"></i></span>
                <?php endif; ?>
            </div>
            <div class="uc-user-name"><?= htmlspecialchars($frontUser['nickname'] ?? $frontUser['username'] ?? '') ?></div>
            <div class="uc-user-card__money">
                <span class="uc-user-card__money-label">账户余额</span>
                <span class="uc-user-card__money-value"><?= htmlspecialchars($currencySymbol) ?><?= $displayMoney ?></span>
            </div>
            <a href="/user/wallet.php" data-pjax="#userContent" class="uc-user-card__action">
                <i class="fa fa-credit-card"></i> 去充值
            </a>
        </div>

        <!-- 导航菜单 -->
        <nav class="uc-nav">
            <div class="uc-nav-title">账户</div>
            <a href="/user/home.php" data-pjax="#userContent" class="uc-nav-item is-active">
                <i class="fa fa-dashboard"></i><span>概览</span>
            </a>
            <a href="/user/profile.php" data-pjax="#userContent" class="uc-nav-item">
                <i class="fa fa-user-circle-o"></i><span>个人资料</span>
            </a>

            <div class="uc-nav-title">交易</div>
            <a href="/user/order.php" data-pjax="#userContent" class="uc-nav-item">
                <i class="fa fa-file-text-o"></i><span>我的订单</span>
            </a>
            <a href="/user/wallet.php" data-pjax="#userContent" class="uc-nav-item">
                <i class="fa fa-credit-card"></i><span>我的钱包</span>
            </a>
            <a href="/user/balance_log.php" data-pjax="#userContent" class="uc-nav-item">
                <i class="fa fa-list-alt"></i><span>余额明细</span>
            </a>
            <a href="/user/coupon.php" data-pjax="#userContent" class="uc-nav-item">
                <i class="fa fa-ticket"></i><span>我的优惠券</span>
            </a>
            <?php // 推广 / 返佣只在主站启用；商户子域名下隐藏入口 ?>
            <?php if (MerchantContext::currentId() === 0): ?>
            <a href="/user/rebate.php" data-pjax="#userContent" class="uc-nav-item">
                <i class="fa fa-share-alt"></i><span>我的推广</span>
            </a>
            <?php endif; ?>
            <a href="/user/address.php" data-pjax="#userContent" class="uc-nav-item">
                <i class="fa fa-map-marker"></i><span>收货地址</span>
            </a>

            <?php
            // 分站（商户）入口：后端沿用商户术语，前台对用户展示为"分站"更易理解
            // 当前请求若已在某个商户站下（二级域名 / 自定义域名），隐藏"开通分站"入口，
            // 避免在分站里再开分站（传销风险）
            $merchantId = (int) ($frontUser['merchant_id'] ?? 0);
            $inMerchantContext = class_exists('MerchantContext') && MerchantContext::currentId() > 0;
            $showMerchantSection = ($merchantId > 0) || !$inMerchantContext;
            ?>
            <?php if ($showMerchantSection): ?>
            <div class="uc-nav-title">分站</div>
            <?php if ($merchantId > 0): ?>
            <a href="/user/merchant/home.php" class="uc-nav-item">
                <i class="fa fa-sitemap"></i><span>我的分站</span>
            </a>
            <?php else: ?>
            <a href="/user/merchant/apply.php" class="uc-nav-item">
                <i class="fa fa-plus-circle"></i><span>开通分站</span>
            </a>
            <?php endif; ?>
            <?php endif; /* showMerchantSection */ ?>

            <div class="uc-nav-title">开发</div>
            <a href="/user/api.php" data-pjax="#userContent" class="uc-nav-item">
                <i class="fa fa-plug"></i><span>API对接</span>
            </a>
        </nav>
    </aside>

    <!-- 右侧内容区 -->
    <div id="userContent" class="uc-content">
        <?php include $userContentView; ?>
    </div>
</div>

<!-- 全屏 Loading 遮罩 -->
<div class="uc-loading" id="ucLoading">
    <div class="uc-loading-spinner"></div>
</div>

<script>
window.userCsrfToken = <?= json_encode($csrfToken) ?>;

(function () {
    // PJAX 初始化
    $(document).pjax(
        '.uc-nav-item[data-pjax]',
        '#userContent',
        { fragment: '#userContent', timeout: 8000, scrollTo: false }
    );

    // 内容区内部链接也走 PJAX
    $(document).on('click', '#userContent a[data-pjax]', function (e) {
        $.pjax.click(e, {
            url: this.href,
            container: '#userContent',
            fragment: '#userContent',
            timeout: 8000,
            scrollTo: false
        });
    });

    // PJAX 表单提交
    $(document).on('submit', '#userContent form[data-pjax]', function (e) {
        $.pjax.submit(e, {
            container: '#userContent',
            fragment: '#userContent',
            timeout: 8000
        });
    });

    // Loading 遮罩
    var $loading = $('#ucLoading');
    $(document).on('pjax:send', function () {
        $loading.addClass('is-active');
    });
    $(document).on('pjax:complete pjax:error', function () {
        $loading.removeClass('is-active');
    });

    // PJAX 完成后更新侧边栏高亮
    $(document).on('pjax:success', function (e, data, status, xhr, options) {
        updateNavActive(options.url);
    });

    // 侧边栏高亮
    function updateNavActive(url) {
        var path = url.replace(location.origin, '').split('?')[0];
        $('.uc-nav-item').removeClass('is-active');
        $('.uc-nav-item[href]').each(function () {
            var href = $(this).attr('href').split('?')[0];
            if (href === path) {
                $(this).addClass('is-active');
            }
        });
    }

    // 初始高亮
    updateNavActive(location.href);

    // 顶部用户下拉开合
    var $userMenu = $('#ucHeaderUser');
    $userMenu.on('click', '.uc-header-user__trigger', function (e) {
        e.stopPropagation();
        $userMenu.toggleClass('is-open');
    });
    $(document).on('click', function () { $userMenu.removeClass('is-open'); });
    // 点击菜单项后自动关闭
    $userMenu.on('click', '.uc-header-user__item', function () { $userMenu.removeClass('is-open'); });
})();
</script>

</body>
</html>
