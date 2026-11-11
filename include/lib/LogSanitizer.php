<?php

declare(strict_types=1);

/**
 * 日志脱敏（兼容门面）。
 *
 * 实际实现已迁到 App\Support\LogSanitizer 实例服务；这里保留原有静态 API，
 * CliServer::log() 等调用点都不用改。
 */
final class LogSanitizer
{
    /** @var \App\Support\LogSanitizer|null */
    private static $service;

    /**
     * 获取内部日志脱敏服务实例。
     */
    public static function instance(): \App\Support\LogSanitizer
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\LogSanitizer::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/LogSanitizer.php';
            }
            self::$service = new \App\Support\LogSanitizer();
        }

        return self::$service;
    }

    /**
     * 对一条日志消息做脱敏。
     */
    public static function sanitize(string $message): string
    {
        return self::instance()->sanitize($message);
    }
}
