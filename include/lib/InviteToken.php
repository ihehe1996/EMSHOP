<?php

declare(strict_types=1);

/**
 * 推广归因 Cookie（兼容门面）。
 *
 * 实际实现已迁到 App\Support\InviteToken 实例服务；这里保留原有静态 API 和常量，
 * init.php、注册流程、返佣服务的调用点都不用改。
 */
class InviteToken
{
    public const COOKIE_NAME     = \App\Support\InviteToken::COOKIE_NAME;
    public const COOKIE_LIFETIME = \App\Support\InviteToken::COOKIE_LIFETIME;
    public const QUERY_PARAM     = \App\Support\InviteToken::QUERY_PARAM;

    /** @var \App\Support\InviteToken|null */
    private static $service;

    /**
     * 获取内部归因服务实例。
     */
    public static function instance(): \App\Support\InviteToken
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\InviteToken::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/InviteToken.php';
            }
            self::$service = new \App\Support\InviteToken();
        }

        return self::$service;
    }

    /**
     * 根据 invite_code 找到上级用户 id。找不到返回 0。
     */
    public static function resolveInviterId(string $inviteCode): int
    {
        return self::instance()->resolveInviterId($inviteCode);
    }

    /**
     * 路由入口调用：若当前 URL 带 ?r=xxx 且 Cookie 还没绑，则首次写入。
     */
    public static function captureFromQuery(): void
    {
        self::instance()->captureFromQuery();
    }

    /**
     * 手动设置 Cookie（注册流程等调用）。
     */
    public static function setCookie(string $code): void
    {
        self::instance()->setCookie($code);
    }

    /**
     * 读取 Cookie 中的 invite_code；无则返回空串。
     */
    public static function getCode(): string
    {
        return self::instance()->getCode();
    }

    /**
     * 返回当前 Cookie 对应的上级用户 id；没有或无效时 0。
     */
    public static function currentInviterId(): int
    {
        return self::instance()->currentInviterId();
    }

    /**
     * 为新注册用户生成一个全局唯一的 invite_code。
     */
    public static function generateUniqueCode(int $len = 8): string
    {
        return self::instance()->generateUniqueCode($len);
    }
}
