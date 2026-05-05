<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
?>
<div class="admin-page">
    <h1 class="admin-page__title">充值订单</h1>

    <!-- 顶部数据卡 -->
    <div class="rch-overview">
        <div class="rch-stat rch-stat--count">
            <div class="rch-stat__icon"><i class="fa fa-list-ol"></i></div>
            <div class="rch-stat__body">
                <div class="rch-stat__label">今日成功充值</div>
                <div class="rch-stat__value"><span id="rchTodayCount"></span> <span class="rch-stat__unit">笔</span></div>
            </div>
        </div>
        <div class="rch-stat rch-stat--today">
            <div class="rch-stat__icon"><i class="fa fa-calendar"></i></div>
            <div class="rch-stat__body">
                <div class="rch-stat__label">今日金额</div>
                <div class="rch-stat__value">¥<span id="rchTodayAmount"></span></div>
            </div>
        </div>
        <div class="rch-stat rch-stat--month">
            <div class="rch-stat__icon"><i class="fa fa-calendar-check-o"></i></div>
            <div class="rch-stat__body">
                <div class="rch-stat__label">本月金额</div>
                <div class="rch-stat__value">¥<span id="rchMonthAmount"></span></div>
            </div>
        </div>
        <div class="rch-stat rch-stat--total">
            <div class="rch-stat__icon"><i class="fa fa-trophy"></i></div>
            <div class="rch-stat__body">
                <div class="rch-stat__label">累计已充值</div>
                <div class="rch-stat__value">¥<span id="rchTotalAmount"></span></div>
            </div>
        </div>
    </div>

    <!-- 状态切换 chips —— 全部 / 待支付 / 已充值 / 已取消，每个右侧带数字徽章 -->
    <div class="rch-status-tabs">
        <button type="button" class="rch-chip is-active" data-status="">
            <i class="fa fa-list"></i> 全部 <span class="rch-chip__cnt" data-key="all">0</span>
        </button>
        <button type="button" class="rch-chip" data-status="pending">
            <i class="fa fa-clock-o"></i> 待支付 <span class="rch-chip__cnt" data-key="pending">0</span>
        </button>
        <button type="button" class="rch-chip" data-status="paid">
            <i class="fa fa-check-circle"></i> 已充值 <span class="rch-chip__cnt" data-key="paid">0</span>
        </button>
        <button type="button" class="rch-chip" data-status="cancelled">
            <i class="fa fa-ban"></i> 已取消 <span class="rch-chip__cnt" data-key="cancelled">0</span>
        </button>
    </div>

    <!-- 工具条：搜索 -->
    <div class="rch-toolbar">
        <div class="rch-toolbar__field">
            <i class="fa fa-search"></i>
            <input type="text" id="rchKeyword" class="rch-input" placeholder="单号 / 用户名 / 昵称">
        </div>
        <button type="button" class="em-btn em-sm-btn em-save-btn" id="rchSearchBtn"><i class="fa fa-search"></i>搜索</button>
        <button type="button" class="em-btn em-sm-btn em-reset-btn" id="rchResetBtn"><i class="fa fa-rotate-left"></i>重置</button>
    </div>

    <table id="rechargeTable" lay-filter="rechargeTable"></table>
</div>

