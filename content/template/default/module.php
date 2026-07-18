<?php
defined('EM_ROOT') || exit('access denied!');

/**
 * 测试模板 - 模块逻辑
 *
 * 在模板渲染前执行，用于生成导航等模板变量。
 * 通过 $this（View 实例）注入变量到模板。
 *
 * 变量命名约定：
 *   nav_ 前缀的变量专供 header.php / footer.php 使用
 */

// ============================================================
// 1. 收集全局数据（由 Dispatcher 在 render 前注入）
// ============================================================
$data = $this->getData();
$controller   = $data['_controller'] ?? 'index';      // 当前控制器名（已被 HOMEPAGE_MODE 替换）
$homepageMode = $data['homepage_mode'] ?? 'mall';     // 'mall' / 'goods_list' / 'blog'
$isHomepage   = !empty($data['is_homepage']);         // 当前是否落在站点根 "/"（替换前的原始判断）

// ============================================================
// 2. 判断当前页面身份（决定哪个导航项高亮）
// ============================================================
$isMallMode      = ($homepageMode === 'mall');
$isGoodsListMode = ($homepageMode === 'goods_list');
$isBlogMode      = ($homepageMode === 'blog');

// is_homepage 优先 —— 三种首页模式下都让"首页"导航高亮，避免被替换后的 controller 干扰
if ($isHomepage) {
    $navId = 'home';
} elseif (in_array($controller, ['goods_list', 'goods', 'goods_index', 'goods_tag'], true)) {
    $navId = 'goods';
} elseif (in_array($controller, ['blog_list', 'blog_index', 'blog', 'blog_tag'], true)) {
    $navId = 'blog';
} else {
    $navId = '';
}

// ============================================================
// 3. 常用链接（其他页面可能用到）
// ============================================================
// URL 帮助函数按 Config('url_format') 生成对应格式。"商城/博客"导航的目标按当前
// 首页模式分流——首页占用了哪个入口，导航就指向另一种页面，避免点击和首页重复。
//   mall      ：首页=goods_index → 商城导航去 goods_list / 博客导航去 blog_index
//   goods_list：首页=goods_list  → 商城导航去 goods_index / 博客导航去 blog_index
//   blog      ：首页=blog_index  → 商城导航去 goods_index / 博客导航去 blog_list
$navGoodsUrl = $isMallMode ? url_goods_list() : url_goods_index();
$navBlogUrl  = $isBlogMode ? url_blog_list()  : url_blog_index();
$navSearchUrl = url_search();

// ============================================================
// 4. 从数据库加载导航（NaviModel）—— 按当前 MerchantContext 过滤：
//    主站只看主站导航 + 系统导航；商户看自己的自定义 + 系统导航
// ============================================================
$naviModel = new NaviModel();
$naviTree = $naviModel->getEnabledTree(MerchantContext::currentId());

// 系统导航的链接根据首页模式动态调整
$systemLinkMap = [
    '首页' => url_home(),
    '商城' => $navGoodsUrl,
    '博客' => $navBlogUrl,
];

// 构建 navItems 供模板使用
$navItems = [];
foreach ($naviTree as $nav) {
    $url = $nav['link'];
    // 系统导航根据首页模式动态覆盖链接
    if ($nav['type'] === 'system' && isset($systemLinkMap[$nav['name']])) {
        $url = $systemLinkMap[$nav['name']];
    }

    $item = [
        'id'       => 'nav_' . $nav['id'],
        'text'     => $nav['name'],
        'url'      => $url,
        'target'   => $nav['target'] ?? '_self',
        'children' => [],
    ];

    // 子导航
    if (!empty($nav['children'])) {
        foreach ($nav['children'] as $child) {
            $item['children'][] = [
                'text'   => $child['name'],
                'url'    => $child['link'],
                'target' => $child['target'] ?? '_self',
            ];
        }
    }

    $navItems[] = $item;
}

// 系统「博客」导航是否启用（禁用时首页不展示文章板块）
$navBlogEnabled = false;
foreach ($naviTree as $nav) {
    if (($nav['type'] ?? '') === 'system' && ($nav['name'] ?? '') === '博客') {
        $navBlogEnabled = true;
        break;
    }
}

// 当前请求的 path（用于 URL 精确匹配高亮，规范化为以 / 开头、无尾斜杠）
$currentPath = '/';
if (!empty($_SERVER['REQUEST_URI'])) {
    $p = parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_string($p) && $p !== '') {
        $currentPath = $p;
    }
}
$currentPath = '/' . trim($currentPath, '/');

