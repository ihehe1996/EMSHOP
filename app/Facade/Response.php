<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * Response 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Http\Response} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class Response extends Facade
{
    /** @var \App\Http\Response|null */
    private static $service;

    public static function service(): \App\Http\Response
    {
        return self::$service ??= new \App\Http\Response();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Http\Response
    {
        return self::service();
    }

}
