<?php
defined('EM_ROOT') || exit('access denied!');

// 优惠券类型文案映射（前端展示用）
$typeLabel = [
    'fixed_amount'  => '满减券',
    'percent'       => '折扣券',
    'free_shipping' => '免邮券',
];
?>
<div class="page-body">

    <div class="page-title">领券中心</div>
    <p class="coupon-intro">
        <i class="fa fa-info-circle"></i>
        领取后可在"个人中心 / 我的优惠券"查看；下单时也可直接输入券码使用
    </p>

    <?php if (empty($coupons)): ?>
    <div class="card empty-state">
        <div class="empty-icon">🎫</div>
        <h3>暂无可领优惠券</h3>
        <p>请稍后再来看看</p>
    </div>
    <?php else: ?>
    <div class="coupon-grid">
        <?php foreach ($coupons as $c): ?>
        <?php
            $id = (int) $c['id'];
            $alreadyClaimed = in_array($id, $claimed_ids, true);

            // 主值文字（按访客币种展示券面额 / 门槛；折扣券的"折"无关币种，不换算）
            if ($c['type'] === 'fixed_amount') {
                $valueBig = Currency::displayMain((float) $c['value']);
                $valueCaption = '满 ' . Currency::displayMain((float) $c['min_amount']) . ' 可用';
            } elseif ($c['type'] === 'percent') {
                $valueBig = number_format(((int) $c['value']) / 10, 1) . '折';
                $valueCaption = (float) $c['min_amount'] > 0
                    ? '满 ' . Currency::displayMain((float) $c['min_amount']) . ' 可用'
                    : '无门槛';
            } else {
                $valueBig = '免邮';
                $valueCaption = (float) $c['min_amount'] > 0
                    ? '满 ' . Currency::displayMain((float) $c['min_amount']) . ' 可用'
                    : '无门槛';
            }
        ?>
        <div class="coupon-card" data-coupon-id="<?= $id ?>" data-coupon-code="<?= htmlspecialchars((string) $c['code']) ?>">
            <div class="coupon-card__left">
                <div class="coupon-card__value"><?= htmlspecialchars($valueBig) ?></div>
                <div class="coupon-card__caption"><?= htmlspecialchars($valueCaption) ?></div>
            </div>
            <div class="coupon-card__right">
                <div class="coupon-card__title"><?= htmlspecialchars($c['title'] ?: $c['name']) ?></div>
                <?php if (!empty($c['description'])): ?>
                <div class="coupon-card__desc"><?= htmlspecialchars($c['description']) ?></div>
                <?php endif; ?>
                <div class="coupon-card__meta">
                    <span class="coupon-card__tag"><?= htmlspecialchars($typeLabel[$c['type']] ?? $c['type']) ?></span>
                    <?php if (!empty($c['end_at'])): ?>
                    <span class="coupon-card__valid">至 <?= htmlspecialchars(substr((string) $c['end_at'], 0, 16)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="coupon-card__code">
                    券码：<span class="coupon-card__code-val"><?= htmlspecialchars((string) $c['code']) ?></span>
                </div>
                <div class="coupon-card__actions">
                    <button type="button" class="coupon-btn coupon-btn-ghost js-coupon-copy">复制码</button>
                    <?php if (!$is_logged_in): ?>
                        <!-- 游客：禁用，hover 提示 -->
                        <button type="button" class="coupon-btn coupon-btn-primary is-disabled js-coupon-tip">领取</button>
                    <?php elseif ($alreadyClaimed): ?>
                        <button type="button" class="coupon-btn coupon-btn-primary is-disabled" disabled>已领取</button>
                    <?php else: ?>
                        <button type="button" class="coupon-btn coupon-btn-primary js-coupon-receive">领取</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <script>
    (function () {
        $(document).off('.emCoupon');

        // 复制券码
        $(document).on('click.emCoupon', '.js-coupon-copy', function () {
            var code = $(this).closest('.coupon-card').data('coupon-code');
            if (!code) return;
            var txt = String(code);
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(txt).then(
                    function () { layer.msg('已复制：' + txt); },
                    function () { fallbackCopy(txt); }
                );
            } else {
                fallbackCopy(txt);
            }
        });
        function fallbackCopy(txt) {
            var $i = $('<input style="position:fixed;top:-100px;">').val(txt).appendTo('body').select();
            try { document.execCommand('copy'); layer.msg('已复制：' + txt); }
            catch (e) { layer.msg('复制失败，请手动选择'); }
            $i.remove();
        }

        $(document).on('click.emCoupon', '.js-coupon-tip', function () {
            layer.msg('登录后可领取');
        });

        // 登录用户领取
        $(document).on('click.emCoupon', '.js-coupon-receive', function () {
            var $btn = $(this);
            var $card = $btn.closest('.coupon-card');
            var couponId = $card.data('coupon-id');
            $btn.prop('disabled', true).text('领取中...');
            $.post('?c=coupon&a=receive', { coupon_id: couponId }, function (res) {
                if (res.code === 200) {
                    layer.msg('领取成功');
                    $btn.removeClass('js-coupon-receive').addClass('is-disabled').prop('disabled', true).text('已领取');
                } else {
                    $btn.prop('disabled', false).text('领取');
                    layer.msg(res.msg || '领取失败');
                }
            }, 'json').fail(function () {
                $btn.prop('disabled', false).text('领取');
                layer.msg('网络异常');
            });
        });
    })();
    </script>
</div>
