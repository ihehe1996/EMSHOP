<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * LogSanitizer 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\LogSanitizer} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class LogSanitizer extends Facade
{
    /** @var \App\Support\LogSanitizer|null */
    private static $service;

    public static function service(): \App\Support\LogSanitizer
    {
        return self::$service ??= new \App\Support\LogSanitizer();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\LogSanitizer
    {
        return self::service();
    }

}
