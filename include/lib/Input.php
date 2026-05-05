<?php

declare(strict_types=1);

/**
 * 输入接收类。
 *
 * 专门负责接收 GET、POST 参数，并做基础默认值和字符串去空白处理。
 */
final class Input
{
    /**
     * 获取 POST 参数。
     *
     * @param mixed $default
     * @return mixed
     */
    public static function post(string $key, $default = '')
    {
        return self::value($_POST, $key, $default);
    }

    /**
     * 获取 GET 参数。
     *
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = '')
    {
        return self::value($_GET, $key, $default);
    }

    /**
     * 获取全部 POST 数据。
     *
     * @return array<string, mixed>
     */
    public static function allPost(): array
    {
        return self::normalize($_POST);
    }

    /**
     * 获取全部 GET 数据。
     *
     * @return array<string, mixed>
     */
    public static function allGet(): array
    {
        return self::normalize($_GET);
    }

    /**
     * @param array<string, mixed> $source
     * @param mixed $default
     * @return mixed
     */
    private static function value(array $source, string $key, $default)
    {
        if (!array_key_exists($key, $source)) {
            return $default;
        }

        return self::clean($source[$key]);
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private static function normalize(array $source): array
    {
        $data = [];
        foreach ($source as $key => $value) {
            $data[$key] = self::clean($value);
        }

        return $data;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function clean($value)
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_array($value)) {
            $cleaned = [];
            foreach ($value as $key => $item) {
                $cleaned[$key] = self::clean($item);
            }
            return $cleaned;
        }

        return $value;
    }

    /**
     * 获取 POST 字符串参数（自动去首尾空白）。
     */
    public static function postStr(string $key, string $default = ''): string
    {
        return (string) self::post($key, $default);
    }

    /**
     * 获取 GET 字符串参数（自动去首尾空白）。
     */
    public static function getStr(string $key, string $default = ''): string
    {
        return (string) self::get($key, $default);
    }

    /**
     * 获取 POST 整数参数。
     */
    public static function postInt(string $key, int $default = 0): int
    {
        return (int) self::post($key, $default);
    }

    /**
     * 获取 GET 整数参数。
     */
    public static function getInt(string $key, int $default = 0): int
    {
        return (int) self::get($key, $default);
    }

    // 以下为兼容别名
    public static function postStrVar(string $key, string $default = ''): string
    {
        return self::postStr($key, $default);
    }

    public static function getStrVar(string $key, string $default = ''): string
    {
        return self::getStr($key, $default);
    }
}
