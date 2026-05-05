<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
$isEdit = isset($editCat) && $editCat !== null;

$placeholderImg = EM_CONFIG['placeholder_img'];
$esc = function (string $str) use (&$esc): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
};
include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <form class="layui-form" id="catForm" lay-filter="catForm">
        <input type="hidden" name="_action" value="<?php echo $isEdit ? 'update' : 'create'; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">
        <input type="hidden" name="id" value="<?php echo $isEdit ? $esc((string) $editCat['id']) : ''; ?>">

        <!-- 选项卡（em-tabs，和商品分类弹窗同款） -->
        <div class="em-tabs" id="catEditTabs" style="margin-bottom: 0;">
            <a class="em-tabs__item is-active"><i class="fa fa-cog"></i>基础配置</a>
            <a class="em-tabs__item"><i class="fa fa-search"></i>SEO 配置</a>
            <a class="em-tabs__item"><i class="fa fa-sliders"></i>其他设置</a>
        </div>
        <div class="layui-tab-content cat-edit-content">

            <!-- ========== Tab 1: 基础配置 ========== -->
            <div class="layui-tab-item layui-show">
                <div class="popup-section">
                    <div class="layui-form-item">
                        <label class="layui-form-label">上级分类</label>
                        <div class="layui-input-block">
                            <select name="parent_id" class="layui-select">
                                <option value="0">顶级分类</option>
                                <?php foreach ($topLevelCats as $cat): ?>
                                    <?php if ($isEdit && (int) $editCat['id'] === (int) $cat['id']) continue; ?>
                                    <option value="<?php echo (int) $cat['id']; ?>" <?php echo $isEdit && (int) $editCat['parent_id'] === (int) $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo $esc($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="layui-form-mid layui-word-aux">选择所属的顶级分类，仅支持二级分类</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">分类名称</label>
                        <div class="layui-input-block">
                            <input type="text" class="layui-input" name="name" maxlength="100" placeholder="请输入分类名称"
                                   value="<?php echo $isEdit ? $esc($editCat['name']) : ''; ?>">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">URL别名</label>
                        <div class="layui-input-block">
                            <input type="text" class="layui-input" name="slug" maxlength="100" placeholder="用于URL路径，可留空"
                                   value="<?php echo $isEdit ? $esc($editCat['slug']) : ''; ?>">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">分类描述</label>
                        <div class="layui-input-block">
                            <textarea class="layui-textarea" name="description" rows="2" maxlength="500" placeholder="可选填写分类描述"><?php echo $isEdit ? $esc($editCat['description']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- 封面图片 -->
                <div class="popup-section">
                    <div class="layui-form-item">
                        <div class="layui-input-block">
                            <div class="admin-img-field" id="coverField" data-placeholder="<?php echo $esc($placeholderImg); ?>">
                                <img src="<?php echo $isEdit && !empty($editCat['cover_image']) ? $esc($editCat['cover_image']) : $esc($placeholderImg); ?>"
                                     alt="" id="coverPreview">
                                <input type="text" class="layui-input admin-img-url" id="coverUrl" name="cover_image" maxlength="500"
                                       placeholder="封面图片URL，可上传或选择"
                                       value="<?php echo $isEdit ? $esc($editCat['cover_image']) : ''; ?>">
                                <div class="admin-img-btns">
                                    <button type="button" class="layui-btn layui-btn-xs" id="coverUploadBtn" title="上传"><i class="fa fa-upload"></i></button>
                                    <button type="button" class="layui-btn layui-btn-xs layui-btn-normal" id="coverPickBtn" title="选择"><i class="fa fa-image"></i></button>
                                    <button type="button" class="layui-btn layui-btn-xs layui-btn-danger" id="coverClearBtn" title="清除"><i class="fa fa-times"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="layui-form-mid layui-word-aux">建议尺寸 400x300，支持 JPG/PNG/GIF/WebP</div>
                    </div>
                </div>
            </div>

            <!-- ========== Tab 2: SEO 配置 ========== -->
            <div class="layui-tab-item">
                <div class="popup-section">
                    <div class="layui-form-item">
                        <label class="layui-form-label">标题 title</label>
                        <div class="layui-input-block">
                            <input type="text" class="layui-input" name="seo_title" maxlength="200" placeholder="留空则使用分类名称"
                                   value="<?php echo $isEdit ? $esc($editCat['seo_title']) : ''; ?>">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">关键词 keywords</label>
                        <div class="layui-input-block">
                            <input type="text" class="layui-input" name="seo_keywords" maxlength="500" placeholder="多个关键词用英文逗号分隔"
                                   value="<?php echo $isEdit ? $esc($editCat['seo_keywords']) : ''; ?>">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">描述 description</label>
                        <div class="layui-input-block">
                            <textarea class="layui-textarea" name="seo_description" rows="3" maxlength="500" placeholder="留空则使用分类描述"><?php echo $isEdit ? $esc($editCat['seo_description']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========== Tab 3: 其他设置 ========== -->
            <div class="layui-tab-item">
                <div class="popup-section">
                    <div class="layui-form-item">
                        <label class="layui-form-label">排序值</label>
                        <div class="layui-input-block admin-form-width-sm">
                            <input type="number" class="layui-input" name="sort" step="1" min="0" placeholder="100"
                                   value="<?php echo $isEdit ? (int) $editCat['sort'] : 100; ?>">
                        </div>
                        <div class="layui-form-mid layui-word-aux">数值越小越靠前</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">启用状态</label>
                        <div class="layui-input-block">
                            <input type="checkbox" name="status" lay-skin="switch" lay-text="启用|禁用" value="1"
                                <?php echo $isEdit ? ($editCat['status'] == 1 ? 'checked' : '') : 'checked'; ?>>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

<div class="popup-footer">
    <button type="button" class="em-btn em-reset-btn" id="catCancelBtn"><i class="fa fa-times"></i>取消</button>
    <button type="button" class="em-btn em-save-btn" id="catSubmitBtn"><i class="fa fa-check"></i>确认保存</button>
</div>

<style>
/* 选项卡内的 layui-tab-item 去掉默认 padding（和 goods_category.php 做法一致） */
.cat-edit-content > .layui-tab-item { padding: 0; }
</style>

<script>
$(function () {
    // em-tabs 点击切换：同步 .is-active 到 tab 项，同步 .layui-show 到对应面板
    $('#catEditTabs').on('click', '.em-tabs__item', function () {
        var $item = $(this);
        if ($item.hasClass('is-active')) return;
        var index = $item.index();
        $item.addClass('is-active').siblings().removeClass('is-active');
        $item.closest('.em-tabs').next('.layui-tab-content')
            .children('.layui-tab-item')
            .removeClass('layui-show').eq(index).addClass('layui-show');
    });

    layui.use(['layer', 'form', 'upload'], function () {
        var layer = layui.layer;
        var form = layui.form;
        var upload = layui.upload;

        form.render('checkbox');
        form.render('switch');
        form.render('select');

        var CROP_AREA_CROP = [window.innerWidth >= 800 ? '500px' : '95%', window.innerHeight >= 700 ? '580px' : '80%'];
        var CROP_AREA_PICK = [window.innerWidth >= 800 ? '700px' : '95%', window.innerHeight >= 500 ? '500px' : '80%'];

        var coverCfg = {
            previewId: 'coverPreview',
            urlId: 'coverUrl',
            clearBtnId: 'coverClearBtn',
            pickBtnId: 'coverPickBtn',
            uploadBtnId: 'coverUploadBtn',
            fieldId: 'coverField',
            aspectRatio: 4 / 3,
            cropWidth: 400,
            cropHeight: 300,
            context: 'blog_category_cover'
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
            $cropperWrap.html('<div class="cropper-container"><img id="cropperImg"></div><div class="cropper-tip"><p>拖动裁剪框调整图片范围，确认后点击"保存"</p></div>');
            $('body').append($cropperWrap);

            var $cropperImg = $('#cropperImg');
            $cropperImg.attr('src', imgSrc);

            var cropperInstance = null;

            layer.open({
                type: 1,
                title: '裁剪封面图片',
                skin: 'admin-modal',
                maxmin: true,
                area: CROP_AREA_CROP,
                shadeClose: false,
                content: $cropperWrap,
                btn: ['保存', '使用原图', '取消'],
                success: function () {
                    cropperInstance = new Cropper($cropperImg[0], {
                        aspectRatio: cfg.aspectRatio,
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
                        width: cfg.cropWidth,
                        height: cfg.cropHeight,
                        imageSmoothingEnabled: true,
                        imageSmoothingQuality: 'high',
                    });
                    canvas.toBlob(function (blob) {
                        var formData = new FormData();
                        formData.append('file', blob, 'cover.jpg');
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
                            error: function () { layer.msg('上传失败，请重试'); }
                        });
                        layer.close(index);
                    }, 'image/jpeg', 0.9);
                },
                btn2: function (index) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', imgSrc, true);
                    xhr.responseType = 'blob';
                    xhr.onload = function () {
                        if (xhr.status === 200) {
                            var blob = xhr.response;
                            var formData = new FormData();
                            formData.append('file', blob, 'cover_orig.jpg');
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
                            layer.close(index);
                        }
                    };
                    xhr.send();
                    return false;
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

        // URL 输入实时预览
        $('#' + coverCfg.urlId).on('input', function () {
            var url = $(this).val();
            var $preview = $('#' + coverCfg.previewId);
            $preview.attr('src', url || $(this).closest('.admin-img-field').data('placeholder') || '');
        });

        // 清除
        $('#' + coverCfg.clearBtnId).on('click', function () {
            updateFieldPreview(coverCfg, '');
        });

        // 选择图片
        $('#' + coverCfg.pickBtnId).on('click', function () {
            var csrfToken = $('input[name="csrf_token"]').val();
            layer.open({
                type: 2,
                title: '选择图片',
                skin: 'admin-modal',
                maxmin: true,
                area: CROP_AREA_PICK,
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
                    openCropperForField(coverCfg, url, false);
                }
            });
        });

        // 上传
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
                    openCropperForField(coverCfg, e.target.result, true);
                };
                reader.readAsDataURL(file);
                $(this).val('');
            }
        });

        $('#' + coverCfg.uploadBtnId).on('click', function () {
            $fileInput.trigger('click');
        });

        // 取消
        $('#catCancelBtn').on('click', function () {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        // 确认保存
        $('#catSubmitBtn').on('click', function () {
            var $btn = $(this);
            $btn.find('i').attr('class', 'fa fa-refresh admin-spin');
            $btn.prop('disabled', true).addClass('is-loading');

            $.ajax({
                url: '/admin/blog_category.php',
                type: 'POST',
                data: $('#catForm').serialize(),
                dataType: 'json',
                success: function (res) {
                    if (res.code === 200) {
                        var msg = res.msg || '保存成功';
                        if (res.data && res.data.csrf_token) {
                            try { parent.updateCsrf(res.data.csrf_token); } catch(e) {}
                        }
                        try { parent.window._catPopupSaved = true; } catch(e) {}
                        var index = parent.layer.getFrameIndex(window.name);
                        parent.layer.msg(msg);
                        parent.layer.close(index);
                    } else {
                        if (res.data && res.data.csrf_token) {
                            $('input[name="csrf_token"]').val(res.data.csrf_token);
                        }
                        layer.msg(res.msg || '操作失败');
                    }
                },
                error: function () {
                    layer.msg('网络错误，请重试');
                },
                complete: function () {
                    $btn.find('i').attr('class', 'fa fa-check');
                    $btn.prop('disabled', false).removeClass('is-loading');
                }
            });
        });
    });
});
</script>
<?php
include __DIR__ . '/footer.php';
?>
