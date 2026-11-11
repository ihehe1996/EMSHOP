<?php

declare(strict_types=1);

/**
 * 文件缓存（兼容门面）。
 *
 * 实际实现已迁到 App\Support\Cache 实例服务；这里保留原有静态 API，
 * 现有调用点、插件和模板都不用改。新代码可以通过容器拿到同一个实例：
 *   \App\Core\Application::instance()->container()->make(\App\Support\Cache::class)
 */
final class Cache
{
    /** @var \App\Support\Cache|null */
    private static $service;

    /**
     * 获取内部缓存服务实例。
     */
    public static function instance(): \App\Support\Cache
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\Cache::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/Cache.php';
            }
            self::$service = new \App\Support\Cache();
        }

        return self::$service;
    }

    /**
     * 初始化缓存目录。
     */
    public static function init(?string $directory = null): void
    {
        self::instance()->init($directory);
    }

    /**
     * 设置缓存前缀（用于多项目隔离）。
     */
    public static function setPrefix(string $prefix): void
    {
        self::instance()->setPrefix($prefix);
    }

    /**
     * 设置默认 TTL。
     */
    public static function setDefaultTtl(int $seconds): void
    {
        self::instance()->setDefaultTtl($seconds);
    }

    /**
     * 获取缓存。未命中或已过期返回 null。
     *
     * @return mixed|null
     */
    public static function get(string $key)
    {
        return self::instance()->get($key);
    }

    /**
     * 设置缓存。
     *
     * @param mixed $data
     * @param mixed $ttlArg
     */
    public static function set(string $key, $data, $ttlArg = 0, string $group = ''): bool
    {
        return self::instance()->set($key, $data, $ttlArg, $group);
    }

    /**
     * 删除指定缓存。
     */
    public static function delete(string $key): bool
    {
        return self::instance()->delete($key);
    }

    /**
     * 删除指定分组下的所有缓存。
     */
    public static function deleteGroup(string $group): bool
    {
        return self::instance()->deleteGroup($group);
    }

    /**
     * 清空所有缓存。
     */
    public static function clear(): bool
    {
        return self::instance()->clear();
    }

    /**
     * 判断缓存是否存在且未过期。
     */
    public static function has(string $key): bool
    {
        return self::instance()->has($key);
    }

    /**
     * 记忆模式：缓存存在则返回，不存在则执行回调并缓存结果。
     *
     * @return mixed
     */
    public static function remember(string $key, string $group, callable $callback, int $ttl = 0)
    {
        return self::instance()->remember($key, $group, $callback, $ttl);
    }
}
