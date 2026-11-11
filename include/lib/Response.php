<?php

declare(strict_types=1);

/**
 * 统一响应（兼容门面）。
 *
 * 实际实现已迁到 App\Http\Response 实例服务；这里保留原有静态 API，
 * 现有调用点、插件和模板都不用改。
 *
 * 注意：方法会以 exit 结束请求，与迁移前一致。
 */
final class Response
{
    /** @var \App\Http\Response|null */
    private static $service;

    /**
     * 获取内部响应服务实例。
     */
    public static function instance(): \App\Http\Response
    {
        if (self::$service === null) {
            if (!class_exists(\App\Http\Response::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Http/Response.php';
            }
            self::$service = new \App\Http\Response();
        }

        return self::$service;
    }

    /**
     * 输出标准 JSON 结构。
     *
     * @param array<string, mixed> $data
     */
    public static function json(int $code, string $msg, array $data = []): void
    {
        self::instance()->json($code, $msg, $data);
    }

    /**
     * 输出成功 JSON。
     *
     * @param array<string, mixed> $data
     */
    public static function success(string $msg, array $data = []): void
    {
        self::instance()->success($msg, $data);
    }

    /**
     * 输出失败 JSON。
     *
     * @param array<string, mixed> $data
     */
    public static function error(string $msg, array $data = []): void
    {
        self::instance()->error($msg, $data);
    }

    /**
     * 执行页面跳转。
     */
    public static function redirect(string $url): void
    {
        self::instance()->redirect($url);
    }
}
