<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
?>
<!-- 搜索条件（em-filter 风格，和商品列表 / 分类列表一致） -->
<div class="em-filter" id="commentFilter">
    <div class="em-filter__head" id="commentFilterHead">
        <span class="em-filter__title"><i class="fa fa-filter"></i>搜索条件</span>
        <span class="em-filter__toggle"><i class="fa fa-angle-down"></i><span class="em-filter__toggle-text">展开</span></span>
    </div>
    <div class="em-filter__body">
        <div class="em-filter__grid">
            <div class="em-filter__field">
                <label>评论内容</label>
                <input type="text" id="commentSearchKeyword" placeholder="搜索评论内容" autocomplete="off">
            </div>
        </div>
        <div class="em-filter__actions">
            <button type="button" class="em-btn em-reset-btn" id="commentResetBtn"><i class="fa fa-undo mr-5"></i>重置</button>
            <button type="button" class="em-btn em-save-btn" id="commentSearchBtn"><i class="fa fa-search mr-5"></i>搜索</button>
        </div>
    </div>
</div>

<!-- 选项卡：按状态筛选（em-tabs 样式，和商品管理同款，计数徽章动态填） -->
<div class="em-tabs" id="commentTabs">
    <a class="em-tabs__item is-active" data-status=""><i class="fa fa-comments"></i>全部<em class="em-tabs__count" id="tabCountAll"></em></a>
    <a class="em-tabs__item" data-status="0"><i class="fa fa-clock-o"></i>待审核<em class="em-tabs__count" id="tabCountPending"></em></a>
    <a class="em-tabs__item" data-status="1"><i class="fa fa-check-circle"></i>已通过<em class="em-tabs__count" id="tabCountApproved"></em></a>
    <a class="em-tabs__item" data-status="2"><i class="fa fa-ban"></i>已拒绝<em class="em-tabs__count" id="tabCountRejected"></em></a>
</div>

<div class="admin-page">
    <h1 class="admin-page__title">评论管理</h1>
    <table id="commentTable" lay-filter="commentTable"></table>
</div>

<!-- 工具栏模板 -->
<script type="text/html" id="commentToolbarTpl">
    <div class="layui-btn-container">
        <a class="em-btn em-reset-btn" id="commentRefreshBtn"><i class="fa fa-refresh"></i>刷新</a>
        <a class="em-btn em-red-btn em-disabled-btn" lay-event="batchDelete"><i class="fa fa-trash"></i>批量删除</a>
        <a class="em-btn em-reset-btn em-disabled-btn" id="commentStatusDropdownBtn">
            <i class="fa fa-check-circle"></i>审核操作
            <i class="layui-icon layui-icon-down layui-font-12"></i>
        </a>
    </div>
</script>

