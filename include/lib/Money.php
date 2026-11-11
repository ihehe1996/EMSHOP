<?php

declare(strict_types=1);

/**
 * 金额解析与换算（兼容门面）。
 *
 * 实际实现已迁到 App\Support\Money 实例服务；这里保留原有静态 API 和常量，
 * 现有调用点、插件和模板都不用改。
 */
final class Money
{
    /** 一个主货币单位对应的 micro 值。 */
    public const SCALE = \App\Support\Money::SCALE;

    /** 小数位上限（对应 micro 精度）。 */
    public const MAX_DECIMALS = \App\Support\Money::MAX_DECIMALS;

    /** @var \App\Support\Money|null */
    private static $service;

    /**
     * 获取内部金额服务实例。
     */
    public static function instance(): \App\Support\Money
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\Money::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/Money.php';
            }
            self::$service = new \App\Support\Money();
        }

        return self::$service;
    }

    /**
     * 把用户提交的金额解析为主货币 micro 整数。
     *
     * @param mixed $input
     * @throws InvalidArgumentException
     */
    public static function parse($input, bool $allowZero = false): int
    {
        return self::instance()->parse($input, $allowZero);
    }

    /**
     * 尝试解析，失败返回 null。
     *
     * @param mixed $input
     */
    public static function tryParse($input, bool $allowZero = true): ?int
    {
        return self::instance()->tryParse($input, $allowZero);
    }

    /**
     * 该字符串是否为合法的金额格式。
     *
     * @param mixed $input
     */
    public static function isValid($input): bool
    {
        return self::instance()->isValid($input);
    }

    /**
     * micro 整数 → 主货币「元」字符串。
     */
    public static function format(int $micro, int $decimals = 2): string
    {
        return self::instance()->format($micro, $decimals);
    }
}
