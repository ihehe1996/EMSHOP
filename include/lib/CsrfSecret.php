<?php

declare(strict_types=1);

/**
 * CSRF cookie 签名密钥（兼容门面）。
 *
 * 实际实现已迁到 App\Support\CsrfSecret 实例服务；这里保留原有静态 API，
 * Csrf 和后台登录入口的调用点都不用改。
 */
final class CsrfSecret
{
    /** @var \App\Support\CsrfSecret|null */
    private static $service;

    /**
     * 获取内部密钥服务实例。
     */
    public static function instance(): \App\Support\CsrfSecret
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\CsrfSecret::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/CsrfSecret.php';
            }
            self::$service = new \App\Support\CsrfSecret();
        }

        return self::$service;
    }

    /**
     * 读取密钥；没有时返回空串，不写文件。
     */
    public static function get(): string
    {
        return self::instance()->get();
    }

    /**
     * 确保密钥存在；老站没有时生成并写回 config.php。
     */
    public static function ensure(): string
    {
        return self::instance()->ensure();
    }
}
