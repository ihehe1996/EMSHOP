<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * HtmlSanitizer 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\HtmlSanitizer} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class HtmlSanitizer extends Facade
{
    /** @var \App\Support\HtmlSanitizer|null */
    private static $service;

    public static function service(): \App\Support\HtmlSanitizer
    {
        return self::$service ??= new \App\Support\HtmlSanitizer();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\HtmlSanitizer
    {
        return self::service();
    }

}
