-- 虚拟商品卡密表：按 goods_id + spec_id + status 查询优化
SET @table := '__PREFIX__goods_virtual_card';
SET @has_table := (
    SELECT COUNT(*)
    FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = @table
);

SET @has_index := (
    SELECT COUNT(*)
    FROM `information_schema`.`STATISTICS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = @table
      AND `INDEX_NAME` = 'idx_goods_spec_status'
);

SET @ddl := IF(
    @has_table = 0 OR @has_index > 0,
    'SELECT 1',
    CONCAT(
        'CREATE INDEX `idx_goods_spec_status` ON `', @table, '` (`goods_id`, `spec_id`, `status`, `id`)'
    )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
