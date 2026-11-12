<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * InviteToken 门面。
 *
 * 静态调用转发到容器里的 {@see \App\Support\InviteToken} 单例；全局短名由 bootstrap/aliases.php 注册。
 */
final class InviteToken extends Facade
{
    /** @var \App\Support\InviteToken|null */
    private static $service;

    public static function service(): \App\Support\InviteToken
    {
        return self::$service ??= new \App\Support\InviteToken();
    }

    /** 兼容旧调用名 */
    public static function instance(): \App\Support\InviteToken
    {
        return self::service();
    }

    public const COOKIE_NAME     = \App\Support\InviteToken::COOKIE_NAME;
    public const COOKIE_LIFETIME = \App\Support\InviteToken::COOKIE_LIFETIME;
    public const QUERY_PARAM     = \App\Support\InviteToken::QUERY_PARAM;

}
