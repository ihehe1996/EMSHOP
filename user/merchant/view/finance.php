<?php
/** @var array<string, mixed> $frontUser */
/** @var array<string, mixed> $currentMerchant */
/** @var array<string, mixed> $uc */

$csrfToken = Csrf::token();
?>
<div class="mc-page">
    <div class="mc-page-header">
        <h2 class="mc-page-title">余额明细</h2>
        <p class="mc-page-desc">店铺余额的所有变动流水，按类型 / 月份筛选</p>
    </div>

    <!-- 顶部数据卡：店铺余额 + 当月统计 -->
    <div class="fin-overview">
        <div class="fin-balance-card">
            <div class="fin-balance-card__bg"></div>
            <div class="fin-balance-card__inner">
                <div class="fin-balance-card__label">当前店铺余额</div>
                <div class="fin-balance-card__value"><?= htmlspecialchars((string) $uc['shopBalance']) ?></div>
                <a href="/user/merchant/withdraw.php" data-pjax="#merchantContent" class="fin-balance-card__cta">
                    <i class="fa fa-credit-card"></i> 立即提现
                </a>
            </div>
        </div>
        <div class="fin-stat-grid">
            <div class="fin-stat fin-stat--income">
                <div class="fin-stat__icon"><i class="fa fa-arrow-up"></i></div>
                <div class="fin-stat__body">
                    <div class="fin-stat__label">本月进账</div>
                    <div class="fin-stat__value" id="mcFinSumIncrease">—</div>
                </div>
            </div>
            <div class="fin-stat fin-stat--refund">
                <div class="fin-stat__icon"><i class="fa fa-arrow-down"></i></div>
                <div class="fin-stat__body">
                    <div class="fin-stat__label">本月退款</div>
                    <div class="fin-stat__value" id="mcFinSumRefund">—</div>
                </div>
            </div>
            <div class="fin-stat fin-stat--withdraw">
                <div class="fin-stat__icon"><i class="fa fa-credit-card"></i></div>
                <div class="fin-stat__body">
                    <div class="fin-stat__label">本月提现</div>
                    <div class="fin-stat__value" id="mcFinSumWithdraw">—</div>
                </div>
            </div>
            <div class="fin-stat fin-stat--rebate">
                <div class="fin-stat__icon"><i class="fa fa-share-alt"></i></div>
                <div class="fin-stat__body">
                    <div class="fin-stat__label">本月子商返佣</div>
                    <div class="fin-stat__value" id="mcFinSumSubRebate">—</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 流水列表 -->
    <div class="mc-section" style="padding:0;overflow:hidden;">
        <!-- 工具条 -->
        <div class="fin-toolbar">
            <div class="fin-chip-tabs">
                <button type="button" class="fin-chip is-active" data-type=""><i class="fa fa-list"></i> 全部</button>
                <button type="button" class="fin-chip" data-type="increase"><i class="fa fa-plus"></i> 进账</button>
                <button type="button" class="fin-chip" data-type="refund"><i class="fa fa-undo"></i> 退款</button>
                <button type="button" class="fin-chip" data-type="withdraw"><i class="fa fa-credit-card"></i> 提现</button>
                <button type="button" class="fin-chip" data-type="withdraw_fee"><i class="fa fa-percent"></i> 手续费</button>
                <button type="button" class="fin-chip" data-type="sub_rebate"><i class="fa fa-share-alt"></i> 子商返佣</button>
                <button type="button" class="fin-chip" data-type="adjust"><i class="fa fa-wrench"></i> 人工调整</button>
            </div>
            <div class="fin-toolbar__right">
                <div class="fin-month-input">
                    <i class="fa fa-calendar"></i>
                    <input type="text" id="mcFinMonth" placeholder="选择月份" autocomplete="off">
                    <button type="button" class="fin-month-clear" id="mcFinMonthClear" title="清除月份"><i class="fa fa-times"></i></button>
                </div>
            </div>
        </div>

        <table id="mcFinTable" lay-filter="mcFinTable"></table>
    </div>
</div>

<style>
/* 顶部数据卡：左大右小 */
.fin-overview {
    display: grid;
    grid-template-columns: minmax(280px, 360px) 1fr;
    gap: 14px;
    margin-bottom: 18px;
}
@media (max-width: 900px) { .fin-overview { grid-template-columns: 1fr; } }

