<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * Captcha 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\Captcha} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class Captcha extends Facade
{
    /** @var \App\Support\Captcha|null */
    private static $service;

    public static function service(): \App\Support\Captcha
    {
        return self::$service ??= new \App\Support\Captcha();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\Captcha
    {
        return self::service();
    }

}
