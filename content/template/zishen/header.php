<?php
/*
Template Name:子神
Version:1.0.0
Template Url:
Description:二次元风格独立布局 · 马卡龙浅彩主题
Author:EMSHOP
Author Url:
*/
?>
<?php
// SEO 元信息：优先读 seo_* 配置；为空时回退到 site_* 或站点名
$seoTitle       = (string) (Config::get('seo_title', '') ?: ($site_name ?? 'EMSHOP'));
$seoKeywords    = (string) (Config::get('seo_keywords', '') ?: (Config::get('site_keywords', '')));
$seoDescription = (string) (Config::get('seo_description', '') ?: (Config::get('site_description', '')));
$pageTitle      = isset($page_title) && $page_title !== '' ? $page_title : '';
$fullTitle      = $pageTitle !== '' ? ($pageTitle . ' - ' . $seoTitle) : $seoTitle;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($fullTitle) ?></title>
<?php if ($seoKeywords !== ''): ?>
<meta name="keywords" content="<?= htmlspecialchars($seoKeywords) ?>">
<?php endif; ?>
<?php if ($seoDescription !== ''): ?>
<meta name="description" content="<?= htmlspecialchars($seoDescription) ?>">
<?php endif; ?>
<link rel="stylesheet" href="/content/static/lib/font-awesome-4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="/content/static/lib/layui-v2.13.5/layui/css/layui.css">
<link rel="stylesheet" href="/content/static/lib/viewer.js/viewer.min.css">
<link rel="stylesheet" href="<?= htmlspecialchars(theme_asset_url('style.css', (string) ($_theme ?? 'default'))) ?>?v=<?= @filemtime(__DIR__ . '/style.css') ?>">
<script src="/content/static/lib/jquery.min.3.5.1.js"></script>
<script src="/content/static/lib/viewer.js/viewer.min.js"></script>
<script src="/content/static/lib/jquery.pjax.js"></script>
<script src="/content/static/lib/layui-v2.13.5/layui/layui.js"></script>
<script>
window.EMSHOP_URLS = {
    coupon:     <?= json_encode(url_coupon()) ?>,
    goodsList:  <?= json_encode(url_goods_list()) ?>,
    blogList:   <?= json_encode(url_blog_list()) ?>,
    search:     <?= json_encode(url_search()) ?>
};
window.EMSHOP_CURRENCY = {
    code:   <?= json_encode($currency_code ?? '') ?>,
    symbol: <?= json_encode($currency_symbol ?? '¥') ?>,
    rate:   <?= json_encode((float) ($currency_rate ?? 1)) ?>
};
</script>
</head>
<body class="theme-zishen">

<div class="zs-bg" aria-hidden="true">
    <div class="zs-bg__blob zs-bg__blob--a"></div>
    <div class="zs-bg__blob zs-bg__blob--b"></div>
    <div class="zs-bg__blob zs-bg__blob--c"></div>
    <div class="zs-bg__noise"></div>
</div>

<div class="pjax-bar" id="pjaxBar"></div>

