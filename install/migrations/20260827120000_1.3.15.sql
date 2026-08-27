-- 1.3.15：密码重置令牌表

SET @has_password_reset := (
    SELECT COUNT(*)
    FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = '__PREFIX__password_reset'
);

SET @ddl_create_password_reset := IF(
    @has_password_reset > 0,
    'SELECT 1',
    'CREATE TABLE `__PREFIX__password_reset` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT \'自增主键\',
        `user_id` BIGINT UNSIGNED NOT NULL COMMENT \'用户ID\',
        `email` VARCHAR(120) NOT NULL COMMENT \'申请重置时的邮箱\',
        `token_hash` CHAR(64) NOT NULL COMMENT \'令牌 SHA256 哈希\',
        `expires_at` DATETIME NOT NULL COMMENT \'过期时间\',
        `used_at` DATETIME DEFAULT NULL COMMENT \'使用时间\',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT \'创建时间\',
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_token_hash` (`token_hash`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_email_created` (`email`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'密码重置令牌\''
);
PREPARE stmt FROM @ddl_create_password_reset; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 本版本发布：迁移时 bump 一次以触发 Swoole 热重载
UPDATE `__PREFIX__config`
SET `config_value` = CAST(UNIX_TIMESTAMP() AS CHAR)
WHERE `config_name` IN ('server_file_version_pending', 'new_swoole_file_version');
