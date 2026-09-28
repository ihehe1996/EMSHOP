<?php

declare(strict_types=1);

/**
 * 登录限流类。
 *
 * 计数落库（em_login_attempt），**不再使用 session**。
 *
 * 为什么必须落库：原先把失败次数存在 $_SESSION 里，而攻击者只要每次请求丢弃
 * Cookie 就是一个全新会话 —— 计数器每次从 0 开始，等于完全没有限流。
 *
 * 同时按两个维度计数：
 *   - 账号 + IP：挡住针对某个账号的定向爆破
 *   - 纯 IP    ：挡住换着账号名喷洒（credential stuffing）
 * 任一把锁生效即视为锁定。
 *
 * 注意：IP 取自 RateLimit::clientIp()，其默认不信任 X-Forwarded-For 等转发头
 * （那些头由请求方控制）。部署在反代/CDN 后面的站点需配置 trusted_proxies，
 * 否则所有访客会被当成同一个 IP 计数。
 */
final class LoginThrottle
{
    /** 各作用域的计数互相独立（em_login_attempt 的 uk_scope_key 保证） */
    public const SCOPE_ADMIN = 'admin';
    public const SCOPE_FRONT = 'front';

    /**
     * 作用域：后台登录 / 前台登录 / 预留分站后台。
     *
     * 前后台分开计数是有意的：前台被爆破锁住时，不能连带把管理员挡在后台之外。
     */
    private string $scope;

    /**
     * @var array<string, mixed>
     */
    private $config;

    /** 表名（含前缀） */
    private $table;

    /** 是否已确认过表存在（每请求只检查一次） */
    private static $tableChecked = false;

    public function __construct(string $scope = self::SCOPE_ADMIN)
    {
        $this->scope = trim($scope) !== '' ? trim($scope) : self::SCOPE_ADMIN;
        $this->config = EM_CONFIG['auth'];
        $this->table = Database::prefix() . 'login_attempt';
    }

    /**
     * 当前是否处于锁定状态。
     *
     * @param string $account 登录账号；为空时只按 IP 维度判断
     */
    public function isLocked(string $account = ''): bool
    {
        return $this->lockedUntil($account) > time();
    }

    /**
     * 返回剩余锁定秒数。
     */
    public function remainingSeconds(string $account = ''): int
    {
        $until = $this->lockedUntil($account);
        return $until > time() ? $until - time() : 0;
    }

    /**
     * 记录一次失败登录。
     */
    public function hit(string $account = ''): void
    {
        $this->ensureTable();

        foreach ($this->keysFor($account) as $key) {
            $this->bump($key);
        }
    }

    /**
     * 清理失败记录（登录成功后调用）。
     */
    public function clear(string $account = ''): void
    {
        $this->ensureTable();

        foreach ($this->keysFor($account) as $key) {
            Database::execute(
                'DELETE FROM `' . $this->table . '` WHERE `scope` = ? AND `key_hash` = ?',
                [$this->scope, $key]
            );
        }
    }

    /**
     * 需要计数的键集合。
     *
     * @return array<int, string>
     */
    private function keysFor(string $account): array
    {
        $ip = RateLimit::clientIp();
        $account = strtolower(trim($account));

        $keys = [hash('sha256', 'ip|' . $ip)];
        if ($account !== '') {
            $keys[] = hash('sha256', 'acct|' . $account . '|' . $ip);
        }

        return $keys;
    }

    /**
     * 取所有计数键中最晚的解锁时间。
     */
    private function lockedUntil(string $account): int
    {
        $max = 0;

        try {
            $this->ensureTable();
            foreach ($this->keysFor($account) as $key) {
                $row = Database::fetchOne(
                    'SELECT `locked_until` FROM `' . $this->table . '`
                      WHERE `scope` = ? AND `key_hash` = ? LIMIT 1',
                    [$this->scope, $key]
                );
                if ($row === null || empty($row['locked_until'])) {
                    continue;
                }
                $ts = strtotime((string) $row['locked_until']);
                if ($ts !== false && $ts > $max) {
                    $max = $ts;
                }
            }
        } catch (Throwable $e) {
            // 限流表不可用时不能把登录整个打死：放行本次判断（fail-open），
            // 但留一条系统日志，便于站长发现「表没建 / 库有问题」。
            self::logThrottleFailure($e);
            return 0;
        }

        return $max;
    }

    /**
     * 累加一次失败；达到阈值则置锁。
     *
     * 用 INSERT ... ON DUPLICATE KEY UPDATE 一条语句完成自增 ——
     * 「先 SELECT 再 UPDATE」在并发下会丢计数，正好被爆破利用。
     */
    private function bump(string $key): void
    {
        $maxAttempts = max(1, (int) ($this->config['max_attempts'] ?? 5));
        $lockMinutes = max(1, (int) ($this->config['lock_minutes'] ?? 15));

        Database::execute(
            'INSERT INTO `' . $this->table . '` (`scope`, `key_hash`, `attempts`, `locked_until`, `updated_at`)
             VALUES (?, ?, 1, NULL, NOW())
             ON DUPLICATE KEY UPDATE `attempts` = `attempts` + 1, `updated_at` = NOW()',
            [$this->scope, $key]
        );

        Database::execute(
            'UPDATE `' . $this->table . '`
                SET `locked_until` = DATE_ADD(NOW(), INTERVAL ? MINUTE), `attempts` = 0
              WHERE `scope` = ? AND `key_hash` = ? AND `attempts` >= ?',
            [$lockMinutes, $this->scope, $key, $maxAttempts]
        );
    }

    /**
     * 确保限流表存在。
     *
     * 正常路径由 InstallService（新装）与 install/migrations（升级）建表；
     * 这里再兜一次「代码已更新但迁移尚未执行」的窗口 —— 否则登录会直接报错。
     * 每请求只检查一次。
     */
    private function ensureTable(): void
    {
        if (self::$tableChecked) {
            return;
        }
        self::$tableChecked = true;

        Database::statement(
            'CREATE TABLE IF NOT EXISTS `' . $this->table . '` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `scope` VARCHAR(32) NOT NULL COMMENT \'作用域，如 admin\',
                `key_hash` CHAR(64) NOT NULL COMMENT \'计数键的 sha256（账号+IP / 纯 IP）\',
                `attempts` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT \'当前窗口内失败次数\',
                `locked_until` DATETIME DEFAULT NULL COMMENT \'锁定到期时间\',
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_scope_key` (`scope`, `key_hash`),
                KEY `idx_locked_until` (`locked_until`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'登录失败计数（跨会话持久化）\''
        );
    }

    /**
     * 记录限流组件自身的故障，不抛给调用方。
     */
    private static function logThrottleFailure(Throwable $e): void
    {
        try {
            if (class_exists('SystemLogModel')) {
                (new SystemLogModel())->error(
                    'system',
                    '登录限流不可用',
                    '读取登录失败计数失败，本次登录限流被跳过：' . $e->getMessage(),
                    []
                );
            }
        } catch (Throwable $ignore) {
            // 日志本身失败也不能影响登录
        }
    }
}
