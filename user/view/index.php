<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$siteLogoType = (string) (Config::get('site_logo_type') ?? 'text');
$siteLogo     = (string) (Config::get('site_logo') ?? '');
$userDisplayName = htmlspecialchars($frontUser['nickname'] ?? $frontUser['username'] ?? '');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>个人中心 - <?= htmlspecialchars($siteName) ?></title>
    <link rel="icon" href="<?= htmlspecialchars(site_favicon_href(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="/content/static/lib/font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="/content/static/lib/layui-v2.13.5/layui/css/layui.css">
    <link rel="stylesheet" href="/user/static/css/user.css">
    <script src="/content/static/lib/jquery.min.3.5.1.js"></script>
    <script src="/content/static/lib/jquery.pjax.js"></script>
    <script src="/content/static/lib/layui-v2.13.5/layui/layui.js"></script>
    <script src="/user/static/js/order_delivery_poll.js"></script>
</head>
<body class="uc-body">

<div class="uc-bg" aria-hidden="true"></div>

<div class="uc-shell">
    <div class="uc-overlay" id="ucSidebarMask"></div>

    <div class="uc-container">
        <!-- 左侧：仅菜单（对齐后台 admin-sidebar） -->
        <aside class="uc-sidebar" id="ucSidebar">
            <div class="uc-sidebar__header">
                <a href="/user/home.php" data-pjax="#userContent" class="uc-sidebar__site-name">个人中心</a>
            </div>
            <div class="uc-sidebar__body">
                <div class="uc-menu-title">账户</div>
                <a href="/user/home.php" data-pjax="#userContent" class="uc-menu-item is-active">
                    <i class="fa fa-dashboard"></i><span>概览</span>
                </a>
                <a href="/user/profile.php" data-pjax="#userContent" class="uc-menu-item">
                    <i class="fa fa-user-circle-o"></i><span>个人资料</span>
                </a>

                <div class="uc-menu-title">交易</div>
                <a href="/user/order.php" data-pjax="#userContent" class="uc-menu-item">
                    <i class="fa fa-file-text-o"></i><span>我的订单</span>
                </a>
                <a href="/user/wallet.php" data-pjax="#userContent" class="uc-menu-item">
                    <i class="fa fa-credit-card"></i><span>我的钱包</span>
                </a>
                <a href="/user/balance_log.php" data-pjax="#userContent" class="uc-menu-item">
                    <i class="fa fa-list-alt"></i><span>余额明细</span>
                </a>
                <?php if (shop_coupon_enabled()): ?>
                <a href="/user/coupon.php" data-pjax="#userContent" class="uc-menu-item">
                    <i class="fa fa-ticket"></i><span>我的优惠券</span>
                </a>
                <?php endif; ?>
                <?php if (MerchantContext::currentId() === 0): ?>
                <a href="/user/rebate.php" data-pjax="#userContent" class="uc-menu-item">
                    <i class="fa fa-share-alt"></i><span>我的推广</span>
                </a>
                <?php endif; ?>
                <a href="/user/address.php" data-pjax="#userContent" class="uc-menu-item">
                    <i class="fa fa-map-marker"></i><span>收货地址</span>
                </a>

                <?php
                $merchantId = (int) ($frontUser['merchant_id'] ?? 0);
                $inMerchantContext = class_exists('MerchantContext') && MerchantContext::currentId() > 0;
                $showMerchantSection = ($merchantId > 0) || !$inMerchantContext;
                ?>
                <?php if ($showMerchantSection): ?>
                <div class="uc-menu-title">分站</div>
                <?php if ($merchantId > 0): ?>
                <a href="/user/merchant/home.php" class="uc-menu-item">
                    <i class="fa fa-sitemap"></i><span>我的分站</span>
                </a>
                <?php else: ?>
                <a href="/user/merchant/apply.php" class="uc-menu-item">
                    <i class="fa fa-plus-circle"></i><span>开通分站</span>
                </a>
                <?php endif; ?>
                <?php endif; ?>

                <div class="uc-menu-title">开发</div>
                <a href="/user/api.php" data-pjax="#userContent" class="uc-menu-item">
                    <i class="fa fa-plug"></i><span>API 对接</span>
                </a>
            </div>
        </aside>

        <!-- 右侧：工具栏 + 内容 -->
        <div class="uc-right">
            <div class="uc-toolbar">
                <div class="uc-toolbar__left">
                    <button type="button" class="uc-toolbar__toggle" id="ucSidebarToggle" aria-label="切换菜单">
                        <i class="fa fa-bars"></i>
                    </button>
                    <span class="uc-toolbar__title"><?= htmlspecialchars($siteName) ?></span>
                </div>
                <div class="uc-toolbar__right">
                    <?php
                    $lastMerchant = class_exists('MerchantContext') ? MerchantContext::lastMerchant() : null;
                    if ($lastMerchant !== null && $lastMerchant['url'] !== ''):
                        $mName = $lastMerchant['name'] ?: $lastMerchant['slug'];
                        if (mb_strlen($mName, 'UTF-8') > 10) {
                            $mName = mb_substr($mName, 0, 10, 'UTF-8') . '…';
                        }
                    ?>
                    <a href="<?= htmlspecialchars($lastMerchant['url']) ?>" class="uc-toolbar__ghost" title="返回 <?= htmlspecialchars($lastMerchant['name']) ?>">
                        <i class="fa fa-sitemap"></i><span>返回 <?= htmlspecialchars($mName) ?></span>
                    </a>
                    <?php endif; ?>
                    <a href="/" class="uc-toolbar__ghost"><i class="fa fa-home"></i><span>首页</span></a>

                    <div class="uc-user-menu" id="ucHeaderUser">
                        <button type="button" class="uc-user-menu__trigger">
                            <span class="uc-user-menu__avatar">
                                <?php if (!empty($frontUser['avatar'])): ?>
                                <img src="<?= htmlspecialchars($frontUser['avatar']) ?>" alt="">
                                <?php else: ?>
                                <i class="fa fa-user"></i>
                                <?php endif; ?>
                            </span>
                            <span class="uc-user-menu__meta">
                                <strong><?= $userDisplayName ?></strong>
                                <small>余额 <?= htmlspecialchars($currencySymbol) ?><?= $displayMoney ?></small>
                            </span>
                            <i class="fa fa-angle-down uc-user-menu__arrow"></i>
                        </button>
                        <div class="uc-user-menu__dropdown uc-glass-panel">
                            <a href="/user/profile.php" data-pjax="#userContent" class="uc-user-menu__link">
                                <i class="fa fa-user-circle-o"></i> 个人资料
                            </a>
                            <a href="/user/wallet.php" data-pjax="#userContent" class="uc-user-menu__link">
                                <i class="fa fa-credit-card"></i> 我的钱包
                            </a>
                            <a href="/user/order.php" data-pjax="#userContent" class="uc-user-menu__link">
                                <i class="fa fa-list-alt"></i> 我的订单
                            </a>
                            <div class="uc-user-menu__divider"></div>
                            <a href="/?c=login&a=logout" class="uc-user-menu__link uc-user-menu__link--danger">
                                <i class="fa fa-sign-out"></i> 退出登录
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <main id="userContent" class="uc-content">
                <?php include $userContentView; ?>
            </main>
        </div>
    </div>
