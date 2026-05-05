<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
$isEdit = isset($editLink) && $editLink !== null;

$placeholderImg = EM_CONFIG['placeholder_img'];
$esc = function (string $str) use (&$esc): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
};

// 格式化过期时间
$expireTimeValue = '';
if ($isEdit && !empty($editLink['expire_time']) && $editLink['expire_time'] !== '0000-00-00 00:00:00') {
    $ts = strtotime($editLink['expire_time']);
    if ($ts !== false) {
        $expireTimeValue = date('Y-m-d H:i:s', $ts);
    }
}

include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <form class="layui-form" id="linkForm" lay-filter="linkForm">
        <input type="hidden" name="_action" value="<?php echo $isEdit ? 'update' : 'create'; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">
        <input type="hidden" name="id" value="<?php echo $isEdit ? $esc((string) $editLink['id']) : ''; ?>">

        <!-- 基本信息 -->
        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">链接名称</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" id="linkName" name="name" maxlength="100" placeholder="如：腾讯云、阿里云"
                           value="<?php echo $isEdit ? $esc($editLink['name']) : ''; ?>">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">链接地址</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" id="linkUrl" name="url" maxlength="500" placeholder="https://cloud.tencent.com"
                           value="<?php echo $isEdit ? $esc($editLink['url']) : ''; ?>">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">排序</label>
                <div class="layui-input-block">
                    <input type="number" class="layui-input" name="sort" step="1" min="0" placeholder="数值越大越靠前"
                           value="<?php echo $isEdit ? (int) $editLink['sort'] : '0'; ?>">
                </div>
                <div class="layui-form-mid layui-word-aux">数值越大排序越靠前</div>
            </div>
        </div>

        <!-- 图片设置 -->
        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">链接图片</label>
                <div class="layui-input-block">
                    <div class="admin-img-field" id="imageField" data-placeholder="<?php echo $esc($placeholderImg); ?>">
                        <img src="<?php echo $isEdit && !empty($editLink['image']) ? $esc($editLink['image']) : $esc($placeholderImg); ?>"
                             alt="" id="imagePreview">
                        <input type="text" class="layui-input admin-img-url" id="imageUrl" name="image" maxlength="500"
                               placeholder="Logo 图片 URL，可上传或选择"
                               value="<?php echo $isEdit ? $esc($editLink['image']) : ''; ?>">
                        <div class="admin-img-btns">
                            <button type="button" class="layui-btn layui-btn-xs" id="imageUploadBtn" title="上传"><i class="fa fa-upload"></i></button>
                            <button type="button" class="layui-btn layui-btn-xs layui-btn-normal" id="imagePickBtn" title="选择"><i class="fa fa-image"></i></button>
                            <button type="button" class="layui-btn layui-btn-xs layui-btn-danger" id="imageClearBtn" title="清除"><i class="fa fa-times"></i></button>
                        </div>
                    </div>
                </div>
                <div class="layui-form-mid layui-word-aux">建议尺寸 200x80（5:2），支持 JPG/PNG/GIF/WebP</div>
            </div>
        </div>

        <!-- 有效期 -->
        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">过期时间</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" id="linkExpireTime" name="expire_time" placeholder="留空表示永久有效"
                           value="<?php echo $esc($expireTimeValue); ?>" readonly>
                </div>
                <div class="layui-form-mid layui-word-aux">留空表示永久有效，支持日期 + 时间</div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">启用状态</label>
                <div class="layui-input-block">
                    <input type="checkbox" name="enabled" lay-skin="switch" lay-text="启用|禁用" value="y"
                        <?php echo $isEdit ? ($editLink['enabled'] === 'y' ? 'checked' : '') : 'checked'; ?>>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">描述</label>
                <div class="layui-input-block">
                    <textarea class="layui-textarea" name="description" rows="2" maxlength="500" placeholder="可选填写描述信息"><?php echo $isEdit ? ($editLink['description'] ?? '') : ''; ?></textarea>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="popup-footer">
    <button type="button" class="popup-btn" id="linkCancelBtn"><i class="fa fa-times"></i> 取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="linkSubmitBtn"><i class="fa fa-check mr-5"></i>确认保存</button>
</div>

