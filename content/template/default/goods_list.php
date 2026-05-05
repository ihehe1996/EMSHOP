<?php
defined('EM_ROOT') || exit('access denied!');

// $current_category 由 GoodsController 设置：category_id 或 slug 解析后的 id
$current_category = isset($current_category) ? (int) $current_category : (int) ($_GET['category_id'] ?? 0);
$current_category_source = isset($current_category_source) ? (string) $current_category_source : (string) ($_GET['category_source'] ?? '');
$goods_sort = isset($goods_sort) ? (string) $goods_sort : 'default';
if (!in_array($goods_sort, ['default', 'hot', 'sold', 'price_asc', 'price_desc'], true)) {
    $goods_sort = 'default';
}
$_goods_sort_extra = ($goods_sort !== 'default') ? ['sort' => $goods_sort] : [];
$goods_list_base_params = [];
if ($current_category > 0) {
    $goods_list_base_params['category_id'] = $current_category;
    if ($current_category_source === 'merchant') {
        $goods_list_base_params['category_source'] = 'merchant';
    }
}
if (isset($current_tag) && (int) $current_tag > 0) {
    $goods_list_base_params['tag_id'] = (int) $current_tag;
}
?>
<!-- 商品列表 · GoodsController::_list() -->

<?php
// 店铺公告 —— 当前 scope 已设公告且勾选了"商品列表页"展示位置时输出
$_announce = $announcement ?? null;
if (is_array($_announce) && !empty($_announce['html']) && in_array('goods_list', $_announce['positions'] ?? [], true)):
?>
<div class="wrapper">
    <div class="site-announcement">
        <div class="site-announcement__head">
            <span class="site-announcement__icon"><i class="fa fa-bullhorn"></i></span>
            <span class="site-announcement__title">店铺公告</span>
            <span class="site-announcement__title-sep"></span>
        </div>
        <div class="site-announcement__body"><?= $_announce['html'] ?></div>
    </div>
</div>
<?php endif; ?>