// 生成 header 导航 HTML（支持二级下拉）
$navHtml = '';
foreach ($navItems as $item) {
    // 高亮判断：
    //   1) 主匹配：当前 URL path 和 nav item 的 URL path 完全一致（覆盖 CMS 页面、商品分类导航、自定义链接等）
    //   2) 兜底：对系统导航（首页 / 商城 / 博客），按名称 + $navId 匹配（让商品详情页也能高亮"商城"父项）
    $active = '';
    $itemText = $item['text'];

    $itemPath = '/';
    $ip = parse_url((string) ($item['url'] ?? ''), PHP_URL_PATH);
    if (is_string($ip) && $ip !== '') {
        $itemPath = $ip;
    }
    $itemPath = '/' . trim($itemPath, '/');

    if ($itemPath !== '/' && $itemPath === $currentPath) {
        // 非首页 + 精确匹配 → 高亮
        $active = ' active';
    } elseif ($itemText === '首页' && $navId === 'home') {
        $active = ' active';
    } elseif ($itemText === '商城' && $navId === 'goods') {
        $active = ' active';
    } elseif ($itemText === '博客' && $navId === 'blog') {
        $active = ' active';
    }

    $hasChildren = !empty($item['children']);
    $targetAttr = ($item['target'] === '_blank') ? ' target="_blank"' : '';
    $itemNavPathAttr = ($itemPath !== '/')
        ? (' data-nav-path="' . htmlspecialchars($itemPath, ENT_QUOTES, 'UTF-8') . '"')
        : '';

    if ($hasChildren) {
        $navHtml .= '<div class="nav-dropdown" data-nav="' . $item['id'] . '">';
        $navHtml .= '<a href="' . htmlspecialchars($item['url']) . '" data-pjax data-nav="' . $item['id'] . '" class="' . trim($active) . '"' . $itemNavPathAttr . $targetAttr . '>'
                  . htmlspecialchars($item['text'])
                  . '<svg class="nav-arrow" width="10" height="10" viewBox="0 0 10 10"><path d="M2 3.5L5 6.5L8 3.5" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                  . '</a>';
        $navHtml .= '<div class="nav-dropdown-menu">';
        foreach ($item['children'] as $child) {
            $childPath = '/';
            $cip = parse_url((string) ($child['url'] ?? ''), PHP_URL_PATH);
            if (is_string($cip) && $cip !== '') {
                $childPath = $cip;
            }
            $childPath = '/' . trim($childPath, '/');
            $childNavPathAttr = ($childPath !== '/')
                ? (' data-nav-path="' . htmlspecialchars($childPath, ENT_QUOTES, 'UTF-8') . '"')
                : '';
            $childTarget = ($child['target'] === '_blank') ? ' target="_blank"' : '';
            $navHtml .= '<a href="' . htmlspecialchars($child['url']) . '" data-pjax' . $childNavPathAttr . $childTarget . '>' . htmlspecialchars($child['text']) . '</a>';
        }
        $navHtml .= '</div></div>';
    } else {
        $navHtml .= '<a href="' . htmlspecialchars($item['url']) . '" data-pjax data-nav="' . $item['id'] . '" class="' . trim($active) . '"' . $itemNavPathAttr . $targetAttr . '>' . htmlspecialchars($item['text']) . '</a>';
    }
}

// 生成 footer 导航 HTML
$navFooterHtml = '';
foreach ($navItems as $item) {
    $navFooterHtml .= '<a href="' . htmlspecialchars($item['url']) . '" data-pjax>' . htmlspecialchars($item['text']) . '</a>';
}

// ============================================================
// 5. 前台用户登录状态（从数据库刷新实时数据）
// ============================================================
$frontUser = $_SESSION['em_front_user'] ?? null;
if ($frontUser && !empty($frontUser['id'])) {
    $freshUser = (new UserListModel())->findById((int) $frontUser['id']);
    if ($freshUser) {
        $frontUser['money']    = (int) ($freshUser['money'] ?? 0);
        $frontUser['nickname'] = (string) ($freshUser['nickname'] ?: $freshUser['username']);
        $frontUser['avatar']   = (string) $freshUser['avatar'];
        $frontUser['email']    = (string) $freshUser['email'];
        $frontUser['mobile']   = (string) ($freshUser['mobile'] ?? '');
        $_SESSION['em_front_user'] = $frontUser;
    }
}

// ============================================================
// 6. 注入模板变量（供 header.php / footer.php 直接输出）
// ============================================================
// ============================================================
// 7. ICP 备案 + 第三方统计代码
//    - 主站 / 二级域名访问：用主站 site_icp（同一个备案主体）
//    - 自定义顶级域名访问：必须用商户自己 merchant.icp
//      （因为该域名是商户独立备案的，不属于主站备案号）
//    - 统计代码暂不区分（只主站可配置，sub-站自动复用主站埋点；后续如需可扩）
// ============================================================
$_currentHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
if (($_p = strpos($_currentHost, ':')) !== false) $_currentHost = substr($_currentHost, 0, $_p);

$_mc = MerchantContext::current();
$_onCustomDomain = $_mc !== null
    && !empty($_mc['custom_domain'])
    && strtolower((string) $_mc['custom_domain']) === $_currentHost;

$siteIcp = $_onCustomDomain
    ? (string) ($_mc['icp'] ?? '')
    : (string) (Config::get('site_icp') ?? '');
$siteStatisticalCode = (string) (Config::get('site_statistical_code') ?? '');
$userLoginEnabled    = (string) (Config::get('user_login', '1')) === '1';
$userRegisterEnabled = (string) (Config::get('user_register', '0')) === '1';

// ============================================================
// 8. 注入模板变量（供 header.php / footer.php 直接输出）
// ============================================================
$this->assign([
    'nav_html'              => $navHtml,
    'nav_items'             => $navItems,
    'nav_footer_html'       => $navFooterHtml,
    'nav_current_path'      => $currentPath,
    'nav_id'                => $navId,
    'nav_goods_url'         => $navGoodsUrl,
    'nav_blog_url'          => $navBlogUrl,
    'nav_blog_enabled'      => $navBlogEnabled,
    'nav_search_url'        => $navSearchUrl,
    'user_login_enabled'    => $userLoginEnabled,
    'user_register_enabled' => $userRegisterEnabled,
    'front_user'            => $frontUser,
    'site_icp'              => $siteIcp,
    'site_statistical_code' => $siteStatisticalCode,
]);
