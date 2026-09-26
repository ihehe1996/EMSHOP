<?php

declare(strict_types=1);

/**
 * 金额解析与换算。
 *
 * 数据库约定：所有金额按**主货币 ×1000000** 存整数（下称 micro）。
 * 用户提交的金额是「元」的字符串，必须经本类转成 micro 整数。
 *
 * 为什么不能继续在各处手写 `bcmul($x, '1000000', 0)`：
 *   1. 上游校验普遍只有 `is_numeric`，而它**放行科学计数法**（`1e5`、`1e300`）。
 *      `bcmul('1e5', '1000000', 0)` 在 PHP 8 抛未捕获的 ValueError（PHP 7.4 静默
 *      截断成 1），前者让公开接口直接 500，后者把金额算错。
 *   2. float 路线（`(int) round($x * 1000000)`）有浮点精度问题。
 *
 * 本类用**纯字符串整数运算**，既不依赖 bcmath 扩展，也不经过 float。
 *
 * 用法：
 *   $raw = Money::parse(Input::post('amount', ''));   // 非法/非正 → 抛异常
 *   $raw = Money::tryParse($cfg, true);               // 非法 → null，供配置类静默兜底
 */
final class Money
{
    /** 一个主货币单位对应的 micro 值。 */
    public const SCALE = 1000000;

    /** 小数位上限（对应 micro 精度）。 */
    public const MAX_DECIMALS = 6;

    /**
     * 把用户提交的金额解析为主货币 micro 整数。
     *
     * @param mixed $input     用户输入（字符串 / 整数 / 浮点）
     * @param bool  $allowZero 是否允许 0（默认不允许，便于「金额必须为正」的校验）
     * @return int micro 整数
     * @throws InvalidArgumentException 格式非法、为负，或为零但未允许
     */
    public static function parse($input, bool $allowZero = false): int
    {
        $raw = self::normalize($input);

        // 白名单：数字 + 最多 6 位小数。刻意排除科学计数法、千分位、前后缀单位 ——
        // 这些要么会被算错，要么会让 bcmath 抛异常。整数位上限 12 位，
        // 保证 ×1000000 后仍在 PHP_INT_MAX（约 9.22e18）以内，不会溢出。
        if (preg_match('/^\d{1,12}(?:\.\d{1,' . self::MAX_DECIMALS . '})?$/', $raw) !== 1) {
            throw new InvalidArgumentException('金额格式不正确');
        }

        $micro = self::toMicro($raw);

        if (!$allowZero && $micro === 0) {
            throw new InvalidArgumentException('金额必须大于 0');
        }

        return $micro;
    }

    /**
     * 尝试解析，失败返回 null。
     *
     * 供「配置项写错时静默跳过」这类场景使用；面向用户的金额入口不应使用它 ——
     * 那会把非法输入悄悄变成 0。
     *
     * @param mixed $input
     */
    public static function tryParse($input, bool $allowZero = true): ?int
    {
        try {
            return self::parse($input, $allowZero);
        } catch (InvalidArgumentException $e) {
            return null;
        }
    }

    /**
     * 该字符串是否为合法的金额格式（不抛异常版本）。
     *
     * @param mixed $input
     */
    public static function isValid($input): bool
    {
        return self::tryParse($input, true) !== null;
    }

    /**
     * micro 整数 → 主货币「元」字符串（用于展示、日志、组装支付参数）。
     *
     * @param int $micro
     * @param int $decimals 小数位，默认 2
     */
    public static function format(int $micro, int $decimals = 2): string
    {
        $decimals = max(0, min(self::MAX_DECIMALS, $decimals));
        $sign = $micro < 0 ? '-' : '';
        $abs = (string) abs($micro);

        // 左侧补零到至少 7 位，保证整数部分非空
        $abs = str_pad($abs, self::MAX_DECIMALS + 1, '0', STR_PAD_LEFT);
        $intPart = substr($abs, 0, -self::MAX_DECIMALS);
        $fracPart = substr($abs, -self::MAX_DECIMALS);

        if ($decimals === 0) {
            return $sign . $intPart;
        }

        return $sign . $intPart . '.' . substr($fracPart, 0, $decimals);
    }

    /**
     * 归一化输入为待校验的字符串。
     *
     * 浮点入参走 sprintf 而非直接强转 —— 后者会输出 1.0E+25 这类
     * 科学计数法表示，既不符合白名单，也说明调用方本就不该传浮点。
     *
     * @param mixed $input
     */
    private static function normalize($input): string
    {
        if (is_string($input)) {
            return trim($input);
        }
        if (is_int($input)) {
            return (string) $input;
        }
        if (is_float($input)) {
            // %.6F 强制定点表示（大写 F 不走科学计数法），再做一次裁尾
            return rtrim(rtrim(sprintf('%.' . self::MAX_DECIMALS . 'F', $input), '0'), '.');
        }
        return '';
    }

    /**
     * 纯字符串整数运算：'12.34' → 12340000。
     *
     * 不经 float、不依赖 bcmath —— 位数固定，结果精确。
     */
    private static function toMicro(string $amount): int
    {
        $parts = explode('.', $amount);
        $intPart = $parts[0];
        $fracPart = $parts[1] ?? '';

        // 小数位右补零到 6 位；白名单已保证不会超过 6 位
        $fracPart = str_pad($fracPart, self::MAX_DECIMALS, '0', STR_PAD_RIGHT);

        // ltrim 去掉整数部分的左前导零，避免 '007' 变成 9 位以上字符串；
        // 全零时保留一个 '0'，让拼接结果至少是 7 位。
        $intPart = ltrim($intPart, '0');
        if ($intPart === '') {
            $intPart = '0';
        }

        return (int) ($intPart . $fracPart);
    }
}
