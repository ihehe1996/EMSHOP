<?php
/** @var array<string, mixed> $frontUser */
/** @var array<string, mixed> $currentMerchant */
/** @var array<string, mixed> $uc */
$csrfToken = Csrf::token();
?>
<div class="mc-page">
    <div class="mc-page-header">
        <h2 class="mc-page-title">订单管理</h2>
        <p class="mc-page-desc">本店订单的发货、详情查看；金额按买家下单时的快照币种展示，不受访客切币种影响</p>
    </div>

    <!-- 状态切换 tabs -->
    <div class="mco-tabs" id="mcoTabs">
        <a href="javascript:;" class="mco-tab is-active" data-status="all"><i class="fa fa-th-large"></i> 全部</a>
        <a href="javascript:;" class="mco-tab" data-status="pending"><i class="fa fa-clock-o"></i> 待付款</a>
        <a href="javascript:;" class="mco-tab" data-status="paid"><i class="fa fa-cube"></i> 待发货</a>
        <a href="javascript:;" class="mco-tab" data-status="delivered"><i class="fa fa-truck"></i> 待收货</a>
        <a href="javascript:;" class="mco-tab" data-status="completed"><i class="fa fa-check-circle"></i> 已完成</a>
        <a href="javascript:;" class="mco-tab" data-status="refunded"><i class="fa fa-undo"></i> 已退款</a>
        <a href="javascript:;" class="mco-tab" data-status="cancelled"><i class="fa fa-times-circle"></i> 已取消</a>
    </div>

    <div class="mc-section" style="padding:0;overflow:hidden;">
        <div class="mco-toolbar">
            <div class="mco-toolbar__field">
                <i class="fa fa-search"></i>
                <input type="text" id="mcOrderKeyword" class="mco-input" placeholder="订单号 / 联系信息">
            </div>
            <button type="button" class="mc-btn mc-btn--primary" id="mcOrderSearchBtn"><i class="fa fa-search"></i> 搜索</button>
            <button type="button" class="mc-btn" id="mcOrderResetBtn"><i class="fa fa-rotate-left"></i> 重置</button>
        </div>

        <table id="mcOrderTable" lay-filter="mcOrderTable"></table>
    </div>
</div>

