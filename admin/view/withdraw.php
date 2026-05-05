<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
?>

<!-- 状态选项卡（和订单管理页同款，带 fa 图标） -->
<div class="em-tabs" id="withdrawStatusTabs">
    <a class="em-tabs__item is-active" data-status=""         href="javascript:;"><i class="fa fa-th-large"></i><span>全部</span></a>
    <a class="em-tabs__item"           data-status="pending"  href="javascript:;"><i class="fa fa-clock-o"></i><span>待审核</span></a>
    <a class="em-tabs__item"           data-status="approved" href="javascript:;"><i class="fa fa-hourglass-half"></i><span>待打款</span></a>
    <a class="em-tabs__item"           data-status="paid"     href="javascript:;"><i class="fa fa-check-circle"></i><span>已打款</span></a>
    <a class="em-tabs__item"           data-status="rejected" href="javascript:;"><i class="fa fa-times-circle"></i><span>已驳回</span></a>
</div>

<div class="admin-page">
    <h1 class="admin-page__title">钱包提现申请</h1>

    <!-- 快捷搜索：姓名 / 账号 / 用户名 / 昵称 —— 统一回车触发 -->
    <div class="em-quick-search" id="withdrawQuickSearchBox">
        <i class="fa fa-search em-quick-search__ico"></i>
        <input type="text" id="withdrawQuickSearch" placeholder="姓名 / 账号 / 用户名 / 昵称，回车搜索" autocomplete="off">
        <button type="button" class="em-quick-search__clear" id="withdrawQuickClear" title="清空">
            <i class="fa fa-times"></i>
        </button>
    </div>

    <table id="withdrawTable" lay-filter="withdrawTable"></table>
</div>

<!-- 工具栏 -->
<script type="text/html" id="withdrawToolbarTpl">
    <div class="layui-btn-container">
        <a class="em-btn em-reset-btn" id="withdrawRefreshBtn"><i class="fa fa-refresh"></i>刷新</a>
    </div>
</script>

<!-- 用户列：昵称 + ID 小字 -->
<script type="text/html" id="withdrawUserTpl">
    <div style="line-height:1.4;text-align:left;">
        <div style="font-size:13px;color:#1f2937;">{{ d.nickname || d.username || '-' }}</div>
        <div style="font-size:11.5px;color:#9ca3af;">ID {{ d.user_id }}</div>
    </div>
</script>

<!-- 金额：红色 tag -->
<script type="text/html" id="withdrawAmountTpl">
    <span class="em-tag em-tag--red">¥{{ d.amount_display }}</span>
</script>

