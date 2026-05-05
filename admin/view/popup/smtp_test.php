<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
/** @var array{from_email:string,from_name:string,host:string,password:string,port:string} $smtpTestCfg */
/** @var string $csrfToken */

$esc = static function (?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
$pageTitle = '发送测试邮件';
include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <form class="layui-form" id="smtpTestForm" autocomplete="off">
        <input type="hidden" name="_action" value="smtp_test">
        <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
        <!-- smtp 配置来自调用方（邮箱配置页当前表单）用 URL 带进来，再通过隐藏字段随 POST 一起发回 -->
        <input type="hidden" name="from_email" value="<?= $esc($smtpTestCfg['from_email']) ?>">
        <input type="hidden" name="from_name"  value="<?= $esc($smtpTestCfg['from_name']) ?>">
        <input type="hidden" name="host"       value="<?= $esc($smtpTestCfg['host']) ?>">
        <input type="hidden" name="password"   value="<?= $esc($smtpTestCfg['password']) ?>">
        <input type="hidden" name="port"       value="<?= $esc($smtpTestCfg['port']) ?>">

        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">接收邮箱</label>
                <div class="layui-input-block">
                    <input type="email" class="layui-input" name="to" id="smtpTestTo"
                           placeholder="如：test@example.com" autofocus>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="popup-footer">
    <button type="button" class="em-btn em-reset-btn" id="smtpTestCancelBtn"><i class="fa fa-times"></i>取消</button>
    <button type="button" class="em-btn em-save-btn" id="smtpTestSendBtn"><i class="fa fa-paper-plane"></i>发送测试</button>
</div>

<script>
layui.use(['layer', 'form'], function () {
    var $ = layui.$, layer = layui.layer;

    $('#smtpTestCancelBtn').on('click', function () {
        var idx = parent.layer.getFrameIndex(window.name);
        parent.layer.close(idx);
    });

    $('#smtpTestSendBtn').on('click', function () {
        var $btn = $(this);
        var $icon = $btn.find('i');
        var to = $.trim($('#smtpTestTo').val() || '');
        if (to === '') {
            // layer.msg 不设置 icon
            layer.msg('请填写接收邮箱');
            $('#smtpTestTo').focus();
            return;
        }
        // 发送中只换图标不改文字；图标用 fa-refresh + admin-spin，与保存按钮保持统一
        $btn.prop('disabled', true).addClass('is-loading');
        $icon.attr('class', 'fa fa-refresh admin-spin');

        $.ajax({
            url: '/admin/settings.php',
            type: 'POST',
            dataType: 'json',
            data: $('#smtpTestForm').serialize(),
            timeout: 30000
        }).done(function (res) {
            // 成功/失败都停留在弹窗，用户自己关；仅以 msg 提示
            layer.msg((res && res.msg) || (res && res.code === 200 ? '发送成功，请查收' : '发送失败'));
        }).fail(function () {
            layer.msg('网络异常，请稍后重试');
        }).always(function () {
            $btn.prop('disabled', false).removeClass('is-loading');
            $icon.attr('class', 'fa fa-paper-plane');
        });
    });
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
