<?php

declare(strict_types=1);

/**
 * 输入接收（兼容门面）。
 *
 * 实际实现已迁到 App\Support\Input 实例服务；这里保留原有静态 API，
 * 现有调用点、插件和模板都不用改。
 */
final class Input
{
    /** @var \App\Support\Input|null */
    private static $service;

    /**
     * 获取内部输入服务实例。
     */
    public static function instance(): \App\Support\Input
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\Input::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/Input.php';
            }
            self::$service = new \App\Support\Input();
        }

        return self::$service;
    }

    /**
     * 获取 POST 参数。
     *
     * @param mixed $default
     * @return mixed
     */
    public static function post(string $key, $default = '')
    {
        return self::instance()->post($key, $default);
    }

    /**
     * 获取 GET 参数。
     *
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = '')
    {
        return self::instance()->get($key, $default);
    }

    /**
     * 获取全部 POST 数据。
     *
     * @return array<string, mixed>
     */
    public static function allPost(): array
    {
        return self::instance()->allPost();
    }

    /**
     * 获取全部 GET 数据。
     *
     * @return array<string, mixed>
     */
    public static function allGet(): array
    {
        return self::instance()->allGet();
    }

    public static function postStr(string $key, string $default = ''): string
    {
        return self::instance()->postStr($key, $default);
    }

    public static function getStr(string $key, string $default = ''): string
    {
        return self::instance()->getStr($key, $default);
    }

    public static function postInt(string $key, int $default = 0): int
    {
        return self::instance()->postInt($key, $default);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        return self::instance()->getInt($key, $default);
    }

    public static function postStrVar(string $key, string $default = ''): string
    {
        return self::instance()->postStrVar($key, $default);
    }

    public static function getStrVar(string $key, string $default = ''): string
    {
        return self::instance()->getStrVar($key, $default);
    }
}
