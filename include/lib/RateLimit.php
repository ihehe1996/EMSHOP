<?php

declare(strict_types=1);

/**
 * 通用限流（兼容门面）。
 *
 * 实际实现已迁到 App\Support\RateLimit 实例服务；这里保留原有静态 API，
 * 现有调用点、插件和控制器都不用改。
 */
final class RateLimit
{
    /** @var \App\Support\RateLimit|null */
    private static $service;

    /**
     * 获取内部限流服务实例。
     */
    public static function instance(): \App\Support\RateLimit
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\RateLimit::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/RateLimit.php';
            }
            self::$service = new \App\Support\RateLimit();
        }

        return self::$service;
    }

    /**
     * 是否已超出限制（仅查不增计数）。
     */
    public static function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return self::instance()->tooManyAttempts($key, $maxAttempts);
    }

    /**
     * 当前窗口内已记录次数。
     */
    public static function attempts(string $key): int
    {
        return self::instance()->attempts($key);
    }

    /**
     * 记录一次命中，返回累计后的计数。
     */
    public static function hit(string $key, int $windowSeconds): int
    {
        return self::instance()->hit($key, $windowSeconds);
    }

    /**
     * 距离当前窗口失效还有多少秒；未限流或已过期返回 0。
     */
    public static function availableIn(string $key): int
    {
        return self::instance()->availableIn($key);
    }

    /**
     * 清掉某 key 的计数。
     */
    public static function clear(string $key): void
    {
        self::instance()->clear($key);
    }

    /**
     * 取请求方真实 IP，兼容常见反代头。
     */
    public static function clientIp(): string
    {
        return self::instance()->clientIp();
    }
}
