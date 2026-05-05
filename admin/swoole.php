<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * Swoole 监控页面。
 */
adminRequireLogin();

$csrfToken = Csrf::token();
// Swoole API 地址（后台系统设置中配置 swoole_api_url，生产环境填 http://127.0.0.1:9601）
$swooleApiUrls = swooleApiCandidates(Config::get('swoole_api_url', 'http://127.0.0.1:9601'));

// AJAX 请求：代理转发到 Swoole HTTP API
if (Request::isPost()) {
    $action = (string) Input::post('_action', '');
    

    switch ($action) {
        case 'status':
            $result = swooleApiGetAny($swooleApiUrls, '/status');
            if ($result === null) {
                Response::success('', ['running' => swoolePidRunning()]);
            }
            $data = $result['data'] ?? [];
            if (!isset($data['running'])) {
                $data['running'] = true;
            }
            Response::success('', $data);
            break;

        case 'queue_recent':
            $result = swooleApiGetAny($swooleApiUrls, '/queue/recent');
            Response::success('', ['list' => $result['data'] ?? []]);
            break;

        case 'queue_retry':
            $id = (int) Input::post('id', 0);
            swooleApiPostAny($swooleApiUrls, '/queue/retry', ['id' => $id]);
            Response::success('已重置', ['csrf_token' => Csrf::refresh()]);
            break;

        default:
            Response::error('未知操作');
    }
}

// 页面渲染
if ((string) Input::get('_popup', '') !== '') {
    // 弹窗模式（iframe 嵌入）：只渲染监控内容 + 必要的 CSS/JS，不套后台框架（避免侧边栏重复出现）
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swoole 监控</title>
    <link rel="stylesheet" href="/content/static/lib/layui-v2.13.5/layui/css/layui.css">
    <link rel="stylesheet" href="/content/static/lib/font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="/admin/static/css/reset-layui.css">
    <link rel="stylesheet" href="/admin/static/css/admin.css">
    <script src="/content/static/lib/jquery.min.3.5.1.js"></script>
    <script src="/content/static/lib/layui-v2.13.5/layui/layui.js"></script>
    <script src="/admin/static/js/admin.js"></script>
    <script>window.adminCsrfToken = <?php echo json_encode($csrfToken); ?>;</script>
    <style>
        /* 覆盖 admin.css 对 html/body 的"全局锁死滚动"策略（后台主框架依赖它做内滚动）
           在弹窗 iframe 模式下我们需要由 body 接管滚动，否则内容超高时会被裁掉 */
        html, body { height: auto; overflow: auto; }
        body { background: #f8fafc; margin: 0; }
        /* 弹窗里不需要 admin-content 的 flex:1 逻辑，解除它以便正常按内容流布局 */
        .swoole-popup-body { padding: 16px 20px 24px; }
    </style>
</head>
<body>
<div class="swoole-popup-body"><?php include __DIR__ . '/view/swoole.php'; ?></div>
</body>
</html>
    <?php
    exit;
}

if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/swoole.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/swoole.php';
    require __DIR__ . '/index.php';
}


/**
 * 请求 Swoole HTTP API（GET）。
 */
function swooleApiGet(string $url): ?array
{
    $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return null;
    }
    return json_decode($body, true);
}

/**
 * 请求 Swoole HTTP API（POST）。
 */
function swooleApiPost(string $url, array $data): ?array
{
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
            'timeout' => 3,
            'ignore_errors' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return null;
    }
    return json_decode($body, true);
}

/**
 * 规范化并生成可回退的 Swoole API 地址列表。
 *
 * 优先使用后台配置值；若该值不可达，再回退到本机地址。
 *
 * @return array<int, string>
 */
function swooleApiCandidates(string $configured): array
{
    $add = static function (array &$list, string $url): void {
        $u = trim($url);
        if ($u === '') {
            return;
        }
        $u = rtrim($u, '/');
        if (!preg_match('#^https?://#i', $u)) {
            return;
        }
        if (!in_array($u, $list, true)) {
            $list[] = $u;
        }
    };

    $urls = [];
    $configured = trim($configured);
    if ($configured !== '') {
        $add($urls, $configured);
        $parts = @parse_url($configured);
        if (is_array($parts)) {
            $scheme = strtolower((string) ($parts['scheme'] ?? 'http'));
            if ($scheme !== 'https') {
                $scheme = 'http';
            }
            $port = (int) ($parts['port'] ?? 0);
            if ($port < 1 || $port > 65535) {
                $port = 9601;
            }
            // 同端口多地址回退：只在当前站点配置端口内切换，避免串到同机其它站点实例。
            $add($urls, $scheme . '://127.0.0.1:' . $port);
            $add($urls, $scheme . '://localhost:' . $port);
        }
    } else {
        $add($urls, 'http://127.0.0.1:9601');
        $add($urls, 'http://localhost:9601');
    }
    return $urls;
}

/**
 * 依次尝试多个 API 地址发起 GET，请求到任意一个成功即返回。
 *
 * @param array<int, string> $baseUrls
 */
function swooleApiGetAny(array $baseUrls, string $path): ?array
{
    foreach ($baseUrls as $baseUrl) {
        $result = swooleApiGet($baseUrl . $path);
        if ($result !== null) {
            return $result;
        }
    }
    return null;
}

/**
 * 依次尝试多个 API 地址发起 POST，请求到任意一个成功即返回。
 *
 * @param array<int, string> $baseUrls
 */
function swooleApiPostAny(array $baseUrls, string $path, array $data): ?array
{
    foreach ($baseUrls as $baseUrl) {
        $result = swooleApiPost($baseUrl . $path, $data);
        if ($result !== null) {
            return $result;
        }
    }
    return null;
}

/**
 * 兜底进程检测：与 `php swoole/server.php status` 一致，读取 PID 并检查进程是否存在。
 */
function swoolePidRunning(): bool
{
    $pid = swooleReadPid();
    if ($pid <= 0) {
        return false;
    }

    if (function_exists('posix_kill')) {
        return @posix_kill($pid, 0);
    }

    // 某些 FPM 环境未启用 posix 扩展，Linux 下退化为 /proc 检查。
    if (PHP_OS_FAMILY === 'Linux') {
        return is_dir('/proc/' . $pid);
    }
    return false;
}

/**
 * 读取 Swoole 主进程 PID。
 */
function swooleReadPid(): int
{
    $pidFile = EM_ROOT . '/swoole/swoole.pid';
    if (!is_file($pidFile)) {
        return 0;
    }
    return (int) trim((string) @file_get_contents($pidFile));
}
