<?php
/** @var array<string, mixed> $frontUser */
/** @var array<string, mixed> $currentMerchant */
/** @var array<string, mixed> $uc */
/** @var array<int, array<string, mixed>> $blogCategories 本店博客分类，用于选择 blog_cat 类型 */
/** @var array<int, array{id:int,title:string,slug:string}> $publishedPages 本店已发布页面，用于选择 page 类型 */

$csrfToken = Csrf::token();
?>
<div class="mc-page">
    <div class="mc-page-header">
        <h2 class="mc-page-title">导航管理</h2>
        <p class="mc-page-desc">系统导航全站共享、本页仅展示；自定义导航只在本店生效，可指向任意 URL 或本店博客分类</p>
    </div>

    <div class="mc-card">
        <div class="mc-card__toolbar">
            <div class="mc-card__hint" style="padding:0;margin:0;color:#9ca3af;font-size:12px;">
                <i class="fa fa-info-circle" style="color:#9ca3af;"></i>
                带"系统"标记的是主站全站统一的导航，本店看得到改不了；其余是本店自定义的导航。
            </div>
            <button type="button" class="mc-btn mc-btn--primary" id="mcNaviAddBtn"><i class="fa fa-plus"></i> 添加导航</button>
        </div>
        <div id="mcNaviList">
            <div class="mc-placeholder" style="padding:30px;"><i class="fa fa-spinner fa-spin"></i> 加载中...</div>
        </div>
    </div>
</div>

