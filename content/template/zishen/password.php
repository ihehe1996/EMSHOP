<?php
defined('EM_ROOT') || exit('access denied!');
?>
<!-- 密码验证页模板（用于查看受保护内容） -->
<div class="page-body zs-password-wrap">
<div class="password-page">
<div class="password-icon">&#128274;</div>
<div class="password-title">此内容受密码保护</div>
<div class="password-desc">请输入密码查看此内容</div>
<form method="post">
    <input type="password" name="password" class="password-input" placeholder="请输入访问密码" required>
    <button type="submit" class="btn btn-primary" style="width:100%; padding:12px;">验证密码</button>
</form>
<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
<div class="password-error">密码错误，请重试</div>
<?php endif; ?>
</div>
</div>
