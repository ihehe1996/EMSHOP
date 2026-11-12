<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * Database 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\Database} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class Database extends Facade
{
    /** @var \App\Support\Database|null */
    private static $service;

    public static function service(): \App\Support\Database
    {
        return self::$service ??= new \App\Support\Database();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\Database
    {
        return self::service();
    }

    public const MIN_MYSQL_VERSION = \App\Support\Database::MIN_MYSQL_VERSION;

}