<style>
.mc-card { background:#fff; border-radius:10px; padding:16px 18px; box-shadow:0 1px 3px rgba(0,0,0,0.04); }
.mc-card__toolbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; gap:12px; }
.mc-card__hint { padding:10px 12px; background:#f0f9ff; border-left:3px solid #38bdf8; border-radius:4px; color:#0c4a6e; font-size:12px; line-height:1.7; }
.mc-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border:1px solid #e5e7eb; border-radius:6px; background:#fff; color:#555; font-size:13px; cursor:pointer; transition:border-color .15s,color .15s,background .15s; }
.mc-btn:hover { border-color:#4e6ef2; color:#4e6ef2; }
.mc-btn--primary { background:#4e6ef2; border-color:#4e6ef2; color:#fff; }
.mc-btn--primary:hover { background:#3d5bd9; border-color:#3d5bd9; color:#fff; }
.mc-btn--danger { border-color:#fee2e2; color:#ef4444; }
.mc-btn--danger:hover { background:#fee2e2; border-color:#fecaca; color:#dc2626; }
.mc-btn--sm { padding:3px 10px; font-size:12px; }

.mc-navi-list { background:#fff; border:1px solid #f0f1f4; border-radius:8px; overflow:hidden; }
.mc-navi-row {
    display:grid; grid-template-columns: 1.2fr 0.8fr 1.5fr 80px 80px 220px;
    gap:10px; padding:12px 14px; border-bottom:1px solid #f3f4f6; align-items:center; transition:background .15s;
}
.mc-navi-row:last-child { border-bottom:0; }
.mc-navi-row:hover:not(.mc-navi-row--head) { background:#fafbfc; }
.mc-navi-row.is-child { padding-left:38px; background:#fafbfc; }
.mc-navi-row.is-dim { background:#fafafa; opacity:0.7; }
.mc-navi-row--head { background:#f9fafb; font-weight:500; color:#6b7280; font-size:12px; }
.mc-navi-row--head:hover { background:#f9fafb; }
.mc-navi-tag { display:inline-block; padding:1px 8px; border-radius:10px; font-size:11px; }
.mc-navi-tag--sys { background:#fef3c7; color:#92400e; }
.mc-navi-tag--custom { background:#eef2ff; color:#4e6ef2; }
.mc-navi-tag--blog { background:#ecfdf5; color:#059669; }
.mc-navi-tag--page { background:#fce7f3; color:#a21caf; }
.mc-navi-tag--hidden { background:#f3f4f6; color:#6b7280; margin-left:6px; }
.mc-navi-link { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:12px; color:#6b7280; word-break:break-all; }
.mc-navi-actions { display:flex; gap:6px; justify-content:flex-end; }
</style>

<script>
$(function () {
    'use strict';
    var csrfToken = <?= json_encode($csrfToken) ?>;
    var blogCategories = <?= json_encode(array_values(array_map(function ($c) {
        return ['id' => (int) $c['id'], 'name' => $c['name'], 'parent_id' => (int) $c['parent_id']];
    }, $blogCategories ?? [])), JSON_UNESCAPED_UNICODE) ?>;
    var publishedPages = <?= json_encode(array_values(array_map(function ($p) {
        return ['id' => (int) $p['id'], 'title' => $p['title'], 'slug' => $p['slug']];
    }, $publishedPages ?? [])), JSON_UNESCAPED_UNICODE) ?>;

    // PJAX 防重复绑定
    $(document).off('.mcNaviPage');

    layui.use(['layer', 'form'], function () {
        var layer = layui.layer;
        var form = layui.form;

        var topItems = []; // 顶级导航缓存（添加子导航时选择父级用）
        var allRows = [];

        function loadList() {
            $.ajax({
                url: '/user/merchant/navi.php',
                type: 'POST', dataType: 'json',
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
            topItems = rows.filter(function (r) { return parseInt(r.parent_id, 10) === 0; });
            var children = rows.filter(function (r) { return parseInt(r.parent_id, 10) > 0; });
            var byParent = {};
            children.forEach(function (c) { (byParent[c.parent_id] = byParent[c.parent_id] || []).push(c); });

            if (!topItems.length) {
                $('#mcNaviList').html('<div class="mc-placeholder"><i class="fa fa-compass"></i><div>还没有导航，点击右上角添加第一个</div></div>');
                return;
            }

            var html = '<div class="mc-navi-list">'
                + '<div class="mc-navi-row mc-navi-row--head">'
                +   '<div>名称</div><div>类型</div><div>链接</div>'
                +   '<div style="text-align:center;">排序</div><div style="text-align:center;">状态</div>'
                +   '<div style="text-align:right;">操作</div>'
                + '</div>';

            topItems.forEach(function (it) {
                html += rowHtml(it, false);
                (byParent[it.id] || []).forEach(function (ch) {
                    html += rowHtml(ch, true);
                });
            });
            html += '</div>';
            $('#mcNaviList').html(html);
        }

        function rowHtml(r, isChild) {
            var isSys = parseInt(r.is_system, 10) === 1;
            var isMine = !isSys && parseInt(r.merchant_id, 10) === <?= (int) $currentMerchant['id'] ?>;
            var hiddenForMe = parseInt(r.is_hidden_for_me, 10) === 1;
            var typeTag = '';
            if (isSys) {
                typeTag = '<span class="mc-navi-tag mc-navi-tag--sys">系统</span>';
                if (hiddenForMe) typeTag += '<span class="mc-navi-tag mc-navi-tag--hidden">本店已隐藏</span>';
            } else if (r.type === 'blog_cat') typeTag = '<span class="mc-navi-tag mc-navi-tag--blog">博客分类</span>';
            else if (r.type === 'page') typeTag = '<span class="mc-navi-tag mc-navi-tag--page">页面</span>';
            else typeTag = '<span class="mc-navi-tag mc-navi-tag--custom">自定义</span>';

            var status = parseInt(r.status, 10) === 1
                ? '<span style="display:inline-block;padding:1px 8px;border-radius:10px;background:#ecfdf5;color:#059669;font-size:12px;">启用</span>'
                : '<span style="display:inline-block;padding:1px 8px;border-radius:10px;background:#f3f4f6;color:#9ca3af;font-size:12px;">禁用</span>';

            var actions = '';
            if (isMine) {
                actions = '<button class="mc-btn mc-btn--sm mc-navi-toggle" data-id="' + r.id + '">'
                        +   (parseInt(r.status, 10) === 1 ? '<i class="fa fa-pause"></i> 禁用' : '<i class="fa fa-play"></i> 启用')
                        + '</button>'
                        + '<button class="mc-btn mc-btn--sm mc-navi-edit" data-id="' + r.id + '"><i class="fa fa-pencil"></i> 编辑</button>'
                        + '<button class="mc-btn mc-btn--sm mc-btn--danger mc-navi-del" data-id="' + r.id + '" data-name="' + escapeHtml(r.name) + '"><i class="fa fa-trash"></i></button>';
            } else if (isSys) {
                // 系统导航只能"在本店隐藏 / 显示"
                actions = '<button class="mc-btn mc-btn--sm mc-navi-toggle-hide" data-id="' + r.id + '">'
                        + (hiddenForMe ? '<i class="fa fa-eye"></i> 在本店显示' : '<i class="fa fa-eye-slash"></i> 在本店隐藏')
                        + '</button>';
            } else {
                actions = '<span style="color:#9ca3af;font-size:12px;">非本店导航</span>';
            }

            return '<div class="mc-navi-row' + (isChild ? ' is-child' : '') + (hiddenForMe ? ' is-dim' : '') + '">'
                +   '<div>' + (isChild ? '<i class="fa fa-level-up fa-rotate-90" style="color:#d1d5db;margin-right:6px;"></i>' : '')
                +     escapeHtml(r.name) + '</div>'
                +   '<div>' + typeTag + '</div>'
                +   '<div class="mc-navi-link">' + escapeHtml(r.link || '') + '</div>'
                +   '<div style="text-align:center;color:#6b7280;">' + r.sort + '</div>'
                +   '<div style="text-align:center;">' + status + '</div>'
                +   '<div class="mc-navi-actions">' + actions + '</div>'
                + '</div>';
        }

        function escapeHtml(s) {
            return String(s == null ? '' : s).replace(/[<>&"']/g, function (m) {
                return {'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&#39;'}[m];
            });
        }

        // ============ 操作 ============
        $(document).on('click.mcNaviPage', '#mcNaviAddBtn', function () { openPopup(null); });
        $(document).on('click.mcNaviPage', '.mc-navi-edit', function () {
            var id = $(this).data('id');
            var row = allRows.find(function (r) { return r.id == id; });
            if (row) openPopup(row);
        });
        $(document).on('click.mcNaviPage', '.mc-navi-del', function () {
            var id = $(this).data('id');
            var name = $(this).data('name');
            layer.confirm('确定删除「' + name + '」？', function (idx) {
                $.ajax({
                    url: '/user/merchant/navi.php', type: 'POST', dataType: 'json',
                    data: { _action: 'delete', csrf_token: csrfToken, id: id },
                    success: function (res) {
                        if (res.code === 200) {
                            if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                            layer.msg('已删除');
                            loadList();
                        } else { layer.msg(res.msg || '删除失败'); }
                    },
                    complete: function () { layer.close(idx); }
                });
            });
        });
        $(document).on('click.mcNaviPage', '.mc-navi-toggle', function () {
            var id = $(this).data('id');
            $.ajax({
                url: '/user/merchant/navi.php', type: 'POST', dataType: 'json',
                data: { _action: 'toggle_status', csrf_token: csrfToken, id: id },
                success: function (res) {
                    if (res.code === 200) {
                        if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                        layer.msg(res.msg || '已更新');
                        loadList();
                    } else { layer.msg(res.msg || '更新失败'); }
                }
            });
        });
        // 系统导航：在本店隐藏 / 显示
        $(document).on('click.mcNaviPage', '.mc-navi-toggle-hide', function () {
            var id = $(this).data('id');
            $.ajax({
                url: '/user/merchant/navi.php', type: 'POST', dataType: 'json',
                data: { _action: 'toggle_hide_system', csrf_token: csrfToken, id: id },
                success: function (res) {
                    if (res.code === 200) {
                        if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                        layer.msg(res.msg || '已更新');
                        loadList();
                    } else { layer.msg(res.msg || '更新失败'); }
                }
            });
        });

        function openPopup(row) {
            var isEdit = !!row;
            var topOptions = '<option value="0">顶级导航</option>';
            topItems.forEach(function (t) {
                if (isEdit && t.id == row.id) return;
                topOptions += '<option value="' + t.id + '"' + (isEdit && row.parent_id == t.id ? ' selected' : '') + '>' + escapeHtml(t.name) + '</option>';
            });

            var blogOptions = '<option value="">请选择博客分类</option>';
            blogCategories.forEach(function (c) {
                blogOptions += '<option value="' + c.id + '"' + (isEdit && row.type === 'blog_cat' && row.type_ref_id == c.id ? ' selected' : '') + '>' + escapeHtml(c.name) + '</option>';
            });

            var pageOptions = '<option value="">请选择页面</option>';
            publishedPages.forEach(function (p) {
                pageOptions += '<option value="' + p.id + '"' + (isEdit && row.type === 'page' && row.type_ref_id == p.id ? ' selected' : '') + '>' + escapeHtml(p.title) + '（' + escapeHtml(p.slug) + '）</option>';
            });

            var curType = isEdit ? (row.type || 'custom') : 'custom';
            var typeRadio = ''
                + '<input type="radio" name="type" value="custom" lay-filter="naviType"' + (curType === 'custom' ? ' checked' : '') + ' title="自定义链接">'
                + '<input type="radio" name="type" value="blog_cat" lay-filter="naviType"' + (curType === 'blog_cat' ? ' checked' : '') + ' title="本店博客分类">'
                + '<input type="radio" name="type" value="page" lay-filter="naviType"' + (curType === 'page' ? ' checked' : '') + ' title="本店页面">';

            var html = '<form class="layui-form" id="mcNaviForm" style="padding:18px;">'
                + '<input type="hidden" name="csrf_token" value="' + csrfToken + '">'
                + '<input type="hidden" name="_action" value="' + (isEdit ? 'update' : 'create') + '">'
                + '<input type="hidden" name="id" value="' + (isEdit ? row.id : '') + '">'
                + '<div class="layui-form-item"><label class="layui-form-label">父导航</label><div class="layui-input-block"><select name="parent_id">' + topOptions + '</select></div></div>'
                + '<div class="layui-form-item"><label class="layui-form-label">名称</label><div class="layui-input-block"><input type="text" class="layui-input" name="name" maxlength="100" value="' + (isEdit ? escapeHtml(row.name) : '') + '" placeholder="如：联系我们"></div></div>'
                + '<div class="layui-form-item"><label class="layui-form-label">类型</label><div class="layui-input-block">' + typeRadio + '</div></div>'
                + '<div class="layui-form-item mcNaviRefBlogRow"' + (curType === 'blog_cat' ? '' : ' style="display:none;"') + '><label class="layui-form-label">博客分类</label><div class="layui-input-block"><select name="type_ref_id_blog">' + blogOptions + '</select></div></div>'
                + '<div class="layui-form-item mcNaviRefPageRow"' + (curType === 'page' ? '' : ' style="display:none;"') + '><label class="layui-form-label">页面</label><div class="layui-input-block"><select name="type_ref_id_page">' + pageOptions + '</select>' + (publishedPages.length === 0 ? '<div class="layui-form-mid layui-word-aux">尚无已发布页面，<a href="/user/merchant/page.php" target="_blank" style="color:#4e6ef2;">去创建</a></div>' : '') + '</div></div>'
                + '<div class="layui-form-item mcNaviLinkRow"' + (curType === 'custom' ? '' : ' style="display:none;"') + '><label class="layui-form-label">链接</label><div class="layui-input-block"><input type="text" class="layui-input" name="link" maxlength="500" value="' + (isEdit && row.type === 'custom' ? escapeHtml(row.link || '') : '') + '" placeholder="如 https://example.com 或 ?c=page&id=1"></div></div>'
                + '<div class="layui-form-item"><label class="layui-form-label">图标</label><div class="layui-input-block"><input type="text" class="layui-input" name="icon" maxlength="255" value="' + (isEdit ? escapeHtml(row.icon || '') : '') + '" placeholder="选填，如 fa fa-home"></div></div>'
                + '<div class="layui-form-item"><label class="layui-form-label">打开方式</label><div class="layui-input-block">'
                +   '<input type="radio" name="target" value="_self" title="当前窗口"' + (isEdit && row.target === '_blank' ? '' : ' checked') + '>'
                +   '<input type="radio" name="target" value="_blank" title="新窗口"' + (isEdit && row.target === '_blank' ? ' checked' : '') + '>'
                + '</div></div>'
                + '<div class="layui-form-item"><label class="layui-form-label">排序</label><div class="layui-input-block"><input type="number" class="layui-input" name="sort" value="' + (isEdit ? row.sort : 100) + '"></div></div>'
                + '<div class="layui-form-item"><label class="layui-form-label">启用</label><div class="layui-input-block"><input type="checkbox" name="status" value="1" lay-skin="switch" lay-text="启用|禁用"' + (isEdit && parseInt(row.status, 10) === 0 ? '' : ' checked') + '></div></div>'
                + '</form>';

            layer.open({
                type: 1, title: isEdit ? '编辑导航' : '添加导航', skin: 'admin-modal',
                area: [window.innerWidth >= 600 ? '520px' : '95%', 'auto'],
                content: html,
                btn: ['保存', '取消'],
                success: function () {
                    form.render();
                    form.on('radio(naviType)', function (data) {
                        $('.mcNaviRefBlogRow').toggle(data.value === 'blog_cat');
                        $('.mcNaviRefPageRow').toggle(data.value === 'page');
                        $('.mcNaviLinkRow').toggle(data.value === 'custom');
                    });
                },
                yes: function (idx) {
                    var data = $('#mcNaviForm').serializeArray();
                    var hasStatus = false;
                    var typeVal = 'custom';
                    $.each(data, function (_, it) {
                        if (it.name === 'status') hasStatus = true;
                        if (it.name === 'type') typeVal = it.value;
                    });
                    if (!hasStatus) data.push({ name: 'status', value: '0' });

                    // 把对应类型的 type_ref_id_* 折叠成 type_ref_id（后端只认 type_ref_id）
                    var refKey = typeVal === 'blog_cat' ? 'type_ref_id_blog'
                              : (typeVal === 'page' ? 'type_ref_id_page' : null);
                    var refVal = '';
                    if (refKey) {
                        $.each(data, function (_, it) { if (it.name === refKey) { refVal = it.value; } });
                    }
                    // 移除内部字段，再加规范字段
                    data = data.filter(function (it) { return it.name !== 'type_ref_id_blog' && it.name !== 'type_ref_id_page'; });
                    data.push({ name: 'type_ref_id', value: refVal });

                    $.ajax({
                        url: '/user/merchant/navi.php', type: 'POST', dataType: 'json',
                        data: $.param(data),
                        success: function (res) {
                            if (res.code === 200) {
                                if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                                layer.close(idx);
                                layer.msg(res.msg || '已保存');
                                loadList();
                            } else { layer.msg(res.msg || '保存失败'); }
                        }
                    });
                }
            });
        }

        loadList();
    });
});
</script>
