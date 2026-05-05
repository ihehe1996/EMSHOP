<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
$levels = $levels ?? [];
?>
<div class="layui-collapse admin-search-collapse" lay-filter="mchSearchCollapse">
    <div class="layui-colla-item">
        <div class="layui-colla-title"><i class="fa fa-filter"></i> 搜索条件</div>
        <div class="layui-colla-content">
            <div class="layui-form layui-row layui-col-space12">
                <div class="layui-col-md4">
                    <div class="layui-form-item">
                        <label class="layui-form-label">店铺名</label>
                        <div class="layui-input-block">
                            <input type="text" id="mchSearchKeyword" placeholder="按店铺名搜索" class="layui-input" autocomplete="off">
                        </div>
                    </div>
                </div>
                <div class="layui-col-md3">
                    <div class="layui-form-item">
                        <label class="layui-form-label">状态</label>
                        <div class="layui-input-block">
                            <select id="mchSearchStatus">
                                <option value="">全部</option>
                                <option value="1">启用</option>
                                <option value="0">禁用</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="layui-col-md3">
                    <div class="layui-form-item">
                        <label class="layui-form-label">等级</label>
                        <div class="layui-input-block">
                            <select id="mchSearchLevel">
                                <option value="">全部</option>
                                <?php foreach ($levels as $lv): ?>
                                <option value="<?= (int) $lv['id'] ?>"><?= htmlspecialchars($lv['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="layui-form-item search-btn-group">
                    <button class="layui-btn" id="mchSearchBtn"><i class="fa fa-search mr-6"></i>搜索</button>
                    <button type="button" class="layui-btn layui-btn-primary" id="mchResetBtn"><i class="fa fa-rotate-left mr-6"></i>重置</button>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="admin-page">
    <h1 class="admin-page__title">商户管理</h1>
    <table id="mchTable" lay-filter="mchTable"></table>
</div>

<script type="text/html" id="mchToolbarTpl">
    <div class="layui-btn-container">
        <a class="layui-btn layui-btn-primary" id="mchRefreshBtn"><i class="fa fa-refresh mr-6"></i>刷新</a>
        <a class="layui-btn" lay-event="open"><i class="fa fa-plus-circle mr-6"></i>开通商户</a>
    </div>
</script>

<script type="text/html" id="mchRowActionTpl">
    <div class="layui-clear-space">
        <a class="em-btn em-sm-btn em-save-btn" lay-event="edit"><i class="fa fa-pencil"></i>编辑</a>
        <a class="em-btn em-sm-btn em-red-btn" lay-event="del"><i class="fa fa-trash"></i>删除</a>
    </div>
</script>

<script type="text/html" id="mchNameTpl">
    <div style="line-height:1.4;text-align:left;">
        <div style="font-weight:600;">{{ d.name }}</div>
        <div style="color:#999;font-size:12px;">ID: {{ d.id }}</div>
    </div>
</script>

<script type="text/html" id="mchUserTpl">
    <div style="line-height:1.4;text-align:left;">
        <div>{{ d.user_nickname || d.user_username }}</div>
        <div style="color:#999;font-size:12px;">ID: {{ d.user_id }}</div>
    </div>
</script>

<script type="text/html" id="mchLevelTpl">
    {{ d.level_name || '-' }}
</script>

<script type="text/html" id="mchParentTpl">
    {{ d.parent_id > 0 ? (d.parent_name || '#' + d.parent_id) : '<span style="color:#999">—</span>' }}
</script>

<script type="text/html" id="mchOpenedViaTpl">
    {{# if(d.opened_via === 'self'){ }}
    <span class="layui-badge layui-bg-blue">自助</span>
    {{# } else { }}
    <span class="layui-badge layui-bg-gray">后台</span>
    {{# } }}
</script>

<script type="text/html" id="mchBalanceTpl">
    ¥{{ d.shop_balance_view }}
</script>

<script type="text/html" id="mchStatusTpl">
    <input type="checkbox" name="status" value="{{d.id}}" lay-skin="switch" lay-text="启用|禁用" lay-filter="mchStatusFilter" {{d.status == 1 ? 'checked' : ''}}>
</script>

<script>
$(function(){
    // PJAX 防重复绑定：清掉本页历史 .admMerchant handler，避免事件成倍触发
    $(document).off('.admMerchant');
    $(window).off('.admMerchant');

    'use strict';

    var csrfToken = <?php echo json_encode($csrfToken); ?>;
    function updateCsrf(t) { if (t) csrfToken = t; }
    window.updateCsrf = updateCsrf;

    layui.use(['layer', 'form', 'table', 'element'], function () {
        var layer = layui.layer;
        var form = layui.form;
        var table = layui.table;
        var element = layui.element;

        form.render('select');

        var collapseKey = 'merchant_collapse';
        var $collapseContent = $('.admin-search-collapse .layui-colla-content');
        var $collapseTitle = $('.admin-search-collapse .layui-colla-title');
        element.on('collapse(mchSearchCollapse)', function (data) {
            localStorage.setItem(collapseKey, data.show ? 'y' : 'n');
        });
        if (localStorage.getItem(collapseKey) == 'y') {
            $collapseContent.addClass('layui-show');
            $collapseTitle.addClass('layui-colla-title-collapsed');
            element.render('collapse');
        }

        function buildWhere() {
            return {
                _action: 'list',
                keyword: $('#mchSearchKeyword').val() || '',
                status: $('#mchSearchStatus').val() || '',
                level_id: $('#mchSearchLevel').val() || ''
            };
        }

        table.render({
            elem: '#mchTable',
            id: 'mchTableId',
            url: '/admin/merchant.php',
            headers: {csrf: csrfToken},
            method: 'POST',
            where: buildWhere(),
            page: true,
            limit: 20,
            toolbar: '#mchToolbarTpl',
            defaultToolbar: [],
            lineStyle: 'height: 55px;',
            cols: [[
                {title: '店铺', minWidth: 200, templet: '#mchNameTpl'},
                {title: '商户主', minWidth: 160, templet: '#mchUserTpl'},
                {title: '等级', minWidth: 100, templet: '#mchLevelTpl', align: 'center'},
                {title: '上级商户', minWidth: 120, templet: '#mchParentTpl', align: 'center'},
                {title: '店铺余额', minWidth: 110, templet: '#mchBalanceTpl', align: 'right'},
                {title: '开通', width: 80, templet: '#mchOpenedViaTpl', align: 'center'},
                {title: '状态', width: 90, templet: '#mchStatusTpl', align: 'center'},
                {title: '操作', width: 170, templet: '#mchRowActionTpl', align: 'center'}
            ]],
            done: function (res) {
                csrfToken = (res.data && res.data.csrf_token) ? res.data.csrf_token : csrfToken;
            },
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

        $(document).on('click.admMerchant', '#mchSearchBtn', function () {
            table.reload('mchTableId', {page: {curr: 1}, where: buildWhere()});
        });
        $(document).on('click.admMerchant', '#mchResetBtn', function () {
            $('#mchSearchKeyword').val('');
            $('#mchSearchStatus').val('');
            $('#mchSearchLevel').val('');
            form.render('select');
            table.reload('mchTableId', {page: {curr: 1}, where: buildWhere()});
        });
        $(document).on('click.admMerchant', '#mchRefreshBtn', function () {
            table.reload('mchTableId');
        });

        // 状态开关
        form.on('switch(mchStatusFilter)', function (obj) {
            var id = this.value;
            $.ajax({
                url: '/admin/merchant.php',
                type: 'POST',
                dataType: 'json',
                data: {csrf_token: csrfToken, _action: 'toggle', id: id},
                success: function (res) {
                    if (res.code === 200) {
                        csrfToken = res.data && res.data.csrf_token ? res.data.csrf_token : csrfToken;
                        layer.msg(res.msg || '状态已更新');
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


        table.on('tool(mchTable)', function (obj) {
            var data = obj.data;
            if (obj.event === 'edit') {
                openPopup('edit', '编辑商户', data.id);
            } else if (obj.event === 'del') {
                layer.confirm('确定要删除商户「' + data.name + '」吗？商户主将解除绑定。', function (idx) {
                    $.ajax({
                        url: '/admin/merchant.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {csrf_token: csrfToken, _action: 'delete', id: data.id},
                        success: function (res) {
                            if (res.code === 200) {
                                csrfToken = res.data && res.data.csrf_token ? res.data.csrf_token : csrfToken;
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

        table.on('toolbar(mchTable)', function (obj) {
            if (obj.event === 'open') openPopup('open', '开通商户');
        });

        function openPopup(mode, title, id) {
            var url = '/admin/merchant.php?_popup=' + mode;
            if (id) url += '&id=' + encodeURIComponent(id);
            layer.open({
                type: 2,
                title: title,
                skin: 'admin-modal',
                maxmin: true,
                area: [window.innerWidth >= 900 ? '720px' : '95%', window.innerHeight >= 800 ? '760px' : '90%'],
                shadeClose: true,
                content: url,
                end: function () {
                    if (window._mchPopupSaved) {
                        window._mchPopupSaved = false;
                        table.reload('mchTableId');
                    }
                }
            });
        }
    });
});
</script>
