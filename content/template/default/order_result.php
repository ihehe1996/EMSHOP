<?php
defined('EM_ROOT') || exit('access denied!');
?>
<div class="page-body">

    <?php if (!empty($order)): ?>
    <?php
    $isPaid = !in_array($order['status'], ['pending', 'expired', 'cancelled', 'failed']);
    $statusName = OrderModel::statusName($order['status']);
    // 订单金额按下单时快照币种/汇率展示 —— 历史订单不受后续访客切换币种影响
    $orderDispCode = (string) ($order['display_currency_code'] ?? '');
    $orderDispRate = (int) ($order['display_rate'] ?? 0);
    ?>

    <div class="order-result">
        <!-- 状态卡片 -->
        <div class="order-result-card <?= $isPaid ? 'order-result-card--success' : '' ?>">
            <div class="order-result-icon">
                <?php if ($isPaid): ?>
                <i class="fa fa-check-circle"></i>
                <?php else: ?>
                <i class="fa fa-clock-o"></i>
                <?php endif; ?>
            </div>
            <div class="order-result-title"><?= $isPaid ? '支付成功' : '等待支付' ?></div>
            <div class="order-result-desc">
                <?php if ($isPaid): ?>
                您的订单已支付成功<?= $order['status'] === 'completed' ? '，商品已发货' : '' ?>
                <?php else: ?>
                请尽快完成支付，超时订单将自动关闭
                <?php endif; ?>
            </div>
        </div>

        <!-- 订单信息 -->
        <div class="order-info-card">
            <h3 class="order-info-title">订单信息</h3>
            <div class="order-info-grid">
                <div class="order-info-item">
                    <span class="order-info-label">订单编号</span>
                    <span class="order-info-value" style="font-family:monospace;"><?= htmlspecialchars($order['order_no']) ?></span>
                </div>
                <div class="order-info-item">
                    <span class="order-info-label">订单状态</span>
                    <span class="order-info-value"><?= $statusName ?></span>
                </div>
                <div class="order-info-item">
                    <span class="order-info-label">支付金额</span>
                    <span class="order-info-value" style="color:#fa5252; font-weight:600;"><?= Currency::displaySnapshot((int) $order['pay_amount'], $orderDispCode, $orderDispRate) ?></span>
                </div>
                <div class="order-info-item">
                    <span class="order-info-label">支付方式</span>
                    <span class="order-info-value"><?= htmlspecialchars($order['payment_name'] ?: '未选择') ?></span>
                </div>
                <div class="order-info-item">
                    <span class="order-info-label">下单时间</span>
                    <span class="order-info-value"><?= htmlspecialchars($order['created_at']) ?></span>
                </div>
                <?php if ($order['pay_time']): ?>
                <div class="order-info-item">
                    <span class="order-info-label">支付时间</span>
                    <span class="order-info-value"><?= htmlspecialchars($order['pay_time']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 商品列表 -->
        <?php if (!empty($order_goods)): ?>
        <div class="order-info-card">
            <h3 class="order-info-title">商品信息</h3>
            <?php foreach ($order_goods as $og): ?>
            <div class="order-goods-item">
                <div class="order-goods-img">
                    <?php if (!empty($og['cover_image'])): ?>
                    <img src="<?= htmlspecialchars($og['cover_image']) ?>" alt="">
                    <?php else: ?>
                    <span class="order-goods-img-placeholder"><i class="fa fa-picture-o"></i></span>
                    <?php endif; ?>
                </div>
                <div class="order-goods-info">
                    <div class="order-goods-name"><?= htmlspecialchars($og['goods_title']) ?></div>
                    <?php if (!empty($og['spec_name'])): ?>
                    <div class="order-goods-spec"><?= htmlspecialchars($og['spec_name']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="order-goods-qty">×<?= (int) $og['quantity'] ?></div>
                <div class="order-goods-price"><?= Currency::displaySnapshot((int) $og['price'], $orderDispCode, $orderDispRate) ?></div>
            </div>

            <?php if (!empty($og['delivery_content'])): ?>
            <div class="order-delivery-content">
                <div class="order-delivery-label"><i class="fa fa-gift"></i> 发货内容</div>
                <div class="order-delivery-text"><?= nl2br(htmlspecialchars($og['delivery_content'])) ?></div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div style="text-align:center; margin-top:24px;">
            <a href="<?= url_goods_list() ?>" data-pjax class="btn btn-primary">继续购物</a>
        </div>
    </div>

    <?php else: ?>
    <div class="card empty-state">
        <div class="empty-icon">&#128270;</div>
        <h3>订单不存在</h3>
        <p>该订单可能不存在或您无权查看</p>
        <a href="<?= url_home() ?>" data-pjax class="btn btn-primary">返回首页</a>
    </div>
    <?php endif; ?>

</div>
