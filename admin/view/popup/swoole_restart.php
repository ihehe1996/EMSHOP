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
        <strong>本次升级需重启 Swoole 后才会生效。</strong>
        请在宝塔「进程守护管理器」中停止服务，等待约 10 秒后再启动。
    </div>

    <div class="popup-section">
        <div class="swoole-guide__title">守护进程启动命令</div>
        <div class="layui-word-aux" style="margin: 0 0 8px;">
            默认 <code>php</code> 为 CLI 默认版本，可用 <code>php -v</code> 查看；若 Swoole 装在指定版本上，请改用对应命令。
        </div>
        <div class="swoole-guide__cmd">
            <span class="swoole-guide__cmd-label">默认命令（项目根目录）</span>
            <code>php server</code>
        </div>
        <div class="swoole-guide__cmd">
            <span class="swoole-guide__cmd-label">指定 PHP 版本示例（PHP 8.2）</span>
            <code>php82 server</code>
        </div>
        <div class="layui-word-aux" style="margin-top: 10px;">
            若守护进程仍使用旧版启动命令，请先停止并修改为上述命令后再启动。
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
}

.swoole-guide__title {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 10px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.swoole-guide__title::before {
    content: '';
    width: 3px;
    height: 12px;
    background: linear-gradient(180deg, #6366f1, #8b5cf6);
    border-radius: 2px;
}

.swoole-guide__cmd {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 10px 12px;
}
.swoole-guide__cmd + .swoole-guide__cmd {
    margin-top: 8px;
}
.swoole-guide__cmd-label {
    display: block;
    font-size: 12px;
    color: #9ca3af;
    margin-bottom: 6px;
}
.swoole-guide__cmd code {
    display: block;
    font-family: Menlo, Consolas, Monaco, monospace;
    font-size: 13px;
    color: #111827;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    padding: 8px 10px;
    word-break: break-all;
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
