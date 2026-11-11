<?php

declare(strict_types=1);

/**
 * Key-Value 配置存储（兼容门面）。
 *
 * 实际实现已迁到 App\Support\Option 实例服务；这里保留原有静态 API，
 * 插件与 shutdown 时的 Option::flush() 调用都不用改。
 */
final class Option
{
    /** @var \App\Support\Option|null */
    private static $service;

    /**
     * 获取内部配置存储服务实例。
     */
    public static function instance(): \App\Support\Option
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\Option::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/Option.php';
            }
            self::$service = new \App\Support\Option();
        }

        return self::$service;
    }

    /**
     * 获取配置值。
     *
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $name, $default = null)
    {
        return self::instance()->get($name, $default);
    }

    /**
     * 设置配置值（自动 JSON 编码数组/对象）。
     *
     * @param mixed $value
     */
    public static function set(string $name, $value): bool
    {
        return self::instance()->set($name, $value);
    }

    /**
     * 删除配置项。
     */
    public static function delete(string $name): bool
    {
        return self::instance()->delete($name);
    }

    /**
     * 批量获取。
     *
     * @param array<string, mixed> $names
     * @return array<string, mixed>
     */
    public static function gets(array $names, $default = null): array
    {
        return self::instance()->gets($names, $default);
    }

    /**
     * 刷新缓存。
     */
    public static function flush(): void
    {
        self::instance()->flush();
    }
}
