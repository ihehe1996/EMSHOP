<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * Csrf 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\Csrf} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class Csrf extends Facade
{
    /** @var \App\Support\Csrf|null */
    private static $service;

    public static function service(): \App\Support\Csrf
    {
        return self::$service ??= new \App\Support\Csrf();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\Csrf
    {
        return self::service();
    }

}
