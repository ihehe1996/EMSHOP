<?php

if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

$pageTitle = '运行模式说明';

$esc = static function (?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

$videoUrl = 'https://www.bilibili.com/video/BV1XdV96rEKr';

// 与首页运行模式卡片同一个判据：发货能力心跳新鲜 = CLI 模式（异步发货）。
// 这里是独立请求（iframe 加载），所以状态得自己算一遍。
$isCli   = WorkerHeartbeat::isAlive(WorkerHeartbeat::CAPABILITY_DELIVERY);
$hostAlive = WorkerHeartbeat::hostAlive();
$hostAge   = WorkerHeartbeat::hostAge();

// 已注册的后台任务：类型由插件通过 server_worker_types 过滤器提供，核心不写死。
// 没装这类插件时列表为空 —— 此时即便切到 CLI 模式也没有子进程可跑。
$workers = [];
try {
    foreach (CliServer::workerDefinitions() as $def) {
        $type = (string) $def['type'];
        $workers[] = [
            'type'   => $type,
            'label'  => (string) $def['label'],
            'plugin' => (string) ($def['plugin'] ?? ''),
            'alive'  => WorkerHeartbeat::isAlive($type),
            'age'    => WorkerHeartbeat::age($type),
        ];
    }
} catch (Throwable $e) {
    // 取不到 worker 列表不影响说明页渲染
}

include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <?php if ($isCli): ?>
    <div class="form-tips form-tips--ok">
        <strong>当前为 CLI 模式：订单异步发货。</strong>
        后台常驻进程正在消费发货队列，发货失败会自动重试。
    </div>
    <?php else: ?>
    <div class="form-tips form-tips--warn">
        <strong>当前为 FPM 模式：订单同步发货。</strong>
        订单在收到支付回调的那个请求里直接发货，不依赖常驻进程；但发货失败不会重试，
        失败订单会转为「发货失败」等待人工处理。
    </div>
    <?php endif; ?>

    <div class="popup-section">
        <div class="server-guide__title">两种模式</div>
        <div class="server-guide__modes">
            <div class="server-guide__mode <?= $isCli ? 'is-on' : '' ?>">
                <div class="server-guide__mode-head">
                    <span class="server-guide__mode-name">CLI 模式</span>
                    <?php if ($isCli): ?><span class="server-guide__mode-badge">当前</span><?php endif; ?>
                </div>
                <ul class="server-guide__mode-list">
                    <li>需要进程守护工具（Supervisor / 宝塔进程守护管理器）常驻 <code>php server</code>。</li>
                    <li>订单异步发货，失败自动重试。</li>
                    <li>只有这种模式能跑常驻能力：上游库存 / 价格同步、上游订单轮询等。</li>
                </ul>
            </div>
            <div class="server-guide__mode <?= !$isCli ? 'is-on' : '' ?>">
                <div class="server-guide__mode-head">
                    <span class="server-guide__mode-name">FPM 模式</span>
                    <?php if (!$isCli): ?><span class="server-guide__mode-badge">当前</span><?php endif; ?>
                </div>
                <ul class="server-guide__mode-list">
                    <li>无需任何常驻进程，装完即用。</li>
                    <li>订单在付款请求内同步发货，买家等待时间会随发货耗时增加。</li>
                    <li>发货失败不重试，转为「发货失败」需人工处理。</li>
                </ul>
            </div>
        </div>
        <div class="layui-form-mid layui-word-aux" style="margin: 10px 0 0; padding-left: 0;">
            系统会自动切换：CLI 模式的发货进程在线时走异步，否则自动退回同步，无需手动配置。
        </div>
    </div>

    <div class="popup-section">
        <div class="server-guide__title">后台任务</div>
        <?php if ($workers === []): ?>
        <div class="server-guide__empty">
            当前没有插件注册后台任务。切换运行模式不会有子进程启动 ——
            请先启用发货 / 同步类插件。
        </div>
        <?php else: ?>
        <div class="server-guide__workers">
            <?php foreach ($workers as $w): ?>
            <div class="server-guide__worker">
                <span class="server-guide__dot <?= $w['alive'] ? 'is-alive' : 'is-idle' ?>"></span>
                <span class="server-guide__worker-name"><?= $esc($w['label']) ?></span>
                <span class="server-guide__worker-type"><?= $esc($w['type']) ?></span>
                <span class="server-guide__worker-state <?= $w['alive'] ? 'is-alive' : 'is-idle' ?>">
                    <?php
                    if ($w['alive']) {
                        echo '运行中';
                    } elseif ($w['age'] === null) {
                        echo '未启动';
                    } else {
                        echo '心跳停滞 ' . (int) $w['age'] . ' 秒';
                    }
                    ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="server-guide__host">
            主进程（管家）：
            <span class="<?= $hostAlive ? 'is-alive' : 'is-idle' ?>">
                <?php
                if ($hostAlive) {
                    echo '运行中';
                } elseif ($hostAge === null) {
                    echo '未检测到';
                } else {
                    echo '心跳停滞 ' . (int) $hostAge . ' 秒';
                }
                ?>
            </span>
        </div>
    </div>

    <div class="popup-section">
        <div class="server-guide__title">如何启用 CLI 模式</div>
        <ol class="server-guide__steps">
            <li>在宝塔「进程守护管理器 / Supervisor」中添加守护进程，启动命令见下方（只需一条命令）。</li>
            <li>启动成功后返回后台首页，「运行模式」卡片应显示为「CLI 模式」。</li>
        </ol>
        <div class="layui-form-mid layui-word-aux" style="margin: 10px 0 8px; padding-left: 0;">
            使用 PHP CLI 执行；可用 <code>php -v</code> 查看当前 CLI 版本。主进程会按已启用插件注册的任务自动拉起对应子进程。
        </div>
        <div class="server-guide__cmd">
            <span class="server-guide__cmd-label">默认命令（项目根目录执行）</span>
            <code>php server</code>
        </div>
        <div class="layui-form-mid layui-word-aux" style="margin: 10px 0 8px; padding-left: 0;">
            若需指定 PHP 版本，请改用对应命令，例如 PHP 8.2：
        </div>
        <div class="server-guide__cmd">
            <span class="server-guide__cmd-label">指定 PHP 版本示例</span>
            <code>php82 server start</code>
        </div>
    </div>

    <div class="popup-section" style="margin-bottom: 0;">
        <div class="server-guide__title">视频教程</div>
        <a href="<?= $esc($videoUrl) ?>" target="_blank" rel="noopener noreferrer" class="server-guide__link">
            <span class="server-guide__link-icon"><i class="fa fa-play"></i></span>
            <span class="server-guide__link-body">
                <span class="server-guide__link-title">查看完整安装与配置教程</span>
                <span class="server-guide__link-url"><?= $esc($videoUrl) ?></span>
            </span>
            <i class="fa fa-external-link server-guide__link-arrow"></i>
        </a>
    </div>
</div>

<div class="popup-footer popup-footer--single">
    <button type="button" class="popup-btn popup-btn--primary" id="serverGuideCloseBtn"><i class="fa fa-check mr-5"></i>我知道了</button>
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
.form-tips--ok {
    background: #f0fdf4;
    border-color: #bbf7d0;
    color: #14532d;
}
.form-tips--ok strong {
    color: #166534;
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

/* ---------- 两种模式对比 ---------- */
.server-guide__modes {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}
.server-guide__mode {
    padding: 12px 14px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}
/* 当前生效的模式：描边高亮 */
.server-guide__mode.is-on {
    background: #fff;
    border-color: #c7d2fe;
    box-shadow: 0 2px 10px rgba(99, 102, 241, 0.12);
}
.server-guide__mode-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}
.server-guide__mode-name {
    font-size: 13px;
    font-weight: 600;
    color: #111827;
}
.server-guide__mode-badge {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 999px;
    background: #eef2ff;
    color: #4f46e5;
}
.server-guide__mode-list {
    margin: 0;
    font-size: 12.5px;
    color: #4b5563;
    line-height: 1.7;
}
.server-guide__mode-list li + li { margin-top: 4px; }
.server-guide__mode-list code {
    font-family: Menlo, Consolas, Monaco, monospace;
    font-size: 12px;
    background: #eef2ff;
    color: #4338ca;
    padding: 1px 5px;
    border-radius: 3px;
}

/* ---------- 后台任务列表 ---------- */
.server-guide__workers {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
}
.server-guide__worker {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 12px;
    font-size: 12.5px;
    background: #fff;
}
.server-guide__worker + .server-guide__worker { border-top: 1px dashed #f3f4f6; }
.server-guide__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
    background: #d1d5db;
}
.server-guide__dot.is-alive { background: #10b981; }
.server-guide__dot.is-idle { background: #f59e0b; }
.server-guide__worker-name {
    font-weight: 600;
    color: #111827;
}
.server-guide__worker-type {
    flex: 1;
    min-width: 0;
    font-family: Menlo, Consolas, Monaco, monospace;
    font-size: 11.5px;
    color: #9ca3af;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.server-guide__worker-state { flex-shrink: 0; color: #6b7280; }
.server-guide__worker-state.is-alive { color: #059669; }
.server-guide__worker-state.is-idle { color: #b45309; }
.server-guide__empty {
    padding: 14px 16px;
    background: #f8fafc;
    border: 1px dashed #e5e7eb;
    border-radius: 8px;
    font-size: 12.5px;
    color: #6b7280;
    line-height: 1.7;
}
.server-guide__host {
    margin-top: 8px;
    font-size: 12px;
    color: #9ca3af;
}
.server-guide__host .is-alive { color: #059669; }
.server-guide__host .is-idle { color: #b45309; }

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

/* 窄屏：两种模式改为上下排列 */
@media (max-width: 520px) {
    .server-guide__modes { grid-template-columns: 1fr; }
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
