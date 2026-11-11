<?php

declare(strict_types=1);

/**
 * 展示名称校验（兼容门面）。
 *
 * 实际实现已迁到 App\Support\DisplayName 实例服务；这里保留原有静态 API，
 * 注册、后台设置等调用点都不用改。
 */
final class DisplayName
{
    /** @var \App\Support\DisplayName|null */
    private static $service;

    /**
     * 获取内部展示名称服务实例。
     */
    public static function instance(): \App\Support\DisplayName
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\DisplayName::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/DisplayName.php';
            }
            self::$service = new \App\Support\DisplayName();
        }

        return self::$service;
    }

    /**
     * @return string|null 错误信息；通过返回 null
     */
    public static function validate(string $value, string $label = '名称', int $max = 100): ?string
    {
        return self::instance()->validate($value, $label, $max);
    }
}
