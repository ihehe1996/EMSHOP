<?php

declare(strict_types=1);

/**
 * 邮件发送（兼容门面）。
 *
 * 实际实现已迁到 App\Support\Mailer 实例服务；这里保留原有静态 API，
 * 后台设置页、密码重置等调用点都不用改。
 */
final class Mailer
{
    /** @var \App\Support\Mailer|null */
    private static $service;

    /**
     * 获取内部邮件服务实例。
     */
    public static function instance(): \App\Support\Mailer
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\Mailer::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/Mailer.php';
            }
            self::$service = new \App\Support\Mailer();
        }

        return self::$service;
    }

    /**
     * 用系统当前 Config 里的 SMTP 配置发邮件。
     */
    public static function send(string $to, string $subject, string $bodyHtml, string $bodyText = ''): bool
    {
        return self::instance()->send($to, $subject, $bodyHtml, $bodyText);
    }

    /**
     * 用指定 SMTP 配置发邮件。
     *
     * @param array{from_email:string,from_name?:string,host:string,password:string,port:int} $cfg
     */
    public static function sendWith(array $cfg, string $to, string $subject, string $bodyHtml, string $bodyText = ''): bool
    {
        return self::instance()->sendWith($cfg, $to, $subject, $bodyHtml, $bodyText);
    }

    /**
     * 获取最近一次发送失败的错误描述。成功后返回 null。
     */
    public static function lastError(): ?string
    {
        return self::instance()->lastError();
    }
}
