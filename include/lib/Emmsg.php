<?php

/**
 * 统一错误页面静态工具类
 * 美化版 UI 优雅报错页面
 */
class Emmsg
{
    /**
     * 输出友好错误页面并终止程序
     * @param string $msg 自定义错误提示
     * @param \Exception|string|null $e 异常对象或错误详情字符串
     */
    public static function error(string $msg, $e = null): void
    {
        http_response_code(500);
        
        // 统一处理 $e 参数：转换为 Exception 对象或 null
        $exception = null;
        $detailMessage = '';
        
        if ($e instanceof \Exception) {
            $exception = $e;
            $detailMessage = $e->getMessage();
        } elseif (is_string($e) && $e !== '') {
            $detailMessage = $e;
        }
        
        ?>
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>系统错误</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    min-height: 100vh;
                    background: linear-gradient(135deg, #f0f7f5 0%, #e8f3f0 50%, #dce8e5 100%);
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Microsoft Yahei", sans-serif;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                    position: relative;
                    overflow: hidden;
                }
                
                /* 背景装饰 */
                body::before {
                    content: '';
                    position: absolute;
                    top: -50%;
                    right: -20%;
                    width: 600px;
                    height: 600px;
                    background: radial-gradient(circle, rgba(76, 125, 113, 0.08) 0%, transparent 70%);
                    border-radius: 50%;
                    pointer-events: none;
                }
                
                body::after {
                    content: '';
                    position: absolute;
                    bottom: -30%;
                    left: -15%;
                    width: 500px;
                    height: 500px;
                    background: radial-gradient(circle, rgba(76, 125, 113, 0.06) 0%, transparent 70%);
                    border-radius: 50%;
                    pointer-events: none;
                }
                
                .error-container {
                    width: 100%;
                    max-width: 560px;
                    position: relative;
                    z-index: 1;
                    animation: slideUp 0.5s ease-out;
                }
                
                @keyframes slideUp {
                    from {
                        opacity: 0;
                        transform: translateY(30px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                
                .error-card {
                    background: #ffffff;
                    border-radius: 20px;
                    box-shadow: 0 10px 40px rgba(76, 125, 113, 0.12), 0 2px 8px rgba(0, 0, 0, 0.04);
                    padding: 50px 40px;
                    text-align: center;
                    position: relative;
                    overflow: hidden;
                }
                
                .error-card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 4px;
                    background: linear-gradient(90deg, #4C7D71 0%, #5a9486 50%, #4C7D71 100%);
                }
                
                .error-icon-wrapper {
                    width: 90px;
                    height: 90px;
                    margin: 0 auto 25px;
                    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    position: relative;
                    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);
                }
                
                .error-icon {
                    font-size: 48px;
                    line-height: 1;
                    animation: pulse 2s ease-in-out infinite;
                }
                
                @keyframes pulse {
                    0%, 100% {
                        transform: scale(1);
                    }
                    50% {
                        transform: scale(1.05);
                    }
                }
                
                .error-title {
                    font-size: 26px;
                    color: #1a202c;
                    font-weight: 700;
                    margin-bottom: 16px;
                    letter-spacing: -0.5px;
                }
                
                .error-desc {
                    font-size: 15px;
                    color: #4a5568;
                    line-height: 1.8;
                    margin-bottom: 28px;
                    padding: 0 10px;
                }
                
                .error-detail {
                    background: #f7fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 10px;
                    padding: 20px;
                    font-size: 13px;
                    color: #718096;
                    text-align: left;
                    word-break: break-all;
                    line-height: 1.7;
                    max-height: 400px;
                    overflow-y: auto;
                    margin-top: 16px;
                    font-family: 'Consolas', 'Monaco', monospace;
                }
                
                .error-actions {
                    margin-top: 30px;
                    display: flex;
                    gap: 12px;
                    justify-content: center;
                    flex-wrap: wrap;
                }
                
                .error-btn {
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
                
                .error-btn-primary {
                    background: linear-gradient(135deg, #4C7D71 0%, #5a9486 100%);
                    color: #ffffff;
                    box-shadow: 0 2px 8px rgba(76, 125, 113, 0.25);
                }
                
                .error-btn-primary:hover {
                    background: linear-gradient(135deg, #427065 0%, #4C7D71 100%);
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(76, 125, 113, 0.3);
                }
                
                .error-btn-secondary {
                    background: #ffffff;
                    color: #4C7D71;
                    border: 1px solid #4C7D71;
                }
                
                .error-btn-secondary:hover {
                    background: #f0f7f5;
                    transform: translateY(-2px);
                    box-shadow: 0 2px 8px rgba(76, 125, 113, 0.15);
                }
                
                .error-footer {
                    margin-top: 24px;
                    padding-top: 20px;
                    border-top: 1px solid #e2e8f0;
                    font-size: 12px;
                    color: #a0aec0;
                }
                
                /* 滚动条样式 */
                .error-detail::-webkit-scrollbar {
                    width: 6px;
                }
                
                .error-detail::-webkit-scrollbar-track {
                    background: #f1f1f1;
                    border-radius: 3px;
                }
                
                .error-detail::-webkit-scrollbar-thumb {
                    background: #cbd5e0;
                    border-radius: 3px;
                }
                
                .error-detail::-webkit-scrollbar-thumb:hover {
                    background: #a0aec0;
                }
                
                /* 响应式 */
                @media (max-width: 640px) {
                    .error-card {
                        padding: 40px 25px;
                    }
                    
                    .error-title {
                        font-size: 22px;
                    }
                    
                    .error-icon-wrapper {
                        width: 75px;
                        height: 75px;
                    }
                    
                    .error-icon {
                        font-size: 40px;
                    }
                    
                    .error-actions {
                        flex-direction: column;
                    }
                    
                    .error-btn {
                        width: 100%;
                        justify-content: center;
                    }
                }
            </style>
        </head>
        <body>
        <div class="error-container">
            <div class="error-card">
                <div class="error-icon-wrapper">
                    <div class="error-icon">⚠️</div>
                </div>
                <div class="error-title">系统遇到了一点问题</div>
                <div class="error-desc">
                    <?php echo htmlspecialchars($msg); ?>
                </div>
                
                <?php if ($detailMessage !== ''): ?>
                    <div class="error-detail">
                        <?php echo nl2br(htmlspecialchars($detailMessage)); ?>
                        <?php if ($exception && $exception->getFile()): ?>
                            <br><br>
                            <strong>文件：</strong><?php echo htmlspecialchars($exception->getFile()); ?>
                            <br>
                            <strong>行号：</strong><?php echo $exception->getLine(); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="error-actions">
                    <button class="error-btn error-btn-primary" onclick="location.reload()">
                        <span>🔄</span>
                        <span>刷新页面</span>
                    </button>
                    <button class="error-btn error-btn-secondary" onclick="history.back()">
                        <span>←</span>
                        <span>返回上一页</span>
                    </button>
                </div>
                
                <div class="error-footer">
                    如果问题持续存在，请联系技术支持
                </div>
            </div>
        </div>
        </body>
        </html>
        <?php
        exit;
    }
}