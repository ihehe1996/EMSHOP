<?php
defined('EM_ROOT') || exit('access denied!');
?>
<!-- 商城首页 · GoodsController::_index() -->
<div class="page-body zs-home-body">

<?php include __DIR__ . '/hero.php'; ?>

<?php
// 店铺公告 —— 当前 scope 已设公告且勾选了"商城首页"展示位置时输出
$_announce = $announcement ?? null;
if (is_array($_announce) && !empty($_announce['html']) && in_array('home', $_announce['positions'] ?? [], true)):
?>
    <div class="site-announcement">
        <div class="site-announcement__head">
            <span class="site-announcement__icon"><i class="fa fa-bullhorn"></i></span>
            <span class="site-announcement__title">店铺公告</span>
            <span class="site-announcement__title-sep"></span>
        </div>
        <div class="site-announcement__body"><?= $_announce['html'] ?></div>
    </div>
<?php endif; ?>

    <div class="wrapper zs-home-shell">
        <div class="zs-home-main">
            <section class="zs-home-panel zs-home-panel--goods">
                <header class="zs-home-panel-head">
                    <div class="zs-home-panel-title-wrap">
                        <div class="zs-home-panel-kicker">PICKS</div>
                        <h2 class="zs-home-panel-title">推荐商品</h2>
                    </div>
                    <a href="<?= url_goods_list() ?>" data-pjax class="zs-home-panel-link">查看全部</a>
                </header>
                <?php if (!empty($recommended_goods)): ?>
                <div class="zs-home-goods-grid">
                    <?php foreach ($recommended_goods as $g): ?>
                    <article class="zs-home-goods-item">
                        <a <?= goods_card_href_attrs($g) ?> class="zs-home-goods-cover">
                            <?php if (trim((string) ($g['image'] ?? '')) !== ''): ?>
                            <img src="<?= htmlspecialchars($g['image']) ?>" alt="<?= htmlspecialchars($g['name']) ?>">
                            <?php else: ?>
                            <span class="zs-home-goods-cover-empty" aria-hidden="true"></span>
                            <?php endif; ?>
                            <?php if (($g['delivery_type'] ?? '') === 'auto'): ?>
                            <span class="zs-home-badge zs-home-badge--auto">自动</span>
                            <?php elseif (($g['delivery_type'] ?? '') === 'manual'): ?>
                            <span class="zs-home-badge zs-home-badge--manual">人工</span>
                            <?php endif; ?>
                        </a>
                        <a <?= goods_card_href_attrs($g) ?> class="zs-home-goods-name"><?= htmlspecialchars($g['name']) ?></a>
                        <div class="zs-home-goods-meta">
                            <span>库存 <?= htmlspecialchars((string) ($g['stock_text'] ?? '0')) ?></span>
                            <span>销量 <?= (int) ($g['sold'] ?? 0) ?></span>
                        </div>
                        <div class="zs-home-goods-price">
                            <span class="zs-home-price-main"><?= Currency::displayMain((float) $g['price']) ?></span>
                            <?php if (!empty($g['original_price'])): ?>
                            <span class="zs-home-price-old"><?= Currency::displayMain((float) $g['original_price']) ?></span>
                            <?php endif; ?>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="zs-home-empty">
                    <i class="fa fa-shopping-bag"></i>
                    <p>商品正在准备中，稍后再来看看。</p>
                </div>
                <?php endif; ?>
            </section>

            <section class="zs-home-panel zs-home-panel--article">
                <header class="zs-home-panel-head">
                    <div class="zs-home-panel-title-wrap">
                        <div class="zs-home-panel-kicker">NEWS</div>
                        <h2 class="zs-home-panel-title">最新文章</h2>
                    </div>
                    <a href="<?= $nav_blog_url ?? '?c=blog_list' ?>" data-pjax class="zs-home-panel-link">查看全部</a>
                </header>
                <?php if (!empty($recent_articles)): ?>
                <div class="zs-home-article-list">
                    <?php foreach ($recent_articles as $a): ?>
                    <a href="<?= url_blog((int) $a['id']) ?>" class="zs-home-article-item">
                        <?php if (!empty($a['image'])): ?>
                        <span class="zs-home-article-thumb"><img src="<?= htmlspecialchars($a['image']) ?>" alt="<?= htmlspecialchars($a['title']) ?>"></span>
                        <?php else: ?>
                        <span class="zs-home-article-thumb zs-home-article-thumb--empty"></span>
                        <?php endif; ?>
                        <span class="zs-home-article-body">
                            <span class="zs-home-article-title"><?= htmlspecialchars($a['title']) ?></span>
                            <span class="zs-home-article-excerpt"><?= htmlspecialchars(truncate($a['excerpt'], 60)) ?></span>
                            <span class="zs-home-article-meta"><?= htmlspecialchars($a['date']) ?> · <?= (int) $a['views'] ?> 阅读</span>
                        </span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="zs-home-empty">
                    <i class="fa fa-pencil-square-o"></i>
                    <p>还没有文章，后续将持续更新内容。</p>
                </div>
                <?php endif; ?>
            </section>
        </div>

        <aside class="zs-home-aside">
            <?php include __DIR__ . '/goods_side.php'; ?>
        </aside>
    </div>
</div>
