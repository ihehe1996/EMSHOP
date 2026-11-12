<?php

declare(strict_types=1);

namespace App\Http;

/**
 * 统一响应服务（实例实现）。
 *
 * 负责 JSON 输出和页面跳转，避免入口文件中重复写 header + exit。
 * 兼容门面见 app/Facade/Response.php。
 *
 * 与 Request 不同，本服务的方法以 exit 结束请求 —— 这是原有契约的一部分。
 */
final class Response
{
    /**
     * 输出标准 JSON 结构。
     *
     * @param array<string, mixed> $data
     */
    public function json(int $code, string $msg, array $data = []): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * 输出成功 JSON。
     *
     * @param array<string, mixed> $data
     */
    public function success(string $msg, array $data = []): void
    {
        $this->json(200, $msg, $data);
    }

    /**
     * 输出失败 JSON。
     *
     * @param array<string, mixed> $data
     */
    public function error(string $msg, array $data = []): void
    {
        $this->json(400, $msg, $data);
    }

    /**
     * 执行页面跳转。
     */
    public function redirect(string $url): void
    {
        if (!headers_sent()) {
            header('Location: ' . $url);
        }
        exit;
    }
}
