<?php
defined('EM_ROOT') || exit('access denied!');

$search_type = trim($_GET['type'] ?? 'all');
if (!in_array($search_type, ['all', 'goods', 'article'])) {
    $search_type = 'all';
}
?>
<!-- 搜索结果 · SearchController::_index() · 子神布局 -->
<div class="page-body zs-search-page">

    <header class="zs-search-hero">
        <h1 class="zs-search-hero__title">搜索结果</h1>
        <p class="zs-search-hero__hint">在站内查找商品与文章</p>
    </header>

    <form class="zs-search-toolbar" method="get" data-pjax>
        <input type="hidden" name="c" value="search">
        <input type="hidden" name="type" value="<?= htmlspecialchars($search_type) ?>">
        <div class="zs-search-toolbar__inner">
            <span class="zs-search-toolbar__icon" aria-hidden="true"><i class="fa fa-search"></i></span>
            <input type="text" name="q" class="zs-search-input" placeholder="输入关键词…" value="<?= htmlspecialchars($keyword ?? '') ?>" autocomplete="off">
            <button type="submit" class="btn btn-primary zs-search-submit">搜索</button>
        </div>
    </form>

    <?php if (!empty($keyword)): ?>
    <nav class="zs-search-tabs" aria-label="结果类型">
        <a href="<?= url_search($keyword) ?>&type=all" data-pjax
           class="zs-search-tab<?= $search_type === 'all' ? ' is-active' : '' ?>">全部</a>
        <a href="<?= url_search($keyword) ?>&type=goods" data-pjax
           class="zs-search-tab<?= $search_type === 'goods' ? ' is-active' : '' ?>">商品</a>
        <a href="<?= url_search($keyword) ?>&type=article" data-pjax
           class="zs-search-tab<?= $search_type === 'article' ? ' is-active' : '' ?>">文章</a>
    </nav>
    <p class="zs-search-summary">
        找到 <strong><?= (int) ($result_count ?? 0) ?></strong> 条与「<?= htmlspecialchars($keyword) ?>」相关的结果
    </p>
    <?php endif; ?>

    <?php if ($search_type !== 'article' && !empty($results)): ?>
    <?php if ($search_type === 'all'): ?>
    <section class="zs-search-section">
        <h2 class="zs-search-section__title">商品</h2>
        <div class="zs-home-goods-grid zs-search-goods-grid">
            <?php foreach ($results as $item): ?>
            <?php $g = $item; include __DIR__ . '/partials/goods_card_item.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php else: ?>
    <div class="zs-home-goods-grid zs-search-goods-grid">
        <?php foreach ($results as $item): ?>
        <?php $g = $item; include __DIR__ . '/partials/goods_card_item.php'; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($search_type !== 'goods' && !empty($article_results)): ?>
    <?php if ($search_type === 'all'): ?>
    <section class="zs-search-section zs-search-section--articles">
        <h2 class="zs-search-section__title">文章</h2>
        <div class="zs-search-article-list">
            <?php foreach ($article_results as $a): ?>
            <a href="<?= url_blog((int) $a['id']) ?>" class="zs-search-article-card" data-pjax>
                <div class="zs-search-article-card__body">
                    <div class="zs-search-article-card__title"><?= htmlspecialchars($a['title']) ?></div>
                    <div class="zs-search-article-card__excerpt"><?= htmlspecialchars($a['excerpt']) ?></div>
                    <div class="zs-search-article-card__meta">
                        <span><i class="fa fa-calendar-o"></i> <?= htmlspecialchars($a['date']) ?></span>
                        <span><i class="fa fa-user-o"></i> <?= htmlspecialchars($a['author'] ?? '管理员') ?></span>
                    </div>
                </div>
                <span class="zs-search-article-card__arrow" aria-hidden="true"><i class="fa fa-angle-right"></i></span>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php else: ?>
    <div class="zs-search-article-list">
        <?php foreach ($article_results as $a): ?>
        <a href="<?= url_blog((int) $a['id']) ?>" class="zs-search-article-card" data-pjax>
            <div class="zs-search-article-card__body">
                <div class="zs-search-article-card__title"><?= htmlspecialchars($a['title']) ?></div>
                <div class="zs-search-article-card__excerpt"><?= htmlspecialchars($a['excerpt']) ?></div>
                <div class="zs-search-article-card__meta">
                    <span><i class="fa fa-calendar-o"></i> <?= htmlspecialchars($a['date']) ?></span>
                    <span><i class="fa fa-user-o"></i> <?= htmlspecialchars($a['author'] ?? '管理员') ?></span>
                </div>
            </div>
            <span class="zs-search-article-card__arrow" aria-hidden="true"><i class="fa fa-angle-right"></i></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (empty($keyword)): ?>
    <div class="zs-search-empty">
        <div class="zs-search-empty__icon" aria-hidden="true"><i class="fa fa-search"></i></div>
        <h3 class="zs-search-empty__title">输入关键词开始搜索</h3>
        <p class="zs-search-empty__text">支持商品名、文章标题等</p>
    </div>
    <?php elseif (empty($results) && $search_type === 'goods'): ?>
    <div class="zs-search-empty">
        <div class="zs-search-empty__icon" aria-hidden="true"><i class="fa fa-inbox"></i></div>
        <h3 class="zs-search-empty__title">未找到相关商品</h3>
        <p class="zs-search-empty__text">试试其它关键词或切换到「全部」</p>
    </div>
    <?php elseif ($search_type === 'article' && !empty($keyword) && empty($article_results)): ?>
    <div class="zs-search-empty">
        <div class="zs-search-empty__icon" aria-hidden="true"><i class="fa fa-file-text-o"></i></div>
        <h3 class="zs-search-empty__title">未找到相关文章</h3>
        <p class="zs-search-empty__text">换个说法试试，或切换到「商品」</p>
    </div>
    <?php elseif ($search_type === 'all' && !empty($keyword) && empty($results) && empty($article_results)): ?>
    <div class="zs-search-empty">
        <div class="zs-search-empty__icon" aria-hidden="true"><i class="fa fa-search"></i></div>
        <h3 class="zs-search-empty__title">暂无匹配结果</h3>
        <p class="zs-search-empty__text">未找到相关商品与文章，可缩短关键词或换词重试</p>
    </div>
    <?php endif; ?>

</div>
