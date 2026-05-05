<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

$typeLabel = [
    'fixed_amount'  => '满减券',
    'percent'       => '折扣券',
    'free_shipping' => '免邮券',
];

// 视觉主题：不同 tab 的券卡片色调
$viewTheme = [
    'unused'  => ['grad' => 'linear-gradient(135deg, #4e6ef2, #2b7de9)', 'tag' => '#eef2ff', 'tagColor' => '#4e6ef2'],
    'used'    => ['grad' => 'linear-gradient(135deg, #cfd8e3, #a0aec0)', 'tag' => '#f5f5f5', 'tagColor' => '#888'],
    'expired' => ['grad' => 'linear-gradient(135deg, #cfd8e3, #a0aec0)', 'tag' => '#f5f5f5', 'tagColor' => '#888'],
    'invalid' => ['grad' => 'linear-gradient(135deg, #cfd8e3, #a0aec0)', 'tag' => '#f5f5f5', 'tagColor' => '#888'],
];
$theme = $viewTheme[$view] ?? $viewTheme['unused'];
?>
<div class="uc-page">
    <div class="uc-page-header">
        <h2 class="uc-page-title">我的优惠券</h2>
        <p class="uc-page-desc">查看您领取的优惠券</p>
    </div>

    <!-- 状态 tab -->
    <div class="uc-coupon-tabs">
        <?php foreach ($viewTabs as $key => $label): ?>
        <a href="/user/coupon.php?view=<?= $key ?>" data-pjax="#userContent"
           class="uc-coupon-tab<?= $view === $key ? ' is-active' : '' ?>">
            <?= $label ?>
            <?php if (($counts[$key] ?? 0) > 0): ?>
            <span class="uc-coupon-tab-count"><?= (int) $counts[$key] ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($coupons)): ?>
    <div class="uc-empty">
        <i class="fa fa-inbox"></i>
        <p>暂无<?= $viewTabs[$view] ?>的优惠券</p>
        <a href="/?c=coupon" class="uc-btn uc-btn--primary" style="margin-top:16px;">去领券中心</a>
    </div>
    <?php else: ?>
    <div class="uc-coupon-grid">
        <?php foreach ($coupons as $c): ?>
        <?php
            // 展示文字 —— 金额按访客当前展示币种换算，折扣券的"折"和币种无关
            if ($c['type'] === 'fixed_amount') {
                $valueBig = Currency::displayMain((float) $c['value']);
                $caption = (float) $c['min_amount'] > 0 ? '满 ' . Currency::displayMain((float) $c['min_amount']) . ' 可用' : '无门槛';
            } elseif ($c['type'] === 'percent') {
                $valueBig = number_format(((int) $c['value']) / 10, 1) . '折';
                $caption = (float) $c['min_amount'] > 0 ? '满 ' . Currency::displayMain((float) $c['min_amount']) . ' 可用' : '无门槛';
            } else {
                $valueBig = '免邮';
                $caption = (float) $c['min_amount'] > 0 ? '满 ' . Currency::displayMain((float) $c['min_amount']) . ' 可用' : '无门槛';
            }
        ?>
        <div class="uc-coupon-card uc-coupon-card--<?= $view ?>">
            <div class="uc-coupon-card__left" style="background:<?= $theme['grad'] ?>;">
                <div class="uc-coupon-card__value"><?= htmlspecialchars($valueBig) ?></div>
                <div class="uc-coupon-card__caption"><?= htmlspecialchars($caption) ?></div>
            </div>
            <div class="uc-coupon-card__right">
                <div class="uc-coupon-card__title"><?= htmlspecialchars($c['title'] ?: $c['name']) ?></div>
                <?php if (!empty($c['description'])): ?>
                <div class="uc-coupon-card__desc"><?= htmlspecialchars($c['description']) ?></div>
                <?php endif; ?>
                <div class="uc-coupon-card__meta">
                    <span class="uc-coupon-card__tag" style="background:<?= $theme['tag'] ?>;color:<?= $theme['tagColor'] ?>;">
                        <?= htmlspecialchars($typeLabel[$c['type']] ?? $c['type']) ?>
                    </span>
                    <?php if (!empty($c['end_at'])): ?>
                    <span>至 <?= htmlspecialchars(substr((string) $c['end_at'], 0, 16)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="uc-coupon-card__code">券码：<span><?= htmlspecialchars((string) $c['code']) ?></span></div>
                <?php if ($view === 'used' && !empty($c['order_id'])): ?>
                <div class="uc-coupon-card__used-at">
                    使用于 <?= htmlspecialchars((string) substr((string) $c['used_at'], 0, 16)) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
