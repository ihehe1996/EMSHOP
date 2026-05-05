<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
?>
<div class="admin-page">
    <h1 class="admin-page__title">用户列表</h1>

    <!-- 快捷搜索（表格工具栏右上角；不随 table.reload 重建） -->
    <div class="em-quick-search" id="userQuickSearchBox">
        <i class="fa fa-search em-quick-search__ico"></i>
        <input type="text" id="userQuickSearch" placeholder="用户名 / 昵称 / 邮箱，回车搜索" autocomplete="off">
        <button type="button" class="em-quick-search__clear" id="userQuickClear" title="清空">
            <i class="fa fa-times"></i>
        </button>
    </div>

    <table id="userTable" lay-filter="userTable"></table>
</div>

<!-- 头部工具栏 -->
<script type="text/html" id="userToolbarTpl">
    <div class="layui-btn-container">
        <a class="em-btn em-reset-btn" id="userRefreshBtn"><i class="fa fa-refresh"></i>刷新</a>
        <a class="em-btn em-save-btn" lay-event="add"><i class="fa fa-plus-circle"></i>添加用户</a>
        <a class="em-btn em-red-btn em-disabled-btn" id="userBatchDelBtn"><i class="fa fa-trash"></i>批量删除</a>
    </div>
</script>

<!-- 行内操作 -->
<script type="text/html" id="userRowActionTpl">
    <div class="layui-clear-space">
        <a class="em-btn em-sm-btn em-save-btn" lay-event="edit"><i class="fa fa-pencil"></i>编辑</a>
        <a class="em-btn em-sm-btn em-red-btn" lay-event="del"><i class="fa fa-trash"></i>删除</a>
    </div>
</script>

<!-- ID：#数字，淡灰去干扰（鼠标悬停时可复制） -->
<script type="text/html" id="userIdTpl">
    <span class="ul-id">#{{d.id}}</span>
</script>

<!-- 头像：圆形 + 细边框；无头像时使用系统默认头像图片 -->
<script type="text/html" id="userAvatarTpl">
    <span lay-event="previewImg" class="ul-avatar" title="点击预览">
        <img src="{{ d.avatar || '/content/static/img/user-avatar.png' }}" alt="">
    </span>
</script>

<!-- 账号：用户名等宽字体凸显 -->
<script type="text/html" id="userNameTpl">
    <span class="ul-username">{{d.username}}</span>
</script>

