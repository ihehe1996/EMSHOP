<?php

declare(strict_types=1);

/**
 * 请求方法（兼容门面）。
 *
 * 实际实现已迁到 App\Http\Request 实例服务；这里保留原有静态 API，
 * 现有调用点、插件和模板都不用改。
 */
final class Request
{
    /** @var \App\Http\Request|null */
    private static $service;

    /**
     * 获取内部请求服务实例。
     */
    public static function instance(): \App\Http\Request
    {
        if (self::$service === null) {
            if (!class_exists(\App\Http\Request::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Http/Request.php';
            }
            self::$service = new \App\Http\Request();
        }

        return self::$service;
    }

    /**
     * 返回当前请求方法。
     */
    public static function method(): string
    {
        return self::instance()->method();
    }

    /**
     * 判断当前是否为 POST 请求。
     */
    public static function isPost(): bool
    {
        return self::instance()->isPost();
    }

    /**
     * 判断当前是否为 GET 请求。
     */
    public static function isGet(): bool
    {
        return self::instance()->isGet();
    }

    /**
     * 判断当前是否为 Pjax 请求。
     */
    public static function isPjax(): bool
    {
        return self::instance()->isPjax();
    }

    /**
     * 当前请求的站点根地址（协议 + 域名 + 子目录）。
     */
    public static function baseUrl(): string
    {
        return self::instance()->baseUrl();
    }
}
