<?php
defined('EM_ROOT') || exit('access denied!');
?>
<!-- 404 页面 -->
<div class="page-body">
    <div class="not-found">
        <div class="not-found-code">404</div>
        <div class="not-found-title"><?= htmlspecialchars($page_title ?: '页面不存在') ?></div>
        <div class="not-found-desc">
            您访问的页面不存在或已被删除
            <?php if (!empty($_404_reason)): ?>
            <br>原因：<?= htmlspecialchars($_404_reason) ?>
            <?php endif; ?>
        </div>
        <div style="display:flex; gap:12px; justify-content:center;">
            <a href="<?= url_home() ?>" data-pjax class="btn btn-primary">返回首页</a>
            <a href="<?= url_goods_list() ?>" data-pjax class="btn btn-outline">商品列表</a>
        </div>
    </div>
</div>
