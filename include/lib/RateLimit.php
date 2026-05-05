<?php

declare(strict_types=1);

/**
 * 通用限流工具：基于 Cache 做"按 key 计数"的固定窗口限流。
 *
 * 设计：
 *   - 每个 key 一个计数器 + 窗口重置时间。窗口内计数累加；窗口过期则归零重计。
 *   - 计数自然随 Cache TTL 过期，不需要后台清理。
 *   - 不依赖 session（IP 限流场景下 session 容易被攻击者每次新建绕过）。
 *
 * 典型用法：
 *   $key = 'find_order:' . RateLimit::clientIp();
 *   if (RateLimit::tooManyAttempts($key, 10)) {
 *       $wait = RateLimit::availableIn($key);
 *       throw new RuntimeException("请求过于频繁，请 {$wait} 秒后再试");
 *   }
 *   RateLimit::hit($key, 60);   // 60 秒窗口
 *   // ... 处理业务 ...
 *
 * 防撞库场景下不用 clear()（成功也计数），让攻击者即使猜中部分凭据也无法突破速率上限。
 */
final class RateLimit
{
    /** Cache key 前缀，避免和其它缓存混 */
    private const PREFIX = 'rl_';

    /**
     * 是否已超出限制（仅查不增计数）。
     */
    public static function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return self::attempts($key) >= $maxAttempts;
    }

    /**
     * 当前窗口内已记录次数。
     */
    public static function attempts(string $key): int
    {
        $state = Cache::get(self::PREFIX . $key);
        if (!is_array($state)) return 0;
        return (int) ($state['count'] ?? 0);
    }

    /**
     * 记录一次命中。窗口过期则重置为 1；否则计数+1，TTL 沿用首次窗口余量。
     * 返回累计后的计数。
     */
    public static function hit(string $key, int $windowSeconds): int
    {
        $cacheKey = self::PREFIX . $key;
        $state = Cache::get($cacheKey);

        $now = time();
        if (!is_array($state) || (int) ($state['reset_at'] ?? 0) <= $now) {
            // 首次或窗口已过：重新开窗
            $state = ['count' => 1, 'reset_at' => $now + $windowSeconds];
            Cache::set($cacheKey, $state, $windowSeconds);
            return 1;
        }

        // 窗口内累加：保留原 reset_at，只刷新计数
        $state['count'] = (int) $state['count'] + 1;
        $ttl = (int) $state['reset_at'] - $now;
        if ($ttl < 1) $ttl = 1;
        Cache::set($cacheKey, $state, $ttl);
        return (int) $state['count'];
    }

    /**
     * 距离当前窗口失效还有多少秒；未限流或已过期返回 0。
     */
    public static function availableIn(string $key): int
    {
        $state = Cache::get(self::PREFIX . $key);
        if (!is_array($state)) return 0;
        $diff = (int) ($state['reset_at'] ?? 0) - time();
        return max(0, $diff);
    }

    /**
     * 清掉某 key 的计数（业务上确认安全后才调用，例如用户注销/管理员重置）。
     * 防撞库场景下不要在"查询成功"时调，否则正确凭据者每次都能重置攻击者计数。
     */
    public static function clear(string $key): void
    {
        Cache::delete(self::PREFIX . $key);
    }

    /**
     * 取请求方真实 IP，兼容常见反代头。
     * 反代头的可信度由部署方负责（前置反代必须是受信节点），此处仅做格式校验。
     */
    public static function clientIp(): string
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
            $_SERVER['HTTP_X_REAL_IP'] ?? '',
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
        ];
        foreach ($candidates as $raw) {
            if ($raw === '') continue;
            // X-Forwarded-For 是逗号分隔列表，取最左（最接近原始客户端）
            $first = trim((string) strtok($raw, ','));
            if ($first !== '' && filter_var($first, FILTER_VALIDATE_IP) !== false) {
                return $first;
            }
        }
        return '0.0.0.0';
    }
}
