<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$pageTitle = '添加分类';
$csrfToken = Csrf::token();
$isEdit = isset($cat) && $cat !== null;
if ($isEdit) {
    $pageTitle = '编辑分类';
}
include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <form class="layui-form" id="catForm" lay-filter="catForm">
        <input type="hidden" name="_action" value="<?php echo $isEdit ? 'update' : 'create'; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">
        <input type="hidden" name="id" value="<?php echo $isEdit ? (int) $cat['id'] : ''; ?>">
        <input type="hidden" name="type" value="<?php echo $esc($currentType); ?>">
        <input type="hidden" name="status" value="<?php echo $isEdit ? (int) $cat['status'] : 1; ?>">

        <div class="layui-form-item">
            <label class="layui-form-label">上级分类</label>
            <div class="layui-input-block">
                <select name="parent_id" id="catParentId" lay-append-to="body" lay-search="">
                    <?php
                    $preSelected = $defaultParentId;
                    echo '<option value="0"' . ($preSelected === 0 ? ' selected' : '') . '>顶级分类</option>';
                    echo $parentOptions;
                    ?>
                </select>
            </div>
            <div class="layui-form-mid layui-word-aux">选择作为哪个分类的子分类</div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">分类名称</label>
            <div class="layui-input-block">
                <input type="text" class="layui-input" id="catName" name="name" maxlength="100" placeholder="请输入分类名称" value="<?php echo $isEdit ? $esc($cat['name']) : ''; ?>">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">别名</label>
            <div class="layui-input-block">
                <input type="text" class="layui-input" name="slug" maxlength="100" placeholder="URL标识，可留空" value="<?php echo $isEdit ? $esc($cat['slug']) : ''; ?>">
            </div>
            <div class="layui-form-mid layui-word-aux">用于URL中，低版本兼容</div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">描述</label>
            <div class="layui-input-block">
                <textarea class="layui-textarea" name="description" rows="2" maxlength="500" placeholder="分类简短描述"><?php echo $isEdit ? $esc($cat['description']) : ''; ?></textarea>
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">排序</label>
            <div class="layui-input-inline" style="width:120px;">
                <input type="number" class="layui-input" name="sort" value="<?php echo $isEdit ? (int) $cat['sort'] : 100; ?>" min="0" max="9999">
            </div>
            <div class="layui-form-mid layui-word-aux">数值越小排序越靠前</div>
        </div>

        <?php if ($currentType === 'nav'): ?>
        <div class="layui-form-item">
            <label class="layui-form-label">链接地址</label>
            <div class="layui-input-block">
                <input type="text" class="layui-input" name="link" maxlength="500" placeholder="如：/article/1 或 https://example.com" value="<?php echo $isEdit ? $esc($cat['link']) : ''; ?>">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">打开方式</label>
            <div class="layui-input-block">
                <select name="target" lay-append-to="body">
                    <option value="_self"<?php echo $isEdit && $cat['target'] === '_self' ? ' selected' : ''; ?>>当前窗口</option>
                    <option value="_blank"<?php echo $isEdit && $cat['target'] === '_blank' ? ' selected' : ''; ?>>新窗口</option>
                </select>
            </div>
        </div>
        <?php endif; ?>

        <div class="layui-form-item">
            <label class="layui-form-label">分类图片</label>
            <div class="layui-input-block">
                <div class="cat-img-field">
                    <img src="<?php echo $isEdit && !empty($cat['cover_image']) ? $esc($cat['cover_image']) : ''; ?>"
                         alt="" id="catImgPreview"
                         style="<?php echo ($isEdit && !empty($cat['cover_image'])) ? '' : 'opacity:0; position:absolute; left:-9999px;'; ?>">
                    <div class="cat-img-inner">
                        <input type="text" class="layui-input cat-img-url" id="catImgUrl" name="cover_image" maxlength="500"
                               placeholder="图片地址，可手动填写或上传"
                               value="<?php echo $isEdit ? $esc($cat['cover_image']) : ''; ?>">
                        <div class="cat-img-btns">
                            <button type="button" class="layui-btn layui-btn-xs" id="catImgUploadBtn"><i class="layui-icon layui-icon-upload"></i></button>
                            <button type="button" class="layui-btn layui-btn-xs layui-btn-normal" id="catImgPickBtn"><i class="layui-icon layui-icon-picture"></i></button>
                            <button type="button" class="layui-btn layui-btn-xs layui-btn-danger" id="catImgClearBtn"><i class="layui-icon layui-icon-close"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">SEO 标题</label>
            <div class="layui-input-block">
                <input type="text" class="layui-input" name="seo_title" maxlength="200" value="<?php echo $isEdit ? $esc($cat['seo_title']) : ''; ?>">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">SEO 关键词</label>
            <div class="layui-input-block">
                <input type="text" class="layui-input" name="seo_keywords" maxlength="500" placeholder="多个关键词用英文逗号分隔" value="<?php echo $isEdit ? $esc($cat['seo_keywords']) : ''; ?>">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">SEO 描述</label>
            <div class="layui-input-block">
                <textarea class="layui-textarea" name="seo_description" rows="2" maxlength="500"><?php echo $isEdit ? $esc($cat['seo_description']) : ''; ?></textarea>
            </div>
        </div>
    </form>
