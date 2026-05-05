<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$pageTitle = '修改密码';
$csrfToken = Csrf::token();

include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <div class="form-tips">
        为保障账号安全，修改密码前请验证当前登录密码。新密码长度不少于6位，建议包含字母与数字的组合。
    </div>

    <form id="pwdForm">
        <input type="hidden" name="action" value="password">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="form-group">
            <label class="form-group__label" for="oldPwd">当前密码</label>
            <input type="password" class="form-group__input" id="oldPwd" name="old_password" autocomplete="current-password" placeholder="请输入当前登录密码">
            <p class="form-group__error" id="oldPwdError"></p>
        </div>

        <div class="form-group">
            <label class="form-group__label" for="newPwd">新密码</label>
            <input type="password" class="form-group__input" id="newPwd" name="new_password" autocomplete="new-password" placeholder="请输入新密码，至少6位">
            <p class="form-group__help">密码长度不少于6位</p>
            <p class="form-group__error" id="newPwdError"></p>
        </div>

        <div class="form-group">
            <label class="form-group__label" for="confirmPwd">确认新密码</label>
            <input type="password" class="form-group__input" id="confirmPwd" name="confirm_password" autocomplete="new-password" placeholder="请再次输入新密码">
            <p class="form-group__error" id="confirmPwdError"></p>
        </div>
    </form>
</div>

<div class="popup-footer">
    <button type="button" class="popup-btn popup-btn--primary" id="submitBtn">确认修改</button>
</div>

<script>
$(function () {
    layui.use(["layer"], function () {
        var layer = layui.layer;

        $("#submitBtn").on("click", function () {
            var $form = $("#pwdForm");
            var $btn = $("#submitBtn");
            $btn.prop("disabled", true).text("修改中...");

            $.ajax({
                url: location.pathname,
                type: "POST",
                data: $form.serialize(),
                dataType: "json",
                success: function (res) {
                    if (res.code === 200) {
                        var msg = res.msg || "密码修改成功";
                        if (res.data && res.data.csrf_token) {
                            try { parent.updateCsrf(res.data.csrf_token); } catch(e) {}
                        }
                        var index = parent.layer.getFrameIndex(window.name);
                        parent.layer.msg(msg);
                        parent.layer.close(index);
                    } else {
                        layer.msg(res.msg);
                    }
                },
                error: function () {
                    layer.msg("网络错误，请重试");
                },
                complete: function () {
                    $btn.prop("disabled", false).text("确认修改");
                }
            });
        });
    });
});
</script>
<?php
include __DIR__ . '/footer.php';
