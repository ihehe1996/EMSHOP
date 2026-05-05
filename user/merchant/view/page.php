<?php
/** @var array<string, mixed> $frontUser */
/** @var array<string, mixed> $currentMerchant */
/** @var array<string, mixed> $uc */

$csrfToken = Csrf::token();
?>
<div class="mc-page">
    <div class="mc-page-header">
        <h2 class="mc-page-title">页面管理</h2>
        <p class="mc-page-desc">本店静态页（关于我们 / 联系方式等），可挂到导航；slug 在本店唯一</p>
    </div>

    <div class="mc-card">
        <div class="mc-blog-toolbar">
            <div class="mc-blog-tabs">
                <button type="button" class="mc-blog-tab is-active" data-status=""><span>全部</span><span class="cnt" data-key="all">0</span></button>
                <button type="button" class="mc-blog-tab" data-status="1"><span>已发布</span><span class="cnt" data-key="published">0</span></button>
                <button type="button" class="mc-blog-tab" data-status="0"><span>草稿</span><span class="cnt" data-key="draft">0</span></button>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <input type="text" id="mcPageKeyword" class="mc-input" placeholder="搜索标题或别名" style="width:220px;">
                <button class="mc-btn" id="mcPageSearchBtn"><i class="fa fa-search"></i> 搜索</button>
                <button class="mc-btn mc-btn--primary" id="mcPageAddBtn"><i class="fa fa-plus"></i> 新建页面</button>
            </div>
        </div>

        <table id="mcPageTable" lay-filter="mcPage"></table>

        <script type="text/html" id="mcPageRowTpl">
            {{# if (d.is_homepage == 1) { }}<span style="display:inline-block;padding:1px 7px;border-radius:9px;background:#fef3c7;color:#a16207;border:1px solid #fde68a;font-size:11px;margin-right:6px;"><i class="fa fa-home"></i> 本店首页</span>{{# } }}
            <a href="javascript:;" lay-event="edit" style="color:#1e40af;">{{ d.title }}</a>
        </script>
        <script type="text/html" id="mcPageSlugTpl">
            <code style="background:#f3f4f6;padding:1px 6px;border-radius:3px;font-size:12px;">/p/{{ d.slug }}</code>
        </script>
        <script type="text/html" id="mcPageStatusTpl">
            {{# if (d.status == 1) { }}
                <span style="display:inline-block;padding:1px 8px;border-radius:10px;background:#ecfdf5;color:#059669;font-size:12px;">已发布</span>
            {{# } else { }}
                <span style="display:inline-block;padding:1px 8px;border-radius:10px;background:#f3f4f6;color:#9ca3af;font-size:12px;">草稿</span>
            {{# } }}
        </script>
        <script type="text/html" id="mcPageToolbarTpl">
            <a class="layui-btn layui-btn-xs" lay-event="edit"><i class="fa fa-pencil"></i> 编辑</a>
            <a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="toggle_status">{{ d.status == 1 ? '转草稿' : '发布' }}</a>
            {{# if (d.is_homepage == 1) { }}
            <a class="layui-btn layui-btn-xs layui-btn-warm" lay-event="clear_homepage" title="取消本店首页"><i class="fa fa-home"></i> 取消首页</a>
            {{# } else { }}
            <a class="layui-btn layui-btn-xs" lay-event="set_homepage" title="设为本店首页" style="background:#fff;color:#a16207;border:1px solid #fde68a;"><i class="fa fa-home"></i> 设为首页</a>
            {{# } }}
            <a class="layui-btn layui-btn-xs layui-btn-danger" lay-event="delete">删除</a>
        </script>

        <div class="mc-blog-batch" id="mcPageBatchBar" style="display:none;">
            已选 <span id="mcPageBatchCnt">0</span> 项：
            <button class="mc-btn mc-btn--sm" data-batch="publish">批量发布</button>
            <button class="mc-btn mc-btn--sm" data-batch="draft">转草稿</button>
            <button class="mc-btn mc-btn--sm mc-btn--danger" data-batch="delete">批量删除</button>
        </div>
    </div>
</div>

<style>
.mc-card { background:#fff; border-radius:10px; padding:16px 18px; box-shadow:0 1px 3px rgba(0,0,0,0.04); }
.mc-blog-toolbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; gap:14px; flex-wrap:wrap; }
.mc-blog-tabs { display:flex; gap:4px; background:#f5f7fa; padding:4px; border-radius:8px; }
.mc-blog-tab { padding:6px 14px; border:0; border-radius:5px; background:transparent; color:#555; font-size:13px; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.mc-blog-tab .cnt { display:inline-block; min-width:18px; padding:0 6px; height:18px; line-height:18px; text-align:center; background:#e5e7eb; color:#6b7280; border-radius:9px; font-size:11px; }
.mc-blog-tab.is-active { background:#fff; color:#4e6ef2; box-shadow:0 1px 2px rgba(0,0,0,0.05); }
.mc-blog-tab.is-active .cnt { background:#eef2ff; color:#4e6ef2; }
.mc-input { height:32px; padding:0 10px; border:1px solid #e5e7eb; border-radius:6px; background:#fff; color:#374151; font-size:13px; outline:none; }
.mc-input:focus { border-color:#4e6ef2; }
.mc-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border:1px solid #e5e7eb; border-radius:6px; background:#fff; color:#555; font-size:13px; cursor:pointer; transition:all .15s; }
.mc-btn:hover { border-color:#4e6ef2; color:#4e6ef2; }
.mc-btn--primary { background:#4e6ef2; border-color:#4e6ef2; color:#fff; }
.mc-btn--primary:hover { background:#3d5bd9; border-color:#3d5bd9; color:#fff; }
.mc-btn--danger { border-color:#fee2e2; color:#ef4444; }
.mc-btn--danger:hover { background:#fef2f2; border-color:#fca5a5; }
.mc-btn--sm { padding:3px 10px; font-size:12px; }
.mc-blog-batch { margin-top:12px; padding:10px 14px; background:#fef3c7; border-radius:6px; display:flex; align-items:center; gap:8px; font-size:13px; color:#92400e; }
</style>

<script>
$(function () {
    'use strict';
    var csrfToken = <?= json_encode($csrfToken) ?>;
    $(document).off('.mcPagePage');

    layui.use(['table', 'layer'], function () {
        var table = layui.table;
        var layer = layui.layer;

        var currentStatus = '';
        var currentKeyword = '';

        function renderTable() {
            table.render({
                elem: '#mcPageTable',
                url: '/user/merchant/page.php',
                method: 'POST',
                where: { _action: 'list', status: currentStatus, keyword: currentKeyword },
                page: true, limit: 20, limits: [10, 20, 50, 100],
                cols: [[
                    { type: 'checkbox', fixed: 'left', width: 40 },
                    { field: 'id', title: 'ID', width: 70, sort: false },
                    { field: 'title', title: '标题', minWidth: 240, templet: '#mcPageRowTpl' },
                    { field: 'slug', title: 'URL 别名', width: 200, templet: '#mcPageSlugTpl' },
                    { field: 'views_count', title: '浏览', width: 80, sort: false },
                    { field: 'status', title: '状态', width: 100, templet: '#mcPageStatusTpl' },
                    { field: 'updated_at', title: '更新时间', width: 160 },
                    { title: '操作', width: 240, fixed: 'right', toolbar: '#mcPageToolbarTpl', align: 'center' },
                ]],
                done: function (res) {
                    if (res && res.csrf_token) csrfToken = res.csrf_token;
                    if (res && res.tab_counts) {
                        $('.mc-blog-tab .cnt[data-key="all"]').text(res.tab_counts.all || 0);
                        $('.mc-blog-tab .cnt[data-key="published"]').text(res.tab_counts.published || 0);
                        $('.mc-blog-tab .cnt[data-key="draft"]').text(res.tab_counts.draft || 0);
                    }
                    $('#mcPageBatchBar').hide();
                }
            });
        }

        table.on('tool(mcPage)', function (obj) {
            var data = obj.data;
            var event = obj.event;

            if (event === 'edit') { openEdit(data.id); return; }
            if (event === 'toggle_status') {
                $.post('/user/merchant/page.php', { _action: 'toggle_status', csrf_token: csrfToken, id: data.id }, function (res) {
                    if (res.code === 200) {
                        if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                        layer.msg(res.msg || '已更新');
                        renderTable();
                    } else { layer.msg(res.msg || '操作失败'); }
                }, 'json');
                return;
            }
            if (event === 'set_homepage') {
                layer.confirm('把「' + data.title + '」设为本店首页？同时会替换当前已设的页面首页。', function (idx) {
                    $.post('/user/merchant/page.php', { _action: 'set_homepage', csrf_token: csrfToken, id: data.id }, function (res) {
                        if (res.code === 200) {
                            if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                            layer.msg(res.msg || '已设为本店首页');
                            renderTable();
                        } else { layer.msg(res.msg || '设置失败'); }
                    }, 'json');
                    layer.close(idx);
                });
                return;
            }
            if (event === 'clear_homepage') {
                $.post('/user/merchant/page.php', { _action: 'clear_homepage', csrf_token: csrfToken, id: data.id }, function (res) {
                    if (res.code === 200) {
                        if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                        layer.msg(res.msg || '已取消本店首页');
                        renderTable();
                    } else { layer.msg(res.msg || '取消失败'); }
                }, 'json');
                return;
            }
            if (event === 'delete') {
                layer.confirm('确定删除「' + data.title + '」？', function (idx) {
                    $.post('/user/merchant/page.php', { _action: 'delete', csrf_token: csrfToken, id: data.id }, function (res) {
                        if (res.code === 200) {
                            if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                            layer.msg('已删除');
                            renderTable();
                        } else { layer.msg(res.msg || '删除失败'); }
                    }, 'json');
                    layer.close(idx);
                });
            }
        });

        table.on('checkbox(mcPage)', function () {
            var sel = table.checkStatus('mcPageTable').data;
            $('#mcPageBatchCnt').text(sel.length);
            $('#mcPageBatchBar').toggle(sel.length > 0);
        });

        $(document).on('click.mcPagePage', '#mcPageBatchBar [data-batch]', function () {
            var act = $(this).data('batch');
            var sel = table.checkStatus('mcPageTable').data;
            if (!sel.length) { layer.msg('请先选择页面'); return; }
            var ids = sel.map(function (r) { return r.id; });
            var msg = act === 'delete' ? '确定批量删除选中的 ' + ids.length + ' 个页面？' : '确定批量' + (act === 'publish' ? '发布' : '转为草稿') + '?';
            layer.confirm(msg, function (idx) {
                $.post('/user/merchant/page.php', {
                    _action: 'batch', csrf_token: csrfToken,
                    batch_action: act, ids: JSON.stringify(ids)
                }, function (res) {
                    if (res.code === 200) {
                        if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                        layer.msg('已处理');
                        renderTable();
                    } else { layer.msg(res.msg || '失败'); }
                }, 'json');
                layer.close(idx);
            });
        });

        $(document).on('click.mcPagePage', '.mc-blog-tab', function () {
            $('.mc-blog-tab').removeClass('is-active');
            $(this).addClass('is-active');
            currentStatus = String($(this).data('status') == null ? '' : $(this).data('status'));
            renderTable();
        });
        $(document).on('click.mcPagePage', '#mcPageSearchBtn', function () {
            currentKeyword = $.trim($('#mcPageKeyword').val());
            renderTable();
        });
        $(document).on('keypress.mcPagePage', '#mcPageKeyword', function (e) {
            if (e.which === 13) $('#mcPageSearchBtn').click();
        });
        $(document).on('click.mcPagePage', '#mcPageAddBtn', function () { openEdit(0); });

        function openEdit(id) {
            window._mcPagePopupSaved = false;
            layer.open({
                type: 2,
                title: id > 0 ? '编辑页面' : '新建页面',
                skin: 'admin-modal',
                area: [window.innerWidth >= 1200 ? '1100px' : '95%', window.innerHeight >= 700 ? '90%' : '95%'],
                maxmin: true, shadeClose: false,
                content: '/user/merchant/page_edit.php?_popup=1' + (id > 0 ? '&id=' + id : ''),
                end: function () {
                    if (window._mcPagePopupSaved) {
                        window._mcPagePopupSaved = false;
                        renderTable();
                    }
                }
            });
        }

        window.updateCsrf = function (token) { if (token) csrfToken = token; };

        renderTable();
    });
});
</script>
