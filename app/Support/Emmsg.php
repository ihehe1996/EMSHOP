<?php

namespace App\Support;

/**
 * 统一消息页面服务（实例实现）
 * - error：错误/异常场景
 * - info：操作指引、前置条件提示等
 *
 * 兼容门面见 app/Facade/Emmsg.php。
 *
 * 页面是自包含的：样式全部内联，不引用 content/static 下的任何资源。
 * 数据库连不上、静态资源加载失败时，这张页面恰恰最需要正常显示，别改成外链样式。
 * 版式与站点 404.html 是同一套语言（纸张底色 + 墨色 + 单一强调色）。
 */
class Emmsg
{
    /**
     * 输出友好错误页面并终止程序
     *
     * @param string $msg 自定义错误提示
     * @param \Exception|string|null $e 异常对象或错误详情字符串
     */
    public function error(string $msg, $e = null): void
    {
        $exception = null;
        $detailMessage = '';

        if ($e instanceof \Exception) {
            $exception = $e;
            $detailMessage = $e->getMessage();
        } elseif (is_string($e) && $e !== '') {
            $detailMessage = $e;
        }

        $this->render([
            'httpCode'  => 500,
            'pageTitle' => '系统错误',
            'variant'   => 'error',
            'title'     => '系统遇到了一点问题',
            'msg'       => $msg,
            'detail'    => $detailMessage,
            'exception' => $exception,
            'footer'    => '如果问题持续存在，请联系技术支持',
            'actions'   => [
                ['label' => '刷新页面', 'onclick' => 'location.reload()', 'primary' => true],
                ['label' => '返回上一页', 'onclick' => 'history.back()'],
            ],
        ]);
    }

    /**
     * 输出操作指引/提示页面并终止程序
     *
     * @param string $msg 提示正文（告诉用户该做什么）
     * @param string $title 标题，默认「温馨提示」
     * @param string|null $detail 补充说明（步骤、注意事项等，可选）
     * @param array<int, array{label: string, onclick?: string, href?: string, icon?: string, primary?: bool}>|null $actions 自定义按钮；null 时使用默认「返回上一页」
     */
    public function info(string $msg, string $title = '温馨提示', ?string $detail = null, ?array $actions = null): void
    {
        if ($actions === null) {
            $actions = [
                ['label' => '返回上一页', 'onclick' => 'history.back()', 'primary' => true],
            ];
        }

        $this->render([
            'httpCode'  => 200,
            'pageTitle' => $title,
            'variant'   => 'info',
            'title'     => $title,
            'msg'       => $msg,
            'detail'    => $detail ?? '',
            'exception' => null,
            'footer'    => '完成上述操作后，请刷新或重新进入本页',
            'actions'   => $actions,
        ]);
    }

