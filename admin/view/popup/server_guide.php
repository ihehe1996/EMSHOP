<?php

declare(strict_types=1);

if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

$pageTitle = '????????';

$esc = static function (?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

$videoUrl = 'https://www.bilibili.com/video/BV1XdV96rEKr';

include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <div class="form-tips form-tips--warn">
        <strong>???????????????</strong>
        ??????????????????????????????????????????????????????
    </div>

    <div class="popup-section">
        <div class="server-guide__title">??????</div>
        <ol class="server-guide__steps">
            <li>???????? <strong>PHP CLI</strong>??? PHP 7.4 ????????????</li>
            <li>??????????? / Supervisor?????????????????????????</li>
            <li>????????????????????????????</li>
        </ol>
    </div>

    <div class="popup-section">
        <div class="server-guide__title">????????</div>
        <div class="layui-form-mid layui-word-aux" style="margin: 10px 0 8px; padding-left: 0;">
            ?? PHP CLI ????? <code>php -v</code> ???? CLI ???????????????????????
        </div>
        <div class="server-guide__cmd">
            <span class="server-guide__cmd-label">?????????????</span>
            <code>php server start</code>
        </div>
        <div class="layui-form-mid layui-word-aux" style="margin: 10px 0 8px; padding-left: 0;">
            ???? PHP ????????????? PHP 8.2?
        </div>
        <div class="server-guide__cmd">
            <span class="server-guide__cmd-label">?? PHP ????</span>
            <code>php82 server start</code>
        </div>
    </div>

    <div class="popup-section" style="margin-bottom: 0;">
        <div class="server-guide__title">????</div>
        <a href="<?= $esc($videoUrl) ?>" target="_blank" rel="noopener noreferrer" class="server-guide__link">
            <span class="server-guide__link-icon"><i class="fa fa-play"></i></span>
            <span class="server-guide__link-body">
                <span class="server-guide__link-title">???????????</span>
                <span class="server-guide__link-url"><?= $esc($videoUrl) ?></span>
            </span>
            <i class="fa fa-external-link server-guide__link-arrow"></i>
        </a>
    </div>
</div>

<div class="popup-footer popup-footer--single">
    <button type="button" class="popup-btn popup-btn--primary" id="serverGuideCloseBtn"><i class="fa fa-check mr-5"></i>????</button>
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

.server-guide__steps {
    margin: 0;
    padding-left: 20px;
    font-size: 13px;
    color: #4b5563;
    line-height: 1.75;
}
.server-guide__steps li + li {
    margin-top: 6px;
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

.server-guide__link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    text-decoration: none;
    transition: border-color 0.15s ease, background 0.15s ease;
}
.server-guide__link:hover {
    border-color: #c7d2fe;
    background: #faf8ff;
}
.server-guide__link-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: #6366f1;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 14px;
}
.server-guide__link-body {
    flex: 1;
    min-width: 0;
}
.server-guide__link-title {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 2px;
}
.server-guide__link-url {
    display: block;
    font-size: 12px;
    color: #6366f1;
    font-family: Menlo, Consolas, Monaco, monospace;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.server-guide__link-arrow {
    flex-shrink: 0;
    font-size: 12px;
    color: #cbd5e1;
    transition: color 0.15s ease, transform 0.15s ease;
}
.server-guide__link:hover .server-guide__link-arrow {
    color: #6366f1;
    transform: translateX(2px);
}
</style>

<script>
$(function () {
    $('#serverGuideCloseBtn').on('click', function () {
        var idx = parent.layer.getFrameIndex(window.name);
        parent.layer.close(idx);
    });
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
