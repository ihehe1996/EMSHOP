<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>选择图片</title>
    <link rel="stylesheet" href="/content/static/lib/layui-v2.13.5/layui/css/layui.css">
    <link rel="stylesheet" href="/admin/static/css/admin.css">
    <style>
        html, body { height: 100%; overflow: hidden; }
        body { background: #fff; padding: 16px; display: flex; flex-direction: column; }
        .media-toolbar { flex-shrink: 0; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .media-grid-wrap { flex: 1; overflow-y: auto; }
        .media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px; }
        .media-item { position: relative; aspect-ratio: 1; border: 2px solid transparent; border-radius: 4px; overflow: hidden; cursor: pointer; background: #f5f5f5; transition: border-color .15s; }
        .media-item:hover { border-color: #1aa094; }
        .media-item.selected { border-color: #1aa094; }
        .media-item img { width: 100%; height: 100%; object-fit: cover; }
        .media-item .media-item__check { position: absolute; top: 4px; right: 4px; width: 20px; height: 20px; background: #1aa094; border-radius: 50%; display: none; align-items: center; justify-content: center; color: #fff; font-size: 12px; }
        .media-item.selected .media-item__check { display: flex; }
        .media-empty { text-align: center; color: #999; padding: 40px 0; grid-column: 1 / -1; }
        .media-page { flex-shrink: 0; text-align: center; margin-top: 12px; }
    </style>
</head>
<body>

<div class="media-toolbar">
    <select class="layui-select" id="mediaContextSelect" style="width:130px;">
        <option value="all">全部</option>
        <option value="avatar">头像</option>
        <option value="article">文章</option>
        <option value="product">商品</option>
        <option value="default">其他</option>
    </select>
    <span style="color:#999;font-size:12px;">点击图片选中，确认后点击"确定"</span>
</div>

<div class="media-grid-wrap">
<div class="media-grid" id="mediaGrid">
    <div class="media-empty">暂无上传记录</div>
</div>
</div>

<div class="media-page" id="mediaPage"></div>

<script src="/content/static/lib/jquery.min.3.5.1.js"></script>
<script src="/content/static/lib/layui-v2.13.5/layui/layui.js"></script>
<script>
(function () {
    layui.use(['layer'], function () {
        var layer = layui.layer;
        var currentPage = 1;
        var selectedUrl = null;

        function loadMedia(page) {
            var context = $('#mediaContextSelect').val();
            $.ajax({
                url: '/admin/media.php',
                type: 'POST',
                data: { action: 'list', page: page, limit: 24, context: context },
                dataType: 'json',
                success: function (res) {
                    if (res.code !== 200 || !res.data.data || res.data.data.length === 0) {
                        $('#mediaGrid').html('<div class="media-empty">暂无上传记录</div>');
                        $('#mediaPage').empty();
                        return;
                    }

                    var html = '';
                    res.data.data.forEach(function (item) {
                        html += '<div class="media-item" data-url="' + item.file_url + '">'
                            + '<img src="' + item.file_url + '" alt="">'
                            + '<div class="media-item__check"><i class="layui-icon layui-icon-ok"></i></div>'
                            + '</div>';
                    });
                    $('#mediaGrid').html(html);

                    // 分页
                    var total = res.data.total;
                    var limit = res.data.limit;
                    var pages = Math.ceil(total / limit);
                    var pageHtml = '';
                    if (pages > 1) {
                        pageHtml += '<button class="layui-btn layui-btn-sm" id="mediaPagePrev"' + (page <= 1 ? ' disabled' : '') + '>上一页</button>';
                        pageHtml += '<span style="padding:0 12px;">第 ' + page + ' / ' + pages + ' 页，共 ' + total + ' 张</span>';
                        pageHtml += '<button class="layui-btn layui-btn-sm" id="mediaPageNext"' + (page >= pages ? ' disabled' : '') + '>下一页</button>';
                    }
                    $('#mediaPage').html(pageHtml);

                    $('#mediaPagePrev').off('click').on('click', function () { if (page > 1) loadMedia(page - 1); });
                    $('#mediaPageNext').off('click').on('click', function () { if (page < pages) loadMedia(page + 1); });
                },
                error: function () {
                    layer.msg('加载失败');
                }
            });
        }

        // 选中（单选，点击即确认）
        $(document).on('click', '.media-item', function () {
            $('.media-item').removeClass('selected');
            $(this).addClass('selected');
            selectedUrl = $(this).data('url');
            // 自动触发父窗口确认（关闭弹窗 + 裁剪流程）
            if (window.parent && window.parent.selectMediaAndCrop) {
                window.parent.selectMediaAndCrop(selectedUrl);
            }
        });

        // 确认
        window.selectMedia = function () {
            if (!selectedUrl) {
                layer.msg('请先选择一张图片');
                return;
            }
            return selectedUrl;
        };

        $('#mediaContextSelect').on('change', function () {
            loadMedia(1);
        });

        loadMedia(1);
    });
})();
</script>
</body>
</html>
