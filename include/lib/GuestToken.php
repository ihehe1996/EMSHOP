<?php

declare(strict_types=1);

/**
 * 游客身份标识。
 *
 * 通过永久 Cookie 为每个浏览器分配全局唯一 ID，
 * 用于关联游客订单等数据。
 */
class GuestToken
{
    private const COOKIE_NAME = 'em_guest_token';
    private const COOKIE_LIFETIME = 86400 * 365 * 10; // 10年

    /**
     * 获取当前访客的 guest_token。
     * 不存在则自动生成并写入 Cookie。
     */
    public static function get(): string
    {
        if (!empty($_COOKIE[self::COOKIE_NAME])) {
            return (string) $_COOKIE[self::COOKIE_NAME];
        }

        // 生成全局唯一 ID
        $token = self::generate();
        self::setCookie($token);

        return $token;
    }

    /**
     * 生成全局唯一标识。
     * 格式：32位十六进制，基于 random_bytes 保证唯一性。
     */
    private static function generate(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * 写入 Cookie。
     */
    private static function setCookie(string $token): void
    {
        $_COOKIE[self::COOKIE_NAME] = $token;
        @setcookie(self::COOKIE_NAME, $token, [
            'expires'  => time() + self::COOKIE_LIFETIME,
            'path'     => '/',
            'domain'   => '',
            'secure'   => false,
            'httponly'  => true,
            'samesite' => 'Lax',
        ]);
    }
}
