<?php
/** @var array<string, mixed> $frontUser */
/** @var array<string, mixed> $currentMerchant */
/** @var array<string, mixed> $uc */
/** @var int $feeRate  商户等级提现手续费率（万分位） */

$csrfToken = Csrf::token();
$feeRateView = rtrim(rtrim(number_format($feeRate / 100, 2, '.', ''), '0'), '.');
?>
<div class="mc-page">
    <div class="mc-page-header">
        <h2 class="mc-page-title">店铺余额</h2>
        <p class="mc-page-desc">店铺余额提现到消费余额，实时到账；当前手续费率 <strong><?= $feeRateView ?>%</strong></p>
    </div>

    <div class="wd-grid">
        <!-- 左侧：申请卡 -->
        <div class="wd-apply">
            <!-- Hero 余额展示 -->
            <div class="wd-hero">
                <div class="wd-hero__bg"></div>
                <div class="wd-hero__inner">
                    <div class="wd-hero__label">
                        <i class="fa fa-wallet"></i> 可提现店铺余额
                    </div>
                    <div class="wd-hero__value"><?= htmlspecialchars((string) $uc['shopBalance']) ?></div>
                    <div class="wd-hero__hint">提现到消费余额后可用于站内购物</div>
                </div>
            </div>

            <form id="mcWdForm" autocomplete="off" class="wd-form">
                <input type="hidden" name="_action" value="apply">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <!-- 金额输入 -->
                <div class="wd-field">
                    <label class="wd-field__label">提现金额</label>
                    <div class="wd-amount-input">
                        <span class="wd-amount-input__currency"><?= htmlspecialchars((string) $uc['currencySymbol']) ?></span>
                        <input type="number" name="amount" id="mcWdAmount" step="0.01" min="0.01" placeholder="0.00" autocomplete="off">
                    </div>
                </div>

                <!-- 快捷比例 -->
                <div class="wd-quick">
                    <button type="button" class="wd-quick__btn mc-wd-quick" data-ratio="0.25">25%</button>
                    <button type="button" class="wd-quick__btn mc-wd-quick" data-ratio="0.5">50%</button>
                    <button type="button" class="wd-quick__btn mc-wd-quick" data-ratio="0.75">75%</button>
                    <button type="button" class="wd-quick__btn mc-wd-quick" data-ratio="1">全部</button>
                </div>

                <!-- 实时预览（手续费 / 实到） -->
                <div class="wd-preview" id="mcWdPreview">
                    <div class="wd-preview__row wd-preview__row--placeholder">
                        <i class="fa fa-info-circle"></i> 输入金额后查看手续费和实到金额
                    </div>
                </div>

                <!-- 提交按钮 -->
                <button type="button" class="wd-submit" id="mcWdSubmit">
                    <i class="fa fa-paper-plane"></i> 立即提现
                </button>

                <!-- 说明 -->
                <div class="wd-tips">
                    <div class="wd-tips__row"><i class="fa fa-bolt"></i> 直通到账，无需审核</div>
                    <div class="wd-tips__row"><i class="fa fa-percent"></i> 手续费率 <strong><?= $feeRateView ?>%</strong>，按申请毛额计算</div>
                    <div class="wd-tips__row"><i class="fa fa-arrow-right"></i> 实到 = 申请金额 − 手续费</div>
                </div>
            </form>
        </div>

        <!-- 右侧：提现记录 -->
        <div class="wd-history">
            <div class="wd-history__head">
                <div class="wd-history__title">
                    <i class="fa fa-history"></i> 提现记录
                </div>
                <a href="/user/merchant/finance.php" data-pjax="#merchantContent" class="wd-history__more">
                    查看全部余额明细 <i class="fa fa-angle-right"></i>
                </a>
            </div>
            <table id="mcWdTable" lay-filter="mcWdTable"></table>
        </div>
    </div>
</div>

<style>
.wd-grid {
    display: grid;
    grid-template-columns: minmax(320px, 380px) 1fr;
    gap: 16px;
    align-items: start;
}
@media (max-width: 980px) { .wd-grid { grid-template-columns: 1fr; } }

/* ============ 左侧申请卡 ============ */
.wd-apply {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
}

