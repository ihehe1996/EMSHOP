<?php

declare(strict_types=1);

/**
 * 前台模板辅助函数。
 *
 * 提供模板中常用的 URL 构建和格式化函数。
 * 该文件由 init.php 自动加载，所有模板中可直接调用。
 */

/**
 * 当前 URL 格式（读一次 Config 缓存到 static）。
 *
 * 四种模式：
 *   default  原始 query string 格式      ?post=1 / ?blog=1 / ?c=goods_list
 *   file     文件格式 (.html 结尾)        /post-1.html / /blog-1.html / /post-list.html
 *   dir1     目录格式 · 前缀 post/blog    /post/1 / /blog/1 / /post/list
 *   dir2     目录格式 · 前缀 buy/blog     /buy/1 / /blog/1 / /buy/list
 */
function url_format(): string
{
    static $fmt = null;
    if ($fmt === null) {
        $fmt = (string) Config::get('url_format', 'default');
        if (!in_array($fmt, ['default', 'file', 'dir1', 'dir2'], true)) $fmt = 'default';
    }
    return $fmt;
}

/**
 * 根据当前模式返回商品路径前缀（post / buy）。
 */
function url_goods_prefix(): string
{
    return url_format() === 'dir2' ? 'buy' : 'post';
}

/**
 * 把 query 参数附到已有 URL 上，兼容 '?' 与 '&'。
 *
 * @param string $url 已经带 '?' 或全路径的 URL
 * @param array  $params 额外参数
 */
function url_append(string $url, array $params): string
{
    if (empty($params)) return $url;
    $sep = strpos($url, '?') === false ? '?' : '&';
    $parts = [];
    foreach ($params as $k => $v) $parts[] = urlencode((string) $k) . '=' . urlencode((string) $v);
    return $url . $sep . implode('&', $parts);
}

/**
 * 构建商品详情页 URL。
 */
function url_goods(int $id, array $params = []): string
{
    $prefix = url_goods_prefix();
    switch (url_format()) {
        case 'file':
            return url_append('/' . $prefix . '-' . $id . '.html', $params);
        case 'dir1':
        case 'dir2':
            return url_append('/' . $prefix . '/' . $id, $params);
        default:
            return url_append('/?post=' . $id, $params);
    }
}

/**
 * 商品卡片 <a> 标签的属性串：
 *   - jump_url 非空：直接跳外链（target=_blank + rel=nofollow noopener）
 *   - 否则：跳商品详情页（同站）
 *
 * 用法：<a <?= goods_card_href_attrs($g) ?> class="card goods-card">...
 *
 * @param array<string,mixed> $row 商品行（来自 BaseController::queryGoodsList* 的输出，至少含 id 和 jump_url）
 */
