<?php

declare(strict_types=1);

/**
 * 统一响应类。
 *
 * 负责 JSON 输出和页面跳转，避免入口文件中重复写 header + exit。
 */
final class Response
{
    /**
     * 输出标准 JSON 结构。
     *
     * @param array<string, mixed> $data
     */
    public static function json(int $code, string $msg, array $data = []): void
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
    public static function success(string $msg, array $data = []): void
    {
        self::json(200, $msg, $data);
    }

    /**
     * 输出失败 JSON。
     *
     * @param array<string, mixed> $data
     */
    public static function error(string $msg, array $data = []): void
    {
        self::json(400, $msg, $data);
    }

    /**
     * 执行页面跳转。
     */
    public static function redirect(string $url): void
    {
        if (!headers_sent()) {
            header('Location: ' . $url);
        }
        exit;
    }
}
