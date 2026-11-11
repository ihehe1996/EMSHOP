<?php

declare(strict_types=1);

/**
 * CSRF 防护（兼容门面）。
 *
 * 实际实现已迁到 App\Support\Csrf 实例服务；这里保留原有静态 API，
 * 现有调用点、插件和模板都不用改。
 */
final class Csrf
{
    /** @var \App\Support\Csrf|null */
    private static $service;

    /**
     * 获取内部 CSRF 服务实例。
     */
    public static function instance(): \App\Support\Csrf
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\Csrf::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/Csrf.php';
            }
            self::$service = new \App\Support\Csrf();
        }

        return self::$service;
    }

    /**
     * 获取当前 CSRF token。
     */
    public static function token(): string
    {
        return self::instance()->token();
    }

    /**
     * 校验 token 是否合法。
     */
    public static function validate(string $token): bool
    {
        return self::instance()->validate($token);
    }

    /**
     * 校验 token；没有 token 时退化为「仅允许同源请求」。
     */
    public static function validateOrSameOrigin(string $token): bool
    {
        return self::instance()->validateOrSameOrigin($token);
    }

    /**
     * 刷新 token（旧 token 进入宽限期）。
     */
    public static function refresh(int $graceSeconds = 1800): string
    {
        return self::instance()->refresh($graceSeconds);
    }

    /**
     * 清除宽限期内的旧 token。
     */
    public static function clearGrace(): void
    {
        self::instance()->clearGrace();
    }
}
