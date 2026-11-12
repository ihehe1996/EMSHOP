<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * GuestToken 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\GuestToken} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class GuestToken extends Facade
{
    /** @var \App\Support\GuestToken|null */
    private static $service;

    public static function service(): \App\Support\GuestToken
    {
        return self::$service ??= new \App\Support\GuestToken();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\GuestToken
    {
        return self::service();
    }

}