<style>
/* tabs */
.mco-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:14px; }
.mco-tab {
    display:inline-flex; align-items:center; gap:6px;
    padding:8px 16px; border-radius:8px; font-size:13px;
    color:#6b7280; background:#fff; transition:all .15s;
    border: 1px solid #e5e7eb;
}
.mco-tab:hover { background:#f5f7fa; color:#1f2937; }
.mco-tab.is-active { background:#eef2ff; color:#4e6ef2; border-color:#c7d2fe; font-weight:500; }
.mco-tab i { font-size:12px; }
.mco-tab__cnt {
    display:inline-block; min-width:18px; padding:0 6px;
    margin-left:2px; height:17px; line-height:17px; text-align:center;
    background:#fee2e2; color:#dc2626; border-radius:9px;
    font-size:11px; font-weight:500;
}
.mco-tab.is-active .mco-tab__cnt { background:#4e6ef2; color:#fff; }

/* toolbar */
.mco-toolbar {
    display:flex; align-items:center; gap:8px; flex-wrap:wrap;
    padding:14px 16px; background:#fafbfc;
    border-bottom:1px solid #f0f1f4;
}
.mco-toolbar__field {
    display:inline-flex; align-items:center; gap:6px;
    padding:0 10px; height:32px; flex:1; max-width:340px;
    background:#fff; border:1px solid #e5e7eb; border-radius:6px;
    transition:border-color .15s;
}
.mco-toolbar__field:focus-within { border-color:#4e6ef2; }
.mco-toolbar__field i { color:#9ca3af; font-size:12px; }
.mco-input { border:0; outline:none; background:transparent; flex:1; font-size:13px; color:#374151; min-width:120px; }
.mco-input::placeholder { color:#9ca3af; }

/* 行内组件 */
.mco-orderno {
    display:inline-block;
    padding:1px 8px; font-size:12px;
    background:#f3f4f6; border:1px solid #e5e7eb; border-radius:4px;
    color:#374151;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}
.mco-orderno__sub { color:#9ca3af; font-size:11px; margin-top:3px; }
.mco-goods { display:flex; align-items:center; gap:8px; line-height:1.4; }
.mco-goods__cover { width:32px; height:32px; border-radius:4px; object-fit:cover; background:#f1f5f9; flex-shrink:0; }
.mco-goods__info { flex:1; min-width:0; }
.mco-goods__title { font-size:12.5px; color:#1f2937; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.mco-goods__title__more { display:inline-block; margin-left:4px; padding:0 6px; font-size:11px; background:#f3f4f6; color:#6b7280; border-radius:9px; }
.mco-goods__sub { font-size:11.5px; color:#9ca3af; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

.mco-buyer { font-size:12.5px; color:#1f2937; }
.mco-buyer--guest { color:#9ca3af; font-style:italic; }

.mco-amount { line-height:1.4; text-align:right; }
.mco-amount__pay { color:#1f2937; font-weight:600; font-size:13px; }
.mco-amount__sub { font-size:11px; color:#9ca3af; }
.mco-amount__net { color:#16a34a; font-weight:700; font-size:13px; }

.mco-pay {
    display:inline-flex; align-items:center; gap:4px;
    padding:1px 8px; font-size:11.5px; font-weight:500;
    background:#eef2ff; color:#4338ca; border-radius:10px;
}
.mco-pay--empty { background:#f3f4f6; color:#9ca3af; font-weight:400; }

.mco-status {
    display:inline-flex; align-items:center; gap:4px;
    padding:2px 9px; font-size:12px; font-weight:500; border-radius:11px;
}
.mco-status i { font-size:10px; }
.mco-status--pending          { background:#fff7ed; color:#c2410c; }
.mco-status--paid             { background:#dbeafe; color:#1e40af; }
.mco-status--delivering       { background:#ede9fe; color:#5b21b6; }
.mco-status--delivered        { background:#cffafe; color:#155e75; }
.mco-status--completed        { background:#dcfce7; color:#166534; }
.mco-status--cancelled        { background:#f3f4f6; color:#6b7280; }
.mco-status--refunding        { background:#fef3c7; color:#92400e; }
.mco-status--refunded         { background:#f3f4f6; color:#4b5563; }
.mco-status--expired          { background:#f3f4f6; color:#6b7280; }
.mco-status--failed,
.mco-status--delivery_failed  { background:#fef2f2; color:#b91c1c; }

.mco-time {
    display:inline-flex; flex-direction:column; align-items:center; line-height:1.3;
}
.mco-time__date { color:#374151; font-weight:500; font-size:12.5px; }
.mco-time__hms  { color:#9ca3af; font-size:11.5px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
.mco-empty { color:#d1d5db; }

.mco-actions { display:flex; gap:4px; justify-content:center; }
.mco-act-btn {
    display:inline-flex; align-items:center; gap:3px;
    padding:3px 10px; font-size:12px;
    border:1px solid #e5e7eb; border-radius:5px;
    background:#fff; color:#4b5563; cursor:pointer; transition:all .15s;
}
.mco-act-btn:hover { border-color:#4e6ef2; color:#4e6ef2; background:#f5f7ff; }
.mco-act-btn--primary { background:#4e6ef2; color:#fff; border-color:#4e6ef2; }
.mco-act-btn--primary:hover { background:#3c58d9; color:#fff; border-color:#3c58d9; }
</style>

<script type="text/html" id="mcoOrderTpl">
    <div>
        <div><span class="mco-orderno">{{ d.order_no }}</span></div>
        <div class="mco-orderno__sub">{{ d.items_count }} 件商品{{# if (d.pending_ship_count > 0) { }} · <span style="color:#c2410c;">{{ d.pending_ship_count }} 待发</span>{{# } }}</div>
    </div>
</script>

<script type="text/html" id="mcoGoodsTpl">
    {{# if (d.goods_count > 0) { var first = d.goods[0]; }}
    <div class="mco-goods">
        <img class="mco-goods__cover" src="{{ first.cover || '/content/static/img/placeholder.png' }}" alt="" onerror="this.style.visibility='hidden';">
        <div class="mco-goods__info">
            <div class="mco-goods__title">
                {{ first.title }}
                {{# if (d.goods_count > 1) { }}<span class="mco-goods__title__more">+{{ d.goods_count - 1 }}</span>{{# } }}
            </div>
            <div class="mco-goods__sub">{{# if (first.spec) { }}{{ first.spec }} · {{# } }}× {{ first.quantity }}</div>
        </div>
    </div>
    {{# } else { }}
    <span class="mco-empty">—</span>
    {{# } }}
</script>

<script type="text/html" id="mcoBuyerTpl">
    {{# if (d.is_guest) { }}
    <span class="mco-buyer mco-buyer--guest">{{ d.buyer_label }}</span>
    {{# } else { }}
    <span class="mco-buyer">{{ d.buyer_label }}</span>
    {{# } }}
</script>

<script type="text/html" id="mcoAmountTpl">
    <div class="mco-amount">
        <div class="mco-amount__pay">{{ d.pay_amount_view }}</div>
        <div class="mco-amount__sub">成本 {{ d.total_cost_view }}{{# if (parseInt(d.total_fee) > 0) { }} · 手续费 {{ d.total_fee_view }}{{# } }}</div>
    </div>
</script>

<script type="text/html" id="mcoNetTpl">
    <span class="mco-amount__net">{{ d.net_income_view }}</span>
</script>

<script type="text/html" id="mcoPayTpl">
    {{# if (d.payment_name) { }}
    <span class="mco-pay"><i class="fa fa-credit-card"></i> {{ d.payment_name }}</span>
    {{# } else { }}
    <span class="mco-pay mco-pay--empty">未支付</span>
    {{# } }}
</script>

<script type="text/html" id="mcoStatusTpl">
    {{# var icons = {pending:'clock-o',paid:'check-circle',delivering:'paper-plane',delivered:'truck',completed:'flag-checkered',
                    cancelled:'ban',refunding:'undo',refunded:'times-circle',expired:'hourglass-end',failed:'exclamation-circle',delivery_failed:'exclamation-triangle'};
        var s = String(d.status || ''); }}
    <span class="mco-status mco-status--{{ s }}"><i class="fa fa-{{ icons[s] || 'circle' }}"></i> {{ d.status_name }}</span>
</script>

<script type="text/html" id="mcoTimeTpl">
    {{# if (d.created_at) {
        var dt = String(d.created_at).replace('T',' ').substring(0,19);
        var parts = dt.split(' ');
    }}
    <span class="mco-time">
        <span class="mco-time__date">{{ parts[0] }}</span>
        <span class="mco-time__hms">{{ parts[1] || '' }}</span>
    </span>
    {{# } else { }}<span class="mco-empty">—</span>{{# } }}
</script>

<script type="text/html" id="mcoActionTpl">
    <div class="mco-actions">
        {{# if (d.can_ship) { }}
        <button type="button" class="mco-act-btn mco-act-btn--primary" lay-event="ship"><i class="fa fa-paper-plane"></i> 发货</button>
        {{# } }}
        <button type="button" class="mco-act-btn" lay-event="detail"><i class="fa fa-eye"></i> 详情</button>
    </div>
</script>

<script>
$(function(){
    'use strict';
    // PJAX 防重复绑定
    $(document).off('.mcOrderPage');
    $(window).off('.mcOrderPage');

    var csrfToken = <?php echo json_encode($csrfToken); ?>;
    window.updateCsrf = function (t) { if (t) csrfToken = t; };

    layui.use(['layer', 'form', 'table'], function () {
        var layer = layui.layer, form = layui.form, table = layui.table;

        var currentStatus = 'all';

        function buildWhere() {
            return {
                _action: 'list',
                status: currentStatus,
                keyword: $('#mcOrderKeyword').val() || ''
            };
        }
        function refreshTable() {
            table.reload('mcOrderTableId', { page: { curr: 1 }, where: buildWhere() });
        }

        function applyTabCounts(counts) {
            if (!counts) return;
            // 待发货 = paid + delivering 合并；已退款 = refunded + refunding 合并
            var merged = {
                all: counts.all || 0,
                pending: counts.pending || 0,
                paid: (counts.paid || 0) + (counts.delivering || 0),
                delivered: counts.delivered || 0,
                completed: counts.completed || 0,
                refunded: (counts.refunded || 0) + (counts.refunding || 0),
                cancelled: counts.cancelled || 0
            };
            $('#mcoTabs .mco-tab').each(function () {
                var $t = $(this);
                var st = $t.data('status');
                var c = merged[st] != null ? merged[st] : 0;
                $t.find('.mco-tab__cnt').remove();
                if (c > 0) $t.append(' <span class="mco-tab__cnt">' + c + '</span>');
            });
        }

        table.render({
            elem: '#mcOrderTable',
            id: 'mcOrderTableId',
            url: '/user/merchant/order.php',
            method: 'POST',
            where: buildWhere(),
            page: true,
            limit: 20,
            limits: [10, 20, 50, 100],
            cellMinWidth: 80,
            lineStyle: 'height: 64px;',
            cols: [[
                { title: '订单',     width: 200, templet: '#mcoOrderTpl' },
                { title: '商品',     minWidth: 240, templet: '#mcoGoodsTpl' },
                { title: '买家',     width: 120, align: 'center', templet: '#mcoBuyerTpl' },
                { title: '实付 / 成本', width: 160, align: 'right', templet: '#mcoAmountTpl' },
                { title: '净收入',   width: 110, align: 'right', templet: '#mcoNetTpl' },
                { title: '支付方式', width: 130, align: 'center', templet: '#mcoPayTpl' },
                { title: '状态',     width: 110, align: 'center', templet: '#mcoStatusTpl' },
                { title: '下单时间', width: 130, align: 'center', templet: '#mcoTimeTpl' },
                { title: '操作',     width: 170, align: 'center', templet: '#mcoActionTpl' }
            ]],
            parseData: function (res) {
                if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                if (res.data && res.data.tab_counts) applyTabCounts(res.data.tab_counts);
                return {
                    'code': res.code === 200 ? 0 : res.code,
                    'msg': res.msg,
                    'data': res.data ? res.data.data : [],
                    'count': res.data ? res.data.total : 0
                };
            }
        });

        // tabs
        $(document).on('click.mcOrderPage', '#mcoTabs .mco-tab', function () {
            $('#mcoTabs .mco-tab').removeClass('is-active');
            $(this).addClass('is-active');
            currentStatus = $(this).data('status');
            refreshTable();
        });
        // 搜索 / 重置
        $(document).on('click.mcOrderPage', '#mcOrderSearchBtn', function () {
            refreshTable();
        });
        $(document).on('keypress.mcOrderPage', '#mcOrderKeyword', function (e) {
            if (e.which === 13) refreshTable();
        });
        $(document).on('click.mcOrderPage', '#mcOrderResetBtn', function () {
            $('#mcOrderKeyword').val('');
            currentStatus = 'all';
            $('#mcoTabs .mco-tab').removeClass('is-active');
            $('#mcoTabs .mco-tab[data-status="all"]').addClass('is-active');
            refreshTable();
        });

        // 行内事件：详情 / 发货
        table.on('tool(mcOrderTable)', function (obj) {
            var d = obj.data;
            if (obj.event === 'detail') {
                openDetail(d);
            } else if (obj.event === 'ship') {
                openShip(d);
            }
        });

        function openDetail(d) {
            layer.open({
                type: 2,
                title: '订单详情 - ' + d.order_no,
                skin: 'admin-modal',
                maxmin: true,
                shadeClose: true,
                area: [window.innerWidth >= 1100 ? '960px' : '95%', window.innerHeight >= 760 ? '720px' : '92%'],
                content: '/user/merchant/order.php?_popup=detail&id=' + encodeURIComponent(d.id)
            });
        }

        function openShip(d) {
            // 标志位让 popup 在发货成功后通知父窗口刷新
            window._orderShipSuccess = false;
            var idx = layer.open({
                type: 2,
                title: '发货 - ' + d.order_no,
                skin: 'admin-modal',
                maxmin: true,
                shadeClose: false,
                area: [window.innerWidth >= 900 ? '780px' : '95%', window.innerHeight >= 700 ? '640px' : '92%'],
                content: '/user/merchant/order.php?_popup=ship&id=' + encodeURIComponent(d.id),
                end: function () {
                    if (window._orderShipSuccess) {
                        window._orderShipSuccess = false;
                        refreshTable();
                    }
                }
            });
        }
    });
});
</script>
