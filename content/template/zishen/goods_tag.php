<?php
defined('EM_ROOT') || exit('access denied!');
?>
<!-- 商品标签页 · GoodsTagController::_detail() · 子神布局 -->
<div class="page-body zs-goods-tag-page">

    <nav class="breadcrumb" aria-label="面包屑">
        <a href="<?= url_home() ?>" data-pjax>首页</a>
        <span class="sep">/</span>
        <a href="<?= url_goods_list() ?>" data-pjax>商品列表</a>
        <span class="sep">/</span>
        <?php if (!empty($tag)): ?>
        <span><?= htmlspecialchars($tag['name']) ?></span>
        <?php else: ?>
        <span>标签</span>
        <?php endif; ?>
    </nav>

    <?php if (!empty($tag)): ?>

    <header class="zs-gtag-hero">
        <div class="zs-gtag-hero__icon" aria-hidden="true"><i class="fa fa-tag"></i></div>
        <div class="zs-gtag-hero__body">
            <p class="zs-gtag-hero__kicker">商品标签</p>
            <h1 class="zs-gtag-hero__title"><?= htmlspecialchars($tag['name']) ?></h1>
            <p class="zs-gtag-hero__meta">共 <strong><?= (int) ($tag['goods_count'] ?? 0) ?></strong> 件商品</p>
        </div>
    </header>

    <?php if (!empty($all_tags)): ?>
    <nav class="zs-gtag-chips" aria-label="相关标签">
        <?php foreach ($all_tags as $t): ?>
        <a href="<?= url_goods_tag((int) $t['id']) ?>"
           class="zs-gtag-chip<?= (int) $t['id'] === (int) $tag['id'] ? ' is-active' : '' ?>"
           data-pjax>
            <span class="zs-gtag-chip__name"><?= htmlspecialchars($t['name']) ?></span>
            <span class="zs-gtag-chip__count"><?= (int) ($t['goods_count'] ?? 0) ?></span>
        </a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <?php if (!empty($goods_list)): ?>
    <div class="goods-grid zs-gtag-grid">
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

    <?php if (!empty($pagination) && $pagination['total_pages'] > 1): ?>
    <?php $pg = $pagination; ?>
    <div class="zs-gtag-pagination">
        <div class="pagination">
            <?php if ($pg['page'] > 1): ?>
            <a href="<?= url_goods_tag((int) $tag['id'], ['page' => $pg['page'] - 1]) ?>" class="pagination-btn" data-pjax><i class="fa fa-chevron-left"></i></a>
            <?php else: ?>
            <span class="pagination-btn disabled"><i class="fa fa-chevron-left"></i></span>
            <?php endif; ?>

            <?php
            $start = max(1, $pg['page'] - 2);
            $end = min($pg['total_pages'], $start + 4);
            $start = max(1, $end - 4);
            ?>
            <?php if ($start > 1): ?>
            <a href="<?= url_goods_tag((int) $tag['id'], ['page' => 1]) ?>" class="pagination-num" data-pjax>1</a>
            <?php if ($start > 2): ?><span class="pagination-dots">...</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start; $i <= $end; $i++): ?>
            <?php if ($i === $pg['page']): ?>
            <span class="pagination-num active"><?= $i ?></span>
            <?php else: ?>
            <a href="<?= url_goods_tag((int) $tag['id'], ['page' => $i]) ?>" class="pagination-num" data-pjax><?= $i ?></a>
            <?php endif; ?>
            <?php endfor; ?>

            <?php if ($end < $pg['total_pages']): ?>
            <?php if ($end < $pg['total_pages'] - 1): ?><span class="pagination-dots">...</span><?php endif; ?>
            <a href="<?= url_goods_tag((int) $tag['id'], ['page' => $pg['total_pages']]) ?>" class="pagination-num" data-pjax><?= $pg['total_pages'] ?></a>
            <?php endif; ?>

            <?php if ($pg['page'] < $pg['total_pages']): ?>
            <a href="<?= url_goods_tag((int) $tag['id'], ['page' => $pg['page'] + 1]) ?>" class="pagination-btn" data-pjax><i class="fa fa-chevron-right"></i></a>
            <?php else: ?>
            <span class="pagination-btn disabled"><i class="fa fa-chevron-right"></i></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="zs-gtag-empty">
        <div class="zs-gtag-empty__icon" aria-hidden="true"><i class="fa fa-inbox"></i></div>
        <h2 class="zs-gtag-empty__title">该标签下暂无商品</h2>
        <p class="zs-gtag-empty__text">试试其它标签，或返回商品列表浏览全部</p>
        <a href="<?= url_goods_list() ?>" data-pjax class="btn btn-primary zs-gtag-empty__btn">去商品列表</a>
    </div>
    <?php endif; ?>

    <?php else: ?>

    <div class="zs-gtag-missing">
        <div class="zs-gtag-missing__icon" aria-hidden="true"><i class="fa fa-tags"></i></div>
        <h1 class="zs-gtag-missing__title">标签不存在</h1>
        <p class="zs-gtag-missing__text">该标签可能已删除或链接有误，请从下方选择其它标签。</p>
    </div>

    <?php if (!empty($all_tags)): ?>
    <nav class="zs-gtag-chips zs-gtag-chips--solo" aria-label="全部商品标签">
        <?php foreach ($all_tags as $t): ?>
        <a href="<?= url_goods_tag((int) $t['id']) ?>" class="zs-gtag-chip" data-pjax>
            <span class="zs-gtag-chip__name"><?= htmlspecialchars($t['name']) ?></span>
            <span class="zs-gtag-chip__count"><?= (int) ($t['goods_count'] ?? 0) ?></span>
        </a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <?php endif; ?>

</div>