    /**
     * 渲染消息页并终止程序。
     *
     * 页面自带全部样式（内联 CSS、零外部资源）：数据库连不上、静态资源没加载时
     * 它也必须能正常显示，所以不要改成引用 content/static 下的样式表或图片。
     *
     * @param array{
     *   httpCode: int,
     *   pageTitle: string,
     *   variant: string,
     *   title: string,
     *   msg: string,
     *   detail: string,
     *   exception: \Exception|null,
     *   footer: string,
     *   actions: array<int, array{label: string, onclick?: string, href?: string, primary?: bool}>,
     *   code?: string,
     *   kicker?: string,
     *   status?: string,
     *   detailLabel?: string
     * } $cfg
     */
    private function render(array $cfg): void
    {
        http_response_code((int) $cfg['httpCode']);
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        $variant = ((string) ($cfg['variant'] ?? '')) === 'info' ? 'info' : 'error';
        $isInfo = $variant === 'info';
        $exception = $cfg['exception'] ?? null;

        // 版式跟着 404.html 走：纸张底色 + 墨色 + 单一强调色。
        // 错误用橙红，提示用品牌绿；大字块在错误页是状态码，在提示页是「!」。
        $code        = (string) ($cfg['code'] ?? ($isInfo ? '!' : '500'));
        $kicker      = (string) ($cfg['kicker'] ?? ($isInfo ? 'Notice' : 'Error / 500'));
        $status      = (string) ($cfg['status'] ?? ($isInfo ? 'Information' : 'Internal Server Error'));
        $detailLabel = (string) ($cfg['detailLabel'] ?? ($isInfo ? '说明' : '错误详情'));
        $sideText    = $isInfo ? 'EMSHOP — Notice — Information' : 'EMSHOP — Server Error — 500';

        // 技术细节：异常信息 + 文件/行号（有异常对象时补上）
        $detailBody = (string) ($cfg['detail'] ?? '');
        if ($exception instanceof \Exception && (string) $exception->getFile() !== '') {
            $detailBody .= ($detailBody === '' ? '' : "\n\n")
                . '文件：' . $exception->getFile() . "\n"
                . '行号：' . $exception->getLine();
        }

        $actions = is_array($cfg['actions'] ?? null) ? $cfg['actions'] : [];
        $footer = (string) ($cfg['footer'] ?? '');
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');

        $esc = static function (string $value): string {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        };

        ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="light">
<meta name="theme-color" content="#f3efe6">
<title><?php echo $esc((string) $cfg['pageTitle']); ?></title>
<style>
    :root {
        --paper: #f3efe6;
        --ink: #141414;
        --muted: #625d53;
        --line: rgba(20, 20, 20, .16);
        --sans: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
        --mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
    }

    *,
    *::before,
    *::after { box-sizing: border-box; }

    html { -webkit-text-size-adjust: 100%; }

    body {
        margin: 0;
        min-height: 100vh;
        min-height: 100dvh;
        background: var(--paper);
        color: var(--ink);
        font-family: var(--sans);
        -webkit-font-smoothing: antialiased;
    }

    body.emmsg--error { --accent: #ff4d1f; }
    body.emmsg--info  { --accent: #2f7a66; }

    .poster {
        display: grid;
        grid-template-columns: minmax(0, 1fr) clamp(120px, 26vw, 360px);
        min-height: 100vh;
        min-height: 100dvh;
    }

    .main {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 32px;
        min-width: 0;
        padding: clamp(24px, 4.5vw, 64px);
    }

    .top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        font-family: var(--mono);
        font-size: 11px;
        letter-spacing: .22em;
        text-transform: uppercase;
    }

    .top .mark::before {
        content: "";
        display: inline-block;
        width: 8px;
        height: 8px;
        margin-right: 10px;
        vertical-align: 1px;
        background: var(--accent);
    }

    .hero {
        margin: auto 0;
        padding: clamp(10px, 2vh, 28px) 0;
        min-width: 0;
    }

    .code {
        margin: 0 0 0 -.04em;
        font-size: clamp(80px, 15vw, 190px);
        font-weight: 900;
        line-height: .8;
        letter-spacing: -.06em;
        text-shadow: clamp(5px, .9vw, 13px) clamp(5px, .9vw, 13px) 0 var(--accent);
    }

    /* 提示页的大字块是「!」，再加投影会把感叹号的圆点叠成两个，反而不干净 */
    body.emmsg--info .code { text-shadow: none; }

    h1 {
        margin: clamp(14px, 2.4vw, 24px) 0 0;
        font-size: clamp(28px, 4.4vw, 52px);
        font-weight: 900;
        line-height: 1.12;
        letter-spacing: -.015em;
        word-break: break-word;
        overflow-wrap: anywhere;
    }

    .desc {
        margin: 16px 0 0;
        max-width: 62ch;
        color: var(--muted);
        font-size: clamp(14px, 1.6vw, 15.5px);
        line-height: 1.9;
        word-break: break-word;
        overflow-wrap: anywhere;
    }

    .detail {
        margin-top: clamp(20px, 3vw, 28px);
        max-width: 860px;
        border: 1px solid var(--line);
        background: rgba(255, 255, 255, .55);
    }

    .detail-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 9px 14px;
        border-bottom: 1px solid var(--line);
        font-family: var(--mono);
        font-size: 10.5px;
        letter-spacing: .18em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .detail-body {
        margin: 0;
        padding: 14px;
        max-height: min(36vh, 260px);
        overflow: auto;
        font-family: var(--mono);
        font-size: 12px;
        line-height: 1.75;
        color: var(--ink);
        white-space: pre-wrap;
        word-break: break-word;
        overflow-wrap: anywhere;
        -webkit-overflow-scrolling: touch;
    }

    .detail-body::-webkit-scrollbar { width: 8px; height: 8px; }
    .detail-body::-webkit-scrollbar-thumb { background: rgba(20, 20, 20, .22); }
    .detail-body::-webkit-scrollbar-track { background: transparent; }

    .actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 16px 26px;
        margin-top: clamp(22px, 3.2vw, 32px);
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 50px;
        padding: 0 30px;
        border: 2px solid var(--ink);
        border-radius: 0;
        background: var(--accent);
        color: var(--ink);
        font-family: inherit;
        font-size: 15px;
        font-weight: 800;
        letter-spacing: .02em;
        text-decoration: none;
        cursor: pointer;
        transition: background-color .16s ease, color .16s ease, border-color .16s ease;
    }

    .btn:hover {
        background: var(--ink);
        color: var(--paper);
    }

    .link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 4px 0;
        border: 0;
        border-bottom: 2px solid var(--ink);
        background: transparent;
        color: var(--ink);
        font-family: inherit;
        font-size: 15px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        transition: color .16s ease, border-color .16s ease;
    }

