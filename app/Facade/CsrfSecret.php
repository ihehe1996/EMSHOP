<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * CsrfSecret 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\CsrfSecret} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class CsrfSecret extends Facade
{
    /** @var \App\Support\CsrfSecret|null */
    private static $service;

    public static function service(): \App\Support\CsrfSecret
    {
        return self::$service ??= new \App\Support\CsrfSecret();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\CsrfSecret
    {
        return self::service();
    }

}
