<?php
defined('EM_ROOT') || exit('access denied!');
?>
<!-- 博客首页 · BlogController::_index() -->

<!-- Hero 轮播（博客场景） -->
<?php $_hero_scene = 'blog'; include __DIR__ . '/hero.php'; ?>

<div class="page-body">
    <div class="blog-layout">

        <!-- 主内容 -->
        <div class="blog-main">

            <!-- 文章网格（每行2篇） -->
            <?php if (!empty($article_list)): ?>
            <div class="blog-article-grid">
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
            <?php $pg = $pagination; ?>
            <div class="pagination">
                <?php if ($pg['page'] > 1): ?>
                <a href="<?= url_blog_index(['page' => $pg['page'] - 1]) ?>" class="pagination-btn" data-pjax><i class="fa fa-chevron-left"></i></a>
                <?php else: ?>
                <span class="pagination-btn disabled"><i class="fa fa-chevron-left"></i></span>
                <?php endif; ?>

                <?php
                $start = max(1, $pg['page'] - 2);
                $end = min($pg['total_pages'], $start + 4);
                $start = max(1, $end - 4);
                ?>
                <?php if ($start > 1): ?>
                <a href="<?= url_blog_index(['page' => 1]) ?>" class="pagination-num" data-pjax>1</a>
                <?php if ($start > 2): ?><span class="pagination-dots">...</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                <?php if ($i === $pg['page']): ?>
                <span class="pagination-num active"><?= $i ?></span>
                <?php else: ?>
                <a href="<?= url_blog_index(['page' => $i]) ?>" class="pagination-num" data-pjax><?= $i ?></a>
                <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $pg['total_pages']): ?>
                <?php if ($end < $pg['total_pages'] - 1): ?><span class="pagination-dots">...</span><?php endif; ?>
                <a href="<?= url_blog_index(['page' => $pg['total_pages']]) ?>" class="pagination-num" data-pjax><?= $pg['total_pages'] ?></a>
                <?php endif; ?>

                <?php if ($pg['page'] < $pg['total_pages']): ?>
                <a href="<?= url_blog_index(['page' => $pg['page'] + 1]) ?>" class="pagination-btn" data-pjax><i class="fa fa-chevron-right"></i></a>
                <?php else: ?>
                <span class="pagination-btn disabled"><i class="fa fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="card empty-state">
                <div class="empty-icon">&#128196;</div>
                <h3>暂无文章</h3>
                <p>还没有发布任何文章</p>
            </div>
            <?php endif; ?>

        </div>

        <!-- 侧边栏 -->
        <?php include __DIR__ . '/blog_side.php'; ?>

    </div>
</div>
