-- v1.3.13 数据库变更

-- 【商城设置】已售罄商品禁止访问（默认开启）
INSERT INTO `__PREFIX__config` (`config_name`, `config_value`, `description`)
SELECT 'shop_block_sold_out_access', '1', '已售罄商品禁止访问'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `__PREFIX__config` WHERE `config_name` = 'shop_block_sold_out_access'
);

-- 【用户设置】注册必填项（默认手机号+邮箱，与历史行为一致）
INSERT INTO `__PREFIX__config` (`config_name`, `config_value`, `description`)
SELECT 'user_register_fields', 'mobile,email', '注册必填项（mobile/email，逗号分隔）'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `__PREFIX__config` WHERE `config_name` = 'user_register_fields'
);

-- 【用户表】允许注册时不填邮箱：去掉 (email, role) 唯一约束，改为普通索引
-- 空邮箱可多人并存；非空邮箱仍由业务层 existsEmail 保证唯一
SET @has_uniq_email := (
    SELECT COUNT(*)
    FROM `information_schema`.`STATISTICS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = '__PREFIX__user'
      AND `INDEX_NAME` = 'uniq_email'
);

SET @ddl_drop_uniq_email := IF(
    @has_uniq_email > 0,
    'ALTER TABLE `__PREFIX__user` DROP INDEX `uniq_email`',
    'SELECT 1'
);
PREPARE stmt FROM @ddl_drop_uniq_email; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx_email_role := (
    SELECT COUNT(*)
    FROM `information_schema`.`STATISTICS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = '__PREFIX__user'
      AND `INDEX_NAME` = 'idx_email_role'
);

SET @ddl_add_idx_email_role := IF(
    @has_idx_email_role > 0,
    'SELECT 1',
    'ALTER TABLE `__PREFIX__user` ADD KEY `idx_email_role` (`email`, `role`)'
);
PREPARE stmt FROM @ddl_add_idx_email_role; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 本版本发布：迁移时 bump 一次以触发 Swoole 热重载
UPDATE `__PREFIX__config`
SET `config_value` = CAST(UNIX_TIMESTAMP() AS CHAR)
WHERE `config_name` IN ('server_file_version_pending', 'new_swoole_file_version');
