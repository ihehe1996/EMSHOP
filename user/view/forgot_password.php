<?php
/**
 * 核心视图 - 找回密码（申请重置邮件）
 * 位于 include/view/，各模板可通过主题内同名文件覆盖。
 */
?>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <h2 class="auth-title">找回密码</h2>
            <p class="auth-subtitle">输入注册邮箱，我们将发送重置链接</p>
        </div>
        <form id="forgotForm" class="auth-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="auth-field">
                <label class="auth-label">注册邮箱</label>
                <div class="auth-input-wrap">
                    <i class="fa fa-envelope"></i>
                    <input type="email" name="email" placeholder="请输入注册时使用的邮箱" autocomplete="email" required>
                </div>
            </div>
            <div class="auth-field">
                <label class="auth-label">验证码</label>
                <div class="auth-captcha">
                    <span class="auth-captcha__expr" id="captchaExpr"><?= htmlspecialchars($captcha_expr) ?> = ?</span>
                    <input type="text" name="captcha" class="auth-captcha__input" placeholder="算出结果" maxlength="3" inputmode="numeric" autocomplete="off" required>
                    <button type="button" class="auth-captcha__refresh" id="captchaRefresh" title="换一题" tabindex="-1">
                        <i class="fa fa-refresh"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="auth-submit" id="forgotBtn">发送重置链接</button>
        </form>
        <div class="auth-footer">
            <span>想起密码了？</span>
            <a href="?c=login" data-pjax>返回登录</a>
        </div>
    </div>
</div>

<style>
.auth-captcha {
    display: flex;
    align-items: center;
    gap: 10px;
    height: 44px;
    padding: 0 14px;
    border: 1px solid #e8e8e8;
    border-radius: 8px;
    background: #fafafa;
    transition: border-color .2s;
}
.auth-captcha:focus-within { border-color: #4e6ef2; background: #fff; }
.auth-captcha__expr {
    flex-shrink: 0;
    font-size: 15px;
    font-weight: 600;
    color: #333;
    user-select: none;
}
.auth-captcha__input {
    flex: 1;
    min-width: 0;
    border: none;
    background: transparent;
    font-size: 15px;
    outline: none;
}
.auth-captcha__refresh {
    flex-shrink: 0;
    border: none;
    background: none;
    color: #999;
    cursor: pointer;
    padding: 4px;
    font-size: 14px;
}
.auth-captcha__refresh:hover { color: #4e6ef2; }
</style>

<script>
(function () {
    $('#captchaRefresh').on('click', function () {
        $.post('?c=login&a=forgot', {
            action: 'refresh_captcha',
            csrf_token: $('input[name="csrf_token"]').val()
        }, function (res) {
            if (res.code === 200 && res.data && res.data.expr) {
                $('#captchaExpr').text(res.data.expr + ' = ?');
                $('input[name="captcha"]').val('').focus();
            }
        }, 'json');
    });

    $('#forgotForm').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#forgotBtn');
        if ($btn.hasClass('is-loading')) return;

        $btn.addClass('is-loading').text('发送中...');

        $.ajax({
            url: '?c=login&a=forgot',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.code === 200) {
                    layer.msg(res.msg || '发送成功', { time: 3000 });
                    $btn.removeClass('is-loading').text('发送重置链接');
                } else {
                    layer.msg(res.msg || '发送失败');
                    if (res.data && res.data.captcha_expr) {
                        $('#captchaExpr').text(res.data.captcha_expr + ' = ?');
                        $('input[name="captcha"]').val('');
                    }
                    $btn.removeClass('is-loading').text('发送重置链接');
                }
            },
            error: function () {
                layer.msg('网络异常，请稍后重试');
                $btn.removeClass('is-loading').text('发送重置链接');
            }
        });
    });
})();
</script>
