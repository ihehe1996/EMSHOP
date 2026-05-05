<?php
/**
 * 测试模板 - 登录页
 */
?>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <h2 class="auth-title">欢迎回来</h2>
            <p class="auth-subtitle">登录你的账号</p>
        </div>
        <form id="loginForm" class="auth-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="auth-field">
                <label class="auth-label">账号</label>
                <div class="auth-input-wrap">
                    <i class="fa fa-user"></i>
                    <input type="text" name="account" placeholder="账号 / 手机号 / 邮箱" autocomplete="username" required>
                </div>
            </div>
            <div class="auth-field">
                <label class="auth-label">密码</label>
                <div class="auth-input-wrap">
                    <i class="fa fa-lock"></i>
                    <input type="password" name="password" placeholder="请输入密码" autocomplete="current-password" required>
                    <button type="button" class="auth-eye" tabindex="-1"><i class="fa fa-eye-slash"></i></button>
                </div>
            </div>
            <button type="submit" class="auth-submit" id="loginBtn">登 录</button>
        </form>
        <div class="auth-footer">
            <span>还没有账号？</span>
            <a href="?c=register" data-pjax>立即注册</a>
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

    // 登录提交
    $('#loginForm').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#loginBtn');
        if ($btn.hasClass('is-loading')) return;

        $btn.addClass('is-loading').text('登录中...');

        $.ajax({
            url: '?c=login',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.code === 200) {
                    location.href = '?';
                } else {
                    layer.msg(res.msg || '登录失败');
                    $btn.removeClass('is-loading').text('登 录');
                }
            },
            error: function () {
                layer.msg('网络异常，请稍后重试');
                $btn.removeClass('is-loading').text('登 录');
            }
        });
    });
})();
</script>
