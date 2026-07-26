<?php

declare(strict_types=1);

if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

$pageTitle = '????????';

include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <div class="form-tips form-tips--warn">
        <strong>???????????????????</strong>
        ?????????????????????? 2 ???????
        <strong>???3???????????????</strong>
    </div>

    <div class="popup-section">
        <div class="server-guide__title">????????</div>
        <div class="layui-word-aux" style="margin: 0 0 8px;">
            ?? <code>php</code> ? CLI ??????? <code>php -v</code> ??????????
        </div>
        <div class="server-guide__cmd">
            <span class="server-guide__cmd-label">???????????</span>
            <code>php server start</code>
        </div>
        <div class="server-guide__cmd">
            <span class="server-guide__cmd-label">?? PHP ?????PHP 8.2?</span>
            <code>php82 server start</code>
        </div>
        <div class="layui-word-aux" style="margin-top: 10px;">
            ????????????????????????????????
        </div>
    </div>
</div>

<div class="popup-footer popup-footer--single">
    <button type="button" class="popup-btn popup-btn--primary" id="serverRestartCloseBtn"><i class="fa fa-check mr-5"></i>????</button>
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

.server-guide__title {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 10px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.server-guide__title::before {
    content: '';
    width: 3px;
    height: 12px;
    background: linear-gradient(180deg, #6366f1, #8b5cf6);
    border-radius: 2px;
}

.server-guide__cmd {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 10px 12px;
}
.server-guide__cmd + .server-guide__cmd {
    margin-top: 8px;
}
.server-guide__cmd-label {
    display: block;
    font-size: 12px;
    color: #9ca3af;
    margin-bottom: 6px;
}
.server-guide__cmd code {
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
    $('#serverRestartCloseBtn').on('click', function () {
        var idx = parent.layer.getFrameIndex(window.name);
        parent.layer.close(idx);
    });
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
