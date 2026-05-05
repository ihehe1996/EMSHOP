<?php
/*
Template Name:默认模板
Version:1.0.0
Template Url:
Description:系统内置默认模板
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
// 给 JS 注入按当前 url_format 生成好的入口 URL
window.EMSHOP_URLS = {
    coupon:     <?= json_encode(url_coupon()) ?>,
    goodsList:  <?= json_encode(url_goods_list()) ?>,
    blogList:   <?= json_encode(url_blog_list()) ?>,
    search:     <?= json_encode(url_search()) ?>
};
// 货币展示：JS 算动态金额时用 —— 数据从 data-price / spec.price 拿到主货币数值（BigInt 除 1e6 的元值），
// 显示前 × EMSHOP_CURRENCY.rate 换算成访客币种，并拼上 EMSHOP_CURRENCY.symbol
// 发给后端（AJAX）的金额保持主货币数值，不乘 rate，保证后端用统一单位
window.EMSHOP_CURRENCY = {
    code:   <?= json_encode($currency_code ?? '') ?>,
    symbol: <?= json_encode($currency_symbol ?? '¥') ?>,
    rate:   <?= json_encode((float) ($currency_rate ?? 1)) ?>
};
</script>
</head>
<body>
<!-- PJAX 加载进度条 -->
<div class="pjax-bar" id="pjaxBar"></div>

<!-- 顶部导航 -->
<header class="site-header" id="siteHeader">
<div class="wrapper">
    <!-- Logo -->
    <?php $logoType = $site_logo_type ?? 'text'; ?>
    <a href="<?= url_home() ?>" data-pjax class="site-logo">
        <?php if ($logoType === 'image' && !empty($site_logo)): ?>
        <img src="<?= htmlspecialchars($site_logo) ?>" alt="<?= htmlspecialchars($site_name ?? 'EMSHOP') ?>" class="site-logo-img">
        <?php else: ?>
        <span class="site-logo-text"><?= htmlspecialchars($site_name ?? 'EMSHOP') ?></span>
        <?php endif; ?>
    </a>

    <!-- 主导航 -->
    <nav class="main-nav" id="mainNav">
        <?= $nav_html ?>
    </nav>

    <!-- 右侧功能区 -->
    <div class="header-actions">
        <button type="button" class="header-action-btn" id="searchToggle" title="搜索">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>
        <a href="/user/find_order.php" class="header-action-btn header-action-btn--text" title="查询订单">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            <span>查询订单</span>
        </a>
        <?php
        // 余额按访客当前展示币种换算 + 加符号一起输出（内部走 visitorCode 换算）
        // 数据库里 money 是主货币 BIGINT ×1000000，displayAmount 自动还原 + 乘汇率
        $displayMoney = !empty($front_user['money'])
            ? Currency::displayAmount((int) $front_user['money'])
            : Currency::displayAmount(0);
        ?>
        <div class="header-user-dropdown">
            <?php if (!empty($front_user)): ?>
            <!-- 已登录：头像 + 账号 + 余额 -->
            <a href="/user/" class="header-user">
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
            <div class="header-user-menu">
                <a href="/user/order.php" class="header-user-menu-item"><i class="fa fa-file-text-o"></i>我的订单</a>
                <a href="/user/wallet.php" class="header-user-menu-item"><i class="fa fa-credit-card"></i>我的钱包</a>
                <a href="/user/balance_log.php" class="header-user-menu-item"><i class="fa fa-list-alt"></i>余额明细</a>
                <?php // 推广 / 返佣只在主站启用；商户子域名下隐藏入口 ?>
                <?php if (MerchantContext::currentId() === 0): ?>
                <a href="/user/rebate.php" class="header-user-menu-item"><i class="fa fa-share-alt"></i>我的推广</a>
                <?php endif; ?>
                <div class="header-user-menu-divider"></div>
                <a href="?c=login&a=logout" class="header-user-menu-item header-user-menu-item--danger"><i class="fa fa-sign-out"></i>退出登录</a>
            </div>
            <?php else: ?>
            <!-- 未登录：默认头像 + 下拉菜单 -->
            <button type="button" class="header-user">
                <span class="header-user-avatar header-user-avatar--default"><i class="fa fa-user"></i></span>
            </button>
            <div class="header-user-menu">
                <a href="?c=login" data-pjax class="header-user-menu-item"><i class="fa fa-sign-in"></i>登录</a>
                <a href="?c=register" data-pjax class="header-user-menu-item"><i class="fa fa-user-plus"></i>注册</a>
            </div>
            <?php endif; ?>
        </div>
        <!-- 移动端菜单按钮 -->
        <button type="button" class="header-menu-btn" id="menuToggle" title="菜单">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>
    </div>
</div>

<!-- 移动端导航菜单 -->
<div class="mobile-nav" id="mobileNav">
    <div class="mobile-nav-inner">
        <?php
        // 按导航名称分配图标
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

<!-- 搜索弹窗 -->
<div class="search-modal" id="searchModal">
    <div class="search-modal-mask"></div>
    <div class="search-modal-body">
        <div class="search-modal-tabs">
            <button type="button" class="search-modal-tab active" data-type="all">全部</button>
            <button type="button" class="search-modal-tab" data-type="goods">商品</button>
            <button type="button" class="search-modal-tab" data-type="article">文章</button>
        </div>
        <form id="searchModalForm" class="search-modal-bar">
            <input type="text" id="searchModalInput" placeholder="输入关键词搜索..." autocomplete="off">
            <button type="submit">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </button>
        </form>
        <div class="search-modal-hint">按 ESC 关闭</div>
    </div>
</div>

<!-- 主内容区域（PJAX 容器） · data-nav-id 让 JS 在 PJAX 切换后同步刷新顶部导航高亮 -->
<div id="main" data-nav-id="<?= htmlspecialchars($nav_id ?? '') ?>" data-nav-current-path="<?= htmlspecialchars($nav_current_path ?? '', ENT_QUOTES, 'UTF-8') ?>">
