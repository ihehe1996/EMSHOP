<?php

declare(strict_types=1);

/**
 * 登录限流类。
 *
 * 使用 session 做最小限流，防止后台登录被短时间暴力尝试。
 */
final class LoginThrottle
{
    /**
     * @var array<string, mixed>
     */
    private $config;

    public function __construct()
    {
        $this->config = EM_CONFIG['auth'];
        $this->startSession();
    }

    /**
     * 当前是否处于锁定状态。
     */
    public function isLocked(): bool
    {
        $state = $this->state();
        return !empty($state['locked_until']) && (int) $state['locked_until'] > time();
    }

    /**
     * 返回剩余锁定秒数。
     */
    public function remainingSeconds(): int
    {
        $state = $this->state();
        if (empty($state['locked_until'])) {
            return 0;
        }

        $seconds = (int) $state['locked_until'] - time();
        return $seconds > 0 ? $seconds : 0;
    }

    /**
     * 记录一次失败登录。
     */
    public function hit(): void
    {
        $state = $this->state();
        $state['count'] = (int) $state['count'] + 1;

        if ($state['count'] >= (int) $this->config['max_attempts']) {
            $state['locked_until'] = time() + ((int) $this->config['lock_minutes'] * 60);
            $state['count'] = 0;
        }

        $_SESSION[$this->config['throttle_key']] = $state;
    }

    /**
     * 清理失败记录。
     */
    public function clear(): void
    {
        unset($_SESSION[$this->config['throttle_key']]);
    }

    /**
     * 返回当前限流状态。
     *
     * @return array<string, mixed>
     */
    private function state(): array
    {
        $state = isset($_SESSION[$this->config['throttle_key']]) ? $_SESSION[$this->config['throttle_key']] : [];

        if (!empty($state['locked_until']) && (int) $state['locked_until'] <= time()) {
            $state = [];
            $_SESSION[$this->config['throttle_key']] = $state;
        }

        return array_merge([
            'count' => 0,
            'locked_until' => 0,
        ], $state);
    }

    /**
     * 确保 session 已启动。
     */
    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
