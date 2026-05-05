<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$esc = function (string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
};
$avatarUrl = !empty($userFull['avatar']) ? $esc((string) $userFull['avatar']) : $esc(EM_CONFIG['avatar'] ?? '/content/static/img/avatar.jpeg');
?>
<div class="admin-page">
    <h1 class="admin-page__title">个人信息</h1>

    <div class="admin-profile">
        <!-- 头像区域 -->
        <div class="admin-profile__section">
            <h3 class="admin-profile__section-title">头像</h3>
            <div class="admin-profile__avatar-field" id="profileAvatarField">
                <img src="<?php echo $avatarUrl; ?>" alt="头像" id="profileAvatarPreview" layer-src="<?php echo $avatarUrl; ?>" onerror="this.src='<?php echo $esc(EM_CONFIG['avatar'] ?? '/content/static/img/avatar.jpeg'); ?>';this.onerror=null;">
                <input type="text" class="admin-profile__avatar-url" id="profileAvatarUrl" placeholder="头像图片 URL，可上传或选择" readonly disabled value="<?= empty($userFull['avatar']) ? '' : $userFull['avatar'] ?>">
                <div class="admin-profile__avatar-btns">
                    <button type="button" class="layui-btn layui-btn-xs" id="profileAvatarBtn" title="上传"><i class="fa fa-upload"></i></button>
                    <button type="button" class="layui-btn layui-btn-xs layui-btn-normal" id="profileAvatarPickBtn" title="选择"><i class="fa fa-image"></i></button>
                    <button type="button" class="layui-btn layui-btn-xs layui-btn-danger" id="profileResetAvatarBtn" title="重置"><i class="fa fa-times"></i></button>
                </div>
            </div>
        </div>

        <!-- 基本信息 -->
        <div class="admin-profile__section">
            <h3 class="admin-profile__section-title">基本信息</h3>
            <form class="admin-form" id="profileInfoForm">
                <input type="hidden" name="action" value="profile">
                <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">
                <div class="admin-form__group">
                    <label class="admin-form__label" for="profileUsername">账号</label>
                    <input type="text" class="admin-form__input" id="profileUsername" name="username" value="<?php echo $esc((string) $userFull['username']); ?>" maxlength="30">
                </div>
                <div class="admin-form__group">
                    <label class="admin-form__label" for="profileNickname">昵称</label>
                    <input type="text" class="admin-form__input" id="profileNickname" name="nickname" value="<?php echo $esc((string) $userFull['nickname']); ?>" maxlength="50">
                </div>
                <div class="admin-form__group">
                    <label class="admin-form__label" for="profileEmail">邮箱</label>
                    <input type="email" class="admin-form__input" id="profileEmail" name="email" value="<?php echo $esc((string) $userFull['email']); ?>" maxlength="120">
                </div>
                <div class="admin-form__group">
                    <label class="admin-form__label" for="profileMobile">手机号码</label>
                    <input type="text" class="admin-form__input" id="profileMobile" name="mobile" value="<?php echo $esc((string) ($userFull['mobile'] ?? '')); ?>" maxlength="20" placeholder="可选填写">
                </div>
                <div class="admin-form__actions">
                    <button type="submit" class="admin-btn admin-btn--primary">保存修改</button>
                    <button type="button" class="admin-btn" id="changePasswordBtn">修改密码</button>
                </div>
            </form>
        </div>

    </div>
</div>

<style>
@keyframes avatarSpin {
    to { transform: rotate(360deg); }
}
</style>

<script>

