<?php
/** @var array<string, mixed> $frontUser */
/** @var array<string, mixed> $currentMerchant */
/** @var array<string, mixed>|null $merchantLevel */
/** @var array<string, mixed> $uc */
/** @var string $currentTab */
/** @var bool $allowSelf */

$csrfToken = Csrf::token();
?>
<div class="mc-page">
    <div class="mc-page-header" style="display:flex;align-items:center;justify-content:space-between;">
        <div>
            <h2 class="mc-page-title">商品管理</h2>
            <p class="mc-page-desc">管理主站推送到本店的主站商品 / 本店自建商品</p>
        </div>
    </div>

    <!-- Tab 切换（卡片式胶囊，替换 layui-tab） -->
    <div class="mc-goods-tabs">
        <button type="button" class="mc-goods-tab <?= $currentTab === 'ref' ? 'is-active' : '' ?>" data-tab="ref">
            <i class="fa fa-share-square-o"></i> 主站商品
        </button>
        <button type="button" class="mc-goods-tab <?= $currentTab === 'self' ? 'is-active' : '' ?>" data-tab="self">
            <i class="fa fa-cube"></i> 自建商品
            <?php if (!$allowSelf): ?>
            <span class="mc-goods-tab__off">权限关闭</span>
            <?php endif; ?>
        </button>
    </div>

    <!-- 主站商品 Tab -->
    <div id="mcRefPane" style="<?= $currentTab === 'ref' ? '' : 'display:none;' ?>">
        <div class="mc-card">
            <div class="mc-filter-bar">
                <input type="text" class="mc-input mc-input--search" id="mcRefKeyword" placeholder="搜索商品名 / 编码">
                <select class="mc-input mc-input--select" id="mcRefStatus">
                    <option value="">全部状态</option>
                    <option value="1">已上架</option>
                    <option value="0">已下架</option>
                </select>
                <button type="button" class="mc-btn mc-btn--primary" id="mcRefSearchBtn"><i class="fa fa-search"></i> 搜索</button>
                <button type="button" class="mc-btn" id="mcRefResetBtn"><i class="fa fa-rotate-left"></i> 重置</button>
            </div>

            <div id="mcRefDiscountTip" class="mc-discount-tip"></div>

            <table id="mcRefTable" lay-filter="mcRefTable"></table>
        </div>
    </div>

    <!-- 自建商品 Tab -->
    <div id="mcSelfPane" style="<?= $currentTab === 'self' ? '' : 'display:none;' ?>">
        <?php if (!$allowSelf): ?>
        <div class="mc-card mc-card--empty">
            <i class="fa fa-lock mc-empty-icon"></i>
            <div class="mc-empty-title">当前商户等级不允许上架自建商品</div>
            <div class="mc-empty-desc">如需开启，请升级商户等级或联系管理员</div>
        </div>
        <?php else: ?>
        <div class="mc-card">
            <div class="mc-filter-bar mc-filter-bar--split">
                <div class="mc-filter-bar__left">
                    <input type="text" class="mc-input mc-input--search" id="mcSelfKeyword" placeholder="搜索商品名 / 编码">
                    <select class="mc-input mc-input--select" id="mcSelfStatus">
                        <option value="">全部状态</option>
                        <option value="1">已上架</option>
                        <option value="0">已下架</option>
                    </select>
                    <button type="button" class="mc-btn mc-btn--primary" id="mcSelfSearchBtn"><i class="fa fa-search"></i> 搜索</button>
                    <button type="button" class="mc-btn" id="mcSelfResetBtn"><i class="fa fa-rotate-left"></i> 重置</button>
                </div>
                <button type="button" class="mc-btn mc-btn--primary" id="mcSelfAddBtn">
                    <i class="fa fa-plus"></i> 新建商品
                </button>
            </div>

            <!-- 批量操作工具条：表格勾选后出现，提供批量上下架 / 删除 / 推荐 -->
            <div class="mc-self-batch-bar" id="mcSelfBatchBar" style="display:none;padding:10px 12px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;margin-bottom:10px;">
                <span style="color:#374151;font-size:13px;margin-right:10px;">已选 <b id="mcSelfBatchCount">0</b> 项</span>
                <button type="button" class="mc-btn" data-batch="on_sale"><i class="fa fa-arrow-up"></i> 上架</button>
                <button type="button" class="mc-btn" data-batch="off_sale"><i class="fa fa-arrow-down"></i> 下架</button>
                <button type="button" class="mc-btn" data-batch="recommend"><i class="fa fa-star"></i> 推荐</button>
                <button type="button" class="mc-btn" data-batch="unrecommend"><i class="fa fa-star-o"></i> 取消推荐</button>
                <button type="button" class="mc-btn" style="color:#dc2626;border-color:#fecaca;" data-batch="delete"><i class="fa fa-trash"></i> 删除</button>
                <button type="button" class="mc-btn" id="mcSelfBatchClear" style="margin-left:auto;"><i class="fa fa-times"></i> 清空选择</button>
            </div>

            <table id="mcSelfTable" lay-filter="mcSelfTable"></table>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* ===== 商品管理页美化 ===== */