.wd-hero {
    position: relative;
    color: #fff;
    overflow: hidden;
}
.wd-hero__bg {
    position: absolute; inset: 0;
    background: linear-gradient(135deg, #4e6ef2 0%, #2563eb 60%, #1d4ed8 100%);
}
.wd-hero__bg::after {
    content: ''; position: absolute; right: -50px; top: -50px;
    width: 200px; height: 200px; border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
}
.wd-hero__bg::before {
    content: ''; position: absolute; left: -40px; bottom: -60px;
    width: 160px; height: 160px; border-radius: 50%;
    background: rgba(255, 255, 255, 0.06);
}
.wd-hero__inner {
    position: relative;
    padding: 22px 22px 26px;
}
.wd-hero__label {
    font-size: 12px; opacity: 0.9; letter-spacing: 0.5px;
}
.wd-hero__label i { margin-right: 5px; }
.wd-hero__value {
    font-size: 32px; font-weight: 700;
    margin: 8px 0 4px;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    letter-spacing: 0.5px;
}
.wd-hero__hint {
    font-size: 12px; opacity: 0.85;
}

.wd-form { padding: 20px 22px 22px; }

.wd-field { margin-bottom: 14px; }
.wd-field__label {
    display: block;
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 8px;
    letter-spacing: 0.3px;
}

.wd-amount-input {
    display: flex; align-items: center;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 6px 14px;
    transition: all 0.15s;
}
.wd-amount-input:focus-within {
    background: #fff;
    border-color: #4e6ef2;
    box-shadow: 0 0 0 3px rgba(78, 110, 242, 0.12);
}
.wd-amount-input__currency {
    font-size: 18px;
    color: #6b7280;
    font-weight: 500;
    margin-right: 8px;
}
.wd-amount-input input {
    flex: 1;
    border: 0;
    outline: none;
    background: transparent;
    font-size: 24px;
    font-weight: 600;
    color: #1f2937;
    padding: 8px 0;
    width: 100%;
    /* 取消 number 输入的上下小箭头 */
    -moz-appearance: textfield;
}
.wd-amount-input input::-webkit-outer-spin-button,
.wd-amount-input input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.wd-quick {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 6px;
    margin-bottom: 14px;
}
.wd-quick__btn {
    padding: 7px 0;
    font-size: 12px;
    color: #6b7280;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.12s;
}
.wd-quick__btn:hover { border-color: #4e6ef2; color: #4e6ef2; background: #f5f7ff; }
.wd-quick__btn:active { transform: translateY(1px); }

.wd-preview {
    padding: 12px 14px;
    background: #f9fafb;
    border-radius: 8px;
    margin-bottom: 14px;
    font-size: 13px;
    line-height: 1.5;
}
.wd-preview__row {
    display: flex; justify-content: space-between; align-items: center;
    color: #6b7280;
}
.wd-preview__row + .wd-preview__row { margin-top: 6px; }
.wd-preview__row strong { color: #1f2937; font-weight: 600; }
.wd-preview__row--net strong { color: #16a34a; font-size: 16px; }
.wd-preview__row--placeholder { color: #9ca3af; font-size: 12px; justify-content: flex-start; gap: 6px; }
.wd-preview__row--placeholder i { color: #d1d5db; }

.wd-submit {
    width: 100%;
    padding: 12px;
    font-size: 14px; font-weight: 600;
    color: #fff;
    background: linear-gradient(135deg, #4e6ef2, #2563eb);
    border: 0;
    border-radius: 8px;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(78, 110, 242, 0.3);
    transition: transform 0.12s, box-shadow 0.15s, opacity 0.15s;
}
.wd-submit:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(78, 110, 242, 0.4);
}
.wd-submit:active:not(:disabled) {
    transform: translateY(0);
}
.wd-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.wd-submit i { margin-right: 6px; }

.wd-tips {
    margin-top: 16px;
    padding: 12px 14px;
    background: #fafbfc;
    border-radius: 6px;
    border: 1px dashed #e5e7eb;
}
.wd-tips__row {
    display: flex; align-items: center; gap: 8px;
    font-size: 12px; color: #6b7280;
    line-height: 1.7;
}
.wd-tips__row i { color: #9ca3af; width: 14px; text-align: center; }
.wd-tips__row strong { color: #1f2937; }

/* ============ 右侧记录 ============ */
.wd-history {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
}
.wd-history__head {
    display: flex; justify-content: space-between; align-items: center;
    padding: 14px 18px;
    border-bottom: 1px solid #f3f4f6;
}
.wd-history__title {
    font-size: 14px; font-weight: 600; color: #1f2937;
}
.wd-history__title i { color: #6b7280; margin-right: 6px; }
.wd-history__more {
    font-size: 12px; color: #4e6ef2;
    text-decoration: none;
}
.wd-history__more:hover { text-decoration: underline; }

/* 表格行内组件 */
.wd-amount-cell {
    text-align: right;
    line-height: 1.4;
}
.wd-amount-cell__main {
    color: #1f2937;
    font-weight: 600;
    font-size: 14px;
}
.wd-amount-cell__fee {
    color: #9ca3af;
    font-size: 11px;
    margin-top: 2px;
}
.wd-net {
    color: #16a34a;
    font-weight: 700;
    font-size: 14px;
}

.wd-status {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 10px; border-radius: 11px; font-size: 12px; font-weight: 500;
}
.wd-status--done     { background: #dcfce7; color: #166534; }
.wd-status--pending  { background: #fff7ed; color: #c2410c; }
.wd-status--rejected { background: #fef2f2; color: #b91c1c; }
.wd-status i { font-size: 10px; }

.wd-time { color: #6b7280; font-size: 12px; }
</style>

<script type="text/html" id="mcWdAmountTpl">
    <div class="wd-amount-cell">
        <div class="wd-amount-cell__main">{{ d.amount_view }}</div>
        {{# if(d.fee_amount && parseInt(d.fee_amount) > 0){ }}
        <div class="wd-amount-cell__fee">手续费 {{ d.fee_view }}</div>
        {{# } }}
    </div>
</script>

<script type="text/html" id="mcWdNetTpl">
    <span class="wd-net">{{ d.net_view }}</span>
</script>

<script type="text/html" id="mcWdStatusTpl">
    {{# var labels = {done:'已到账',pending:'审核中',rejected:'已驳回'};
        var icons  = {done:'check-circle',pending:'clock-o',rejected:'times-circle'}; }}
    <span class="wd-status wd-status--{{ d.status }}"><i class="fa fa-{{ icons[d.status] || 'circle' }}"></i> {{ labels[d.status] || d.status }}</span>
</script>

<script type="text/html" id="mcWdTimeTpl">
    <span class="wd-time">{{ d.created_at }}</span>
</script>

<script>
$(function () {
    'use strict';
    // PJAX 防重复绑定
    $(document).off('.mcWdPage');
    $(window).off('.mcWdPage');

    var csrfToken = <?php echo json_encode($csrfToken); ?>;
    var feeRate = <?= (int) $feeRate ?>;

    // 余额全部在访客当前展示币种语义下做加减；提交时再换回主货币传给后端
    var CUR = window.EMSHOP_CURRENCY || { symbol: '<?= htmlspecialchars((string) $uc['currencySymbol']) ?>', rate: 1 };
    var balanceRaw = <?= (int) ($uc['shopBalanceRaw'] ?? 0) ?>;       // BIGINT 主货币
    var balancePrimaryMain = balanceRaw / 1000000;                      // 主货币"元"
    var balanceVisitorMain = balancePrimaryMain * (CUR.rate || 1);      // 访客币"元"

    function toPrimaryMain(visitorMain) {
        return visitorMain / (CUR.rate || 1);
    }

    layui.use(['layer', 'table'], function () {
        var layer = layui.layer;
        var table = layui.table;

        function updatePreview() {
            var amtVisitor = parseFloat($('#mcWdAmount').val()) || 0;
            if (amtVisitor <= 0) {
                $('#mcWdPreview').html('<div class="wd-preview__row wd-preview__row--placeholder"><i class="fa fa-info-circle"></i> 输入金额后查看手续费和实到金额</div>');
                return;
            }
            // 手续费按主货币毛额 × feeRate/10000 取整（后端口径一致），再换回访客币显示
            var amtPrimary = toPrimaryMain(amtVisitor);
            var feePrimary = Math.floor(amtPrimary * 1000000 * feeRate / 10000) / 1000000;
            var netPrimary = amtPrimary - feePrimary;
            var feeVisitor = feePrimary * (CUR.rate || 1);
            var netVisitor = netPrimary * (CUR.rate || 1);

            $('#mcWdPreview').html(
                '<div class="wd-preview__row">'
                + '<span>申请金额</span>'
                + '<strong>' + CUR.symbol + amtVisitor.toFixed(2) + '</strong>'
                + '</div>'
                + '<div class="wd-preview__row">'
                + '<span>手续费（' + (feeRate / 100).toFixed(2).replace(/\.?0+$/, '') + '%）</span>'
                + '<strong style="color:#dc2626;">−' + CUR.symbol + feeVisitor.toFixed(2) + '</strong>'
                + '</div>'
                + '<div class="wd-preview__row wd-preview__row--net">'
                + '<span>实到账</span>'
                + '<strong>' + CUR.symbol + netVisitor.toFixed(2) + '</strong>'
                + '</div>'
            );
        }
        $(document).on('input.mcWdPage', '#mcWdAmount', updatePreview);
        updatePreview();

        $(document).on('click.mcWdPage', '.mc-wd-quick', function () {
            var ratio = parseFloat($(this).data('ratio')) || 1;
            $('#mcWdAmount').val((balanceVisitorMain * ratio).toFixed(2));
            updatePreview();
        });

        $(document).on('click.mcWdPage', '#mcWdSubmit', function () {
            var amtVisitor = parseFloat($('#mcWdAmount').val()) || 0;
            if (amtVisitor <= 0) { layer.msg('请填写金额'); return; }
            // +0.005 容忍浮点误差（100% 按钮回填时可能产生 0.00499 类误差）
            if (amtVisitor > balanceVisitorMain + 0.005) { layer.msg('超过可提现余额'); return; }

            var amtPrimary = toPrimaryMain(amtVisitor);
            var $btn = $(this);
            var origHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> 处理中...');

            $.ajax({
                url: '/user/merchant/withdraw.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    _action: 'apply',
                    csrf_token: $('#mcWdForm [name="csrf_token"]').val(),
                    // 换算成主货币再入库；保留 6 位小数避免 round-trip 损失
                    amount: amtPrimary.toFixed(6)
                },
                success: function (res) {
                    if (res.code === 200) {
                        if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                        layer.msg('提现 ' + res.data.amount + '，实到 ' + res.data.net, { time: 1200 });
                        // 重载页（余额、记录同步）
                        setTimeout(function () { location.reload(); }, 1200);
                    } else {
                        layer.msg(res.msg || '提现失败');
                        $btn.prop('disabled', false).html(origHtml);
                    }
                },
                error: function () {
                    layer.msg('网络异常');
                    $btn.prop('disabled', false).html(origHtml);
                }
            });
        });

        table.render({
            elem: '#mcWdTable',
            id: 'mcWdTableId',
            url: '/user/merchant/withdraw.php',
            method: 'POST',
            where: { _action: 'list', csrf_token: csrfToken },
            page: true,
            limit: 10,
            limits: [10, 20, 50],
            lineStyle: 'height: 56px;',
            cols: [[
                { title: '申请金额', width: 160, templet: '#mcWdAmountTpl', align: 'right' },
                { title: '实到账',   width: 130, templet: '#mcWdNetTpl',    align: 'right' },
                { title: '状态',     width: 110, templet: '#mcWdStatusTpl', align: 'center' },
                { title: '申请时间', minWidth: 160, templet: '#mcWdTimeTpl', align: 'center' },
                { field: 'audited_at', title: '到账时间', minWidth: 160, align: 'center' }
            ]],
            parseData: function (res) {
                if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                return {
                    code: res.code === 200 ? 0 : res.code,
                    msg: res.msg,
                    data: res.data ? res.data.data : [],
                    count: res.data ? res.data.total : 0
                };
            }
        });
    });
});
</script>
