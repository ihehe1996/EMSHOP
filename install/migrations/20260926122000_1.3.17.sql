-- 1.3.17：登录失败计数表（跨会话持久化）
--
-- 背景：登录限流原先把失败次数存在 $_SESSION 里。攻击者只要每次请求丢弃 Cookie
-- 就是一个全新会话，计数器每次从 0 开始 —— 等于完全没有限流，可以无限爆破后台密码。
-- 改为落库并按「账号 + IP」与「纯 IP」两个维度计数。
--
-- 代码侧另有兜底：LoginThrottle::ensureTable() 会在表缺失时自动建表，
-- 因此即使本迁移尚未执行，也不会把登录打挂。此处的建表是权威定义。

SET @has_table := (
    SELECT COUNT(*)
    FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = '__PREFIX__login_attempt'
);

SET @ddl_create_login_attempt := IF(
    @has_table > 0,
    'SELECT 1',
    'CREATE TABLE `__PREFIX__login_attempt` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `scope` VARCHAR(32) NOT NULL COMMENT ''作用域，如 admin'',
        `key_hash` CHAR(64) NOT NULL COMMENT ''计数键的 sha256（账号+IP 或 纯 IP）'',
        `attempts` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''当前窗口内失败次数'',
        `locked_until` DATETIME DEFAULT NULL COMMENT ''锁定到期时间'',
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_scope_key` (`scope`, `key_hash`),
        KEY `idx_locked_until` (`locked_until`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''登录失败计数（跨会话持久化）'''
);

PREPARE stmt_create_login_attempt FROM @ddl_create_login_attempt;
EXECUTE stmt_create_login_attempt;
DEALLOCATE PREPARE stmt_create_login_attempt;

-- 本版本发布：迁移时 bump 一次以触发 worker 热重载
UPDATE `__PREFIX__config`
SET `config_value` = CAST(UNIX_TIMESTAMP() AS CHAR)
WHERE `config_name` IN ('server_file_version_pending', 'new_swoole_file_version');
