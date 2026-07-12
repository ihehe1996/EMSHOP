<?php

/**
 * 统一消息页面静态工具类
 * - error：错误/异常场景
 * - info：操作指引、前置条件提示等
 */
class Emmsg
{
    /**
     * 输出友好错误页面并终止程序
     *
     * @param string $msg 自定义错误提示
     * @param \Exception|string|null $e 异常对象或错误详情字符串
     */
    public static function error(string $msg, $e = null): void
    {
        $exception = null;
        $detailMessage = '';

        if ($e instanceof \Exception) {
            $exception = $e;
            $detailMessage = $e->getMessage();
        } elseif (is_string($e) && $e !== '') {
            $detailMessage = $e;
        }

        self::render([
            'httpCode'  => 500,
            'pageTitle' => '系统错误',
            'variant'   => 'error',
            'icon'      => '⚠️',
            'title'     => '系统遇到了一点问题',
            'msg'       => $msg,
            'detail'    => $detailMessage,
            'exception' => $exception,
            'footer'    => '如果问题持续存在，请联系技术支持',
            'actions'   => [
                ['label' => '刷新页面', 'icon' => '🔄', 'onclick' => 'location.reload()', 'primary' => true],
                ['label' => '返回上一页', 'icon' => '←', 'onclick' => 'history.back()'],
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
    public static function info(string $msg, string $title = '温馨提示', ?string $detail = null, ?array $actions = null): void
    {
        if ($actions === null) {
            $actions = [
                ['label' => '返回上一页', 'icon' => '←', 'onclick' => 'history.back()', 'primary' => true],
            ];
        }

        self::render([
            'httpCode'  => 200,
            'pageTitle' => $title,
            'variant'   => 'info',
            'icon'      => '💡',
            'title'     => $title,
            'msg'       => $msg,
            'detail'    => $detail ?? '',
            'exception' => null,
            'footer'    => '完成上述操作后，请刷新或重新进入本页',
            'actions'   => $actions,
        ]);
    }

    /**
     * @param array{
     *   httpCode: int,
     *   pageTitle: string,
     *   variant: string,
     *   icon: string,
     *   title: string,
     *   msg: string,
     *   detail: string,
     *   exception: \Exception|null,
     *   footer: string,
     *   actions: array<int, array{label: string, onclick?: string, href?: string, icon?: string, primary?: bool}>
     * } $cfg
     */
    private static function render(array $cfg): void
    {
        http_response_code((int) $cfg['httpCode']);

        $variant = (string) $cfg['variant'];
        $exception = $cfg['exception'];
        $detailMessage = (string) $cfg['detail'];
        $actions = $cfg['actions'];

        ?>
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo htmlspecialchars((string) $cfg['pageTitle']); ?></title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }

                html {
                    height: 100%;
                    -webkit-text-size-adjust: 100%;
                }

                body {
                    height: 100%;
                    min-height: 100%;
                    background: linear-gradient(135deg, #f0f7f5 0%, #e8f3f0 50%, #dce8e5 100%);
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Microsoft Yahei", sans-serif;
                    display: flex;
                    flex-direction: column;
                    margin: 0;
                    position: relative;
                    overflow-x: hidden;
                    overflow-y: auto;
                    -webkit-overflow-scrolling: touch;
                }

                .emmsg-page {
                    flex: 1 0 auto;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    width: 100%;
                    min-height: 100%;
                    min-height: 100dvh;
                    box-sizing: border-box;
                    padding:
                        max(24px, env(safe-area-inset-top, 0px))
                        max(28px, env(safe-area-inset-right, 0px))
                        max(24px, env(safe-area-inset-bottom, 0px))
                        max(28px, env(safe-area-inset-left, 0px));
                    position: relative;
                    z-index: 1;
                }

                body::before {
                    content: '';
                    position: fixed;
                    top: -40%;
                    right: -25%;
                    width: min(600px, 90vw);
                    height: min(600px, 90vw);
                    background: radial-gradient(circle, rgba(76, 125, 113, 0.08) 0%, transparent 70%);
                    border-radius: 50%;
                    pointer-events: none;
                }

                body::after {
                    content: '';
                    position: fixed;
                    bottom: -35%;
                    left: -20%;
                    width: min(500px, 80vw);
                    height: min(500px, 80vw);
                    background: radial-gradient(circle, rgba(76, 125, 113, 0.06) 0%, transparent 70%);
                    border-radius: 50%;
                    pointer-events: none;
                }

                .emmsg-container {
                    width: 100%;
                    max-width: 520px;
                    margin: 0 auto;
                    flex-shrink: 0;
                    animation: emmsgSlideUp 0.5s ease-out;
                }

                @keyframes emmsgSlideUp {
                    from { opacity: 0; transform: translateY(30px); }
                    to   { opacity: 1; transform: translateY(0); }
                }

                .emmsg-card {
                    background: #ffffff;
                    border-radius: 20px;
                    box-shadow: 0 10px 40px rgba(76, 125, 113, 0.12), 0 2px 8px rgba(0, 0, 0, 0.04);
                    padding: 50px 40px;
                    text-align: center;
                    position: relative;
                    overflow: hidden;
                }

                .emmsg-card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 4px;
                }

                .emmsg-card--error::before {
                    background: linear-gradient(90deg, #e11d48 0%, #f43f5e 50%, #e11d48 100%);
                }

                .emmsg-card--info::before {
                    background: linear-gradient(90deg, #4C7D71 0%, #5a9486 50%, #4C7D71 100%);
                }

                .emmsg-icon-wrapper {
                    width: 90px;
                    height: 90px;
                    margin: 0 auto 25px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    position: relative;
                }

                .emmsg-icon-wrapper--error {
                    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
                    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);
                }

                .emmsg-icon-wrapper--info {
                    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
                    box-shadow: 0 4px 12px rgba(76, 125, 113, 0.18);
                }

                .emmsg-icon {
                    font-size: 48px;
                    line-height: 1;
                }

                .emmsg-icon-wrapper--error .emmsg-icon {
                    animation: emmsgPulse 2s ease-in-out infinite;
                }

                @keyframes emmsgPulse {
                    0%, 100% { transform: scale(1); }
                    50%      { transform: scale(1.05); }
                }

                .emmsg-title {
                    font-size: 26px;
                    color: #1a202c;
                    font-weight: 700;
                    margin-bottom: 16px;
                    letter-spacing: -0.5px;
                }

                .emmsg-desc {
                    font-size: 15px;
                    color: #4a5568;
                    line-height: 1.8;
                    margin-bottom: 28px;
                    padding: 0 4px;
                    word-break: break-word;
                    overflow-wrap: anywhere;
                }

                .emmsg-detail {
                    border-radius: 10px;
                    padding: 16px;
                    font-size: 13px;
                    text-align: left;
                    word-break: break-word;
                    overflow-wrap: anywhere;
                    line-height: 1.7;
                    max-height: min(40vh, 280px);
                    overflow-y: auto;
                    margin-top: 16px;
                    -webkit-overflow-scrolling: touch;
                }

                .emmsg-detail--error {
                    background: #f7fafc;
                    border: 1px solid #e2e8f0;
                    color: #718096;
                    font-family: 'Consolas', 'Monaco', monospace;
                }

                .emmsg-detail--info {
                    background: #f0f7f5;
                    border: 1px solid #c6ddd7;
                    color: #4a5568;
                }

                .emmsg-actions {
                    margin-top: 30px;
                    display: flex;
                    gap: 12px;
                    justify-content: center;
                    flex-wrap: wrap;
                }

                .emmsg-btn {
                    padding: 12px 24px;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    border: none;
                    text-decoration: none;
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                }

                .emmsg-btn-primary {
                    background: linear-gradient(135deg, #4C7D71 0%, #5a9486 100%);
                    color: #ffffff;
                    box-shadow: 0 2px 8px rgba(76, 125, 113, 0.25);
                }

                .emmsg-btn-primary:hover {
                    background: linear-gradient(135deg, #427065 0%, #4C7D71 100%);
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(76, 125, 113, 0.3);
                }

                .emmsg-btn-secondary {
                    background: #ffffff;
                    color: #4C7D71;
                    border: 1px solid #4C7D71;
                }

                .emmsg-btn-secondary:hover {
                    background: #f0f7f5;
                    transform: translateY(-2px);
                    box-shadow: 0 2px 8px rgba(76, 125, 113, 0.15);
                }

                .emmsg-footer {
                    margin-top: 24px;
                    padding-top: 20px;
                    border-top: 1px solid #e2e8f0;
                    font-size: 12px;
                    color: #a0aec0;
                }

                .emmsg-detail::-webkit-scrollbar { width: 6px; }
                .emmsg-detail::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 3px; }
                .emmsg-detail::-webkit-scrollbar-thumb { background: #cbd5e0; border-radius: 3px; }
                .emmsg-detail::-webkit-scrollbar-thumb:hover { background: #a0aec0; }

                @media (max-width: 640px) {
                    .emmsg-page {
                        padding:
                            max(20px, env(safe-area-inset-top, 0px))
                            max(32px, env(safe-area-inset-right, 0px))
                            max(20px, env(safe-area-inset-bottom, 0px))
                            max(32px, env(safe-area-inset-left, 0px));
                    }

                    .emmsg-container { max-width: 100%; }

                    .emmsg-card {
                        padding: 28px 22px;
                        border-radius: 16px;
                    }

                    .emmsg-title {
                        font-size: 20px;
                        margin-bottom: 12px;
                    }

                    .emmsg-desc {
                        font-size: 14px;
                        line-height: 1.7;
                        margin-bottom: 20px;
                    }

                    .emmsg-icon-wrapper {
                        width: 68px;
                        height: 68px;
                        margin-bottom: 18px;
                    }

                    .emmsg-icon { font-size: 36px; }

                    .emmsg-detail {
                        padding: 12px;
                        font-size: 12px;
                        max-height: min(35vh, 200px);
                    }

                    .emmsg-actions {
                        flex-direction: column;
                        margin-top: 22px;
                        gap: 10px;
                    }

                    .emmsg-btn {
                        width: 100%;
                        justify-content: center;
                        padding: 13px 20px;
                    }

                    .emmsg-footer {
                        margin-top: 18px;
                        padding-top: 16px;
                        font-size: 11px;
                    }
                }
            </style>
        </head>
        <body>
        <div class="emmsg-page">
            <div class="emmsg-container">
                <div class="emmsg-card emmsg-card--<?php echo htmlspecialchars($variant); ?>">
                    <div class="emmsg-icon-wrapper emmsg-icon-wrapper--<?php echo htmlspecialchars($variant); ?>">
                        <div class="emmsg-icon"><?php echo $cfg['icon']; ?></div>
                    </div>
                    <div class="emmsg-title"><?php echo htmlspecialchars((string) $cfg['title']); ?></div>
                    <div class="emmsg-desc"><?php echo nl2br(htmlspecialchars((string) $cfg['msg'])); ?></div>

                    <?php if ($detailMessage !== ''): ?>
                        <div class="emmsg-detail emmsg-detail--<?php echo htmlspecialchars($variant); ?>">
                            <?php echo nl2br(htmlspecialchars($detailMessage)); ?>
                            <?php if ($exception && $exception->getFile()): ?>
                                <br><br>
                                <strong>文件：</strong><?php echo htmlspecialchars($exception->getFile()); ?>
                                <br>
                                <strong>行号：</strong><?php echo $exception->getLine(); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($actions !== []): ?>
                        <div class="emmsg-actions">
                            <?php foreach ($actions as $action): ?>
                                <?php
                                $label = (string) ($action['label'] ?? '');
                                if ($label === '') {
                                    continue;
                                }
                                $icon = (string) ($action['icon'] ?? '');
                                $isPrimary = !empty($action['primary']);
                                $btnClass = $isPrimary ? 'emmsg-btn-primary' : 'emmsg-btn-secondary';
                                $href = trim((string) ($action['href'] ?? ''));
                                $onclick = trim((string) ($action['onclick'] ?? ''));
                                ?>
                                <?php if ($href !== ''): ?>
                                    <a class="emmsg-btn <?php echo $btnClass; ?>" href="<?php echo htmlspecialchars($href); ?>">
                                        <?php if ($icon !== ''): ?><span><?php echo $icon; ?></span><?php endif; ?>
                                        <span><?php echo htmlspecialchars($label); ?></span>
                                    </a>
                                <?php else: ?>
                                    <button type="button" class="emmsg-btn <?php echo $btnClass; ?>"<?php echo $onclick !== '' ? ' onclick="' . htmlspecialchars($onclick, ENT_QUOTES) . '"' : ''; ?>>
                                        <?php if ($icon !== ''): ?><span><?php echo $icon; ?></span><?php endif; ?>
                                        <span><?php echo htmlspecialchars($label); ?></span>
                                    </button>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ((string) $cfg['footer'] !== ''): ?>
                        <div class="emmsg-footer"><?php echo htmlspecialchars((string) $cfg['footer']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <script>
        (function () {
            function fitViewport() {
                var h = window.innerHeight;
                if (h > 0) {
                    document.documentElement.style.height = h + 'px';
                    document.body.style.minHeight = h + 'px';
                }
            }
            fitViewport();
            window.addEventListener('resize', fitViewport);
        })();
        </script>
        </body>
        </html>
        <?php
        exit;
    }
}