.fin-balance-card {
    position: relative;
    border-radius: 10px;
    overflow: hidden;
    color: #fff;
    min-height: 140px;
    box-shadow: 0 2px 8px rgba(78, 110, 242, 0.18);
}
.fin-balance-card__bg {
    position: absolute; inset: 0;
    background: linear-gradient(135deg, #4e6ef2 0%, #2563eb 60%, #1d4ed8 100%);
}
.fin-balance-card__bg::after {
    content: ''; position: absolute; right: -40px; top: -40px;
    width: 180px; height: 180px; border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
}
.fin-balance-card__bg::before {
    content: ''; position: absolute; left: -30px; bottom: -50px;
    width: 140px; height: 140px; border-radius: 50%;
    background: rgba(255, 255, 255, 0.05);
}
.fin-balance-card__inner {
    position: relative; padding: 18px 20px; height: 100%;
    display: flex; flex-direction: column; justify-content: space-between;
}
.fin-balance-card__label { font-size: 12px; opacity: 0.85; letter-spacing: 0.5px; }
.fin-balance-card__value {
    font-size: 28px; font-weight: 700; letter-spacing: 0.5px; margin-top: 6px;
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}
.fin-balance-card__cta {
    align-self: flex-start; margin-top: 12px;
    padding: 5px 14px; font-size: 12px; color: #fff;
    background: rgba(255, 255, 255, 0.18); border-radius: 16px;
    text-decoration: none; transition: background 0.15s;
}
.fin-balance-card__cta:hover { background: rgba(255, 255, 255, 0.28); color: #fff; }
.fin-balance-card__cta i { margin-right: 4px; }

/* 当月统计：紧凑 2x2 */
.fin-stat-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
}
.fin-stat {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px; background: #fff;
    border: 1px solid #e5e7eb; border-radius: 8px;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.fin-stat:hover { border-color: #d1d5db; box-shadow: 0 2px 6px rgba(0,0,0,0.04); }
.fin-stat__icon {
    width: 36px; height: 36px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
}
.fin-stat--income   .fin-stat__icon { background: #f0fdf4; color: #16a34a; }
.fin-stat--refund   .fin-stat__icon { background: #fef2f2; color: #dc2626; }
.fin-stat--withdraw .fin-stat__icon { background: #fff7ed; color: #ea580c; }
.fin-stat--rebate   .fin-stat__icon { background: #ecfeff; color: #0891b2; }
.fin-stat__label { font-size: 11px; color: #9ca3af; }
.fin-stat__value { font-size: 18px; font-weight: 600; color: #1f2937; line-height: 1.3; margin-top: 2px; }

/* 工具条：chip + 月份 */
.fin-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 16px; background: #fafbfc;
    border-bottom: 1px solid #f0f1f4; gap: 12px; flex-wrap: wrap;
}
.fin-chip-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
.fin-chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 12px; font-size: 12px; color: #6b7280;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 14px;
    cursor: pointer; transition: all 0.15s;
}
.fin-chip:hover { border-color: #4e6ef2; color: #4e6ef2; }
.fin-chip.is-active { background: #4e6ef2; border-color: #4e6ef2; color: #fff; }
.fin-chip i { font-size: 11px; }

.fin-month-input {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 0 10px; height: 30px;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 6px;
    transition: border-color 0.15s;
}
.fin-month-input:focus-within { border-color: #4e6ef2; }
.fin-month-input i.fa-calendar { color: #9ca3af; font-size: 12px; }
.fin-month-input input {
    border: 0; outline: none; background: transparent;
    width: 100px; font-size: 13px; color: #374151;
}
.fin-month-clear {
    border: 0; background: transparent; color: #d1d5db;
    cursor: pointer; padding: 0 2px; font-size: 11px;
    display: none;
}
.fin-month-input.has-value .fin-month-clear { display: inline-block; }
.fin-month-clear:hover { color: #6b7280; }

/* 表格行模板里的小标签和文字 */
.fin-type-tag {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 10px; border-radius: 11px; font-size: 12px; font-weight: 500;
}
.fin-type-tag i { font-size: 10px; }
.fin-type-tag--increase    { background: #dcfce7; color: #166534; }
.fin-type-tag--refund      { background: #fef2f2; color: #b91c1c; }
.fin-type-tag--withdraw    { background: #fff7ed; color: #c2410c; }
.fin-type-tag--withdraw_fee{ background: #fef3c7; color: #92400e; }
.fin-type-tag--sub_rebate  { background: #ecfeff; color: #0e7490; }
.fin-type-tag--decrease    { background: #f3f4f6; color: #6b7280; }
.fin-type-tag--adjust      { background: #e0e7ff; color: #4338ca; }

.fin-amount {
    font-size: 16px; font-weight: 700; letter-spacing: 0.3px;
}
.fin-amount--plus  { color: #16a34a; }
.fin-amount--minus { color: #dc2626; }

.fin-balance-cell {
    line-height: 1.45; text-align: right; font-size: 12px;
}
.fin-balance-cell__after  { color: #1f2937; font-weight: 500; }
.fin-balance-cell__before { color: #9ca3af; font-size: 11px; }

.fin-order-no {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 11px; color: #4e6ef2;
    padding: 1px 6px; background: #eef2ff; border-radius: 3px;
}
.fin-order-empty { color: #d1d5db; font-size: 12px; }

.fin-remark { color: #4b5563; font-size: 13px; }
.fin-remark-empty { color: #d1d5db; }
.fin-time { color: #6b7280; font-size: 12px; }

/* 表格收紧 */
#mcFinTable + .layui-table-view .layui-table-body { background: #fff; }
</style>

<script type="text/html" id="mcFinTypeTpl">
    {{# var labels = {increase:'进账',refund:'退款',withdraw:'提现',withdraw_fee:'提现手续费',decrease:'减少',sub_rebate:'子商返佣',adjust:'人工调整'};
        var icons  = {increase:'plus',refund:'undo',withdraw:'credit-card',withdraw_fee:'percent',decrease:'minus',sub_rebate:'share-alt',adjust:'wrench'}; }}
    <span class="fin-type-tag fin-type-tag--{{ d.type }}"><i class="fa fa-{{ icons[d.type] || 'circle-o' }}"></i> {{ labels[d.type] || d.type }}</span>
</script>

<script type="text/html" id="mcFinAmountTpl">
    {{# if(d.direction === '+'){ }}
        <span class="fin-amount fin-amount--plus">+{{ d.amount_view }}</span>
    {{# } else { }}
        <span class="fin-amount fin-amount--minus">-{{ d.amount_view }}</span>
    {{# } }}
</script>

<script type="text/html" id="mcFinBalanceTpl">
    <div class="fin-balance-cell">
        <div class="fin-balance-cell__after">{{ d.after_view }}</div>
        <div class="fin-balance-cell__before">前 {{ d.before_view }}</div>
    </div>
</script>

<script type="text/html" id="mcFinOrderTpl">
    {{# if(d.order_id > 0){ }}
        <span class="fin-order-no">#{{ d.order_id }}</span>
    {{# } else { }}
        <span class="fin-order-empty">—</span>
    {{# } }}
</script>

<script type="text/html" id="mcFinRemarkTpl">
    {{# if(d.remark){ }}
        <span class="fin-remark">{{ d.remark }}</span>
    {{# } else { }}
        <span class="fin-remark-empty">—</span>
    {{# } }}
</script>

<script type="text/html" id="mcFinTimeTpl">
    <span class="fin-time">{{ d.created_at }}</span>
</script>

<script>
$(function () {
    'use strict';
    // PJAX 防重复绑定：清掉本页历史 .mcFinPage handler
    $(document).off('.mcFinPage');
    $(window).off('.mcFinPage');

    var csrfToken = <?php echo json_encode($csrfToken); ?>;

    layui.use(['layer', 'form', 'table', 'laydate'], function () {
        var layer = layui.layer;
        var table = layui.table;
        var laydate = layui.laydate;

        laydate.render({
            elem: '#mcFinMonth',
            type: 'month',
            format: 'yyyy-MM',
            done: function (val) {
                $('#mcFinMonth').closest('.fin-month-input').toggleClass('has-value', !!val);
                reload();
            }
        });

        function where() {
            var activeChip = $('.fin-chip.is-active').first();
            return {
                _action: 'list',
                type: activeChip.data('type') || '',
                month: $('#mcFinMonth').val() || ''
            };
        }
        function reload() {
            table.reload('mcFinTableId', { page: { curr: 1 }, where: where() });
        }

        table.render({
            elem: '#mcFinTable',
            id: 'mcFinTableId',
            url: '/user/merchant/finance.php',
            method: 'POST',
            where: where(),
            page: true,
            limit: 20,
            limits: [10, 20, 50, 100],
            lineStyle: 'height: 56px;',
            cols: [[
                { title: '类型',     width: 130, templet: '#mcFinTypeTpl',    align: 'center' },
                { title: '金额',     width: 150, templet: '#mcFinAmountTpl',  align: 'right' },
                { title: '余额变化', width: 160, templet: '#mcFinBalanceTpl', align: 'right' },
                { title: '关联订单', width: 110, templet: '#mcFinOrderTpl',   align: 'center' },
                { title: '备注',     minWidth: 220, templet: '#mcFinRemarkTpl', align: 'left' },
                { title: '时间',     width: 170, templet: '#mcFinTimeTpl',   align: 'center' }
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

        // chip 切换
        $(document).on('click.mcFinPage', '.fin-chip', function () {
            $('.fin-chip').removeClass('is-active');
            $(this).addClass('is-active');
            reload();
        });

        // 月份清除
        $(document).on('click.mcFinPage', '#mcFinMonthClear', function (e) {
            e.stopPropagation();
            $('#mcFinMonth').val('').closest('.fin-month-input').removeClass('has-value');
            reload();
        });

        // 加载本月汇总
        $.ajax({
            url: '/user/merchant/finance.php',
            type: 'POST',
            dataType: 'json',
            data: { _action: 'summary' },
            success: function (res) {
                if (res.code !== 200 || !res.data || !res.data.data) return;
                var s = res.data.data;
                $('#mcFinSumIncrease').text(s.increase || '—');
                $('#mcFinSumRefund').text(s.refund || '—');
                $('#mcFinSumWithdraw').text(s.withdraw || '—');
                $('#mcFinSumSubRebate').text(s.sub_rebate || '—');
            }
        });
    });
});
</script>
