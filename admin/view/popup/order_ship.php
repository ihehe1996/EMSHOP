<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$esc = function (?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
};
// 提交 URL 由调用方决定：admin 走 /admin/order.php；商户走 /user/merchant/order.php
$shipSubmitUrl = isset($shipSubmitUrl) && $shipSubmitUrl !== '' ? $shipSubmitUrl : '/admin/order.php';
include __DIR__ . '/header.php';
?>

<style>
.popup-body { background: #f5f7fa; }
.popup-body .popup-inner { padding: 20px; }

.ship-err {
    background: #fff; border: 1px solid #fee2e2; border-radius: 10px;
    padding: 22px 26px; color: #991b1b; font-size: 13.5px; line-height: 1.7;
    text-align: center;
}
.ship-err i { color: #ef4444; display: block; font-size: 32px; margin-bottom: 10px; }

.ship-empty {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
    padding: 40px 24px; text-align: center; color: #6b7280; font-size: 13px;
}
.ship-empty i { color: #10b981; display: block; font-size: 32px; margin-bottom: 10px; }

.ship-card {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
    padding: 18px 22px; margin-bottom: 14px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.ship-card:last-child { margin-bottom: 0; }
.ship-card__head { display: flex; align-items: center; gap: 12px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; margin-bottom: 14px; }
.ship-card__cover { width: 44px; height: 44px; border-radius: 6px; object-fit: cover; background: #f1f5f9; flex: 0 0 44px; }
.ship-card__info { flex: 1; min-width: 0; }
.ship-card__title { font-size: 13.5px; font-weight: 600; color: #0f172a; line-height: 1.4; margin-bottom: 3px; }
.ship-card__meta { font-size: 12px; color: #94a3b8; }
.ship-card__meta .em-tag { margin-left: 6px; font-size: 11px; padding: 0 6px; }
.ship-card__actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 12px; padding-top: 10px; border-top: 1px solid #f1f5f9; }
.ship-card__submit {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 16px; border: 0; border-radius: 6px;
    background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff;
    font-size: 13px; cursor: pointer; transition: all 0.15s;
}
.ship-card__submit:hover { filter: brightness(1.05); }
.ship-card__submit:disabled { background: #c7d2fe; cursor: not-allowed; }

.ship-plugin-missing {
    padding: 12px 16px; background: #fef3c7; border: 1px solid #fde68a; border-radius: 6px;
    color: #92400e; font-size: 12.5px; line-height: 1.7;
}
</style>

<div class="popup-inner">
    <?php if (!empty($shipError)): ?>
        <div class="ship-err">
            <i class="fa fa-exclamation-triangle"></i>
            <?= $esc($shipError) ?>
        </div>
    <?php elseif (empty($shippableGoods)): ?>
        <div class="ship-empty">
            <i class="fa fa-check-circle"></i>
            订单内所有商品均已发货
        </div>
    <?php else: ?>
        <?php foreach ($shippableGoods as $og):
            $goodsType = (string) ($og['goods_type'] ?? '');
            $typeLabel = applyFilter('goods_type_label', $goodsType, $og);
            // 插件提供的发货表单 HTML：约定接收 (html, order_goods_row)，返回完整 form 内部 HTML（不含 <form> 标签）
            // 插件若未提供表单 → 返回空串，核心提示"该类型暂未支持手动发货"
            $formHtml = (string) applyFilter("goods_type_{$goodsType}_manual_delivery_form", '', $og);
        ?>
        <div class="ship-card" data-og-id="<?= (int) $og['id'] ?>">
            <div class="ship-card__head">
                <?php if (!empty($og['cover_image'])): ?>
                <img class="ship-card__cover" src="<?= $esc((string) $og['cover_image']) ?>" alt="" onerror="this.style.visibility='hidden'">
                <?php endif; ?>
                <div class="ship-card__info">
                    <div class="ship-card__title"><?= $esc((string) $og['goods_title']) ?></div>
                    <div class="ship-card__meta">
                        <?php if (!empty($og['spec_name'])): ?><?= $esc((string) $og['spec_name']) ?> · <?php endif; ?>
                        × <?= (int) $og['quantity'] ?>
                        <?php if ($typeLabel !== ''): ?>
                            <span class="em-tag em-tag--muted"><?= $esc((string) $typeLabel) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <form class="ship-form" data-og-id="<?= (int) $og['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
                <input type="hidden" name="_action" value="ship">
                <input type="hidden" name="order_goods_id" value="<?= (int) $og['id'] ?>">
                <?php if ($formHtml !== ''): ?>
                    <?= $formHtml /* 插件自己 escape + 命名字段，提交时通过 FormData 收走 */ ?>
                    <div class="ship-card__actions">
                        <button type="submit" class="ship-card__submit">
                            <i class="fa fa-paper-plane"></i>确认发货
                        </button>
                    </div>
                <?php else: ?>
                    <div class="ship-plugin-missing">
                        <i class="fa fa-info-circle"></i>
                        商品类型「<?= $esc($goodsType) ?>」插件未提供手动发货表单，无法发货
                    </div>
                <?php endif; ?>
            </form>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
$(function () {
    $('.ship-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var origHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> 发货中...');

        $.ajax({
            url: <?= json_encode($shipSubmitUrl) ?>,
            type: 'POST',
            dataType: 'json',
            data: $form.serialize(),
            success: function (res) {
                if (res.code === 200) {
                    layer.msg(res.msg || '发货成功');
                    // 通知父窗口刷新订单列表
                    if (window.parent && window.parent.layer) {
                        window._orderShipSuccess = true;
                    }
                    // 整张卡片渐隐移除；全部发完就关闭窗口
                    $form.closest('.ship-card').slideUp(200, function () {
                        $(this).remove();
                        if ($('.ship-card').length === 0) {
                            $('.popup-inner').html('<div class="ship-empty"><i class="fa fa-check-circle"></i>订单内所有商品均已发货</div>');
                            setTimeout(function () {
                                if (window.parent && window.parent.layer) {
                                    var idx = window.parent.layer.getFrameIndex(window.name);
                                    if (idx) window.parent.layer.close(idx);
                                }
                            }, 800);
                        }
                    });
                } else {
                    layer.msg(res.msg || '发货失败');
                    $btn.prop('disabled', false).html(origHtml);
                }
            },
            error: function () {
                layer.msg('网络异常');
                $btn.prop('disabled', false).html(origHtml);
            }
        });
    });
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
