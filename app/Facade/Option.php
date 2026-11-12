<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * Option 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\Option} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class Option extends Facade
{
    /** @var \App\Support\Option|null */
    private static $service;

    public static function service(): \App\Support\Option
    {
        return self::$service ??= new \App\Support\Option();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\Option
    {
        return self::service();
    }

}
