<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = $csrfToken ?? Csrf::token();
$moneyRaw = isset($moneyRaw) ? (int) $moneyRaw : 0;
$moneyText = (string) ($moneyText ?? Currency::displayAmount($moneyRaw));
?>
<style>
.mas-page { padding: 8px 4px 40px; background: unset; }
.mc-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 34px;
    padding: 0 14px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    background: #fff;
    color: #555;
    font-size: 13px;
    cursor: pointer;
    transition: border-color .15s, color .15s, background .15s;
}
.mc-btn:hover:not(:disabled) { border-color: #4e6ef2; color: #4e6ef2; }
.mc-btn:disabled { cursor: not-allowed; opacity: .65; }
.mc-btn--primary { background: #4e6ef2; border-color: #4e6ef2; color: #fff; }
.mc-btn--primary:hover:not(:disabled) { background: #3d5bd9; border-color: #3d5bd9; color: #fff; }
.mc-btn--line { background: #f8fafc; color: #94a3b8; border-color: #e2e8f0; }

.mas-toolbar {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    margin-bottom: 12px;
}
.mas-balance {
    margin-left: auto;
    font-size: 13px; color: #334155;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 7px 10px;
}
.mas-balance b { color: #0f172a; font-family: Menlo,Consolas,monospace; }
.mas-search {
    position: relative;
    width: 280px;
}
.mas-search input {
    width: 100%;
    height: 34px;
    padding: 0 30px 0 32px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    outline: none;
    font-size: 13px;
}
.mas-search input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,.12);
}
.mas-search i.fa-search {
    position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
    color: #94a3b8; font-size: 12px;
}
.mas-search__clear {
    position: absolute; right: 7px; top: 50%; transform: translateY(-50%);
    width: 20px; height: 20px; border: none; border-radius: 50%;
    background: #e5e7eb; color: #64748b; font-size: 10px; cursor: pointer;
    display: none;
}
.mas-search input:not(:placeholder-shown) ~ .mas-search__clear { display: inline-block; }
.mas-search__clear:hover { background: #ef4444; color: #fff; }

.mas-type-tag {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 4px; font-size: 11px; line-height: 18px;
}
.mas-type-tag--plugin { background: #f5f3ff; color: #7c3aed; }
.mas-type-tag--template { background: #ecfeff; color: #0891b2; }
.mas-price {
    font-weight: 600;
    color: #0f172a;
    font-family: Menlo,Consolas,monospace;
}
.mas-stock { font-size: 12px; }
.mas-stock--ok { color: #059669; }
.mas-stock--empty { color: #dc2626; }
</style>

<div class="mas-page">
    <div class="mc-page-header">
        <h2 class="mc-page-title">应用商店</h2>
        <p class="mc-page-desc">这里展示主站已上架给分站购买的应用，购买后可在插件/模板管理里启用。</p>
    </div>

    <div class="mas-toolbar">
        <div class="mas-search">
            <i class="fa fa-search"></i>
            <input type="text" id="masSearch" placeholder="搜索应用名称 / 描述" autocomplete="off">
            <button type="button" class="mas-search__clear" id="masSearchClear"><i class="fa fa-times"></i></button>
        </div>
        <button type="button" class="mc-btn" id="masRefreshBtn"><i class="fa fa-refresh"></i> 刷新</button>
        <div class="mas-balance">账户余额：<b id="masBalanceText"><?= htmlspecialchars($moneyText, ENT_QUOTES) ?></b></div>
    </div>

    <table id="masTable" lay-filter="masTable"></table>
</div>

<script type="text/html" id="masTitleTpl">
<div>
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
        <span class="mas-type-tag mas-type-tag--{{ d.type === 'template' ? 'template' : 'plugin' }}">
            <i class="fa {{ d.type === 'template' ? 'fa-paint-brush' : 'fa-puzzle-piece' }}"></i>
            {{ d.type === 'template' ? '模板' : '插件' }}
        </span>
        <span style="font-weight:600;color:#0f172a;">{{ d.name_cn || d.name_en || '-' }}</span>
        {{# if (d.version) { }}<span style="color:#94a3b8;font-size:12px;">v{{ d.version }}</span>{{# } }}
    </div>
    <div style="font-size:12px;color:#64748b;line-height:1.5;">
        {{ (d.content || '').replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim() || '该应用未配置描述信息' }}
    </div>
</div>
</script>

<script type="text/html" id="masPriceTpl">
{{# var p = Number(d.my_price || 0) / 1000000; }}
{{# if (p <= 0) { }}
    <span class="mas-price">免费</span>
{{# } else { }}
    <span class="mas-price">¥ {{ p.toFixed(2) }}</span>
{{# } }}
</script>

<script type="text/html" id="masStockTpl">
{{# var rem = Number(d.remaining || 0); }}
{{# if (d.is_purchased == 1) { }}
    <span style="color:#64748b;">已购买</span>
{{# } else if (rem > 0) { }}
    <span class="mas-stock mas-stock--ok">库存 {{ rem }}</span>
{{# } else { }}
    <span class="mas-stock mas-stock--empty">缺货</span>
{{# } }}
</script>

<script type="text/html" id="masActionTpl">
{{# var p = Number(d.my_price || 0) / 1000000;
   var isFree = p <= 0; }}
{{# if (d.is_purchased == 1) { }}
    <button class="mc-btn mc-btn--line" disabled><i class="fa fa-check"></i>已购买</button>
{{# } else if ((Number(d.remaining || 0)) <= 0) { }}
    <button class="mc-btn mc-btn--line" disabled><i class="fa fa-ban"></i>缺货</button>
{{# } else { }}
    <button class="mc-btn mc-btn--primary" lay-event="buy">
        <i class="fa fa-shopping-cart"></i>{{ isFree ? '领取' : ('购买 ¥' + p.toFixed(2)) }}
    </button>
{{# } }}
</script>

<script>
window.MAS_CSRF = <?= json_encode($csrfToken) ?>;
window.MAS_BALANCE_RAW = <?= (int) $moneyRaw ?>;

layui.use(['table', 'layer'], function () {
    var $ = layui.jquery, table = layui.table, layer = layui.layer;

    function buildWhere() {
        return {
            keyword: ($('#masSearch').val() || '').trim()
        };
    }

    table.render({
        elem: '#masTable',
        id: 'masTableId',
        url: '/user/merchant/appstore.php?_action=list',
        method: 'get',
        where: buildWhere(),
        page: true,
        limit: 12,
        limits: [12, 24, 48],
        cellMinWidth: 100,
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
            { field: 'name_cn', title: '应用', minWidth: 320, templet: '#masTitleTpl' },
            { field: 'my_price', title: '售价', width: 130, align: 'center', templet: '#masPriceTpl' },
            { title: '状态', width: 120, align: 'center', templet: '#masStockTpl' },
            { title: '操作', width: 220, align: 'center', toolbar: '#masActionTpl' }
        ]]
    });

    function reloadTable(resetPage) {
        var opts = { where: buildWhere() };
        if (resetPage) opts.page = { curr: 1 };
        table.reload('masTableId', opts);
    }

    var searchTimer;
    $('#masSearch').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { reloadTable(true); }, 260);
    });
    $('#masSearchClear').on('click', function () {
        $('#masSearch').val('').trigger('input').focus();
    });
    $('#masRefreshBtn').on('click', function () {
        reloadTable(false);
    });

    function updateBalanceTextByRaw(raw, text) {
        if (text) {
            $('#masBalanceText').text(text);
            return;
        }
        $('#masBalanceText').text('¥' + (Number(raw || 0) / 1000000).toFixed(2));
    }

    table.on('tool(masTable)', function (obj) {
        var d = obj.data;
        if (obj.event !== 'buy') return;

        var name = d.name_cn || d.name_en || '';
        var priceRaw = Number(d.my_price || 0);
        var msg = priceRaw > 0
            ? ('确认购买《' + name + '》？<br><span style="color:#dc2626;">将扣除余额 ¥' + (priceRaw / 1000000).toFixed(2) + '</span>')
            : ('确认领取《' + name + '》？');

        layer.confirm(msg, { title: '应用购买', shadeClose: false }, function (idx) {
            layer.close(idx);
            var loading = layer.load(2, { shade: [0.28, '#000'] });
            $.post('/user/merchant/appstore.php', {
                _action: 'buy',
                csrf_token: window.MAS_CSRF,
                market_id: d.id
            }).done(function (res) {
                layer.close(loading);
                if (res.code === 200) {
                    if (res.data && res.data.csrf_token) window.MAS_CSRF = res.data.csrf_token;
                    if (res.data && typeof res.data.after_balance !== 'undefined') {
                        window.MAS_BALANCE_RAW = Number(res.data.after_balance || 0);
                        updateBalanceTextByRaw(window.MAS_BALANCE_RAW, res.data.after_balance_text || '');
                    }
                    layer.msg('购买成功', { icon: 1 });
                    reloadTable(false);
                } else {
                    layer.msg(res.msg || '购买失败', { icon: 2 });
                }
            }).fail(function () {
                layer.close(loading);
                layer.msg('网络异常', { icon: 2 });
            });
        });
    });
});
</script>