<!-- 行内操作按钮模板 -->
<script type="text/html" id="commentRowActionTpl">
    <div class="layui-clear-space">
        {{# if(d.status != 1){ }}
        <a class="em-btn em-sm-btn em-save-btn" lay-event="approve"><i class="fa fa-check"></i>通过</a>
        {{# } }}
        {{# if(d.status != 2){ }}
        <a class="em-btn em-sm-btn em-purple-btn" lay-event="reject"><i class="fa fa-ban"></i>拒绝</a>
        {{# } }}
        <a class="em-btn em-sm-btn em-red-btn" lay-event="delete"><i class="fa fa-trash"></i>删除</a>
    </div>
</script>

<!-- 评论内容模板 -->
<script type="text/html" id="commentContentTpl">
    <span class="comment-clamp" title="{{ d.content }}">{{ d.content }}</span>
</script>

<!-- 用户名模板 -->
<script type="text/html" id="commentUserTpl">
    <div class="comment-user">
        <img src="{{ d.avatar || '/content/static/img/user-avatar.png' }}" class="comment-user__avatar">
        <span class="comment-user__name">{{ d.nickname || d.username || '匿名' }}</span>
    </div>
</script>

<!-- 文章标题模板 -->
<script type="text/html" id="commentBlogTpl">
    {{# if(d.blog_title){ }}
    <span class="comment-clamp" title="{{ d.blog_title }}">{{ d.blog_title }}</span>
    {{# } else { }}
    <span class="em-tag em-tag--muted">未知文章</span>
    {{# } }}
</script>

<!-- 状态模板：em-tag 胶囊 + 彩色小圆点 -->
<script type="text/html" id="commentStatusTpl">
    {{# if(d.status == 0){ }}
    <span class="em-tag em-tag--amber"><span class="em-tag__dot"></span>待审核</span>
    {{# } else if(d.status == 1){ }}
    <span class="em-tag em-tag--on"><span class="em-tag__dot"></span>已通过</span>
    {{# } else { }}
    <span class="em-tag em-tag--red"><span class="em-tag__dot"></span>已拒绝</span>
    {{# } }}
</script>

<!-- 类型模板 -->
<script type="text/html" id="commentTypeTpl">
    {{# if(d.parent_id == 0){ }}
    <span class="em-tag em-tag--blue">评论</span>
    {{# } else { }}
    <span class="em-tag em-tag--purple">回复</span>
    {{# } }}
</script>

<style>
.comment-clamp {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    word-break: break-all;
    line-height: 1.4;
    max-width: 100%;
}
.comment-user { display: inline-flex; align-items: center; gap: 8px; }
.comment-user__avatar {
    width: 28px; height: 28px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid #e5e7eb;
    background: #fff;
}
.comment-user__name { font-size: 13px; color: #374151; }
</style>

<script>
$(function(){
    // PJAX 防重复绑定：清掉本页历史 .admBlogComment handler，避免事件成倍触发
    $(document).off('.admBlogComment');
    $(window).off('.admBlogComment');

    'use strict';

    var csrfToken = <?= json_encode($csrfToken) ?>;
    var table, dropdown;

    function updateCsrf(token) {
        if (token) csrfToken = token;
    }
    window.updateCsrf = updateCsrf;

    layui.use(['layer', 'form', 'table', 'dropdown'], function () {
        var layer = layui.layer;
        var form = layui.form;
        table = layui.table;
        dropdown = layui.dropdown;

        // em-filter 展开/收起（和其他列表页一致，用 localStorage 记忆）
        var $filter = $('#commentFilter');
        var filterOpenKey = 'comment_filter_open';
        function setFilterOpen(open) {
            $filter.toggleClass('is-open', open);
            $('.em-filter__toggle-text', $filter).text(open ? '收起' : '展开');
            localStorage.setItem(filterOpenKey, open ? 'y' : 'n');
        }
        setFilterOpen(localStorage.getItem(filterOpenKey) === 'y');
        $('#commentFilterHead').on('click', function () {
            setFilterOpen(!$filter.hasClass('is-open'));
        });

        // ============================================================
        // 渲染表格
        // ============================================================
        var currentStatusTab = '';

        table.render({
            elem: '#commentTable',
            id: 'commentTableId',
            url: '/admin/blog_comment.php?_action=list',
            method: 'POST',
            toolbar: '#commentToolbarTpl',
            defaultToolbar: [],
            page: true,
            limit: 20,
            limits: [10, 20, 50, 100],
            cellMinWidth: 80,
            lineStyle: 'height: 55px;',
            cols: [[
                {type: 'checkbox', width: 50},
                {field: 'id', title: 'ID', width: 70, align: 'center', sort: true},
                {field: 'content', title: '评论内容', minWidth: 300, templet: '#commentContentTpl'},
                {title: '用户', width: 160, templet: '#commentUserTpl'},
                {field: 'blog_title', title: '所属文章', width: 200, templet: '#commentBlogTpl'},
                {title: '类型', width: 80, align: 'center', templet: '#commentTypeTpl'},
                {field: 'status', title: '状态', width: 110, align: 'center', templet: '#commentStatusTpl'},
                {field: 'created_at', title: '评论时间', width: 170, align: 'center'},
                {title: '操作', width: 250, align: 'center', toolbar: '#commentRowActionTpl'}
            ]],
            done: function (res) {
                if (res.csrf_token) csrfToken = res.csrf_token;
                // tab_counts 动态填数字徽章
                if (res.tab_counts) {
                    $('#tabCountAll').text(res.tab_counts.all || '');
                    $('#tabCountPending').text(res.tab_counts.pending || '');
                    $('#tabCountApproved').text(res.tab_counts.approved || '');
                    $('#tabCountRejected').text(res.tab_counts.rejected || '');
                }
                initToolbarDropdowns();
            }
        });

        // ============================================================
        // 工具栏下拉菜单（审核操作）
        // ============================================================
        function initToolbarDropdowns() {
            dropdown.render({
                elem: '#commentStatusDropdownBtn',
                data: [
                    {title: '批量通过', templet: '<i class="fa fa-check"></i> {{= d.title }}', id: 'batchApprove'},
                    {title: '批量拒绝', templet: '<i class="fa fa-ban"></i> {{= d.title }}', id: 'batchReject'}
                ],
                click: function(obj) {
                    if ($('#commentStatusDropdownBtn').hasClass('em-disabled-btn')) return;
                    var checkStatus = table.checkStatus('commentTableId');
                    var data = checkStatus.data;
                    if (data.length === 0) { layer.msg('请选择评论'); return; }
                    batchAction(obj.id === 'batchApprove' ? 'approve' : 'reject', data);
                }
            });
        }
        initToolbarDropdowns();

        // ============================================================
        // em-tabs 状态切换（同款点击 → .is-active + 表格 reload）
        // ============================================================
        $('#commentTabs').on('click', '.em-tabs__item', function () {
            var $item = $(this);
            if ($item.hasClass('is-active')) return;
            $item.addClass('is-active').siblings().removeClass('is-active');
            currentStatusTab = $item.data('status');
            if (currentStatusTab === undefined) currentStatusTab = '';
            table.reload('commentTableId', {
                where: {
                    keyword: $('#commentSearchKeyword').val() || '',
                    status: currentStatusTab === '' ? '' : String(currentStatusTab)
                },
                page: {curr: 1}
            });
        });

        // 复选框联动：有勾选行时激活批量按钮
        table.on('checkbox(commentTable)', function () {
            var checked = table.checkStatus('commentTableId').data.length > 0;
            var $btns = $('[lay-event="batchDelete"], #commentStatusDropdownBtn');
            if (checked) {
                $btns.removeClass('em-disabled-btn');
            } else {
                $btns.addClass('em-disabled-btn');
            }
        });

        // ============================================================
        // 搜索 / 重置 / 刷新
        // ============================================================
        $(document).on('click.admBlogComment', '#commentSearchBtn', function () {
            table.reload('commentTableId', {
                where: {
                    keyword: $('#commentSearchKeyword').val() || '',
                    status: currentStatusTab === '' ? '' : String(currentStatusTab)
                },
                page: {curr: 1}
            });
        });

        $(document).on('click.admBlogComment', '#commentResetBtn', function () {
            $('#commentSearchKeyword').val('');
            table.reload('commentTableId', {
                where: { keyword: '', status: currentStatusTab === '' ? '' : String(currentStatusTab) },
                page: {curr: 1}
            });
        });

        $(document).on('click.admBlogComment', '#commentRefreshBtn', function () {
            table.reload('commentTableId');
        });

        // ============================================================
        // 工具栏事件（批量删除）
        // ============================================================
        table.on('toolbar(commentTable)', function (obj) {
            var checkStatus = table.checkStatus('commentTableId');
            var data = checkStatus.data;
            if (obj.event === 'batchDelete') {
                if ($(obj.tr || obj.elem).closest('[lay-event="batchDelete"]').hasClass('em-disabled-btn')
                    || $('[lay-event="batchDelete"]').hasClass('em-disabled-btn')) {
                    if (data.length === 0) { layer.msg('请选择评论'); return; }
                }
                if (data.length === 0) { layer.msg('请选择评论'); return; }
                layer.confirm('确定要删除选中的 ' + data.length + ' 条评论吗？', function (idx) {
                    batchAction('delete', data, idx);
                });
            }
        });

        // ============================================================
        // 行内事件（通过 / 拒绝 / 删除）
        // ============================================================
        table.on('tool(commentTable)', function (obj) {
            if (obj.event === 'approve') {
                $.ajax({
                    url: '/admin/blog_comment.php?_action=approve',
                    type: 'POST',
                    dataType: 'json',
                    data: {csrf_token: csrfToken, id: obj.data.id},
                    success: function (res) {
                        updateCsrf(res.data && res.data.csrf_token);
                        if (res.code === 200) {
                            layer.msg('已通过');
                            table.reload('commentTableId');
                        } else {
                            layer.msg(res.msg || '操作失败');
                        }
                    },
                    error: function () { layer.msg('网络异常'); }
                });
            } else if (obj.event === 'reject') {
                $.ajax({
                    url: '/admin/blog_comment.php?_action=reject',
                    type: 'POST',
                    dataType: 'json',
                    data: {csrf_token: csrfToken, id: obj.data.id},
                    success: function (res) {
                        updateCsrf(res.data && res.data.csrf_token);
                        if (res.code === 200) {
                            layer.msg('已拒绝');
                            table.reload('commentTableId');
                        } else {
                            layer.msg(res.msg || '操作失败');
                        }
                    },
                    error: function () { layer.msg('网络异常'); }
                });
            } else if (obj.event === 'delete') {
                layer.confirm('确定要删除该评论吗？', function (idx) {
                    $.ajax({
                        url: '/admin/blog_comment.php?_action=delete',
                        type: 'POST',
                        dataType: 'json',
                        data: {csrf_token: csrfToken, id: obj.data.id},
                        success: function (res) {
                            updateCsrf(res.data && res.data.csrf_token);
                            if (res.code === 200) {
                                layer.msg('删除成功');
                                table.reload('commentTableId');
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

        // ============================================================
        // 批量操作（通用 POST 到 batch 端点）
        // ============================================================
        function batchAction(action, data, closeIdx) {
            var ids = data.map(function(item) { return item.id; });
            $.ajax({
                url: '/admin/blog_comment.php?_action=batch',
                type: 'POST',
                dataType: 'json',
                data: {csrf_token: csrfToken, batch_action: action, ids: ids},
                success: function (res) {
                    updateCsrf(res.data && res.data.csrf_token);
                    if (res.code === 200) {
                        layer.msg(res.msg || '操作成功');
                        table.reload('commentTableId');
                    } else {
                        layer.msg(res.msg || '操作失败');
                    }
                },
                error: function () { layer.msg('网络异常'); },
                complete: function () { if (closeIdx) layer.close(closeIdx); }
            });
        }
    });
});
</script>
