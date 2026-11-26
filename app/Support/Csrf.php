<?php

declare(strict_types=1);

namespace App\Support;

/**
 * CSRF 防护服务（实例实现）。
 *
 * 无自有状态：token 存在 session / cookie 中。兼容门面见 app/Facade/Csrf.php。
 *
 * 设计原则：
 * - 主验证基于 Session token（稳定，会话期间不变）
 * - 备援验证基于 Cookie（双重提交模式兜底，session 异常时仍可正常工作）
 * - validate() 支持"宽限期"机制：同时接受当前 token 和最近轮换掉的若干历史 token
 * - 普通写操作**不轮换** token：refresh() 只把当前 token 同步回 cookie
 * - 只有认证边界（登录 / 注册 / 退出）才用 rotate() 真正换一把新 token
 *
 * 为什么普通写操作不轮换：本站一个 PHP session 里只有一把 CSRF token，前台、
 * 用户中心、商户后台、后台共用（见 AuthService::logout 的注释）。而各页面都会把
 * 拿到的 token 缓存在 JS 里（window.adminCsrfToken / APPSTORE_CSRF / PJAX 页面），
 * 轮换后它们不会自动同步。早先每次写操作都换 token、且只认「上一把」，
 * 于是同一浏览器里任意两处写操作（另一个标签页、iframe 弹窗、前台登录）就会把
 * 后台首页缓存的 token 顶成永久失效，表现为「有时候点提交提示请求已失效，
 * 刷新页面又好了」。
 *
 * 统一改进点：
 * - Cookie 双重提交兜底：即使 session 启动失败，cookie 验证仍可工作
 * - 宽限期保留多把历史 token（默认 24 小时）：多标签页 / 轮换后仍能继续提交
 * - Token 同时写入 session 和 cookie，确保验证链路稳定
 */
final class Csrf
{
    /** Cookie 名 */
    private const COOKIE_NAME = 'em_csrf_token';

    /** Cookie 有效期（秒）。0 = 随浏览器会话，与 session cookie 同寿 */
    private const COOKIE_MAX_AGE = 0;

    /** 历史有效 token 的 session key（轮换后的一段时间内仍然认） */
    private const PREV_TOKEN_KEY = 'em_csrf_prev';

    /** 轮换后最多保留几把历史 token（覆盖多标签页 / 并发轮换） */
    private const MAX_GRACE_TOKENS = 8;

    /** 历史 token 默认宽限期（秒）：24 小时，足够覆盖一直开着的后台标签页 */
    private const GRACE_SECONDS = 86400;

