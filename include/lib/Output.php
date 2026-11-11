<?php

declare(strict_types=1);

/**
 * 输出响应（兼容门面）。
 *
 * 实际实现已迁到 App\Http\Output 实例服务；这里保留原有静态 API，
 * 旧插件里的 Output::ok() / fail() / json() 调用不用改。
 */
final class Output
{
    /** @var \App\Http\Output|null */
    private static $service;

    /**
     * 获取内部输出服务实例。
     */
    public static function instance(): \App\Http\Output
    {
        if (self::$service === null) {
            if (!class_exists(\App\Http\Output::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Http/Output.php';
            }
            self::$service = new \App\Http\Output();
        }

        return self::$service;
    }

    /**
     * 输出成功响应。
     *
     * @param array<string, mixed> $data
     */
    public static function ok(string $msg = '操作成功', array $data = []): void
    {
        self::instance()->ok($msg, $data);
    }

    /**
     * 输出失败响应。
     *
     * @param array<string, mixed> $data
     */
    public static function fail(string $msg = '操作失败', array $data = []): void
    {
        self::instance()->fail($msg, $data);
    }

    /**
     * 通用 JSON 输出。
     *
     * @param array<string, mixed> $data
     */
    public static function json(int $code, string $msg, array $data = []): void
    {
        self::instance()->json($code, $msg, $data);
    }
}
