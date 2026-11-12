<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * Cache 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\Cache} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class Cache extends Facade
{
    /** @var \App\Support\Cache|null */
    private static $service;

    public static function service(): \App\Support\Cache
    {
        return self::$service ??= new \App\Support\Cache();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\Cache
    {
        return self::service();
    }

}
