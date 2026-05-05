<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
?>
<style>
/* 和控制台 / 模板管理一致：去掉 .admin-page 白底，卡片/栅格浮在灰底画布上 */
.admin-page-attachment { padding: 8px 4px 40px; background: unset; }

/* ===== 顶部统计卡（3 格栅格） ===== */
.attach-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
    margin-bottom: 14px;
}
.attach-stat-card {
    display: flex; align-items: center; gap: 14px;
    padding: 16px 18px;
    background: #fff;
    border: 1px solid #e8e8ec;
    border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    transition: border-color .2s, box-shadow .2s;
}
.attach-stat-card:hover { border-color: #a5b4fc; box-shadow: 0 4px 16px rgba(99,102,241,.1); }
.attach-stat-card__icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}
.attach-stat-card__icon--blue   { background: #e0e7ff; color: #4f46e5; }
.attach-stat-card__icon--green  { background: #d1fae5; color: #059669; }
.attach-stat-card__icon--amber  { background: #fef3c7; color: #d97706; }
.attach-stat-card__num {
    font-size: 20px;
    font-weight: 600;
    color: #111827;
    line-height: 1.2;
}
.attach-stat-card__label {
    font-size: 12px;
    color: #6b7280;
    margin-top: 2px;
}

/* ===== 工具栏（刷新 / 全选 / 批量删除 / 选中计数） ===== */
.attach-toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 14px;
    padding: 10px 14px;
    background: #fff;
    border: 1px solid #e8e8ec;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.attach-toolbar__spacer { flex: 1; }
.attach-toolbar__selected {
    font-size: 13px;
    color: #6b7280;
    padding: 0 6px;
}
.attach-toolbar__selected strong { color: #4f46e5; font-weight: 600; }

/* ===== 图片网格 ===== */
.attach-grid-card {
    background: #fff;
    border: 1px solid #e8e8ec;
    border-radius: 14px;
    padding: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    min-height: 400px;
}
.attach-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 12px;
}
.attach-item {
    position: relative;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
    background: #f9fafb;
    aspect-ratio: 1;
    transition: border-color .2s, box-shadow .2s, transform .15s;
}
.attach-item:hover {
    border-color: #6366f1;
    box-shadow: 0 4px 14px rgba(99,102,241,.18);
    transform: translateY(-1px);
}
.attach-item.is-selected {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79,70,229,.2);
}
.attach-item__thumb {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    cursor: zoom-in;
}
.attach-item__file {
    width: 100%; height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #9ca3af;
    cursor: default;
}
.attach-item__file i { font-size: 40px; }
.attach-item__file-ext {
    font-size: 13px;
    font-weight: 600;
    color: #6366f1;
    padding: 2px 8px;
    border: 1px solid #c7d2fe;
    border-radius: 4px;
    background: #eef2ff;
}
.attach-item__check {
    position: absolute;
    top: 8px; left: 8px;
    width: 22px; height: 22px;
    border-radius: 50%;
    background: rgba(255,255,255,0.95);
    border: 1.5px solid #d1d5db;
    display: flex; align-items: center; justify-content: center;
    z-index: 2;
    color: #fff;
    font-size: 11px;
    cursor: pointer;
    transition: background .15s, border-color .15s;
}
.attach-item__check:hover { border-color: #6366f1; }
.attach-item.is-selected .attach-item__check {
    background: #4f46e5;
    border-color: #4f46e5;
}
.attach-item__actions {
    position: absolute;
    top: 8px; right: 8px;
    display: flex; gap: 4px;
    opacity: 0;
    z-index: 2;
    transition: opacity .15s;
}
.attach-item:hover .attach-item__actions { opacity: 1; }
.attach-action-btn {
    width: 26px; height: 26px;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px;
    background: rgba(255,255,255,.95);
    box-shadow: 0 1px 3px rgba(0,0,0,.15);
    transition: transform .15s, color .15s;
}
.attach-action-btn:hover { transform: scale(1.1); }
.attach-action-btn--copy { color: #059669; }
.attach-action-btn--copy:hover { color: #047857; }
.attach-action-btn--del  { color: #dc2626; }
.attach-action-btn--del:hover { color: #b91c1c; }
.attach-item__overlay {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    background: linear-gradient(transparent, rgba(0,0,0,.72));
    padding: 20px 10px 8px;
    opacity: 0;
    transition: opacity .15s;
    pointer-events: none;
}
.attach-item:hover .attach-item__overlay { opacity: 1; }
.attach-item__name {
    font-size: 11px;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-align: center;
}

/* ===== 空状态 ===== */
.attach-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 0;
    color: #9ca3af;
}
.attach-empty i { font-size: 48px; display: block; margin-bottom: 12px; color: #d1d5db; }
.attach-empty__text { font-size: 14px; }

/* ===== 底部分页 ===== */
.attach-bottom {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 16px;
}
</style>

<div class="admin-page admin-page-attachment">
    <h1 class="admin-page__title">资源管理</h1>

    <!-- 统计卡 -->
    <div class="attach-stats">
        <div class="attach-stat-card">
            <div class="attach-stat-card__icon attach-stat-card__icon--blue"><i class="fa fa-file"></i></div>
            <div>
                <div class="attach-stat-card__num" id="statTotal"><?= (int) $stats['total']; ?></div>
                <div class="attach-stat-card__label">附件总数</div>
            </div>
        </div>
        <div class="attach-stat-card">
            <div class="attach-stat-card__icon attach-stat-card__icon--green"><i class="fa fa-image"></i></div>
            <div>
                <div class="attach-stat-card__num" id="statImages"><?= (int) $stats['image_count']; ?></div>
                <div class="attach-stat-card__label">图片数量</div>
            </div>
        </div>
        <div class="attach-stat-card">
            <div class="attach-stat-card__icon attach-stat-card__icon--amber"><i class="fa fa-database"></i></div>
            <div>
                <div class="attach-stat-card__num" id="statSize"><?= htmlspecialchars($stats['total_size_fmt']); ?></div>
                <div class="attach-stat-card__label">占用空间</div>
            </div>
        </div>
    </div>

    <!-- 工具栏 -->
    <div class="attach-toolbar">
        <a class="em-btn em-reset-btn" id="attachRefreshBtn"><i class="fa fa-refresh"></i>刷新</a>
        <a class="em-btn em-save-btn" id="attachSelectAll"><i class="fa fa-check-square-o"></i>全选/反选</a>
        <a class="em-btn em-red-btn em-disabled-btn" id="attachBatchDel"><i class="fa fa-trash"></i>批量删除</a>
        <div class="attach-toolbar__spacer"></div>
        <div class="attach-toolbar__selected">已选择 <strong id="selectedCount">0</strong> 项</div>
    </div>

    <!-- 网格 -->
    <div class="attach-grid-card">
        <div class="attach-grid" id="attachGrid">
            <div class="attach-empty">
                <i class="fa fa-image"></i>
                <div class="attach-empty__text">加载中...</div>
            </div>
        </div>
    </div>

    <!-- 分页 -->
    <div class="attach-bottom">
        <div id="attachPage"></div>
    </div>
</div>

<script>
$(function () {
    // PJAX 防重复绑定：清掉本页历史 .admAttach handler，避免事件成倍触发
    $(document).off('.admAttach');
    $(window).off('.admAttach');

    'use strict';

    var csrfToken = <?php echo json_encode($csrfToken); ?>;
    var currentPage = 1;
    var perPage = 21;
    var selectedIds = {};
    var totalItems = 0;
    // 当前页所有图片的 URL 数组（Viewer.js 多图预览用）
    var currentImages = [];

    function updateCsrf(token) {
        if (token) csrfToken = token;
    }
    window.updateCsrf = updateCsrf;

    function escHtml(str) {
        if (str == null) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    layui.use(['layer', 'laypage'], function () {
        var layer = layui.layer;
        var laypage = layui.laypage;

        // ====== 渲染网格 ======
        function renderGrid(items) {
            if (!items || items.length === 0) {
                return '<div class="attach-empty"><i class="fa fa-image"></i><div class="attach-empty__text">暂无附件</div></div>';
            }

            var html = '';
            currentImages = [];
            items.forEach(function (item, idx) {
                var isImage = item.mime_type && item.mime_type.indexOf('image/') === 0;
                var imgIndex = '';
                if (isImage) {
                    imgIndex = ' data-img-index="' + currentImages.length + '"';
                    currentImages.push({ src: item.file_url, alt: item.file_name });
                }

                var thumbHtml = isImage
                    ? '<img class="attach-item__thumb" src="' + item.file_url + '" alt="' + escHtml(item.file_name) + '"' + imgIndex + '>'
                    : '<div class="attach-item__file">'
                        + '<i class="fa fa-file-o"></i>'
                        + '<span class="attach-item__file-ext">' + escHtml((item.file_ext || '').toUpperCase()) + '</span>'
                      + '</div>';

                var selectedCls = selectedIds[item.id] ? ' is-selected' : '';
                html += '<div class="attach-item' + selectedCls + '" '
                     +    'data-id="' + item.id + '" '
                     +    'data-url="' + escHtml(item.file_url) + '" '
                     +    'data-name="' + escHtml(item.file_name) + '" '
                     +    'data-is-image="' + (isImage ? '1' : '0') + '">'
                     +      '<div class="attach-item__check" title="选中"><i class="fa fa-check"></i></div>'
                     +      thumbHtml
                     +      '<div class="attach-item__actions">'
                     +        '<button class="attach-action-btn attach-action-btn--copy" title="复制链接"><i class="fa fa-link"></i></button>'
                     +        '<button class="attach-action-btn attach-action-btn--del" title="删除"><i class="fa fa-trash"></i></button>'
                     +      '</div>'
                     +      '<div class="attach-item__overlay"><div class="attach-item__name">' + escHtml(item.file_name) + '</div></div>'
                     +    '</div>';
            });
            return html;
        }

        // ====== 加载数据 ======
        function loadData(page) {
            currentPage = page || 1;

            $.ajax({
                url: '/admin/attachment.php',
                type: 'POST',
                dataType: 'json',
                data: { _action: 'list', page: currentPage, limit: perPage },
                success: function (res) {
                    if (res.code !== 200) {
                        layer.msg(res.msg || '加载失败');
                        return;
                    }
                    csrfToken = res.data && res.data.csrf_token ? res.data.csrf_token : csrfToken;
                    totalItems = res.data.total || 0;

                    $('#attachGrid').html(renderGrid(res.data.data));

                    // 统计刷新
                    if (res.data.stats) {
                        $('#statTotal').text(res.data.stats.total);
                        $('#statImages').text(res.data.image_count || res.data.stats.total);
                        $('#statSize').text(res.data.stats.total_size_fmt || '0 B');
                    }

                    // 分页
                    laypage.render({
                        elem: 'attachPage',
                        count: totalItems,
                        limit: perPage,
                        curr: currentPage,
                        layout: ['prev', 'page', 'next', 'skip'],
                        jump: function (obj, first) { if (!first) loadData(obj.curr); }
                    });
                },
                error: function () { layer.msg('网络异常'); }
            });
        }

        // ====== Viewer.js 多图预览 ======
        // 点击缩略图/预览按钮 → 打开当前页面所有图片，起始索引 = 被点击图片的索引
        function openViewer(startIndex) {
            if (!currentImages.length) return;

            var $container = $('<div style="display:none;"></div>');
            currentImages.forEach(function (img) {
                $container.append(
                    $('<img>').attr('src', img.src).attr('alt', img.alt || '')
                );
            });
            $('body').append($container);

            var viewer = new Viewer($container[0], {
                navbar: true,       // 底部缩略图条
                title: true,        // 显示文件名（alt）
                toolbar: true,      // 放大/旋转等工具
                initialViewIndex: startIndex,
                hidden: function () { viewer.destroy(); $container.remove(); }
            });
            viewer.show();
        }

        // 点击图片缩略图
        $(document).on('click.admAttach', '.attach-item__thumb', function (e) {
            e.stopPropagation();
            var idx = parseInt($(this).attr('data-img-index') || '-1', 10);
            if (idx >= 0) openViewer(idx);
        });

        // 点击"选中"圆圈 → toggle 选中态（不触发预览）
        $(document).on('click.admAttach', '.attach-item__check', function (e) {
            e.stopPropagation();
            var $item = $(this).closest('.attach-item');
            var id = $item.data('id');
            $item.toggleClass('is-selected');
            if ($item.hasClass('is-selected')) {
                selectedIds[id] = true;
            } else {
                delete selectedIds[id];
            }
            updateSelectedCount();
        });

        // 复制链接
        $(document).on('click.admAttach', '.attach-action-btn--copy', function (e) {
            e.stopPropagation();
            var url = $(this).closest('.attach-item').data('url');
            var fullUrl = window.location.protocol + '//' + window.location.host + url;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(fullUrl).then(function () {
                    layer.msg('链接已复制');
                }).catch(function () { fallbackCopy(fullUrl); });
            } else {
                fallbackCopy(fullUrl);
            }
        });

        function fallbackCopy(text) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;left:-9999px;';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); layer.msg('链接已复制'); }
            catch (err) { layer.msg('复制失败，请手动复制', { icon: 2 }); }
            document.body.removeChild(ta);
        }

        // 删除单个
        $(document).on('click.admAttach', '.attach-action-btn--del', function (e) {
            e.stopPropagation();
            var $item = $(this).closest('.attach-item');
            var id = $item.data('id');
            var name = $item.data('name');
            layer.confirm('确定要删除附件「' + escHtml(name) + '」吗？删除后不可恢复。', function (idx) {
                $.ajax({
                    url: '/admin/attachment.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { csrf_token: csrfToken, _action: 'delete', id: id },
                    success: function (res) {
                        if (res.code === 200) {
                            csrfToken = res.data && res.data.csrf_token ? res.data.csrf_token : csrfToken;
                            delete selectedIds[id];
                            updateSelectedCount();
                            loadData(currentPage);
                            layer.msg(res.msg || '删除成功');
                        } else {
                            layer.msg(res.msg || '删除失败');
                        }
                    },
                    error: function () { layer.msg('网络异常'); },
                    complete: function () { layer.close(idx); }
                });
            });
        });

        // 选中计数 + 批量按钮启用态
        function updateSelectedCount() {
            var count = Object.keys(selectedIds).length;
            $('#selectedCount').text(count);
            $('#attachBatchDel').toggleClass('em-disabled-btn', count === 0);
        }

        // 全选 / 反选
        $('#attachRefreshBtn').on('click', function () { loadData(currentPage); });

        $('#attachSelectAll').on('click', function () {
            var $items = $('.attach-item');
            if (!$items.length) return;
            var allSelected = $items.filter('.is-selected').length === $items.length;
            if (allSelected) {
                $items.removeClass('is-selected');
                selectedIds = {};
            } else {
                $items.addClass('is-selected');
                $items.each(function () { selectedIds[$(this).data('id')] = true; });
            }
            updateSelectedCount();
        });

        // 批量删除
        $('#attachBatchDel').on('click', function () {
            if ($(this).hasClass('em-disabled-btn')) return;
            var ids = Object.keys(selectedIds);
            if (!ids.length) { layer.msg('请先选择要删除的附件'); return; }
            layer.confirm('确定要删除选中的 <strong>' + ids.length + '</strong> 个附件吗？删除后不可恢复。', function (idx) {
                var loadIdx = layer.load(1);
                $.ajax({
                    url: '/admin/attachment.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { csrf_token: csrfToken, _action: 'batchDelete', ids: ids.join(',') },
                    success: function (res) {
                        if (res.code === 200) {
                            csrfToken = res.data && res.data.csrf_token ? res.data.csrf_token : csrfToken;
                            selectedIds = {};
                            updateSelectedCount();
                            loadData(currentPage);
                            layer.msg(res.msg || '删除成功');
                        } else {
                            layer.msg(res.msg || '删除失败');
                        }
                    },
                    error: function () { layer.msg('网络异常'); },
                    complete: function () {
                        layer.close(loadIdx);
                        layer.close(idx);
                    }
                });
            });
        });

        // 初始加载
        loadData(1);
    });
});
</script>
