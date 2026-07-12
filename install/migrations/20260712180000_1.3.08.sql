-- v1.3.08 数据库变更

-- 商品主表：使用教程（富文本 HTML）
SET @table := '__PREFIX__goods';
SET @has_col := (
    SELECT COUNT(*)
    FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = @table
      AND `COLUMN_NAME` = 'guide'
);

SET @ddl := IF(
    @has_col > 0,
    'SELECT 1',
    CONCAT(
        'ALTER TABLE `', @table, '` ADD COLUMN `guide` TEXT NULL COMMENT ''使用教程'' AFTER `content`'
    )
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 本版本发布：迁移时 bump 一次以触发 Swoole 热重载
UPDATE `__PREFIX__config`
SET `config_value` = CAST(UNIX_TIMESTAMP() AS CHAR)
WHERE `config_name` = 'new_swoole_file_version';
