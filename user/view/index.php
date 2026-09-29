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
        <!-- 左侧：菜单 -->
        <?php
        $merchantId = (int) ($frontUser['merchant_id'] ?? 0);
        $inMerchantContext = class_exists('MerchantContext') && MerchantContext::currentId() > 0;

        // 菜单表：侧栏渲染、当前项高亮、页头标题三处共用一份，别再各写一遍
        $ucMenu = [
            [
                'title' => '账户',
                'items' => [
                    ['href' => '/user/home.php',    'icon' => 'fa-dashboard',     'label' => '概览'],
                    ['href' => '/user/profile.php', 'icon' => 'fa-user-circle-o', 'label' => '个人资料'],
                ],
            ],
            [
                'title' => '交易',
                'items' => array_values(array_filter([
                    ['href' => '/user/order.php',       'icon' => 'fa-file-text-o', 'label' => '我的订单'],
                    ['href' => '/user/wallet.php',      'icon' => 'fa-credit-card', 'label' => '我的钱包'],
                    ['href' => '/user/balance_log.php', 'icon' => 'fa-list-alt',    'label' => '余额明细'],
                    shop_coupon_enabled() ? ['href' => '/user/coupon.php', 'icon' => 'fa-ticket', 'label' => '我的优惠券'] : null,
                    MerchantContext::currentId() === 0 ? ['href' => '/user/rebate.php', 'icon' => 'fa-share-alt', 'label' => '我的推广'] : null,
                    ['href' => '/user/address.php', 'icon' => 'fa-map-marker', 'label' => '收货地址'],
                ])),
            ],
        ];

        // 分站入口：商户站内登录时隐藏（那边进的是商户后台，不是「开通分站」）
        if ($merchantId > 0 || !$inMerchantContext) {
            $ucMenu[] = [
                'title' => '分站',
                'items' => [
                    $merchantId > 0
                        ? ['href' => '/user/merchant/home.php',  'icon' => 'fa-sitemap',     'label' => '我的分站', 'pjax' => false]
                        : ['href' => '/user/merchant/apply.php', 'icon' => 'fa-plus-circle', 'label' => '开通分站', 'pjax' => false],
                ],
            ];
        }

        $ucMenu[] = [
            'title' => '开发',
            'items' => [
                ['href' => '/user/api.php', 'icon' => 'fa-plug', 'label' => 'API 对接'],
            ],
        ];

        // 当前页：菜单里命中哪一项，就高亮哪一项、页头标题显示哪个名字
        $ucCurrentNav = null;
        $ucCurrentPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        foreach ($ucMenu as $ucGroup) {
            foreach ($ucGroup['items'] as $ucItem) {
                if ($ucItem['href'] === $ucCurrentPath) { $ucCurrentNav = $ucItem; break 2; }
            }
        }
        // 命中不到（订单详情、/user/ 根路径等）就只留「个人中心」，不硬塞站点名
        $ucPageTitle = $ucCurrentNav['label'] ?? '';
        ?>
        <aside class="uc-sidebar" id="ucSidebar">
            <div class="uc-sidebar__header">
                <a href="/user/home.php" data-pjax="#userContent" class="uc-sidebar__brand">
                    <?php if ($siteLogoType === 'image' && $siteLogo !== ''): ?>
                    <img src="<?= htmlspecialchars($siteLogo) ?>" alt="" class="uc-sidebar__brand-img">
                    <?php else: ?>
                    <span class="uc-sidebar__brand-mark"><i class="fa fa-user-circle-o"></i></span>
                    <?php endif; ?>
                    <span class="uc-sidebar__brand-text">
                        <strong>个人中心</strong>
                        <small><?= htmlspecialchars($siteName) ?></small>
                    </span>
                </a>
            </div>

            <nav class="uc-sidebar__body">
                <?php foreach ($ucMenu as $ucGroup): ?>
                <div class="uc-menu-title"><?= htmlspecialchars($ucGroup['title']) ?></div>
                <?php foreach ($ucGroup['items'] as $ucItem): ?>
                <a href="<?= htmlspecialchars($ucItem['href']) ?>"<?= ($ucItem['pjax'] ?? true) ? ' data-pjax="#userContent"' : '' ?> class="uc-menu-item<?= ($ucCurrentNav !== null && $ucCurrentNav['href'] === $ucItem['href']) ? ' is-active' : '' ?>">
                    <i class="fa <?= htmlspecialchars($ucItem['icon']) ?>"></i><span><?= htmlspecialchars($ucItem['label']) ?></span>
                </a>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </nav>
        </aside>

        <!-- 右侧：工具栏 + 内容 -->
        <div class="uc-right">
            <div class="uc-toolbar">
                <div class="uc-toolbar__left">
                    <button type="button" class="uc-toolbar__toggle" id="ucSidebarToggle" aria-label="切换菜单">
                        <i class="fa fa-bars"></i>
                    </button>
                    <nav class="uc-toolbar__crumb" aria-label="当前位置">
                        <a href="/user/home.php" data-pjax="#userContent" class="uc-toolbar__crumb-home">个人中心</a>
                        <i class="fa fa-angle-right uc-toolbar__crumb-sep"<?= $ucPageTitle === '' ? ' hidden' : '' ?>></i>
                        <span class="uc-toolbar__crumb-current" id="ucPageTitle"<?= $ucPageTitle === '' ? ' hidden' : '' ?>><?= htmlspecialchars($ucPageTitle) ?></span>
                    </nav>
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

// 货币展示三件套。用户中心不加载主题 header，所以要在布局里自己注入 ——
// 缺了它，页面里任何「按访客币种显示、提交前换回主货币」的换算都会退化成 rate=1，
// 也就是静默不换算（商户提现页此前就是这样失效的）。
// rate 语义：1 主货币 = N 访客币（见 Currency::visitorFactor()）。
window.EMSHOP_CURRENCY = {
    code:   <?= json_encode(Currency::visitorCode()) ?>,
    symbol: <?= json_encode($currencySymbol ?? Currency::visitorSymbol()) ?>,
    rate:   <?= json_encode(Currency::visitorFactor()) ?>
};

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
        var title = '';
        $('.uc-menu-item').removeClass('is-active');
        $('.uc-menu-item[href]').each(function () {
            var href = $(this).attr('href').split('?')[0];
            if (href === path) {
                $(this).addClass('is-active');
                title = $.trim($(this).find('span').text());
            }
        });
        // 面包屑跟着菜单走；命中不到（如订单详情）就保留上一个，别退化成站点名
        if (title) {
            $('#ucPageTitle').text(title).removeAttr('hidden');
            $('.uc-toolbar__crumb-sep').removeAttr('hidden');
        }
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
