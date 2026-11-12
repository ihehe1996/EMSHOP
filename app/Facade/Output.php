<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * Output 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Http\Output} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class Output extends Facade
{
    /** @var \App\Http\Output|null */
    private static $service;

    public static function service(): \App\Http\Output
    {
        return self::$service ??= new \App\Http\Output();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Http\Output
    {
        return self::service();
    }

}
