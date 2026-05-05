<?php
defined('EM_ROOT') || exit('access denied!');
/**
 * 子神统一商品卡片（与商城首页推荐区同一套 DOM，共用 style.css 中 .zs-home-goods-* 样式）。
 * 调用方需在循环内提供变量 $g：BaseController::queryGoodsList* 返回的单条结构。
 */
if (!isset($g) || !is_array($g)) {
    return;
}
?>
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