<!-- 方式：按支付通道配色（蓝=支付宝 · 绿=微信 · 紫=银行卡），比都灰色容易扫读 -->
<script type="text/html" id="withdrawChannelTpl">
    {{# var nameMap = {alipay:'支付宝', wxpay:'微信', bank:'银行卡'}; }}
    {{# var colorMap = {alipay:'em-tag--blue', wxpay:'em-tag--on', bank:'em-tag--purple'}; }}
    <span class="em-tag {{ colorMap[d.channel] || 'em-tag--muted' }}">{{ nameMap[d.channel] || d.channel }}</span>
</script>

<!-- 收款信息：姓名 + 账号 + 银行名 -->
<script type="text/html" id="withdrawAccountTpl">
    <div style="line-height:1.5;text-align:left;">
        <div style="font-weight:500;color:#1f2937;">{{ d.account_name }}</div>
        <div style="font-size:12px;color:#6b7280;font-family:Menlo,Consolas,monospace;">{{ d.account_no }}</div>
        {{# if (d.channel === 'bank' && d.bank_name) { }}
        <div style="font-size:11.5px;color:#9ca3af;">{{ d.bank_name }}</div>
        {{# } }}
    </div>
</script>

<!-- 状态：em-tag 颜色变体 -->
<script type="text/html" id="withdrawStatusTpl">
    {{# var map = {
        pending:'em-tag--amber',
        approved:'em-tag--blue',
        paid:'em-tag--on',
        rejected:'em-tag--muted'
    }; }}
    {{# var nameMap = {pending:'待审核', approved:'待打款', paid:'已打款', rejected:'已驳回'}; }}
    <span class="em-tag {{ map[d.status] || 'em-tag--muted' }}">{{ nameMap[d.status] || d.status }}</span>
</script>

<!-- 管理员备注：空值用灰色 dash -->
<script type="text/html" id="withdrawRemarkTpl">
    {{# if (d.admin_remark) { }}
    <span style="color:#4b5563;">{{ d.admin_remark }}</span>
    {{# } else { }}
    <span style="color:#d1d5db;"></span>
    {{# } }}
</script>

<!-- 时间：日期/时分秒两行 -->
<script type="text/html" id="withdrawTimeTpl">
    {{# if (d.created_at) { var t = d.created_at; }}
    <div style="line-height:1.4;text-align:center;">
        <div style="font-size:12.5px;">{{ t.substring(0,10) }}</div>
        <div style="font-size:11.5px;color:#999;">{{ t.substring(11,19) }}</div>
    </div>
    {{# } else { }}<span style="color:#bbb;"></span>{{# } }}
</script>
<script type="text/html" id="withdrawProcessedTpl">
    {{# if (d.processed_at) { var t = d.processed_at; }}
    <div style="line-height:1.4;text-align:center;">
        <div style="font-size:12.5px;">{{ t.substring(0,10) }}</div>
        <div style="font-size:11.5px;color:#999;">{{ t.substring(11,19) }}</div>
    </div>
    {{# } else { }}<span style="color:#bbb;">待处理</span>{{# } }}
</script>

<!-- 行内操作：按状态切换按钮组合 -->
<script type="text/html" id="withdrawActionTpl">
    <div class="layui-clear-space">
    {{# if (d.status === 'pending') { }}
        <a class="em-btn em-sm-btn em-save-btn" lay-event="approve"><i class="fa fa-check"></i>通过</a>
        <a class="em-btn em-sm-btn em-red-btn" lay-event="reject"><i class="fa fa-times"></i>驳回</a>
    {{# } else if (d.status === 'approved') { }}
        <a class="em-btn em-sm-btn em-green-btn" lay-event="paid"><i class="fa fa-money"></i>已打款</a>
        <a class="em-btn em-sm-btn em-red-btn" lay-event="reject"><i class="fa fa-times"></i>驳回</a>
    {{# } else { }}
        <span style="color:#d1d5db;"></span>
    {{# } }}
    </div>
</script>

<script>
$(function () {
    // PJAX 防重复绑定：清掉本页历史 .admWithdraw handler，避免事件成倍触发
    $(document).off('.admWithdraw');
    $(window).off('.admWithdraw');

    'use strict';
    var csrfToken = <?= json_encode($csrfToken) ?>;

    layui.use(['layer', 'form', 'table'], function () {
        var layer = layui.layer, form = layui.form, table = layui.table;

        // 当前筛选（由 tab 控制）
        var currentStatus = '';

        function buildWhere() {
            return {
                _action: 'list',
                keyword: $.trim($('#withdrawQuickSearch').val() || ''),
                status: currentStatus
            };
        }
        function doReload() {
            table.reload('withdrawTableId', { page: { curr: 1 }, where: buildWhere() });
        }

        // ============================================================
        // 表格
        // ============================================================
        table.render({
            elem: '#withdrawTable',
            id: 'withdrawTableId',
            url: '/admin/withdraw.php',
            method: 'POST',
            where: buildWhere(),
            page: true,
            toolbar: '#withdrawToolbarTpl',
            defaultToolbar: [],
            lineStyle: 'height: 58px;',
            limit: 10,
            limits: [10, 20, 50, 100],
            cols: [[
                {field: 'id', title: 'ID', width: 70, align: 'center'},
                {field: 'user_id', title: '用户', width: 140, templet: '#withdrawUserTpl'},
                {field: 'amount_display', title: '金额', width: 120, align: 'center', templet: '#withdrawAmountTpl'},
                {field: 'channel', title: '方式', width: 90, align: 'center', templet: '#withdrawChannelTpl'},
                {field: 'account', title: '收款信息', minWidth: 200, templet: '#withdrawAccountTpl'},
                {field: 'status', title: '状态', width: 100, align: 'center', templet: '#withdrawStatusTpl'},
                {field: 'admin_remark', title: '管理员备注', minWidth: 140, templet: '#withdrawRemarkTpl'},
                {field: 'created_at', title: '申请时间', width: 140, align: 'center', templet: '#withdrawTimeTpl'},
                {field: 'processed_at', title: '处理时间', width: 140, align: 'center', templet: '#withdrawProcessedTpl'},
                {title: '操作', width: 190, align: 'center', templet: '#withdrawActionTpl'}
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

        // ============================================================
        // 状态选项卡：点击切换 currentStatus 并刷新
        // ============================================================
        $(document).on('click.admWithdraw', '#withdrawStatusTabs .em-tabs__item', function (e) {
            e.preventDefault();
            $('#withdrawStatusTabs .em-tabs__item').removeClass('is-active');
            $(this).addClass('is-active');
            currentStatus = $(this).attr('data-status') || '';
            doReload();
        });

        // ============================================================
        // 快捷搜索：回车触发；清空按钮立即刷新
        // ============================================================
        $(document).on('keypress.admWithdraw', '#withdrawQuickSearch', function (e) {
            if (e.which !== 13) return;
            e.preventDefault();
            doReload();
        });
        $(document).on('click.admWithdraw', '#withdrawQuickClear', function () {
            $('#withdrawQuickSearch').val('').focus();
            doReload();
        });

        // 刷新
        $(document).on('click.admWithdraw', '#withdrawRefreshBtn', function () {
            table.reload('withdrawTableId');
        });

        // ============================================================
        // 行内操作：审核通过 / 驳回 / 标记已打款
        // ============================================================
        function submit(ev, id, remark) {
            $.post('/admin/withdraw.php',
                { _action: ev, id: id, remark: remark, csrf_token: csrfToken },
                function (res) {
                    if (res.code === 200) {
                        if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                        layer.msg(res.msg || '操作成功');
                        table.reload('withdrawTableId');
                    } else {
                        layer.msg(res.msg || '操作失败');
                    }
                }, 'json').fail(function () { layer.msg('网络异常'); });
        }

        table.on('tool(withdrawTable)', function (obj) {
            var ev = obj.event, row = obj.data;
            var titleMap = {
                approve: '确认审核通过？',
                reject:  '请填写驳回理由（金额将退回用户余额）',
                paid:    '确认已打款并标记完成？'
            };
            if (ev === 'reject') {
                layer.prompt({ title: titleMap[ev], formType: 2 }, function (val, idx) {
                    val = $.trim(val || '');
                    if (!val) { layer.msg('请填写驳回理由'); return; }
                    layer.close(idx);
                    submit(ev, row.id, val);
                });
            } else {
                layer.confirm(titleMap[ev], function (idx) {
                    layer.close(idx);
                    submit(ev, row.id, '');
                });
            }
        });
    });
});
</script>
