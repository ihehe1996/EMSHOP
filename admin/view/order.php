<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
$cs = $currencySymbol ?? '¥';
?>

<!-- 订单状态选项卡（每项带图标，和前台"我的订单"状态一致） -->
<div class="em-tabs" id="orderStatusTabs">
    <a class="em-tabs__item is-active" data-status=""          href="javascript:;"><i class="fa fa-th-large"></i><span>全部</span></a>
    <a class="em-tabs__item"           data-status="pending"   href="javascript:;"><i class="fa fa-clock-o"></i><span>待付款</span></a>
    <a class="em-tabs__item"           data-status="paid"      href="javascript:;"><i class="fa fa-cube"></i><span>待发货</span></a>
    <a class="em-tabs__item"           data-status="delivered" href="javascript:;"><i class="fa fa-truck"></i><span>待收货</span></a>
    <a class="em-tabs__item"           data-status="completed" href="javascript:;"><i class="fa fa-check-circle"></i><span>已完成</span></a>
    <a class="em-tabs__item"           data-status="refunded"  href="javascript:;"><i class="fa fa-undo"></i><span>已退款</span></a>
    <a class="em-tabs__item"           data-status="cancelled" href="javascript:;"><i class="fa fa-times-circle"></i><span>已取消</span></a>
</div>

<div class="admin-page">
    <h1 class="admin-page__title">订单管理</h1>

    <!-- 快捷搜索：订单号 / 商品名 / 昵称 / 账号 / 手机 / 邮箱 —— 统一回车触发 -->
    <div class="em-quick-search" id="orderQuickSearchBox">
        <i class="fa fa-search em-quick-search__ico"></i>
        <input type="text" id="orderQuickSearch" placeholder="订单号 / 商品名 / 昵称 / 账号 / 手机号 / 邮箱，回车搜索" autocomplete="off">
        <button type="button" class="em-quick-search__clear" id="orderQuickClear" title="清空">
            <i class="fa fa-times"></i>
        </button>
    </div>

    <table id="orderTable" lay-filter="orderTable"></table>
</div>

<!-- 工具栏：刷新 + 批量删除（未勾选时禁用） -->
<script type="text/html" id="orderToolbarTpl">
    <div class="layui-btn-container">
        <a class="em-btn em-reset-btn" id="orderRefreshBtn"><i class="fa fa-refresh"></i>刷新</a>
        <a class="em-btn em-red-btn em-disabled-btn" lay-event="batchDelete"><i class="fa fa-trash"></i>批量删除</a>
    </div>
</script>

<!-- 订单号 -->
<script type="text/html" id="orderNoTpl">
    <span style="font-size:12.5px;">{{d.order_no}}</span>
</script>

