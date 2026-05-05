<?php
defined('EM_ROOT') || exit('access denied!');

// 优惠券类型文案映射（前端展示用）
$typeLabel = [
    'fixed_amount'  => '满减券',
    'percent'       => '折扣券',
    'free_shipping' => '免邮券',
];
?>
<div class="page-body zs-coupon-page">

    <header class="zs-coupon-hero">
        <div class="zs-coupon-hero__badge" aria-hidden="true"><i class="fa fa-ticket"></i></div>
        <div class="zs-coupon-hero__text">
            <h1 class="zs-coupon-hero__title">领券中心</h1>
            <p class="zs-coupon-hero__desc">领取后可在「个人中心 / 我的优惠券」查看；下单时也可直接输入券码使用</p>
        </div>
    </header>

    <?php if (empty($coupons)): ?>
    <div class="zs-coupon-empty">
        <div class="zs-coupon-empty__icon" aria-hidden="true">🎫</div>
        <h3 class="zs-coupon-empty__title">暂无可领优惠券</h3>
        <p class="zs-coupon-empty__text">请稍后再来看看</p>
    </div>
    <?php else: ?>
    <div class="zs-coupon-grid">
        <?php foreach ($coupons as $c): ?>
        <?php
            $id = (int) $c['id'];
            $alreadyClaimed = in_array($id, $claimed_ids, true);

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
            $typeKey = (string) ($c['type'] ?? '');
        ?>
        <article class="coupon-card zs-coupon-card zs-coupon-card--<?= htmlspecialchars($typeKey) ?>" data-coupon-id="<?= $id ?>" data-coupon-code="<?= htmlspecialchars((string) $c['code']) ?>">
            <div class="zs-coupon-card__ribbon">
                <span class="zs-coupon-card__value"><?= htmlspecialchars($valueBig) ?></span>
                <span class="zs-coupon-card__caption"><?= htmlspecialchars($valueCaption) ?></span>
            </div>
            <div class="zs-coupon-card__main">
                <span class="zs-coupon-card__kind"><?= htmlspecialchars($typeLabel[$typeKey] ?? $typeKey) ?></span>
                <h2 class="zs-coupon-card__name"><?= htmlspecialchars($c['title'] ?: $c['name']) ?></h2>
                <?php if (!empty($c['description'])): ?>
                <p class="zs-coupon-card__desc"><?= htmlspecialchars($c['description']) ?></p>
                <?php endif; ?>
                <?php if (!empty($c['end_at'])): ?>
                <p class="zs-coupon-card__valid"><i class="fa fa-clock-o"></i> 有效期至 <?= htmlspecialchars(substr((string) $c['end_at'], 0, 16)) ?></p>
                <?php endif; ?>
                <div class="zs-coupon-card__code-row">
                    <span class="zs-coupon-card__code-label">券码</span>
                    <code class="zs-coupon-card__code"><?= htmlspecialchars((string) $c['code']) ?></code>
                </div>
                <div class="zs-coupon-card__actions">
                    <button type="button" class="coupon-btn coupon-btn-ghost zs-coupon-btn zs-coupon-btn--ghost js-coupon-copy">复制券码</button>
                    <?php if (!$is_logged_in): ?>
                    <button type="button" class="coupon-btn coupon-btn-primary is-disabled zs-coupon-btn zs-coupon-btn--primary js-coupon-tip">领取</button>
                    <?php elseif ($alreadyClaimed): ?>
                    <button type="button" class="coupon-btn coupon-btn-primary is-disabled zs-coupon-btn zs-coupon-btn--primary" disabled>已领取</button>
                    <?php else: ?>
                    <button type="button" class="coupon-btn coupon-btn-primary zs-coupon-btn zs-coupon-btn--primary js-coupon-receive">立即领取</button>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <script>
    (function () {
        $(document).off('.emCoupon');

        function zsMsg(text) {
            if (window.layui && typeof layui.msg === 'function') {
                layui.msg(text);
            } else {
                alert(text);
            }
        }

        $(document).on('click.emCoupon', '.js-coupon-copy', function () {
            var code = $(this).closest('.coupon-card').data('coupon-code');
            if (!code) return;
            var txt = String(code);
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(txt).then(
                    function () { zsMsg('已复制：' + txt); },
                    function () { fallbackCopy(txt); }
                );
            } else {
                fallbackCopy(txt);
            }
        });
        function fallbackCopy(txt) {
            var $i = $('<input style="position:fixed;top:-100px;">').val(txt).appendTo('body').select();
            try { document.execCommand('copy'); zsMsg('已复制：' + txt); }
            catch (e) { zsMsg('复制失败，请手动选择'); }
            $i.remove();
        }

        $(document).on('click.emCoupon', '.js-coupon-tip', function () {
            zsMsg('登录后可领取');
        });

        $(document).on('click.emCoupon', '.js-coupon-receive', function () {
            var $btn = $(this);
            var $card = $btn.closest('.coupon-card');
            var couponId = $card.data('coupon-id');
            $btn.prop('disabled', true).text('领取中...');
            $.post('?c=coupon&a=receive', { coupon_id: couponId }, function (res) {
                if (res.code === 200) {
                    zsMsg('领取成功');
                    $btn.removeClass('js-coupon-receive').addClass('is-disabled').prop('disabled', true).text('已领取');
                } else {
                    $btn.prop('disabled', false).text('立即领取');
                    zsMsg(res.msg || '领取失败');
                }
            }, 'json').fail(function () {
                $btn.prop('disabled', false).text('立即领取');
                zsMsg('网络异常');
            });
        });
    })();
    </script>
</div>
