<?php
/**
 * 订单附加选项展示片段（前台：个人中心 / 查单详情）。
 *
 * 依赖变量：
 *   $extraPairs  array<string, string>
 *   $layout      'uc' | 'fo'  布局风格
 */
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
if (empty($extraPairs)) {
    return;
}
$layout = $layout ?? 'uc';
$esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<?php if ($layout === 'fo'): ?>
<section class="fo-detail__block">
    <h3 class="fo-detail__block-title"><i class="fa fa-list-alt"></i> 附加选项</h3>
    <dl class="fo-detail__kv">
        <?php foreach ($extraPairs as $label => $val): ?>
        <div class="fo-detail__kv-item">
            <dt><?= $esc((string) $label) ?></dt>
            <dd><?= $esc((string) $val) ?></dd>
        </div>
        <?php endforeach; ?>
    </dl>
</section>
<?php else: ?>
<div class="uc-form-card" style="margin-bottom:16px;">
    <div class="uc-section-title"><i class="fa fa-list-alt"></i> 附加选项</div>
    <div class="uc-order-info-grid">
        <?php foreach ($extraPairs as $label => $val): ?>
        <div class="uc-order-info-item">
            <span class="uc-order-info-label"><?= $esc((string) $label) ?></span>
            <span class="uc-order-info-value"><?= $esc((string) $val) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