<!-- 商品：首商品缩略图 + 标题 + 规格×数量；多商品显示 "+N" 小徽章 -->
<script type="text/html" id="orderGoodsTpl">
    {{# if(d.goods_count > 0){ var first = d.goods[0]; }}
    <div style="display:flex;align-items:center;gap:8px;line-height:1.4;text-align:left;">
        <img src="{{ first.cover || '' }}" onerror="this.style.visibility='hidden'"
             style="width:30px;height:30px;border-radius:4px;object-fit:cover;background:#f5f5f5;flex:0 0 30px;">
        <div style="flex:1;min-width:0;overflow:hidden;">
            <div style="font-size:12.5px;color:#1f2937;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ first.title }}">
                {{ first.title }}
                {{# if(d.goods_count > 1){ }}
                <span class="em-tag em-tag--muted" style="margin-left:4px;font-size:11px;padding:0 5px;">+{{ d.goods_count - 1 }}</span>
                {{# } }}
            </div>
            <div style="font-size:11.5px;color:#9ca3af;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                {{# if(first.spec){ }}{{ first.spec }} · {{# } }}× {{ first.quantity }}
            </div>
        </div>
    </div>
    {{# } else { }}
    <span style="color:#bbb;">-</span>
    {{# } }}
</script>

<!-- 买家：已登录 → 昵称/用户名，未登录 → 游客 tag -->
<script type="text/html" id="orderBuyerTpl">
    {{# if(d.user_id > 0){ }}
    <span>{{d.nickname || d.username}}</span>
    {{# } else { }}
    <span class="em-tag em-tag--muted">游客</span>
    {{# } }}
</script>

<script type="text/html" id="orderAmountTpl">
    <span class="em-tag em-tag--red"><?= $cs ?>{{d.pay_amount_fmt}}</span>
</script>

<script type="text/html" id="orderPaymentTpl">
    {{# if (d.payment_name) { }}
        <span class="ord-pay">
            {{# if (d.payment_image) { }}
                <img src="{{ d.payment_image }}" alt="" class="ord-pay__icon" onerror="this.style.display='none';">
            {{# } else { }}
                <i class="fa fa-credit-card"></i>
            {{# } }}
            {{ d.payment_name }}
        </span>
    {{# } else { }}
        <span class="ord-pay ord-pay--empty">未支付</span>
    {{# } }}
</script>
<style>
.ord-pay {
    display: inline-flex; align-items: center; gap: 5px; justify-content: center;
    padding: 2px 9px; font-size: 12px; font-weight: 500;
    background: #eef2ff; color: #4338ca; border-radius: 10px;
}
.ord-pay__icon { width: 16px; height: 16px; border-radius: 3px; object-fit: contain; background: #fff; }
.ord-pay--empty { background: #f3f4f6; color: #9ca3af; font-weight: 400; }
</style>

<!-- 状态：用 em-tag 的语义颜色变体代替 layui-badge -->
<script type="text/html" id="orderStatusTpl">
    {{# var map = {
        pending:'em-tag--amber',
        paid:'em-tag--blue',
        delivering:'em-tag--purple',
        delivered:'em-tag--blue',
        completed:'em-tag--on',
        expired:'em-tag--muted',
        cancelled:'em-tag--muted',
        delivery_failed:'em-tag--red',
        refunding:'em-tag--amber',
        refunded:'em-tag--muted',
        failed:'em-tag--red'
    }; }}
    <span class="em-tag {{ map[d.status] || 'em-tag--muted' }}">{{d.status_name}}</span>
</script>

<!-- 时间：日期/时分秒分两行，更易扫读 -->
<script type="text/html" id="orderTimeTpl">
    {{# if(d.created_at){ }}
    {{# var t = d.created_at; }}
    <div style="line-height:1.4;text-align:center;">
        <div style="font-size:12.5px;">{{ t.substring(0,10) }}</div>
        <div style="font-size:11.5px;color:#999;">{{ t.substring(11,19) }}</div>
    </div>
    {{# } else { }}
    <span style="color:#bbb;">-</span>
    {{# } }}
</script>

<!-- 支付时间：同款两行样式；未支付时显示灰色占位 -->
<script type="text/html" id="orderPayTimeTpl">
    {{# if(d.pay_time){ }}
    {{# var t = d.pay_time; }}
    <div style="line-height:1.4;text-align:center;">
        <div style="font-size:12.5px;">{{ t.substring(0,10) }}</div>
        <div style="font-size:11.5px;color:#999;">{{ t.substring(11,19) }}</div>
    </div>
    {{# } else { }}
    <span class="em-tag em-tag--muted">未支付</span>
    {{# } }}
</script>

<!-- 行内操作：详情（蓝）+ 删除（红） -->
<script type="text/html" id="orderActionTpl">
    <div class="layui-clear-space">
        <a class="em-btn em-sm-btn em-save-btn" lay-event="detail"><i class="fa fa-eye"></i>详情</a>
        <a class="em-btn em-sm-btn em-red-btn" lay-event="delete"><i class="fa fa-trash"></i>删除</a>
    </div>
</script>

<script>
$(function () {
    // PJAX 防重复绑定：清掉本页历史 .admOrder handler，避免事件成倍触发
    $(document).off('.admOrder');
    $(window).off('.admOrder');

    'use strict';
    var csrfToken = <?= json_encode($csrfToken) ?>;

    layui.use(['layer', 'form', 'table', 'element'], function () {
        var layer = layui.layer;
        var form = layui.form;
        var table = layui.table;

        form.render('select');

        // 当前筛选状态（由 tab 控制）
        var currentStatus = '';

        function buildWhere() {
            return {
                _action: 'list',
                keyword: $.trim($('#orderQuickSearch').val() || ''),
                status: currentStatus
            };
        }
        function doReload() {
            table.reload('orderTableId', { page: {curr: 1}, where: buildWhere() });
        }

        // ============================================================
        // 表格
        // ============================================================
        table.render({
            elem: '#orderTable',
            id: 'orderTableId',
            url: '/admin/order.php',
            method: 'POST',
            where: buildWhere(),
            page: true,
            toolbar: '#orderToolbarTpl',
            defaultToolbar: [],
            lineStyle: 'height: 55px;',
            limit: 10,
            limits: [10, 20, 50, 100],
            cols: [[
                {type: 'checkbox'},
                {field: 'order_no', title: '订单号', width: 200, templet: '#orderNoTpl'},
                {field: 'goods', title: '商品', minWidth: 240, templet: '#orderGoodsTpl'},
                {field: 'user_id', title: '买家', width: 120, align: 'center', templet: '#orderBuyerTpl'},
                {field: 'pay_amount', title: '金额', width: 110, align: 'center', templet: '#orderAmountTpl'},
                {field: 'payment_name', title: '支付方式', width: 110, align: 'center', templet: '#orderPaymentTpl'},
                {field: 'status', title: '状态', width: 100, align: 'center', templet: '#orderStatusTpl'},
                {field: 'created_at', title: '下单时间', width: 140, align: 'center', templet: '#orderTimeTpl'},
                {field: 'pay_time', title: '支付时间', width: 140, align: 'center', templet: '#orderPayTimeTpl'},
                {title: '操作', width: 170, align: 'center', templet: '#orderActionTpl'}
            ]],
            parseData: function (res) {
                if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                return {
                    'code': res.code === 200 ? 0 : res.code,
                    'msg': res.msg,
                    'data': res.data ? res.data.data : [],
                    'count': res.data ? res.data.total : 0
                };
            }
        });

        // 勾选 → 切换批量删除按钮启用态
        table.on('checkbox(orderTable)', function () {
            var checked = table.checkStatus('orderTableId').data.length > 0;
            $('[lay-event="batchDelete"]').toggleClass('em-disabled-btn', !checked);
        });

        // ============================================================
        // 状态选项卡：点击切换 currentStatus 并刷新
        // ============================================================
        $(document).on('click.admOrder', '#orderStatusTabs .em-tabs__item', function (e) {
            e.preventDefault();
            $('#orderStatusTabs .em-tabs__item').removeClass('is-active');
            $(this).addClass('is-active');
            currentStatus = $(this).attr('data-status') || '';
            doReload();
        });

        // ============================================================
        // 快捷搜索：回车触发；清空按钮立即刷新
        // ============================================================
        $(document).on('keypress.admOrder', '#orderQuickSearch', function (e) {
            if (e.which !== 13) return;
            e.preventDefault();
            doReload();
        });
        $(document).on('click.admOrder', '#orderQuickClear', function () {
            $('#orderQuickSearch').val('').focus();
            doReload();
        });

        // 刷新
        $(document).on('click.admOrder', '#orderRefreshBtn', function () {
            table.reload('orderTableId');
        });

        // ============================================================
        // 工具栏事件：批量删除
        // ============================================================
        table.on('toolbar(orderTable)', function (obj) {
            if (obj.event !== 'batchDelete') return;
            var checked = table.checkStatus('orderTableId').data;
            if (checked.length === 0) { layer.msg('请先勾选订单'); return; }
            var ids = checked.map(function (r) { return r.id; });
            layer.confirm('确定要删除选中的 ' + ids.length + ' 条订单吗？将同时清理关联的发货队列和订单商品，此操作不可恢复。', function (idx) {
                $.ajax({
                    url: '/admin/order.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {csrf_token: csrfToken, _action: 'batch_delete', ids: ids},
                    success: function (res) {
                        if (res.code === 200) {
                            if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                            layer.msg(res.msg || '删除成功');
                            table.reload('orderTableId');
                        } else {
                            layer.msg(res.msg || '删除失败');
                        }
                    },
                    error: function () { layer.msg('网络异常'); },
                    complete: function () { layer.close(idx); }
                });
            });
        });

        // ============================================================
        // 行内事件：详情（iframe 打开 popup）/ 单条删除
        // ============================================================
        table.on('tool(orderTable)', function (obj) {
            if (obj.event === 'detail') {
                showOrderDetail(obj.data);
            } else if (obj.event === 'delete') {
                var data = obj.data;
                layer.confirm('确定要删除订单「' + data.order_no + '」吗？将同时清理关联的发货队列和订单商品，此操作不可恢复。', function (idx) {
                    $.ajax({
                        url: '/admin/order.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {csrf_token: csrfToken, _action: 'delete', id: data.id},
                        success: function (res) {
                            if (res.code === 200) {
                                if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                                layer.msg(res.msg || '删除成功');
                                obj.del();
                            } else {
                                layer.msg(res.msg || '删除失败');
                            }
                        },
                        error: function () { layer.msg('网络异常'); },
                        complete: function () { layer.close(idx); }
                    });
                });
            }
        });

        // 订单详情：iframe 打开独立 popup 页
        function showOrderDetail(data) {
            layer.open({
                type: 2,
                title: '订单详情 - ' + data.order_no,
                skin: 'admin-modal',
                maxmin: true,
                shadeClose: true,
                area: [window.innerWidth >= 900 ? '780px' : '95%', window.innerHeight >= 700 ? '640px' : '90%'],
                content: '/admin/order.php?_popup=detail&id=' + encodeURIComponent(data.id)
            });
        }
    });
});
</script>
