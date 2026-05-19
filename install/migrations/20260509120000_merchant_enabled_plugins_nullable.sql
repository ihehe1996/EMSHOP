-- 与全新安装一致：em_merchant.enabled_plugins 允许为 NULL，便于插入时省略该字段



ALTER TABLE `__PREFIX__merchant` MODIFY COLUMN `enabled_plugins` TEXT NULL DEFAULT NULL COMMENT '商户启用插件 slug（逗号分隔；空/NULL 表示未配置）';



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

