<?php
/** @var array<string, mixed> $frontUser */
/** @var array<string, mixed> $currentMerchant */
/** @var array<string, mixed> $uc */

$csrfToken = Csrf::token();
?>
<div class="mc-page">
    <div class="mc-page-header">
        <h2 class="mc-page-title">分类管理</h2>
        <p class="mc-page-desc">给主站分类在本店起一个别名，或维护本店独立的自定义分类</p>
    </div>

    <!-- 选项卡：主站分类 在前；自定义分类 在后 -->
    <div class="mc-cat-tabs">
        <button type="button" class="mc-cat-tab is-active" data-tab="map"><i class="fa fa-sitemap"></i> 主站分类</button>
        <button type="button" class="mc-cat-tab" data-tab="cat"><i class="fa fa-folder"></i> 自定义分类</button>
    </div>

    <!-- 主站分类（分类映射，默认显示） -->
    <div id="mcMapPane">
        <div class="mc-card">
            <div class="mc-card__hint">
                <i class="fa fa-info-circle"></i> 在下表中填写别名即可让主站分类在本店以别的名称展示；留空则使用主站原名。
            </div>
            <div id="mcMapList">
                <div class="mc-placeholder" style="padding:30px;"><i class="fa fa-spinner fa-spin"></i> 加载中...</div>
            </div>
        </div>
    </div>

    <!-- 自定义分类 -->
    <div id="mcCatPane" style="display:none;">
        <div class="mc-card">
            <div class="mc-card__toolbar">
                <div class="mc-card__hint" style="padding:0;margin:0;color:#9ca3af;font-size:12px;">本店的独立分类，只在本店商品上生效</div>
                <button type="button" class="mc-btn mc-btn--primary" id="mcCatAddBtn"><i class="fa fa-plus"></i> 添加分类</button>
            </div>
            <div id="mcCatList">
                <div class="mc-placeholder" style="padding:30px;"><i class="fa fa-spinner fa-spin"></i> 加载中...</div>
            </div>
        </div>
    </div>
</div>

