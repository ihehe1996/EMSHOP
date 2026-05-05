<?php

declare(strict_types=1);

/**
 * 推广归因 Cookie 工具。
 *
 * 访客通过推广链接 `?r=XXX` 进入时写一个 10 年长期 Cookie；
 * 注册或下单时读取 Cookie，完成归因绑定。
 *
 * Cookie 值 = 上级用户的 invite_code（不是 user_id，避免被枚举）。
 */
class InviteToken
{
    public const COOKIE_NAME     = 'em_inviter';
    public const COOKIE_LIFETIME = 86400 * 365 * 10; // 10 年
    public const QUERY_PARAM     = 'r';

    /**
     * 根据 invite_code 找到上级用户 id。找不到返回 0。
     */
    public static function resolveInviterId(string $inviteCode): int
    {
        $inviteCode = trim($inviteCode);
        if ($inviteCode === '') return 0;
        $row = Database::fetchOne(
            "SELECT id FROM " . Database::prefix() . "user WHERE invite_code = ? AND status = 1 LIMIT 1",
            [$inviteCode]
        );
        return $row ? (int) $row['id'] : 0;
    }

    /**
     * 路由入口调用：若当前 URL 带 ?r=xxx 且 Cookie 还没绑，则首次写入。
     *
     * 规则：
     *   - 已登录用户访问不写（已经有账户绑定）
     *   - Cookie 已经存在不覆盖（保留首次归因）
     *   - 自邀不写（登录用户访问自己的链接）
     */
    public static function captureFromQuery(): void
    {
        if (php_sapi_name() === 'cli') return;

        $code = isset($_GET[self::QUERY_PARAM]) ? trim((string) $_GET[self::QUERY_PARAM]) : '';
        if ($code === '') return;

        // 已有 Cookie 的不覆盖
        if (!empty($_COOKIE[self::COOKIE_NAME])) return;

        // 校验 code 存在
        $inviterId = self::resolveInviterId($code);
        if ($inviterId <= 0) return;

        // 登录用户：自邀过滤；且本身已有 inviter 也不再写
        if (session_status() === PHP_SESSION_NONE) session_start();
        $frontUser = $_SESSION['em_front_user'] ?? null;
        if (!empty($frontUser['id']) && (int) $frontUser['id'] === $inviterId) return;

        self::setCookie($code);
    }

    /**
     * 手动设置 Cookie（注册流程等调用）。
     */
    public static function setCookie(string $code): void
    {
        $_COOKIE[self::COOKIE_NAME] = $code;
        @setcookie(self::COOKIE_NAME, $code, [
            'expires'  => time() + self::COOKIE_LIFETIME,
            'path'     => '/',
            'samesite' => 'Lax',
            'httponly' => true,
        ]);
    }

    /**
     * 读取 Cookie 中的 invite_code；无则返回空串。
     */
    public static function getCode(): string
    {
        return isset($_COOKIE[self::COOKIE_NAME]) ? (string) $_COOKIE[self::COOKIE_NAME] : '';
    }

    /**
     * 返回当前 Cookie 对应的上级用户 id（供下单时查）；没有或无效时 0。
     */
    public static function currentInviterId(): int
    {
        return self::resolveInviterId(self::getCode());
    }

    /**
     * 为新注册用户生成一个全局唯一的 invite_code。
     */
    public static function generateUniqueCode(int $len = 8): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // 去掉 I O L 1 0
        $prefix = Database::prefix();
        for ($try = 0; $try < 10; $try++) {
            $code = '';
            for ($i = 0; $i < $len; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $exist = Database::fetchOne(
                "SELECT id FROM {$prefix}user WHERE invite_code = ? LIMIT 1",
                [$code]
            );
            if (!$exist) return $code;
        }
        // 极小概率都撞上；降级为更长 code
        return self::generateUniqueCode($len + 2);
    }
}