function goods_card_href_attrs(array $row): string
{
    $jump = trim((string) ($row['jump_url'] ?? ''));
    if ($jump !== '') {
        return 'href="' . htmlspecialchars($jump, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="nofollow noopener"';
    }
    return 'href="' . htmlspecialchars(url_goods((int) ($row['id'] ?? 0)), ENT_QUOTES, 'UTF-8') . '"';
}

/**
 * 构建商品列表页 URL。
 * 支持 slug / category_id / tag_id / page 等 params。
 *
 * 文件 / 目录格式分类 + 分页路径规则：
 *   /post-list.html               → 全部 · 第 1 页
 *   /post-list-all-N.html         → 全部 · 第 N 页
 *   /post-list-ID.html            → 分类 id=ID · 第 1 页
 *   /post-list-ID-N.html          → 分类 id=ID · 第 N 页
 *   /post-SLUG.html               → slug 分类 · 第 1 页
 *   /post-SLUG-N.html             → slug 分类 · 第 N 页（末段数字=页码）
 *
 *   目录格式同构：/post/list、/post/list/all/N、/post/list/ID[/N]、/post/c/SLUG[/N]
 */
function url_goods_list(array $params = []): string
{
    $prefix     = url_goods_prefix();
    $fmt        = url_format();
    $slug       = isset($params['slug']) ? (string) $params['slug'] : '';
    $categoryId = isset($params['category_id']) ? (int) $params['category_id'] : 0;
    $page       = isset($params['page']) ? (int) $params['page'] : 0;
    unset($params['slug'], $params['category_id'], $params['page']);

    switch ($fmt) {
        case 'file':
            if ($slug !== '') {
                $pageSuffix = $page > 1 ? '-' . $page : '';
                return url_append('/' . $prefix . '-' . rawurlencode($slug) . $pageSuffix . '.html', $params);
            }
            if ($categoryId > 0) {
                $pageSuffix = $page > 1 ? '-' . $page : '';
                return url_append('/' . $prefix . '-list-' . $categoryId . $pageSuffix . '.html', $params);
            }
            if ($page > 1) {
                return url_append('/' . $prefix . '-list-all-' . $page . '.html', $params);
            }
            return url_append('/' . $prefix . '-list.html', $params);

        case 'dir1':
        case 'dir2':
            if ($slug !== '') {
                $pageSuffix = $page > 1 ? '/' . $page : '';
                return url_append('/' . $prefix . '/c/' . rawurlencode($slug) . $pageSuffix, $params);
            }
            if ($categoryId > 0) {
                $pageSuffix = $page > 1 ? '/' . $page : '';
                return url_append('/' . $prefix . '/list/' . $categoryId . $pageSuffix, $params);
            }
            if ($page > 1) {
                return url_append('/' . $prefix . '/list/all/' . $page, $params);
            }
            return url_append('/' . $prefix . '/list', $params);

        default: // query string
            $parts = ['c=goods_list'];
            if ($slug !== '')      $parts[] = 'slug=' . rawurlencode($slug);
            if ($categoryId > 0)   $parts[] = 'category_id=' . $categoryId;
            if ($page > 1)         $parts[] = 'page=' . $page;
            foreach ($params as $k => $v) $parts[] = urlencode((string) $k) . '=' . urlencode((string) $v);
            return '/?' . implode('&', $parts);
    }
}

/**
 * 按分类构建商品列表页 URL（有 slug 优先 slug，无 slug 用 id）。
 *
 * 商户自建分类（source=merchant）会在 URL 上加 category_source=merchant，
 * 让 GoodsController._list 区分主站分类与商户分类（id 在两套表里可能撞号）。
 *
 * @param array<string,mixed> $extra 合并进列表 URL 的查询参数（如 sort、tag_id）
 */
function url_goods_category(array $cat, array $extra = []): string
{
    $isMerchantCat = (string) ($cat['source'] ?? 'main') === 'merchant';
    $slug = trim((string) ($cat['slug'] ?? ''));
    // 商户自建分类没有 slug —— 永远走 category_id 路径，并带上 source 参数
    if ($isMerchantCat) {
        return url_goods_list(array_merge([
            'category_id'     => (int) $cat['id'],
            'category_source' => 'merchant',
        ], $extra));
    }
    if ($slug !== '') return url_goods_list(array_merge(['slug' => $slug], $extra));
    return url_goods_list(array_merge(['category_id' => (int) $cat['id']], $extra));
}

/**
 * 商城首页 URL（显式指向 goods_index 控制器）。
 *
 * 不能简单返回 "/" —— 因为 "/" 在 HOMEPAGE_MODE='blog' / 'goods_list' 时
 * 会被 Dispatcher 替换成博客首页 / 商品列表，点"商城"导航就回不到商城首页了。
 * 必须用显式路径，让路由跳过首页模式替换。
 */
function url_goods_index(array $params = []): string
{
    switch (url_format()) {
        case 'file':
            return url_append('/post.html', $params);
        case 'dir1':
        case 'dir2':
            return url_append('/post/', $params);
        default:
            return url_append('/?c=goods_index', $params);
    }
}

/**
 * 站点首页 URL —— 永远是 "/"，跟随后台 homepage_mode 走对应控制器。
 * 用于 logo / "首页"导航等"回到当前站点根入口"的场景。
 */
function url_home(): string { return '/'; }

/**
 * 构建商品标签页 URL。
 * file/dir 模式下 page 走路径：/tag-1-2.html、/tag/1/2
 */
function url_goods_tag(int $id, array $params = []): string
{
    $page = isset($params['page']) ? (int) $params['page'] : 0;
    unset($params['page']);

    switch (url_format()) {
        case 'file':
            $pageSuffix = $page > 1 ? '-' . $page : '';
            return url_append('/tag-' . $id . $pageSuffix . '.html', $params);
        case 'dir1':
        case 'dir2':
            $pageSuffix = $page > 1 ? '/' . $page : '';
            return url_append('/tag/' . $id . $pageSuffix, $params);
        default:
            $base = '/?c=goods_tag&id=' . $id;
            if ($page > 1) $base .= '&page=' . $page;
            return url_append($base, $params);
    }
}

/**
 * 构建博客详情页 URL。
 */
function url_blog(int $id, array $params = []): string
{
    switch (url_format()) {
        case 'file': return url_append('/blog-' . $id . '.html', $params);
        case 'dir1':
        case 'dir2': return url_append('/blog/' . $id, $params);
        default:     return url_append('/?blog=' . $id, $params);
    }
}

/**
 * 构建文章列表页 URL。
 *
 * 路径规则与商品列表同构：
 *   /blog-list.html / /blog-list-all-N.html / /blog-list-ID[-N].html / /blog-SLUG[-N].html
 *   /blog/list / /blog/list/all/N / /blog/list/ID[/N] / /blog/c/SLUG[/N]
 */
function url_blog_list(array $params = []): string
{
    $slug       = isset($params['slug']) ? (string) $params['slug'] : '';
    $categoryId = isset($params['category_id']) ? (int) $params['category_id'] : 0;
    $page       = isset($params['page']) ? (int) $params['page'] : 0;
    unset($params['slug'], $params['category_id'], $params['page']);

    switch (url_format()) {
        case 'file':
            if ($slug !== '') {
                $pageSuffix = $page > 1 ? '-' . $page : '';
                return url_append('/blog-' . rawurlencode($slug) . $pageSuffix . '.html', $params);
            }
            if ($categoryId > 0) {
                $pageSuffix = $page > 1 ? '-' . $page : '';
                return url_append('/blog-list-' . $categoryId . $pageSuffix . '.html', $params);
            }
            if ($page > 1) return url_append('/blog-list-all-' . $page . '.html', $params);
            return url_append('/blog-list.html', $params);

        case 'dir1':
        case 'dir2':
            if ($slug !== '') {
                $pageSuffix = $page > 1 ? '/' . $page : '';
                return url_append('/blog/c/' . rawurlencode($slug) . $pageSuffix, $params);
            }
            if ($categoryId > 0) {
                $pageSuffix = $page > 1 ? '/' . $page : '';
                return url_append('/blog/list/' . $categoryId . $pageSuffix, $params);
            }
            if ($page > 1) return url_append('/blog/list/all/' . $page, $params);
            return url_append('/blog/list', $params);

        default:
            $parts = ['c=blog_list'];
            if ($slug !== '')    $parts[] = 'slug=' . rawurlencode($slug);
            if ($categoryId > 0) $parts[] = 'category_id=' . $categoryId;
            if ($page > 1)       $parts[] = 'page=' . $page;
            foreach ($params as $k => $v) $parts[] = urlencode((string) $k) . '=' . urlencode((string) $v);
            return '/?' . implode('&', $parts);
    }
}

/**
 * 博客首页 URL。
 */
function url_blog_index(array $params = []): string
{
    switch (url_format()) {
        case 'file': return url_append('/blog.html', $params);
        case 'dir1':
        case 'dir2': return url_append('/blog/', $params);
        default:     return url_append('/?c=blog_index', $params);
    }
}

/**
 * 博客标签页 URL。
 * file/dir 模式下 page 走路径：/blog-tag-1-2.html、/blog/tag/1/2
 */
function url_blog_tag(int $id, array $params = []): string
{
    $page = isset($params['page']) ? (int) $params['page'] : 0;
    unset($params['page']);

    switch (url_format()) {
        case 'file':
            $pageSuffix = $page > 1 ? '-' . $page : '';
            return url_append('/blog-tag-' . $id . $pageSuffix . '.html', $params);
        case 'dir1':
        case 'dir2':
            $pageSuffix = $page > 1 ? '/' . $page : '';
            return url_append('/blog/tag/' . $id . $pageSuffix, $params);
        default:
            $base = '/?c=blog_tag&id=' . $id;
            if ($page > 1) $base .= '&page=' . $page;
            return url_append($base, $params);
    }
}

/**
 * 搜索结果页 URL。
 */
function url_search(string $keyword = ''): string
{
    $kw = trim($keyword);
    switch (url_format()) {
        case 'file':
            return $kw === '' ? '/search.html' : '/search-' . rawurlencode($kw) . '.html';
        case 'dir1':
        case 'dir2':
            return $kw === '' ? '/search/' : '/search/' . rawurlencode($kw);
        default:
            return $kw === '' ? '/?c=search' : '/?c=search&q=' . urlencode($kw);
    }
}

/**
 * 优惠券中心 URL。
 */
function url_coupon(): string
{
    switch (url_format()) {
        case 'file': return '/coupon.html';
        case 'dir1':
        case 'dir2': return '/coupon/';
        default:     return '/?c=coupon';
    }
}

/**
 * 是否启用优惠券（后台「商城设置 → 启用优惠券」；配置为 '0' 时关闭）。
 */
function shop_coupon_enabled(): bool
{
    return (string) Config::get('shop_enable_coupon', '1') !== '0';
}

/**
 * 格式化价格。
 */
function format_price(float $price): string
{
    return '¥' . number_format($price, 2);
}

/**
 * 截断文本。
 */
function truncate(string $text, int $length = 100, string $suffix = '...'): string
{
    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length, 'UTF-8') . $suffix;
}

