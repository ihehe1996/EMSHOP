<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * WorkerHeartbeat 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\WorkerHeartbeat} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class WorkerHeartbeat extends Facade
{
    /** @var \App\Support\WorkerHeartbeat|null */
    private static $service;

    public static function service(): \App\Support\WorkerHeartbeat
    {
        return self::$service ??= new \App\Support\WorkerHeartbeat();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\WorkerHeartbeat
    {
        return self::service();
    }

    public const CAPABILITY_DELIVERY = \App\Support\WorkerHeartbeat::CAPABILITY_DELIVERY;
    public const WORKER_QUEUE = \App\Support\WorkerHeartbeat::WORKER_QUEUE;
    public const WORKER_ORDER_POLL = \App\Support\WorkerHeartbeat::WORKER_ORDER_POLL;
    public const WORKER_GOODS_SYNC = \App\Support\WorkerHeartbeat::WORKER_GOODS_SYNC;
    public const TTL = \App\Support\WorkerHeartbeat::TTL;

}
