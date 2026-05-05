<?php
/**
 * 测试模板 - 注册页
 */
?>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <h2 class="auth-title">创建账号</h2>
            <p class="auth-subtitle">注册一个新账号</p>
        </div>
        <form id="registerForm" class="auth-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="auth-field">
                <label class="auth-label">账号</label>
                <div class="auth-input-wrap">
                    <i class="fa fa-user"></i>
                    <input type="text" name="username" placeholder="3-20位字母、数字、下划线或中文" autocomplete="username" required>
                </div>
            </div>
            <div class="auth-field">
                <label class="auth-label">手机号</label>
                <div class="auth-input-wrap">
                    <i class="fa fa-mobile" style="font-size:18px"></i>
                    <input type="tel" name="mobile" placeholder="请输入手机号码" autocomplete="tel" required>
                </div>
            </div>
            <div class="auth-field">
                <label class="auth-label">邮箱</label>
                <div class="auth-input-wrap">
                    <i class="fa fa-envelope"></i>
                    <input type="email" name="email" placeholder="请输入邮箱地址" autocomplete="email" required>
                </div>
            </div>
            <div class="auth-field">
                <label class="auth-label">密码</label>
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
            <button type="submit" class="auth-submit" id="registerBtn">注 册</button>
        </form>
        <div class="auth-footer">
            <span>已有账号？</span>
            <a href="?c=login" data-pjax>立即登录</a>
        </div>
    </div>
</div>

<script>
(function () {
    // 密码显示/隐藏
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

    // 注册提交
    $('#registerForm').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#registerBtn');
        if ($btn.hasClass('is-loading')) return;

        // 前端校验
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

        $btn.addClass('is-loading').text('注册中...');

        $.ajax({
            url: '?c=register',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.code === 200) {
                    location.href = '?';
                } else {
                    layer.msg(res.msg || '注册失败');
                    $btn.removeClass('is-loading').text('注 册');
                }
            },
            error: function () {
                layer.msg('网络异常，请稍后重试');
                $btn.removeClass('is-loading').text('注 册');
            }
        });
    });
})();
</script>
