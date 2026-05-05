<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
?>
<div class="admin-page">
    <h1 class="admin-page__title">商品标签管理</h1>

    <!-- 快捷搜索（右上角，回车搜索） -->
    <div class="em-quick-search" id="goodsTagQuickSearchBox">
        <i class="fa fa-search em-quick-search__ico"></i>
        <input type="text" id="goodsTagSearchKeyword" placeholder="搜索标签名，回车" autocomplete="off">
        <button type="button" class="em-quick-search__clear" id="goodsTagQuickClear" title="清空">
            <i class="fa fa-times"></i>
        </button>
    </div>

    <table id="goodsTagTable" lay-filter="goodsTagTable"></table>
</div>

<!-- 工具栏模板（em-btn 体系；刷新计数已去掉，改为保存商品 / 删除商品时自动刷新） -->
<script type="text/html" id="goodsTagToolbarTpl">
    <div class="layui-btn-container">
        <a class="em-btn em-reset-btn" id="goodsTagRefreshBtn"><i class="fa fa-refresh"></i>刷新</a>
        <a class="em-btn em-save-btn" id="goodsTagAddBtn"><i class="fa fa-plus-circle"></i>新增标签</a>
        <a class="em-btn em-red-btn em-disabled-btn" lay-event="batchDelete"><i class="fa fa-trash"></i>批量删除</a>
    </div>
</script>

<!-- 行内操作按钮模板 -->
<script type="text/html" id="goodsTagRowActionTpl">
    <div class="layui-clear-space">
        <a class="em-btn em-sm-btn em-save-btn" lay-event="edit"><i class="fa fa-pencil"></i>编辑</a>
        <a class="em-btn em-sm-btn em-red-btn" lay-event="delete"><i class="fa fa-trash"></i>删除</a>
    </div>
</script>

<!-- 编辑弹窗模板 -->
<script type="text/html" id="goodsTagFormTpl">
    <div style="padding:20px 30px 0;">
        <form class="layui-form" lay-filter="goodsTagForm">
            <input type="hidden" name="id" value="">
            <div class="layui-form-item">
                <label class="layui-form-label">标签名 <span style="color:red;">*</span></label>
                <div class="layui-input-block">
                    <input type="text" name="name" lay-verify="required" placeholder="请输入标签名" class="layui-input" autocomplete="off">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">排序</label>
                <div class="layui-input-block">
                    <input type="number" name="sort" value="0" placeholder="数字越小越靠前" class="layui-input">
                </div>
            </div>
            <div class="layui-form-item" style="text-align:right; padding-top:10px; border-top:1px solid #f0f0f0;">
                <button type="button" class="em-btn em-reset-btn" id="goodsTagFormCancel"><i class="fa fa-times mr-5"></i>取消</button>
                <button type="button" class="em-btn em-save-btn" lay-submit lay-filter="goodsTagFormSubmit"><i class="fa fa-check mr-5"></i>保存</button>
            </div>
        </form>
    </div>
</script>

