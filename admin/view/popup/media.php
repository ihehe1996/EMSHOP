<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

$pageTitle = '选择图片';
include __DIR__ . '/header.php';
?>

<style>
/* 媒体选择：内容区不滚动，由网格区域自行滚动，底部按钮固定 */
.popup-inner.media-picker-inner {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    padding-bottom: 16px;
}
.media-toolbar {
    flex-shrink: 0;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.media-grid-wrap {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}
.media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 12px;
}
.media-item {
    position: relative;
    aspect-ratio: 1;
    border: 2px solid transparent;
    border-radius: 4px;
    overflow: hidden;
    cursor: pointer;
    background: #f5f5f5;
    transition: border-color .15s;
}
.media-item:hover { border-color: #1aa094; }
.media-item.selected { border-color: #1aa094; }
.media-item img { width: 100%; height: 100%; object-fit: cover; }
.media-item .media-item__check {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 20px;
    height: 20px;
    background: #1aa094;
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 12px;
}
.media-item.selected .media-item__check { display: flex; }
.media-empty {
    text-align: center;
    color: #999;
    padding: 40px 0;
    grid-column: 1 / -1;
}
.media-page {
    flex-shrink: 0;
    text-align: center;
    margin-top: 12px;
}
</style>

<div class="popup-inner media-picker-inner">
    <div class="media-toolbar">
        <select class="layui-select" id="mediaContextSelect" style="width:130px;">
            <option value="all">全部</option>
            <option value="avatar">头像</option>
            <option value="article">文章</option>
            <option value="product">商品</option>
            <option value="default">其他</option>
        </select>
        <span style="color:#999;font-size:12px;">点击图片选中，再点右下角「确定」</span>
    </div>

    <div class="media-grid-wrap">
        <div class="media-grid" id="mediaGrid">
            <div class="media-empty">暂无上传记录</div>
        </div>
    </div>

    <div class="media-page" id="mediaPage"></div>
</div>

<div class="popup-footer">
    <button type="button" class="em-btn em-reset-btn" id="mediaCancelBtn"><i class="fa fa-times"></i>取消</button>
    <button type="button" class="em-btn em-save-btn" id="mediaOkBtn"><i class="fa fa-check"></i>确定</button>
</div>

<script>
(function () {
    layui.use(['layer'], function () {
        var topLayer = (window.parent && window.parent.layer) ? window.parent.layer : layui.layer;
        var selectedUrl = null;
        var listUrl = (window.TEMPLATE_MEDIA_URL || '/admin/media.php').split('?')[0];

        function loadMedia(page) {
            var context = $('#mediaContextSelect').val();
            $.ajax({
                url: listUrl,
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

                    var total = res.data.total;
                    var limit = res.data.limit;
                    var pages = Math.ceil(total / limit);
                    var pageHtml = '';
                    if (pages > 1) {
                        pageHtml += '<button type="button" class="layui-btn layui-btn-sm" id="mediaPagePrev"' + (page <= 1 ? ' disabled' : '') + '>上一页</button>';
                        pageHtml += '<span style="padding:0 12px;">第 ' + page + ' / ' + pages + ' 页，共 ' + total + ' 张</span>';
                        pageHtml += '<button type="button" class="layui-btn layui-btn-sm" id="mediaPageNext"' + (page >= pages ? ' disabled' : '') + '>下一页</button>';
                    }
                    $('#mediaPage').html(pageHtml);

                    $('#mediaPagePrev').off('click').on('click', function () { if (page > 1) loadMedia(page - 1); });
                    $('#mediaPageNext').off('click').on('click', function () { if (page < pages) loadMedia(page + 1); });
                },
                error: function () {
                    topLayer.msg('加载失败');
                }
            });
        }

        $(document).on('click', '.media-item', function () {
            $('.media-item').removeClass('selected');
            $(this).addClass('selected');
            selectedUrl = $(this).data('url');
        });

        // 兼容旧调用方：仍可通过 iframe.contentWindow.selectMedia() 取值
        window.selectMedia = function () {
            return selectedUrl || '';
        };

        function closeSelf() {
            if (window.parent && window.parent.layer) {
                var idx = window.parent.layer.getFrameIndex(window.name);
                window.parent.layer.close(idx);
            }
        }

        $('#mediaCancelBtn').on('click', function () {
            if (window.parent) {
                window.parent.__emMediaPickCallback = null;
            }
            closeSelf();
        });

        $('#mediaOkBtn').on('click', function () {
            if (!selectedUrl) {
                topLayer.msg('请先选择一张图片');
                return;
            }
            var parentWin = window.parent || window;
            var cb = parentWin.__emMediaPickCallback;
            if (typeof cb === 'function') {
                parentWin.__emMediaPickCallback = null;
                cb(selectedUrl);
            } else if (typeof parentWin.selectMediaAndCrop === 'function') {
                parentWin.selectMediaAndCrop(selectedUrl);
            }
            closeSelf();
        });

        $('#mediaContextSelect').on('change', function () {
            selectedUrl = null;
            loadMedia(1);
        });

        loadMedia(1);
    });
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>
