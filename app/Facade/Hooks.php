<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * Hooks 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\Hooks} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class Hooks extends Facade
{
    /** @var \App\Support\Hooks|null */
    private static $service;

    public static function service(): \App\Support\Hooks
    {
        return self::$service ??= new \App\Support\Hooks();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\Hooks
    {
        return self::service();
    }

    /**
     * 注册全局钩子函数（addAction / doAction / addFilter / applyFilter 等）。
     *
     * 函数必须定义在全局命名空间，所以单独放在 app/helpers/hooks.php。
     */
    public static function boot(): void
    {
        require_once EM_ROOT . '/app/helpers/hooks.php';
    }
}