<div class="page-body">

    <!-- 面包屑 -->
    <div class="breadcrumb">
        <a href="<?= url_home() ?>" data-pjax>首页</a>
        <span class="sep">/</span>
        商品列表
    </div>

    <!-- 分类筛选 -->
    <?php if (!empty($goods_categories)): ?>
    <?php
    // 判断当前分类是否属于某个父级（用于高亮父级 + 展开子级）
    $activeParentId = 0;
    foreach ($goods_categories as $_cat) {
        if ((int) $_cat['id'] === $current_category) { $activeParentId = (int) $_cat['id']; break; }
        foreach ($_cat['children'] ?? [] as $_child) {
            if ((int) $_child['id'] === $current_category) { $activeParentId = (int) $_cat['id']; break 2; }
        }
    }
    ?>
    <?php
    // 计算全部商品数（顶级分类自身 + 子分类之和）
    $allGoodsCount = 0;
    foreach ($goods_categories as $_c) {
        $allGoodsCount += (int) $_c['goods_count'];
        foreach ($_c['children'] ?? [] as $_ch) {
            $allGoodsCount += (int) $_ch['goods_count'];
        }
    }
    ?>
    <div class="category-tabs">
        <a href="<?= url_goods_list($_goods_sort_extra) ?>"
           class="category-tab<?= $current_category === 0 ? ' active' : '' ?>"
           data-pjax>
            <span class="category-tab-icon" aria-hidden="true" style="display:inline-flex;align-items:center;justify-content:center;background:#eef2ff;color:#4e6ef2;font-size:14px;">
                <i class="fa fa-th-large"></i>
            </span>
            <span class="category-tab-name">全部</span>
            <span class="category-tab-count"><?= $allGoodsCount ?></span>
        </a>
        <?php foreach ($goods_categories as $cat): ?>
        <?php
        // 父分类商品数包含子分类
        $catCount = (int) $cat['goods_count'];
        foreach ($cat['children'] ?? [] as $_ch) { $catCount += (int) $_ch['goods_count']; }
        ?>
        <a href="<?= url_goods_category($cat, $_goods_sort_extra) ?>"
           class="category-tab<?= (int) $cat['id'] === $current_category || ((int) $cat['id'] === $activeParentId && (int) $cat['id'] !== $current_category) ? ' active' : '' ?>"
           data-pjax>
            <?php $catImg = $cat['cover_image'] ?: ($cat['icon'] ?? ''); ?>
            <?php if ($catImg !== ''): ?>
            <img class="category-tab-icon" src="<?= htmlspecialchars($catImg) ?>" alt="">
            <?php endif; ?>
            <span class="category-tab-name"><?= htmlspecialchars($cat['name']) ?></span>
            <span class="category-tab-count"><?= $catCount ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php if ($activeParentId > 0): ?>
    <?php
    $activeChildren = [];
    foreach ($goods_categories as $_cat) {
        if ((int) $_cat['id'] === $activeParentId) { $activeChildren = $_cat['children'] ?? []; break; }
    }
    ?>
    <?php if (!empty($activeChildren)): ?>
    <div class="category-tabs category-tabs--sub">
        <?php
        // 父分类"全部"链接：从 $goods_categories 找到对应行以便取 slug
        $activeParentRow = ['id' => $activeParentId];
        foreach ($goods_categories as $_c) {
            if ((int) $_c['id'] === $activeParentId) { $activeParentRow = $_c; break; }
        }
        ?>
        <a href="<?= url_goods_category($activeParentRow, $_goods_sort_extra) ?>"
           class="category-tab<?= $current_category === $activeParentId ? ' active' : '' ?>"
           data-pjax>
            <span class="category-tab-name">全部</span>
        </a>
        <?php foreach ($activeChildren as $child): ?>
        <a href="<?= url_goods_category($child, $_goods_sort_extra) ?>"
           class="category-tab<?= (int) $child['id'] === $current_category ? ' active' : '' ?>"
           data-pjax>
            <span class="category-tab-name"><?= htmlspecialchars($child['name']) ?></span>
            <span class="category-tab-count"><?= (int) $child['goods_count'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>

    <?php
    $_goods_sort_opts = [
        'default'    => '默认排序',
        'hot'        => '热度优先',
        'sold'       => '销量优先',
        'price_asc'  => '价格升序',
        'price_desc' => '价格降序',
    ];
    ?>
    <div class="goods-sort-bar" role="toolbar" aria-label="商品排序">
        <?php foreach ($_goods_sort_opts as $_sk => $_sl): ?>
        <?php
        $_hrefParams = $goods_list_base_params;
        if ($_sk !== 'default') {
            $_hrefParams['sort'] = $_sk;
        }
        ?>
        <a href="<?= htmlspecialchars(url_goods_list($_hrefParams), ENT_QUOTES, 'UTF-8') ?>"
           class="goods-sort-link<?= $goods_sort === $_sk ? ' active' : '' ?>"
           data-pjax><?= htmlspecialchars($_sl) ?></a>
        <?php endforeach; ?>
    </div>

    <!-- 商品网格 -->
    <?php if (!empty($goods_list)): ?>
    <div class="goods-grid">
        <?php foreach ($goods_list as $g): ?>
        <a <?= goods_card_href_attrs($g) ?> class="card goods-card">
            <div class="card-img">
                <?php if (trim((string) ($g['image'] ?? '')) !== ''): ?>
                <img src="<?= htmlspecialchars($g['image']) ?>" alt="<?= htmlspecialchars($g['name']) ?>">
                <?php else: ?>
                <div class="goods-no-image" aria-hidden="true"></div>
                <?php endif; ?>
                <?php if (($g['delivery_type'] ?? '') === 'auto'): ?>
                <span class="goods-badge goods-badge--auto">自动发货</span>
                <?php elseif (($g['delivery_type'] ?? '') === 'manual'): ?>
                <span class="goods-badge goods-badge--manual">人工发货</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="card-title"><?= htmlspecialchars($g['name']) ?></div>
                <div class="card-stats">
                    <span>库存 <?= htmlspecialchars((string) ($g['stock_text'] ?? '0')) ?></span>
                    <span>销量 <?= (int) ($g['sold'] ?? 0) ?></span>
                </div>
                <div class="card-bottom">
                    <span class="price"><?= Currency::displayMain((float) $g['price']) ?></span>
                    <?php if (!empty($g['original_price'])): ?>
                    <span class="price-original"><?= Currency::displayMain((float) $g['original_price']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <!-- 分页 -->
    <?php if (!empty($pagination) && $pagination['total_pages'] > 1): ?>
    <?php
    $pg = $pagination;
    $pgParams = $goods_list_base_params;
    if ($goods_sort !== 'default') {
        $pgParams['sort'] = $goods_sort;
    }
    ?>
    <div class="pagination">
        <?php if ($pg['page'] > 1): ?>
        <a href="<?= url_goods_list(array_merge($pgParams, ['page' => $pg['page'] - 1])) ?>" class="pagination-btn" data-pjax><i class="fa fa-chevron-left"></i></a>
        <?php else: ?>
        <span class="pagination-btn disabled"><i class="fa fa-chevron-left"></i></span>
        <?php endif; ?>

        <?php
        // 页码范围：最多显示 5 个页码
        $start = max(1, $pg['page'] - 2);
        $end = min($pg['total_pages'], $start + 4);
        $start = max(1, $end - 4);
        ?>
        <?php if ($start > 1): ?>
        <a href="<?= url_goods_list(array_merge($pgParams, ['page' => 1])) ?>" class="pagination-num" data-pjax>1</a>
        <?php if ($start > 2): ?><span class="pagination-dots">...</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
        <?php if ($i === $pg['page']): ?>
        <span class="pagination-num active"><?= $i ?></span>
        <?php else: ?>
        <a href="<?= url_goods_list(array_merge($pgParams, ['page' => $i])) ?>" class="pagination-num" data-pjax><?= $i ?></a>
        <?php endif; ?>
        <?php endfor; ?>

        <?php if ($end < $pg['total_pages']): ?>
        <?php if ($end < $pg['total_pages'] - 1): ?><span class="pagination-dots">...</span><?php endif; ?>
        <a href="<?= url_goods_list(array_merge($pgParams, ['page' => $pg['total_pages']])) ?>" class="pagination-num" data-pjax><?= $pg['total_pages'] ?></a>
        <?php endif; ?>

        <?php if ($pg['page'] < $pg['total_pages']): ?>
        <a href="<?= url_goods_list(array_merge($pgParams, ['page' => $pg['page'] + 1])) ?>" class="pagination-btn" data-pjax><i class="fa fa-chevron-right"></i></a>
        <?php else: ?>
        <span class="pagination-btn disabled"><i class="fa fa-chevron-right"></i></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="card empty-state">
        <div class="empty-icon">&#128722;</div>
        <h3>暂无商品</h3>
        <p>还没有上架任何商品</p>
    </div>
    <?php endif; ?>

</div>
