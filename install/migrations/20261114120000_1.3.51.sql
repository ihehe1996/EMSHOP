-- v1.3.51 数据库变更

-- 商品主表：补回历史站点缺失的「使用教程」字段
--
-- `guide` 最早在 v1.3.08 引入，对应的迁移文件已随发版删除。
-- 从更早版本直接升级到当前版本、或历史迁移未成功执行的站点会缺这一列，
-- 查单 / 订单详情查询 `em_goods.guide` 时会报 Unknown column。
-- 这里做幂等补齐：字段已存在时执行 SELECT 1，不做任何变更。
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

-- 本版本发布：迁移时 bump 一次以触发 worker 热重载
UPDATE `__PREFIX__config`
SET `config_value` = CAST(UNIX_TIMESTAMP() AS CHAR)
WHERE `config_name` IN ('server_file_version_pending', 'new_swoole_file_version');
