<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
$isEdit = isset($editLang) && $editLang !== null;

$esc = function (string $str) use (&$esc): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
};

// 占位图
$placeholderImg = EM_CONFIG['placeholder_img'];

include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <form class="layui-form" id="langForm" lay-filter="langForm">
        <input type="hidden" name="_action" value="<?php echo $isEdit ? 'update' : 'create'; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">
        <input type="hidden" name="id" value="<?php echo $isEdit ? $esc((string) $editLang['id']) : ''; ?>">

        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">语言名称</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" id="langName" name="name" maxlength="50" placeholder="如：简体中文、English" value="<?php echo $isEdit ? $esc($editLang['name']) : ''; ?>">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">语言代码</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" id="langCode" name="code" maxlength="20" placeholder="如：zh-CN、en-US" value="<?php echo $isEdit ? $esc($editLang['code']) : ''; ?>">
                </div>
                <div class="layui-form-mid layui-word-aux">浏览器语言识别码</div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">语言图标</label>
                <div class="layui-input-block">
                    <div class="admin-img-field" id="iconField" data-placeholder="<?php echo $esc($placeholderImg); ?>">
                        <img src="<?php echo $isEdit && !empty($editLang['icon']) ? $esc($editLang['icon']) : $esc($placeholderImg); ?>"
                             alt="" id="iconPreview">
                        <input type="text" class="layui-input admin-img-url" id="iconUrl" name="icon" maxlength="500"
                               placeholder="国旗图片URL，可上传或选择"
                               value="<?php echo $isEdit ? $esc($editLang['icon']) : ''; ?>">
                        <div class="admin-img-btns">
                            <button type="button" class="layui-btn layui-btn-xs" id="iconUploadBtn" title="上传"><i class="fa fa-upload"></i></button>
                            <button type="button" class="layui-btn layui-btn-xs layui-btn-normal" id="iconPickBtn" title="选择"><i class="fa fa-image"></i></button>
                            <button type="button" class="layui-btn layui-btn-xs layui-btn-danger" id="iconClearBtn" title="清除"><i class="fa fa-times"></i></button>
                        </div>
                    </div>
                </div>
                <div class="layui-form-mid layui-word-aux">建议尺寸 64x32（2:1），支持 JPG/PNG/GIF/WebP</div>
            </div>
        </div>

        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">默认语言</label>
                <div class="layui-input-block">
                    <input type="checkbox" name="is_default" lay-skin="switch" lay-text="是|否" value="y"
                        <?php echo $isEdit ? ($editLang['is_default'] === 'y' ? 'checked' : '') : ''; ?>>
                </div>
                <div class="layui-form-mid layui-word-aux">设为默认后，其他语言将自动取消默认标记</div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">启用状态</label>
                <div class="layui-input-block">
                    <input type="checkbox" name="enabled" lay-skin="switch" lay-text="启用|禁用" value="y"
                        <?php echo $isEdit ? ($editLang['enabled'] === 'y' ? 'checked' : '') : 'checked'; ?>>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="popup-footer">
    <button type="button" class="popup-btn" id="langCancelBtn"><i class="fa fa-times"></i> 取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="langSubmitBtn"><i class="fa fa-check mr-5"></i>确认保存</button>
</div>

