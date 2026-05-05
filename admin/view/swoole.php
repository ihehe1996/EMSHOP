<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
?>
<div class="admin-page">
    <h1 class="admin-page__title" style="margin-bottom:18px;">Swoole 监控</h1>

    <!-- 服务状态卡片 -->
    <div class="swoole-status-cards" id="swooleStatusCards">
        <div class="swoole-card">
            <div class="swoole-card__label">服务状态</div>
            <div class="swoole-card__value" id="srvStatus"><span class="swoole-badge swoole-badge--gray">检测中...</span></div>
        </div>
        <div class="swoole-card">
            <div class="swoole-card__label">PID</div>
            <div class="swoole-card__value" id="srvPid">-</div>
        </div>
        <div class="swoole-card">
            <div class="swoole-card__label">运行时长</div>
            <div class="swoole-card__value" id="srvUptime">-</div>
        </div>
        <div class="swoole-card">
            <div class="swoole-card__label">Worker 数</div>
            <div class="swoole-card__value" id="srvWorkers">-</div>
        </div>
    </div>

    <!-- 队列统计 -->
    <div class="swoole-section">
        <div class="swoole-section__header">
            <h2 class="swoole-section__title">队列概况</h2>
            <button class="layui-btn layui-btn-sm" id="swooleRefreshBtn"><i class="fa fa-refresh"></i> 刷新</button>
        </div>
        <div class="swoole-queue-stats" id="swooleQueueStats">
            <div class="swoole-stat"><span class="swoole-stat__num" id="qTotal">0</span><span class="swoole-stat__label">总任务</span></div>
            <div class="swoole-stat"><span class="swoole-stat__num swoole-stat--pending" id="qPending">0</span><span class="swoole-stat__label">等待中</span></div>
            <div class="swoole-stat"><span class="swoole-stat__num swoole-stat--processing" id="qProcessing">0</span><span class="swoole-stat__label">处理中</span></div>
            <div class="swoole-stat"><span class="swoole-stat__num swoole-stat--success" id="qSuccess">0</span><span class="swoole-stat__label">已完成</span></div>
            <div class="swoole-stat"><span class="swoole-stat__num swoole-stat--failed" id="qFailed">0</span><span class="swoole-stat__label">失败</span></div>
        </div>
    </div>

    <!-- 最近任务列表 -->
    <div class="swoole-section">
        <h2 class="swoole-section__title">最近任务</h2>
        <div class="swoole-table-wrap">
            <table class="swoole-table" id="swooleQueueTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>订单ID</th>
                        <th>类型</th>
                        <th>商品类型</th>
                        <th>状态</th>
                        <th>重试</th>
                        <th>创建时间</th>
                        <th>完成时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="swooleQueueBody">
                    <tr><td colspan="9" style="text-align:center;color:#ccc;padding:32px;">加载中...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 启动说明 -->
    <div class="swoole-section">
        <h2 class="swoole-section__title">命令行操作（备用）</h2>
        <div class="swoole-help">
            <p>请在服务器终端进入项目根目录执行：</p>
            <pre class="swoole-code">
# 启动服务
php swoole/server.php start

# 后台启动
php swoole/server.php start &

# 停止服务
php swoole/server.php stop

# 平滑重启（reload，加载新插件代码且不丢请求）
php swoole/server.php reload

# 查看状态
php swoole/server.php status</pre>
        </div>
    </div>
</div>

<style>
/* ========================= Swoole 监控页美化样式 =========================
 * 设计语言与首页 dash-* 卡片一致：柔和阴影、圆角 12px、紫色 #6366f1 作主色
 * 关键点：
 *   - 4 张状态卡：左侧彩色条 + hover 微上浮
 *   - 服务状态 badge：运行中带脉动圆点
 *   - 分区标题：左侧紫色装饰条
 *   - 队列统计：5 张独立卡片（替代原来一条横贯布局）
 *   - 任务表格：现代圆角 + 行 hover
 * ================================================================= */

