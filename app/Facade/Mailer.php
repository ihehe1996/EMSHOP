<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * Mailer 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\Mailer} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class Mailer extends Facade
{
    /** @var \App\Support\Mailer|null */
    private static $service;

    public static function service(): \App\Support\Mailer
    {
        return self::$service ??= new \App\Support\Mailer();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\Mailer
    {
        return self::service();
    }

}
