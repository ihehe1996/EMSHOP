<?php

/**
 * 新后台首页（过渡期最小页面）。
 *
 * 仅验证路由、登录守卫与视图渲染，不引用旧后台的 CSS/JS。
 *
 * @var string               $siteName
 * @var array<string, mixed> $user
 */

$adminName = trim((string) ($user['username'] ?? ''));
$adminName = $adminName !== '' ? $adminName : '管理员';
$siteTitle = $siteName !== '' ? $siteName : 'EMSHOP';
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>后台 · <?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') ?></title>
<style>
body{margin:0;background:#f3f4f6;color:#111827;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;}
.wrap{max-width:720px;margin:0 auto;padding:64px 20px;}
.card{background:#fff;border-radius:14px;padding:36px 32px;box-shadow:0 10px 30px rgba(15,23,42,.06);}
.tag{display:inline-block;margin-bottom:16px;padding:4px 10px;border-radius:999px;background:#eef2ff;color:#4f46e5;font-size:12px;}
h1{margin:0 0 10px;font-size:20px;}
p{margin:0 0 10px;color:#4b5563;font-size:14px;line-height:1.8;}
.links{margin-top:22px;padding-top:18px;border-top:1px solid #f1f5f9;font-size:13px;}
.links a{color:#2563eb;text-decoration:none;margin-right:18px;}
.links a:hover{text-decoration:underline;}
</style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <span class="tag">新后台路由</span>
        <h1><?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') ?> 后台</h1>
        <p>你好，<?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?>。新后台路由与登录守卫已经接通。</p>
        <p>当前是过渡期最小页面，布局与菜单会在后续迁移中逐步替换。</p>
        <div class="links">
            <a href="/admin/home">新后台首页</a>
            <a href="/admin/index.php">旧后台</a>
        </div>
    </div>
</div>
</body>
</html>
