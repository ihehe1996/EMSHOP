<?php
defined('EM_ROOT') || exit('access denied!');

$search_type = trim($_GET['type'] ?? 'all');
if (!in_array($search_type, ['all', 'goods', 'article'])) {
    $search_type = 'all';
}
?>
<!-- 搜索结果 · SearchController::_index() -->
<div class="page-body">

    <div class="page-title">搜索结果</div>

    <!-- 搜索表单（结果页内也可再次搜索） -->
    <form class="search-box" method="get" data-pjax>
        <input type="hidden" name="c" value="search">
        <input type="hidden" name="type" value="<?= htmlspecialchars($search_type) ?>">
        <input type="text" name="q" class="search-input" placeholder="输入关键词搜索..." value="<?= htmlspecialchars($keyword ?? '') ?>">
        <button type="submit" class="btn btn-primary">搜索</button>
    </form>

    <!-- 类型切换 Tab -->
    <?php if (!empty($keyword)): ?>
    <div class="search-type-tabs">
        <a href="<?= url_search($keyword) ?>&type=all" data-pjax
           class="search-type-tab<?= $search_type === 'all' ? ' active' : '' ?>">全部</a>
        <a href="<?= url_search($keyword) ?>&type=goods" data-pjax
           class="search-type-tab<?= $search_type === 'goods' ? ' active' : '' ?>">商品</a>
        <a href="<?= url_search($keyword) ?>&type=article" data-pjax
           class="search-type-tab<?= $search_type === 'article' ? ' active' : '' ?>">文章</a>
    </div>

    <div class="search-hint" style="margin-bottom:16px;">
        找到 <?= (int) ($result_count ?? 0) ?> 个与 "<?= htmlspecialchars($keyword) ?>" 相关的结果
    </div>
    <?php endif; ?>

    <!-- 商品结果（type=all 或 type=goods 时显示） -->
    <?php if ($search_type !== 'article' && !empty($results)): ?>
    <?php if ($search_type === 'all'): ?>
    <div class="section-header" style="margin-bottom:12px;">
        <div class="section-title" style="font-size:15px;">商品</div>
    </div>
    <?php endif; ?>
    <div class="goods-grid" style="margin-bottom:32px;">
        <?php foreach ($results as $item): ?>
        <a <?= goods_card_href_attrs($item) ?> class="card goods-card">
            <div class="card-img">
                <?php if (trim((string) ($item['image'] ?? '')) !== ''): ?>
                <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                <?php else: ?>
                <div class="goods-no-image" aria-hidden="true"></div>
                <?php endif; ?>
                <?php if (($item['delivery_type'] ?? '') === 'auto'): ?>
                <span class="goods-badge goods-badge--auto">自动发货</span>
                <?php elseif (($item['delivery_type'] ?? '') === 'manual'): ?>
                <span class="goods-badge goods-badge--manual">人工发货</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="card-title"><?= htmlspecialchars($item['name']) ?></div>
                <div class="card-stats">
                    <span>库存 <?= htmlspecialchars((string) ($item['stock_text'] ?? '0')) ?></span>
                    <span>销量 <?= (int) ($item['sold'] ?? 0) ?></span>
                </div>
                <div class="card-bottom">
                    <span class="price"><?= Currency::displayMain((float) $item['price']) ?></span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 文章结果（type=all 或 type=article 时显示） -->
    <?php if ($search_type !== 'goods' && !empty($article_results)): ?>
    <?php if ($search_type === 'all'): ?>
    <div class="section-header" style="margin-bottom:12px;">
        <div class="section-title" style="font-size:15px;">文章</div>
    </div>
    <?php endif; ?>
    <div class="article-list" style="margin-bottom:32px;">
        <?php foreach ($article_results as $a): ?>
        <a href="<?= url_blog((int) $a['id']) ?>" class="card article-card">
            <div class="card-content">
                <div class="card-title"><?= htmlspecialchars($a['title']) ?></div>
                <div class="card-excerpt"><?= htmlspecialchars($a['excerpt']) ?></div>
                <div class="card-meta">
                    <span><?= htmlspecialchars($a['date']) ?></span>
                    <span><?= htmlspecialchars($a['author'] ?? '管理员') ?></span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 无结果 -->
    <?php if (empty($keyword)): ?>
    <div class="card empty-state">
        <div class="empty-icon">&#128269;</div>
        <h3>输入关键词搜索</h3>
    </div>
    <?php elseif (empty($results) && $search_type === 'goods'): ?>
    <div class="card empty-state">
        <div class="empty-icon">&#128269;</div>
        <h3>未找到相关商品</h3>
        <p>尝试使用其他关键词搜索</p>
    </div>
    <?php endif; ?>

</div>
