<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

// 应用收费由中心服务端统一以人民币结算，这里固定用 ¥ 不读站点主货币
// 分类 tabs 由 PHP 直接渲染(基于 PluginModel::MAIN_PLUGIN_CATEGORIES);列表仍走 /admin/appstore.php?_action=list 异步加载

// 应用图片（封面 / 内容图）统一基于 license_urls 第 0 个线路拼接 —— 永远是第一个，
// 不跟随用户切换的线路；以此保证资源 URL 在全站稳定、可被浏览器缓存
$__appstoreLines = LicenseClient::lines();
$appstoreAssetHost = $__appstoreLines ? rtrim($__appstoreLines[0]['url'], '/') : '';

// 当前站点是否已激活授权 —— 未激活时付费应用不允许安装/购买，引导到授权页
$appstoreLicensed = LicenseService::isActivated();
$csrfToken = $csrfToken ?? Csrf::token();
?>
<style>
/* 和控制台 / 模板管理 / 资源管理一致：去掉 .admin-page 默认白底，内容块浮在灰底画布 */
.admin-page-appstore { padding: 8px 4px 40px; background: unset; }

/* ===== 顶部工具条：只放一个快捷搜索，右对齐 ===== */
.appstore-toolbar {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    margin-bottom: 12px;
}
.appstore-search {
    position: relative;
    width: 280px;
}
.appstore-search input {
    width: 100%;
    height: 34px;
    padding: 0 32px 0 34px;
    font-size: 13px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    outline: none;
    transition: border-color .15s ease, box-shadow .15s ease;
}
.appstore-search input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,.12);
}
.appstore-search i.fa-search {
    position: absolute;
    left: 12px; top: 50%; transform: translateY(-50%);
    color: #9ca3af; font-size: 12px;
    pointer-events: none;
}
.appstore-search__clear {
    position: absolute;
    right: 8px; top: 50%; transform: translateY(-50%);
    display: none;
    width: 20px; height: 20px;
    border: none;
    border-radius: 50%;
    background: #e5e7eb;
    color: #6b7280;
    cursor: pointer;
    font-size: 10px;
    align-items: center;
    justify-content: center;
    transition: background .15s;
}
.appstore-search__clear:hover { background: #ef4444; color: #fff; }
.appstore-search input:not(:placeholder-shown) ~ .appstore-search__clear { display: inline-flex; }

/* ===== 封面小图（表格第一列） ===== */
.appstore-cover {
    width: 44px; height: 44px;
    display: inline-block;
    border-radius: 8px;
    object-fit: cover;
    background: #f3f4f6;
    box-shadow: 0 1px 3px rgba(15,23,42,.08);
    vertical-align: middle;
}
.appstore-cover--empty {
    display: inline-flex; align-items: center; justify-content: center;
    color: #9ca3af; font-size: 18px;
}
.appstore-cover--zoom { cursor: zoom-in; transition: transform .15s ease, box-shadow .15s ease; }
.appstore-cover--zoom:hover {
    transform: scale(1.08);
    box-shadow: 0 4px 12px rgba(15,23,42,.18);
}

/* ===== 类型 tag（前置到名称行） ===== */
.appstore-type {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px;
    font-size: 11px; font-weight: 500;
    border-radius: 4px;
    line-height: 18px;
    flex-shrink: 0;
}
.appstore-type i { font-size: 10px; }
.appstore-type--template { background: #ecfeff; color: #0891b2; }
.appstore-type--plugin   { background: #f5f3ff; color: #7c3aed; }

/* ===== 名称行 ===== */
.appstore-title__row {
    display: flex; align-items: center; gap: 8px;
    min-width: 0;
}
.appstore-title__name {
    color: #0f172a;
    line-height: 1.35;
    flex: 1; min-width: 0;
    overflow: hidden; white-space: nowrap; text-overflow: ellipsis;
}
.appstore-title__desc {
    font-size: 12px; color: #9ca3af;
    margin-top: 3px;
    font-weight: 400;
    line-height: 1.4;
    display: -webkit-box; -webkit-line-clamp: 1; line-clamp: 1;
    -webkit-box-orient: vertical; overflow: hidden;
}

/* ===== 价格 chip（免费/付费/不可用） ===== */
.appstore-chip {
    display: inline-block;
    min-width: 56px;
    padding: 4px 12px;
    font-size: 12px; font-weight: 600;
    border-radius: 5px;
    letter-spacing: .2px;
    line-height: 18px;
    border: 1px solid transparent;
}
.appstore-chip__cur { font-size: 10px; opacity: .7; margin-right: 1px; }

.appstore-chip--free {
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    color: #047857;
    border-color: rgba(16,185,129,.28);
    box-shadow: 0 1px 2px rgba(16,185,129,.08);
}
.appstore-chip--paid {
    background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%);
    color: #be123c;
    border-color: rgba(225,29,72,.28);
    box-shadow: 0 1px 2px rgba(225,29,72,.08);
}
.appstore-chip--na {
    background: #f9fafb;
    color: #cbd5e1;
    border: 1px dashed #e5e7eb;
    font-weight: 400;
}

/* 刷新按钮（放在搜索框右侧） */
.appstore-refresh-btn i { margin-right: 4px; }

/* ===== 主站 / 分站 应用商店切换器(挂在标题下方) ===== */
.appstore-tab-switch {
    display: inline-flex;
    gap: 0;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 16px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}
.appstore-tab-switch__item {
    padding: 10px 20px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    user-select: none;
    text-decoration: none;
    position: relative;
    border-right: 1px solid #e2e8f0;
}
.appstore-tab-switch__item:last-child {
    border-right: none;
}
.appstore-tab-switch__item:hover {
    color: #4C7D71;
    background: rgba(76, 125, 113, 0.04);
}
.appstore-tab-switch__item.is-active {
    background: linear-gradient(135deg, #4C7D71 0%, #5a9486 100%);
    color: #ffffff;
    font-weight: 600;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
}
.appstore-tab-switch__item.is-active:hover {
    background: linear-gradient(135deg, #427065 0%, #4C7D71 100%);
}
.appstore-tab-switch__item .fa {
    font-size: 13px;
    transition: transform 0.2s ease;
}
.appstore-tab-switch__item:hover .fa {
    transform: scale(1.1);
}

/* ===== 分站应用商店提示框 ===== */
.merchant-store-notice {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    margin-bottom: 16px;
    font-size: 13px;
    line-height: 1.6;
    color: #4a5568;
    background: linear-gradient(135deg, #fef9e7 0%, #fdf3d6 100%);
    border-left: 4px solid #f59e0b;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(245, 158, 11, 0.08);
}
.merchant-store-notice__icon {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #f59e0b;
    font-size: 16px;
    margin-top: 1px;
}
.merchant-store-notice__content {
    flex: 1;
}

</style>

<div class="admin-page admin-page-appstore">
    <h1 class="admin-page__title">应用商店</h1>

    <!-- 主站 / 分站 切换:跳转两个独立 view(本文件 = 分站) -->
    <div class="appstore-tab-switch" id="appstoreTabSwitch">
        <a class="appstore-tab-switch__item" href="/admin/appstore.php">
            <i class="fa fa-server"></i>主站应用商店
        </a>
        <a class="appstore-tab-switch__item is-active" href="/admin/appstore.php?tab=merchant">
            <i class="fa fa-cubes"></i>分站应用商店
        </a>
    </div>

    <!-- 分站应用商店功能说明 -->
    <div class="merchant-store-notice">
        <div class="merchant-store-notice__icon">
            <i class="fa fa-info-circle"></i>
        </div>
        <div class="merchant-store-notice__content">
            此处下载的应用将上架到分站的应用商店，主站可自定义应用的价格，分站站长使用账号余额购买插件的使用权限。分站应用后续由主站统一管理。
        </div>
    </div>

    <!-- 分类选项卡：服务端直接渲染（"全部" + PluginModel::MERCHANT_PLUGIN_CATEGORIES），分站不展示模板主题 -->
    <div class="em-tabs" id="appstoreTabs">
        <a class="em-tabs__item is-active" data-filter='{"type":"all","id":0}'>
            <i class="fa fa-th-large"></i>全部<em class="em-tabs__count"></em>
        </a>
        <?php foreach (PluginModel::MERCHANT_PLUGIN_CATEGORIES as $__cid => $__cname): ?>
        <a class="em-tabs__item" data-filter='<?= htmlspecialchars(json_encode(['type' => '', 'id' => (int) $__cid]), ENT_QUOTES, 'UTF-8') ?>'>
            <i class="fa fa-folder-o"></i><?= htmlspecialchars((string) $__cname, ENT_QUOTES, 'UTF-8') ?><em class="em-tabs__count"></em>
        </a>
        <?php endforeach; ?>
        <a class="em-tabs__item" data-filter='{"type":"all","id":0,"list_mode":"purchased"}'>
            <i class="fa fa-check-circle"></i>已购买<em class="em-tabs__count"></em>
        </a>
    </div>

    <!-- 工具条：右侧快捷搜索 -->
    <div class="appstore-toolbar">
        <div class="appstore-search">
            <i class="fa fa-search"></i>
            <input type="text" id="appstoreSearch" placeholder="搜索应用名称 / 描述…" autocomplete="off">
            <button type="button" class="appstore-search__clear" id="appstoreSearchClear" title="清空">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <button type="button" class="em-btn em-sm-btn em-reset-btn appstore-refresh-btn" id="appstoreRefreshBtn">
            <i class="fa fa-refresh"></i>刷新
        </button>
    </div>

    <table id="appstoreTable" lay-filter="appstoreTable"></table>
</div>

<!-- 名称 + 类型 tag + 描述 -->
<script type="text/html" id="appstoreTitleTpl">
    <div>
        <div class="appstore-title__row">
            <span class="appstore-type appstore-type--{{ d.type === 'template' ? 'template' : 'plugin' }}">
                <i class="fa {{ d.type === 'template' ? 'fa-paint-brush' : 'fa-puzzle-piece' }}"></i>
                {{ d.type === 'template' ? '模板' : '插件' }}
            </span>
            <span class="appstore-title__name">{{ d.name_cn || d.name_en || '-' }}</span>
        </div>
        <div class="appstore-title__desc" title="{{ (d.content || '').replace(/<[^>]+>/g, '').trim() || '该应用未配置描述信息' }}">
            {{ (d.content || '').replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim() || '该应用未配置描述信息' }}
        </div>
    </div>
</script>

<script type="text/html" id="appstoreInstallTpl">
    <span style="font-family:Menlo,Consolas,monospace;color:#374151;">{{ (Number(d.install_num) || 0).toLocaleString() }}</span>
</script>

<!-- 至尊价格：统一免费 -->
<script type="text/html" id="appstorePriceSupremeTpl">
    <span class="appstore-chip appstore-chip--free" title="至尊会员全场免费">免费</span>
</script>

<!-- SVIP 价格 -->
<script type="text/html" id="appstorePriceSvipTpl">
    {{# if(parseFloat(d.svip_price || 0) <= 0){ }}
        <span class="appstore-chip appstore-chip--free">免费</span>
    {{# } else { }}
        <span class="appstore-chip appstore-chip--paid">
            <span class="appstore-chip__cur">¥</span>{{ parseFloat(d.svip_price).toFixed(2) }}
        </span>
    {{# } }}
</script>

<!-- VIP 价格 -->
<script type="text/html" id="appstorePriceVipTpl">
    {{# if(parseFloat(d.vip_price || 0) <= 0){ }}
        <span class="appstore-chip appstore-chip--free">免费</span>
    {{# } else { }}
        <span class="appstore-chip appstore-chip--paid">
            <span class="appstore-chip__cur">¥</span>{{ parseFloat(d.vip_price).toFixed(2) }}
        </span>
    {{# } }}
</script>

<!--
    操作按钮分支(tab=main / tab=merchant 共用模板,文案按 window.APPSTORE_TAB 切换):
    - 已装/已上架                        → 灰色"已安装"
    - 未装 · 未激活 · 付费                → 紫色"先激活授权"
    - 未装 · 免费                         → 蓝色"安装" / "采购上架"
    - 未装 · 付费                         → 红色"购买 ¥my_price" / "采购 ¥my_price"
-->
<script type="text/html" id="appstoreActionTpl">
    {{# var tab = window.APPSTORE_TAB || 'main';
       var L = tab === 'merchant'
           ? { installed: '已安装', install: '安装', buy: '采购' }
           : { installed: '已安装', install: '安装',     buy: '购买' };
       var effectiveFree = (d.is_free == 1) || (parseFloat(d.my_price || 0) <= 0); }}
    {{# if (d.is_installed == 1) { }}
        <a class="em-btn em-sm-btn em-reset-btn em-disabled-btn"><i class="fa fa-check"></i>{{ L.installed }}</a>
    {{# } else if (effectiveFree) { }}
        <a class="em-btn em-sm-btn em-save-btn" lay-event="install"><i class="fa fa-download"></i>{{ L.install }}</a>
    {{# } else if (!window.APPSTORE_LICENSED) { }}
        <a class="em-btn em-sm-btn em-purple-btn" lay-event="needLicense"><i class="fa fa-shield"></i>先激活授权</a>
    {{# } else { }}
        <a class="em-btn em-sm-btn em-red-btn" lay-event="buy"><i class="fa fa-shopping-cart"></i>{{ L.buy }} ¥{{ parseFloat(d.my_price || 0).toFixed(2) }}</a>
    {{# } }}
</script>

<script>
// 资源 host（由 PHP 注入）：始终取 license_urls[0].url，不跟随线路切换
var APPSTORE_ASSET_HOST = <?= json_encode($appstoreAssetHost, JSON_UNESCAPED_SLASHES) ?>;
// 当前站点是否已激活授权（templet 通过 window.APPSTORE_LICENSED 读取）
window.APPSTORE_LICENSED = <?= $appstoreLicensed ? 'true' : 'false' ?>;
// CSRF token（安装 action 校验）
var APPSTORE_CSRF = <?= json_encode($csrfToken) ?>;
// 当前 tab(main / merchant):决定调服务端哪个货架接口、装到主站本地还是落分站市场
// templet 通过 window.APPSTORE_TAB 读取以切换按钮文案
window.APPSTORE_TAB = 'merchant';

function appstoreAbsUrl(url) {
    if (!url) return '';
    if (/^https?:\/\//i.test(url)) return url;
    return APPSTORE_ASSET_HOST + (url.charAt(0) === '/' ? '' : '/') + url;
}

$(function () {
    layui.use(['layer', 'table', 'util'], function () {
        var layer = layui.layer;
        var table = layui.table;

        var $tabs = $('#appstoreTabs');

        // ---------- 当前 Tab 过滤参数 ----------
        function currentFilter() {
            var raw = $tabs.find('.em-tabs__item.is-active').attr('data-filter');
            try { return JSON.parse(raw || '{}'); } catch (e) { return { type: 'all', id: 0, list_mode: '' }; }
        }
        function buildWhere() {
            var f = currentFilter();
            var where = {
                keyword: ($('#appstoreSearch').val() || '').trim(),
                tab:     window.APPSTORE_TAB || 'main'
            };
            if (String(f.list_mode || '') === 'purchased') {
                where.list_mode = 'purchased';
                return where;
            }
            if (f.type && f.type !== 'all') where.type = f.type;
            if (!f.type && f.id > 0)        where.category_id = f.id;
            return where;
        }

        // ---------- 服务端分页表格 ----------
        table.render({
            elem: '#appstoreTable',
            id: 'appstoreTableId',
            url: '/admin/appstore.php?_action=list',
            method: 'GET',
            where: buildWhere(),
            page: true,
            limit: 10,
            limits: [10, 20, 50],
            cellMinWidth: 80,
            lineStyle: 'height: 62px;',
            parseData: function (res) {
                var d = res && res.data ? res.data : {};
                return {
                    code: res.code === 200 ? 0 : (res.code || 500),
                    msg:  res.msg || '',
                    count: d.count || 0,
                    data:  d.list || []
                };
            },
            request: { pageName: 'page', limitName: 'limit' },
            cols: [[
                {
                    field: 'cover', title: '封面', width: 80, align: 'center', unresize: true,
                    templet: function (d) {
                        if (!d.cover) {
                            return '<span class="appstore-cover appstore-cover--empty"><i class="fa fa-cube"></i></span>';
                        }
                        var imgs = (Array.isArray(d.images) && d.images.length > 0 ? d.images : [d.cover]).map(appstoreAbsUrl);
                        return '<img class="appstore-cover appstore-cover--zoom" src="' + appstoreAbsUrl(d.cover) +
                               '" alt="" data-imgs="' + encodeURIComponent(JSON.stringify(imgs)) + '">';
                    }
                },
                { field: 'name_cn', title: '应用名称', minWidth: 240, templet: '#appstoreTitleTpl' },
                { field: 'install_num', title: '安装量', width: 100, templet: '#appstoreInstallTpl', align: 'center', sort: true },
                { title: '至尊授权', width: 120, templet: '#appstorePriceSupremeTpl', align: 'center' },
                { field: 'svip_price', title: 'SVIP 授权', width: 130, templet: '#appstorePriceSvipTpl', align: 'center' },
                { field: 'vip_price', title: 'VIP 授权', width: 130, templet: '#appstorePriceVipTpl', align: 'center' },
                { title: '操作', width: 200, align: 'center', toolbar: '#appstoreActionTpl' }
            ]]
        });

        function reloadTable() {
            table.reload('appstoreTableId', {
                where: buildWhere(),
                page: { curr: 1 }
            });
        }
        function refreshTable() {
            table.reload('appstoreTableId', {
                where: buildWhere()
            });
        }

        // ---------- em-tabs 切换 ----------
        $tabs.on('click', '.em-tabs__item', function () {
            var $item = $(this);
            if ($item.hasClass('is-active')) return;
            $item.addClass('is-active').siblings().removeClass('is-active');
            reloadTable();
        });

        // ---------- 搜索（输入即过滤，防抖 300ms） + 清空按钮 ----------
        var searchTimer;
        $('#appstoreSearch').on('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(reloadTable, 300);
        });
        $('#appstoreSearchClear').on('click', function () {
            $('#appstoreSearch').val('').trigger('input').focus();
        });
        $('#appstoreRefreshBtn').on('click', function () {
            refreshTable();
        });

        // ---------- 封面点击放大（Viewer.js，全局已加载） ----------
        $(document).off('click.appstoreCover').on('click.appstoreCover', '.appstore-cover--zoom', function () {
            var raw = $(this).attr('data-imgs');
            var imgs = [];
            try { imgs = JSON.parse(decodeURIComponent(raw || '')); } catch (e) {}
            if (!imgs.length) return;

            var $container = $('<div style="display:none;"></div>');
            imgs.forEach(function (url) { $container.append('<img src="' + url + '">'); });
            $('body').append($container);

            var viewer = new Viewer($container[0], {
                navbar: imgs.length > 1,
                title: false,
                toolbar: true,
                hidden: function () { viewer.destroy(); $container.remove(); }
            });
            viewer.show();
        });

        // ---------- 安装 ----------
        function installApp(d) {
            var displayName = d.name_cn || d.name_en || d.id;
            var typeLabel = d.type === 'template' ? '模板' : '插件';
            var actionLabel = '安装';
            var loadingIdx = layer.load(2, { shade: [0.3, '#000'] });
            $.post('/admin/appstore.php', {
                _action:    'install',
                csrf_token: APPSTORE_CSRF,
                name:       d.name_en,
                type:       d.type === 'template' ? 'template' : 'plugin',
                file_path:  d.file_path || '',
                version:    d.version || '',
                // tab=merchant 时后端会走 MainAppPurchaseService 落 em_app_market
                tab:           window.APPSTORE_TAB || 'main',
                cost_per_unit: Math.round((parseFloat(d.my_price || 0)) * 1000000),
                remote_app_id: d.id || 0
            }).done(function (res) {
                layer.close(loadingIdx);
                if (res && (res.code === 200 || res.code === 0)) {
                    if (res.data && res.data.csrf_token) APPSTORE_CSRF = res.data.csrf_token;
                    layer.msg(typeLabel + actionLabel + '成功：' + displayName);
                    reloadTable();
                } else {
                    layer.msg((res && res.msg) || (actionLabel + '失败'));
                }
            }).fail(function (xhr) {
                layer.close(loadingIdx);
                var msg = actionLabel + '请求失败';
                try {
                    var j = JSON.parse(xhr.responseText || '{}');
                    if (j && j.msg) msg = j.msg;
                } catch (e) {}
                layer.msg(msg);
            });
        }

        function escapeHtml(text) {
            return String(text == null ? '' : text).replace(/[&<>"']/g, function (char) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char];
            });
        }

        function parsePaymentCodes(payment) {
            var raw = Array.isArray(payment) ? payment.join(',') : String(payment || '');
            if (!raw) return [];
            var seen = {};
            var codes = [];
            raw.split(',').forEach(function (item) {
                var code = $.trim(String(item || '')).toLowerCase();
                if (!code || seen[code]) return;
                seen[code] = true;
                codes.push(code);
            });
            return codes;
        }

        function ensurePayDialogStyle() {
            if (document.getElementById('appstorePayDialogStyle')) return;
            var css = ''
                + '.appstore-pay-dialog{padding:12px 14px 8px;}'
                + '.appstore-pay-dialog__meta{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px 12px;margin-bottom:12px;}'
                + '.appstore-pay-dialog__line{display:flex;justify-content:space-between;align-items:center;gap:10px;font-size:13px;color:#334155;line-height:1.8;}'
                + '.appstore-pay-dialog__line strong{font-weight:600;color:#0f172a;}'
                + '.appstore-pay-dialog__line--amount strong{color:#dc2626;font-size:16px;font-family:Menlo,Consolas,monospace;}'
                + '.appstore-pay-dialog__title{font-size:13px;color:#475569;margin:2px 0 10px;}'
                + '.appstore-pay-dialog__methods{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-bottom:12px;}'
                + '.appstore-pay-dialog__method{position:relative;display:flex;align-items:center;gap:8px;border:1px solid #e2e8f0;border-radius:10px;padding:10px;cursor:pointer;background:#fff;transition:all .15s ease;}'
                + '.appstore-pay-dialog__method:hover{border-color:#c7d2fe;background:#f8faff;}'
                + '.appstore-pay-dialog__method.is-active{border-color:#6366f1;background:#eef2ff;box-shadow:0 1px 8px rgba(99,102,241,.18);}'
                + '.appstore-pay-dialog__icon{width:26px;height:26px;object-fit:contain;flex-shrink:0;}'
                + '.appstore-pay-dialog__name{font-size:13px;color:#0f172a;font-weight:500;}'
                + '.appstore-pay-dialog__empty{padding:16px 10px;text-align:center;font-size:12px;color:#94a3b8;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:10px;margin-bottom:12px;}'
                + '.appstore-pay-dialog__actions{display:flex;justify-content:flex-end;gap:8px;}'
                + '.appstore-pay-dialog__confirm{min-width:190px;}'
                + '.appstore-pay-dialog__confirm-price{font:inherit;margin-left:6px;}';
            $('head').append('<style id="appstorePayDialogStyle">' + css + '</style>');
        }

        function openPaymentMethodDialog(orderData, app) {
            ensurePayDialogStyle();
            var paymentMeta = {
                alipay: { name: '支付宝', icon: '/content/static/img/alipay.png' },
                wxpay:  { name: '微信支付', icon: '/content/static/img/wxpay.png' },
                trx:    { name: 'TRX', icon: '/content/static/img/trx.png' },
                usdt:   { name: 'USDT', icon: '/content/static/img/usdt.png' }
            };
            var orderNo = String((orderData && orderData.out_trade_no) || '');
            var amount = String((orderData && orderData.amount) || '');
            var amountNum = parseFloat(amount);
            var buttonPrice = !isNaN(amountNum) ? ('¥' + amountNum.toFixed(2)) : '¥--';
            var subject = String((orderData && orderData.subject) || (app && (app.name_cn || app.name_en)) || '');
            var codes = parsePaymentCodes(orderData && orderData.payment);
            var selectedCode = codes.length ? codes[0] : '';

            var methodsHtml = '';
            if (!codes.length) {
                methodsHtml = '<div class="appstore-pay-dialog__empty">当前订单未返回可用支付方式</div>';
            } else {
                methodsHtml = codes.map(function (code) {
                    var meta = paymentMeta[code] || {};
                    var name = meta.name || code.toUpperCase();
                    var icon = meta.icon || '';
                    return '<div class="appstore-pay-dialog__method' + (selectedCode === code ? ' is-active' : '') + '" data-code="' + escapeHtml(code) + '" data-name="' + escapeHtml(name) + '">'
                        + '<img class="appstore-pay-dialog__icon" src="' + escapeHtml(icon) + '" alt="">'
                        + '<span class="appstore-pay-dialog__name">' + escapeHtml(name) + '</span>'
                        + '</div>';
                }).join('');
            }

            var html = ''
                + '<div class="appstore-pay-dialog">'
                +   '<div class="appstore-pay-dialog__meta">'
                +     '<div class="appstore-pay-dialog__line"><span>订单号</span><strong>' + escapeHtml(orderNo || '-') + '</strong></div>'
                +     '<div class="appstore-pay-dialog__line"><span>应用</span><strong>' + escapeHtml(subject || '-') + '</strong></div>'
                +     '<div class="appstore-pay-dialog__line appstore-pay-dialog__line--amount"><span>金额</span><strong>¥' + escapeHtml(amount || '--') + '</strong></div>'
                +   '</div>'
                +   '<div class="appstore-pay-dialog__title">请选择支付方式</div>'
                +   '<div class="appstore-pay-dialog__methods">' + methodsHtml + '</div>'
                +   '<div class="appstore-pay-dialog__actions">'
                +     '<button type="button" class="em-btn em-save-btn appstore-pay-dialog__confirm"' + (!codes.length ? ' disabled' : '') + '>'
                +       '<i class="fa fa-shopping-cart"></i>立即购买'
                +       '<span class="appstore-pay-dialog__confirm-price">' + escapeHtml(buttonPrice) + '</span>'
                +     '</button>'
                +   '</div>'
                + '</div>';

            layer.open({
                type: 1,
                title: '选择支付方式',
                skin: 'admin-modal appstore-pay-modal',
                area: [window.innerWidth >= 640 ? '480px' : '92%', 'auto'],
                shadeClose: false,
                maxmin: false,
                content: html,
                success: function (layero, index) {
                    var $layer = $(layero);
                    $layer.on('click', '.appstore-pay-dialog__method', function () {
                        var $item = $(this);
                        selectedCode = String($item.data('code') || '');
                        $item.addClass('is-active').siblings('.appstore-pay-dialog__method').removeClass('is-active');
                    });
                    $layer.on('click', '.appstore-pay-dialog__confirm', function () {
                        if (!selectedCode) {
                            layer.msg('请选择支付方式');
                            return;
                        }
                        if (!orderNo) {
                            layer.msg('订单号缺失，无法跳转支付');
                            return;
                        }
                        var payUrl = appstoreAbsUrl(
                            '/api/app_pay_entry.php?out_trade_no=' + encodeURIComponent(orderNo)
                            + '&pay_method=' + encodeURIComponent(selectedCode)
                        );
                        // Safari 等浏览器在 noopener/noreferrer 场景可能返回 null（即使已成功打开新标签），
                        // 先打开 about:blank 再赋值 URL，避免误判导致当前页也跳转。
                        var payWin = window.open('about:blank', '_blank');
                        if (payWin) {
                            try { payWin.opener = null; } catch (e) {}
                            payWin.location.href = payUrl;
                        } else {
                            window.location.href = payUrl;
                        }
                    });
                }
            });
        }

        // ---------- 购买：创建订单 ----------
        function createOrder(app) {
            var id = parseInt(app.id, 10) || 0;
            if (!id) { layer.msg('应用标识缺失，无法发起购买', { icon: 2 }); return; }
            var loadingIdx = layer.load(2, { shade: [0.3, '#000'] });
            $.post('/admin/appstore.php', {
                _action: 'app_buy',
                csrf_token: APPSTORE_CSRF,
                app_id: id,
                tab: window.APPSTORE_TAB || 'main'
            }).done(function (res) {
                layer.close(loadingIdx);
                if (res && (res.code === 200 || res.code === 0)) {
                    if (res.data && res.data.csrf_token) APPSTORE_CSRF = res.data.csrf_token;
                    openPaymentMethodDialog((res && res.data) || {}, app);
                } else {
                    layer.msg((res && res.msg) || '创建订单失败');
                }
            }).fail(function (xhr) {
                layer.close(loadingIdx);
                var msg = '创建订单请求失败';
                try {
                    var j = JSON.parse(xhr.responseText || '{}');
                    if (j && j.msg) msg = j.msg;
                } catch (e) {}
                layer.msg(msg);
            });
        }

        // ---------- 行操作 ----------
        table.on('tool(appstoreTable)', function (obj) {
            var d = obj.data;
            if (obj.event === 'install')          installApp(d);
            else if (obj.event === 'buy')         createOrder(d);
            else if (obj.event === 'needLicense') {
                layer.confirm('付费应用需先激活正版授权。是否前往激活？', { icon: 3, title: '提示' }, function (idx) {
                    layer.close(idx);
                    if ($.pjax) $.pjax({ url: '/admin/license.php', container: '#adminContent' });
                    else location.href = '/admin/license.php';
                });
            }
        });

        // ---------- 主站 / 分站 应用商店切换 ----------
        // 物理拆分两个 view 后,切换走 PJAX 跳转(失败回退整页跳转);本 view 是分站
        $('#appstoreTabSwitch').on('click', '.appstore-tab-switch__item', function (e) {
            e.preventDefault();
            var $item = $(this);
            if ($item.hasClass('is-active')) return;
            var url = $item.attr('href');
            if ($.pjax) $.pjax({ url: url, container: '#adminContent' });
            else        location.href = url;
        });
    });
});
</script>
