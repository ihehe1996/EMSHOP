<?php

/**
 * 新后台登录页（/admin/sign）。
 *
 * 不引用旧后台的 layui / admin.css，过渡期自成一体；表单 POST 回本页，
 * 成功后由控制器 302 到 /admin/home。
 *
 * @var string $siteName
 * @var string $csrfToken
 * @var string $error
 * @var string $account
 * @var bool   $remember
 */

$siteTitle = $siteName !== '' ? $siteName : 'EMSHOP';
$safeAccount = htmlspecialchars($account, ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>管理员登录 · <?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') ?></title>
<style>
*{box-sizing:border-box;}
body{margin:0;min-height:100vh;background:linear-gradient(160deg,#eef2ff 0%,#f8fafc 55%,#f3f4f6 100%);color:#111827;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;display:flex;align-items:center;justify-content:center;padding:24px 16px;}
.card{width:100%;max-width:400px;background:#fff;border-radius:16px;padding:36px 30px;box-shadow:0 18px 48px rgba(15,23,42,.10);}
.brand{margin:0 0 6px;font-size:20px;font-weight:600;text-align:center;}
.sub{margin:0 0 26px;font-size:13px;color:#6b7280;text-align:center;}
.err{margin:0 0 16px;padding:10px 12px;border-radius:8px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;font-size:13px;line-height:1.6;}
.field{margin-bottom:16px;}
.label{display:block;margin-bottom:6px;font-size:13px;color:#374151;}
.input{width:100%;height:42px;padding:0 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;color:#111827;background:#fff;outline:none;transition:border-color .15s,box-shadow .15s;}
.input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.15);}
.row{display:flex;align-items:center;gap:8px;margin:2px 0 22px;font-size:13px;color:#4b5563;}
.row input{width:15px;height:15px;}
.btn{width:100%;height:44px;border:0;border-radius:8px;background:#4f46e5;color:#fff;font-size:15px;font-weight:500;cursor:pointer;transition:background-color .15s;}
.btn:hover{background:#4338ca;}
@media (max-width:420px){.card{padding:30px 22px;}.brand{font-size:18px;}}
</style>
</head>
<body>
<div class="card">
    <h1 class="brand"><?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="sub">管理员登录</p>

    <?php if ($error !== ''): ?>
        <div class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" action="/admin/sign" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <div class="field">
            <label class="label" for="account">账号</label>
            <input class="input" type="text" name="account" id="account" value="<?= $safeAccount ?>" placeholder="账号、邮箱或手机号" autocomplete="username" required>
        </div>

        <div class="field">
            <label class="label" for="password">密码</label>
            <input class="input" type="password" name="password" id="password" placeholder="登录密码" autocomplete="current-password" required>
        </div>

        <label class="row">
            <input type="checkbox" name="remember" value="1"<?= $remember ? ' checked' : '' ?>>
            <span>记住登录</span>
        </label>

        <button class="btn" type="submit">登录</button>
    </form>
</div>
</body>
</html>
