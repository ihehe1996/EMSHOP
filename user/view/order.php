<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

// 订单卡片右上角状态徽章：[文字, 前景色, 底色]
// 统一使用用户视角的措辞：paid / delivering 都称"待发货"（用户视角他们都在等收货），delivered 称"待收货"
$statusColorMap = [
    'pending'    => ['待付款', '#faad14', '#fffbe6'],
    'paid'       => ['待发货', '#1890ff', '#e6f7ff'],
    'delivering' => ['待发货', '#1890ff', '#e6f7ff'],
    'delivered'  => ['待收货', '#52c41a', '#f6ffed'],
    'completed'  => ['已完成', '#52c41a', '#f6ffed'],
    'cancelled'  => ['已取消', '#999',   '#f5f5f5'],
    'expired'    => ['已过期', '#999',   '#f5f5f5'],
    'refunding'  => ['退款中', '#faad14', '#fffbe6'],
    'refunded'   => ['已退款', '#52c41a', '#f6ffed'],
    'failed'     => ['失败',   '#f5222d', '#fff2f0'],
];
?>
<div class="uc-page">
    <div class="uc-page-header">
        <h2 class="uc-page-title">我的订单</h2>
        <p class="uc-page-desc">查看您的全部订单记录</p>
    </div>

    <!-- 状态筛选 tabs -->
    <div class="uc-order-tabs">
        <?php foreach ($statusTabs as $key => $label): ?>
        <?php $cnt = (int) ($statusCounts[$key] ?? 0); ?>
        <a href="/user/order.php?status=<?= $key ?>" data-pjax="#userContent"
           class="uc-order-tab<?= $status === $key ? ' is-active' : '' ?>">
            <?= $label ?><?php if ($cnt > 0): ?> <span class="uc-order-tab__count"><?= $cnt ?></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($orders)): ?>
    <div class="uc-order-list">
        <?php foreach ($orders as $order): ?>
        <?php
        $orderId = (int) $order['id'];
        $statusInfo = $statusColorMap[$order['status']] ?? [$order['status'], '#999', '#f5f5f5'];
        $goodsItems = $orderGoodsMap[$orderId] ?? [];
        // 订单金额用快照（下单时的币种 + 汇率），历史稳定
        $orderDispCode = (string) ($order['display_currency_code'] ?? '');
        $orderDispRate = (int) ($order['display_rate'] ?? 0);
        $totalQty = 0;
        foreach ($goodsItems as $g) { $totalQty += (int) $g['quantity']; }
        ?>
        <div class="uc-order-card">
            <!-- 卡片头：订单号 + 时间 + 状态 -->
            <div class="uc-order-card-head">
                <div class="uc-order-card-head-left">
                    <span class="uc-order-no">订单号：<?= htmlspecialchars($order['order_no']) ?></span>
                    <span class="uc-order-time"><?= htmlspecialchars(substr((string) $order['created_at'], 0, 19)) ?></span>
                </div>
                <span class="uc-order-status" style="color:<?= $statusInfo[1] ?>;background:<?= $statusInfo[2] ?>;">
                    <?= htmlspecialchars($statusInfo[0]) ?>
                </span>
            </div>

            <!-- 商品列表（快照） -->
            <?php foreach ($goodsItems as $g): ?>
            <div class="uc-order-goods">
                <div class="uc-order-goods-img">
                    <?php if (!empty($g['cover_image'])): ?>
                    <img src="<?= htmlspecialchars($g['cover_image']) ?>" alt="">
                    <?php else: ?>
                    <span class="uc-order-goods-img--empty"><i class="fa fa-image"></i></span>
                    <?php endif; ?>
                </div>
                <div class="uc-order-goods-info">
                    <div class="uc-order-goods-title"><?= htmlspecialchars($g['goods_title']) ?></div>
                    <?php if (!empty($g['spec_name'])): ?>
                    <div class="uc-order-goods-spec"><?= htmlspecialchars($g['spec_name']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="uc-order-goods-price">
                    <?= Currency::displaySnapshot((int) $g['price'], $orderDispCode, $orderDispRate) ?>
                    <span class="uc-order-goods-qty">× <?= (int) $g['quantity'] ?></span>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- 卡片脚：合计 + 操作 -->
            <div class="uc-order-card-foot">
                <div class="uc-order-total">
                    共 <strong><?= $totalQty ?></strong> 件，实付
                    <strong class="uc-order-total-amount">
                        <?= Currency::displaySnapshot((int) $order['pay_amount'], $orderDispCode, $orderDispRate) ?>
                    </strong>
                </div>
                <div class="uc-order-actions">
                    <a href="/user/order_detail.php?order_no=<?= urlencode((string) $order['order_no']) ?>"
                       data-pjax="#userContent" class="uc-order-btn">查看详情</a>
                    <?php if ($order['status'] === 'pending'): ?>
                    <a href="/user/order_detail.php?order_no=<?= urlencode((string) $order['order_no']) ?>"
                       data-pjax="#userContent" class="uc-order-btn uc-order-btn--primary">去支付</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="uc-pagination">
        <?php if ($page > 1): ?>
        <a href="/user/order.php?status=<?= $status ?>&page=<?= $page - 1 ?>"
           data-pjax="#userContent" class="uc-page-btn">&laquo; 上一页</a>
        <?php endif; ?>

        <?php
        $pStart = max(1, $page - 2);
        $pEnd = min($totalPages, $page + 2);
        for ($i = $pStart; $i <= $pEnd; $i++):
        ?>
        <a href="/user/order.php?status=<?= $status ?>&page=<?= $i ?>"
           data-pjax="#userContent" class="uc-page-btn<?= $i === $page ? ' active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
        <a href="/user/order.php?status=<?= $status ?>&page=<?= $page + 1 ?>"
           data-pjax="#userContent" class="uc-page-btn">下一页 &raquo;</a>
        <?php endif; ?>

        <span class="uc-page-info">共 <?= $total ?> 条</span>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="uc-empty">
        <i class="fa fa-inbox"></i>
        <p>暂无订单记录</p>
    </div>
    <?php endif; ?>
</div>
