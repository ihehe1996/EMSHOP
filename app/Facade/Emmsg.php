<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * Emmsg 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\Emmsg} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class Emmsg extends Facade
{
    /** @var \App\Support\Emmsg|null */
    private static $service;

    public static function service(): \App\Support\Emmsg
    {
        return self::$service ??= new \App\Support\Emmsg();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\Emmsg
    {
        return self::service();
    }

}
