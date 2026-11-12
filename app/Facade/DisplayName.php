<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * DisplayName 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\DisplayName} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class DisplayName extends Facade
{
    /** @var \App\Support\DisplayName|null */
    private static $service;

    public static function service(): \App\Support\DisplayName
    {
        return self::$service ??= new \App\Support\DisplayName();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\DisplayName
    {
        return self::service();
    }

}