<script>
$(function () {
    layui.use(['layer', 'form', 'laydate'], function () {
        var layer = layui.layer;
        var form = layui.form;
        var laydate = layui.laydate;

        form.render('checkbox');
        form.render('switch');

        // 日期时间选择器
        laydate.render({
            elem: '#linkExpireTime',
            type: 'datetime',
            format: 'yyyy-MM-dd HH:mm:ss',
            done: function(value, date, endDate) {
                $('#linkExpireTime').val(value);
            }
        });

        // ============================================================
        // 常量
        // ============================================================
        var CROP_AREA_CROP = [window.innerWidth >= 800 ? '500px' : '95%', window.innerHeight >= 700 ? '580px' : '80%'];
        var CROP_AREA_PICK = [window.innerWidth >= 800 ? '700px' : '95%', window.innerHeight >= 500 ? '500px' : '80%'];

        // ============================================================
        // 图片上传公共函数
        // ============================================================
        var imageCfg = {
            previewId: 'imagePreview',
            urlId: 'imageUrl',
            clearBtnId: 'imageClearBtn',
            pickBtnId: 'imagePickBtn',
            uploadBtnId: 'imageUploadBtn',
            fieldId: 'imageField',
            aspectRatio: 2.5,
            cropWidth: 250,
            cropHeight: 100,
            context: 'friend_link'
        };

        function updateFieldPreview(cfg, url) {
            var $preview = $('#' + cfg.previewId);
            var $url = $('#' + cfg.urlId);
            var $field = $('#' + cfg.fieldId);
            var placeholder = $field.data('placeholder') || '';
            if (url) {
                $preview.attr('src', url);
                $url.val(url);
            } else {
                $preview.attr('src', placeholder);
                $url.val('');
            }
        }

        function openCropperForField(cfg, imgSrc, isFile) {
            var $cropperWrap = $('<div id="cropperWrap" class="cropper-wrap"></div>');
            $cropperWrap.html('<div class="cropper-container">'
                + '<img id="cropperImg">'
                + '</div>'
                + '<div class="cropper-tip">'
                + '<p>拖动裁剪框调整图片范围，确认后点击"保存"</p>'
                + '</div>');
            $('body').append($cropperWrap);

            var $cropperImg = $('#cropperImg');
            $cropperImg.attr('src', imgSrc);

            var cropperInstance = null;

            var cropLayerIndex = layer.open({
                type: 1,
                title: '裁剪友链图片',
                skin: 'admin-modal',
                maxmin: true,
                area: CROP_AREA_CROP,
                shadeClose: false,
                content: $cropperWrap,
                btn: ['保存', '使用原图', '取消'],
                success: function () {
                    var img = $cropperImg[0];
                    cropperInstance = new Cropper(img, {
                        aspectRatio: cfg.aspectRatio,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 0.8,
                        restore: false,
                        guides: true,
                        center: true,
                        highlight: false,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                        toggleDragModeOnDblclick: false,
                    });
                },
                yes: function (index) {
                    var croppedCanvas = cropperInstance.getCroppedCanvas({
                        width: cfg.cropWidth,
                        height: cfg.cropHeight,
                        imageSmoothingEnabled: true,
                        imageSmoothingQuality: 'high',
                    });
                    croppedCanvas.toBlob(function (blob) {
                        var formData = new FormData();
                        formData.append('file', blob, 'friend_link.jpg');
                        formData.append('csrf_token', $('input[name="csrf_token"]').val());
                        formData.append('context', cfg.context);

                        $.ajax({
                            url: '/admin/upload.php',
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            dataType: 'json',
                            success: function (res) {
                                if (res.code === 0 || res.code === 200) {
                                    updateFieldPreview(cfg, res.data.url);
                                    $('input[name="csrf_token"]').val(res.data.csrf_token);
                                    try { parent.updateCsrf(res.data.csrf_token); } catch(e) {}
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
                            formData.append('file', blob, 'friend_link_orig.jpg');
                            formData.append('csrf_token', $('input[name="csrf_token"]').val());
                            formData.append('context', cfg.context);
                            $.ajax({
                                url: '/admin/upload.php',
                                type: 'POST',
                                data: formData,
                                processData: false,
                                contentType: false,
                                dataType: 'json',
                                success: function (res) {
                                    if (res.code === 0 || res.code === 200) {
                                        updateFieldPreview(cfg, res.data.url);
                                        $('input[name="csrf_token"]').val(res.data.csrf_token);
                                        try { parent.updateCsrf(res.data.csrf_token); } catch(e) {}
                                        layer.msg('上传成功');
                                    } else {
                                        layer.msg(res.msg || '上传失败');
                                    }
                                },
                                error: function () { layer.msg('上传失败，请重试'); }
                            });
                            layer.close(index);
                        }, 'image/jpeg', 0.9);
                    };
                    return false;
                },
                end: function () {
                    if (cropperInstance) {
                        cropperInstance.destroy();
                        cropperInstance = null;
                    }
                    $('#cropperWrap').remove();
                }
            });
        }

        // 文件上传
        $('#imageUploadBtn').on('click', function () {
            var $fileInput = $('<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">');
            $('body').append($fileInput);
            $fileInput.on('change', function () {
                var file = this.files[0];
                if (!file) return;
                if (!file.type.match(/image\/(jpeg|png|gif|webp)/i)) {
                    layer.msg('仅支持 JPG、PNG、GIF、WebP 格式'); $(this).val(''); return;
                }
                if (file.size > 10 * 1024 * 1024) {
                    layer.msg('图片大小不能超过 10MB'); $(this).val(''); return;
                }
                var reader = new FileReader();
                reader.onload = function (e) { openCropperForField(imageCfg, e.target.result, true); };
                reader.readAsDataURL(file);
                $(this).val('');
            });
            $fileInput.click();
        });

        // 选择已有图片
        $('#imagePickBtn').on('click', function () {
            var csrfToken = $('input[name="csrf_token"]').val();
            layer.open({
                type: 2,
                title: '选择图片',
                skin: 'admin-modal',
                area: CROP_AREA_PICK,
                shadeClose: true,
                content: '/admin/media.php?_csrf=' + encodeURIComponent(csrfToken),
                btn: ['确定', '取消'],
                yes: function (index, layero) {
                    var win = layero.find('iframe')[0].contentWindow;
                    var url = win.selectMedia();
                    if (url === undefined) {
                        layer.msg('请先选择一张图片');
                        return;
                    }
                    updateFieldPreview(imageCfg, url);
                    layer.close(index);
                }
            });
        });

        // 清除
        $('#imageClearBtn').on('click', function () {
            updateFieldPreview(imageCfg, '');
        });

        // URL 输入后回车预览
        $('#imageUrl').on('blur', function () {
            var url = $(this).val();
            if (url) {
                $('#imagePreview').attr('src', url);
            }
        });

        // ============================================================
        // 取消按钮
        // ============================================================
        $('#linkCancelBtn').on('click', function () {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        // ============================================================
        // 确认保存
        // ============================================================
        $('#linkSubmitBtn').on('click', function () {
            var name = $('#linkName').val();
            var url = $('#linkUrl').val();

            if (!name) {
                layer.msg('请填写链接名称');
                return;
            }
            if (!url) {
                layer.msg('请填写链接地址');
                return;
            }
            if (!/^https?:\/\//i.test(url)) {
                layer.msg('链接地址必须以 http:// 或 https:// 开头');
                return;
            }

            var $btn = $(this);
            $btn.find('i').attr('class', 'fa fa-refresh admin-spin mr-5');
            $btn.prop('disabled', true);

            $.ajax({
                url: '/admin/friend_link.php',
                type: 'POST',
                data: $('#linkForm').serialize(),
                dataType: 'json',
                success: function (res) {
                    if (res.code === 200) {
                        var msg = res.msg || '保存成功';
                        if (res.data && res.data.csrf_token) {
                            try { parent.updateCsrf(res.data.csrf_token); } catch(e) {}
                        }
                        try { parent.window._linkPopupSaved = true; } catch(e) {}
                        var index = parent.layer.getFrameIndex(window.name);
                        parent.layer.msg(msg);
                        parent.layer.close(index);
                    } else {
                        layer.msg(res.msg || '操作失败');
                    }
                },
                error: function () {
                    layer.msg('网络错误，请重试');
                },
                complete: function () {
                    $btn.find('i').attr('class', 'fa fa-check mr-5');
                    $btn.prop('disabled', false);
                }
            });
        });
    });
});
</script>
<?php
include __DIR__ . '/footer.php';
