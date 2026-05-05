<?php
defined('EM_ROOT') || exit('access denied!');
?>
<!-- 密码验证页模板（用于查看受保护内容） -->
<style>
.password-page { background: #fff; border-radius: 12px; padding: 60px 40px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.06); max-width: 500px; margin: 60px auto; }
.password-icon { font-size: 64px; margin-bottom: 20px; }
.password-title { font-size: 22px; font-weight: 600; color: #333; margin-bottom: 8px; }
.password-desc { font-size: 14px; color: #999; margin-bottom: 24px; }
.password-input { width: 100%; padding: 12px 16px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; outline: none; margin-bottom: 12px; text-align: center; }
.password-input:focus { border-color: #e63946; }
</style>

<div class="password-page">
<div class="password-icon">&#128274;</div>
<div class="password-title">此内容受密码保护</div>
<div class="password-desc">请输入密码查看内容</div>
<form method="post">
    <input type="password" name="password" class="password-input" placeholder="请输入访问密码" required>
    <button type="submit" class="btn btn-primary" style="width:100%; padding:12px;">验证密码</button>
</form>
<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
<div style="margin-top:12px; color:#e63946; font-size:14px;">密码错误，请重试</div>
<?php endif; ?>
</div>
