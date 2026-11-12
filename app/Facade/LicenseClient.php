<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * LicenseClient 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\LicenseClient} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class LicenseClient extends Facade
{
    /** @var \App\Support\LicenseClient|null */
    private static $service;

    public static function service(): \App\Support\LicenseClient
    {
        return self::$service ??= new \App\Support\LicenseClient();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\LicenseClient
    {
        return self::service();
    }

    public const OFFICIAL_BASE_URL = \App\Support\LicenseClient::OFFICIAL_BASE_URL;
    public const DEV_BASE_URL = \App\Support\LicenseClient::DEV_BASE_URL;

}
