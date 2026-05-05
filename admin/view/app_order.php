<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
?>
<style>
.admin-page-app-order { padding: 8px 4px 40px; background: unset; }
.ao-toolbar {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 12px; flex-wrap: wrap;
}
.ao-search {
    position: relative;
    width: 260px;
}
.ao-search input {
    width: 100%; height: 34px; padding: 0 12px 0 32px;
    border: 1px solid #e5e7eb; border-radius: 8px; outline: none;
    font-size: 13px; background: #fff;
}
.ao-search input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,.12);
}
.ao-search i {
    position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
    color: #94a3b8; font-size: 12px;
}
.ao-amount { font-family: Menlo,Consolas,monospace; color: #0f172a; font-weight: 600; }
.ao-balance { font-family: Menlo,Consolas,monospace; color: #475569; }
.ao-type {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 4px; font-size: 11px;
}
.ao-type--plugin { background: #f5f3ff; color: #7c3aed; }
.ao-type--template { background: #ecfeff; color: #0891b2; }
</style>

<div class="admin-page admin-page-app-order">
    <h1 class="admin-page__title">应用订单</h1>

    <div class="ao-toolbar">
        <div class="ao-search">
            <i class="fa fa-search"></i>
            <input type="text" id="aoKeyword" placeholder="订单号 / 应用 / 商户 / 用户">
        </div>
        <select id="aoType" class="layui-select">
            <option value="">全部类型</option>
            <option value="plugin">插件</option>
            <option value="template">模板</option>
        </select>
        <button type="button" class="em-btn em-sm-btn em-reset-btn" id="aoRefreshBtn"><i class="fa fa-refresh"></i> 刷新</button>
    </div>

    <table id="aoTable" lay-filter="aoTable"></table>
</div>

<script type="text/html" id="aoTypeTpl">
{{# if (d.type === 'template') { }}
<span class="ao-type ao-type--template"><i class="fa fa-paint-brush"></i>模板</span>
{{# } else { }}
<span class="ao-type ao-type--plugin"><i class="fa fa-puzzle-piece"></i>插件</span>
{{# } }}
</script>

<script type="text/html" id="aoAmountTpl">
<span class="ao-amount">¥{{ d.amount_view || '0.00' }}</span>
</script>

<script type="text/html" id="aoBalanceTpl">
<div class="ao-balance">前：¥{{ d.before_balance_view || '0.00' }}</div>
<div class="ao-balance">后：¥{{ d.after_balance_view || '0.00' }}</div>
</script>

<script>
$(function () {
    layui.use(['table'], function () {
        var $ = layui.jquery, table = layui.table;
    function esc(text) {
        return String(text == null ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function buildWhere() {
        return {
            keyword: ($('#aoKeyword').val() || '').trim(),
            type: $('#aoType').val() || ''
        };
    }

    table.render({
        elem: '#aoTable',
        id: 'aoTableId',
        url: '/admin/app_order.php?_action=list',
        method: 'get',
        where: buildWhere(),
        page: true,
        limit: 20,
        limits: [20, 50, 100],
        parseData: function (res) {
            var d = res && res.data ? res.data : {};
            return {
                code: res.code === 200 ? 0 : (res.code || 500),
                msg: res.msg || '',
                count: d.count || 0,
                data: d.list || []
            };
        },
        request: { pageName: 'page', limitName: 'limit' },
        cols: [[
            { field: 'order_no', title: '订单号', minWidth: 180 },
            { field: 'app_title', title: '应用', minWidth: 220, templet: function (d) {
                return '<div style="font-weight:600;color:#0f172a;">' + esc(d.app_title || d.app_code || '-') + '</div>'
                    + '<div style="font-size:12px;color:#94a3b8;">' + esc(d.app_code || '-') + '</div>';
            }},
            { field: 'type', title: '类型', width: 90, templet: '#aoTypeTpl', align: 'center' },
            { field: 'merchant_name', title: '分站', minWidth: 150, templet: function (d) {
                return esc(d.merchant_name || ('#' + (d.merchant_id || 0)));
            }},
            { field: 'nickname', title: '购买用户', minWidth: 150, templet: function (d) {
                return esc(d.nickname || d.username || ('#' + (d.user_id || 0)));
            }},
            { field: 'amount', title: '订单金额', width: 120, align: 'right', templet: '#aoAmountTpl' },
            { title: '余额变化', width: 150, templet: '#aoBalanceTpl' },
            { field: 'created_at', title: '下单时间', width: 170 },
            { field: 'paid_at', title: '支付时间', width: 170 }
        ]]
    });

    function reloadTable(resetPage) {
        var opts = { where: buildWhere() };
        if (resetPage) opts.page = { curr: 1 };
        table.reload('aoTableId', opts);
    }

    var timer;
    $('#aoKeyword').on('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { reloadTable(true); }, 260);
    });
    $('#aoType').on('change', function () { reloadTable(true); });
    $('#aoRefreshBtn').on('click', function () { reloadTable(false); });
    });
});
</script>