<style>
/* ===== 分类页（主站分类 / 自定义分类）===== */
.mc-cat-tabs {
    display:flex; gap:4px; margin-bottom:14px;
    background:#fff; padding:6px; border-radius:10px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.mc-cat-tab {
    flex:none; padding:8px 18px; border:0; border-radius:6px;
    background:transparent; color:#555; font-size:13px; cursor:pointer;
    display:inline-flex; align-items:center; gap:6px; transition:background .15s, color .15s;
}
.mc-cat-tab:hover { background:#f5f7fa; color:#333; }
.mc-cat-tab.is-active { background:#eef2ff; color:#4e6ef2; font-weight:500; }

.mc-card {
    background:#fff; border-radius:10px; padding:16px 18px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.mc-card__hint {
    margin-bottom:12px; padding:10px 12px; background:#f0f9ff;
    border-left:3px solid #38bdf8; border-radius:4px;
    color:#0c4a6e; font-size:12px; line-height:1.7;
}
.mc-card__toolbar {
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:12px; gap:12px;
}
.mc-btn {
    display:inline-flex; align-items:center; gap:6px;
    padding:6px 14px; border:1px solid #e5e7eb; border-radius:6px;
    background:#fff; color:#555; font-size:13px; cursor:pointer;
    transition:border-color .15s, color .15s, background .15s;
}
.mc-btn:hover { border-color:#4e6ef2; color:#4e6ef2; }
.mc-btn--primary { background:#4e6ef2; border-color:#4e6ef2; color:#fff; }
.mc-btn--primary:hover { background:#3d5bd9; border-color:#3d5bd9; color:#fff; }
.mc-btn--danger { border-color:#fee2e2; color:#ef4444; }
.mc-btn--danger:hover { background:#fee2e2; border-color:#fecaca; color:#dc2626; }
.mc-btn--sm { padding:3px 10px; font-size:12px; }

.mc-cat-list { background:#fff; border:1px solid #f0f1f4; border-radius:8px; overflow:hidden; }
.mc-cat-row {
    display:grid; grid-template-columns: 1fr 100px 100px 180px;
    gap:10px; padding:12px 14px;
    border-bottom: 1px solid #f3f4f6; align-items:center;
    transition: background .15s;
}
.mc-cat-row:last-child { border-bottom:0; }
.mc-cat-row:hover:not(.mc-cat-row--head) { background:#fafbfc; }
.mc-cat-row.is-child { padding-left: 38px; background:#fafbfc; }
.mc-cat-row.is-child:hover { background:#f5f6f8; }
.mc-cat-row--head { background:#f9fafb; font-weight:500; color:#6b7280; font-size:12px; }
.mc-cat-row--head:hover { background:#f9fafb; }
.mc-cat-name { font-size:14px; color:#1f2937; }
.mc-cat-name-child { color:#6b7280; font-size:13px; }
.mc-cat-meta { color:#9ca3af; font-size:12px; }
.mc-cat-actions { text-align:right; display:flex; justify-content:flex-end; gap:6px; }

.mc-map-row {
    display:grid; grid-template-columns: 1fr 1fr 180px;
    gap:12px; padding:10px 14px;
    border-bottom: 1px solid #f3f4f6; align-items:center;
    transition: background .15s;
}
.mc-map-row:last-child { border-bottom:0; }
.mc-map-row:hover:not(.mc-map-row--head) { background:#fafbfc; }
.mc-map-row.is-child { padding-left: 38px; background:#fafbfc; }
.mc-map-row--head { background:#f9fafb; font-weight:500; color:#6b7280; font-size:12px; }
.mc-map-row--head:hover { background:#f9fafb; }
.mc-map-row input[type="text"] {
    width:100%; box-sizing:border-box; padding:6px 10px; font-size:13px;
    border:1px solid #e5e7eb; border-radius:5px; outline:none;
    transition: border-color .15s;
}
.mc-map-row input[type="text"]:focus { border-color:#4e6ef2; }
</style>

<script>
$(function(){
    'use strict';
    var csrfToken = <?php echo json_encode($csrfToken); ?>;

    // PJAX 防重复绑定：商户后台用 PJAX 切来切去，document 上的 delegated handler 会叠加。
    // 统一用 .mcCatPage 命名空间，进页时先 off 一次清掉历史 binding。
    $(document).off('.mcCatPage');

    layui.use(['layer', 'form'], function () {
        var layer = layui.layer;
        var form = layui.form;

        var topCats = []; // 顶级分类缓存（供添加子分类时选择）

        // Tab 切换（主站分类 默认激活；切到自定义分类才惰性加载一次）
        $(document).on('click.mcCatPage', '.mc-cat-tab', function () {
            $('.mc-cat-tab').removeClass('is-active');
            $(this).addClass('is-active');
            var tab = $(this).data('tab');
            $('#mcCatPane').toggle(tab === 'cat');
            $('#mcMapPane').toggle(tab === 'map');
            if (tab === 'cat' && !catLoaded) loadCats();
        });

        // ============ 自定义分类 ============
        var catLoaded = false;
        function loadCats() {
            catLoaded = true;
            $.ajax({
                url: '/user/merchant/category.php',
                type: 'POST',
                dataType: 'json',
                data: {_action: 'list'},
                success: function (res) {
                    if (res.code !== 200) { layer.msg(res.msg || '加载失败'); return; }
                    if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                    renderCats(res.data.data || []);
                }
            });
        }

        function renderCats(rows) {
            topCats = rows.filter(function (r) { return r.parent_id == 0; });
            var children = rows.filter(function (r) { return r.parent_id != 0; });
            var byParent = {};
            children.forEach(function (c) {
                (byParent[c.parent_id] = byParent[c.parent_id] || []).push(c);
            });

            if (!topCats.length) {
                $('#mcCatList').html('<div class="mc-placeholder"><i class="fa fa-folder-open-o"></i><div>还没有分类，点击右上角添加第一个</div></div>');
                return;
            }

            var html = '<div class="mc-cat-list">'
                 + '<div class="mc-cat-row mc-cat-row--head">'
                 +   '<div>分类名</div>'
                 +   '<div style="text-align:center;">排序</div>'
                 +   '<div style="text-align:center;">状态</div>'
                 +   '<div style="text-align:right;">操作</div>'
                 + '</div>';

            topCats.forEach(function (c) {
                html += rowHtml(c, false);
                (byParent[c.id] || []).forEach(function (ch) {
                    html += rowHtml(ch, true);
                });
            });
            html += '</div>';
            $('#mcCatList').html(html);
        }

        function rowHtml(c, isChild) {
            var status = c.status == 1
                ? '<span style="display:inline-block;padding:1px 8px;border-radius:10px;background:#ecfdf5;color:#059669;font-size:12px;">启用</span>'
                : '<span style="display:inline-block;padding:1px 8px;border-radius:10px;background:#f3f4f6;color:#9ca3af;font-size:12px;">禁用</span>';
            return '<div class="mc-cat-row' + (isChild ? ' is-child' : '') + '">'
                 +   '<div>'
                 +     (isChild ? '<i class="fa fa-level-up fa-rotate-90" style="color:#d1d5db;margin-right:6px;"></i>' : '')
                 +     '<span class="' + (isChild ? 'mc-cat-name-child' : 'mc-cat-name') + '">' + escapeHtml(c.name) + '</span>'
                 +     (c.icon ? ' <i class="' + escapeHtml(c.icon) + '" style="margin-left:4px;color:#9ca3af;"></i>' : '')
                 +   '</div>'
                 +   '<div style="text-align:center;color:#6b7280;">' + c.sort + '</div>'
                 +   '<div style="text-align:center;">' + status + '</div>'
                 +   '<div class="mc-cat-actions">'
                 +     '<button class="mc-btn mc-btn--sm mc-cat-edit" data-id="' + c.id + '"><i class="fa fa-pencil"></i> 编辑</button>'
                 +     '<button class="mc-btn mc-btn--sm mc-btn--danger mc-cat-del" data-id="' + c.id + '" data-name="' + escapeHtml(c.name) + '"><i class="fa fa-trash"></i></button>'
                 +   '</div>'
                 + '</div>';
        }

        function escapeHtml(s) {
            return String(s || '').replace(/[<>&"']/g, function (m) {
                return {'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&#39;'}[m];
            });
        }

        $(document).on('click.mcCatPage', '#mcCatAddBtn', function () { openCatPopup(null); });
        $(document).on('click.mcCatPage', '.mc-cat-edit', function () {
            var id = $(this).data('id');
            var all = topCats.concat();
            // 从整个列表再找
            $.ajax({url:'/user/merchant/category.php',type:'POST',dataType:'json',data:{_action:'list'}})
                .done(function (res) {
                    if (!res || res.code !== 200) { layer.msg('加载失败'); return; }
                    var row = (res.data.data || []).find(function (r) { return r.id == id; });
                    if (row) openCatPopup(row);
                });
        });
        $(document).on('click.mcCatPage', '.mc-cat-del', function () {
            var id = $(this).data('id');
            var name = $(this).data('name');
            layer.confirm('确定删除分类「' + name + '」？', function (idx) {
                $.ajax({
                    url: '/user/merchant/category.php',
                    type: 'POST', dataType: 'json',
                    data: {_action: 'delete', csrf_token: csrfToken, id: id},
                    success: function (res) {
                        if (res.code === 200) {
                            if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                            layer.msg('已删除');
                            loadCats();
                        } else { layer.msg(res.msg || '删除失败'); }
                    },
                    complete: function () { layer.close(idx); }
                });
            });
        });

        function openCatPopup(row) {
            var isEdit = !!row;
            var topOptions = '<option value="0">顶级分类</option>';
            topCats.forEach(function (c) {
                if (isEdit && c.id == row.id) return; // 不能选自己
                topOptions += '<option value="' + c.id + '"' + (isEdit && row.parent_id == c.id ? ' selected' : '') + '>' + escapeHtml(c.name) + '</option>';
            });

            var html = '<form class="layui-form" id="mcCatForm" style="padding:18px;">'
                 + '<input type="hidden" name="csrf_token" value="' + csrfToken + '">'
                 + '<input type="hidden" name="_action" value="save">'
                 + '<input type="hidden" name="id" value="' + (isEdit ? row.id : '') + '">'
                 + '<div class="layui-form-item"><label class="layui-form-label">父分类</label><div class="layui-input-block"><select name="parent_id">' + topOptions + '</select></div></div>'
                 + '<div class="layui-form-item"><label class="layui-form-label">分类名</label><div class="layui-input-block"><input type="text" class="layui-input" name="name" maxlength="100" value="' + (isEdit ? escapeHtml(row.name) : '') + '" placeholder="如：数码产品"></div></div>'
                 + '<div class="layui-form-item"><label class="layui-form-label">图标</label><div class="layui-input-block"><input type="text" class="layui-input" name="icon" maxlength="255" value="' + (isEdit ? escapeHtml(row.icon) : '') + '" placeholder="选填，fa-xxx 或图片 URL"></div></div>'
                 + '<div class="layui-form-item"><label class="layui-form-label">排序</label><div class="layui-input-block"><input type="number" class="layui-input" name="sort" value="' + (isEdit ? row.sort : 100) + '"></div></div>'
                 + '<div class="layui-form-item"><label class="layui-form-label">启用</label><div class="layui-input-block"><input type="checkbox" name="status" value="1" lay-skin="switch" lay-text="启用|禁用"' + (isEdit && row.status == 0 ? '' : ' checked') + '></div></div>'
                 + '</form>';

            layer.open({
                type: 1,
                title: isEdit ? '编辑分类' : '添加分类',
                skin: 'admin-modal',
                area: [window.innerWidth >= 600 ? '480px' : '95%', 'auto'],
                content: html,
                btn: ['保存', '取消'],
                success: function () { form.render(); },
                yes: function (idx) {
                    var data = $('#mcCatForm').serializeArray();
                    var has = false;
                    $.each(data, function (_, it) { if (it.name === 'status') has = true; });
                    if (!has) data.push({name: 'status', value: '0'});

                    $.ajax({
                        url: '/user/merchant/category.php',
                        type: 'POST', dataType: 'json',
                        data: $.param(data),
                        success: function (res) {
                            if (res.code === 200) {
                                if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                                layer.close(idx);
                                layer.msg(res.msg || '已保存');
                                loadCats();
                            } else { layer.msg(res.msg || '保存失败'); }
                        }
                    });
                }
            });
        }

        // ============ 主站分类（别名映射） ============
        function loadMap() {
            $.ajax({
                url: '/user/merchant/category.php',
                type: 'POST', dataType: 'json',
                data: {_action: 'list_map'},
                success: function (res) {
                    if (res.code !== 200) { layer.msg(res.msg || '加载失败'); return; }
                    if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                    renderMap(res.data.data || []);
                }
            });
        }
        function renderMap(cats) {
            if (!cats.length) {
                $('#mcMapList').html('<div class="mc-placeholder"><i class="fa fa-folder-open-o"></i><div>主站暂无可用分类</div></div>');
                return;
            }
            var tops = cats.filter(function (c) { return c.parent_id == 0; });
            var children = cats.filter(function (c) { return c.parent_id != 0; });
            var byParent = {};
            children.forEach(function (c) {
                (byParent[c.parent_id] = byParent[c.parent_id] || []).push(c);
            });

            var html = '<div class="mc-cat-list">'
                 + '<div class="mc-map-row mc-map-row--head">'
                 +   '<div>主站分类</div><div>本店别名（留空则沿用原名）</div><div style="text-align:right;">操作</div>'
                 + '</div>';

            tops.forEach(function (c) {
                html += mapRowHtml(c, false);
                (byParent[c.id] || []).forEach(function (ch) {
                    html += mapRowHtml(ch, true);
                });
            });
            html += '</div>';
            $('#mcMapList').html(html);
        }
        function mapRowHtml(c, isChild) {
            var hidden = parseInt(c.is_hidden, 10) === 1;
            var hideBtnLabel = hidden ? '<i class="fa fa-eye"></i> 显示' : '<i class="fa fa-eye-slash"></i> 隐藏';
            var hideBtnClass = hidden ? 'mc-btn--warn' : '';
            var hiddenTag = hidden ? ' <span class="em-tag em-tag--muted" style="margin-left:6px;">已隐藏</span>' : '';
            return '<div class="mc-map-row' + (isChild ? ' is-child' : '') + '" data-master-id="' + c.id + '">'
                 +   '<div>'
                 +     (isChild ? '<i class="fa fa-level-up fa-rotate-90" style="color:#d1d5db;margin-right:6px;"></i>' : '')
                 +     escapeHtml(c.name)
                 +     hiddenTag
                 +   '</div>'
                 +   '<div><input type="text" class="mc-map-alias" data-id="' + c.id + '" value="' + escapeHtml(c.alias_name || '') + '" placeholder="保持留空则使用主站原名" maxlength="100"></div>'
                 +   '<div style="text-align:right;display:flex;gap:6px;justify-content:flex-end;">'
                 +     '<button type="button" class="mc-btn mc-btn--sm ' + hideBtnClass + ' mc-map-toggle-hide" data-id="' + c.id + '" data-hidden="' + (hidden ? 1 : 0) + '">' + hideBtnLabel + '</button>'
                 +     '<button type="button" class="mc-btn mc-btn--sm mc-btn--primary mc-map-save" data-id="' + c.id + '"><i class="fa fa-check"></i> 保存</button>'
                 +   '</div>'
                 + '</div>';
        }
        $(document).on('click.mcCatPage', '.mc-map-save', function () {
            var id = $(this).data('id');
            var alias = $('.mc-map-alias[data-id="' + id + '"]').val();
            $.ajax({
                url: '/user/merchant/category.php',
                type: 'POST', dataType: 'json',
                data: {_action: 'save_map', csrf_token: csrfToken, master_category_id: id, alias_name: alias},
                success: function (res) {
                    if (res.code === 200) {
                        if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                        layer.msg(res.msg || '已保存');
                    } else { layer.msg(res.msg || '保存失败'); }
                }
            });
        });
        // 隐藏 / 恢复显示
        $(document).on('click.mcCatPage', '.mc-map-toggle-hide', function () {
            var id = $(this).data('id');
            $.ajax({
                url: '/user/merchant/category.php',
                type: 'POST', dataType: 'json',
                data: {_action: 'toggle_hide_map', csrf_token: csrfToken, master_category_id: id},
                success: function (res) {
                    if (res.code === 200) {
                        if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                        layer.msg(res.msg || '已更新');
                        loadMap(); // 重新拉一次保持 UI 状态一致
                    } else { layer.msg(res.msg || '更新失败'); }
                }
            });
        });

        // 默认激活主站分类 tab
        loadMap();
    });
});
</script>
