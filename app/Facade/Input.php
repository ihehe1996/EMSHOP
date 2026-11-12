<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * Input 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\Input} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class Input extends Facade
{
    /** @var \App\Support\Input|null */
    private static $service;

    public static function service(): \App\Support\Input
    {
        return self::$service ??= new \App\Support\Input();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\Input
    {
        return self::service();
    }

}
