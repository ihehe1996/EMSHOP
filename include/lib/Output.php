<?php

declare(strict_types=1);

/**
 * 输出响应类（Response 别名，兼容旧插件写法）。
 */
final class Output
{
    /**
     * 输出成功响应。
     *
     * @param array<string, mixed> $data
     */
    public static function ok(string $msg = '操作成功', array $data = []): void
    {
        Response::success($msg, $data);
    }

    /**
     * 输出失败响应。
     *
     * @param array<string, mixed> $data
     */
    public static function fail(string $msg = '操作失败', array $data = []): void
    {
        Response::error($msg, $data);
    }

    /**
     * 通用 JSON 输出。
     *
     * @param array<string, mixed> $data
     */
    public static function json(int $code, string $msg, array $data = []): void
    {
        Response::json($code, $msg, $data);
    }
}
