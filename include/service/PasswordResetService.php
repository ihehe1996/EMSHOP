<?php

declare(strict_types=1);

/**
 * 找回密码业务：发重置邮件、校验令牌、更新密码。
 */
final class PasswordResetService
{
    /** 重置链接有效时长（秒） */
    private const TOKEN_TTL = 3600;

    /** IP 维度：每窗口最多请求次数 */
    private const IP_MAX_ATTEMPTS = 5;

    /** IP 限流窗口（秒）：1 分钟内最多 5 次 */
    private const IP_WINDOW = 60;

    /** 邮箱维度：每窗口最多请求次数 */
    private const EMAIL_MAX_ATTEMPTS = 3;

    /** 邮箱限流窗口（秒）：1 分钟内最多 3 次 */
    private const EMAIL_WINDOW = 60;

    /** 重置提交 IP 限流：1 分钟内最多 10 次 */
    private const RESET_IP_MAX_ATTEMPTS = 10;

    private const RESET_IP_WINDOW = 60;

    private PasswordResetModel $resetModel;

    private UserListModel $userModel;

    public function __construct()
    {
        $this->resetModel = new PasswordResetModel();
        $this->userModel = new UserListModel();
    }

    /**
     * 申请发送重置邮件。
     *
     * @return array{ok: bool, msg: string, captcha_expr?: string}
     */
    public function requestReset(string $email, string $captchaInput): array
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'msg' => '请输入有效的邮箱地址'];
        }

        $ipKey = 'forgot_pwd_ip:v2:' . RateLimit::clientIp();
        if (RateLimit::tooManyAttempts($ipKey, self::IP_MAX_ATTEMPTS)) {
            $wait = RateLimit::availableIn($ipKey);
            return ['ok' => false, 'msg' => "请求过于频繁，请 {$wait} 秒后再试", 'captcha_expr' => Captcha::issue('forgot_password')];
        }
        RateLimit::hit($ipKey, self::IP_WINDOW);

        if (!Captcha::verify($captchaInput, 'forgot_password')) {
            return ['ok' => false, 'msg' => '验证码错误，请重试', 'captcha_expr' => Captcha::issue('forgot_password')];
        }

        $emailKey = 'forgot_pwd_email:v2:' . hash('sha256', $email);
        if (RateLimit::tooManyAttempts($emailKey, self::EMAIL_MAX_ATTEMPTS)) {
            $wait = RateLimit::availableIn($emailKey);
            return ['ok' => false, 'msg' => "该邮箱请求过于频繁，请 {$wait} 秒后再试"];
        }
        RateLimit::hit($emailKey, self::EMAIL_WINDOW);

        $user = $this->findUserByEmail($email);
        if ($user === null) {
            return ['ok' => false, 'msg' => '该邮箱未注册'];
        }
        if ((int) ($user['status'] ?? 0) !== 1) {
            return ['ok' => false, 'msg' => '账号已被禁用，请联系管理员'];
        }

        if (!$this->isMailConfigured()) {
            return ['ok' => false, 'msg' => '邮件服务未配置，请联系管理员'];
        }

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_TTL);

        $this->resetModel->invalidatePendingForUser((int) $user['id']);
        $this->resetModel->create((int) $user['id'], $email, $tokenHash, $expiresAt);

        $resetUrl = $this->buildResetUrl($rawToken);
        $siteName = (string) Config::get('sitename', 'EMSHOP');
        $subject = $siteName . ' - 重置登录密码';
        $html = $this->buildResetEmailHtml($siteName, $resetUrl, self::TOKEN_TTL / 60);

        if (!Mailer::send($email, $subject, $html)) {
            return ['ok' => false, 'msg' => '邮件发送失败，请稍后重试或联系管理员'];
        }

        return ['ok' => true, 'msg' => '重置链接已发送至您的邮箱，请查收'];
    }

    /**
     * 校验重置令牌是否有效。
     *
     * @return array<string, mixed>|null 令牌行
     */
    public function validateToken(string $rawToken): ?array
    {
        $rawToken = trim($rawToken);
        if ($rawToken === '' || !preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            return null;
        }

        return $this->resetModel->findValidByTokenHash(hash('sha256', $rawToken));
    }

    /**
     * 提交新密码。
     *
     * @return array{ok: bool, msg: string}
     */
    public function resetPassword(string $rawToken, string $password, string $confirm): array
    {
        $ipKey = 'reset_pwd_ip:v2:' . RateLimit::clientIp();
        if (RateLimit::tooManyAttempts($ipKey, self::RESET_IP_MAX_ATTEMPTS)) {
            $wait = RateLimit::availableIn($ipKey);
            return ['ok' => false, 'msg' => "请求过于频繁，请 {$wait} 秒后再试"];
        }
        RateLimit::hit($ipKey, self::RESET_IP_WINDOW);

        $row = $this->validateToken($rawToken);
        if ($row === null) {
            return ['ok' => false, 'msg' => '重置链接无效或已过期，请重新申请'];
        }

        if ($password === '') {
            return ['ok' => false, 'msg' => '请输入新密码'];
        }
        if (mb_strlen($password) < 6) {
            return ['ok' => false, 'msg' => '密码长度不能少于 6 位'];
        }
        if ($password !== $confirm) {
            return ['ok' => false, 'msg' => '两次输入的密码不一致'];
        }

        $hasher = new PasswordHash(8, true);
        $hash = $hasher->HashPassword($password);

        $userId = (int) ($row['user_id'] ?? 0);
        if (!$this->userModel->update($userId, ['password' => $hash])) {
            return ['ok' => false, 'msg' => '密码更新失败，请稍后重试'];
        }

        $this->resetModel->markUsed((int) $row['id']);
        $this->resetModel->invalidatePendingForUser($userId);

        return ['ok' => true, 'msg' => '密码已重置，请使用新密码登录'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findUserByEmail(string $email): ?array
    {
        $table = Database::prefix() . 'user';
        $sql = sprintf(
            'SELECT `id`, `email`, `status` FROM `%s` WHERE `email` = ? AND `role` = \'user\' LIMIT 1',
            $table
        );

        return Database::fetchOne($sql, [$email]);
    }

    private function isMailConfigured(): bool
    {
        $from = trim((string) Config::get('mail_from_address', ''));
        $host = trim((string) Config::get('mail_host', ''));
        $password = (string) Config::get('mail_password', '');
        $port = (int) (Config::get('mail_port', '465') ?: 465);

        return $from !== '' && $host !== '' && $password !== '' && $port > 0;
    }

    /**
     * 生成重置密码绝对链接（基于当前请求域名，含子目录）。
     */
    private function buildResetUrl(string $rawToken): string
    {
        $base = rtrim(Request::baseUrl(), '/');

        $query = http_build_query([
            'c' => 'login',
            'a' => 'reset',
            'token' => $rawToken,
        ]);

        return $base . '/?' . $query;
    }

    private function buildResetEmailHtml(string $siteName, string $resetUrl, int $validMinutes): string
    {
        $siteNameEsc = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
        $resetUrlEsc = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

        return '<div style="font-family:sans-serif;line-height:1.6;color:#333;max-width:560px;margin:0 auto;">'
            . '<h2 style="color:#4e6ef2;margin-bottom:16px;">' . $siteNameEsc . '</h2>'
            . '<p>您好，我们收到了重置登录密码的请求。请点击下方按钮设置新密码：</p>'
            . '<p style="margin:24px 0;"><a href="' . $resetUrlEsc . '" '
            . 'style="display:inline-block;padding:12px 24px;background:#4e6ef2;color:#fff;text-decoration:none;border-radius:6px;">'
            . '重置密码</a></p>'
            . '<p style="font-size:13px;color:#888;">或复制以下链接到浏览器打开：<br>'
            . '<span style="word-break:break-all;">' . $resetUrlEsc . '</span></p>'
            . '<p style="font-size:13px;color:#888;">链接 ' . (int) $validMinutes . ' 分钟内有效，仅可使用一次。'
            . '如非本人操作，请忽略此邮件。</p>'
            . '</div>';
    }
}
