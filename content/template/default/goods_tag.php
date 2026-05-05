<?php
defined('EM_ROOT') || exit('access denied!');
?>
<!-- 商品标签页 · GoodsTagController::_detail() -->
<div class="page-body">

    <!-- 面包屑 -->
    <div class="breadcrumb">
        <a href="<?= url_home() ?>" data-pjax>首页</a>
        <span class="sep">/</span>
        <a href="<?= url_goods_list() ?>" data-pjax>商品列表</a>
        <span class="sep">/</span>
        <?php if (!empty($tag)): ?>
        标签：<?= htmlspecialchars($tag['name']) ?>
        <?php else: ?>
        标签
        <?php endif; ?>
    </div>

    <?php if (!empty($tag)): ?>

    <!-- 标签头部 -->
    <div class="tag-page-header">
        <div class="tag-page-icon"><i class="fa fa-tag"></i></div>
        <div class="tag-page-info">
            <h1 class="tag-page-name"><?= htmlspecialchars($tag['name']) ?></h1>
            <div class="tag-page-count">共 <?= (int) $tag['goods_count'] ?> 件商品</div>
        </div>
    </div>

    <!-- 相关标签 -->
    <?php if (!empty($all_tags)): ?>
    <div class="tag-page-related">
        <?php foreach ($all_tags as $t): ?>
        <a href="<?= url_goods_tag((int) $t['id']) ?>"
           class="tag-page-item<?= (int) $t['id'] === (int) $tag['id'] ? ' active' : '' ?>"
           data-pjax><?= htmlspecialchars($t['name']) ?>
            <span class="tag-page-item-count"><?= (int) $t['goods_count'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

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
    <?php $pg = $pagination; ?>
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
    <?php endif; ?>

    <?php else: ?>
    <div class="card empty-state">
        <div class="empty-icon">&#128722;</div>
        <h3>暂无商品</h3>
        <p>该标签下还没有商品</p>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- 标签不存在 -->
    <div class="tag-page-header" style="margin-bottom:24px;">
        <div class="tag-page-icon"><i class="fa fa-tags"></i></div>
        <div class="tag-page-info">
            <h1 class="tag-page-name">所有标签</h1>
            <div class="tag-page-count">标签不存在或已删除</div>
        </div>
    </div>
    <?php if (!empty($all_tags)): ?>
    <div class="tag-page-related">
        <?php foreach ($all_tags as $t): ?>
        <a href="<?= url_goods_tag((int) $t['id']) ?>" class="tag-page-item" data-pjax>
            <?= htmlspecialchars($t['name']) ?>
            <span class="tag-page-item-count"><?= (int) $t['goods_count'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div>
