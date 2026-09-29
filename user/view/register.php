<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

/**
 * 注册页（公共视图，全站共用一份）。
 *
 * 与登录页同理：不走主题，自带完整 HTML 骨架（见同目录 _auth_shell.php），
 * 开关、必填项与令牌都在这里自己从核心读，判断规则与 RegisterController 的校验一致。
 */

$csrf_token = Csrf::token();

// 需要填哪些字段（后台「注册必填项」，逗号分隔）
$regFields = array_filter(array_map('trim', explode(',', (string) Config::get('user_register_fields', 'mobile,email'))));

$user_login_enabled      = (string) Config::get('user_login', '1') === '1';
$register_require_mobile = in_array('mobile', $regFields, true);
$register_require_email  = in_array('email', $regFields, true);

ob_start();
?>
<form id="registerForm" class="auth-form" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <div>
        <label class="auth-label" for="regUsername">账号</label>
        <input type="text" id="regUsername" name="username" class="auth-input"
               placeholder="3-20位字母、数字或下划线" autocomplete="username" required>
    </div>
    <?php if ($register_require_mobile): ?>
    <div>
        <label class="auth-label" for="regMobile">手机号</label>
        <input type="tel" id="regMobile" name="mobile" class="auth-input"
               placeholder="请输入手机号码" autocomplete="tel" required>
    </div>
    <?php endif; ?>
    <?php if ($register_require_email): ?>
    <div>
        <label class="auth-label" for="regEmail">邮箱</label>
        <input type="email" id="regEmail" name="email" class="auth-input"
               placeholder="请输入邮箱地址" autocomplete="email" required>
    </div>
    <?php endif; ?>
    <div>
        <label class="auth-label" for="regPassword">密码</label>
        <div class="auth-input-wrap">
            <input type="password" id="regPassword" name="password" class="auth-input"
                   placeholder="至少6位" autocomplete="new-password" required>
            <button type="button" class="auth-eye" tabindex="-1" aria-label="显示或隐藏密码"><i class="fa fa-eye-slash"></i></button>
        </div>
    </div>
    <div>
        <label class="auth-label" for="regPasswordConfirm">确认密码</label>
        <div class="auth-input-wrap">
            <input type="password" id="regPasswordConfirm" name="password_confirm" class="auth-input"
                   placeholder="再次输入密码" autocomplete="new-password" required>
            <button type="button" class="auth-eye" tabindex="-1" aria-label="显示或隐藏密码"><i class="fa fa-eye-slash"></i></button>
        </div>
    </div>
    <button type="submit" class="auth-submit" id="registerBtn">注 册</button>
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

    // 注册提交
    $('#registerForm').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#registerBtn');
        if ($btn.hasClass('is-loading')) return;

        // 前端校验
        var username = $.trim($('input[name="username"]').val());
        var password = $('input[name="password"]').val();
        var confirm = $('input[name="password_confirm"]').val();
        if (username.length < 3 || username.length > 20) {
            layer.msg('账号长度为 3-20 个字符');
            return;
        }
        if (!/^[a-zA-Z0-9_]+$/.test(username)) {
            layer.msg('账号只能包含字母、数字和下划线，不能包含中文');
            return;
        }
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
<?php
$auth_form_html = ob_get_clean();

$auth_title    = '创建账号';
$auth_desc     = '注册后即可下单、查单与领取优惠券。';
$auth_pill     = '新用户注册';
$auth_headline = '注册一个新账号';
$auth_blurb    = '一个账号走通全站：下单、查单、优惠券与个人中心。';
$auth_footer_html = $user_login_enabled
    ? '已有账号？<a href="?c=login">立即登录</a>'
    : '';

include __DIR__ . '/_auth_shell.php';
