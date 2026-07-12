<?php

declare(strict_types=1);

if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

$pageTitle = 'Swoole 服务说明';

$esc = static function (?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
 
$videoUrl = 'https://www.bilibili.com/video/BV1XdV96rEKr';

include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <div class="form-tips form-tips--warn">
        <strong>服务未运行，订单无法自动发货。</strong>
        Swoole 负责订单自动发货、发货队列与定时任务，属于必启组件。未启动时，买家付款后订单将停留在待发货状态。
    </div>

    <div class="popup-section">
        <div class="swoole-guide__title">快速启用步骤</div>
        <ol class="swoole-guide__steps">
            <li>在 PHP CLI 环境安装 <strong>Swoole 4.x</strong> 扩展，建议使用 PHP 7.4 及以上版本。</li>
            <li>在宝塔「进程守护管理器」或同类工具中添加守护进程，启动命令见下方。</li>
            <li>启动成功后返回后台首页，Swoole 服务卡片应显示为「运行中」。</li>
        </ol>
    </div>

    <div class="popup-section">
        <div class="swoole-guide__title">守护进程启动命令</div>
        <div class="layui-form-mid layui-word-aux" style="margin: 10px 0 8px; padding-left: 0;">
            默认命令php使用的是默认的php cli版本，您可以在命令行中使用php -v查看当前cli使用的php版本
        </div>
        <div class="swoole-guide__cmd">
            <span class="swoole-guide__cmd-label">默认命令（项目根目录执行）</span>
            <code>php server</code>
        </div>
        <div class="layui-form-mid layui-word-aux" style="margin: 10px 0 8px; padding-left: 0;">
            若 Swoole 安装在指定 PHP 版本上，请改用对应命令，例如 PHP 8.2：
        </div>
        <div class="swoole-guide__cmd">
            <span class="swoole-guide__cmd-label">指定 PHP 版本示例</span>
            <code>php82 server</code>
        </div>
    </div>

    <div class="popup-section" style="margin-bottom: 0;">
        <div class="swoole-guide__title">视频教程</div>
        <a href="<?= $esc($videoUrl) ?>" target="_blank" rel="noopener noreferrer" class="swoole-guide__link">
            <span class="swoole-guide__link-icon"><i class="fa fa-play"></i></span>
            <span class="swoole-guide__link-body">
                <span class="swoole-guide__link-title">查看完整安装与配置教程</span>
                <span class="swoole-guide__link-url"><?= $esc($videoUrl) ?></span>
            </span>
            <i class="fa fa-external-link swoole-guide__link-arrow"></i>
        </a>
    </div>
</div>

<div class="popup-footer popup-footer--single">
    <button type="button" class="popup-btn popup-btn--primary" id="swooleGuideCloseBtn"><i class="fa fa-check mr-5"></i>我知道了</button>
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

.swoole-guide__steps {
    margin: 0;
    padding-left: 20px;
    font-size: 13px;
    color: #4b5563;
    line-height: 1.75;
}
.swoole-guide__steps li + li {
    margin-top: 6px;
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

.swoole-guide__link {
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
.swoole-guide__link:hover {
    border-color: #c7d2fe;
    background: #faf8ff;
}
.swoole-guide__link-icon {
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
.swoole-guide__link-body {
    flex: 1;
    min-width: 0;
}
.swoole-guide__link-title {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 2px;
}
.swoole-guide__link-url {
    display: block;
    font-size: 12px;
    color: #6366f1;
    font-family: Menlo, Consolas, Monaco, monospace;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.swoole-guide__link-arrow {
    flex-shrink: 0;
    font-size: 12px;
    color: #cbd5e1;
    transition: color 0.15s ease, transform 0.15s ease;
}
.swoole-guide__link:hover .swoole-guide__link-arrow {
    color: #6366f1;
    transform: translateX(2px);
}
</style>

<script>
$(function () {
    $('#swooleGuideCloseBtn').on('click', function () {
        var idx = parent.layer.getFrameIndex(window.name);
        parent.layer.close(idx);
    });
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
 