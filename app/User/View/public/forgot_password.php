<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

/**
 * 找回密码（申请重置邮件）——公共视图，全站共用一份。
 *
 * 与登录 / 注册同一套：不走主题（自带完整 HTML 骨架，见同目录 _auth_shell.php），
 * 令牌与验证码算式在这里自己从核心读，不依赖主题 module.php。
 */

$csrf_token   = Csrf::token();
$captcha_expr = Captcha::issue('forgot_password');

ob_start();
?>
<form id="forgotForm" class="auth-form" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <div>
        <label class="auth-label" for="forgotEmail">注册邮箱</label>
        <input type="email" id="forgotEmail" name="email" class="auth-input"
               placeholder="请输入注册时使用的邮箱" autocomplete="email" required>
    </div>
    <div>
        <label class="auth-label" for="forgotCaptcha">验证码</label>
        <div class="auth-captcha">
            <span class="auth-captcha__expr" id="captchaExpr"><?= htmlspecialchars($captcha_expr) ?> = ?</span>
            <input type="text" id="forgotCaptcha" name="captcha" class="auth-captcha__input"
                   placeholder="算出结果" maxlength="3" inputmode="numeric" autocomplete="off" required>
            <button type="button" class="auth-captcha__refresh" id="captchaRefresh" title="换一题" tabindex="-1">
                <i class="fa fa-refresh"></i>
            </button>
        </div>
    </div>
    <button type="submit" class="auth-submit" id="forgotBtn">发送重置链接</button>
</form>

<script>
(function () {
    // 换一题
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

    // 提交
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
                    layer.msg(res.msg || '发送成功');
                    $btn.removeClass('is-loading').text('发送重置链接');
                } else {
                    layer.msg(res.msg || '发送失败');
                    // 失败时后端会带上新的算式，同步刷新（验证码一次性）
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
<?php
$auth_form_html = ob_get_clean();

$auth_title    = '找回密码';
$auth_desc     = '输入注册邮箱，我们将发送重置链接。';
$auth_pill     = '账号找回';
$auth_headline = '找回你的账号';
$auth_blurb    = '输入注册时用的邮箱，我们会发一条重置链接给你。';
$auth_footer_html = '想起密码了？<a href="?c=login">返回登录</a>';

include __DIR__ . '/_auth_shell.php';
