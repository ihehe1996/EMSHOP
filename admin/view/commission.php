<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
$tab = (string) ($tab ?? 'log');
?>
<div class="admin-page">
    <h1 class="admin-page__title">
        <?= $tab === 'withdraw' ? '提现记录' : '佣金流水' ?>
    </h1>

    <!-- 切换 tab：用 admin 标准 em-tabs 风格 -->
    <div class="em-tabs" style="margin-bottom: 16px;">
        <a href="/admin/commission.php?tab=log" data-pjax="#adminContent" class="em-tabs__item <?= $tab === 'log' ? 'is-active' : '' ?>">
            <i class="fa fa-list-alt"></i><span>佣金流水</span>
        </a>
        <a href="/admin/commission.php?tab=withdraw" data-pjax="#adminContent" class="em-tabs__item <?= $tab === 'withdraw' ? 'is-active' : '' ?>">
            <i class="fa fa-credit-card"></i><span>提现记录</span>
        </a>
    </div>

    <!-- 工具条：搜索 -->
    <div class="cmm-toolbar">
        <div class="cmm-toolbar__field">
            <i class="fa fa-user"></i>
            <input type="number" id="commissionSearchUid" class="cmm-input" placeholder="按归属用户 ID">
        </div>
        <?php if ($tab === 'log'): ?>
        <div class="cmm-toolbar__field">
            <select id="commissionSearchStatus" class="cmm-input">
                <option value="">全部状态</option>
                <option value="frozen">冻结中</option>
                <option value="available">可提现</option>
                <option value="withdrawn">已提现</option>
                <option value="reverted">已倒扣</option>
            </select>
        </div>
        <div class="cmm-toolbar__field">
            <select id="commissionSearchLevel" class="cmm-input">
                <option value="">全部级别</option>
                <option value="1">一级</option>
                <option value="2">二级</option>
            </select>
        </div>
        <?php endif; ?>
        <button class="em-btn em-sm-btn em-save-btn" id="commissionSearchBtn">
            <i class="fa fa-search"></i>搜索
        </button>
        <button type="button" class="em-btn em-sm-btn em-reset-btn" id="commissionResetBtn">
            <i class="fa fa-rotate-left"></i>重置
        </button>
    </div>

    <table id="commissionTable" lay-filter="commissionTable"></table>
</div>

