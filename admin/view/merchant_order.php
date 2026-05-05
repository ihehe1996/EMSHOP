<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
?>
<div class="admin-page">
    <h1 class="admin-page__title">分站订单</h1>
    <p class="admin-page__desc" style="margin:-8px 0 16px;color:#6b7280;font-size:13px;">
        所有商户站产生的订单，主站后台只读监控；发货 / 退款由商户在自己的店铺后台操作。
    </p>

    <!-- 顶部数据卡 -->
    <div class="mo-overview">
        <div class="mo-stat mo-stat--count">
            <div class="mo-stat__icon"><i class="fa fa-list-ol"></i></div>
            <div class="mo-stat__body">
                <div class="mo-stat__label">今日成交订单</div>
                <div class="mo-stat__value"><span id="moTodayCount">—</span> <span class="mo-stat__unit">笔</span></div>
            </div>
        </div>
        <div class="mo-stat mo-stat--today">
            <div class="mo-stat__icon"><i class="fa fa-calendar"></i></div>
            <div class="mo-stat__body">
                <div class="mo-stat__label">今日成交金额</div>
                <div class="mo-stat__value">¥<span id="moTodayAmount">—</span></div>
            </div>
        </div>
        <div class="mo-stat mo-stat--month">
            <div class="mo-stat__icon"><i class="fa fa-calendar-check-o"></i></div>
            <div class="mo-stat__body">
                <div class="mo-stat__label">本月成交金额</div>
                <div class="mo-stat__value">¥<span id="moMonthAmount">—</span></div>
            </div>
        </div>
        <div class="mo-stat mo-stat--total">
            <div class="mo-stat__icon"><i class="fa fa-trophy"></i></div>
            <div class="mo-stat__body">
                <div class="mo-stat__label">累计成交金额</div>
                <div class="mo-stat__value">¥<span id="moTotalAmount">—</span></div>
            </div>
        </div>
    </div>

    <!-- 状态切换 chips -->
    <div class="mo-status-tabs">
        <button type="button" class="mo-chip is-active" data-status="">
            <i class="fa fa-list"></i> 全部 <span class="mo-chip__cnt" data-key="all">0</span>
        </button>
        <button type="button" class="mo-chip" data-status="pending">
            <i class="fa fa-clock-o"></i> 待付款 <span class="mo-chip__cnt" data-key="pending">0</span>
        </button>
        <button type="button" class="mo-chip" data-status="paid">
            <i class="fa fa-check-circle"></i> 已付款 <span class="mo-chip__cnt" data-key="paid">0</span>
        </button>
        <button type="button" class="mo-chip" data-status="delivering">
            <i class="fa fa-paper-plane"></i> 发货中 <span class="mo-chip__cnt" data-key="delivering">0</span>
        </button>
        <button type="button" class="mo-chip" data-status="delivered">
            <i class="fa fa-truck"></i> 已发货 <span class="mo-chip__cnt" data-key="delivered">0</span>
        </button>
        <button type="button" class="mo-chip" data-status="completed">
            <i class="fa fa-flag-checkered"></i> 已完成 <span class="mo-chip__cnt" data-key="completed">0</span>
        </button>
        <button type="button" class="mo-chip" data-status="refunding">
            <i class="fa fa-undo"></i> 退款中 <span class="mo-chip__cnt" data-key="refunding">0</span>
        </button>
        <button type="button" class="mo-chip" data-status="refunded">
            <i class="fa fa-times-circle"></i> 已退款 <span class="mo-chip__cnt" data-key="refunded">0</span>
        </button>
        <button type="button" class="mo-chip" data-status="cancelled">
            <i class="fa fa-ban"></i> 已取消 <span class="mo-chip__cnt" data-key="cancelled">0</span>
        </button>
    </div>

    <!-- 工具条：商户筛选 + 关键字 -->
    <div class="mo-toolbar">
        <div class="mo-toolbar__field">
            <i class="fa fa-building"></i>
            <select id="moMerchantFilter" class="mo-input">
                <option value="0">所有商户</option>
            </select>
        </div>
        <div class="mo-toolbar__field mo-toolbar__field--grow">
            <i class="fa fa-search"></i>
            <input type="text" id="moKeyword" class="mo-input" placeholder="单号 / 用户名 / 昵称 / 商户名 / 商品名">
        </div>
        <button type="button" class="em-btn em-sm-btn em-save-btn" id="moSearchBtn"><i class="fa fa-search"></i>搜索</button>
        <button type="button" class="em-btn em-sm-btn em-reset-btn" id="moResetBtn"><i class="fa fa-rotate-left"></i>重置</button>
    </div>

    <table id="moTable" lay-filter="moTable"></table>