/**
 * 检测当前请求终端类型（与 Dispatcher 保持一致）。
 */
function current_device_type(): string
{
    $device = trim((string) Input::get('device', ''));
    if ($device === 'mobile' || $device === 'pc') {
        return $device;
    }

    $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    foreach (['mobile', 'android', 'iphone', 'ipad', 'ipod', 'windows phone'] as $keyword) {
        if (stripos($agent, $keyword) !== false) {
            return 'mobile';
        }
    }

    return 'pc';
}

/**
 * 当前请求实际启用的主题名。
 */
function active_theme_name(string $fallback = 'default'): string
{
    static $theme = null;
    if ($theme !== null) {
        return $theme !== '' ? $theme : $fallback;
    }

    try {
        $scope = MerchantContext::currentId() > 0
            ? 'merchant_' . MerchantContext::currentId()
            : 'main';
        $theme = (string) ((new TemplateModel())->getActiveTheme(current_device_type(), $scope) ?? '');
    } catch (Throwable $e) {
        $theme = '';
    }

    return $theme !== '' ? $theme : $fallback;
}

/**
 * 当前主题资源 URL。
 */
function theme_asset_url(string $path, ?string $theme = null): string
{
    $themeName = trim((string) ($theme ?? active_theme_name()));
    $relative = ltrim($path, '/');
    return '/content/template/' . rawurlencode($themeName) . '/' . $relative;
}
