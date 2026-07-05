<?php

declare(strict_types=1);

if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

$pageTitle = '需要重启 Swoole';

include __DIR__ . '/header.php';
?> 

<div class="popup-inner">
    <div class="form-tips form-tips--warn">
        <strong>为什么需要配置伪静态</strong>
        <p>伪静态主要用于支付后的回调与路由处理。未配置时，客户支付成功后，订单可能显示为“未支付”，或者跳转到 404 页面。</p>
    </div>

    <div class="rewrite-guide">
        <div class="rewrite-guide__title">推荐配置方式（Nginx）</div>

        <div class="rewrite-guide__step">
            <div class="rewrite-guide__step-title">1. 在站点配置中添加以下规则</div>
            <pre><code>location / {
    try_files $uri $uri/ /index.php$is_args$args;
}</code></pre>
        </div>

        <div class="rewrite-guide__step">
            <div class="rewrite-guide__step-title">2. 宝塔面板操作入口</div>
            <p>宝塔面板 → 网站 → 设置 → 伪静态 → 填写伪静态 → 保存</p>
        </div>

        <div class="rewrite-guide__note">
            <i class="fa fa-info-circle"></i>
            <span>配置完成后，建议重启 Nginx 或重新加载站点配置。</span>
        </div>
    </div>
</div>

<div class="popup-footer popup-footer--single">
    <button type="button" class="popup-btn popup-btn--primary" id="swooleRestartCloseBtn"><i class="fa fa-check mr-5"></i>我知道了</button>
</div>

<style>
body.popup-body { background: #fff; }

.form-tips--warn {
    background: #fff7ed;
    border-color: #fed7aa;
    color: #78350f;
}
.form-tips--warn strong {
    color: #9a3412;
    display: block;
    margin-bottom: 6px;
}
.form-tips--warn p {
    margin: 0;
    line-height: 1.6;
}

.rewrite-guide {
    margin-top: 16px;
    padding: 16px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}
.rewrite-guide__title {
    font-size: 14px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 12px;
}
.rewrite-guide__step + .rewrite-guide__step {
    margin-top: 12px;
}
.rewrite-guide__step-title {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}
.rewrite-guide__step p {
    margin: 0;
    color: #4b5563;
    line-height: 1.6;
}
.rewrite-guide__step pre {
    margin: 0;
    padding: 12px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    overflow-x: auto;
}
.rewrite-guide__step code {
    font-family: Menlo, Consolas, Monaco, monospace;
    font-size: 13px;
    color: #111827;
    white-space: pre;
}
.rewrite-guide__note {
    margin-top: 12px;
    padding: 10px 12px;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    border-radius: 6px;
    color: #4338ca;
    display: flex;
    align-items: center;
    gap: 8px;
}
.rewrite-guide__note i {
    font-size: 14px;
}
</style>

<script>
$(function () {
    $('#swooleRestartCloseBtn').on('click', function () {
        var idx = parent.layer.getFrameIndex(window.name);
        parent.layer.close(idx);
    });
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
