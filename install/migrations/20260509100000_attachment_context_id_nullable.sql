-- attachment.context_id 允许 NULL，与安装脚本及后台通用上传（未传关联 ID）一致

SET @attachment_table := '__PREFIX__attachment';

SET @has_attachment_table := (
    SELECT COUNT(*)
    FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = @attachment_table
);

SET @has_context_id := (
    SELECT COUNT(*)
    FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = @attachment_table
      AND `COLUMN_NAME` = 'context_id'
);

SET @context_id_nullable := (
    SELECT `IS_NULLABLE`
    FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = @attachment_table
      AND `COLUMN_NAME` = 'context_id'
    LIMIT 1
);

SET @ddl := IF(
    @has_attachment_table = 0 OR @has_context_id = 0 OR IFNULL(@context_id_nullable, '') = 'YES',
    'SELECT 1',
    CONCAT(
        'ALTER TABLE `',
        @attachment_table,
        '` MODIFY COLUMN `context_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT ''关联记录ID'''
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 更新 new_swoole_file_version 配置值为当前时间戳
SET @config_table := '__PREFIX__config';
SET @has_config_table := (
    SELECT COUNT(*)
    FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = @config_table
);

SET @ddl := IF(
    @has_config_table = 0,
    'SELECT 1',
    CONCAT(
        'UPDATE `', @config_table, '` ',
        'SET `config_value` = UNIX_TIMESTAMP() ',
        'WHERE `config_name` = ''new_swoole_file_version'''
    )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