<script>
$(function(){
    // PJAX 防重复绑定：清掉本页历史 .admGoodsTag handler，避免事件成倍触发
    $(document).off('.admGoodsTag');
    $(window).off('.admGoodsTag');

    'use strict';

    var csrfToken = <?= json_encode($csrfToken) ?>;

    function updateCsrf(token) {
        if (token) csrfToken = token;
    }

    layui.use(['layer', 'form', 'table'], function () {
        var layer = layui.layer;
        var form = layui.form;
        var table = layui.table;

        // 渲染表格
        table.render({
            elem: '#goodsTagTable',
            id: 'goodsTagTableId',
            url: '/admin/goods_tag.php?_action=list',
            method: 'POST',
            toolbar: '#goodsTagToolbarTpl',
            defaultToolbar: [],
            lineStyle: 'height: 55px;',
            page: true,
            limit: 10,
            limits: [10, 20, 50, 100],
            cellMinWidth: 80,
            cols: [[
                {type: 'checkbox', width: 50},
                {field: 'id', title: 'ID', width: 80, align: 'center', sort: true},
                {field: 'name', title: '标签名', minWidth: 200},
                {field: 'goods_count', title: '商品数', width: 100, align: 'center', sort: true},
                {field: 'sort', title: '排序', width: 80, align: 'center'},
                {field: 'created_at', title: '创建时间', width: 170, align: 'center'},
                {title: '操作', width: 200, align: 'center', toolbar: '#goodsTagRowActionTpl'}
            ]],
            done: function (res) {
                if (res.csrf_token) csrfToken = res.csrf_token;
            }
        });

        // 快捷搜索（回车触发 + 清空按钮）
        function doQuickSearch() {
            table.reload('goodsTagTableId', {
                where: { keyword: $.trim($('#goodsTagSearchKeyword').val() || '') },
                page: {curr: 1}
            });
        }
        $(document).on('keypress.admGoodsTag', '#goodsTagSearchKeyword', function (e) {
            if (e.which === 13) { e.preventDefault(); doQuickSearch(); }
        });
        $(document).on('click.admGoodsTag', '#goodsTagQuickClear', function () {
            $('#goodsTagSearchKeyword').val('').focus();
            doQuickSearch();
        });
        $(document).on('click.admGoodsTag', '#goodsTagRefreshBtn', function () {
            table.reload('goodsTagTableId');
        });

        // 复选框联动：em-disabled-btn 类切换
        table.on('checkbox(goodsTagTable)', function () {
            var checked = table.checkStatus('goodsTagTableId').data.length > 0;
            $('[lay-event="batchDelete"]').toggleClass('em-disabled-btn', !checked);
        });

        // 新增 / 编辑弹窗
        var editLayerIdx = null;
        function openEditForm(data) {
            editLayerIdx = layer.open({
                type: 1,
                title: data ? '编辑标签' : '新增标签',
                area: ['420px', '280px'],
                content: $('#goodsTagFormTpl').html(),
                success: function (layero) {
                    if (data) {
                        layero.find('[name="id"]').val(data.id);
                        layero.find('[name="name"]').val(data.name);
                        layero.find('[name="sort"]').val(data.sort || 0);
                    }
                    form.render('select');
                }
            });
        }

        $(document).on('click.admGoodsTag', '#goodsTagAddBtn', function () { openEditForm(null); });
        $(document).on('click.admGoodsTag', '#goodsTagFormCancel', function () {
            if (editLayerIdx !== null) layer.close(editLayerIdx);
        });

        // 提交表单
        form.on('submit(goodsTagFormSubmit)', function (obj) {
            obj.field.csrf_token = csrfToken;
            $.ajax({
                url: '/admin/goods_tag.php?_action=save',
                type: 'POST',
                dataType: 'json',
                data: obj.field,
                success: function (res) {
                    updateCsrf(res.data && res.data.csrf_token);
                    if (res.code === 200) {
                        layer.msg('保存成功');
                        if (editLayerIdx !== null) layer.close(editLayerIdx);
                        table.reload('goodsTagTableId');
                    } else {
                        layer.msg(res.msg || '保存失败');
                    }
                },
                error: function () { layer.msg('网络异常'); }
            });
            return false;
        });

        // 批量删除
        table.on('toolbar(goodsTagTable)', function (obj) {
            if (obj.event !== 'batchDelete') return;
            var checkStatus = table.checkStatus('goodsTagTableId');
            var data = checkStatus.data;
            if (data.length === 0) { layer.msg('请选择标签'); return; }
            layer.confirm('确定要删除选中的 ' + data.length + ' 个标签吗？', function (idx) {
                var ids = data.map(function(item) { return item.id; });
                $.ajax({
                    url: '/admin/goods_tag.php?_action=batch_delete',
                    type: 'POST',
                    dataType: 'json',
                    data: {csrf_token: csrfToken, ids: ids},
                    success: function (res) {
                        updateCsrf(res.data && res.data.csrf_token);
                        if (res.code === 200) {
                            layer.msg(res.msg || '删除成功');
                            table.reload('goodsTagTableId');
                        } else {
                            layer.msg(res.msg || '删除失败');
                        }
                    },
                    error: function () { layer.msg('网络异常'); },
                    complete: function () { layer.close(idx); }
                });
            });
        });

        // 行内事件
        table.on('tool(goodsTagTable)', function (obj) {
            if (obj.event === 'edit') {
                openEditForm(obj.data);
            } else if (obj.event === 'delete') {
                layer.confirm('确定要删除标签"' + obj.data.name + '"吗？删除后将解除所有商品关联。', function (idx) {
                    $.ajax({
                        url: '/admin/goods_tag.php?_action=delete',
                        type: 'POST',
                        dataType: 'json',
                        data: {csrf_token: csrfToken, id: obj.data.id},
                        success: function (res) {
                            updateCsrf(res.data && res.data.csrf_token);
                            if (res.code === 200) {
                                layer.msg('删除成功');
                                table.reload('goodsTagTableId');
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
    });
});
</script>