    /**
     * 获取当前 CSRF token。
     * token 同时写入 session 和 cookie，确保双重验证链路的稳定性。
     */
    public function token(): string
    {
        $this->ensureSession();
        $key = $this->sessionKey();

        if (empty($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }

        $token = (string) $_SESSION[$key];

        // 同时写入 cookie（HttpOnly=false，允许 JS 读取，用于双重提交验证）
        $this->setCookie($token);

        return $token;
    }

    /**
     * 校验 token 是否合法。
     *
     * 验证顺序：
     * 1. Session 当前 token 匹配
     * 2. Session 宽限期内的旧 token（支持多标签页场景）
     * 3. Cookie 双重提交匹配（session 异常时的兜底验证）
     *
     * @param string $token 待校验的 token
     * @return bool
     */
    public function validate(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $this->ensureSession();
        $key = $this->sessionKey();

        // 1. 当前 session token 严格匹配
        $sessionToken = (string) ($_SESSION[$key] ?? '');
        if ($sessionToken !== '' && hash_equals($sessionToken, $token)) {
            return true;
        }

        // 2. 宽限期内的历史 token（支持多标签页、并发请求、登录轮换后仍开着的页面）
        if ($this->validateFromGrace($token)) {
            return true;
        }

        // 3. Cookie 双重提交兜底（session 异常或跨域场景下的备援验证）
        if ($this->validateFromCookie($token)) {
            return true;
        }

        return false;
    }

    /**
     * 校验 token；旧主题没有 token 时退化为「仅允许同源请求」。
     *
     * 用于前台下单 / 提现 / 注册这类既要有 CSRF 防护、又要兼容旧主题的入口：
     *   - 有 token：走正常校验；
     *   - 没 token：只接受同源请求。商户二级域名等「同站不同源」页面会被拒绝，
     *     跨站页面同样拒绝。
     */
    public function validateOrSameOrigin(string $token): bool
    {
        if ($token !== '' && $this->validate($token)) {
            return true;
        }

        return $this->isSameOriginRequest();
    }

    /**
     * 当前请求是否同源。
     *
     * 优先看 Sec-Fetch-Site（浏览器强制写入，页面 JS 改不了）：
     *   same-origin / none = 用户自己在同源页面发起 → 允许；
     *   same-site / cross-site = 同站子域或外站 → 拒绝。
     * 老浏览器没有这个头时，退回比较 Origin / Referer 与当前 Host。
     */
    private function isSameOriginRequest(): bool
    {
        $site = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
        if ($site === 'same-origin' || $site === 'none') {
            return true;
        }
        if ($site !== '') {
            return false;
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $requestHost = (string) parse_url('http://' . $host, PHP_URL_HOST);
        if ($requestHost === '') {
            return false;
        }

        $source = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($source === '') {
            $source = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        }
        if ($source === '') {
            return false;
        }

        $sourceHost = strtolower((string) parse_url($source, PHP_URL_HOST));
        return $sourceHost !== '' && $sourceHost === $requestHost;
    }

    /**
     * 同步 token（**不再轮换**）。
     *
     * 后台几乎每个写操作成功后会调 refresh() 让前端同步 token，历史上这里每次都会
     * 生成新 token。但 token 是「一个 session 一把、前后台共用」的，而各页面都把它
     * 缓存在 JS 里、轮换后不会自动同步；轮换两次就会让其它页面上缓存的旧 token 永久
     * 失效（只认「上一把」且宽限期 30 分钟）。现在这里只返回当前 token，
     * 需要真正换 token 的认证边界请用 rotate()。
     */
    public function refresh(): string
    {
        return $this->token();
    }

    /**
     * 轮换 token（只在登录 / 注册 / 退出等认证边界调用）。
     *
     * 旧 token 进入宽限期列表而不是立即作废：其它标签页、PJAX 页面、iframe 弹窗里
     * 缓存的旧 token 在宽限期内仍然可以提交。
     *
     * @param int $graceSeconds 宽限期秒数，默认 24 小时
     * @return string 新的 token
     */
    public function rotate(int $graceSeconds = self::GRACE_SECONDS): string
    {
        $this->ensureSession();
        $key = $this->sessionKey();

        $currentToken = (string) ($_SESSION[$key] ?? '');
        if ($currentToken !== '') {
            $grace = $this->graceTokens();
            $grace[] = ['token' => $currentToken, 'expires_at' => time() + $graceSeconds];
            $_SESSION[self::PREV_TOKEN_KEY] = array_slice($grace, -self::MAX_GRACE_TOKENS);
        }

        // 生成新 token
        $_SESSION[$key] = bin2hex(random_bytes(32));
        $newToken = (string) $_SESSION[$key];

        // 同时更新 cookie
        $this->setCookie($newToken);

        return $newToken;
    }

    // ====================== 私有方法 ======================

    /**
     * 校验历史 token（宽限期列表，兼容旧的单条结构）。
     */
    private function validateFromGrace(string $token): bool
    {
        foreach ($this->graceTokens() as $row) {
            if (hash_equals($row['token'], $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 读取仍在宽限期内的历史 token 列表。
     *
     * 旧版本在 session 里存的是一条 ['token' => ..., 'expires_at' => ...]，
     * 这里统一成列表，升级前的 session 不会因为结构变化而报错。
     *
     * @return list<array{token:string, expires_at:int}>
     */
    private function graceTokens(): array
    {
        $data = $_SESSION[self::PREV_TOKEN_KEY] ?? [];
        if (isset($data['token'])) {
            $data = [$data];
        }
        if (!is_array($data)) {
            return [];
        }

        $now = time();
        $out = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $token = (string) ($row['token'] ?? '');
            $expiresAt = (int) ($row['expires_at'] ?? 0);
            if ($token !== '' && $expiresAt > $now) {
                $out[] = ['token' => $token, 'expires_at' => $expiresAt];
            }
        }

        return $out;
    }

    /**
     * 获取 session 中存储 token 的 key。
     */
    private function sessionKey(): string
    {
        return defined('EM_AUTH_CSRF_KEY') ? EM_AUTH_CSRF_KEY : 'csrf_token';
    }

    /**
     * 确保 session 已启动。
     */
    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * 设置 CSRF token cookie。
     */
    private function setCookie(string $token): void
    {
        $secret = $this->getCookieSecret();
        if ($secret === null) {
            return;
        }

        $hmac = hash_hmac('sha256', $token, $secret);
        $cookieValue = $token . '.' . $hmac;

        @setcookie(
            self::COOKIE_NAME,
            $cookieValue,
            [
                // 0 = 随浏览器会话，与 session cookie 同寿；非 0 时才换算成绝对时间
                'expires' => self::COOKIE_MAX_AGE > 0 ? time() + self::COOKIE_MAX_AGE : 0,
                'path' => '/',
                'domain' => '',
                'secure' => false, // 开发环境允许 HTTP
                'httponly' => false, // 允许 JS 读取（双重提交验证需要）
                'samesite' => 'Lax',
            ]
        );
    }

    /**
     * 从 cookie 中验证 token（双重提交验证的 cookie 端）。
     */
    private function validateFromCookie(string $token): bool
    {
        $cookieValue = $_COOKIE[self::COOKIE_NAME] ?? '';

        if ($cookieValue === '') {
            return false;
        }

        $parts = explode('.', $cookieValue, 2);
        if (count($parts) !== 2) {
            return false;
        }

        [$cookieToken, $providedHmac] = $parts;

        // HMAC 签名验证（防止 cookie 被篡改）
        $secret = $this->getCookieSecret();
        if ($secret === null) {
            return false;
        }

        $expectedHmac = hash_hmac('sha256', $cookieToken, $secret);
        if (!hash_equals($expectedHmac, $providedHmac)) {
            return false;
        }

        // Cookie 中的 token 需与提交的 token 匹配（双重提交验证）
        return hash_equals($cookieToken, $token);
    }

    /**
     * 获取 cookie 签名密钥（基于配置派生）。
     *
     * 没有密钥时返回 null，调用方据此停用 cookie 兜底；
     * config.php 里没有密钥时，由 CsrfSecret 在后台首次访问时生成并写入。
     */
    private function getCookieSecret(): ?string
    {
        $secret = \CsrfSecret::get();
        if ($secret === '') {
            return null;
        }

        // 使用配置的密钥派生，确保 cookie 签名不可伪造
        $base = defined('EM_AUTH_CSRF_KEY') ? EM_AUTH_CSRF_KEY : 'default_csrf_key';

        return hash('sha256', $base . $secret, true);
    }
}
