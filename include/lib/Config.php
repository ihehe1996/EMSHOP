<?php

declare(strict_types=1);

/**
 * 系统配置（兼容门面）。
 *
 * 实际实现已迁到 App\Support\Config 实例服务；这里保留原有静态 API，
 * 现有调用点、插件和模板都不用改。
 */
final class Config
{
    /** @var \App\Support\Config|null */
    private static $service;

    /**
     * 获取内部配置服务实例。
     */
    public static function instance(): \App\Support\Config
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\Config::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/Config.php';
            }
            self::$service = new \App\Support\Config();
        }

        return self::$service;
    }

    /**
     * 读取全部系统配置。
     *
     * @return array<string, string>
     */
    public static function load(): array
    {
        return self::instance()->load();
    }

    /**
     * 按名称读取系统配置。
     */
    public static function get(string $name, string $default = ''): string
    {
        return self::instance()->get($name, $default);
    }

    /**
     * 保存或更新一条系统配置（不存在则添加，存在则更新）。
     */
    public static function set(string $name, string $value): bool
    {
        return self::instance()->set($name, $value);
    }

    /**
     * 刷新当前请求内的配置缓存。
     *
     * @return array<string, string>
     */
    public static function reload(): array
    {
        return self::instance()->reload();
    }
}
