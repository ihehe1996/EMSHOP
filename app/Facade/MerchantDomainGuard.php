<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * MerchantDomainGuard 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\MerchantDomainGuard} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class MerchantDomainGuard extends Facade
{
    /** @var \App\Support\MerchantDomainGuard|null */
    private static $service;

    public static function service(): \App\Support\MerchantDomainGuard
    {
        return self::$service ??= new \App\Support\MerchantDomainGuard();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\MerchantDomainGuard
    {
        return self::service();
    }

}
