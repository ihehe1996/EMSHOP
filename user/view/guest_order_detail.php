<?php
defined('EM_ROOT') || exit('access denied!');

// 由 user/find_order.php 注入：
// $detailOrder / $detailDenied / $detailOrderNo / $detailGoods / $detailExtraPairs / $statusMap / $esc

if ($detailOrder !== null):
    $st = $statusMap[$detailOrder['status']] ?? ['label' => $detailOrder['status'], 'color' => '#6b7280', 'bg' => '#f3f4f6'];
    $detailAwaitAsync = in_array((string) ($detailOrder['status'] ?? ''), ['paid', 'delivering'], true);
    $dispCode = (string) ($detailOrder['_disp_code'] ?? '');
    $dispRate = (int) ($detailOrder['_disp_rate'] ?? 0);
    $payAmountHtml = Currency::displaySnapshot((int) ($detailOrder['pay_amount'] ?? 0), $dispCode, $dispRate);

    $statusDescMap = [
        'pending'    => '请尽快完成支付，超时订单将自动关闭',
        'paid'       => '已付款，正在为您处理发货',
        'delivering' => '商品正在发货中，请稍候',
        'delivered'  => '商品已发货，请查收',
        'completed'  => '订单已完成',
        'refunding'  => '退款处理中，请耐心等待',
        'refunded'   => '订单已退款',
        'cancelled'  => '订单已取消',
        'expired'    => '订单已超时关闭',
        'failed'     => '订单处理失败',
    ];
    $statusDesc = $statusDescMap[(string) ($detailOrder['status'] ?? '')] ?? '';

    $detailShipAddr = null;
    if (!empty($detailOrder['shipping_address_snapshot'])) {
        $detailShipAddr = json_decode((string) $detailOrder['shipping_address_snapshot'], true);
    }
