<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
$isEdit = isset($editUser) && $editUser !== null;

$placeholderImg = EM_CONFIG['placeholder_img'];
$esc = function (string $str) use (&$esc): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
};
include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <form class="layui-form" id="userForm" lay-filter="userForm">
        <input type="hidden" name="_action" value="<?php echo $isEdit ? 'update' : 'create'; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">
        <input type="hidden" name="id" value="<?php echo $isEdit ? $esc((string) $editUser['id']) : ''; ?>">

        <!-- 基本信息 -->
        <div class="popup-section">
            <?php if ($isEdit) { ?>
            <div class="layui-form-item">
                <label class="layui-form-label">用户名</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input layui-disabled" disabled value="<?php echo $esc($editUser['username']); ?>">
                    <div class="layui-form-mid layui-word-aux">用户名（账号）不可修改</div>
                </div>
            </div>
            <?php } else { ?>
            <div class="layui-form-item">
                <label class="layui-form-label">用户名</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" id="userUsername" name="username" maxlength="50" placeholder="用户登录账号" value="">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">密码</label>
                <div class="layui-input-block">
                    <input type="password" class="layui-input" id="userPassword" name="password" maxlength="50" placeholder="登录密码（至少6位）" autocomplete="new-password" value="">
                </div>
            </div>
            <?php } ?>
            <div class="layui-form-item">
                <label class="layui-form-label">昵称</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" id="userNickname" name="nickname" maxlength="100" placeholder="用户显示昵称"
                           value="<?php echo $isEdit ? $esc($editUser['nickname']) : ''; ?>">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">邮箱</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" id="userEmail" name="email" maxlength="120" placeholder="用户邮箱地址"
                           value="<?php echo $isEdit ? $esc($editUser['email']) : ''; ?>">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">手机号码</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" id="userMobile" name="mobile" maxlength="20" placeholder="用户手机号码（可选）"
                           value="<?php echo $isEdit ? $esc($editUser['mobile'] ?? '') : ''; ?>">
                </div>
            </div>
        </div>

        <!-- 头像设置 -->
        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">头像</label>
                <div class="layui-input-block">
                    <div class="admin-img-field" id="avatarField" data-placeholder="<?php echo $esc($placeholderImg); ?>">
                        <?php
                        $avatarSrc = $placeholderImg;
                        if ($isEdit && !empty($editUser['avatar'])) {
                            $avatarSrc = $editUser['avatar'];
                        }
                        ?>
                        <img src="<?php echo $esc($avatarSrc); ?>" alt="" id="avatarPreview">
                        <input type="text" class="layui-input admin-img-url" id="avatarUrl" name="avatar" maxlength="500"
                               placeholder="头像URL，可上传或选择"
                               value="<?php echo $isEdit ? $esc($editUser['avatar']) : ''; ?>">
                        <div class="admin-img-btns">
                            <button type="button" class="layui-btn layui-btn-xs" id="avatarUploadBtn" title="上传"><i class="fa fa-upload"></i></button>
                            <button type="button" class="layui-btn layui-btn-xs layui-btn-normal" id="avatarPickBtn" title="选择"><i class="fa fa-image"></i></button>
                            <button type="button" class="layui-btn layui-btn-xs layui-btn-danger" id="avatarClearBtn" title="清除"><i class="fa fa-times"></i></button>
                        </div>
                    </div>
                </div>
                <div class="layui-form-mid layui-word-aux">建议尺寸 200x200，支持 JPG/PNG/GIF/WebP</div>
            </div>
        </div>

        <!-- 用户等级（新建 / 编辑都可设；不设默认 0=无等级=不打折） -->
        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">用户等级</label>
                <div class="layui-input-block">
                    <select name="level_id" lay-search>
                        <option value="0">无等级（不打折）</option>
                        <?php
                        $currentLevelId = $isEdit ? (int) ($editUser['level_id'] ?? 0) : 0;
                        foreach (($userLevels ?? []) as $lv):
                            $lvId = (int) $lv['id'];
                            $lvName = (string) $lv['name'];
                            $disc = (float) ($lv['discount'] ?? 0);
                            // discount 字段 toDb 后存 ×1000000；UserLevelModel::getAll 已转回 9.5 这种数
                            $discTxt = $disc > 0 ? ' · ' . rtrim(rtrim(number_format($disc, 2), '0'), '.') . ' 折' : '';
                        ?>
                        <option value="<?php echo $lvId; ?>" <?php echo $lvId === $currentLevelId ? 'selected' : ''; ?>>
                            <?php echo $esc($lvName . $discTxt); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="layui-form-mid layui-word-aux">买家会员折扣，购买商品时按该折扣计算最终成交价</div>
            </div>
        </div>

        <!-- 状态 -->
        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">启用状态</label>
                <div class="layui-input-block">
                    <input type="checkbox" name="status" lay-skin="switch" lay-text="正常|禁用" value="1"
                        <?php echo $isEdit ? ($editUser['status'] == 1 ? 'checked' : '') : 'checked'; ?>>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="popup-footer">
    <button type="button" class="popup-btn" id="userCancelBtn"><i class="fa fa-times"></i> 取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="userSubmitBtn"><i class="fa fa-check mr-5"></i>确认保存</button>
