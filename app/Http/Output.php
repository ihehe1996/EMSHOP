<?php

declare(strict_types=1);

namespace App\Http;

/**
 * 输出响应服务（实例实现，Response 的兼容别名）。
 *
 * 无状态：方法直接转调 Response 门面并以 exit 结束请求。
 * 兼容门面见 app/Facade/Output.php。
 */
final class Output
{
    /**
     * 输出成功响应。
     *
     * @param array<string, mixed> $data
     */
    public function ok(string $msg = '操作成功', array $data = []): void
    {
        \Response::success($msg, $data);
    }

    /**
     * 输出失败响应。
     *
     * @param array<string, mixed> $data
     */
    public function fail(string $msg = '操作失败', array $data = []): void
    {
        \Response::error($msg, $data);
    }

    /**
     * 通用 JSON 输出。
     *
     * @param array<string, mixed> $data
     */
    public function json(int $code, string $msg, array $data = []): void
    {
        \Response::json($code, $msg, $data);
    }
}
