-- 补齐 order.delivery_time 字段（不存在时创建）
SET @order_table := '__PREFIX__order';
SET @has_order_table := (
    SELECT COUNT(*)
    FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = @order_table
);

SET @has_delivery_time := (
    SELECT COUNT(*)
    FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = @order_table
      AND `COLUMN_NAME` = 'delivery_time'
);

SET @ddl := IF(
    @has_order_table = 0 OR @has_delivery_time > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `delivery_time` DATETIME NULL DEFAULT NULL')
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_complete_time := (
    SELECT COUNT(*)
    FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = @order_table
      AND `COLUMN_NAME` = 'complete_time'
);

SET @ddl := IF(
    @has_order_table = 0 OR @has_complete_time > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `complete_time` DATETIME NULL DEFAULT NULL')
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

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
