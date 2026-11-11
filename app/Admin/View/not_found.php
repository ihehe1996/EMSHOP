<?php

/**
 * 新后台 404 页面。
 */

$siteTitle = (string) \Config::get('sitename', 'EMSHOP');
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>页面不存在 · <?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') ?></title>
<style>
body{margin:0;background:#f3f4f6;color:#111827;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;}
.wrap{max-width:560px;margin:0 auto;padding:96px 20px;}
.card{background:#fff;border-radius:14px;padding:36px 32px;text-align:center;box-shadow:0 10px 30px rgba(15,23,42,.06);}
.code{font-size:40px;font-weight:700;color:#c7d2fe;margin-bottom:8px;}
h1{margin:0 0 10px;font-size:18px;}
p{margin:0 0 18px;color:#6b7280;font-size:14px;line-height:1.8;}
a{display:inline-block;padding:8px 18px;border-radius:8px;background:#4f46e5;color:#fff;font-size:13px;text-decoration:none;}
a:hover{background:#4338ca;}
</style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="code">404</div>
        <h1>后台页面不存在</h1>
        <p>请检查链接是否正确，或返回后台首页。</p>
        <a href="<?= EM_ADMIN_PATH ?>/home">返回后台首页</a>
    </div>
</div>
</body>
</html>
