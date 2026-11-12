<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * Request 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Http\Request} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class Request extends Facade
{
    /** @var \App\Http\Request|null */
    private static $service;

    public static function service(): \App\Http\Request
    {
        return self::$service ??= new \App\Http\Request();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Http\Request
    {
        return self::service();
    }

}
