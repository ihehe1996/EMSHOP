<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

/**
 * 登录页（公共视图，全站共用一份）。
 *
 * 不走主题：自带完整 HTML 骨架（见同目录 _auth_shell.php），不套主题 header/footer，
 * 也不依赖主题 module.php assign 的变量 —— 开关与令牌都在这里自己从核心读。
 *
 * 表单提交走 ?c=login POST（AJAX JSON，见下面的脚本）。
 */

$csrf_token            = Csrf::token();
$user_register_enabled = (string) Config::get('user_register', '0') === '1';

// 先把表单缓冲下来，最后连同标题一起交给外壳渲染
ob_start();
?>
<form id="loginForm" class="auth-form" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <div>
        <label class="auth-label" for="loginAccount">账号</label>
        <input type="text" id="loginAccount" name="account" class="auth-input"
               placeholder="账号 / 手机号 / 邮箱" autocomplete="username" required>
    </div>
    <div>
        <label class="auth-label" for="loginPassword">密码</label>
        <div class="auth-input-wrap">
            <input type="password" id="loginPassword" name="password" class="auth-input"
                   placeholder="请输入密码" autocomplete="current-password" required>
            <button type="button" class="auth-eye" tabindex="-1" aria-label="显示或隐藏密码"><i class="fa fa-eye-slash"></i></button>
        </div>
    </div>
    <div class="auth-row">
        <a class="auth-link" href="?c=login&a=forgot">忘记密码？</a>
    </div>
    <button type="submit" class="auth-submit" id="loginBtn">登 录</button>
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
<?php
$auth_form_html = ob_get_clean();

$auth_title  = '登录你的账号';
$auth_desc   = '用账号、手机号或邮箱登录。';
$auth_pill   = '用户入口';
$auth_footer_html = $user_register_enabled
    ? '还没有账号？<a href="?c=register">立即注册</a>'
    : '';

include __DIR__ . '/_auth_shell.php';