    .link:hover {
        color: var(--accent);
        border-color: var(--accent);
    }

    .btn:focus-visible,
    .link:focus-visible {
        outline: 3px solid var(--accent);
        outline-offset: 3px;
    }

    .note {
        margin: 20px 0 0;
        max-width: 62ch;
        color: var(--muted);
        font-size: 12.5px;
        line-height: 1.8;
    }

    .bottom {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 20px;
        padding-top: 15px;
        border-top: 1px solid var(--line);
        font-family: var(--mono);
        font-size: 10.5px;
        line-height: 1.7;
        color: var(--muted);
    }

    .path {
        display: flex;
        align-items: baseline;
        gap: 10px;
        min-width: 0;
        margin: 0;
    }

    .path span {
        flex: none;
        letter-spacing: .18em;
    }

    .path code {
        display: inline-block;
        max-width: min(52vw, 560px);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: var(--ink);
        font-family: inherit;
        font-size: 10.5px;
    }

    .brand {
        flex: none;
        letter-spacing: .22em;
    }

    /* 右侧色块：与 404 页同一套圆形十字构图 */
    .side {
        position: relative;
        overflow: hidden;
        background: var(--accent);
    }

    .side .vertical {
        position: absolute;
        left: clamp(16px, 2vw, 28px);
        bottom: clamp(24px, 4vw, 44px);
        writing-mode: vertical-rl;
        transform: rotate(180deg);
        font-family: var(--mono);
        font-size: 11px;
        letter-spacing: .32em;
        text-transform: uppercase;
        color: var(--ink);
    }

    .side .shape {
        position: absolute;
        left: 50%;
        top: 50%;
        width: min(64%, 260px);
        aspect-ratio: 1;
        border: 2px solid var(--ink);
        border-radius: 50%;
        transform: translate(-50%, -50%);
    }

    .side .shape::before {
        content: "";
        position: absolute;
        left: 50%;
        top: -10%;
        width: 2px;
        height: 120%;
        background: var(--ink);
        transform: translateX(-50%);
    }

    .side .shape::after {
        content: "";
        position: absolute;
        left: -10%;
        top: 50%;
        width: 120%;
        height: 2px;
        background: var(--ink);
        transform: translateY(-50%);
    }

