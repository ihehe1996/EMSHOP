<?php

declare(strict_types=1);

/**
 * 下载地址白名单校验（兼容门面）。
 *
 * 实际实现已迁到 App\Support\DownloadUrlGuard 实例服务；这里保留原有静态 API，
 * 应用商店、在线升级与发货回调的调用点都不用改。
 */
final class DownloadUrlGuard
{
    /** @var \App\Support\DownloadUrlGuard|null */
    private static $service;

    /**
     * 获取内部下载地址校验服务实例。
     */
    public static function instance(): \App\Support\DownloadUrlGuard
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\DownloadUrlGuard::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/DownloadUrlGuard.php';
            }
            self::$service = new \App\Support\DownloadUrlGuard();
        }

        return self::$service;
    }

    /**
     * 该下载地址是否被允许。
     */
    public static function isAllowed(string $url, string $baseUrl, bool $allowInsecureHttp = false): bool
    {
        return self::instance()->isAllowed($url, $baseUrl, $allowInsecureHttp);
    }

    /**
     * 出站请求的通用安全校验：必须是 http(s)，且目标不指向内网/保留地址。
     */
    public static function isPublicHttpUrl(string $url): bool
    {
        return self::instance()->isPublicHttpUrl($url);
    }
}
