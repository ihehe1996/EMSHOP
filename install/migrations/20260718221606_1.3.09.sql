-- v1.3.09 数据库变更

-- 系统配置：开放登录（默认开启）
INSERT INTO `__PREFIX__config` (`config_name`, `config_value`, `description`)
VALUES ('user_login', '1', '开放登录')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- 本版本发布：迁移时 bump 一次以触发 Swoole 热重载
UPDATE `__PREFIX__config`
SET `config_value` = CAST(UNIX_TIMESTAMP() AS CHAR)
WHERE `config_name` = 'new_swoole_file_version';
