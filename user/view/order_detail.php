<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
?>
<div class="uc-page">
    <?php if (!empty($order)): ?>
    <?php
    $isPaid = !in_array($order['status'], ['pending', 'expired', 'cancelled', 'failed']);
    $statusName = OrderModel::statusName($order['status']);
    // 订单金额用下单时锁定的货币+汇率快照展示，历史不会因当前汇率变动而漂移
    $orderDispCode = (string) ($order['display_currency_code'] ?? '');
    $orderDispRate = (int) ($order['display_rate'] ?? 0);
    ?>

    <!-- 状态卡片 -->
    <div class="uc-order-status-card <?= $isPaid ? 'uc-order-status--success' : '' ?>">
        <div class="uc-order-status-icon">
            <?php if ($order['status'] === 'completed'): ?>
            <i class="fa fa-check-circle"></i>
            <?php elseif ($isPaid): ?>
            <i class="fa fa-truck"></i>
            <?php elseif ($order['status'] === 'pending'): ?>
            <i class="fa fa-clock-o"></i>
            <?php else: ?>
            <i class="fa fa-times-circle"></i>
            <?php endif; ?>
        </div>
        <div class="uc-order-status-text">
            <div class="uc-order-status-title"><?= $statusName ?></div>
            <div class="uc-order-status-desc">
                <?php if ($order['status'] === 'pending'): ?>
                请尽快完成支付，超时订单将自动关闭
                <?php elseif ($order['status'] === 'completed'): ?>
                订单已完成
                <?php elseif ($order['status'] === 'delivering'): ?>
                商品正在发货中，请稍候
                <?php elseif ($order['status'] === 'expired'): ?>
                订单已超时关闭
                <?php endif; ?>
            </div>
        </div>
        <div class="uc-order-status-amount"><?= Currency::displaySnapshot((int) $order['pay_amount'], $orderDispCode, $orderDispRate) ?></div>
    </div>

    <!-- 订单信息 -->
    <div class="uc-form-card" style="margin-bottom:16px;">
        <div class="uc-section-title">订单信息</div>
        <div class="uc-order-info-grid">
            <div class="uc-order-info-item">
                <span class="uc-order-info-label">订单编号</span>
                <span class="uc-order-info-value" style="font-family:monospace;"><?= htmlspecialchars($order['order_no']) ?></span>
            </div>
            <div class="uc-order-info-item">
                <span class="uc-order-info-label">支付方式</span>
                <span class="uc-order-info-value"><?= htmlspecialchars($order['payment_name'] ?: '未选择') ?></span>
            </div>
            <div class="uc-order-info-item">
                <span class="uc-order-info-label">下单时间</span>
                <span class="uc-order-info-value"><?= htmlspecialchars(substr($order['created_at'], 0, 19)) ?></span>
            </div>
            <?php if ($order['pay_time']): ?>
            <div class="uc-order-info-item">
                <span class="uc-order-info-label">支付时间</span>
                <span class="uc-order-info-value"><?= htmlspecialchars(substr($order['pay_time'], 0, 19)) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    // 收货地址快照（仅需要地址的订单有值；虚拟卡密订单保持空，此块不显示）
    $shipAddr = null;
    if (!empty($order['shipping_address_snapshot'])) {
        $shipAddr = json_decode((string) $order['shipping_address_snapshot'], true);
    }
    ?>
    <?php if (is_array($shipAddr) && !empty($shipAddr['recipient'])): ?>
    <div class="uc-form-card uc-order-shipaddr" style="margin-bottom:16px;">
        <div class="uc-section-title"><i class="fa fa-map-marker"></i> 收货地址</div>
        <div class="uc-order-shipaddr-head">
            <strong><?= htmlspecialchars($shipAddr['recipient']) ?></strong>
            <span class="uc-order-shipaddr-mobile"><?= htmlspecialchars($shipAddr['mobile'] ?? '') ?></span>
        </div>
        <div class="uc-order-shipaddr-text">
            <?= htmlspecialchars(trim(($shipAddr['province'] ?? '') . ' ' . ($shipAddr['city'] ?? '') . ' ' . ($shipAddr['district'] ?? ''))) ?>
            <?= htmlspecialchars($shipAddr['detail'] ?? '') ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 商品信息 -->
    <div class="uc-form-card" style="margin-bottom:16px;">
        <div class="uc-section-title">商品信息</div>
        <?php foreach ($orderGoods as $og): ?>
        <div class="uc-order-goods-item">
            <div class="uc-order-goods-img">
                <?php if (!empty($og['cover_image'])): ?>
                <img src="<?= htmlspecialchars($og['cover_image']) ?>" alt="">
                <?php else: ?>
                <span style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;color:#ddd;font-size:20px;"><i class="fa fa-picture-o"></i></span>
                <?php endif; ?>
            </div>
            <div class="uc-order-goods-info">
                <div class="uc-order-goods-name"><?= htmlspecialchars($og['goods_title']) ?></div>
                <div class="uc-order-goods-spec"><?= htmlspecialchars($og['spec_name']) ?> × <?= (int) $og['quantity'] ?></div>
            </div>
            <div class="uc-order-goods-price"><?= Currency::displaySnapshot((int) $og['price'], $orderDispCode, $orderDispRate) ?></div>
        </div>

        <?php
        // 发货内容交给插件渲染（卡密类走分卡片 + 单条复制 + 导出；空串表示不接管 → 核心走默认整体展示）
        $pluginDeliveryHtml = (string) applyFilter('frontend_order_goods_delivery_html', '', $og);
        ?>
        <?php if ($pluginDeliveryHtml !== ''): ?>
            <?= $pluginDeliveryHtml ?>
        <?php elseif (!empty($og['delivery_content'])): ?>
        <div class="uc-order-delivery">
            <div class="uc-order-delivery-label"><i class="fa fa-gift"></i> 发货内容</div>
            <div class="uc-order-delivery-text"><?= nl2br(htmlspecialchars($og['delivery_content'])) ?></div>
            <button type="button" class="uc-btn uc-btn--copy" onclick="copyDelivery(this)" data-content="<?= htmlspecialchars($og['delivery_content']) ?>" title="复制"><i class="fa fa-copy"></i> 复制</button>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div style="text-align:center; padding:12px 0;">
        <a href="?c=goods_list" data-pjax class="uc-btn uc-btn--primary" style="text-decoration:none;">继续购物</a>
    </div>

    <script>
    function copyDelivery(btn) {
        var text = btn.getAttribute('data-content');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () { layui.layer.msg('已复制'); });
        } else {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;left:-9999px;';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            layui.layer.msg('已复制');
        }
    }
    </script>

    <?php else: ?>
    <div class="uc-empty">
        <i class="fa fa-inbox"></i>
        <p>订单不存在或无权查看</p>
    </div>
    <?php endif; ?>
</div>
