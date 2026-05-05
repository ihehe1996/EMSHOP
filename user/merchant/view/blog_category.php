<?php
/** @var array<string, mixed> $frontUser */
/** @var array<string, mixed> $currentMerchant */
/** @var array<string, mixed> $uc */

$csrfToken = Csrf::token();
?>
<div class="mc-page">
    <div class="mc-page-header">
        <h2 class="mc-page-title">文章分类</h2>
        <p class="mc-page-desc">本店博客分类与主站完全独立，仅作用于本店文章</p>
    </div>

    <div class="mc-card">
        <div class="mc-card__toolbar">
            <div class="mc-card__hint" style="padding:0;margin:0;color:#9ca3af;font-size:12px;">
                <i class="fa fa-info-circle" style="color:#9ca3af;"></i> 仅支持 2 级分类，别名（slug）会出现在 URL 里，留空则按 ID
            </div>
            <button type="button" class="mc-btn mc-btn--primary" id="mcBlogCatAddBtn"><i class="fa fa-plus"></i> 添加分类</button>
        </div>
        <div id="mcBlogCatList">
            <div class="mc-placeholder" style="padding:30px;"><i class="fa fa-spinner fa-spin"></i> 加载中...</div>
        </div>
    </div>
</div>

<style>
.mc-card { background:#fff; border-radius:10px; padding:16px 18px; box-shadow:0 1px 3px rgba(0,0,0,0.04); }
.mc-card__toolbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; gap:12px; }
.mc-card__hint { padding:10px 12px; background:#f0f9ff; border-left:3px solid #38bdf8; border-radius:4px; color:#0c4a6e; font-size:12px; line-height:1.7; }
.mc-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border:1px solid #e5e7eb; border-radius:6px; background:#fff; color:#555; font-size:13px; cursor:pointer; transition:all .15s; }
.mc-btn:hover { border-color:#4e6ef2; color:#4e6ef2; }
.mc-btn--primary { background:#4e6ef2; border-color:#4e6ef2; color:#fff; }
.mc-btn--primary:hover { background:#3d5bd9; border-color:#3d5bd9; color:#fff; }
.mc-btn--danger { border-color:#fee2e2; color:#ef4444; }
.mc-btn--danger:hover { background:#fee2e2; border-color:#fecaca; color:#dc2626; }
.mc-btn--sm { padding:3px 10px; font-size:12px; }

.mc-cat-list { background:#fff; border:1px solid #f0f1f4; border-radius:8px; overflow:hidden; }
.mc-cat-row {
    display:grid; grid-template-columns: 1.2fr 0.8fr 80px 80px 220px;
    gap:10px; padding:12px 14px; border-bottom:1px solid #f3f4f6; align-items:center; transition:background .15s;
}
.mc-cat-row:last-child { border-bottom:0; }
.mc-cat-row:hover:not(.mc-cat-row--head) { background:#fafbfc; }
.mc-cat-row.is-child { padding-left:38px; background:#fafbfc; }
.mc-cat-row--head { background:#f9fafb; font-weight:500; color:#6b7280; font-size:12px; }
.mc-cat-row--head:hover { background:#f9fafb; }
.mc-cat-slug { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:12px; color:#6b7280; }
</style>

<script>
$(function () {
    'use strict';
    var csrfToken = <?= json_encode($csrfToken) ?>;
    $(document).off('.mcBlogCatPage');

    layui.use(['layer', 'form'], function () {
        var layer = layui.layer;
        var form = layui.form;

        var topCats = [];
        var allRows = [];

        function load() {
            $.ajax({
                url: '/user/merchant/blog_category.php', type: 'POST', dataType: 'json',
                data: { _action: 'list' },
                success: function (res) {
                    if (res.code !== 200) { layer.msg(res.msg || '加载失败'); return; }
                    if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                    allRows = res.data.data || [];
                    render(allRows);
                }
            });
        }

        function render(rows) {
            topCats = rows.filter(function (r) { return parseInt(r.parent_id, 10) === 0; });
            var children = rows.filter(function (r) { return parseInt(r.parent_id, 10) > 0; });
            var byParent = {};
            children.forEach(function (c) { (byParent[c.parent_id] = byParent[c.parent_id] || []).push(c); });

            if (!topCats.length) {
                $('#mcBlogCatList').html('<div class="mc-placeholder"><i class="fa fa-folder-open-o"></i><div>还没有分类，点击右上角添加第一个</div></div>');
                return;
            }

            var html = '<div class="mc-cat-list">'
                + '<div class="mc-cat-row mc-cat-row--head">'
                +   '<div>分类名</div><div>别名</div>'
                +   '<div style="text-align:center;">排序</div><div style="text-align:center;">状态</div>'
                +   '<div style="text-align:right;">操作</div>'
                + '</div>';

            topCats.forEach(function (c) {
                html += rowHtml(c, false);
                (byParent[c.id] || []).forEach(function (ch) { html += rowHtml(ch, true); });
            });
            html += '</div>';
            $('#mcBlogCatList').html(html);
        }

        function rowHtml(c, isChild) {
            var status = parseInt(c.status, 10) === 1
                ? '<span style="display:inline-block;padding:1px 8px;border-radius:10px;background:#ecfdf5;color:#059669;font-size:12px;">启用</span>'
                : '<span style="display:inline-block;padding:1px 8px;border-radius:10px;background:#f3f4f6;color:#9ca3af;font-size:12px;">禁用</span>';
            return '<div class="mc-cat-row' + (isChild ? ' is-child' : '') + '">'
                +   '<div>' + (isChild ? '<i class="fa fa-level-up fa-rotate-90" style="color:#d1d5db;margin-right:6px;"></i>' : '')
                +     escapeHtml(c.name) + '</div>'
                +   '<div class="mc-cat-slug">' + escapeHtml(c.slug || '') + '</div>'
                +   '<div style="text-align:center;color:#6b7280;">' + c.sort + '</div>'
                +   '<div style="text-align:center;">' + status + '</div>'
                +   '<div style="display:flex;gap:6px;justify-content:flex-end;">'
                +     '<button class="mc-btn mc-btn--sm mc-cat-toggle" data-id="' + c.id + '">' + (parseInt(c.status, 10) === 1 ? '<i class="fa fa-pause"></i> 禁用' : '<i class="fa fa-play"></i> 启用') + '</button>'
                +     '<button class="mc-btn mc-btn--sm mc-cat-edit" data-id="' + c.id + '"><i class="fa fa-pencil"></i> 编辑</button>'
                +     '<button class="mc-btn mc-btn--sm mc-btn--danger mc-cat-del" data-id="' + c.id + '" data-name="' + escapeHtml(c.name) + '"><i class="fa fa-trash"></i></button>'
                +   '</div>'
                + '</div>';
        }

        function escapeHtml(s) {
            return String(s == null ? '' : s).replace(/[<>&"']/g, function (m) {
                return {'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&#39;'}[m];
            });
        }

        $(document).on('click.mcBlogCatPage', '#mcBlogCatAddBtn', function () { openPopup(null); });
        $(document).on('click.mcBlogCatPage', '.mc-cat-edit', function () {
            var id = $(this).data('id');
            var row = allRows.find(function (r) { return r.id == id; });
            if (row) openPopup(row);
        });
        $(document).on('click.mcBlogCatPage', '.mc-cat-toggle', function () {
            var id = $(this).data('id');
            $.ajax({
                url: '/user/merchant/blog_category.php', type: 'POST', dataType: 'json',
                data: { _action: 'toggle_status', csrf_token: csrfToken, id: id },
                success: function (res) {
                    if (res.code === 200) {
                        if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                        layer.msg(res.msg || '已更新');
                        load();
                    } else { layer.msg(res.msg || '更新失败'); }
                }
            });
        });
        $(document).on('click.mcBlogCatPage', '.mc-cat-del', function () {
            var id = $(this).data('id');
            var name = $(this).data('name');
            layer.confirm('确定删除「' + name + '」？', function (idx) {
                $.ajax({
                    url: '/user/merchant/blog_category.php', type: 'POST', dataType: 'json',
                    data: { _action: 'delete', csrf_token: csrfToken, id: id },
                    success: function (res) {
                        if (res.code === 200) {
                            if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                            layer.msg('已删除');
                            load();
                        } else { layer.msg(res.msg || '删除失败'); }
                    },
                    complete: function () { layer.close(idx); }
                });
            });
        });

        function openPopup(row) {
            var isEdit = !!row;
            var topOptions = '<option value="0">顶级分类</option>';
            topCats.forEach(function (c) {
                if (isEdit && c.id == row.id) return;
                topOptions += '<option value="' + c.id + '"' + (isEdit && row.parent_id == c.id ? ' selected' : '') + '>' + escapeHtml(c.name) + '</option>';
            });

            var html = '<form class="layui-form" id="mcBlogCatForm" style="padding:18px;">'
                + '<input type="hidden" name="csrf_token" value="' + csrfToken + '">'
                + '<input type="hidden" name="_action" value="save">'
                + '<input type="hidden" name="id" value="' + (isEdit ? row.id : '') + '">'
                + '<div class="layui-form-item"><label class="layui-form-label">父分类</label><div class="layui-input-block"><select name="parent_id">' + topOptions + '</select></div></div>'
                + '<div class="layui-form-item"><label class="layui-form-label">分类名</label><div class="layui-input-block"><input type="text" class="layui-input" name="name" maxlength="100" value="' + (isEdit ? escapeHtml(row.name) : '') + '" placeholder="如：行业资讯"></div></div>'
                + '<div class="layui-form-item"><label class="layui-form-label">别名 slug</label><div class="layui-input-block"><input type="text" class="layui-input" name="slug" maxlength="100" value="' + (isEdit ? escapeHtml(row.slug || '') : '') + '" placeholder="选填，URL 中显示，如 news"></div></div>'
                + '<div class="layui-form-item"><label class="layui-form-label">描述</label><div class="layui-input-block"><textarea class="layui-textarea" name="description" maxlength="500" placeholder="选填">' + (isEdit ? escapeHtml(row.description || '') : '') + '</textarea></div></div>'
                + '<div class="layui-form-item"><label class="layui-form-label">图标</label><div class="layui-input-block"><input type="text" class="layui-input" name="icon" maxlength="255" value="' + (isEdit ? escapeHtml(row.icon || '') : '') + '" placeholder="选填，如 fa fa-folder"></div></div>'
                + '<div class="layui-form-item"><label class="layui-form-label">排序</label><div class="layui-input-block"><input type="number" class="layui-input" name="sort" value="' + (isEdit ? row.sort : 100) + '"></div></div>'
                + '<div class="layui-form-item"><label class="layui-form-label">启用</label><div class="layui-input-block"><input type="checkbox" name="status" value="1" lay-skin="switch" lay-text="启用|禁用"' + (isEdit && parseInt(row.status, 10) === 0 ? '' : ' checked') + '></div></div>'
                + '</form>';

            layer.open({
                type: 1, title: isEdit ? '编辑分类' : '添加分类', skin: 'admin-modal',
                area: [window.innerWidth >= 600 ? '500px' : '95%', 'auto'],
                content: html,
                btn: ['保存', '取消'],
                success: function () { form.render(); },
                yes: function (idx) {
                    var data = $('#mcBlogCatForm').serializeArray();
                    var hasStatus = false;
                    $.each(data, function (_, it) { if (it.name === 'status') hasStatus = true; });
                    if (!hasStatus) data.push({ name: 'status', value: '0' });

                    $.ajax({
                        url: '/user/merchant/blog_category.php', type: 'POST', dataType: 'json',
                        data: $.param(data),
                        success: function (res) {
                            if (res.code === 200) {
                                if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                                layer.close(idx);
                                layer.msg(res.msg || '已保存');
                                load();
                            } else { layer.msg(res.msg || '保存失败'); }
                        }
                    });
                }
            });
        }

        load();
    });
});
</script>