<style>
/* ============ 顶部数据卡 ============ */
.rch-overview {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}
@media (max-width: 1100px) { .rch-overview { grid-template-columns: repeat(2, 1fr); } }
.rch-stat {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 16px;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
    transition: border-color .15s, box-shadow .15s;
}
.rch-stat:hover { border-color: #d1d5db; box-shadow: 0 2px 6px rgba(0,0,0,0.04); }
.rch-stat__icon {
    width: 40px; height: 40px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}
.rch-stat--count   .rch-stat__icon { background: #eef2ff; color: #4e6ef2; }
.rch-stat--today   .rch-stat__icon { background: #f0fdf4; color: #16a34a; }
.rch-stat--month   .rch-stat__icon { background: #fff7ed; color: #ea580c; }
.rch-stat--total   .rch-stat__icon { background: #fef3c7; color: #a16207; }
.rch-stat__label { font-size: 11.5px; color: #9ca3af; }
.rch-stat__value { font-size: 20px; font-weight: 700; color: #1f2937; line-height: 1.2; margin-top: 2px; }
.rch-stat__unit  { font-size: 12px; color: #6b7280; font-weight: 400; }

/* ============ 状态切换 chips ============ */
.rch-status-tabs {
    display: flex; gap: 6px; margin-bottom: 12px; flex-wrap: wrap;
    padding: 6px; background: #fff;
    border: 1px solid #e5e7eb; border-radius: 8px;
}
.rch-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; font-size: 13px; color: #6b7280;
    background: transparent; border: 0; border-radius: 6px;
    cursor: pointer; transition: all .15s;
}
.rch-chip:hover { background: #f5f7fa; color: #374151; }
.rch-chip.is-active { background: #eef2ff; color: #4e6ef2; font-weight: 500; }
.rch-chip i { font-size: 12px; }
.rch-chip__cnt {
    display: inline-block; min-width: 20px; padding: 0 6px;
    height: 18px; line-height: 18px; text-align: center;
    background: #e5e7eb; color: #6b7280;
    border-radius: 9px; font-size: 11px; font-weight: 500;
}
.rch-chip.is-active .rch-chip__cnt { background: #4e6ef2; color: #fff; }

/* ============ 工具条 ============ */
.rch-toolbar {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    padding: 12px 14px; margin-bottom: 14px;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
}
.rch-toolbar__field {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 0 10px; height: 32px;
    background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px;
    transition: border-color .15s, background .15s;
    flex: 1; max-width: 360px;
}
.rch-toolbar__field:focus-within { background: #fff; border-color: #4e6ef2; }
.rch-toolbar__field i { color: #9ca3af; font-size: 12px; }
.rch-input {
    border: 0; outline: none; background: transparent;
    height: 30px; font-size: 13px; color: #374151;
    flex: 1; min-width: 120px;
}
.rch-input::placeholder { color: #9ca3af; }

/* ============ 表格行内组件 ============ */
.rch-orderno {
    display: inline-block;
    padding: 1px 8px; font-size: 12px;
    background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 4px;
    color: #374151;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}
.rch-user-cell { line-height: 1.4; text-align: left; padding: 0 4px; }
.rch-user-cell__name { color: #1f2937; font-weight: 500; font-size: 13px; }
.rch-user-cell__id   { color: #9ca3af; font-size: 11px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
.rch-amount {
    font-size: 15px; font-weight: 700; color: #16a34a;
    letter-spacing: 0.3px;
}
.rch-amount--pending { color: #c2410c; }
.rch-amount--cancelled { color: #9ca3af; text-decoration: line-through; }
.rch-pay {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 9px; font-size: 11.5px; font-weight: 500;
    background: #eef2ff; color: #4338ca;
    border-radius: 10px;
}
.rch-pay--empty { background: #f3f4f6; color: #9ca3af; font-weight: 400; }
.rch-pay__icon {
    width: 16px; height: 16px; border-radius: 3px;
    object-fit: contain; background: #fff;
}
.rch-trade {
    color: #4b5563; font-size: 12px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}
.rch-status {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 10px; font-size: 12px; font-weight: 500;
    border-radius: 11px;
}
.rch-status i { font-size: 10px; }
.rch-status--pending   { background: #fff7ed; color: #c2410c; }
.rch-status--paid      { background: #dcfce7; color: #166534; }
.rch-status--cancelled { background: #f3f4f6; color: #6b7280; }
.rch-time {
    display: inline-flex; flex-direction: column; align-items: center; line-height: 1.3;
}
.rch-time__date { color: #374151; font-weight: 500; font-size: 12.5px; }
.rch-time__hms  { color: #9ca3af; font-size: 11.5px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
.rch-empty { color: #d1d5db; }
</style>

<!-- 行模板 -->
<script type="text/html" id="rchOrderNoTpl">
    <span class="rch-orderno">{{ d.order_no }}</span>
</script>

<script type="text/html" id="rchUserTpl">
    <div class="rch-user-cell">
        <div class="rch-user-cell__name">{{ d.nickname || d.username || '-' }}</div>
        <div class="rch-user-cell__id">#{{ d.user_id }}</div>
    </div>
</script>

<script type="text/html" id="rchAmountTpl">
    {{# var cls = 'rch-amount';
        if (d.status === 'pending') cls += ' rch-amount--pending';
        else if (d.status === 'cancelled') cls += ' rch-amount--cancelled'; }}
    <span class="{{ cls }}">¥{{ d.amount_display }}</span>
</script>

<script type="text/html" id="rchPayTpl">
    {{# if (d.payment_code) { }}
        <span class="rch-pay">
            {{# if (d.payment_image) { }}
                <img src="{{ d.payment_image }}" alt="" class="rch-pay__icon" onerror="this.style.display='none';">
            {{# } else { }}
                <i class="fa fa-credit-card"></i>
            {{# } }}
            {{ d.payment_name || d.payment_code }}
        </span>
    {{# } else { }}
        <span class="rch-pay rch-pay--empty">未支付</span>
    {{# } }}
</script>

<script type="text/html" id="rchTradeTpl">
    {{# if (d.trade_no) { }}
        <span class="rch-trade">{{ d.trade_no }}</span>
    {{# } else { }}
        <span class="rch-empty"></span>
    {{# } }}
</script>

<script type="text/html" id="rchStatusTpl">
    {{# var labels = {pending:'待支付',paid:'已充值',cancelled:'已取消'};
        var icons = {pending:'clock-o',paid:'check-circle',cancelled:'ban'};
        var s = String(d.status || ''); }}
    <span class="rch-status rch-status--{{ s }}"><i class="fa fa-{{ icons[s] || 'circle' }}"></i> {{ labels[s] || s }}</span>
</script>

<script type="text/html" id="rchCreatedAtTpl">
    {{# if (d.created_at) {
        var dt = String(d.created_at).replace('T',' ').substring(0,19);
        var parts = dt.split(' ');
    }}
    <span class="rch-time">
        <span class="rch-time__date">{{ parts[0] }}</span>
        <span class="rch-time__hms">{{ parts[1] || '' }}</span>
    </span>
    {{# } else { }}<span class="rch-empty"></span>{{# } }}
</script>

<script type="text/html" id="rchPaidAtTpl">
    {{# if (d.paid_at) {
        var dt = String(d.paid_at).replace('T',' ').substring(0,19);
        var parts = dt.split(' ');
    }}
    <span class="rch-time">
        <span class="rch-time__date">{{ parts[0] }}</span>
        <span class="rch-time__hms">{{ parts[1] || '' }}</span>
    </span>
    {{# } else { }}<span class="rch-empty"></span>{{# } }}
</script>

<script type="text/html" id="rchActionTpl">
    {{# if (d.status === 'pending') { }}
    <a class="em-btn em-sm-btn em-warm-btn" lay-event="cancel"><i class="fa fa-ban"></i>取消</a>
    {{# } else { }}
    <span class="rch-empty"></span>
    {{# } }}
</script>

<script>
$(function () {
    'use strict';
    // PJAX 防重复绑定
    $(document).off('.admRecharge');
    $(window).off('.admRecharge');

    var csrfToken = <?= json_encode($csrfToken) ?>;
    var currentStatus = '';
    var currentKeyword = '';

    layui.use(['layer', 'table'], function () {
        var layer = layui.layer, table = layui.table;

        function buildWhere() {
            return { _action: 'list', status: currentStatus, keyword: currentKeyword };
        }

        function refreshTable() {
            table.reload('rechargeTableId', { page: { curr: 1 }, where: buildWhere() });
        }

        table.render({
            elem: '#rechargeTable',
            id: 'rechargeTableId',
            url: '/admin/recharge.php',
            method: 'POST',
            where: buildWhere(),
            page: true,
            limit: 10,
            limits: [10, 20, 50, 100],
            cellMinWidth: 80,
            lineStyle: 'height: 56px;',
            cols: [[
                { field: 'id',           title: 'ID',        width: 70,  align: 'center' },
                { field: 'order_no',     title: '充值单号',   width: 220, align: 'center', templet: '#rchOrderNoTpl' },
                { field: 'user_id',      title: '用户',       width: 150, templet: '#rchUserTpl' },
                { field: 'amount',       title: '金额',       width: 130, align: 'right',  templet: '#rchAmountTpl' },
                { field: 'payment_code', title: '支付方式',   width: 130, align: 'center', templet: '#rchPayTpl' },
                { field: 'trade_no',     title: '第三方流水号', minWidth: 200, templet: '#rchTradeTpl' },
                { field: 'status',       title: '状态',       width: 110, align: 'center', templet: '#rchStatusTpl' },
                { field: 'created_at',   title: '创建时间',   width: 150, align: 'center', templet: '#rchCreatedAtTpl' },
                { field: 'paid_at',      title: '支付时间',   width: 150, align: 'center', templet: '#rchPaidAtTpl' },
                { title: '操作', width: 100, align: 'center', toolbar: '#rchActionTpl' }
            ]],
            parseData: function (res) {
                if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                // tabs 的状态徽章数字一并刷新（list 接口顺手返回了 status_counts）
                if (res.data && res.data.status_counts) {
                    var c = res.data.status_counts;
                    $('.rch-chip__cnt[data-key="all"]').text(c.all || 0);
                    $('.rch-chip__cnt[data-key="pending"]').text(c.pending || 0);
                    $('.rch-chip__cnt[data-key="paid"]').text(c.paid || 0);
                    $('.rch-chip__cnt[data-key="cancelled"]').text(c.cancelled || 0);
                }
                return {
                    code: res.code === 200 ? 0 : res.code,
                    msg: res.msg,
                    data: res.data ? res.data.data : [],
                    count: res.data ? res.data.total : 0
                };
            }
        });

        // 取消 pending 充值单
        table.on('tool(rechargeTable)', function (obj) {
            if (obj.event !== 'cancel') return;
            layer.confirm('确认取消该笔待支付充值单？', function (idx) {
                layer.close(idx);
                $.post('/admin/recharge.php', { _action: 'cancel', id: obj.data.id, csrf_token: csrfToken }, function (res) {
                    if (res.code === 200) {
                        layer.msg(res.msg || '已取消');
                        refreshTable();
                        loadSummary();
                    } else {
                        layer.msg(res.msg || '操作失败');
                    }
                }, 'json');
            });
        });

        // chips 切换状态
        $(document).on('click.admRecharge', '.rch-chip', function () {
            $('.rch-chip').removeClass('is-active');
            $(this).addClass('is-active');
            currentStatus = String($(this).data('status') || '');
            refreshTable();
        });

        // 搜索 / 重置
        $(document).on('click.admRecharge', '#rchSearchBtn', function () {
            currentKeyword = $.trim($('#rchKeyword').val());
            refreshTable();
        });
        $(document).on('keypress.admRecharge', '#rchKeyword', function (e) {
            if (e.which === 13) $('#rchSearchBtn').click();
        });
        $(document).on('click.admRecharge', '#rchResetBtn', function () {
            $('#rchKeyword').val('');
            currentKeyword = '';
            currentStatus = '';
            $('.rch-chip').removeClass('is-active');
            $('.rch-chip[data-status=""]').addClass('is-active');
            refreshTable();
        });

        // 顶部数据卡：单独 summary 接口
        function loadSummary() {
            $.post('/admin/recharge.php', { _action: 'summary' }, function (res) {
                if (res.code !== 200 || !res.data || !res.data.data) return;
                var d = res.data.data;
                $('#rchTodayCount').text(d.today_count || 0);
                $('#rchTodayAmount').text(d.today_amount || '0.00');
                $('#rchMonthAmount').text(d.month_amount || '0.00');
                $('#rchTotalAmount').text(d.total_amount || '0.00');
            }, 'json');
        }
        loadSummary();
    });
});
</script>