$(function(){
    // PJAX 防重复绑定：清掉本页历史 .admProfile handler，避免事件成倍触发
    $(document).off('.admProfile');
    $(window).off('.admProfile');


    layui.use(['layer', 'upload'], function () {
        var layer = layui.layer;
        var upload = layui.upload;

        function updateCsrf(token) {
            if (!token) return;
            $('input[name="csrf_token"]').val(token);
        }
        window.updateCsrf = updateCsrf;

        // 基本信息表单
        $('#profileInfoForm').on('submit', function (e) {
            e.preventDefault();
            var $btn = $(this).find('button[type="submit"]');
            $btn.prop('disabled', true).text('保存中...');

            $.ajax({
                url: '/admin/profile.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (res) {
                    if (res.code === 200) {
                        layer.msg(res.msg);
                        updateCsrf(res.data.csrf_token);
                        // 同步右上角用户名显示
                        if (res.data.user) {
                            var name = res.data.user.nickname || res.data.user.username || '';
                            $('.admin-user-menu__meta strong').text(name);
                            // 如果返回了新的头像则更新，否则保持不变
                            if (res.data.user.avatar) {
                                var newAvatar = res.data.user.avatar;
                                var fallbackAvatar = '<?php echo $esc(EM_CONFIG['avatar'] ?? '/content/static/img/avatar.jpeg'); ?>';
                                $('.admin-avatar').html('<img src="' + newAvatar + '" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;" onerror="this.src=\'' + fallbackAvatar + '\';this.onerror=null;">');
                            }
                        }
                    } else {
                        layer.msg(res.msg);
                    }
                },
                error: function () {
                    layer.msg('网络错误，请重试');
                },
                complete: function () {
                    $btn.prop('disabled', false).text('保存修改');
                }
            });
        });

        // 修改密码
        $('#changePasswordBtn').on('click', function () {
            layer.open({
                type: 2,
                title: '修改密码',
                skin: 'admin-modal',
                maxmin: true,
                area: [window.innerWidth >= 800 ? '500px' : '95%', window.innerHeight >= 500 ? '500px' : '80%'],
                offset: 'auto',
                shadeClose: true,
                content: '/admin/password.php'
            });
        });

        // 头像上传（先裁剪再上传）
        var currentAvatar = $('#profileAvatarPreview').attr('src');
        var $avatarImg = $('#profileAvatarPreview');
        var cropperInstance = null;
        var $cropperModal, $cropperImg;

        function openCropper(file) {
            if (!file.type.match(/image\/(jpeg|png|gif|webp)/i)) {
                layer.msg('仅支持 JPG、PNG、GIF、WebP 格式');
                return;
            }
            if (file.size > 2048 * 1024) {
                layer.msg('图片大小不能超过 2MB');
                return;
            }

            var imgSrc;
            var reader = new FileReader();
            reader.onload = function (e) {
                imgSrc = e.target.result;

                $cropperWrap = $('<div id="cropperWrap"></div>');
                $cropperWrap.html('<div style="padding:16px;">'
                    + '<div id="cropperContainer" style="height:400px;overflow:hidden;line-height:0;">'
                    + '<img id="cropperImg" style="max-width:100%;display:block;">'
                    + '</div>'
                    + '<div style="margin-top:12px;text-align:center;">'
                    + '<p style="margin-bottom:10px;color:#999;font-size:12px;">拖动裁剪框调整头像范围，确认后点击"保存"</p>'
                    + '</div>'
                    + '</div>');
                $('body').append($cropperWrap);

                $cropperImg = $('#cropperImg');
                $cropperImg.attr('src', imgSrc);

                layer.open({
                    type: 1,
                    title: '裁剪头像',
                    skin: 'admin-modal',
                    maxmin: true,
                    area: [window.innerWidth >= 800 ? '500px' : '95%', window.innerHeight >= 700 ? '600px' : '80%'],
                    offset: 'auto',
                    shadeClose: false,
                    content: $cropperWrap,
                    btn: ['保存', '使用原图', '取消'],
                    success: function () {
                        var img = $cropperImg[0];
                        var natW = img.naturalWidth || 400;
                        var natH = img.naturalHeight || 400;
                        var ratio = natW / natH;
                        var dispH = ratio >= 1 ? Math.round(400 / ratio) : 400;
                        $('#cropperContainer').css('height', dispH + 'px');

                        cropperInstance = new Cropper(img, {
                            aspectRatio: 1,
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
                            width: 256,
                            height: 256,
                            imageSmoothingEnabled: true,
                            imageSmoothingQuality: 'high',
                        });

                        canvas.toBlob(function (blob) {
                            var formData = new FormData();
                            formData.append('avatar_file', blob, 'avatar.jpg');
                            formData.append('action', 'avatar');
                            formData.append('csrf_token', $('input[name="csrf_token"]').val());

                            $avatarImg.after('<div id="avatarUploadLoading" style="position:absolute;top:0;left:0;width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.4);border-radius:inherit;z-index:1;"><div style="width:32px;height:32px;border:3px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:avatarSpin .8s linear infinite;"></div></div>');

                            $.ajax({
                                url: '/admin/profile.php',
                                type: 'POST',
                                data: formData,
                                processData: false,
                                contentType: false,
                                dataType: 'json',
                                success: function (res) {
                                    $('#avatarUploadLoading').remove();
                                    if (res.code === 200) {
                                        currentAvatar = res.data.avatar;
                                        $avatarImg.attr('src', currentAvatar).attr('layer-src', currentAvatar);
                                        $('#profileAvatarUrl').val(currentAvatar);
                                        $('.admin-avatar').html('<img src="' + currentAvatar + '" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">');
                                        layer.msg(res.msg);
                                        updateCsrf(res.data.csrf_token);
                                    } else {
                                        layer.msg(res.msg);
                                    }
                                },
                                error: function () {
                                    $('#avatarUploadLoading').remove();
                                    layer.msg('上传失败，请重试');
                                }
                            });

                            layer.close(index);
                        }, 'image/jpeg', 0.9);
                    },
                    btn2: function (index) {
                        var tmpImg = new Image();
                        tmpImg.src = imgSrc;
                        tmpImg.onload = function () {
                            var origCanvas = document.createElement('canvas');
                            origCanvas.width = tmpImg.naturalWidth;
                            origCanvas.height = tmpImg.naturalHeight;
                            origCanvas.getContext('2d').drawImage(tmpImg, 0, 0);
                            origCanvas.toBlob(function (blob) {
                                var formData = new FormData();
                                formData.append('avatar_file', blob, 'avatar_orig.jpg');
                                formData.append('action', 'avatar');
                                formData.append('csrf_token', $('input[name="csrf_token"]').val());

                                $avatarImg.after('<div id="avatarUploadLoading" style="position:absolute;top:0;left:0;width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.4);border-radius:inherit;z-index:1;"><div style="width:32px;height:32px;border:3px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:avatarSpin .8s linear infinite;"></div></div>');

                                $.ajax({
                                    url: '/admin/profile.php',
                                    type: 'POST',
                                    data: formData,
                                    processData: false,
                                    contentType: false,
                                    dataType: 'json',
                                    success: function (res) {
                                        $('#avatarUploadLoading').remove();
                                        if (res.code === 200) {
                                            currentAvatar = res.data.avatar;
                                            $avatarImg.attr('src', currentAvatar).attr('layer-src', currentAvatar);
                                            $('#profileAvatarUrl').val(currentAvatar);
                                            $('.admin-avatar').html('<img src="' + currentAvatar + '" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">');
                                            layer.msg('头像已更新');
                                            updateCsrf(res.data.csrf_token);
                                        } else {
                                            layer.msg(res.msg);
                                        }
                                    },
                                    error: function () {
                                        $('#avatarUploadLoading').remove();
                                        layer.msg('上传失败，请重试');
                                    }
                                });

                                layer.close(index);
                            }, 'image/jpeg', 0.9);
                        };
                    },
                    end: function () {
                        if (cropperInstance) {
                            cropperInstance.destroy();
                            cropperInstance = null;
                        }
                        $cropperWrap.remove();
                    }
                });
            };
            reader.readAsDataURL(file);
        }

        // 隐藏的文件选择器
        var $fileInput = $('<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">');
        $('body').append($fileInput);

        $fileInput.on('change', function () {
            var file = this.files[0];
            if (file) {
                openCropper(file);
                $(this).val('');
            }
        });

        $('#profileAvatarBtn').on('click', function () {
            $fileInput.trigger('click');
        });

        // 选择历史上传的图片
        var pickLayerIndex = null;

        // 选择图片后自动裁剪并保存
        window.selectMediaAndCrop = function (url) {
            if (!url) return;
            if (pickLayerIndex !== null) {
                layer.close(pickLayerIndex);
                pickLayerIndex = null;
            }

            // 加载图片进行裁剪
            var img = new Image();
            img.crossOrigin = 'Anonymous';
            img.onload = function () {
                var imgSrc = img.src;

                var $cropperWrap = $('<div id="cropperWrap"></div>');
                $cropperWrap.html('<div style="padding:16px;">'
                    + '<div id="cropperContainer" style="height:400px;overflow:hidden;line-height:0;">'
                    + '<img id="cropperImg" style="max-width:100%;display:block;">'
                    + '</div>'
                    + '<div style="margin-top:12px;text-align:center;">'
                    + '<p style="margin-bottom:10px;color:#999;font-size:12px;">拖动裁剪框调整头像范围，确认后点击"保存"</p>'
                    + '</div>'
                    + '</div>');
                $('body').append($cropperWrap);

                $cropperImg = $('#cropperImg');
                $cropperImg.attr('src', imgSrc);

                layer.open({
                    type: 1,
                    title: '裁剪头像',
                    skin: 'admin-modal',
                    maxmin: true,
                    area: [window.innerWidth >= 800 ? '500px' : '95%', window.innerHeight >= 700 ? '600px' : '80%'],
                    offset: 'auto',
                    shadeClose: false,
                    content: $cropperWrap,
                    btn: ['保存', '使用原图', '取消'],
                    success: function () {
                        var imgEl = $cropperImg[0];
                        var natW = imgEl.naturalWidth || 400;
                        var natH = imgEl.naturalHeight || 400;
                        var ratio = natW / natH;
                        var dispH = ratio >= 1 ? Math.round(400 / ratio) : 400;
                        $('#cropperContainer').css('height', dispH + 'px');

                        cropperInstance = new Cropper(imgEl, {
                            aspectRatio: 1,
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
                            width: 256,
                            height: 256,
                            imageSmoothingEnabled: true,
                            imageSmoothingQuality: 'high',
                        });

                        canvas.toBlob(function (blob) {
                            var formData = new FormData();
                            formData.append('avatar_file', blob, 'avatar.jpg');
                            formData.append('action', 'avatar');
                            formData.append('csrf_token', $('input[name="csrf_token"]').val());

                            $avatarImg.after('<div id="avatarUploadLoading" style="position:absolute;top:0;left:0;width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.4);border-radius:inherit;z-index:1;"><div style="width:32px;height:32px;border:3px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:avatarSpin .8s linear infinite;"></div></div>');

                            $.ajax({
                                url: '/admin/profile.php',
                                type: 'POST',
                                data: formData,
                                processData: false,
                                contentType: false,
                                dataType: 'json',
                                success: function (res) {
                                    $('#avatarUploadLoading').remove();
                                    if (res.code === 200) {
                                        currentAvatar = res.data.avatar;
                                        $avatarImg.attr('src', currentAvatar).attr('layer-src', currentAvatar);
                                        $('#profileAvatarUrl').val(currentAvatar);
                                        $('.admin-avatar').html('<img src="' + currentAvatar + '" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">');
                                        layer.msg('头像已更新');
                                        updateCsrf(res.data.csrf_token);
                                    } else {
                                        layer.msg(res.msg);
                                    }
                                },
                                error: function () {
                                    $('#avatarUploadLoading').remove();
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
                                formData.append('avatar_file', blob, 'avatar_orig.jpg');
                                formData.append('action', 'avatar');
                                formData.append('csrf_token', $('input[name="csrf_token"]').val());

                                $avatarImg.after('<div id="avatarUploadLoading" style="position:absolute;top:0;left:0;width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.4);border-radius:inherit;z-index:1;"><div style="width:32px;height:32px;border:3px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:avatarSpin .8s linear infinite;"></div></div>');

                                $.ajax({
                                    url: '/admin/profile.php',
                                    type: 'POST',
                                    data: formData,
                                    processData: false,
                                    contentType: false,
                                    dataType: 'json',
                                    success: function (res) {
                                        $('#avatarUploadLoading').remove();
                                        if (res.code === 200) {
                                            currentAvatar = res.data.avatar;
                                            $avatarImg.attr('src', currentAvatar).attr('layer-src', currentAvatar);
                                            $('#profileAvatarUrl').val(currentAvatar);
                                            $('.admin-avatar').html('<img src="' + currentAvatar + '" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">');
                                            layer.msg('头像已更新');
                                            updateCsrf(res.data.csrf_token);
                                        } else {
                                            layer.msg(res.msg);
                                        }
                                    },
                                    error: function () {
                                        $('#avatarUploadLoading').remove();
                                        layer.msg('上传失败，请重试');
                                    }
                                });

                                layer.close(index);
                            }, 'image/jpeg', 0.9);
                        };
                    },
                    end: function () {
                        if (cropperInstance) {
                            cropperInstance.destroy();
                            cropperInstance = null;
                        }
                        $cropperWrap.remove();
                    }
                });
            };
            img.onerror = function () {
                layer.msg('图片加载失败');
            };
            img.src = url;
        };

        $('#profileAvatarPickBtn').on('click', function () {
            var csrfToken = $('input[name="csrf_token"]').val();
            pickLayerIndex = layer.open({
                type: 2,
                title: '选择图片',
                maxmin: true,
                skin: 'admin-modal',
                area: [window.innerWidth >= 800 ? '700px' : '95%', window.innerHeight >= 500 ? '500px' : '80%'],
                shadeClose: false,
                content: '/admin/media.php?_csrf=' + encodeURIComponent(csrfToken),
                btn: ['确定', '取消'],
                yes: function (index, layero) {
                    var win = layero.find('iframe')[0].contentWindow;
                    var url = win.selectMedia();
                    if (url === undefined) {
                        layer.msg('请先选择一张图片');
                        return;
                    }
                    layer.close(index);
                    pickLayerIndex = null;
                    // 调用裁剪流程
                    window.selectMediaAndCrop(url);
                }
            });
        });

        // 头像点击弹出大图
        $(document).on('click.admProfile', '#profileAvatarPreview', function () {
            layer.photos({
                photos: {
                    title: '',
                    id: 0,
                    start: 0,
                    data: [{
                        alt: '头像',
                        pid: 0,
                        src: $(this).attr('src'),
                    }]
                },
                anim: 5
            });
        });

        // 恢复默认头像
        $('#profileResetAvatarBtn').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true);
            $.ajax({
                url: '/admin/profile.php',
                type: 'POST',
                data: {
                    action: 'reset_avatar',
                    csrf_token: $('input[name="csrf_token"]').val(),
                },
                dataType: 'json',
                success: function (res) {
                    if (res.code === 200) {
                        var defaultAvatar = res.data.avatar;
                        currentAvatar = defaultAvatar;
                        $('#profileAvatarPreview').attr('src', defaultAvatar).attr('layer-src', defaultAvatar);
                        $('#profileAvatarUrl').val('');
                        $('.admin-avatar').html('<img src="' + defaultAvatar + '" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">');
                        layer.msg(res.msg);
                        updateCsrf(res.data.csrf_token);
                    } else {
                        layer.msg(res.msg);
                    }
                },
                error: function () {
                    layer.msg('网络错误，请重试');
                },
                complete: function () {
                    $btn.prop('disabled', false);
                }
            });
        });

    });

})

</script>
