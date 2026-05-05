<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
?>
<!-- 搜索条件（em-filter 风格，和其他列表页一致） -->
<div class="em-filter" id="blogFilter">
    <div class="em-filter__head" id="blogFilterHead">
        <span class="em-filter__title"><i class="fa fa-filter"></i>搜索条件</span>
        <span class="em-filter__toggle"><i class="fa fa-angle-down"></i><span class="em-filter__toggle-text">展开</span></span>
    </div>
    <div class="em-filter__body">
        <div class="em-filter__grid">
            <div class="em-filter__field">
                <label>文章标题</label>
                <input type="text" id="blogSearchKeyword" placeholder="标题 / 摘要" autocomplete="off">
            </div>
            <div class="em-filter__field">
                <label>文章分类</label>
                <select id="blogSearchCategory">
                    <option value="">全部分类</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= str_repeat('—', $cat['parent_id'] ? 1 : 0) . htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="em-filter__actions">
            <button type="button" class="em-btn em-reset-btn" id="blogResetBtn"><i class="fa fa-undo mr-5"></i>重置</button>
            <button type="button" class="em-btn em-save-btn" id="blogSearchBtn"><i class="fa fa-search mr-5"></i>搜索</button>
        </div>
    </div>
</div>

<!-- 选项卡：按发布状态筛选（em-tabs 同款，带动态计数徽章） -->
<div class="em-tabs" id="blogTabs">
    <a class="em-tabs__item is-active" data-status=""><i class="fa fa-file-text"></i>全部文章<em class="em-tabs__count" id="tabCountAll"></em></a>
    <a class="em-tabs__item" data-status="1"><i class="fa fa-eye"></i>已发布<em class="em-tabs__count" id="tabCountPublished"></em></a>
    <a class="em-tabs__item" data-status="0"><i class="fa fa-eye-slash"></i>草稿箱<em class="em-tabs__count" id="tabCountDraft"></em></a>
</div>

<div class="admin-page">
    <h1 class="admin-page__title">文章管理</h1>
    <table id="blogTable" lay-filter="blogTable"></table>
</div>

<!-- 工具栏模板（em-btn 体系） -->
<script type="text/html" id="blogToolbarTpl">
    <div class="layui-btn-container">
        <a class="em-btn em-reset-btn" id="blogRefreshBtn"><i class="fa fa-refresh"></i>刷新</a>
        <a class="em-btn em-save-btn" lay-event="add"><i class="fa fa-plus-circle"></i>添加文章</a>
        <a class="em-btn em-red-btn em-disabled-btn" lay-event="batchDelete"><i class="fa fa-trash"></i>批量删除</a>
        <a class="em-btn em-reset-btn em-disabled-btn" id="statusDropdownBtn">
            <i class="fa fa-eye"></i>发布/草稿
            <i class="layui-icon layui-icon-down layui-font-12"></i>
        </a>
    </div>
</script>

<!-- 行内操作按钮模板 -->
<script type="text/html" id="blogRowActionTpl">
    <div class="layui-clear-space">
        <a class="em-btn em-sm-btn em-save-btn" lay-event="edit"><i class="fa fa-pencil"></i>编辑</a>
        <a class="em-btn em-sm-btn em-red-btn" lay-event="delete"><i class="fa fa-trash"></i>删除</a>
    </div>
</script>

