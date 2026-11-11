<?php

declare(strict_types=1);

/**
 * 算术验证码（兼容门面）。
 *
 * 实际实现已迁到 App\Support\Captcha 实例服务；这里保留原有静态 API，
 * 查单、注册等调用点都不用改。
 */
final class Captcha
{
    /** @var \App\Support\Captcha|null */
    private static $service;

    /**
     * 获取内部验证码服务实例。
     */
    public static function instance(): \App\Support\Captcha
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\Captcha::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/Captcha.php';
            }
            self::$service = new \App\Support\Captcha();
        }

        return self::$service;
    }

    /**
     * 生成新一题，写入 session，返回展示字符串。
     */
    public static function issue(string $scope = 'default'): string
    {
        return self::instance()->issue($scope);
    }

    /**
     * 校验用户答案；验证后立即 consume。
     */
    public static function verify(string $userAnswer, string $scope = 'default'): bool
    {
        return self::instance()->verify($userAnswer, $scope);
    }

    /**
     * 是否已签发过题目。
     */
    public static function exists(string $scope = 'default'): bool
    {
        return self::instance()->exists($scope);
    }
}