</div>

<style>
/* ============ 顶部数据卡 ============ */
.mo-overview {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}
@media (max-width: 1100px) { .mo-overview { grid-template-columns: repeat(2, 1fr); } }
.mo-stat {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 16px;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
    transition: border-color .15s, box-shadow .15s;
}
.mo-stat:hover { border-color: #d1d5db; box-shadow: 0 2px 6px rgba(0,0,0,0.04); }
.mo-stat__icon {
    width: 40px; height: 40px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}
.mo-stat--count .mo-stat__icon { background: #eef2ff; color: #4e6ef2; }
.mo-stat--today .mo-stat__icon { background: #f0fdf4; color: #16a34a; }
.mo-stat--month .mo-stat__icon { background: #fff7ed; color: #ea580c; }
.mo-stat--total .mo-stat__icon { background: #fef3c7; color: #a16207; }
.mo-stat__label { font-size: 11.5px; color: #9ca3af; }
.mo-stat__value { font-size: 20px; font-weight: 700; color: #1f2937; line-height: 1.2; margin-top: 2px; }
.mo-stat__unit  { font-size: 12px; color: #6b7280; font-weight: 400; }

/* ============ 状态切换 chips ============ */
.mo-status-tabs {
    display: flex; gap: 4px; margin-bottom: 12px; flex-wrap: wrap;
    padding: 6px; background: #fff;
    border: 1px solid #e5e7eb; border-radius: 8px;
}
.mo-chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 11px; font-size: 12.5px; color: #6b7280;
    background: transparent; border: 0; border-radius: 6px;
    cursor: pointer; transition: all .15s; white-space: nowrap;
}
.mo-chip:hover { background: #f5f7fa; color: #374151; }
.mo-chip.is-active { background: #eef2ff; color: #4e6ef2; font-weight: 500; }
.mo-chip i { font-size: 11px; }
.mo-chip__cnt {
    display: inline-block; min-width: 18px; padding: 0 5px;
    height: 17px; line-height: 17px; text-align: center;
    background: #e5e7eb; color: #6b7280;
    border-radius: 9px; font-size: 11px; font-weight: 500;
}
.mo-chip.is-active .mo-chip__cnt { background: #4e6ef2; color: #fff; }

/* ============ 工具条 ============ */
.mo-toolbar {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    padding: 12px 14px; margin-bottom: 14px;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
}
.mo-toolbar__field {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 0 10px; height: 32px;
    background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px;
    transition: border-color .15s, background .15s;
}
.mo-toolbar__field--grow { flex: 1; min-width: 240px; }
.mo-toolbar__field:focus-within { background: #fff; border-color: #4e6ef2; }
.mo-toolbar__field i { color: #9ca3af; font-size: 12px; }
.mo-input {
    border: 0; outline: none; background: transparent;
    height: 30px; font-size: 13px; color: #374151;
    flex: 1; min-width: 100px;
}
.mo-input::placeholder { color: #9ca3af; }
select.mo-input { cursor: pointer; padding-right: 4px; }

/* ============ 表格行内组件 ============ */
.mo-orderno {
    display: inline-block;
    padding: 1px 8px; font-size: 12px;
    background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 4px;
    color: #374151;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}
.mo-merchant-cell { line-height: 1.4; text-align: left; padding: 0 4px; }
.mo-merchant-cell__name { color: #1f2937; font-weight: 500; font-size: 13px; }
.mo-merchant-cell__id   { color: #9ca3af; font-size: 11px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
.mo-merchant-cell--empty { color: #9ca3af; font-size: 12px; }

.mo-user-cell { line-height: 1.4; text-align: left; padding: 0 4px; }
.mo-user-cell__name { color: #1f2937; font-weight: 500; font-size: 13px; }
.mo-user-cell__id   { color: #9ca3af; font-size: 11px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }

.mo-goods-cell { line-height: 1.5; }
.mo-goods-line { display: flex; align-items: center; gap: 6px; }
.mo-goods-line__cover {
    width: 28px; height: 28px; border-radius: 4px; object-fit: cover;
    background: #f3f4f6; border: 1px solid #f0f1f4; flex-shrink: 0;
}
.mo-goods-line__title {
    font-size: 12.5px; color: #1f2937;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    max-width: 220px;
}
.mo-goods-line__qty {
    flex-shrink: 0;
    font-size: 11px; color: #9ca3af; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}
.mo-goods-more { color: #9ca3af; font-size: 11px; padding-left: 34px; }

.mo-amount {
    font-size: 14px; font-weight: 700; color: #1f2937;
    letter-spacing: 0.3px;
}
.mo-amount--paid { color: #16a34a; }
.mo-amount--cancelled { color: #9ca3af; text-decoration: line-through; }

.mo-pay {
    display: inline-flex; align-items: center; gap: 5px; justify-content: center;
    padding: 2px 9px; font-size: 12px; font-weight: 500;
    background: #eef2ff; color: #4338ca; border-radius: 10px;
}
.mo-pay__icon { width: 16px; height: 16px; border-radius: 3px; object-fit: contain; background: #fff; }
.mo-pay--empty { background: #f3f4f6; color: #9ca3af; font-weight: 400; }

.mo-status {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 9px; font-size: 12px; font-weight: 500;
    border-radius: 11px;
}
.mo-status i { font-size: 10px; }
.mo-status--pending          { background: #fff7ed; color: #c2410c; }
.mo-status--paid             { background: #dbeafe; color: #1e40af; }
.mo-status--delivering       { background: #ede9fe; color: #5b21b6; }
.mo-status--delivered        { background: #cffafe; color: #155e75; }
.mo-status--completed        { background: #dcfce7; color: #166534; }
.mo-status--cancelled        { background: #f3f4f6; color: #6b7280; }
.mo-status--refunding        { background: #fef3c7; color: #92400e; }
.mo-status--refunded         { background: #f3f4f6; color: #4b5563; }
.mo-status--expired          { background: #f3f4f6; color: #6b7280; }
.mo-status--failed           { background: #fef2f2; color: #b91c1c; }
.mo-status--delivery_failed  { background: #fef2f2; color: #b91c1c; }

.mo-time {
    display: inline-flex; flex-direction: column; align-items: center; line-height: 1.3;
}
.mo-time__date { color: #374151; font-weight: 500; font-size: 12.5px; }
.mo-time__hms  { color: #9ca3af; font-size: 11.5px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
.mo-empty { color: #d1d5db; }
</style>

<!-- 行模板 -->
<script type="text/html" id="moOrderNoTpl">
    <span class="mo-orderno">{{ d.order_no }}</span>
</script>

<script type="text/html" id="moMerchantTpl">
    {{# if (d.merchant_name) { }}
    <div class="mo-merchant-cell">
        <div class="mo-merchant-cell__name">{{ d.merchant_name }}</div>
        <div class="mo-merchant-cell__id">#{{ d.merchant_id }}</div>
    </div>
    {{# } else { }}
    <span class="mo-merchant-cell--empty">商户 #{{ d.merchant_id }}（已删除）</span>
    {{# } }}
</script>

<script type="text/html" id="moUserTpl">
    {{# if (d.user_id > 0) { }}
    <div class="mo-user-cell">
        <div class="mo-user-cell__name">{{ d.nickname || d.username || '-' }}</div>
        <div class="mo-user-cell__id">#{{ d.user_id }}</div>
    </div>
    {{# } else { }}
    <span class="mo-empty">游客</span>
    {{# } }}
</script>

<script type="text/html" id="moGoodsTpl">
    <div class="mo-goods-cell">
    {{# if (d.goods && d.goods.length) {
        var maxShow = 2;
        for (var i = 0; i < d.goods.length && i < maxShow; i++) {
            var g = d.goods[i];
    }}
        <div class="mo-goods-line">
            <img class="mo-goods-line__cover" src="{{ g.cover || '/content/static/img/placeholder.png' }}" alt="">
            <span class="mo-goods-line__title">{{ g.title }}{{# if (g.spec) { }} <span style="color:#9ca3af;">/ {{ g.spec }}</span>{{# } }}</span>
            <span class="mo-goods-line__qty">×{{ g.quantity }}</span>
        </div>
    {{# }
        if (d.goods.length > maxShow) { }}
        <div class="mo-goods-more">还有 {{ d.goods.length - maxShow }} 件商品</div>
    {{# }
    } else { }}
        <span class="mo-empty">—</span>
    {{# } }}
    </div>
</script>

<script type="text/html" id="moAmountTpl">
    {{# var cls = 'mo-amount';
        if (d.status === 'paid' || d.status === 'delivering' || d.status === 'delivered' || d.status === 'completed') cls += ' mo-amount--paid';
        else if (d.status === 'cancelled' || d.status === 'expired' || d.status === 'failed') cls += ' mo-amount--cancelled'; }}
    <span class="{{ cls }}">¥{{ d.pay_amount_fmt }}</span>
</script>

<script type="text/html" id="moPaymentTpl">
    {{# if (d.payment_name) { }}
    <span class="mo-pay">
        {{# if (d.payment_image) { }}<img class="mo-pay__icon" src="{{ d.payment_image }}" alt="" onerror="this.style.display='none';">{{# } }}
        {{ d.payment_name }}
    </span>
    {{# } else { }}
    <span class="mo-pay mo-pay--empty">未支付</span>
    {{# } }}
</script>

<script type="text/html" id="moStatusTpl">
    {{# var labels = {pending:'待付款',paid:'已付款',delivering:'发货中',delivered:'已发货',completed:'已完成',
                      cancelled:'已取消',refunding:'退款中',refunded:'已退款',expired:'已过期',failed:'失败',delivery_failed:'发货失败'};
        var icons = {pending:'clock-o',paid:'check-circle',delivering:'paper-plane',delivered:'truck',completed:'flag-checkered',
                     cancelled:'ban',refunding:'undo',refunded:'times-circle',expired:'hourglass-end',failed:'exclamation-circle',delivery_failed:'exclamation-triangle'};
        var s = String(d.status || ''); }}
    <span class="mo-status mo-status--{{ s }}"><i class="fa fa-{{ icons[s] || 'circle' }}"></i> {{ labels[s] || s }}</span>
</script>

<script type="text/html" id="moTimeTpl">
    {{# if (d.created_at) {
        var dt = String(d.created_at).replace('T',' ').substring(0,19);
        var parts = dt.split(' ');
    }}
    <span class="mo-time">
        <span class="mo-time__date">{{ parts[0] }}</span>
        <span class="mo-time__hms">{{ parts[1] || '' }}</span>
    </span>
    {{# } else { }}<span class="mo-empty">—</span>{{# } }}
</script>

<script>
$(function () {
    'use strict';
    // PJAX 防重复绑定
    $(document).off('.admMerchantOrder');
    $(window).off('.admMerchantOrder');

    var csrfToken = <?= json_encode($csrfToken) ?>;
    var currentStatus     = '';
    var currentMerchantId = 0;
    var currentKeyword    = '';

    layui.use(['layer', 'table'], function () {
        var layer = layui.layer, table = layui.table;

        function buildWhere() {
            return {
                _action: 'list',
                status: currentStatus,
                merchant_id: currentMerchantId,
                keyword: currentKeyword
            };
        }
        function refreshTable() {
            table.reload('moTableId', { page: { curr: 1 }, where: buildWhere() });
        }

        table.render({
            elem: '#moTable',
            id: 'moTableId',
            url: '/admin/merchant_order.php',
            method: 'POST',
            where: buildWhere(),
            page: true,
            limit: 15,
            limits: [10, 15, 30, 50, 100],
            cellMinWidth: 80,
            lineStyle: 'height: 64px;',
            cols: [[
                { field: 'id',           title: 'ID',     width: 70,  align: 'center' },
                { field: 'order_no',     title: '订单号', width: 200, align: 'center', templet: '#moOrderNoTpl' },
                { field: 'merchant_id',  title: '所属商户', width: 160, templet: '#moMerchantTpl' },
                { field: 'user_id',      title: '下单用户', width: 140, templet: '#moUserTpl' },
                { field: 'goods',        title: '商品',   minWidth: 240, templet: '#moGoodsTpl' },
                { field: 'pay_amount',   title: '实付金额', width: 130, align: 'right',  templet: '#moAmountTpl' },
                { field: 'payment_name', title: '支付方式', width: 140, align: 'center', templet: '#moPaymentTpl' },
                { field: 'status',       title: '状态',   width: 110, align: 'center', templet: '#moStatusTpl' },
                { field: 'created_at',   title: '下单时间', width: 150, align: 'center', templet: '#moTimeTpl' }
            ]],
            parseData: function (res) {
                if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                if (res.data && res.data.status_counts) {
                    var c = res.data.status_counts;
                    $('.mo-chip__cnt').each(function () {
                        var key = $(this).data('key');
                        $(this).text(c[key] || 0);
                    });
                }
                return {
                    code: res.code === 200 ? 0 : res.code,
                    msg: res.msg,
                    data: res.data ? res.data.data : [],
                    count: res.data ? res.data.total : 0
                };
            }
        });

        // chips 切换状态
        $(document).on('click.admMerchantOrder', '.mo-chip', function () {
            $('.mo-chip').removeClass('is-active');
            $(this).addClass('is-active');
            currentStatus = String($(this).data('status') || '');
            refreshTable();
        });

        // 商户筛选
        $(document).on('change.admMerchantOrder', '#moMerchantFilter', function () {
            currentMerchantId = parseInt($(this).val(), 10) || 0;
            refreshTable();
        });

        // 关键字搜索 / 重置
        $(document).on('click.admMerchantOrder', '#moSearchBtn', function () {
            currentKeyword = $.trim($('#moKeyword').val());
            refreshTable();
        });
        $(document).on('keypress.admMerchantOrder', '#moKeyword', function (e) {
            if (e.which === 13) $('#moSearchBtn').click();
        });
        $(document).on('click.admMerchantOrder', '#moResetBtn', function () {
            $('#moKeyword').val('');
            $('#moMerchantFilter').val('0');
            currentKeyword = '';
            currentMerchantId = 0;
            currentStatus = '';
            $('.mo-chip').removeClass('is-active');
            $('.mo-chip[data-status=""]').addClass('is-active');
            refreshTable();
        });

        // 顶部数据卡
        function loadSummary() {
            $.post('/admin/merchant_order.php', { _action: 'summary' }, function (res) {
                if (res.code !== 200 || !res.data || !res.data.data) return;
                var d = res.data.data;
                $('#moTodayCount').text(d.today_count || 0);
                $('#moTodayAmount').text(d.today_amount || '0.00');
                $('#moMonthAmount').text(d.month_amount || '0.00');
                $('#moTotalAmount').text(d.total_amount || '0.00');
            }, 'json');
        }
        loadSummary();

        // 商户下拉数据
        function loadMerchants() {
            $.post('/admin/merchant_order.php', { _action: 'merchants' }, function (res) {
                if (res.code !== 200 || !res.data || !res.data.data) return;
                var $sel = $('#moMerchantFilter');
                var html = '<option value="0">所有商户</option>';
                res.data.data.forEach(function (m) {
                    var name = m.name || ('商户 #' + m.id);
                    html += '<option value="' + m.id + '">' + $('<span>').text(name).html() + '</option>';
                });
                $sel.html(html);
            }, 'json');
        }
        loadMerchants();
    });
});
</script>
