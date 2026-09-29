<?php
/**
 * 核心视图 - 重置密码（通过邮件链接进入）
 */
$tokenValid = !empty($token_valid);
?>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <h2 class="auth-title">重置密码</h2>
            <?php if ($tokenValid): ?>
            <p class="auth-subtitle">请设置新的登录密码</p>
            <?php else: ?>
            <p class="auth-subtitle" style="color:#e63946;">链接无效或已过期</p>
            <?php endif; ?>
        </div>

        <?php if ($tokenValid): ?>
        <form id="resetForm" class="auth-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <div class="auth-field">
                <label class="auth-label">新密码</label>
                <div class="auth-input-wrap">
                    <i class="fa fa-lock"></i>
                    <input type="password" name="password" placeholder="至少6位" autocomplete="new-password" required>
                    <button type="button" class="auth-eye" tabindex="-1"><i class="fa fa-eye-slash"></i></button>
                </div>
            </div>
            <div class="auth-field">
                <label class="auth-label">确认密码</label>
                <div class="auth-input-wrap">
                    <i class="fa fa-lock"></i>
                    <input type="password" name="password_confirm" placeholder="再次输入密码" autocomplete="new-password" required>
                    <button type="button" class="auth-eye" tabindex="-1"><i class="fa fa-eye-slash"></i></button>
                </div>
            </div>
            <button type="submit" class="auth-submit" id="resetBtn">确认重置</button>
        </form>
        <?php else: ?>
        <div class="auth-footer" style="margin-top:0;">
            <a href="?c=login&a=forgot" data-pjax>重新申请重置链接</a>
            <span style="margin:0 8px;color:#ddd;">|</span>
            <a href="?c=login" data-pjax>返回登录</a>
        </div>
        <?php endif; ?>

        <?php if ($tokenValid): ?>
        <div class="auth-footer">
            <a href="?c=login" data-pjax>返回登录</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($tokenValid): ?>
<script>
(function () {
    $('.auth-eye').on('click', function () {
        var $input = $(this).siblings('input');
        var $icon = $(this).find('i');
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $icon.removeClass('fa-eye-slash').addClass('fa-eye');
        } else {
            $input.attr('type', 'password');
            $icon.removeClass('fa-eye').addClass('fa-eye-slash');
        }
    });

    $('#resetForm').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#resetBtn');
        if ($btn.hasClass('is-loading')) return;

        var password = $('input[name="password"]').val();
        var confirm = $('input[name="password_confirm"]').val();
        if (password.length < 6) {
            layer.msg('密码长度不能少于 6 位');
            return;
        }
        if (password !== confirm) {
            layer.msg('两次输入的密码不一致');
            return;
        }

        $btn.addClass('is-loading').text('提交中...');

        $.ajax({
            url: '?c=login&a=reset',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.code === 200) {
                    layer.msg(res.msg || '重置成功', { time: 2000 }, function () {
                        location.href = '?c=login';
                    });
                } else {
                    layer.msg(res.msg || '重置失败');
                    $btn.removeClass('is-loading').text('确认重置');
                }
            },
            error: function () {
                layer.msg('网络异常，请稍后重试');
                $btn.removeClass('is-loading').text('确认重置');
            }
        });
    });
})();
</script>
<?php endif; ?>
