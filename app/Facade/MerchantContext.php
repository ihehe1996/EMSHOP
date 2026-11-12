<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * MerchantContext 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\MerchantContext} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class MerchantContext extends Facade
{
    /** @var \App\Support\MerchantContext|null */
    private static $service;

    public static function service(): \App\Support\MerchantContext
    {
        return self::$service ??= new \App\Support\MerchantContext();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\MerchantContext
    {
        return self::service();
    }

}