?>
    <div class="fo-detail">
        <a href="/user/find_order.php" data-pjax class="fo-detail__nav">
            <i class="fa fa-angle-left"></i> 返回查单
        </a>

        <!-- 状态总览：状态 + 金额一眼可见 -->
        <section class="fo-detail__hero<?= $detailAwaitAsync ? ' fo-detail__hero--awaiting' : '' ?>">
            <div class="fo-detail__hero-top">
                <span class="fo-status-pill<?= $detailAwaitAsync ? ' fo-status-pill--awaiting' : '' ?>"
                      style="color:<?= $st['color'] ?>;background:<?= $st['bg'] ?>;">
                    <?php if ($detailAwaitAsync): ?><i class="fa fa-spinner fa-spin fo-status-pill__spin" aria-hidden="true"></i><?php endif; ?>
                    <?= $esc($st['label']) ?>
                </span>
                <div class="fo-detail__hero-amount"><?= $payAmountHtml ?></div>
            </div>
            <?php if ($statusDesc !== ''): ?>
            <p class="fo-detail__hero-desc"><?= $esc($statusDesc) ?></p>
            <?php endif; ?>
            <div class="fo-detail__hero-no">
                <span class="fo-detail__hero-no-label">订单编号</span>
                <code class="fo-detail__hero-no-val"><?= $esc($detailOrder['order_no']) ?></code>
            </div>
        </section>

        <!-- 订单信息 -->
        <section class="fo-detail__block">
            <h3 class="fo-detail__block-title">订单信息</h3>
            <dl class="fo-detail__kv">
                <div class="fo-detail__kv-item">
                    <dt>下单时间</dt>
                    <dd><?= $esc((string) ($detailOrder['created_at'] ?? '')) ?></dd>
                </div>
                <?php if (!empty($detailOrder['pay_time'])): ?>
                <div class="fo-detail__kv-item">
                    <dt>支付时间</dt>
                    <dd><?= $esc((string) $detailOrder['pay_time']) ?></dd>
                </div>
                <?php endif; ?>
                <?php if (!empty($detailOrder['payment_name'])): ?>
                <div class="fo-detail__kv-item">
                    <dt>支付方式</dt>
                    <dd><?= $esc((string) $detailOrder['payment_name']) ?></dd>
                </div>
                <?php endif; ?>
                <div class="fo-detail__kv-item">
                    <dt>订单金额</dt>
                    <dd class="fo-detail__kv-amount"><?= $payAmountHtml ?></dd>
                </div>
            </dl>
        </section>

        <?php
        $extraPairs = $detailExtraPairs;
        $layout = 'fo';
        include EM_ROOT . '/include/view/partials/order_extra_fields.php';
        ?>

        <?php if (is_array($detailShipAddr) && !empty($detailShipAddr['recipient'])): ?>
        <section class="fo-detail__block">
            <h3 class="fo-detail__block-title"><i class="fa fa-map-marker"></i> 收货地址</h3>
            <div class="fo-detail__addr">
                <div class="fo-detail__addr-head">
                    <strong><?= $esc((string) $detailShipAddr['recipient']) ?></strong>
                    <span><?= $esc((string) ($detailShipAddr['mobile'] ?? '')) ?></span>
                </div>
                <p class="fo-detail__addr-text">
                    <?= $esc(trim(($detailShipAddr['province'] ?? '') . ' ' . ($detailShipAddr['city'] ?? '') . ' ' . ($detailShipAddr['district'] ?? ''))) ?>
                    <?= $esc((string) ($detailShipAddr['detail'] ?? '')) ?>
                </p>
            </div>
        </section>
        <?php endif; ?>

        <?php
        $orderGoods = $detailGoods;
        $layout = 'fo';
        include EM_ROOT . '/include/view/partials/order_goods_guide.php';
        ?>

        <?php if (!empty($detailGoods)): ?>
        <section class="fo-detail__block">
            <h3 class="fo-detail__block-title">商品明细</h3>
            <div class="fo-detail__goods-list">
            <?php foreach ($detailGoods as $g): ?>
                <?php
                $pluginDeliveryHtml = (string) applyFilter('frontend_order_goods_delivery_html', '', $g);
                $hasDelivery = $pluginDeliveryHtml !== '' || !empty($g['delivery_content']);
                ?>
                <article class="fo-detail__goods<?= $hasDelivery ? ' fo-detail__goods--has-delivery' : '' ?>">
                    <div class="fo-detail__goods-main">
                        <?php if (!empty($g['cover_image'])): ?>
                        <img class="fo-detail__goods-cover" src="<?= $esc((string) $g['cover_image']) ?>" alt="">
                        <?php else: ?>
                        <div class="fo-detail__goods-cover fo-detail__goods-cover--empty"><i class="fa fa-image"></i></div>
                        <?php endif; ?>
                        <div class="fo-detail__goods-body">
                            <div class="fo-detail__goods-title"><?= $esc((string) $g['goods_title']) ?></div>
                            <?php if (!empty($g['spec_name'])): ?>
                            <div class="fo-detail__goods-spec"><?= $esc((string) $g['spec_name']) ?></div>
                            <?php endif; ?>
                            <div class="fo-detail__goods-meta">
                                <span class="fo-detail__goods-price"><?= Currency::displaySnapshot((int) $g['price'], $dispCode, $dispRate) ?></span>
                                <span class="fo-detail__goods-qty">× <?= (int) $g['quantity'] ?></span>
                            </div>
                        </div>
                    </div>
                    <?php if ($pluginDeliveryHtml !== ''): ?>
                        <div class="fo-detail__goods-delivery"><?= $pluginDeliveryHtml ?></div>
                    <?php elseif (!empty($g['delivery_content'])): ?>
                    <div class="fo-detail__delivery">
                        <div class="fo-detail__delivery-label"><i class="fa fa-key"></i> 发货内容</div>
                        <pre class="fo-detail__delivery-content"><?= $esc((string) $g['delivery_content']) ?></pre>
                    </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!empty($detailOrder['remark'])): ?>
        <section class="fo-detail__block">
            <h3 class="fo-detail__block-title">订单备注</h3>
            <div class="fo-detail__remark"><?= $esc((string) $detailOrder['remark']) ?></div>
        </section>
        <?php endif; ?>

        <script>
        (function () {
            if (typeof EMSOrderPoll === 'undefined') return;
            EMSOrderPoll.stopDetail();
            EMSOrderPoll.startDetail({
                orderNo: <?= json_encode((string) ($detailOrder['order_no'] ?? ''), JSON_UNESCAPED_UNICODE) ?>,
                csrfToken: <?= json_encode(Csrf::token(), JSON_UNESCAPED_UNICODE) ?>,
                initialStatus: <?= json_encode((string) ($detailOrder['status'] ?? ''), JSON_UNESCAPED_UNICODE) ?>
            });
        })();
        </script>
    </div>
<?php elseif ($detailDenied): ?>
    <div class="fo-detail">
        <a href="/user/find_order.php" data-pjax class="fo-detail__nav">
            <i class="fa fa-angle-left"></i> 去查询
        </a>
        <section class="fo-detail__empty">
            <div class="fo-detail__empty-icon"><i class="fa fa-lock"></i></div>
            <h2 class="fo-detail__empty-title">无法直接访问订单详情</h2>
            <p class="fo-detail__empty-text">为保护买家隐私，订单详情不能通过链接直接打开。</p>
            <p class="fo-detail__empty-hint">请回到查单页，用浏览器订单 / 联系方式 / 订单密码 / 订单编号查询后，再点「查看详情」。</p>
            <a href="/user/find_order.php" data-pjax class="fo-detail__empty-btn"><i class="fa fa-search"></i> 去查单</a>
        </section>
    </div>
<?php else: ?>
    <div class="fo-detail">
        <a href="/user/find_order.php" data-pjax class="fo-detail__nav">
            <i class="fa fa-angle-left"></i> 去查询
        </a>
        <section class="fo-detail__empty">
            <div class="fo-detail__empty-icon"><i class="fa fa-inbox"></i></div>
            <h2 class="fo-detail__empty-title">订单不存在或已被删除</h2>
            <p class="fo-detail__empty-hint">请回到查单页重新查询。</p>
            <a href="/user/find_order.php" data-pjax class="fo-detail__empty-btn"><i class="fa fa-search"></i> 去查单</a>
        </section>
    </div>
<?php endif; ?>
