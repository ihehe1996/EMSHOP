<?php
defined('EM_ROOT') || exit('access denied!');

$current_category = (int) ($_GET['category_id'] ?? 0);
$current_tag = (int) ($_GET['tag_id'] ?? 0);
?>
<!-- 文章列表 · BlogController::_list() -->
<div class="page-body">

    <!-- 面包屑 -->
    <div class="breadcrumb">
        <a href="<?= url_home() ?>" data-pjax>首页</a>
        <span class="sep">/</span>
        文章列表
    </div>

    <!-- 分类筛选 -->
    <?php if (!empty($blog_categories)): ?>
    <?php
    // 判断当前分类是否属于某个父级（用于高亮父级 + 展开子级）
    $activeParentId = 0;
    foreach ($blog_categories as $_cat) {
        if ((int) $_cat['id'] === $current_category) { $activeParentId = (int) $_cat['id']; break; }
        foreach ($_cat['children'] ?? [] as $_child) {
            if ((int) $_child['id'] === $current_category) { $activeParentId = (int) $_cat['id']; break 2; }
        }
    }
    // 计算全部文章数
    $allArticleCount = 0;
    foreach ($blog_categories as $_c) {
        $allArticleCount += (int) $_c['article_count'];
        foreach ($_c['children'] ?? [] as $_ch) {
            $allArticleCount += (int) $_ch['article_count'];
        }
    }
    ?>
    <div class="category-tabs">
        <a href="<?= url_blog_list() ?>"
           class="category-tab<?= $current_category === 0 ? ' active' : '' ?>"
           data-pjax>
            <span class="category-tab-name">全部</span>
            <span class="category-tab-count"><?= $allArticleCount ?></span>
        </a>
        <?php foreach ($blog_categories as $cat): ?>
        <?php
        $catCount = (int) $cat['article_count'];
        foreach ($cat['children'] ?? [] as $_ch) { $catCount += (int) $_ch['article_count']; }
        ?>
        <a href="<?= url_blog_list(['category_id' => $cat['id']]) ?>"
           class="category-tab<?= (int) $cat['id'] === $current_category || ((int) $cat['id'] === $activeParentId && (int) $cat['id'] !== $current_category) ? ' active' : '' ?>"
           data-pjax>
            <?php if (!empty($cat['icon'])): ?>
            <img class="category-tab-icon" src="<?= htmlspecialchars($cat['icon']) ?>" alt="">
            <?php endif; ?>
            <span class="category-tab-name"><?= htmlspecialchars($cat['name']) ?></span>
            <span class="category-tab-count"><?= $catCount ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php if ($activeParentId > 0): ?>
    <?php
    $activeChildren = [];
    foreach ($blog_categories as $_cat) {
        if ((int) $_cat['id'] === $activeParentId) { $activeChildren = $_cat['children'] ?? []; break; }
    }
    ?>
    <?php if (!empty($activeChildren)): ?>
    <div class="category-tabs category-tabs--sub">
        <a href="<?= url_blog_list(['category_id' => $activeParentId]) ?>"
           class="category-tab<?= $current_category === $activeParentId ? ' active' : '' ?>"
           data-pjax>
            <span class="category-tab-name">全部</span>
        </a>
        <?php foreach ($activeChildren as $child): ?>
        <a href="<?= url_blog_list(['category_id' => $child['id']]) ?>"
           class="category-tab<?= (int) $child['id'] === $current_category ? ' active' : '' ?>"
           data-pjax>
            <span class="category-tab-name"><?= htmlspecialchars($child['name']) ?></span>
            <span class="category-tab-count"><?= (int) $child['article_count'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>

    <!-- 文章网格（每行3篇） -->
    <?php if (!empty($article_list)): ?>
    <div class="blog-article-grid blog-article-grid--3col">
        <?php foreach ($article_list as $a): ?>
        <a href="<?= url_blog((int) $a['id']) ?>" class="card blog-article-card" data-pjax>
            <div class="blog-article-img">
                <?php if (!empty($a['image'])): ?>
                <img src="<?= htmlspecialchars($a['image']) ?>" alt="<?= htmlspecialchars($a['title']) ?>">
                <?php endif; ?>
            </div>
            <div class="blog-article-body">
                <div class="blog-article-title"><?= htmlspecialchars($a['title']) ?></div>
                <div class="blog-article-excerpt"><?= htmlspecialchars($a['excerpt']) ?></div>
                <?php if (!empty($a['tags'])): ?>
                <div class="blog-article-tags">
                    <?php foreach ($a['tags'] as $tag): ?>
                    <span class="article-tag-label"><?= htmlspecialchars($tag['name']) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="blog-article-meta">
                    <span><i class="fa fa-calendar-o"></i> <?= htmlspecialchars($a['date']) ?></span>
                    <span><i class="fa fa-folder-o"></i> <?= htmlspecialchars($a['category'] ?? '未分类') ?></span>
                    <span><i class="fa fa-eye"></i> <?= (int) ($a['views'] ?? 0) ?></span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- 分页 -->
    <?php if (!empty($pagination) && $pagination['total_pages'] > 1): ?>
    <?php
    $pg = $pagination;
    $pgParams = [];
    if ($current_category > 0) $pgParams['category_id'] = $current_category;
    if ($current_tag > 0) $pgParams['tag_id'] = $current_tag;
    ?>
    <div class="pagination">
        <?php if ($pg['page'] > 1): ?>
        <a href="<?= url_blog_list(array_merge($pgParams, ['page' => $pg['page'] - 1])) ?>" class="pagination-btn" data-pjax><i class="fa fa-chevron-left"></i></a>
        <?php else: ?>
        <span class="pagination-btn disabled"><i class="fa fa-chevron-left"></i></span>
        <?php endif; ?>

        <?php
        $start = max(1, $pg['page'] - 2);
        $end = min($pg['total_pages'], $start + 4);
        $start = max(1, $end - 4);
        ?>
        <?php if ($start > 1): ?>
        <a href="<?= url_blog_list(array_merge($pgParams, ['page' => 1])) ?>" class="pagination-num" data-pjax>1</a>
        <?php if ($start > 2): ?><span class="pagination-dots">...</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
        <?php if ($i === $pg['page']): ?>
        <span class="pagination-num active"><?= $i ?></span>
        <?php else: ?>
        <a href="<?= url_blog_list(array_merge($pgParams, ['page' => $i])) ?>" class="pagination-num" data-pjax><?= $i ?></a>
        <?php endif; ?>
        <?php endfor; ?>

        <?php if ($end < $pg['total_pages']): ?>
        <?php if ($end < $pg['total_pages'] - 1): ?><span class="pagination-dots">...</span><?php endif; ?>
        <a href="<?= url_blog_list(array_merge($pgParams, ['page' => $pg['total_pages']])) ?>" class="pagination-num" data-pjax><?= $pg['total_pages'] ?></a>
        <?php endif; ?>

        <?php if ($pg['page'] < $pg['total_pages']): ?>
        <a href="<?= url_blog_list(array_merge($pgParams, ['page' => $pg['page'] + 1])) ?>" class="pagination-btn" data-pjax><i class="fa fa-chevron-right"></i></a>
        <?php else: ?>
        <span class="pagination-btn disabled"><i class="fa fa-chevron-right"></i></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="card empty-state">
        <div class="empty-icon">&#128240;</div>
        <h3>暂无文章</h3>
        <p>还没有发布任何文章</p>
    </div>
    <?php endif; ?>

</div>
