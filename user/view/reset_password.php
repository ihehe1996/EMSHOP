<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

/**
 * 重置密码（通过邮件链接进入）——公共视图，全站共用一份。
 *
 * 与登录 / 注册 / 找回密码同一套：不走主题（自带完整 HTML 骨架，见同目录 _auth_shell.php）。
 * 邮件链接里的 token 与「链接是否还有效」在这里自己取（PasswordResetService 是核心服务）。
 */

$csrf_token = Csrf::token();
$token      = trim((string) Dispatcher::getInstance()->getArg('token', ''));
$tokenValid = (new PasswordResetService())->validateToken($token) !== null;

ob_start();
if ($tokenValid):
?>
<form id="resetForm" class="auth-form" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <div>
        <label class="auth-label" for="resetPassword">新密码</label>
        <div class="auth-input-wrap">
            <input type="password" id="resetPassword" name="password" class="auth-input"
                   placeholder="至少6位" autocomplete="new-password" required>
            <button type="button" class="auth-eye" tabindex="-1" aria-label="显示或隐藏密码"><i class="fa fa-eye-slash"></i></button>
        </div>
    </div>
    <div>
        <label class="auth-label" for="resetPasswordConfirm">确认密码</label>
        <div class="auth-input-wrap">
            <input type="password" id="resetPasswordConfirm" name="password_confirm" class="auth-input"
                   placeholder="再次输入密码" autocomplete="new-password" required>
            <button type="button" class="auth-eye" tabindex="-1" aria-label="显示或隐藏密码"><i class="fa fa-eye-slash"></i></button>
        </div>
    </div>
    <button type="submit" class="auth-submit" id="resetBtn">确认重置</button>
</form>

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

    // 提交
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
                    layer.msg(res.msg || '重置成功');
                    // 提示一闪即走，回登录页用新密码登录
                    setTimeout(function () { location.href = '?c=login'; }, 1200);
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
<?php
else:
?>
<p class="auth-desc auth-desc--danger">链接无效或已过期，请重新申请。</p>
<?php
endif;
$auth_form_html = ob_get_clean();

$auth_title    = '重置密码';
$auth_desc     = $tokenValid ? '请设置新的登录密码。' : '';
$auth_pill     = '账号找回';
$auth_headline = '设置新密码';
$auth_blurb    = '设置新密码后即可用新密码登录。';
$auth_footer_html = $tokenValid
    ? '<a href="?c=login">返回登录</a>'
    : '<a href="?c=login&a=forgot">重新申请重置链接</a>';

include __DIR__ . '/_auth_shell.php';
