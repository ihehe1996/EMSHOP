<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * Money 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\Money} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class Money extends Facade
{
    /** @var \App\Support\Money|null */
    private static $service;

    public static function service(): \App\Support\Money
    {
        return self::$service ??= new \App\Support\Money();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\Money
    {
        return self::service();
    }

    public const SCALE = \App\Support\Money::SCALE;
    public const MAX_DECIMALS = \App\Support\Money::MAX_DECIMALS;

}
