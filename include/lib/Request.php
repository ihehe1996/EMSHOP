<?php

declare(strict_types=1);

/**
 * 请求方法封装。
 *
 * 后续判断 GET、POST、AJAX 等请求时统一从这里取，避免入口文件直接操作 $_SERVER。
 */
final class Request
{
    /**
     * 返回当前请求方法。
     */
    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    /**
     * 判断当前是否为 POST 请求。
     */
    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    /**
     * 判断当前是否为 GET 请求。
     */
    public static function isGet(): bool
    {
        return self::method() === 'GET';
    }

    /**
     * 判断当前是否为 Pjax 请求。
     */
    public static function isPjax(): bool
    {
        return !empty($_SERVER['HTTP_X_PJAX']);
    }
}
