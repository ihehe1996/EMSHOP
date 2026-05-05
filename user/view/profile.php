<?php
/**
 * 用户中心 - 个人资料
 */
$csrfToken = $csrfToken ?? Csrf::token();
?>
<div class="uc-page">
    <div class="uc-page-header">
        <h2 class="uc-page-title">个人资料</h2>
        <p class="uc-page-desc">管理你的账号信息</p>
    </div>

    <div class="uc-form-card">
        <!-- 头像 -->
        <div class="uc-form-avatar">
            <div class="uc-form-avatar-img">
                <?php if (!empty($frontUser['avatar'])): ?>
                <img src="<?= htmlspecialchars($frontUser['avatar']) ?>" alt="" id="avatarPreview">
                <?php else: ?>
                <span class="uc-form-avatar--default" id="avatarPreview"><i class="fa fa-user"></i></span>
                <?php endif; ?>
            </div>
            <div class="uc-form-avatar-info">
                <div class="uc-form-avatar-name"><?= htmlspecialchars($frontUser['nickname'] ?? $frontUser['username'] ?? '') ?></div>
                <div class="uc-form-avatar-hint">建议上传 200x200 像素的正方形图片</div>
            </div>
        </div>

        <!-- 资料表单 -->
        <form id="profileForm" class="uc-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="profile">

            <div class="uc-form-group">
                <label class="uc-form-label">账号</label>
                <div class="uc-form-control">
                    <input type="text" name="username" value="<?= htmlspecialchars($frontUser['username'] ?? '') ?>" readonly class="uc-input uc-input--readonly">
                    <span class="uc-form-hint">账号不可修改</span>
                </div>
            </div>

            <div class="uc-form-group">
                <label class="uc-form-label">昵称</label>
                <div class="uc-form-control">
                    <input type="text" name="nickname" value="<?= htmlspecialchars($frontUser['nickname'] ?? '') ?>" class="uc-input" placeholder="请输入昵称">
                </div>
            </div>

            <div class="uc-form-group">
                <label class="uc-form-label">邮箱</label>
                <div class="uc-form-control">
                    <input type="email" name="email" value="<?= htmlspecialchars($frontUser['email'] ?? '') ?>" class="uc-input" placeholder="请输入邮箱">
                </div>
            </div>

            <div class="uc-form-group">
                <label class="uc-form-label">手机号</label>
                <div class="uc-form-control">
                    <input type="tel" name="mobile" value="<?= htmlspecialchars($frontUser['mobile'] ?? '') ?>" class="uc-input" placeholder="请输入手机号">
                </div>
            </div>

            <div class="uc-form-group">
                <label class="uc-form-label"></label>
                <div class="uc-form-control">
                    <button type="submit" class="uc-btn uc-btn--primary" id="saveProfileBtn">保存修改</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    $('#profileForm').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#saveProfileBtn');
        if ($btn.hasClass('is-loading')) return;
        $btn.addClass('is-loading').text('保存中...');

        $.ajax({
            url: '/user/profile.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.code === 200) {
                    layui.layer.msg('保存成功');
                    if (res.data && res.data.csrf_token) {
                        $('input[name="csrf_token"]').val(res.data.csrf_token);
                    }
                } else {
                    layui.layer.msg(res.msg || '保存失败');
                }
                $btn.removeClass('is-loading').text('保存修改');
            },
            error: function () {
                layui.layer.msg('网络异常');
                $btn.removeClass('is-loading').text('保存修改');
            }
        });
    });
})();
</script>
