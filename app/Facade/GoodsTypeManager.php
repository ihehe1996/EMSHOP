<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * GoodsTypeManager 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\GoodsTypeManager} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class GoodsTypeManager extends Facade
{
    /** @var \App\Support\GoodsTypeManager|null */
    private static $service;

    public static function service(): \App\Support\GoodsTypeManager
    {
        return self::$service ??= new \App\Support\GoodsTypeManager();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\GoodsTypeManager
    {
        return self::service();
    }

}