<script>
$(function () {
    layui.use(['layer', 'form', 'upload'], function () {
        var layer = layui.layer;
        var form = layui.form;
        var upload = layui.upload;

        form.render('switch');

        // ============================================================
        // 常量
        // ============================================================
        var CROP_AREA_CROP = [window.innerWidth >= 800 ? '500px' : '95%', window.innerHeight >= 700 ? '580px' : '80%'];
        var CROP_AREA_PICK = [window.innerWidth >= 800 ? '700px' : '95%', window.innerHeight >= 500 ? '500px' : '80%'];

        // ============================================================
        // 图片上传公共函数
        // ============================================================

        var iconCfg = {
            previewId: 'iconPreview',
            urlId: 'iconUrl',
            clearBtnId: 'iconClearBtn',
            pickBtnId: 'iconPickBtn',
            uploadBtnId: 'iconUploadBtn',
            fieldId: 'iconField',
            aspectRatio: 2,
            cropWidth: 128,
            cropHeight: 64,
            context: 'lang_icon'
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

            var cropLayerIndex = layer.open({
                type: 1,
                title: '裁剪语言图标',
                skin: 'admin-modal',
                maxmin: true,
                area: CROP_AREA_CROP,
                shadeClose: false,
                content: $cropperWrap,
                btn: ['保存', '使用原图', '取消'],
                success: function () {
                    var img = $cropperImg[0];
                    var cropper = new Cropper(img, {
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
                    layer.full(cropLayerIndex);

                    var btns = $('.layui-layer-btn .layui-layer-btn0');
                    btns.on('click', function () {
                        var croppedCanvas = cropper.getCroppedCanvas({
                            width: cfg.cropWidth,
                            height: cfg.cropHeight,
                            imageSmoothingEnabled: true,
                            imageSmoothingQuality: 'high',
                        });
                        croppedCanvas.toBlob(function (blob) {
                            var formData = new FormData();
                            formData.append('file', blob, 'icon.jpg');
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
                                        layer.msg(res.msg || '上传成功');
                                    } else {
                                        layer.msg(res.msg || '上传失败');
                                    }
                                },
                                error: function () {
                                    layer.msg('上传失败，请重试');
                                }
                            });
                            layer.close(cropLayerIndex);
                        }, 'image/jpeg', 0.9);
                    });
                    $('.layui-layer-btn .layui-layer-btn1').on('click', function () {
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
                                formData.append('file', blob, 'icon_orig.jpg');
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
                                            layer.msg('上传成功');
                                        } else {
                                            layer.msg(res.msg || '上传失败');
                                        }
                                    },
                                    error: function () { layer.msg('上传失败，请重试'); }
                                });
                                layer.close(cropLayerIndex);
                            }, 'image/jpeg', 0.9);
                        };
                        return false;
                    });
                },
                end: function () {
                    $('#cropperWrap').remove();
                }
            });
        }

        // 文件上传
        $('#iconUploadBtn').on('click', function () {
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
                reader.onload = function (e) { openCropperForField(iconCfg, e.target.result, true); };
                reader.readAsDataURL(file);
                $(this).val('');
            });
            $fileInput.click();
        });

        // 选择已有图片
        $('#iconPickBtn').on('click', function () {
            var csrfToken = $('input[name="csrf_token"]').val();
            layer.open({
                type: 2,
                title: '选择图片',
                skin: 'admin-modal',
                area: CROP_AREA_PICK,
                shadeClose: true,
                content: '/admin/media.php?_csrf=' + encodeURIComponent(csrfToken),
                btn: ['确认', '取消'],
                yes: function (index, layero) {
                    var win = layero.find('iframe')[0].contentWindow;
                    var url = win.selectMedia();
                    if (url === undefined) {
                        layer.msg('请先选择一张图片');
                        return;
                    }
                    updateFieldPreview(iconCfg, url);
                    layer.close(index);
                }
            });
        });

        // 清除
        $('#iconClearBtn').on('click', function () {
            updateFieldPreview(iconCfg, '');
        });

        // URL 输入后回车预览
        $('#iconUrl').on('blur', function () {
            var url = $(this).val();
            if (url) {
                $('#iconPreview').attr('src', url);
            }
        });

        // ============================================================
        // 表单提交
        // ============================================================

        // 取消按钮
        $('#langCancelBtn').on('click', function () {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        // 确认保存
        $('#langSubmitBtn').on('click', function () {
            var name = $('#langName').val();
            var code = $('#langCode').val();

            if (!name) {
                layer.msg('请填写语言名称');
                return;
            }
            if (!code) {
                layer.msg('请填写语言代码');
                return;
            }

            var $btn = $(this);
            $btn.find('i').attr('class', 'fa fa-refresh admin-spin mr-5');
            $btn.prop('disabled', true);

            $.ajax({
                url: '/admin/language.php',
                type: 'POST',
                data: $('#langForm').serialize(),
                dataType: 'json',
                success: function (res) {
                    if (res.code === 200) {
                        var msg = res.msg || '保存成功';
                        if (res.data && res.data.csrf_token) {
                            try { parent.updateCsrf(res.data.csrf_token); } catch(e) {}
                        }
                        try { parent.window._langPopupSaved = true; } catch(e) {}
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