<style>
.cmm-toolbar {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    padding: 12px 14px; margin-bottom: 14px;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
}
.cmm-toolbar__field {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 0 10px; height: 32px;
    background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px;
    transition: border-color 0.15s, background 0.15s;
}
.cmm-toolbar__field:focus-within { background: #fff; border-color: #4e6ef2; }
.cmm-toolbar__field i { color: #9ca3af; font-size: 12px; }
.cmm-input {
    border: 0; outline: none; background: transparent;
    height: 30px; font-size: 13px; color: #374151;
    min-width: 140px;
}
.cmm-input::placeholder { color: #9ca3af; }
.cmm-input[type="number"] { width: 160px; min-width: 0; }

/* 行内组件 —— 仿 admin/page.php 风格 */
.cmm-uid {
    display: inline-flex; align-items: center;
    padding: 2px 8px; font-size: 12px; line-height: 16px;
    color: #4338ca; background: #eef2ff; border-radius: 10px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}
.cmm-uid--guest { color: #6b7280; background: #f3f4f6; font-family: inherit; }
.cmm-order-no {
    display: inline-block;
    padding: 1px 8px; font-size: 12px;
    background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 4px;
    color: #374151;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}
.cmm-level {
    display: inline-flex; align-items: center;
    padding: 1px 8px; font-size: 11px; font-weight: 500;
    border-radius: 9px;
}
.cmm-level--1 { background: #dbeafe; color: #1d4ed8; }
.cmm-level--2 { background: #ede9fe; color: #5b21b6; }
.cmm-amount {
    font-size: 14px; font-weight: 700; color: #16a34a;
    letter-spacing: 0.3px;
}
.cmm-rate { color: #6b7280; font-size: 12px; }
.cmm-basis { color: #4b5563; font-size: 13px; }
.cmm-balance-cell {
    line-height: 1.4; text-align: center;
}
.cmm-balance-cell__main { color: #1f2937; font-weight: 500; font-size: 13px; }
.cmm-balance-cell__sub  { color: #9ca3af; font-size: 11px; }
.cmm-time {
    display: inline-flex; flex-direction: column; align-items: center; line-height: 1.3;
}
.cmm-time__date { color: #374151; font-weight: 500; font-size: 12.5px; }
.cmm-time__hms  { color: #9ca3af; font-size: 11.5px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
.cmm-empty { color: #d1d5db; }

.cmm-status {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 9px; font-size: 12px; font-weight: 500;
    border-radius: 11px;
}
.cmm-status i { font-size: 10px; }
.cmm-status--frozen     { background: #fff7ed; color: #c2410c; }
.cmm-status--available  { background: #dcfce7; color: #166534; }
.cmm-status--withdrawn  { background: #dbeafe; color: #1e40af; }
.cmm-status--reverted   { background: #f3f4f6; color: #6b7280; }
.cmm-status--done       { background: #dcfce7; color: #166534; }
.cmm-status--pending    { background: #fff7ed; color: #c2410c; }
.cmm-status--rejected   { background: #fef2f2; color: #b91c1c; }
</style>

<script type="text/html" id="cmmStatusTpl">
    {{#
        var s = String(d.status || '');
        var labels = {frozen:'冻结中',available:'可提现',withdrawn:'已提现',reverted:'已倒扣',
                      done:'已到账',pending:'审核中',rejected:'已驳回'};
        var icons  = {frozen:'lock',available:'check-circle',withdrawn:'paper-plane',reverted:'undo',
                      done:'check-circle',pending:'clock-o',rejected:'times-circle'};
    }}
    <span class="cmm-status cmm-status--{{ s }}">
        <i class="fa fa-{{ icons[s] || 'circle' }}"></i> {{ labels[s] || s }}
    </span>
</script>

<script type="text/html" id="cmmUidTpl">
    <span class="cmm-uid">#{{ d.user_id }}</span>
</script>

<script type="text/html" id="cmmFromUserTpl">
    {{# if (d.from_user_id > 0) { }}
        <span class="cmm-uid">#{{ d.from_user_id }}</span>
    {{# } else { }}
        <span class="cmm-uid cmm-uid--guest">游客</span>
    {{# } }}
</script>

<script type="text/html" id="cmmOrderNoTpl">
    {{# if (d.order_no) { }}
        <span class="cmm-order-no">{{ d.order_no }}</span>
    {{# } else { }}
        <span class="cmm-empty">—</span>
    {{# } }}
</script>

<script type="text/html" id="cmmLevelTpl">
    {{# var lname = d.level == 1 ? '一级' : (d.level == 2 ? '二级' : ('L' + d.level)); }}
    <span class="cmm-level cmm-level--{{ d.level }}">L{{ d.level }} · {{ lname }}</span>
</script>

<script type="text/html" id="cmmAmountTpl">
    <span class="cmm-amount">¥{{ d.amount_display }}</span>
</script>

<script type="text/html" id="cmmRateTpl">
    <span class="cmm-rate">{{ (d.rate/100).toFixed(2) }}%</span>
</script>

<script type="text/html" id="cmmBasisTpl">
    <span class="cmm-basis">¥{{ d.basis_amount_display }}</span>
</script>

<script type="text/html" id="cmmWdAmountTpl">
    <span class="cmm-amount">¥{{ d.amount_display }}</span>
</script>

<script type="text/html" id="cmmBalanceTpl">
    <div class="cmm-balance-cell">
        <div class="cmm-balance-cell__main">¥{{ (parseFloat(d.before_balance)/1000000).toFixed(2) }}</div>
        <div class="cmm-balance-cell__sub">→ ¥{{ (parseFloat(d.after_balance)/1000000).toFixed(2) }}</div>
    </div>
</script>

<script type="text/html" id="cmmTimeTpl">
    {{# if (d.created_at) {
        var dt = String(d.created_at).replace('T',' ').substring(0,19);
        var parts = dt.split(' ');
    }}
    <span class="cmm-time">
        <span class="cmm-time__date">{{ parts[0] }}</span>
        <span class="cmm-time__hms">{{ parts[1] || '' }}</span>
    </span>
    {{# } else { }}
    <span class="cmm-empty">—</span>
    {{# } }}
</script>

<script type="text/html" id="cmmFrozenUntilTpl">
    {{# if (d.frozen_until) {
        var dt = String(d.frozen_until).replace('T',' ').substring(0,19);
        var parts = dt.split(' ');
    }}
    <span class="cmm-time">
        <span class="cmm-time__date">{{ parts[0] }}</span>
        <span class="cmm-time__hms">{{ parts[1] || '' }}</span>
    </span>
    {{# } else { }}
    <span class="cmm-empty">—</span>
    {{# } }}
</script>

<script type="text/html" id="cmmRemarkTpl">
    {{# if (d.remark) { }}
        <span style="color:#4b5563;">{{ d.remark }}</span>
    {{# } else { }}
        <span class="cmm-empty">—</span>
    {{# } }}
</script>

<script>
$(function () {
    'use strict';
    // PJAX 防重复绑定
    $(document).off('.admCommission');
    $(window).off('.admCommission');

    var csrfToken = <?= json_encode($csrfToken) ?>;
    var tab = <?= json_encode($tab) ?>;

    layui.use(['layer', 'form', 'table'], function () {
        var layer = layui.layer, form = layui.form, table = layui.table;

        form.render('select');

        var cols;
        if (tab === 'withdraw') {
            cols = [[
                { field: 'id',         title: 'ID',     width: 80,  align: 'center' },
                { field: 'user_id',    title: '用户',   width: 100, align: 'center', templet: '#cmmUidTpl' },
                { field: 'amount',     title: '提现金额', width: 130, align: 'right',  templet: '#cmmWdAmountTpl' },
                { field: 'balance',    title: '余额变化', width: 180, align: 'center', templet: '#cmmBalanceTpl' },
                { field: 'status',     title: '状态',    width: 110, align: 'center', templet: '#cmmStatusTpl' },
                { field: 'remark',     title: '备注',    minWidth: 160, templet: '#cmmRemarkTpl' },
                { field: 'created_at', title: '时间',    width: 150, align: 'center', templet: '#cmmTimeTpl' }
            ]];
        } else {
            cols = [[
                { field: 'id',           title: 'ID',     width: 70,  align: 'center' },
                { field: 'user_id',      title: '归属用户', width: 100, align: 'center', templet: '#cmmUidTpl' },
                { field: 'order_no',     title: '订单号',  width: 200, align: 'center', templet: '#cmmOrderNoTpl' },
                { field: 'from_user_id', title: '下单人',  width: 100, align: 'center', templet: '#cmmFromUserTpl' },
                { field: 'level',        title: '级别',    width: 130, align: 'center', templet: '#cmmLevelTpl' },
                { field: 'amount',       title: '佣金',    width: 120, align: 'right',  templet: '#cmmAmountTpl' },
                { field: 'rate',         title: '比例',    width: 90,  align: 'center', templet: '#cmmRateTpl' },
                { field: 'basis_amount', title: '计算基数', width: 120, align: 'right',  templet: '#cmmBasisTpl' },
                { field: 'status',       title: '状态',    width: 110, align: 'center', templet: '#cmmStatusTpl' },
                { field: 'frozen_until', title: '解冻时间', width: 150, align: 'center', templet: '#cmmFrozenUntilTpl' },
                { field: 'created_at',   title: '创建时间', width: 150, align: 'center', templet: '#cmmTimeTpl' }
            ]];
        }

        table.render({
            elem: '#commissionTable',
            id: 'commissionTableId',
            url: '/admin/commission.php',
            method: 'POST',
            where: { _action: 'list', tab: tab },
            page: true,
            limit: 20,
            limits: [10, 20, 50, 100],
            cols: cols,
            lineStyle: 'height: 56px;',
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

        function buildWhere() {
            var w = { _action: 'list', tab: tab, user_id: $('#commissionSearchUid').val() || '' };
            if (tab === 'log') {
                w.status = $('#commissionSearchStatus').val() || '';
                w.level  = $('#commissionSearchLevel').val() || '';
            }
            return w;
        }

        $(document).on('click.admCommission', '#commissionSearchBtn', function () {
            table.reload('commissionTableId', { page: { curr: 1 }, where: buildWhere() });
        });
        $(document).on('keypress.admCommission', '#commissionSearchUid', function (e) {
            if (e.which === 13) $('#commissionSearchBtn').click();
        });
        $(document).on('click.admCommission', '#commissionResetBtn', function () {
            $('#commissionSearchUid').val('');
            $('#commissionSearchStatus').val('');
            $('#commissionSearchLevel').val('');
            form.render('select');
            table.reload('commissionTableId', { page: { curr: 1 }, where: { _action: 'list', tab: tab } });
        });
    });
});
</script>
