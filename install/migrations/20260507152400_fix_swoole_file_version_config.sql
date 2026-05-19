-- 兼容已发布版本：若配置项缺失则补齐，再按最新规则重置
INSERT INTO `__PREFIX__config` (`config_name`, `config_value`, `description`)
SELECT 'local_swoole_file_version', '0', '本地 Swoole 文件版本'
WHERE NOT EXISTS (
    SELECT 1 FROM `__PREFIX__config` WHERE `config_name` = 'local_swoole_file_version'
);

INSERT INTO `__PREFIX__config` (`config_name`, `config_value`, `description`)
SELECT 'new_swoole_file_version', CAST(UNIX_TIMESTAMP() AS CHAR), '最新 Swoole 文件版本'
WHERE NOT EXISTS (
    SELECT 1 FROM `__PREFIX__config` WHERE `config_name` = 'new_swoole_file_version'
);

UPDATE `__PREFIX__config`
SET `config_value` = '0'
WHERE `config_name` = 'local_swoole_file_version';

UPDATE `__PREFIX__config`
SET `config_value` = CAST(UNIX_TIMESTAMP() AS CHAR)
WHERE `config_name` = 'new_swoole_file_version';
