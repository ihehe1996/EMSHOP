<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * Config 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\Config} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class Config extends Facade
{
    /** @var \App\Support\Config|null */
    private static $service;

    public static function service(): \App\Support\Config
    {
        return self::$service ??= new \App\Support\Config();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\Config
    {
        return self::service();
    }

}