<!-- 文章标题模板（置顶 tag 前缀 + 两行截断） -->
<script type="text/html" id="blogTitleTpl">
    <span class="blog-title-clamp" title="{{ d.title }}">
        {{# if(d.is_top == 1){ }}<span class="em-tag em-tag--amber" style="margin-right:4px;">置顶</span>{{# } }}
        {{ d.title }}
    </span>
</script>

<!-- 发布状态：点击即切换（和商品上下架同款 em-tag + 小圆点） -->
<script type="text/html" id="blogStatusTpl">
    {{# if(d.status == 1){ }}
    <span class="em-tag em-tag--on em-tag--clickable" lay-event="toggleStatus" title="点击转草稿">
        <span class="em-tag__dot"></span>已发布
    </span>
    {{# } else { }}
    <span class="em-tag em-tag--muted em-tag--clickable" lay-event="toggleStatus" title="点击发布">
        <span class="em-tag__dot"></span>草稿
    </span>
    {{# } }}
</script>

<!-- 置顶：点击即切换 -->
<script type="text/html" id="blogTopTpl">
    {{# if(d.is_top == 1){ }}
    <span class="em-tag em-tag--amber em-tag--clickable" lay-event="toggleTop" title="点击取消置顶">
        <span class="em-tag__dot"></span>置顶中
    </span>
    {{# } else { }}
    <span class="em-tag em-tag--muted em-tag--clickable" lay-event="toggleTop" title="点击置顶">
        <span class="em-tag__dot"></span>未置顶
    </span>
    {{# } }}
</script>

<!-- 封面图：有图点击 Viewer.js 预览；无图显示灰标签 -->
<script type="text/html" id="blogCoverTpl">
    {{# if(d.cover_image){ }}
    <img src="{{ d.cover_image }}" class="blog-cover-img" style="width:40px;height:40px;object-fit:cover;border-radius:4px;cursor:pointer;">
    {{# } else { }}
    <span class="em-tag em-tag--muted">无图</span>
    {{# } }}
</script>

<style>
.blog-title-clamp {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    word-break: break-all;
    line-height: 1.4;
}
</style>

<script>
$(function(){
    // PJAX 防重复绑定：清掉本页历史 .admBlog handler，避免事件成倍触发
    $(document).off('.admBlog');
    $(window).off('.admBlog');

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

        // em-filter 展开/收起
        var $filter = $('#blogFilter');
        var filterOpenKey = 'blog_filter_open';
        function setFilterOpen(open) {
            $filter.toggleClass('is-open', open);
            $('.em-filter__toggle-text', $filter).text(open ? '收起' : '展开');
            localStorage.setItem(filterOpenKey, open ? 'y' : 'n');
        }
        setFilterOpen(localStorage.getItem(filterOpenKey) === 'y');
        $('#blogFilterHead').on('click', function () {
            setFilterOpen(!$filter.hasClass('is-open'));
        });

        // ============================================================
        // 渲染表格
        // ============================================================
        var currentStatusTab = '';

        table.render({
            elem: '#blogTable',
            id: 'blogTableId',
            url: '/admin/blog.php?_action=list',
            method: 'POST',
            toolbar: '#blogToolbarTpl',
            defaultToolbar: [],
            lineStyle: 'height: 60px;',
            page: true,
            limit: 20,
            limits: [10, 20, 50, 100],
            cellMinWidth: 80,
            cols: [[
                {type: 'checkbox', width: 50},
                {field: 'cover_image', title: '封面', width: 80, templet: '#blogCoverTpl', align: 'center'},
                {field: 'title', title: '文章标题', minWidth: 260, templet: '#blogTitleTpl'},
                {field: 'category_name', title: '分类', width: 120, align: 'center'},
                {field: 'author', title: '作者', width: 100, align: 'center'},
                {field: 'views_count', title: '阅读', width: 80, align: 'center'},
                {field: 'is_top', title: '置顶', width: 110, templet: '#blogTopTpl', align: 'center', unresize: true},
                {field: 'status', title: '状态', width: 110, templet: '#blogStatusTpl', align: 'center', unresize: true},
                {field: 'created_at', title: '创建时间', width: 170, align: 'center'},
                {title: '操作', width: 200, align: 'center', toolbar: '#blogRowActionTpl'}
            ]],
            done: function (res) {
                if (res.csrf_token) csrfToken = res.csrf_token;
                if (res.tab_counts) {
                    $('#tabCountAll').text(res.tab_counts.all || '');
                    $('#tabCountPublished').text(res.tab_counts.published || '');
                    $('#tabCountDraft').text(res.tab_counts.draft || '');
                }
                initToolbarDropdowns();
            }
        });

        // ============================================================
        // 工具栏下拉菜单（批量发布/转草稿）
        // ============================================================
        function initToolbarDropdowns() {
            dropdown.render({
                elem: '#statusDropdownBtn',
                data: [
                    {title: '批量发布', templet: '<i class="fa fa-eye"></i> {{= d.title }}', id: 'batchPublish'},
                    {title: '批量转草稿', templet: '<i class="fa fa-eye-slash"></i> {{= d.title }}', id: 'batchDraft'}
                ],
                click: function(obj) {
                    if ($('#statusDropdownBtn').hasClass('em-disabled-btn')) return;
                    var checkStatus = table.checkStatus('blogTableId');
                    var data = checkStatus.data;
                    if (data.length === 0) { layer.msg('请选择文章'); return; }
                    batchAction(obj.id === 'batchPublish' ? 'publish' : 'draft', data);
                }
            });
        }
        initToolbarDropdowns();

        // ============================================================
        // em-tabs 切换（状态筛选）
        // ============================================================
        $('#blogTabs').on('click', '.em-tabs__item', function () {
            var $item = $(this);
            if ($item.hasClass('is-active')) return;
            $item.addClass('is-active').siblings().removeClass('is-active');
            currentStatusTab = $item.data('status');
            if (currentStatusTab === undefined) currentStatusTab = '';
            table.reload('blogTableId', {
                where: {
                    keyword: $('#blogSearchKeyword').val() || '',
                    category_id: $('#blogSearchCategory').val() || '',
                    status: currentStatusTab === '' ? '' : String(currentStatusTab)
                },
                page: {curr: 1}
            });
        });

        // 复选框联动：em-disabled-btn 切换
        table.on('checkbox(blogTable)', function () {
            var checked = table.checkStatus('blogTableId').data.length > 0;
            var $btns = $('[lay-event="batchDelete"], #statusDropdownBtn');
            $btns.toggleClass('em-disabled-btn', !checked);
        });

        // ============================================================
        // 搜索 / 重置 / 刷新
        // ============================================================
        $(document).on('click.admBlog', '#blogSearchBtn', function () {
            table.reload('blogTableId', {
                where: {
                    keyword: $('#blogSearchKeyword').val() || '',
                    category_id: $('#blogSearchCategory').val() || '',
                    status: currentStatusTab === '' ? '' : String(currentStatusTab)
                },
                page: {curr: 1}
            });
        });

        $(document).on('click.admBlog', '#blogResetBtn', function () {
            $('#blogSearchKeyword').val('');
            $('#blogSearchCategory').val('');
            table.reload('blogTableId', {
                where: { keyword: '', category_id: '', status: currentStatusTab === '' ? '' : String(currentStatusTab) },
                page: {curr: 1}
            });
        });

        $(document).on('click.admBlog', '#blogRefreshBtn', function () {
            table.reload('blogTableId');
        });

        // 封面图点击：Viewer.js 预览（全局已加载）
        $(document).on('click.admBlog', '.blog-cover-img', function () {
            var src = $(this).attr('src');
            if (!src) return;
            var $tmp = $('<div style="display:none;"><img src="' + src + '"></div>').appendTo('body');
            var viewer = new Viewer($tmp[0], {
                navbar: false, title: false, toolbar: true,
                hidden: function () { viewer.destroy(); $tmp.remove(); }
            });
            viewer.show();
        });

        // ============================================================
        // 工具栏事件
        // ============================================================
        table.on('toolbar(blogTable)', function (obj) {
            var checkStatus = table.checkStatus('blogTableId');
            var data = checkStatus.data;
            if (obj.event === 'add') {
                openEditPopup('添加文章');
            } else if (obj.event === 'batchDelete') {
                if (data.length === 0) { layer.msg('请选择文章'); return; }
                layer.confirm('确定要删除选中的 ' + data.length + ' 篇文章吗？', function (idx) {
                    batchAction('delete', data, idx);
                });
            }
        });

        // ============================================================
        // 行内事件
        // ============================================================
        table.on('tool(blogTable)', function (obj) {
            if (obj.event === 'edit') {
                openEditPopup('编辑文章', obj.data.id);
            } else if (obj.event === 'delete') {
                layer.confirm('确定要删除该文章吗？', function (idx) {
                    $.ajax({
                        url: '/admin/blog.php?_action=delete',
                        type: 'POST',
                        dataType: 'json',
                        data: {csrf_token: csrfToken, id: obj.data.id},
                        success: function (res) {
                            if (res.code === 200) {
                                csrfToken = res.data && res.data.csrf_token ? res.data.csrf_token : csrfToken;
                                layer.msg(res.msg || '删除成功');
                                table.reload('blogTableId');
                            } else {
                                layer.msg(res.msg || '删除失败');
                            }
                        },
                        error: function () { layer.msg('网络异常'); },
                        complete: function () { layer.close(idx); }
                    });
                });
            } else if (obj.event === 'toggleStatus') {
                // 点击发布/草稿标签 → 调 toggle_status；成功后原地切换 class + 文案，不 reload
                toggleTag($(this), '/admin/blog.php?_action=toggle_status', obj.data.id, {
                    onClass: 'em-tag--on', offClass: 'em-tag--muted',
                    onHtml: '<span class="em-tag__dot"></span>已发布', offHtml: '<span class="em-tag__dot"></span>草稿',
                    onTitle: '点击转草稿', offTitle: '点击发布'
                });
            } else if (obj.event === 'toggleTop') {
                // 点击置顶/未置顶标签
                toggleTag($(this), '/admin/blog.php?_action=toggle_top', obj.data.id, {
                    onClass: 'em-tag--amber', offClass: 'em-tag--muted',
                    onHtml: '<span class="em-tag__dot"></span>置顶中', offHtml: '<span class="em-tag__dot"></span>未置顶',
                    onTitle: '点击取消置顶', offTitle: '点击置顶'
                });
            }
        });

        // 通用：点击 em-tag 切换一个布尔字段；success 后按当前状态反向切 class/文案
        function toggleTag($tag, url, id, cfg) {
            if ($tag.hasClass('is-loading')) return;
            $tag.addClass('is-loading');
            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                data: {csrf_token: csrfToken, id: id},
                success: function (res) {
                    if (res.code === 200) {
                        csrfToken = res.data && res.data.csrf_token ? res.data.csrf_token : csrfToken;
                        if ($tag.hasClass(cfg.onClass)) {
                            $tag.removeClass(cfg.onClass).addClass(cfg.offClass)
                                .attr('title', cfg.offTitle).html(cfg.offHtml);
                        } else {
                            $tag.removeClass(cfg.offClass).addClass(cfg.onClass)
                                .attr('title', cfg.onTitle).html(cfg.onHtml);
                        }
                        layer.msg(res.msg || '操作成功');
                    } else {
                        layer.msg(res.msg || '操作失败');
                    }
                },
                error: function () { layer.msg('网络异常'); },
                complete: function () { $tag.removeClass('is-loading'); }
            });
        }

        // ============================================================
        // 批量操作
        // ============================================================
        function batchAction(action, data, closeIdx) {
            var ids = data.map(function(item) { return item.id; });
            $.ajax({
                url: '/admin/blog.php?_action=batch',
                type: 'POST',
                dataType: 'json',
                data: {csrf_token: csrfToken, batch_action: action, ids: ids},
                success: function (res) {
                    if (res.code === 200) {
                        csrfToken = res.data && res.data.csrf_token ? res.data.csrf_token : csrfToken;
                        layer.msg(res.msg || '操作成功');
                        table.reload('blogTableId');
                    } else {
                        layer.msg(res.msg || '操作失败');
                    }
                },
                error: function () { layer.msg('网络异常'); },
                complete: function () { if (closeIdx) layer.close(closeIdx); }
            });
        }

        // ============================================================
        // 打开编辑弹窗
        // ============================================================
        function openEditPopup(title, editId) {
            var url = '/admin/blog_edit.php?_popup=1';
            if (editId) url += '&id=' + encodeURIComponent(editId);
            window._blogPopupSaved = false;
            layer.open({
                type: 2,
                title: title,
                skin: 'admin-modal',
                maxmin: true,
                area: ['800px', '90%'],
                shadeClose: true,
                content: url,
                end: function () {
                    if (window._blogPopupSaved) {
                        window._blogPopupSaved = false;
                        table.reload('blogTableId');
                    }
                }
            });
        }
    });
});
</script>
