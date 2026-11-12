<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * DownloadUrlGuard 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\DownloadUrlGuard} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class DownloadUrlGuard extends Facade
{
    /** @var \App\Support\DownloadUrlGuard|null */
    private static $service;

    public static function service(): \App\Support\DownloadUrlGuard
    {
        return self::$service ??= new \App\Support\DownloadUrlGuard();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\DownloadUrlGuard
    {
        return self::service();
    }

}
