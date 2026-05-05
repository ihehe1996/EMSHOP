<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台登录 - <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/content/static/lib/layui-v2.13.5/layui/css/layui.css">
    <link rel="stylesheet" href="/admin/static/css/reset-layui.css">
    <link rel="stylesheet" href="/admin/static/css/sign.css">
    <?php doAction('admin_sign_head'); ?>
</head>
<body>
<div class="sign-page">
    <div class="sign-cover">
        <div class="sign-cover__inner">
            <p class="sign-cover__tag"><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> 后台管理面板</p>
            <h1>欢迎回到管理后台</h1>
            <p>专注管理与运营，让每一次登录都更轻松。</p>
        </div>
    </div>
    <div class="sign-panel">
        <div class="sign-card layui-anim layui-anim-upbit">
            <div class="sign-brand"><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></div>
            <h2>管理员登录</h2>
            <p class="sign-subtitle">请输入管理员账号继续操作</p>
            <form class="layui-form" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="layui-form-item">
                    <label class="sign-label" for="account">账号</label>
                    <input type="text" name="account" id="account" lay-verify="required" autocomplete="username" placeholder="请输入管理员账号、邮箱或手机号" class="layui-input">
                </div>
                <div class="layui-form-item">
                    <label class="sign-label" for="password">密码</label>
                    <input type="password" name="password" id="password" lay-verify="required" autocomplete="current-password" placeholder="请输入登录密码" class="layui-input">
                </div>
                <div class="sign-options">
                    <input type="checkbox" name="remember" value="1" title="记住登录" lay-skin="primary">
                </div>
                <button type="submit" class="layui-btn layui-btn-fluid sign-submit">立即登录</button>
            </form>
        </div>
    </div>
</div>
<script src="/content/static/lib/jquery.min.3.5.1.js"></script>
<script>
if (typeof jQuery !== 'undefined' && typeof jQuery.trim !== 'function') {
    jQuery.trim = function (value) {
        return value == null ? '' : String(value).trim();
    };
}
</script>
<script src="/content/static/lib/layui-v2.13.5/layui/layui.js"></script>
<script src="/admin/static/js/sign.js"></script>
<?php doAction('admin_sign_footer'); ?>
</body>
</html>