.mc-goods-tabs {
    display:flex; gap:4px; margin-bottom:14px;
    background:#fff; padding:6px; border-radius:10px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    width:fit-content;
}
.mc-goods-tab {
    padding:8px 18px; border:0; border-radius:6px;
    background:transparent; color:#555; font-size:13px; cursor:pointer;
    display:inline-flex; align-items:center; gap:6px;
    transition: background .15s, color .15s;
}
.mc-goods-tab:hover { background:#f5f7fa; color:#333; }
.mc-goods-tab.is-active { background:#eef2ff; color:#4e6ef2; font-weight:500; }
.mc-goods-tab__off {
    margin-left:4px; padding:1px 6px; border-radius:8px;
    background:#f3f4f6; color:#9ca3af; font-size:11px;
}

.mc-card {
    background:#fff; border-radius:10px; padding:16px 18px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.mc-card--empty {
    text-align:center; padding:56px 20px;
}
.mc-empty-icon { font-size:36px; color:#d1d5db; margin-bottom:14px; display:block; }
.mc-empty-title { color:#374151; font-size:14px; margin-bottom:6px; }
.mc-empty-desc { color:#9ca3af; font-size:12px; }

.mc-filter-bar {
    display:flex; gap:10px; flex-wrap:wrap; align-items:center;
    margin-bottom:14px;
}
.mc-filter-bar--split {
    justify-content: space-between;
}
.mc-filter-bar__left { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }

.mc-input {
    display:inline-block; height:34px; box-sizing:border-box;
    padding:0 12px; font-size:13px; color:#1f2937;
    border:1px solid #e5e7eb; border-radius:6px; outline:none;
    background:#fff; font-family:inherit;
    transition: border-color .15s, box-shadow .15s;
}
.mc-input:focus { border-color:#4e6ef2; box-shadow: 0 0 0 3px rgba(78,110,242,0.08); }
.mc-input--search { width:240px; }
.mc-input--select { width:150px; background-image:none; }

.mc-btn {
    display:inline-flex; align-items:center; gap:6px;
    height:34px; padding:0 14px;
    border:1px solid #e5e7eb; border-radius:6px;
    background:#fff; color:#555; font-size:13px; cursor:pointer;
    transition:border-color .15s, color .15s, background .15s;
}
.mc-btn:hover:not(:disabled) { border-color:#4e6ef2; color:#4e6ef2; }
.mc-btn--primary { background:#4e6ef2; border-color:#4e6ef2; color:#fff; }
.mc-btn--primary:hover { background:#3d5bd9; border-color:#3d5bd9; color:#fff; }
.mc-btn:disabled { color:#9ca3af; cursor:not-allowed; }

.mc-discount-tip {
    padding:10px 12px; margin-bottom:12px;
    background:#f0f9ff; border-left:3px solid #38bdf8; border-radius:4px;
    color:#0c4a6e; font-size:12px; line-height:1.7;
}
.mc-discount-tip:empty { display:none; }
</style>

<!-- 行模板 -->
<script type="text/html" id="mcRefCoverTpl">
    {{# if(d.cover_image){ }}
        <img src="{{ d.cover_image }}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">
    {{# } else { }}
        <span style="color:#ccc">无图</span>
    {{# } }}
</script>

<script type="text/html" id="mcRefTitleTpl">
    <div style="text-align:left;line-height:1.4;">
        <div style="font-weight:500;">{{ d.title }}</div>
    </div>
</script>

<script type="text/html" id="mcRefBaseTpl">
    {{# if(d.max_base_price_view && d.max_base_price_view !== d.base_price_view){ }}
        {{ d.base_price_view }} ~ {{ d.max_base_price_view }}
    {{# } else { }}
        {{ d.base_price_view }}
    {{# } }}
</script>

<script type="text/html" id="mcRefCostTpl">
    <span style="color:#8b5cf6;">{{ d.cost_view }}{{# if(d.max_cost_view){ }} ~ {{ d.max_cost_view }}{{# } }}</span>
</script>

<script type="text/html" id="mcRefMarkupTpl">
    {{ d.markup_rate_view }}%
</script>

<script type="text/html" id="mcRefSellTpl">
    <span style="color:#f59e0b;font-weight:600;">{{ d.sell_view }}{{# if(d.max_sell_view){ }} ~ {{ d.max_sell_view }}{{# } }}</span>
</script>

<script type="text/html" id="mcRefSaleTpl">
    <input type="checkbox" name="on_sale" value="{{d.goods_id}}" lay-skin="switch" lay-text="上架|下架" lay-filter="mcRefSaleFilter" {{ d.is_on_sale == 1 ? 'checked' : '' }}>
</script>

<script type="text/html" id="mcRefRecTpl">
    {{# if (d.ref_is_recommended === null || typeof d.ref_is_recommended === 'undefined') { }}
        {{# if (d.goods_is_recommended == 1) { }}
            <span style="color:#fa5252;">推荐</span>
            <span style="color:#9ca3af;font-size:11px;">(跟随)</span>
        {{# } else { }}
            <span style="color:#9ca3af;">—</span>
        {{# } }}
    {{# } else if (d.ref_is_recommended == 1) { }}
        <span style="color:#fa5252;font-weight:600;">推荐</span>
        <span style="color:#9ca3af;font-size:11px;">(覆盖)</span>
    {{# } else { }}
        <span style="color:#9ca3af;">不推荐</span>
        <span style="color:#9ca3af;font-size:11px;">(覆盖)</span>
    {{# } }}
</script>

<script type="text/html" id="mcRefActionTpl">
    <a class="layui-btn layui-btn-sm layui-btn-normal" lay-event="edit"><i class="fa fa-pencil"></i> 编辑</a>
    {{# if (d.ref_id) { }}
    <a class="layui-btn layui-btn-sm" lay-event="reset" title="恢复为默认的加价率 / 上架状态"><i class="fa fa-rotate-left"></i> 恢复默认</a>
    {{# } }}
</script>

<script type="text/html" id="mcSelfSaleTpl">
    <input type="checkbox" name="on_sale" value="{{d.id}}" lay-skin="switch" lay-text="上架|下架" lay-filter="mcSelfSaleFilter" {{ d.is_on_sale == 1 ? 'checked' : '' }}>
</script>

<script type="text/html" id="mcSelfActionTpl">
    <a class="layui-btn layui-btn-sm" lay-event="edit"><i class="fa fa-pencil"></i> 编辑</a>
    <a class="layui-btn layui-btn-sm layui-btn-normal" lay-event="stock"><i class="fa fa-cube"></i> 库存</a>
    <a class="layui-btn layui-btn-sm layui-btn-danger" lay-event="del"><i class="fa fa-trash"></i> 删除</a>
</script>

<script type="text/html" id="mcSelfPriceTpl">
    {{# if(d.min_price == d.max_price){ }}
        <span>{{ d.min_price_view }}</span>
    {{# } else { }}
        <span>{{ d.min_price_view }} ~ {{ d.max_price_view }}</span>
    {{# } }}
</script>

<script>
$(function(){
    'use strict';
    var csrfToken = <?php echo json_encode($csrfToken); ?>;

    // PJAX 防重复绑定：清掉历史 .mcGoodsPage handler，下面所有 $(document).on 都带这个命名空间
    $(document).off('.mcGoodsPage');

    layui.use(['layer', 'form', 'table', 'element'], function () {
        var layer = layui.layer;
        var form = layui.form;
        var table = layui.table;
        var element = layui.element;

        form.render('select');

        // Tab 切换（不刷新页面）
        $(document).on('click.mcGoodsPage', '.mc-goods-tab', function () {
            var tab = $(this).data('tab');
            $('.mc-goods-tab').removeClass('is-active');
            $(this).addClass('is-active');
            $('#mcRefPane').toggle(tab === 'ref');
            $('#mcSelfPane').toggle(tab === 'self');
            // 切到 tab 时惰性加载
            if (tab === 'ref' && !refLoaded) renderRef();
            if (tab === 'self' && !selfLoaded) renderSelf();
        });

        // ========== 主站商品 ==========
        var refLoaded = false;
        function renderRef() {
            refLoaded = true;
            table.render({
                elem: '#mcRefTable',
                id: 'mcRefTableId',
                url: '/user/merchant/goods.php',
                method: 'POST',
                where: refWhere(),
                page: true,
                limit: 20,
                toolbar: false,
                defaultToolbar: [],
                lineStyle: 'height: 55px;',
                cols: [[
                    {title: '图', width: 60, templet: '#mcRefCoverTpl', align: 'center'},
                    {field: 'title', title: '商品', minWidth: 220, templet: '#mcRefTitleTpl'},
                    {field: 'markup_rate', title: '加价率', minWidth: 90, templet: '#mcRefMarkupTpl', align: 'center'},
                    {title: '推荐', width: 120, templet: '#mcRefRecTpl', align: 'center'},
                    {title: '状态', width: 100, templet: '#mcRefSaleTpl', align: 'center'},
                    {title: '操作', width: 190, templet: '#mcRefActionTpl', align: 'center'}
                ]],
                parseData: function (res) {
                    if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                    // 拿货成本 / 加价率提示（v1.3+：商户拿货统一按主站原价；不再有"店主等级折扣"）
                    $('#mcRefDiscountTip').html(
                        '<i class="fa fa-info-circle"></i> 拿货成本 = 主站原价（无折扣）；店内售价 = 主站原价 × (1 + 本店加价率)'
                    );
                    return {
                        'code': res.code === 200 ? 0 : res.code,
                        'msg': res.msg,
                        'data': res.data ? res.data.data : [],
                        'count': res.data ? res.data.total : 0
                    };
                }
            });
        }
        function refWhere() {
            return {
                _action: 'list_ref',
                keyword: $('#mcRefKeyword').val() || '',
                is_on_sale: $('#mcRefStatus').val() || ''
            };
        }
        $(document).on('click.mcGoodsPage', '#mcRefSearchBtn', function () {
            if (!refLoaded) renderRef();
            else table.reload('mcRefTableId', {page: {curr: 1}, where: refWhere()});
        });
        $(document).on('click.mcGoodsPage', '#mcRefResetBtn', function () {
            $('#mcRefKeyword').val('');
            $('#mcRefStatus').val(''); form.render('select');
            if (!refLoaded) renderRef();
            else table.reload('mcRefTableId', {page: {curr: 1}, where: refWhere()});
        });

        form.on('switch(mcRefSaleFilter)', function (obj) {
            var goodsId = this.value;
            $.ajax({
                url: '/user/merchant/goods.php',
                type: 'POST', dataType: 'json',
                data: {csrf_token: csrfToken, _action: 'toggle_ref_sale', goods_id: goodsId},
                success: function (res) {
                    if (res.code === 200) {
                        csrfToken = res.data && res.data.csrf_token ? res.data.csrf_token : csrfToken;
                        layer.msg(res.msg || '已更新');
                    } else {
                        obj.elem.checked = !obj.elem.checked;
                        form.render('switch');
                        layer.msg(res.msg || '更新失败');
                    }
                },
                error: function () {
                    obj.elem.checked = !obj.elem.checked;
                    form.render('switch');
                    layer.msg('网络异常');
                }
            });
        });

        table.on('tool(mcRefTable)', function (obj) {
            var data = obj.data;
            if (obj.event === 'edit') {
                openRefEditPopup(data.goods_id);
            } else if (obj.event === 'reset') {
                layer.confirm('恢复为默认的加价率和上架状态？', function (idx) {
                    $.ajax({
                        url: '/user/merchant/goods.php',
                        type: 'POST', dataType: 'json',
                        data: {csrf_token: csrfToken, _action: 'reset_ref', goods_id: data.goods_id},
                        success: function (res) {
                            if (res.code === 200) {
                                csrfToken = res.data && res.data.csrf_token ? res.data.csrf_token : csrfToken;
                                layer.msg(res.msg || '已恢复默认');
                                table.reload('mcRefTableId');
                            } else { layer.msg(res.msg || '恢复失败'); }
                        },
                        error: function () { layer.msg('网络异常'); },
                        complete: function () { layer.close(idx); }
                    });
                });
            }
        });

        function openRefEditPopup(goodsId) {
            layer.open({
                type: 2,
                title: '编辑主站商品',
                skin: 'admin-modal',
                maxmin: true,
                area: [window.innerWidth >= 800 ? '520px' : '95%', window.innerHeight >= 700 ? '520px' : '88%'],
                shadeClose: true,
                content: '/user/merchant/goods.php?_popup=ref_edit&goods_id=' + encodeURIComponent(goodsId),
                end: function () {
                    if (window._mcRefPopupSaved) {
                        window._mcRefPopupSaved = false;
                        table.reload('mcRefTableId');
                    }
                }
            });
        }
        window.updateCsrf = function (t) { if (t) csrfToken = t; };

        // ========== 自建商品 ==========
        var selfLoaded = false;
        function renderSelf() {
            if (!<?= $allowSelf ? 'true' : 'false' ?>) return;
            selfLoaded = true;
            table.render({
                elem: '#mcSelfTable',
                id: 'mcSelfTableId',
                url: '/user/merchant/goods.php',
                method: 'POST',
                where: selfWhere(),
                page: true,
                limit: 20,
                lineStyle: 'height: 55px;',
                cols: [[
                    {type: 'checkbox'},
                    {field: 'title', title: '商品', minWidth: 260, templet: function (d) {
                        return '<div style="text-align:left;line-height:1.4;">'
                             + '<div style="font-weight:500;">' + d.title + '</div></div>';
                    }},
                    {title: '价格', minWidth: 120, templet: '#mcSelfPriceTpl', align: 'right'},
                    {field: 'type_label', title: '类型', minWidth: 100, align: 'center'},
                    {field: 'total_stock', title: '库存', width: 80, align: 'center'},
                    {field: 'total_sales', title: '销量', width: 80, align: 'center'},
                    {title: '状态', width: 100, templet: '#mcSelfSaleTpl', align: 'center'},
                    {title: '操作', width: 250, templet: '#mcSelfActionTpl', align: 'center'}
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
        }
        function selfWhere() {
            return {
                _action: 'list_self',
                keyword: $('#mcSelfKeyword').val() || '',
                is_on_sale: $('#mcSelfStatus').val() || ''
            };
        }
        $(document).on('click.mcGoodsPage', '#mcSelfSearchBtn', function () {
            if (!selfLoaded) renderSelf();
            else table.reload('mcSelfTableId', {page: {curr: 1}, where: selfWhere()});
        });
        $(document).on('click.mcGoodsPage', '#mcSelfResetBtn', function () {
            $('#mcSelfKeyword').val('');
            $('#mcSelfStatus').val(''); form.render('select');
            if (!selfLoaded) renderSelf();
            else table.reload('mcSelfTableId', {page: {curr: 1}, where: selfWhere()});
        });

        form.on('switch(mcSelfSaleFilter)', function (obj) {
            var id = this.value;
            $.ajax({
                url: '/user/merchant/goods.php',
                type: 'POST', dataType: 'json',
                data: {csrf_token: csrfToken, _action: 'toggle_self_sale', id: id},
                success: function (res) {
                    if (res.code === 200) {
                        csrfToken = res.data && res.data.csrf_token ? res.data.csrf_token : csrfToken;
                        layer.msg(res.msg || '已更新');
                    } else {
                        obj.elem.checked = !obj.elem.checked;
                        form.render('switch');
                        layer.msg(res.msg || '更新失败');
                    }
                },
                error: function () {
                    obj.elem.checked = !obj.elem.checked;
                    form.render('switch');
                    layer.msg('网络异常');
                }
            });
        });

        // 打开自建商品新建/编辑 iframe 弹窗（id=0 为新建）
        // 弹窗内提交成功会写 window._mcSelfSaved = true；这里在关闭时检测并 reload 表格
        function openSelfEditPopup(id) {
            window._mcSelfSaved = false;
            var url = '/user/merchant/goods.php?_popup=self_edit' + (id ? '&id=' + id : '');
            layer.open({
                type: 2,
                title: id ? '编辑商品' : '新建商品',
                skin: 'admin-modal',
                area: [window.innerWidth >= 960 ? '780px' : '94%', window.innerHeight >= 720 ? '640px' : '88%'],
                shadeClose: false,
                maxmin: true,
                content: url,
                end: function () {
                    if (window._mcSelfSaved) {
                        window._mcSelfSaved = false;
                        if (selfLoaded) {
                            table.reload('mcSelfTableId');
                            refreshBatchBar();
                        } else {
                            renderSelf();
                        }
                    }
                }
            });
        }

        // "新建商品" 按钮
        $(document).on('click.mcGoodsPage', '#mcSelfAddBtn', function () {
            openSelfEditPopup(0);
        });

        // 打开库存管理 iframe 弹窗
        function openStockManagerPopup(goodsId) {
            layer.open({
                type: 2,
                title: '库存管理',
                skin: 'admin-modal',
                area: [window.innerWidth >= 960 ? '860px' : '94%', window.innerHeight >= 720 ? '640px' : '88%'],
                shadeClose: false,
                maxmin: true,
                content: '/user/merchant/goods.php?_popup=stock_manager&id=' + goodsId
            });
        }

        // 表格 checkbox 勾选状态 → 刷新批量工具条
        function refreshBatchBar() {
            var sel = table.checkStatus('mcSelfTableId');
            var n = sel && sel.data ? sel.data.length : 0;
            $('#mcSelfBatchCount').text(n);
            $('#mcSelfBatchBar').toggle(n > 0);
        }
        table.on('checkbox(mcSelfTable)', refreshBatchBar);

        // 批量按钮
        $(document).on('click.mcGoodsPage', '#mcSelfBatchBar [data-batch]', function () {
            var action = $(this).data('batch');
            var sel = table.checkStatus('mcSelfTableId');
            if (!sel || !sel.data || !sel.data.length) { layer.msg('请先勾选商品'); return; }
            var ids = sel.data.map(function (r) { return r.id; });
            var confirmText;
            if (action === 'delete') confirmText = '确定删除所选 ' + ids.length + ' 件商品吗？';
            else if (action === 'off_sale') confirmText = '确定下架所选 ' + ids.length + ' 件商品吗？';
            else confirmText = null;
            var run = function () {
                $.ajax({
                    url: '/user/merchant/goods.php',
                    type: 'POST', dataType: 'json',
                    data: { csrf_token: csrfToken, _action: 'batch_self', batch_action: action, ids: ids }
                }).done(function (res) {
                    if (res && res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                    layer.msg(res && res.msg ? res.msg : '操作完成');
                    table.reload('mcSelfTableId');
                    refreshBatchBar();
                }).fail(function () { layer.msg('网络异常'); });
            };
            if (confirmText) {
                layer.confirm(confirmText, function (idx) { layer.close(idx); run(); });
            } else { run(); }
        });
        $(document).on('click.mcGoodsPage', '#mcSelfBatchClear', function () {
            // layui table 没有直接的 clear 方法，这里通过 reload 整表来清空勾选
            table.reload('mcSelfTableId');
            refreshBatchBar();
        });

        table.on('tool(mcSelfTable)', function (obj) {
            var data = obj.data;
            if (obj.event === 'edit') {
                openSelfEditPopup(data.id);
                return;
            }
            if (obj.event === 'stock') {
                openStockManagerPopup(data.id);
                return;
            }
            if (obj.event === 'clone') {
                layer.confirm('确定克隆商品「' + data.title + '」吗？将复制为一件新的自建商品。', function (idx) {
                    layer.close(idx);
                    $.ajax({
                        url: '/user/merchant/goods.php',
                        type: 'POST', dataType: 'json',
                        data: { csrf_token: csrfToken, _action: 'clone_self', id: data.id }
                    }).done(function (res) {
                        if (res && res.code === 200) {
                            if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                            layer.msg('克隆成功');
                            table.reload('mcSelfTableId');
                        } else {
                            layer.msg(res && res.msg ? res.msg : '克隆失败');
                        }
                    }).fail(function () { layer.msg('网络异常'); });
                });
                return;
            }
            if (obj.event === 'del') {
                layer.confirm('确定要删除商品「' + data.title + '」吗？', function (idx) {
                    $.ajax({
                        url: '/user/merchant/goods.php',
                        type: 'POST', dataType: 'json',
                        data: {csrf_token: csrfToken, _action: 'delete_self', id: data.id},
                        success: function (res) {
                            if (res.code === 200) {
                                csrfToken = res.data && res.data.csrf_token ? res.data.csrf_token : csrfToken;
                                layer.msg(res.msg || '删除成功');
                                obj.del();
                            } else { layer.msg(res.msg || '删除失败'); }
                        },
                        error: function () { layer.msg('网络异常'); },
                        complete: function () { layer.close(idx); }
                    });
                });
            }
        });

        // 初始渲染
        if ('<?= $currentTab ?>' === 'ref') renderRef();
        else if ('<?= $currentTab ?>' === 'self') renderSelf();
    });
});
</script>
