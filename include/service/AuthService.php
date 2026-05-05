<?php

declare(strict_types=1);

/**
 * 后台认证服务。
 *
 * 当前服务专门用于后台管理员登录。
 * 管理员账号未来不能用于前台登录，前台登录需单独实现用户认证逻辑。
 */
final class AuthService
{
    /**
     * @var array<string, mixed>
     */
    private $config;

    /**
     * @var UserModel
     */
    private $users;

    /**
     * @var PasswordHash
     */
    private $hasher;

    /**
     * @var LoginThrottle
     */
    private $throttle;

    public function __construct()
    {
        $this->config = EM_CONFIG['auth'];
        $this->users = new UserModel();
        $this->hasher = new PasswordHash(8, true);
        $this->throttle = new LoginThrottle();
    }

    /**
     * 启动 session。
     */
    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * 检查当前管理员是否已登录。
     */
    public function check(): bool
    {
        $this->startSession();

        if (!empty($_SESSION[$this->config['session_key']])) {
            return true;
        }

        $token = isset($_COOKIE[$this->config['remember_cookie']]) ? (string) $_COOKIE[$this->config['remember_cookie']] : '';
        if ($token === '') {
            return false;
        }

        $user = $this->users->findAdminByRememberToken($token);
        if ($user === null) {
            $this->forgetRememberCookie();
            return false;
        }

        $_SESSION[$this->config['session_key']] = $this->sessionPayload($user);
        session_regenerate_id(true);
        return true;
    }

    /**
     * 返回当前已登录管理员信息。
     *
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        $this->startSession();
        return isset($_SESSION[$this->config['session_key']]) ? $_SESSION[$this->config['session_key']] : null;
    }

    /**
     * 刷新 session 中的用户信息（修改资料后同步）。
     *
     * @param array<string, mixed> $user 数据库中最新的用户记录
     */
    public function refreshSession(array $user): void
    {
        $this->startSession();
        $_SESSION[$this->config['session_key']] = $this->sessionPayload($user);
    }

    /**
     * 执行后台管理员登录。
     *
     * @return array<string, mixed>
     */
    public function attemptAdminLogin(string $account, string $password, bool $remember): array
    {
        $account = trim($account);
        if ($account === '' || $password === '') {
            throw new InvalidArgumentException('账号和密码不能为空');
        }

        if ($this->throttle->isLocked()) {
            $minutes = (int) ceil($this->throttle->remainingSeconds() / 60);
            throw new RuntimeException('登录失败次数过多，请在 ' . $minutes . ' 分钟后再试');
        }

        $user = $this->users->findAdminByAccount($account);
        if ($user === null || !$this->hasher->CheckPassword($password, (string) $user['password'])) {
            $this->throttle->hit();
            throw new RuntimeException('账号或密码错误');
        }

        $this->throttle->clear();
        $this->startSession();
        session_regenerate_id(true);
        $_SESSION[$this->config['session_key']] = $this->sessionPayload($user);

        $rememberToken = null;
        $days = $remember ? (int) $this->config['remember_days_checked'] : (int) $this->config['remember_days_default'];
        if ($days > 0) {
            $rememberToken = bin2hex(random_bytes(32));
            $this->queueRememberCookie($rememberToken, $days);
        }

        $this->users->updateLoginMeta((int) $user['id'], $this->clientIp(), $rememberToken);

        return $this->sessionPayload($user);
    }

    /**
     * 退出登录。
     */
    public function logout(): void
    {
        $this->startSession();
        unset($_SESSION[$this->config['session_key']]);
        $this->forgetRememberCookie();

        $_SESSION = [];
        if (session_id() !== '') {
            session_regenerate_id(true);
        }
    }

    /**
     * 生成 session 中存储的管理员信息。
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function sessionPayload(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'nickname' => (string) $user['nickname'],
            'email' => (string) $user['email'],
            'mobile' => (string) ($user['mobile'] ?? ''),
            'avatar' => (string) $user['avatar'],
            'role' => (string) $user['role'],
        ];
    }

    /**
     * 写入记住登录 cookie。
     */
    private function queueRememberCookie(string $token, int $days): void
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        setcookie(
            $this->config['remember_cookie'],
            $token,
            [
                'expires' => time() + ($days * 86400),
                'path' => '/',
                'httponly' => true,
                'secure' => $secure,
                'samesite' => 'Lax',
            ]
        );
    }

    /**
     * 清理记住登录 cookie。
     */
    private function forgetRememberCookie(): void
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        setcookie(
            $this->config['remember_cookie'],
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'secure' => $secure,
                'samesite' => 'Lax',
            ]
        );
    }

    /**
     * 获取客户端 IP。
     */
    private function clientIp(): string
    {
        return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    }
}
