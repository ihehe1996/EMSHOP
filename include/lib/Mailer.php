<?php

declare(strict_types=1);

/**
 * 邮件发送业务类（基于底层 Smtp RFC821 类做业务封装）。
 *
 * 使用方式：
 *   Mailer::send($to, $subject, $htmlBody);               // 用系统配置（Config 里的 mail_* 字段）
 *   Mailer::sendWith($cfg, $to, $subject, $htmlBody);     // 用指定配置（测试邮件用）
 *   $err = Mailer::lastError();                           // 失败时拿错误信息
 *
 * 端口与加密策略：
 *   - 465 → SMTPS（连接建立立即 TLS），不发 STARTTLS
 *   - 587 / 其它 → 明文连接 + EHLO + STARTTLS + EHLO（升级到 TLS）再 AUTH
 *   - 25  → 明文（不推荐，多数云厂商已封锁）
 */
final class Mailer
{
    /** @var string|null 最近一次发送失败的错误描述；成功后被清空 */
    private static ?string $lastError = null;

    /**
     * 用系统当前 Config 里的 SMTP 配置发邮件。
     */
    public static function send(string $to, string $subject, string $bodyHtml, string $bodyText = ''): bool
    {
        return self::sendWith([
            'from_email' => (string) Config::get('mail_from_address', ''),
            'from_name'  => (string) Config::get('mail_from_name', ''),
            'host'       => (string) Config::get('mail_host', ''),
            'password'   => (string) Config::get('mail_password', ''),
            'port'       => (int) (Config::get('mail_port', '465') ?: 465),
        ], $to, $subject, $bodyHtml, $bodyText);
    }

    /**
     * 用指定 SMTP 配置发邮件。
     *
     * @param array{from_email:string,from_name?:string,host:string,password:string,port:int} $cfg
     */
    public static function sendWith(array $cfg, string $to, string $subject, string $bodyHtml, string $bodyText = ''): bool
    {
        self::$lastError = null;

        $fromEmail = trim((string) ($cfg['from_email'] ?? ''));
        $fromName  = (string) ($cfg['from_name'] ?? '');
        $host      = trim((string) ($cfg['host'] ?? ''));
        $password  = (string) ($cfg['password'] ?? '');
        $port      = (int) ($cfg['port'] ?? 465);

        if ($fromEmail === '' || $host === '' || $password === '' || $port <= 0) {
            self::$lastError = '邮箱配置不完整（需填写 SMTP 服务器 / 发送人邮箱 / 密码 / 端口）';
            return false;
        }
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            self::$lastError = '发送人邮箱格式不正确';
            return false;
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::$lastError = '收件人邮箱格式不正确';
            return false;
        }

        $smtp = new Smtp();
        $smtp->Timeout   = 15;
        $smtp->Timelimit = 15;

        // 465 端口用 ssl:// 协议前缀，让 stream_socket_client 直接建 TLS；其它端口明文连接后再 STARTTLS
        $connHost = $port === 465 ? 'ssl://' . $host : $host;

        try {
            if (!$smtp->connect($connHost, $port, 10)) {
                self::$lastError = 'SMTP 连接失败：' . (self::extractErr($smtp) ?: ($host . ':' . $port));
                return false;
            }

            $heloName = self::heloName();

            if (!$smtp->hello($heloName)) {
                self::$lastError = 'SMTP 握手（EHLO）失败：' . self::extractErr($smtp);
                return false;
            }

            // 587 / 其它非 SSL 端口：升级 TLS 后再 EHLO 一次
            if ($port !== 465 && $port !== 25) {
                if (!$smtp->startTLS()) {
                    self::$lastError = 'STARTTLS 升级失败：' . self::extractErr($smtp);
                    return false;
                }
                if (!$smtp->hello($heloName)) {
                    self::$lastError = 'TLS 握手后 EHLO 失败：' . self::extractErr($smtp);
                    return false;
                }
            }

            if (!$smtp->authenticate($fromEmail, $password)) {
                self::$lastError = 'SMTP 认证失败（请检查账号 / 授权码是否正确）：' . self::extractErr($smtp);
                return false;
            }

            if (!$smtp->mail($fromEmail)) {
                self::$lastError = 'MAIL FROM 失败：' . self::extractErr($smtp);
                return false;
            }
            if (!$smtp->recipient($to)) {
                self::$lastError = 'RCPT TO 失败（收件人被拒）：' . self::extractErr($smtp);
                return false;
            }

            $msgData = self::buildMessage($fromEmail, $fromName, $to, $subject, $bodyHtml, $bodyText);
            if (!$smtp->data($msgData)) {
                self::$lastError = '邮件正文发送失败：' . self::extractErr($smtp);
                return false;
            }

            $smtp->quit();
            return true;
        } catch (Throwable $e) {
            self::$lastError = '邮件发送异常：' . $e->getMessage();
            return false;
        } finally {
            $smtp->close();
        }
    }

    /**
     * 获取最近一次发送失败的错误描述。成功后返回 null。
     */
    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * 从底层 Smtp 里取一段可读的错误描述；组合 error + detail。
     */
    private static function extractErr(Smtp $smtp): string
    {
        $err = $smtp->getError();
        $parts = array_filter([
            (string) ($err['error']  ?? ''),
            (string) ($err['detail'] ?? ''),
        ], static fn(string $s): bool => $s !== '');
        return implode(' / ', $parts);
    }

    /**
     * EHLO 时用的本地主机名。优先当前请求域名（去端口），缺省回退 localhost。
     * 有些邮件服务商会校验 EHLO 主机名；用 localhost 可能被拒，但多数提供商容忍。
     */
    private static function heloName(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $host = preg_replace('/:\d+$/', '', $host);
        return $host !== '' ? $host : 'localhost';
    }

    /**
     * 构造 RFC 5322 MIME 消息：multipart/alternative 同时带 text 和 html，
     * 让客户端按偏好渲染；全部 base64 编码，不用担心中文。
     */
    private static function buildMessage(string $fromEmail, string $fromName, string $to, string $subject, string $bodyHtml, string $bodyText): string
    {
        $boundary = '=_em_' . bin2hex(random_bytes(8));

        $fromHeader = $fromName !== ''
            ? '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>'
            : $fromEmail;
        $subjectHeader = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        if ($bodyText === '') {
            // 纯 HTML 时自动提取一份纯文本作为 alternative
            $bodyText = trim(preg_replace('/\s+/', ' ', strip_tags($bodyHtml)) ?? '');
            if ($bodyText === '') $bodyText = ' ';
        }

        $msgHost = self::heloName();
        $headers = [
            'From: ' . $fromHeader,
            'To: ' . $to,
            'Subject: ' . $subjectHeader,
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . $msgHost . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $body = "--{$boundary}\r\n"
              . "Content-Type: text/plain; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($bodyText)) . "\r\n"
              . "--{$boundary}\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($bodyHtml)) . "\r\n"
              . "--{$boundary}--\r\n";

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }
}