</div>

<div class="uc-loading" id="ucLoading">
    <div class="uc-loading__panel uc-glass-panel">
        <div class="uc-loading-spinner"></div>
        <span class="uc-loading__text">加载中</span>
    </div>
</div>

<script>
window.userCsrfToken = <?= json_encode($csrfToken) ?>;

(function () {
    var $body = $('body');
    var $loading = $('#ucLoading');

    function closeSidebar() {
        $body.removeClass('uc-sidebar-open');
        $('#ucSidebar').removeClass('is-open');
    }

    $('#ucSidebarToggle').on('click', function () {
        $body.toggleClass('uc-sidebar-open');
        $('#ucSidebar').toggleClass('is-open');
    });
    $('#ucSidebarMask').on('click', closeSidebar);

    $(document).pjax(
        '.uc-menu-item[data-pjax]',
        '#userContent',
        { fragment: '#userContent', timeout: 8000, scrollTo: false }
    );

    $(document).on('click', '#userContent a[data-pjax]', function (e) {
        $.pjax.click(e, {
            url: this.href,
            container: '#userContent',
            fragment: '#userContent',
            timeout: 8000,
            scrollTo: false
        });
    });

    $(document).on('submit', '#userContent form[data-pjax]', function (e) {
        $.pjax.submit(e, {
            container: '#userContent',
            fragment: '#userContent',
            timeout: 8000
        });
    });

    $(document).on('pjax:send', function () {
        $loading.addClass('is-active');
        $('#userContent').addClass('is-loading');
        if (window.EMSOrderPoll) {
            window.EMSOrderPoll.stopDetail();
            window.EMSOrderPoll.stopList();
            window.EMSOrderPoll.stopFindSnapshot();
        }
    });
    $(document).on('pjax:complete pjax:error', function () {
        $loading.removeClass('is-active');
        $('#userContent').removeClass('is-loading');
    });

    function syncOrderDeliveryPoll() {
        if (typeof window.EMSOrderPoll === 'undefined') return;
        window.EMSOrderPoll.stopDetail();
        window.EMSOrderPoll.stopList();
        window.EMSOrderPoll.stopFindSnapshot();

        var $c = $('#userContent');
        if (!$c.length) return;

        var $detailMeta = $c.find('.uc-ems-poll-root[data-ems-order-detail="1"]');
        if ($detailMeta.length && $detailMeta.attr('data-awaiting') === '1' && window.userCsrfToken) {
            window.EMSOrderPoll.startDetail({
                orderNo: $detailMeta.attr('data-order-no') || '',
                csrfToken: window.userCsrfToken,
                initialStatus: $detailMeta.attr('data-order-status') || ''
            });
            return;
        }

        var $listPage = $c.children('.uc-page[data-ems-order-list="1"]');
        if (!$listPage.length) {
            $listPage = $c.find('.uc-page[data-ems-order-list="1"]').first();
        }
        if ($listPage.length && window.userCsrfToken) {
            var h = $listPage.attr('data-ems-pending-hash');
            window.EMSOrderPoll.startList({
                csrfToken: window.userCsrfToken,
                initialHash: h !== undefined && h !== '' ? h : 'empty'
            });
        }
    }

    $(document).on('pjax:success', function (e, data, status, xhr, options) {
        updateNavActive(options.url);
        closeSidebar();
        syncOrderDeliveryPoll();
        var $main = $('#userContent');
        if ($main.length) {
            $main.scrollTop(0);
        }
    });

    $(function () {
        syncOrderDeliveryPoll();
    });

    function updateNavActive(url) {
        var path = url.replace(location.origin, '').split('?')[0];
        $('.uc-menu-item').removeClass('is-active');
        $('.uc-menu-item[href]').each(function () {
            var href = $(this).attr('href').split('?')[0];
            if (href === path) {
                $(this).addClass('is-active');
            }
        });
    }

    updateNavActive(location.href);

    var $userMenu = $('#ucHeaderUser');
    $userMenu.on('click', '.uc-user-menu__trigger', function (e) {
        e.stopPropagation();
        $userMenu.toggleClass('is-open');
    });
    $(document).on('click', function () { $userMenu.removeClass('is-open'); });
    $userMenu.on('click', '.uc-user-menu__link', function () { $userMenu.removeClass('is-open'); });
})();
</script>

</body>
</html>
