<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

/** @var array<string, mixed> $order */
/** @var array<int, array<string, mixed>> $items */

$esc = function (?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
};

// 解析联系信息（contact_info 可能是 JSON，也可能是普通字符串）
$contactRaw = (string) ($order['contact_info'] ?? '');
$contactPairs = [];
$contactPlain = '';
if ($contactRaw !== '') {
    if (substr($contactRaw, 0, 1) === '{') {
        $decoded = json_decode($contactRaw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $k => $v) {
                $contactPairs[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
            }
        }
    }
    if (!$contactPairs) {
        $contactPlain = $contactRaw;
    }
}

include EM_ROOT . '/admin/view/popup/header.php';
?>

<style>
.popup-body { background: #f5f7fa; }
.popup-body .popup-inner { padding: 18px; }

.od-grid { display: grid; gap: 14px; }
.od-card {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
    padding: 16px 20px;
}
.od-card__title {
    font-size: 13px; font-weight: 600; color: #1f2937;
    margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; gap: 6px;
}
.od-card__title i { color: #6b7280; font-size: 13px; }

.od-meta { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px 24px; font-size: 13px; }
.od-meta__row { display: flex; gap: 10px; align-items: center; }
.od-meta__label { color: #9ca3af; flex-shrink: 0; min-width: 70px; }
.od-meta__value { color: #1f2937; word-break: break-all; }
.od-meta__value code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 12.5px; padding: 1px 6px;
    background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 3px;
}

.od-status {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 9px; font-size: 12px; font-weight: 500;
    border-radius: 11px;
}
.od-status--pending          { background: #fff7ed; color: #c2410c; }
.od-status--paid             { background: #dbeafe; color: #1e40af; }
.od-status--delivering       { background: #ede9fe; color: #5b21b6; }
.od-status--delivered        { background: #cffafe; color: #155e75; }
.od-status--completed        { background: #dcfce7; color: #166534; }
.od-status--cancelled        { background: #f3f4f6; color: #6b7280; }
.od-status--refunding        { background: #fef3c7; color: #92400e; }
.od-status--refunded         { background: #f3f4f6; color: #4b5563; }
.od-status--expired          { background: #f3f4f6; color: #6b7280; }
.od-status--failed,
.od-status--delivery_failed  { background: #fef2f2; color: #b91c1c; }

/* 商品明细表 */
.od-items { width: 100%; border-collapse: collapse; font-size: 13px; }
.od-items thead th {
    text-align: left; font-weight: 500; color: #6b7280; font-size: 12px;
    padding: 8px 10px; background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
}
.od-items tbody td {
    padding: 12px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top;
}
.od-items tbody tr:last-child td { border-bottom: 0; }
.od-items__goods { display: flex; align-items: center; gap: 10px; }
.od-items__cover {
    width: 40px; height: 40px; border-radius: 4px; object-fit: cover;
    background: #f3f4f6; flex-shrink: 0;
}
.od-items__title { font-size: 13px; color: #1f2937; line-height: 1.4; font-weight: 500; }
.od-items__spec { font-size: 11.5px; color: #9ca3af; margin-top: 2px; }
.od-items__price { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
.od-items__cost  { color: #8b5cf6; }
.od-items__fee   { color: #f59e0b; }
.od-items__net   { color: #16a34a; font-weight: 600; }
.od-items__shipped {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 1px 7px; font-size: 11px;
    background: #dcfce7; color: #166534; border-radius: 9px;
}
.od-items__pending {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 1px 7px; font-size: 11px;
    background: #fff7ed; color: #c2410c; border-radius: 9px;
}

/* 订单汇总（金额三件套） */
.od-summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 14px; }
.od-summary__cell {
    text-align: center; padding: 12px;
    background: #f9fafb; border-radius: 8px;
}
.od-summary__label { font-size: 11.5px; color: #9ca3af; margin-bottom: 4px; }
.od-summary__value { font-size: 16px; font-weight: 700; color: #1f2937; }
.od-summary__cell--paid .od-summary__value { color: #2563eb; }
.od-summary__cell--cost .od-summary__value { color: #8b5cf6; font-size: 14px; }
.od-summary__cell--fee  .od-summary__value { color: #f59e0b; font-size: 14px; }
.od-summary__cell--net  .od-summary__value { color: #16a34a; }

/* 联系信息 */
.od-contact-pairs { display: grid; grid-template-columns: max-content 1fr; gap: 6px 16px; font-size: 13px; }
.od-contact-pairs dt { color: #9ca3af; }
.od-contact-pairs dd { color: #1f2937; margin: 0; word-break: break-all; }
.od-contact-plain { color: #1f2937; font-size: 13px; line-height: 1.7; white-space: pre-wrap; word-break: break-all; }
.od-empty { color: #9ca3af; font-size: 12.5px; }
</style>

<div class="popup-inner">
    <div class="od-grid">

        <!-- 订单基本信息 -->
        <div class="od-card">
            <div class="od-card__title"><i class="fa fa-info-circle"></i> 基本信息</div>
            <div class="od-meta">
                <div class="od-meta__row">
                    <span class="od-meta__label">订单号</span>
                    <span class="od-meta__value"><code><?= $esc((string) $order['order_no']) ?></code></span>
                </div>
                <div class="od-meta__row">
                    <span class="od-meta__label">状态</span>
                    <span class="od-meta__value"><span class="od-status od-status--<?= $esc((string) $order['status']) ?>"><?= $esc((string) $order['status_name']) ?></span></span>
                </div>
                <div class="od-meta__row">
                    <span class="od-meta__label">买家</span>
                    <span class="od-meta__value">
                        <?php if ((int) $order['user_id'] > 0): ?>
                            <?= $esc((string) ($order['nickname'] ?? '') ?: ($order['username'] ?? '')) ?: '用户 #' . (int) $order['user_id'] ?>
                            <code>#<?= (int) $order['user_id'] ?></code>
                        <?php else: ?>
                            <span class="od-empty">游客</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="od-meta__row">
                    <span class="od-meta__label">支付方式</span>
                    <span class="od-meta__value"><?= !empty($order['payment_name']) ? $esc((string) $order['payment_name']) : '<span class="od-empty">—</span>' ?></span>
                </div>
                <div class="od-meta__row">
                    <span class="od-meta__label">下单时间</span>
                    <span class="od-meta__value"><?= $esc((string) ($order['created_at'] ?? '')) ?></span>
                </div>
                <div class="od-meta__row">
                    <span class="od-meta__label">支付时间</span>
                    <span class="od-meta__value"><?= !empty($order['pay_time']) ? $esc((string) $order['pay_time']) : '<span class="od-empty">—</span>' ?></span>
                </div>
                <div class="od-meta__row">
                    <span class="od-meta__label">发货时间</span>
                    <span class="od-meta__value"><?= !empty($order['delivery_time']) ? $esc((string) $order['delivery_time']) : '<span class="od-empty">—</span>' ?></span>
                </div>
                <div class="od-meta__row">
                    <span class="od-meta__label">完成时间</span>
                    <span class="od-meta__value"><?= !empty($order['complete_time']) ? $esc((string) $order['complete_time']) : '<span class="od-empty">—</span>' ?></span>
                </div>
            </div>
        </div>

        <!-- 商品明细（含成本/手续费/净收入） -->
        <div class="od-card">
            <div class="od-card__title"><i class="fa fa-shopping-bag"></i> 商品明细 <span style="color:#9ca3af;font-weight:400;font-size:12px;">（共 <?= count($items) ?> 件）</span></div>
            <table class="od-items">
                <thead>
                    <tr>
                        <th>商品</th>
                        <th style="text-align:right;width:140px;">单价 × 数量</th>
                        <th style="text-align:right;width:110px;">小计</th>
                        <th style="text-align:right;width:100px;">拿货成本</th>
                        <th style="text-align:right;width:90px;">手续费</th>
                        <th style="text-align:right;width:110px;">本行净收入</th>
                        <th style="text-align:center;width:80px;">发货</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td>
                            <div class="od-items__goods">
                                <?php if (!empty($it['cover_image'])): ?>
                                <img class="od-items__cover" src="<?= $esc((string) $it['cover_image']) ?>" alt="" onerror="this.style.visibility='hidden'">
                                <?php else: ?>
                                <div class="od-items__cover"></div>
                                <?php endif; ?>
                                <div>
                                    <div class="od-items__title"><?= $esc((string) ($it['goods_title'] ?? '')) ?></div>
                                    <?php if (!empty($it['spec_name'])): ?>
                                    <div class="od-items__spec"><?= $esc((string) $it['spec_name']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="od-items__price" style="text-align:right;">
                            <?= $esc((string) $it['price_view']) ?> × <?= (int) $it['quantity'] ?>
                        </td>
                        <td style="text-align:right;font-weight:500;"><?= $esc((string) $it['line_subtotal_view']) ?></td>
                        <td style="text-align:right;" class="od-items__cost"><?= $esc((string) $it['cost_amount_view']) ?></td>
                        <td style="text-align:right;" class="od-items__fee"><?= $esc((string) $it['fee_amount_view']) ?></td>
                        <td style="text-align:right;" class="od-items__net"><?= $esc((string) $it['line_net_view']) ?></td>
                        <td style="text-align:center;">
                            <?php if (!empty($it['delivery_content'])): ?>
                            <span class="od-items__shipped"><i class="fa fa-check"></i> 已发</span>
                            <?php else: ?>
                            <span class="od-items__pending"><i class="fa fa-clock-o"></i> 待发</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <!-- 订单汇总：金额 -->
            <div class="od-summary">
                <div class="od-summary__cell od-summary__cell--paid">
                    <div class="od-summary__label">买家实付</div>
                    <div class="od-summary__value"><?= $esc((string) $order['pay_amount_view']) ?></div>
                </div>
                <div class="od-summary__cell od-summary__cell--cost">
                    <div class="od-summary__label">拿货成本</div>
                    <div class="od-summary__value"><?= $esc((string) $order['total_cost_view']) ?></div>
                </div>
                <div class="od-summary__cell od-summary__cell--fee">
                    <div class="od-summary__label">手续费</div>
                    <div class="od-summary__value"><?= $esc((string) $order['total_fee_view']) ?></div>
                </div>
            </div>
            <div class="od-summary" style="margin-top:8px;">
                <div class="od-summary__cell od-summary__cell--net" style="grid-column: 1 / 4;">
                    <div class="od-summary__label">本店净收入（实付 − 成本 − 手续费）</div>
                    <div class="od-summary__value" style="font-size:20px;"><?= $esc((string) $order['net_income_view']) ?></div>
                </div>
            </div>
        </div>

        <!-- 买家联系信息 -->
        <div class="od-card">
            <div class="od-card__title"><i class="fa fa-address-card-o"></i> 买家联系方式</div>
            <?php if ($contactPairs): ?>
                <dl class="od-contact-pairs">
                    <?php foreach ($contactPairs as $k => $v): ?>
                    <dt><?= $esc((string) $k) ?></dt>
                    <dd><?= $esc((string) $v) ?></dd>
                    <?php endforeach; ?>
                </dl>
            <?php elseif ($contactPlain !== ''): ?>
                <div class="od-contact-plain"><?= $esc($contactPlain) ?></div>
            <?php else: ?>
                <span class="od-empty">买家未填写联系信息</span>
            <?php endif; ?>
        </div>

        <?php if (!empty($order['remark'])): ?>
        <div class="od-card">
            <div class="od-card__title"><i class="fa fa-comment-o"></i> 买家备注</div>
            <div style="color:#374151;font-size:13px;line-height:1.7;white-space:pre-wrap;"><?= $esc((string) $order['remark']) ?></div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include EM_ROOT . '/admin/view/popup/footer.php'; ?>
