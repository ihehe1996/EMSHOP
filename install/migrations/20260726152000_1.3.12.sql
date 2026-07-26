-- v1.3.12 数据库变更

-- 任务服务文件版本：新 key（applied / pending）
-- 初值优先从旧 local/new_swoole_file_version 拷贝，没有则用当前时间戳
INSERT INTO `__PREFIX__config` (`config_name`, `config_value`, `description`)
SELECT
    'server_file_version_applied',
    COALESCE(
        (
            SELECT `config_value`
            FROM (SELECT `config_value` FROM `__PREFIX__config` WHERE `config_name` = 'local_swoole_file_version' LIMIT 1) AS `src`
        ),
        CAST(UNIX_TIMESTAMP() AS CHAR)
    ),
    '任务服务文件版本（已生效）'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `__PREFIX__config` WHERE `config_name` = 'server_file_version_applied'
);

INSERT INTO `__PREFIX__config` (`config_name`, `config_value`, `description`)
SELECT
    'server_file_version_pending',
    COALESCE(
        (
            SELECT `config_value`
            FROM (SELECT `config_value` FROM `__PREFIX__config` WHERE `config_name` = 'new_swoole_file_version' LIMIT 1) AS `src`
        ),
        CAST(UNIX_TIMESTAMP() AS CHAR)
    ),
    '任务服务文件版本（待生效）'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `__PREFIX__config` WHERE `config_name` = 'server_file_version_pending'
);

-- 本版本发布：bump pending（新旧 key 双写，触发任务服务重载 worker）
UPDATE `__PREFIX__config`
SET `config_value` = CAST(UNIX_TIMESTAMP() AS CHAR)
WHERE `config_name` IN ('server_file_version_pending', 'new_swoole_file_version');