</div>

<script>
$(function () {
    layui.use(['layer', 'form', 'upload'], function () {
        var layer = layui.layer;
        var form = layui.form;
        var upload = layui.upload;

        form.render('checkbox');
        form.render('switch');
        form.render('select');

        var CROP_AREA_CROP = [window.innerWidth >= 800 ? '500px' : '95%', window.innerHeight >= 700 ? '580px' : '80%'];
        var CROP_AREA_PICK = [window.innerWidth >= 800 ? '700px' : '95%', window.innerHeight >= 500 ? '500px' : '80%'];

        var avatarField = {
            previewId: 'avatarPreview',
            urlId: 'avatarUrl',
            clearBtnId: 'avatarClearBtn',
            pickBtnId: 'avatarPickBtn',
            uploadBtnId: 'avatarUploadBtn',
            fieldId: 'avatarField',
            aspectRatio: 1,
            cropWidth: 400,
            cropHeight: 400,
            context: 'user_avatar'
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

        var cropperInstance = null;

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
                title: '裁剪头像',
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
                        formData.append('file', blob, 'avatar.jpg');
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
                            formData.append('file', blob, 'avatar_orig.jpg');
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

        // 头像 URL 实时预览
        $('#' + avatarField.urlId).on('input', function () {
            var url = $(this).val();
            var $preview = $('#' + avatarField.previewId);
            $preview.attr('src', url || $(this).closest('.admin-img-field').data('placeholder') || '');
        });

        // 清除头像
        $('#' + avatarField.clearBtnId).on('click', function () {
            updateFieldPreview(avatarField, '');
        });

        // 选择头像
        $('#' + avatarField.pickBtnId).on('click', function () {
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
                    openCropperForField(avatarField, url, false);
                }
            });
        });

        // 上传头像
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
                    openCropperForField(avatarField, e.target.result, true);
                };
                reader.readAsDataURL(file);
                $(this).val('');
            }
        });

        $('#' + avatarField.uploadBtnId).on('click', function () {
            $fileInput.trigger('click');
        });

        // 取消按钮
        $('#userCancelBtn').on('click', function () {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        // 确认保存
        $('#userSubmitBtn').on('click', function () {
            var $btn = $(this);
            $btn.find('i').attr('class', 'fa fa-refresh admin-spin mr-5');
            $btn.prop('disabled', true);

            $.ajax({
                url: '/admin/user_list.php',
                type: 'POST',
                data: $('#userForm').serialize(),
                dataType: 'json',
                success: function (res) {
                    if (res.code === 200) {
                        var msg = res.msg || '保存成功';
                        if (res.data && res.data.csrf_token) {
                            try { parent.updateCsrf(res.data.csrf_token); } catch(e) {}
                        }
                        try { parent.window._userPopupSaved = true; } catch(e) {}
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
