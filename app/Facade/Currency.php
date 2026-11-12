<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * Currency 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\Currency} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class Currency extends Facade
{
    /** @var static|null 门面实例（getInstance() 返回它，实例调用同样转发到服务） */
    private static $instance;

    /** @var \App\Support\Currency|null */
    private static $service;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public static function service(): \App\Support\Currency
    {
        return self::$service ??= new \App\Support\Currency();
    }

}