    @media (max-width: 760px) {
        .poster {
            grid-template-columns: minmax(0, 1fr);
            grid-template-rows: clamp(72px, 16vw, 104px) minmax(0, 1fr);
        }

        .side {
            grid-row: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 clamp(20px, 5vw, 32px);
        }

        .side .vertical {
            position: static;
            writing-mode: horizontal-tb;
            transform: none;
            letter-spacing: .24em;
            font-size: 10px;
        }

        .side .shape {
            position: relative;
            left: auto;
            top: auto;
            width: clamp(40px, 10vw, 56px);
            transform: none;
        }

        .main {
            gap: 28px;
            padding: clamp(22px, 5vw, 36px);
        }

        .code { font-size: clamp(64px, 22vw, 120px); }

        .bottom {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }

        .path { width: 100%; }
        .path code { max-width: 100%; }
    }

    @media (max-width: 420px) {
        .actions { flex-direction: column; align-items: stretch; }
        .btn { width: 100%; }
        .link { align-self: flex-start; }
    }

    @media (prefers-reduced-motion: reduce) {
        * { transition: none !important; }
    }
</style>
</head>
<body class="emmsg--<?php echo $variant; ?>">
<div class="poster">
    <section class="main">
        <header class="top">
            <span class="mark"><?php echo $esc($kicker); ?></span>
            <span><?php echo $esc($status); ?></span>
        </header>

        <div class="hero">
            <p class="code" aria-hidden="true"><?php echo $esc($code); ?></p>
            <h1><?php echo $esc((string) $cfg['title']); ?></h1>
            <p class="desc"><?php echo nl2br($esc((string) $cfg['msg'])); ?></p>

            <?php if ($detailBody !== ''): ?>
            <div class="detail">
                <div class="detail-head">
                    <span><?php echo $esc($detailLabel); ?></span>
                    <span>Details</span>
                </div>
                <pre class="detail-body"><?php echo $esc($detailBody); ?></pre>
            </div>
            <?php endif; ?>

            <?php if ($actions !== []): ?>
            <div class="actions">
                <?php foreach ($actions as $action): ?>
                    <?php
                    $label = (string) ($action['label'] ?? '');
                    if ($label === '') {
                        continue;
                    }
                    $icon = (string) ($action['icon'] ?? '');
                    $isPrimary = !empty($action['primary']);
                    $btnClass = $isPrimary ? 'btn' : 'link';
                    $href = trim((string) ($action['href'] ?? ''));
                    $onclick = trim((string) ($action['onclick'] ?? ''));
                    ?>
                    <?php if ($href !== ''): ?>
                        <a class="<?php echo $btnClass; ?>" href="<?php echo $esc($href); ?>"><?php if ($icon !== ''): ?><span aria-hidden="true"><?php echo $esc($icon); ?></span><?php endif; ?><span><?php echo $esc($label); ?></span></a>
                    <?php else: ?>
                        <button type="button" class="<?php echo $btnClass; ?>"<?php echo $onclick !== '' ? ' onclick="' . $esc($onclick) . '"' : ''; ?>><?php if ($icon !== ''): ?><span aria-hidden="true"><?php echo $esc($icon); ?></span><?php endif; ?><span><?php echo $esc($label); ?></span></button>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($footer !== ''): ?>
            <p class="note"><?php echo $esc($footer); ?></p>
            <?php endif; ?>
        </div>

        <footer class="bottom">
            <p class="path">
                <span>URL</span>
                <code><?php echo $requestUri !== '' ? $esc($requestUri) : '—'; ?></code>
            </p>
            <span class="brand">EMSHOP</span>
        </footer>
    </section>

    <aside class="side" aria-hidden="true">
        <span class="vertical"><?php echo $esc($sideText); ?></span>
        <span class="shape"></span>
    </aside>
</div>
</body>
</html>
        <?php
        exit;
    }
}