/* 4 张状态卡（服务状态 / PID / 运行时长 / Worker 数） */
.swoole-status-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.swoole-card {
    position: relative;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 16px 18px 14px 22px;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    box-shadow:
        0 1px 2px rgba(15, 23, 42, 0.04),
        0 4px 12px rgba(99, 102, 241, 0.05);
}
.swoole-card::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 3px;
    background: #6366f1;
}
.swoole-card:nth-child(1)::before { background: #10b981; }
.swoole-card:nth-child(2)::before { background: #6366f1; }
.swoole-card:nth-child(3)::before { background: #f59e0b; }
.swoole-card:nth-child(4)::before { background: #0ea5e9; }
.swoole-card:hover {
    transform: translateY(-2px);
    box-shadow:
        0 4px 12px rgba(15, 23, 42, 0.06),
        0 12px 28px rgba(99, 102, 241, 0.14);
}
.swoole-card__label {
    font-size: 11px; color: #6b7280;
    margin-bottom: 6px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    font-weight: 600;
}
.swoole-card__value {
    font-size: 22px;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: 0.2px;
    line-height: 1.2;
}

/* 状态 badge：运行中 → 带脉动绿点；未运行 → 静止红点 */
.swoole-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 12px; border-radius: 999px;
    font-size: 13px; font-weight: 600;
    letter-spacing: 0.2px;
}
.swoole-badge::before {
    content: '';
    width: 8px; height: 8px; border-radius: 50%;
    background: currentColor; flex-shrink: 0;
}
.swoole-badge--green {
    background: #ecfdf5; color: #059669;
}
.swoole-badge--green::before { animation: swBadgePulse 1.6s ease-in-out infinite; }
.swoole-badge--red   { background: #fef2f2; color: #dc2626; }
.swoole-badge--gray  { background: #f3f4f6; color: #6b7280; }
@keyframes swBadgePulse {
    0%, 100% { box-shadow: 0 0 0 0   rgba(16, 185, 129, 0.55); }
    70%      { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
}

/* 分区容器与标题 */
.swoole-section { margin-bottom: 22px; }
.swoole-section__header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 12px;
}
.swoole-section__title {
    font-size: 15px; font-weight: 600; color: #111827;
    margin: 0 0 12px;
    letter-spacing: 0.2px;
    display: inline-flex; align-items: center; gap: 8px;
}
.swoole-section__title::before {
    content: '';
    width: 3px; height: 14px;
    background: linear-gradient(180deg, #6366f1, #8b5cf6);
    border-radius: 2px;
}
.swoole-section__header .swoole-section__title { margin-bottom: 0; }
/* 刷新按钮（覆盖 layui 默认外观，与页面主色呼应） */
.swoole-section__header .layui-btn {
    background: #fff;
    border: 1px solid #e5e7eb;
    color: #374151;
    border-radius: 8px;
    transition: all 0.15s ease;
    height: 30px; line-height: 28px;
    padding: 0 14px; font-size: 12px;
}
.swoole-section__header .layui-btn:hover {
    background: #eef2ff;
    border-color: #c7d2fe;
    color: #4f46e5;
}

/* 队列统计：5 张独立卡 */
.swoole-queue-stats {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 12px;
    background: transparent;
    border: none;
    padding: 0;
}
.swoole-stat {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 16px 14px 14px;
    text-align: center;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.swoole-stat:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
}
.swoole-stat__num {
    display: block;
    font-size: 28px; font-weight: 700;
    color: #0f172a; line-height: 1.1;
    margin-bottom: 4px;
    letter-spacing: 0.5px;
}
.swoole-stat--pending    { color: #f59e0b; }
.swoole-stat--processing { color: #6366f1; }
.swoole-stat--success    { color: #10b981; }
.swoole-stat--failed     { color: #ef4444; }
.swoole-stat__label {
    font-size: 12px; color: #6b7280;
    letter-spacing: 0.3px;
    font-weight: 500;
}

/* 任务表格 */
.swoole-table-wrap {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.swoole-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.swoole-table th {
    background: #f9fafb;
    padding: 12px 14px;
    text-align: left;
    color: #6b7280;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e5e7eb;
}
.swoole-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #f3f4f6;
    color: #374151;
    vertical-align: middle;
}
.swoole-table tbody tr:last-child td { border-bottom: none; }
.swoole-table tbody tr:hover { background: #fafbfc; }
.swoole-table .status-badge {
    display: inline-flex; align-items: center;
    padding: 3px 10px; border-radius: 999px;
    font-size: 11px; font-weight: 600;
    letter-spacing: 0.3px;
}
.swoole-table .status-pending    { background: #fef3c7; color: #b45309; }
.swoole-table .status-processing { background: #e0e7ff; color: #4f46e5; }
.swoole-table .status-success    { background: #d1fae5; color: #047857; }
.swoole-table .status-failed     { background: #fee2e2; color: #b91c1c; }
.swoole-table .status-retry      { background: #fed7aa; color: #c2410c; }
/* 重试按钮（覆盖 layui-btn-xs） */
.swoole-table .layui-btn-xs {
    background: #fff;
    border: 1px solid #fca5a5;
    color: #ef4444;
    border-radius: 6px;
    height: 24px; line-height: 22px;
    padding: 0 10px; font-size: 11px;
    transition: all 0.15s ease;
}
.swoole-table .layui-btn-xs:hover {
    background: #fef2f2;
    color: #dc2626;
    border-color: #ef4444;
}

/* 使用说明 + 代码块 */
.swoole-help {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 18px 20px;
    font-size: 13px;
    color: #374151;
    line-height: 1.7;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.swoole-help p { margin: 0 0 10px; color: #6b7280; }
.swoole-code {
    background: #0f172a;
    color: #e2e8f0;
    border-radius: 8px;
    padding: 14px 16px;
    font-family: 'JetBrains Mono', 'Consolas', 'Monaco', monospace;
    font-size: 12.5px;
    line-height: 1.8;
    overflow-x: auto;
    white-space: pre;
    margin: 0;
    box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.25);
}

/* 响应式 */
@media (max-width: 900px) {
    .swoole-queue-stats { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 600px) {
    .swoole-status-cards { grid-template-columns: repeat(2, 1fr); }
    .swoole-queue-stats  { grid-template-columns: repeat(2, 1fr); }
}
</style>

<script>
$(function () {
    // PJAX 防重复绑定：清掉本页历史 .admSwoole handler，避免事件成倍触发
    $(document).off('.admSwoole');
    $(window).off('.admSwoole');

    'use strict';

    var csrfToken = <?= json_encode($csrfToken) ?>;

    function formatUptime(seconds) {
        if (!seconds || seconds <= 0) return '-';
        var d = Math.floor(seconds / 86400);
        var h = Math.floor((seconds % 86400) / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        var s = seconds % 60;
        var parts = [];
        if (d > 0) parts.push(d + '天');
        if (h > 0) parts.push(h + '时');
        if (m > 0) parts.push(m + '分');
        parts.push(s + '秒');
        return parts.join('');
    }

    var statusMap = {
        pending: '<span class="status-badge status-pending">等待</span>',
        processing: '<span class="status-badge status-processing">处理中</span>',
        success: '<span class="status-badge status-success">成功</span>',
        failed: '<span class="status-badge status-failed">失败</span>',
        retry: '<span class="status-badge status-retry">重试</span>'
    };

    // 加载状态
    function loadStatus() {
        $.post('/admin/swoole.php', { _action: 'status', csrf_token: csrfToken }, function (res) {
            if (res.code === 200 && res.data) {
                var d = res.data;
                if (d.running) {
                    $('#srvStatus').html('<span class="swoole-badge swoole-badge--green">运行中</span>');
                    $('#srvPid').text(d.pid || '-');
                    $('#srvUptime').text(formatUptime(d.uptime));
                    $('#srvWorkers').text(d.workers || '-');
                    if (d.queue) {
                        $('#qTotal').text(d.queue.total || 0);
                        $('#qPending').text(d.queue.pending || 0);
                        $('#qProcessing').text(d.queue.processing || 0);
                        $('#qSuccess').text(d.queue.success || 0);
                        $('#qFailed').text(d.queue.failed || 0);
                    }
                } else {
                    $('#srvStatus').html('<span class="swoole-badge swoole-badge--red">未运行</span>');
                    $('#srvPid, #srvUptime, #srvWorkers').text('-');
                }
                if (res.data.csrf_token) csrfToken = res.data.csrf_token;
            } else {
                $('#srvStatus').html('<span class="swoole-badge swoole-badge--red">未运行</span>');
                $('#srvPid, #srvUptime, #srvWorkers').text('-');
            }
        }, 'json').fail(function () {
            $('#srvStatus').html('<span class="swoole-badge swoole-badge--red">未运行</span>');
        });
    }

    // 加载队列任务
    function loadQueue() {
        $.post('/admin/swoole.php', { _action: 'queue_recent', csrf_token: csrfToken }, function (res) {
            if (res.code === 200 && res.data && res.data.list) {
                var list = res.data.list;
                if (list.length === 0) {
                    $('#swooleQueueBody').html('<tr><td colspan="9" style="text-align:center;color:#ccc;padding:32px;">暂无任务</td></tr>');
                    return;
                }
                var html = '';
                for (var i = 0; i < list.length; i++) {
                    var t = list[i];
                    html += '<tr>'
                        + '<td>' + t.id + '</td>'
                        + '<td>' + t.order_id + '</td>'
                        + '<td>' + (t.task_type || '-') + '</td>'
                        + '<td>' + (t.goods_type || '-') + '</td>'
                        + '<td>' + (statusMap[t.status] || t.status) + '</td>'
                        + '<td>' + t.attempts + '/' + t.max_attempts + '</td>'
                        + '<td>' + (t.created_at || '-') + '</td>'
                        + '<td>' + (t.completed_at || '-') + '</td>'
                        + '<td>'
                        + (t.status === 'failed' ? '<button class="layui-btn layui-btn-xs" onclick="retryTask(' + t.id + ')">重试</button>' : '-')
                        + '</td></tr>';
                }
                $('#swooleQueueBody').html(html);
            }
        }, 'json');
    }

    // 重试任务
    window.retryTask = function (id) {
        $.post('/admin/swoole.php', { _action: 'queue_retry', csrf_token: csrfToken, id: id }, function (res) {
            if (res.code === 200) {
                if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                layui.layer.msg('已重置');
                loadQueue();
                loadStatus();
            } else {
                layui.layer.msg(res.msg || '操作失败');
            }
        }, 'json');
    };

    // 刷新按钮
    $(document).on('click.admSwoole', '#swooleRefreshBtn', function () {
        loadStatus();
        loadQueue();
        layui.layer.msg('已刷新');
    });

    // 初始加载
    loadStatus();
    loadQueue();
});
</script>
