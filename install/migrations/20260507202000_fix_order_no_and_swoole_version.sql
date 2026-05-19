-- 兼容历史订单字段：优先把 out_trade_no 重命名为 order_no；缺失则补建 order_no
SET @order_table := '__PREFIX__order';
SET @has_order_table := (
    SELECT COUNT(*)
    FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = @order_table
);
SET @has_out_trade_no := (
    SELECT COUNT(*)
    FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = @order_table
      AND `COLUMN_NAME` = 'out_trade_no'
);
SET @has_order_no := (
    SELECT COUNT(*)
    FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = @order_table
      AND `COLUMN_NAME` = 'order_no'
);
SET @order_ddl := IF(
    @has_order_table = 0,
    'SELECT 1',
    IF(
        @has_out_trade_no > 0 AND @has_order_no = 0,
        CONCAT('ALTER TABLE `', @order_table, '` CHANGE COLUMN `out_trade_no` `order_no` VARCHAR(32) NOT NULL COMMENT ''订单编号'''),
        IF(
            @has_out_trade_no = 0 AND @has_order_no = 0,
            CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `order_no` VARCHAR(32) NOT NULL DEFAULT '''' COMMENT ''订单编号'' AFTER `id`'),
            'SELECT 1'
        )
    )
);
PREPARE order_stmt FROM @order_ddl;
EXECUTE order_stmt;
DEALLOCATE PREPARE order_stmt;

-- 刷新最新 Swoole 文件版本为当前时间戳
UPDATE `__PREFIX__config`
SET `config_value` = CAST(UNIX_TIMESTAMP() AS CHAR)
WHERE `config_name` = 'new_swoole_file_version';