<!-- 昵称：空值显灰斜体 -->
<script type="text/html" id="userNickTpl">
    {{# if(d.nickname){ }}
    <span style="color:#374151;font-weight:500;">{{d.nickname}}</span>
    {{# } else { }}
    <span class="em-tag em-tag--muted">未设置</span>
    {{# } }}
</script>

<!-- 余额：琥珀胶囊，可点击调整 -->
<script type="text/html" id="userBalanceTpl">
    {{# var m = d.money || 0; var b = (m / 1000000).toFixed(2); }}
    <span class="em-tag em-tag--amber em-tag--clickable" lay-event="balance" title="点击调整余额">
        ¥ {{b}}
    </span>
</script>

<!-- 邮箱：有值显灰字 + 单行省略，无值灰标签 -->
<script type="text/html" id="userEmailTpl">
    {{# if(d.email){ }}
    <span class="ul-email" title="{{d.email}}">{{d.email}}</span>
    {{# } else { }}
    <span class="em-tag em-tag--muted">未填写</span>
    {{# } }}
</script>

<!-- 手机号：有值显浅色底板，无值灰标签 -->
<script type="text/html" id="userMobileTpl">
    {{# if(d.mobile){ }}
    <span class="ul-mobile"><i class="fa fa-mobile" style="color:#6366f1;margin-right:4px;"></i>{{d.mobile}}</span>
    {{# } else { }}
    <span class="em-tag em-tag--muted">未填写</span>
    {{# } }}
</script>

<!-- 商户：未开通显示灰色标签；已开通显示等级名称胶囊，可点击查看详情 -->
<script type="text/html" id="userMerchantTpl">
    {{# if(d.merchant_id){ }}
    <span class="em-tag em-tag--on em-tag--clickable" lay-event="viewMerchant" title="点击查看商户详情">
        {{ d.merchant_level_name || '已开通' }}
    </span>
    {{# } else { }}
    <span class="em-tag em-tag--muted">未开通</span>
    {{# } }}
</script>

<!-- 用户等级：未设等级显示灰标签；已设显示蓝色等级名 -->
<script type="text/html" id="userLevelTpl">
    {{# if(d.user_level_name){ }}
    <span class="em-tag em-tag--blue">{{ d.user_level_name }}</span>
    {{# } else { }}
    <span class="em-tag em-tag--muted">无</span>
    {{# } }}
</script>

<!-- 注册时间：日期粗体 + 时间浅色 -->
<script type="text/html" id="userCreatedAtTpl">
    {{# if(d.created_at){ }}
    {{# var dt = d.created_at.replace('T', ' ').substring(0, 19); var parts = dt.split(' '); }}
    <span class="ul-time">
        <span class="ul-time__date">{{parts[0]}}</span>
        <span class="ul-time__clock">{{parts[1] || ''}}</span>
    </span>
    {{# } else { }}
    <span class="em-tag em-tag--muted">无</span>
    {{# } }}
</script>

<!-- 最后登录时间 -->
<script type="text/html" id="userLoginAtTpl">
    {{# if(d.last_login_at){ }}
    {{# var dt = d.last_login_at.replace('T', ' ').substring(0, 19); var parts = dt.split(' '); }}
    <span class="ul-time">
        <span class="ul-time__date">{{parts[0]}}</span>
        <span class="ul-time__clock">{{parts[1] || ''}}</span>
    </span>
    {{# } else { }}
    <span class="em-tag em-tag--muted">从未登录</span>
    {{# } }}
</script>

<!-- 状态开关 -->
<script type="text/html" id="userStatusTpl">
    <input type="checkbox" name="status" value="{{d.id}}" lay-skin="switch" lay-text="正常|禁用" lay-filter="userStatusFilter" {{d.status == 1 ? 'checked' : ''}}>
</script>

<style>
/* 用户列表单元格样式（ul- 前缀，作用域限定本页避免污染其它页面） */
.ul-id {
    font-family: Menlo, Consolas, monospace;
    color: #9ca3af;
    font-size: 12.5px;
}
.ul-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 5px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    background: #fff;
    cursor: pointer;
    transition: transform 0.15s ease, border-color 0.15s ease;
}
.ul-avatar:hover {
    transform: scale(1.05);
    border-color: #6366f1;
}
.ul-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.ul-username {
    font-family: Menlo, Consolas, monospace;
    font-size: 13px;
    color: #1f2937;
    font-weight: 500;
}
.ul-email {
    color: #4b5563;
    font-size: 12.5px;
    display: inline-block;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    vertical-align: middle;
}
.ul-mobile {
    color: #374151;
    font-size: 13px;
    letter-spacing: 0.3px;
}
.ul-time {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    line-height: 1.3;
}
.ul-time__date {
    color: #374151;
    font-weight: 500;
    font-size: 12.5px;
}
.ul-time__clock {
    color: #9ca3af;
    font-size: 11.5px;
    font-family: Menlo, Consolas, monospace;
}
</style>



<script>
$(function(){
    // PJAX 防重复绑定：清掉本页历史 .admUserList handler，避免事件成倍触发
    $(document).off('.admUserList');
    $(window).off('.admUserList');

    'use strict';

    var csrfToken = <?php echo json_encode($csrfToken); ?>;
    var tableIns;

    function updateCsrf(token) {
        if (token) csrfToken = token;
    }
    window.updateCsrf = updateCsrf;

    layui.use(['layer', 'form', 'table'], function () {
        var layer = layui.layer;
        var form = layui.form;
        var table = layui.table;

        // 搜索参数收集器：只用 quick search 的关键词
        function buildWhere() {
            return {
                _action: 'list',
                keyword: $.trim($('#userQuickSearch').val() || '')
            };
        }
        function doSearchReload() {
            table.reload('userTableId', { page: {curr: 1}, where: buildWhere() });
        }

        // 渲染表格
        tableIns = table.render({
            elem: '#userTable',
            id: 'userTableId',
            url: '/admin/user_list.php',
            headers: {csrf: csrfToken},
            method: 'POST',
            where: {_action: 'list'},
            page: true,
            limit: 10,
            limits: [10, 20, 50, 100],
            toolbar: '#userToolbarTpl',
            defaultToolbar: [],
            lineStyle: 'height: 60px;',
            initSort: {field: 'id', type: 'desc'},
            cols: [[
                {type: 'checkbox', width: 50, align: 'center'},
                {field: 'id', title: 'ID', width: 80, align: 'center', sort: true, templet: '#userIdTpl'},
                {field: 'avatar', title: '头像', width: 70, templet: '#userAvatarTpl', align: 'center'},
                {field: 'username', title: '账号', minWidth: 130, align: 'center', templet: '#userNameTpl'},
                {field: 'nickname', title: '昵称', minWidth: 110, align: 'center', templet: '#userNickTpl'},
                {field: 'money', title: '余额', width: 120, align: 'center', templet: '#userBalanceTpl'},
                {field: 'email', title: '邮箱', minWidth: 200, align: 'center', templet: '#userEmailTpl'},
                {field: 'mobile', title: '手机', minWidth: 130, align: 'center', templet: '#userMobileTpl'},
                {field: 'merchant_level_name', title: '商户', minWidth: 110, align: 'center', templet: '#userMerchantTpl'},
                {field: 'user_level_name', title: '等级', width: 90, align: 'center', templet: '#userLevelTpl'},
                {field: 'created_at', title: '注册时间', minWidth: 130, align: 'center', templet: '#userCreatedAtTpl', sort: true},
                {field: 'status', title: '状态', width: 90, templet: '#userStatusTpl', align: 'center'},
                {title: '操作', width: 200, templet: '#userRowActionTpl', align: 'center'}
            ]],
            parseData: function (res) {
                if (res.data && res.data.csrf_token) {
                    csrfToken = res.data.csrf_token;
                }
                return {
                    'code': res.code === 200 ? 0 : res.code,
                    'msg': res.msg,
                    'data': res.data ? res.data.data : [],
                    'count': res.data ? res.data.total : 0
                };
            }
        });

        // 快捷搜索：回车触发
        $(document).on('keypress.admUserList', '#userQuickSearch', function (e) {
            if (e.which !== 13) return;
            e.preventDefault();
            doSearchReload();
        });
        // 清空按钮：清空输入 + 立即刷新
        $(document).on('click.admUserList', '#userQuickClear', function () {
            $('#userQuickSearch').val('').focus();
            doSearchReload();
        });

        // 复选框联动：em-disabled-btn 切换
        table.on('checkbox(userTable)', function () {
            var checked = table.checkStatus('userTableId').data.length > 0;
            $('#userBatchDelBtn').toggleClass('em-disabled-btn', !checked);
        });

        // 状态开关
        form.on('switch(userStatusFilter)', function (obj) {
            var id = this.value;
            var $switch = $(obj.elem);
            var $wrap = $switch.closest('.layui-unselect');
            var $switchSpan = $wrap.find('.layui-form-switch');

            $switchSpan.css('position', 'relative').append('<i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop" style="position:absolute;left:0;top:0;width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.85);font-size:16px;"></i>');
            $switch.prop('disabled', true);

            $.ajax({
                url: '/admin/user_list.php',
                type: 'POST',
                dataType: 'json',
                data: {csrf_token: csrfToken, _action: 'toggle', id: id},
                success: function (res) {
                    if (res.code === 200) {
                        csrfToken = res.data && res.data.csrf_token ? res.data.csrf_token : csrfToken;
                        $switchSpan.find('i').removeClass().addClass('layui-icon layui-icon-ok').fadeOut(600, function(){ $(this).remove(); });
                        layer.msg(res.msg || '状态已更新');
                    } else {
                        obj.elem.checked = !obj.elem.checked;
                        form.render('switch');
                        $switchSpan.find('i').removeClass().addClass('layui-icon layui-icon-close').fadeOut(600, function(){ $(this).remove(); });
                        layer.msg(res.msg || '更新失败');
                    }
                },
                error: function () {
                    obj.elem.checked = !obj.elem.checked;
                    form.render('switch');
                    layer.msg('网络异常');
                },
                complete: function () {
                    $switch.prop('disabled', false);
                }
            });
        });

        // 行内事件
        table.on('tool(userTable)', function (obj) {
            var data = obj.data;
            switch (obj.event) {
                case 'previewImg':
                    // 用 Viewer.js 预览头像（和商品列表封面图点击同款）
                    var src = $(this).find('img').attr('src') || $(this).attr('src');
                    if (src) {
                        var $container = $('<div style="display:none;"><img src="' + src + '" alt=""></div>');
                        $('body').append($container);
                        var viewer = new Viewer($container[0], {
                            navbar: false,
                            title: false,
                            toolbar: true,
                            hidden: function () {
                                viewer.destroy();
                                $container.remove();
                            }
                        });
                        viewer.show();
                    }
                    break;
                case 'balance':
                    openBalancePopup(data);
                    break;
                case 'viewMerchant':
                    // iframe 查看商户详情（控制器 _popup=merchant 分支渲染）
                    layer.open({
                        type: 2,
                        title: '商户详情 - ' + (data.merchant_name || data.merchant_level_name || ''),
                        skin: 'admin-modal',
                        maxmin: true,
                        area: [window.innerWidth >= 800 ? '640px' : '95%', window.innerHeight >= 800 ? '700px' : '90%'],
                        shadeClose: true,
                        content: '/admin/user_list.php?_popup=merchant&user_id=' + data.id
                    });
                    break;
                case 'edit':
                    openPopup('编辑用户', data.id);
                    break;
                case 'del':
                    layer.confirm('确定要删除用户「' + (data.nickname || data.username) + '」吗？此操作不可恢复。', function (idx) {
                        $.ajax({
                            url: '/admin/user_list.php',
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
                    break;
            }
        });

        // 刷新按钮
        $(document).on('click.admUserList', '#userRefreshBtn', function () {
            table.reload('userTableId');
        });

        // 批量删除
        $(document).on('click.admUserList', '#userBatchDelBtn', function () {
            if ($(this).hasClass('em-disabled-btn')) return;
            var checked = table.checkStatus('userTableId');
            if (checked.data.length === 0) {
                layer.msg('请先勾选要删除的用户');
                return;
            }
            var ids = checked.data.map(function (row) { return row.id; });
            layer.confirm('确定要删除选中的 ' + ids.length + ' 个用户吗？此操作不可恢复。', function (idx) {
                $.ajax({
                    url: '/admin/user_list.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {csrf_token: csrfToken, _action: 'batch_delete', ids: ids.join(',')},
                    success: function (res) {
                        if (res.code === 200) {
                            csrfToken = res.data && res.data.csrf_token ? res.data.csrf_token : csrfToken;
                            layer.msg(res.msg || '删除成功');
                            table.reload('userTableId');
                        } else {
                            layer.msg(res.msg || '删除失败');
                        }
                    },
                    error: function () { layer.msg('网络异常'); },
                    complete: function () { layer.close(idx); }
                });
            });
        });

        // 头部工具栏
        table.on('toolbar(userTable)', function (obj) {
            if (obj.event === 'add') {
                openPopup('添加用户');
            }
        });

        // 余额调整弹窗
        function openBalancePopup(data) {
            var url = '/admin/user_list.php?_popup=balance&id=' + data.id;
            layer.open({
                type: 2,
                title: '余额调整',
                skin: 'admin-modal',
                area: [window.innerWidth >= 800 ? '520px' : '95%', window.innerHeight >= 800 ? '480px' : '80%'],
                shadeClose: true,
                content: url,
                end: function () {
                    if (window._userPopupSaved) {
                        window._userPopupSaved = false;
                        table.reload('userTableId');
                    }
                }
            });
        }

        // 打开弹窗
        function openPopup(title, editId) {
            var url = '/admin/user_list.php?_popup=1';
            if (editId) url += '&id=' + encodeURIComponent(editId);
            var isAdd = !editId;
            layer.open({
                type: 2,
                title: title,
                skin: 'admin-modal',
                maxmin: true,
                area: [window.innerWidth >= 800 ? '520px' : '95%', window.innerHeight >= 800 ? (isAdd ? '720px' : '680px') : '90%'],
                shadeClose: true,
                content: url,
                end: function () {
                    if (window._userPopupSaved) {
                        window._userPopupSaved = false;
                        table.reload('userTableId');
                    }
                }
            });
        }
    });
});
</script>
