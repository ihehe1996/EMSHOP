<?php

declare(strict_types=1);

/**
 * 算术验证码（人类友好的反爬门槛）。
 *
 * 用法：
 *   $expr = Captcha::issue('find_order');   // 返回 "3 + 5"，同时写入 session
 *   // 渲染到页面...
 *   if (!Captcha::verify($_POST['captcha'], 'find_order')) {
 *       error('验证码错误');
 *   }
 *
 * 设计要点：
 *   - 加 / 减 两种运算，单位数操作数，结果 ≥ 0，便于心算
 *   - 一次一用：verify 不论成功失败都 consume，避免重放
 *   - 按 scope 隔离 session key，多个表单互不干扰
 *
 * 注意：算术 captcha 单独防不了脚本（OCR/eval 都能秒解），它的作用是过滤无脑机器人；
 *      真正的撞库防线是 RateLimit 按 IP 速率上限。两层叠加才有意义。
 */
final class Captcha
{
    private const SESSION_PREFIX = 'em_captcha_';

    /**
     * 生成新一题，写入 session，返回展示字符串（如 "3 + 5"）。
     * 调用方负责把这个串拼到页面上让用户作答。
     */
    public static function issue(string $scope = 'default'): string
    {
        self::ensureSession();
        [$expr, $answer] = self::pickProblem();
        $_SESSION[self::SESSION_PREFIX . $scope] = [
            'answer' => $answer,
            'issued_at' => time(),
        ];
        return $expr;
    }

    /**
     * 校验用户答案。
     * 验证后立即 consume（无论对错都失效）—— 用户答错也要刷新一道，避免重放。
     */
    public static function verify(string $userAnswer, string $scope = 'default'): bool
    {
        self::ensureSession();
        $key = self::SESSION_PREFIX . $scope;
        $state = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);   // 一次一用
        if (!is_array($state) || !isset($state['answer'])) {
            return false;
        }
        // 5 分钟有效期，避免长时间挂在那里被慢慢撞
        if ((int) ($state['issued_at'] ?? 0) + 300 < time()) {
            return false;
        }
        $userAnswer = trim($userAnswer);
        if ($userAnswer === '' || !ctype_digit(ltrim($userAnswer, '-'))) {
            return false;
        }
        return (int) $userAnswer === (int) $state['answer'];
    }

    /**
     * 是否已签发过题目（用于判断"未提交答案"vs"已答错"）。
     */
    public static function exists(string $scope = 'default'): bool
    {
        self::ensureSession();
        return isset($_SESSION[self::SESSION_PREFIX . $scope]);
    }

    /**
     * 随机出一道加 / 减算术题，返回 [显示串, 数值答案]。
     * 数字 1-9，减法保证非负。
     *
     * @return array{0: string, 1: int}
     */
    private static function pickProblem(): array
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $op = random_int(0, 1) === 0 ? '+' : '-';
        if ($op === '-' && $a < $b) {
            // 保证非负
            [$a, $b] = [$b, $a];
        }
        $answer = $op === '+' ? $a + $b : $a - $b;
        return ["{$a} {$op} {$b}", $answer];
    }

    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }
}