</div>

<div class="popup-footer">
    <button type="button" class="popup-btn" id="catCancelBtn">取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="catSubmitBtn">确认保存</button>
</div>

<style>
@keyframes avatarSpin {
    to { transform: rotate(360deg); }
}
</style>

<script>
$(function () {
    layui.use(['layer', 'form', 'upload'], function () {
        var layer = layui.layer;
        var form = layui.form;
        var upload = layui.upload;

        form.render('select');

        // ---- 分类图片预览 ----
        var currentImgUrl = $('#catImgUrl').val() || '';

        function updateImgPreview(url) {
            var $preview = $('#catImgPreview');
            var $url = $('#catImgUrl');
            if (url) {
                $preview.attr('src', url).css('opacity', 1).css('position', '').css('left', '');
                $url.val(url);
            } else {
                $preview.attr('src', '').css('opacity', 0).css('position', 'absolute').css('left', '-9999px');
                $url.val('');
            }
        }

        // 输入框内容变化同步预览
        $('#catImgUrl').on('input', function () {
            var url = $(this).val();
            var $preview = $('#catImgPreview');
            if (url) {
                $preview.attr('src', url).css('opacity', 1).css('position', '').css('left', '');
            } else {
                $preview.css('opacity', 0).css('position', 'absolute').css('left', '-9999px');
            }
        });

        // 清除
        $('#catImgClearBtn').on('click', function () {
            updateImgPreview('');
        });

        // ---- 裁剪函数 ----
        var cropperInstance = null;
        function openCropper(imgSrc, isFile) {
            var $cropperWrap = $('<div id="cropperWrap"></div>');
            $cropperWrap.html('<div style="padding:16px;">'
                + '<div id="cropperContainer" style="height:350px;overflow:hidden;line-height:0;">'
                + '<img id="cropperImg" style="max-width:100%;display:block;">'
                + '</div>'
                + '<div style="margin-top:12px;text-align:center;">'
                + '<p style="margin-bottom:10px;color:#999;font-size:12px;">拖动裁剪框调整图片范围，确认后点击"保存"</p>'
                + '</div>'
                + '</div>');
            $('body').append($cropperWrap);

            var $cropperImg = $('#cropperImg');
            $cropperImg.attr('src', imgSrc);

            var cropLayerIndex = layer.open({
                type: 1,
                title: '裁剪分类图片',
                skin: 'admin-modal',
                maxmin: true,
                area: [window.innerWidth >= 800 ? '500px' : '95%', window.innerHeight >= 700 ? '580px' : '80%'],
                shadeClose: false,
                content: $cropperWrap,
                btn: ['保存', '使用原图', '取消'],
                success: function () {
                    var img = $cropperImg[0];
                    var natW = img.naturalWidth || 400;
                    var natH = img.naturalHeight || 400;
                    var ratio = natW / natH;
                    var dispH = ratio >= 1 ? Math.round(350 / ratio) : 350;
                    $('#cropperContainer').css('height', dispH + 'px');

                    cropperInstance = new Cropper(img, {
                        aspectRatio: 16 / 9,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 0.8,
                        guides: true,
                        center: true,
                        highlight: false,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                        toggleDragModeOnDblclick: false,
                    });
                },
                yes: function (index) {
                    var canvas = cropperInstance.getCroppedCanvas({
                        width: 640,
                        height: 360,
                        imageSmoothingEnabled: true,
                        imageSmoothingQuality: 'high',
                    });
                    canvas.toBlob(function (blob) {
                        var formData = new FormData();
                        formData.append('file', blob, 'category.jpg');
                        formData.append('csrf_token', $('input[name="csrf_token"]').val());

                        $.ajax({
                            url: '/admin/upload.php',
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            dataType: 'json',
                            success: function (res) {
                                if (res.code === 0 || res.code === 200) {
                                    updateImgPreview(res.data.url);
                                    $('input[name="csrf_token"]').val(res.data.csrf_token);
                                    layer.msg(res.msg || '上传成功');
                                } else {
                                    layer.msg(res.msg || '上传失败');
                                }
                            },
                            error: function () {
                                layer.msg('上传失败，请重试');
                            }
                        });
                        layer.close(index);
                    }, 'image/jpeg', 0.9);
                },
                btn2: function (index) {
                    // 使用原图上传
                    var tmpImg = new Image();
                    tmpImg.crossOrigin = 'Anonymous';
                    tmpImg.src = imgSrc;
                    tmpImg.onload = function () {
                        var origCanvas = document.createElement('canvas');
                        origCanvas.width = tmpImg.naturalWidth;
                        origCanvas.height = tmpImg.naturalHeight;
                        origCanvas.getContext('2d').drawImage(tmpImg, 0, 0);
                        origCanvas.toBlob(function (blob) {
                            var formData = new FormData();
                            formData.append('file', blob, 'category_orig.jpg');
                            formData.append('csrf_token', $('input[name="csrf_token"]').val());

                            $.ajax({
                                url: '/admin/upload.php',
                                type: 'POST',
                                data: formData,
                                processData: false,
                                contentType: false,
                                dataType: 'json',
                                success: function (res) {
                                    if (res.code === 0 || res.code === 200) {
                                        updateImgPreview(res.data.url);
                                        $('input[name="csrf_token"]').val(res.data.csrf_token);
                                        layer.msg('上传成功');
                                    } else {
                                        layer.msg(res.msg || '上传失败');
                                    }
                                },
                                error: function () {
                                    layer.msg('上传失败，请重试');
                                }
                            });
                            layer.close(index);
                        }, 'image/jpeg', 0.9);
                    };
                    if (!isFile) {
                        // 如果是URL，用xhr加载避免跨域
                        var xhr = new XMLHttpRequest();
                        xhr.open('GET', imgSrc, true);
                        xhr.responseType = 'blob';
                        xhr.onload = function () {
                            if (xhr.status === 200) {
                                var blob = xhr.response;
                                var formData = new FormData();
                                formData.append('file', blob, 'category_orig.jpg');
                                formData.append('action', 'image');
                                formData.append('csrf_token', $('input[name="csrf_token"]').val());
                                $.ajax({
                                    url: '/admin/upload.php',
                                    type: 'POST',
                                    data: formData,
                                    processData: false,
                                    contentType: false,
                                    dataType: 'json',
                                    success: function (res) {
                                        if (res.code === 0 || res.code === 200) {
                                            updateImgPreview(res.data.url);
                                            $('input[name="csrf_token"]').val(res.data.csrf_token);
                                            layer.msg('上传成功');
                                        } else {
                                            layer.msg(res.msg || '上传失败');
                                        }
                                    },
                                    error: function () { layer.msg('上传失败，请重试'); }
                                });
                                layer.close(index);
                            }
                        };
                        xhr.send();
                        return false;
                    }
                },
                end: function () {
                    if (cropperInstance) {
                        cropperInstance.destroy();
                        cropperInstance = null;
                    }
                    $cropperWrap.remove();
                }
            });
        }

        // 选择图片（媒体库）后自动裁剪
        var pickLayerIndex = null;
        $('#catImgPickBtn').on('click', function () {
            var csrfToken = $('input[name="csrf_token"]').val();
            pickLayerIndex = layer.open({
                type: 2,
                title: '选择图片',
                skin: 'admin-modal',
                maxmin: true,
                area: [window.innerWidth >= 800 ? '700px' : '95%', window.innerHeight >= 500 ? '500px' : '80%'],
                shadeClose: false,
                content: '/admin/media.php?_csrf=' + encodeURIComponent(csrfToken),
                btn: ['确定', '取消'],
                yes: function (index, layero) {
                    var win = layero.find('iframe')[0].contentWindow;
                    var url = win.selectMedia();
                    if (!url) {
                        layer.msg('请先选择一张图片');
                        return;
                    }
                    layer.close(index);
                    pickLayerIndex = null;
                    openCropper(url, false);
                }
            });
        });

        // 上传按钮：先裁剪再上传
        var $fileInput = $('<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">');
        $('body').append($fileInput);

        $fileInput.on('change', function () {
            var file = this.files[0];
            if (file) {
                if (!file.type.match(/image\/(jpeg|png|gif|webp)/i)) {
                    layer.msg('仅支持 JPG、PNG、GIF、WebP 格式');
                    $(this).val('');
                    return;
                }
                if (file.size > 10 * 1024 * 1024) {
                    layer.msg('图片大小不能超过 10MB');
                    $(this).val('');
                    return;
                }
                var reader = new FileReader();
                reader.onload = function (e) {
                    openCropper(e.target.result, true);
                };
                reader.readAsDataURL(file);
                $(this).val('');
            }
        });

        $('#catImgUploadBtn').on('click', function () {
            $fileInput.trigger('click');
        });

        // ---- 取消按钮 ----
        $('#catCancelBtn').on('click', function () {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        // ---- 确认保存 ----
        $("#catSubmitBtn").on("click", function () {
            var $form = $("#catForm");
            var $btn = $("#catSubmitBtn");
            $btn.prop("disabled", true).text("保存中...");

            $.ajax({
                url: location.pathname + "?type=<?php echo $esc($currentType); ?>&_popup=1",
                type: "POST",
                data: $form.serialize(),
                dataType: "json",
                success: function (res) {
                    if (res.code === 0) {
                        var msg = res.msg || "保存成功";
                        if (res.data && res.data.csrf_token) {
                            try { parent.updateCsrf(res.data.csrf_token); } catch(e) {}
                        }
                        var index = parent.layer.getFrameIndex(window.name);
                        parent.layer.msg(msg);
                        parent.layer.close(index);
                    } else {
                        layer.msg(res.msg || "操作失败");
                    }
                },
                error: function () {
                    layer.msg("网络错误，请重试");
                },
                complete: function () {
                    $btn.prop("disabled", false).text("确认保存");
                }
            });
        });
    });
});
</script>
<?php
include __DIR__ . '/footer.php';
