<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

/**
 * 订单「使用教程」区块（购买后可见，富文本 HTML），放在商品明细上方。
 *
 * @var array<int, array<string, mixed>> $orderGoods 订单商品列表（含 goods_guide）
 * @var string                          $layout      uc | fo
 */
$orderGoods = $orderGoods ?? [];
$itemsWithGuide = [];
foreach ($orderGoods as $og) {
    $guideHtml = trim((string) ($og['goods_guide'] ?? ''));
    if ($guideHtml === '') {
        continue;
    }
    $itemsWithGuide[] = [
        'title' => trim((string) ($og['goods_title'] ?? '')),
        'guide' => $guideHtml,
    ];
}
if ($itemsWithGuide === []) {
    return;
}

$layout = (string) ($layout ?? 'uc');
$showTitles = count($orderGoods) > 1;
?>

<?php if ($layout === 'fo'): ?>
<section class="fo-detail__block fo-detail__block--guide">
    <h3 class="fo-detail__block-title"><i class="fa fa-book"></i> 使用教程</h3>
    <?php foreach ($itemsWithGuide as $item): ?>
    <div class="fo-detail__guide">
        <?php if ($showTitles && $item['title'] !== ''): ?>
        <div class="fo-detail__guide-goods"><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <div class="fo-detail__guide-body detail-body"><?= $item['guide'] ?></div>
    </div>
    <?php endforeach; ?>
</section>
<?php else: ?>
<div class="uc-form-card uc-order-guide-section" style="margin-bottom:16px;">
    <div class="uc-section-title"><i class="fa fa-book"></i> 使用教程</div>
    <?php foreach ($itemsWithGuide as $item): ?>
    <div class="uc-order-guide">
        <?php if ($showTitles && $item['title'] !== ''): ?>
        <div class="uc-order-guide-goods"><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <div class="uc-order-guide-body detail-body"><?= $item['guide'] ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
