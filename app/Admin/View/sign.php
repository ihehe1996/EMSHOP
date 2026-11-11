<?php

/**
 * 后台登录页（{入口}/sign，入口默认 /admin，可由 EM_APP_ADMIN_ENTRY 配置）。
 *
 * 表单由 content/static/admin/js/sign.js 以 AJAX 提交，成功跳后台首页；
 * 控制器对非 AJAX 的 POST 仍按普通表单处理（失败回显错误、成功 302）。
 *
 * @var string $siteName
 * @var string $csrfToken
 * @var string $error
 * @var string $account
 * @var bool   $remember
 */

if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

$siteTitle = $siteName !== '' ? $siteName : 'EMSHOP';
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台登录 - <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="icon" href="<?php echo htmlspecialchars(site_favicon_href(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="/content/static/lib/layui-v2.13.5/layui/css/layui.css">
    <link rel="stylesheet" href="/content/static/admin/css/reset-layui.css">
    <link rel="stylesheet" href="/content/static/admin/css/sign.css">
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
            <?php if ($error !== ''): ?>
                <p class="sign-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <form class="layui-form" id="loginForm" method="post" action="<?= EM_ADMIN_PATH ?>/sign">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="layui-form-item">
                    <label class="sign-label" for="account">账号</label>
                    <input type="text" name="account" id="account" lay-verify="required" autocomplete="username" placeholder="请输入管理员账号、邮箱或手机号" class="layui-input" value="<?= htmlspecialchars($account, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="layui-form-item">
                    <label class="sign-label" for="password">密码</label>
                    <input type="password" name="password" id="password" lay-verify="required" autocomplete="current-password" placeholder="请输入登录密码" class="layui-input">
                </div>
                <div class="sign-options">
                    <input type="checkbox" name="remember" value="1" title="记住登录" lay-skin="primary"<?= $remember ? ' checked' : '' ?>>
                </div>
                <button type="submit" class="layui-btn layui-btn-fluid sign-submit">立即登录</button>
            </form>
        </div>
    </div>
</div>
<script>window.EM_ADMIN_PATH = <?= json_encode(EM_ADMIN_PATH, JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="/content/static/lib/jquery.min.3.5.1.js"></script>
<script>
if (typeof jQuery !== 'undefined' && typeof jQuery.trim !== 'function') {
    jQuery.trim = function (value) {
        return value == null ? '' : String(value).trim();
    };
}
</script>
<script src="/content/static/lib/layui-v2.13.5/layui/layui.js"></script>
<script src="/content/static/admin/js/sign.js"></script>
<?php doAction('admin_sign_footer'); ?>
</body>
</html>
