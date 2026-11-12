<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * RateLimit 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\RateLimit} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class RateLimit extends Facade
{
    /** @var \App\Support\RateLimit|null */
    private static $service;

    public static function service(): \App\Support\RateLimit
    {
        return self::$service ??= new \App\Support\RateLimit();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\RateLimit
    {
        return self::service();
    }

}