<header class="site-header zs-header" id="siteHeader">
    <div class="zs-header__glow" aria-hidden="true"></div>
    <div class="wrapper zs-header__wrap">
        <div class="zs-header__orbit" aria-hidden="true">
            <span class="zs-orbit-dot"></span>
            <span class="zs-orbit-dot zs-orbit-dot--delay"></span>
        </div>

        <div class="zs-header__panel">
            <div class="zs-header__left">
                <?php $logoType = $site_logo_type ?? 'text'; ?>
                <a href="<?= url_home() ?>" data-pjax class="site-logo zs-logo">
                    <?php if ($logoType === 'image' && !empty($site_logo)): ?>
                    <img src="<?= htmlspecialchars($site_logo) ?>" alt="<?= htmlspecialchars($site_name ?? 'EMSHOP') ?>" class="site-logo-img">
                    <?php else: ?>
                    <span class="site-logo-text zs-logo-text"><?= htmlspecialchars($site_name ?? 'EMSHOP') ?></span>
                    <?php endif; ?>
                    <span class="zs-logo-badge">店铺</span>
                </a>
            </div>

            <nav class="main-nav zs-nav" id="mainNav">
                <?= $nav_html ?>
            </nav>

            <div class="header-actions zs-actions">
                <button type="button" class="header-action-btn zs-icon-btn" id="searchToggle" title="搜索">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </button>
                <a href="/user/find_order.php" class="header-action-btn header-action-btn--text zs-order-link" title="查询订单">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    <span>查单</span>
                </a>
                <?php
                $displayMoney = !empty($front_user['money'])
                    ? Currency::displayAmount((int) $front_user['money'])
                    : Currency::displayAmount(0);
                ?>
                <div class="header-user-dropdown zs-userbox">
                    <?php if (!empty($front_user)): ?>
                    <a href="/user/" class="header-user zs-user-trigger">
                        <?php if (!empty($front_user['avatar'])): ?>
                        <img src="<?= htmlspecialchars($front_user['avatar']) ?>" alt="" class="header-user-avatar">
                        <?php else: ?>
                        <span class="header-user-avatar header-user-avatar--default"><i class="fa fa-user"></i></span>
                        <?php endif; ?>
                        <span class="header-user-info">
                            <span class="header-user-name"><?= htmlspecialchars($front_user['nickname'] ?? $front_user['username'] ?? '') ?></span>
                            <span class="header-user-money"><?= htmlspecialchars($displayMoney) ?></span>
                        </span>
                    </a>
                    <div class="header-user-dropdown-panel">
                    <div class="header-user-menu zs-user-menu">
                        <a href="/user/order.php" class="header-user-menu-item"><i class="fa fa-file-text-o"></i>我的订单</a>
                        <a href="/user/wallet.php" class="header-user-menu-item"><i class="fa fa-credit-card"></i>我的钱包</a>
                        <a href="/user/balance_log.php" class="header-user-menu-item"><i class="fa fa-list-alt"></i>余额明细</a>
                        <?php if (MerchantContext::currentId() === 0): ?>
                        <a href="/user/rebate.php" class="header-user-menu-item"><i class="fa fa-share-alt"></i>我的推广</a>
                        <?php endif; ?>
                        <div class="header-user-menu-divider"></div>
                        <a href="?c=login&a=logout" class="header-user-menu-item header-user-menu-item--danger"><i class="fa fa-sign-out"></i>退出登录</a>
                    </div>
                    </div>
                    <?php else: ?>
                    <button type="button" class="header-user zs-user-trigger">
                        <span class="header-user-avatar header-user-avatar--default"><i class="fa fa-user"></i></span>
                    </button>
                    <div class="header-user-dropdown-panel">
                    <div class="header-user-menu zs-user-menu">
                        <a href="?c=login" data-pjax class="header-user-menu-item"><i class="fa fa-sign-in"></i>登录</a>
                        <a href="?c=register" data-pjax class="header-user-menu-item"><i class="fa fa-user-plus"></i>注册</a>
                    </div>
                    </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="header-menu-btn zs-menu-btn" id="menuToggle" title="菜单">
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                </button>
            </div>
        </div>
    </div>

    <div class="mobile-nav zs-mobile-nav" id="mobileNav">
        <div class="mobile-nav-inner zs-mobile-inner">
            <?php
            $mobileIcons = ['首页' => 'fa-home', '商城' => 'fa-shopping-bag', '博客' => 'fa-pencil'];
            foreach ($nav_items as $item):
                $isActive = ($item['text'] === '首页' && $nav_id === 'home')
                         || ($item['text'] === '商城' && $nav_id === 'goods')
                         || ($item['text'] === '博客' && $nav_id === 'blog');
                $active = $isActive ? ' active' : '';
                $icon = $mobileIcons[$item['text']] ?? 'fa-link';
                $hasChildren = !empty($item['children']);
            ?>
            <?php if ($hasChildren): ?>
            <div class="mobile-nav-group">
                <div class="mobile-nav-item mobile-nav-toggle<?= $active ?>">
                    <i class="fa <?= $icon ?>"></i>
                    <span><?= htmlspecialchars($item['text']) ?></span>
                    <i class="fa fa-chevron-down mobile-nav-arrow"></i>
                </div>
                <div class="mobile-nav-sub">
                    <?php foreach ($item['children'] as $child): ?>
                    <?php
                        $_cp = '/';
                        $_cip = parse_url((string) ($child['url'] ?? ''), PHP_URL_PATH);
                        if (is_string($_cip) && $_cip !== '') {
                            $_cp = $_cip;
                        }
                        $_cp = '/' . trim($_cp, '/');
                        $_childNavPathAttr = ($_cp !== '/') ? ' data-nav-path="' . htmlspecialchars($_cp, ENT_QUOTES, 'UTF-8') . '"' : '';
                    ?>
                    <a href="<?= htmlspecialchars($child['url']) ?>" data-pjax class="mobile-nav-sub-item"<?= $_childNavPathAttr ?>><?= htmlspecialchars($child['text']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <?php
                $_ip = '/';
                $_p = parse_url((string) ($item['url'] ?? ''), PHP_URL_PATH);
                if (is_string($_p) && $_p !== '') {
                    $_ip = $_p;
                }
                $_ip = '/' . trim($_ip, '/');
                $_itemNavPathAttr = ($_ip !== '/') ? ' data-nav-path="' . htmlspecialchars($_ip, ENT_QUOTES, 'UTF-8') . '"' : '';
            ?>
            <a href="<?= htmlspecialchars($item['url']) ?>" data-pjax class="mobile-nav-item<?= $active ?>"<?= $_itemNavPathAttr ?>>
                <i class="fa <?= $icon ?>"></i><span><?= htmlspecialchars($item['text']) ?></span>
            </a>
            <?php endif; ?>
            <?php endforeach; ?>
            <div class="mobile-nav-divider"></div>
            <?php if (!empty($front_user)): ?>
            <a href="/user/" class="mobile-nav-item">
                <i class="fa fa-user"></i><span>个人中心</span>
            </a>
            <a href="/user/order.php" class="mobile-nav-item">
                <i class="fa fa-file-text-o"></i><span>我的订单</span>
            </a>
            <a href="?c=login&a=logout" class="mobile-nav-item mobile-nav-logout">
                <i class="fa fa-sign-out"></i><span>退出登录</span>
            </a>
            <?php else: ?>
            <a href="?c=login" data-pjax class="mobile-nav-item">
                <i class="fa fa-sign-in"></i><span>登录</span>
            </a>
            <a href="?c=register" data-pjax class="mobile-nav-item">
                <i class="fa fa-user-plus"></i><span>注册</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="search-modal zs-search" id="searchModal">
    <div class="search-modal-mask"></div>
    <div class="search-modal-body zs-search-body">
        <div class="search-modal-tabs zs-search-tabs">
            <button type="button" class="search-modal-tab active" data-type="all">全部</button>
            <button type="button" class="search-modal-tab" data-type="goods">商品</button>
            <button type="button" class="search-modal-tab" data-type="article">文章</button>
        </div>
        <form id="searchModalForm" class="search-modal-bar zs-search-bar">
            <input type="text" id="searchModalInput" placeholder="输入魔法咒语… 啊不，关键词搜索" autocomplete="off">
            <button type="submit">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </button>
        </form>
        <div class="search-modal-hint">按 ESC 关闭传送门</div>
    </div>
</div>

<div id="main" data-nav-id="<?= htmlspecialchars($nav_id ?? '') ?>" data-nav-current-path="<?= htmlspecialchars($nav_current_path ?? '', ENT_QUOTES, 'UTF-8') ?>">
