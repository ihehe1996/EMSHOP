<?php

declare(strict_types=1);

/**
 * 游客身份标识（兼容门面）。
 *
 * 实际实现已迁到 App\Support\GuestToken 实例服务；这里保留原有静态 API，
 * 现有调用点、插件和模板都不用改。
 */
final class GuestToken
{
    /** @var \App\Support\GuestToken|null */
    private static $service;

    /**
     * 获取内部游客身份服务实例。
     */
    public static function instance(): \App\Support\GuestToken
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\GuestToken::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/GuestToken.php';
            }
            self::$service = new \App\Support\GuestToken();
        }

        return self::$service;
    }

    /**
     * 获取当前访客的 guest_token。
     * Cookie 缺失 / 签名无效时自动签发新的 v2 身份。
     */
    public static function get(): string
    {
        return self::instance()->get();
    }

    /**
     * 当前请求是否携带有效的 v2 签名身份。
     * 不签发新 Cookie，供「只允许已验证身份」的敏感操作判断。
     */
    public static function hasValidCookie(): bool
    {
        return self::instance()->hasValidCookie();
    }
}
