<?php

declare(strict_types=1);

/**
 * 后台任务心跳（兼容门面）。
 *
 * 实际实现已迁到 App\Support\WorkerHeartbeat 实例服务；这里保留原有静态 API 和常量，
 * CliServerManager、worker 任务与后台状态页的调用点都不用改。
 */
final class WorkerHeartbeat
{
    public const CAPABILITY_DELIVERY = \App\Support\WorkerHeartbeat::CAPABILITY_DELIVERY;
    public const WORKER_QUEUE = \App\Support\WorkerHeartbeat::WORKER_QUEUE;
    public const WORKER_ORDER_POLL = \App\Support\WorkerHeartbeat::WORKER_ORDER_POLL;
    public const WORKER_GOODS_SYNC = \App\Support\WorkerHeartbeat::WORKER_GOODS_SYNC;
    public const TTL = \App\Support\WorkerHeartbeat::TTL;

    /** @var \App\Support\WorkerHeartbeat|null */
    private static $service;

    /**
     * 获取内部心跳服务实例。
     */
    public static function instance(): \App\Support\WorkerHeartbeat
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\WorkerHeartbeat::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/WorkerHeartbeat.php';
            }
            self::$service = new \App\Support\WorkerHeartbeat();
        }

        return self::$service;
    }

    /**
     * 运行目录：与 CliServer 共用 content/server。
     */
    public static function runtimeDir(): string
    {
        return self::instance()->runtimeDir();
    }

    public static function hostFile(): string
    {
        return self::instance()->hostFile();
    }

    /** 由管家进程调用，标记「任务服务主进程存活」 */
    public static function touchHost(): void
    {
        self::instance()->touchHost();
    }

    /** 宿主进程是否在跑 */
    public static function hostAlive(int $ttl = self::TTL): bool
    {
        return self::instance()->hostAlive($ttl);
    }

    /** 宿主心跳距今秒数；文件不存在返回 null */
    public static function hostAge(): ?int
    {
        return self::instance()->hostAge();
    }

    /** worker 心跳文件路径（type 会做白名单过滤） */
    public static function workerFile(string $type): string
    {
        return self::instance()->workerFile($type);
    }

    /** 由 worker 自己调用：一轮任务跑完后刷新自己的心跳 */
    public static function touchWorker(string $type): void
    {
        self::instance()->touchWorker($type);
    }

    /** 指定类型的 worker 最近是否还在干活 */
    public static function isAlive(string $type, int $ttl = self::TTL): bool
    {
        return self::instance()->isAlive($type, $ttl);
    }

    /** 指定类型 worker 的心跳距今秒数；从未跑过返回 null */
    public static function age(string $type): ?int
    {
        return self::instance()->age($type);
    }

    /**
     * 批量查询各 worker 的存活情况。
     *
     * @param list<string> $types
     * @return array<string, array{alive: bool, age: int|null}>
     */
    public static function status(array $types): array
    {
        return self::instance()->status($types);
    }

    /** 清掉所有能力心跳（停服时调用） */
    public static function clearAll(array $types = []): void
    {
        self::instance()->clearAll($types);
    }
}
