-- v1.3.13 数据库变更

-- 【商城设置】已售罄商品禁止访问（默认开启）
INSERT INTO `__PREFIX__config` (`config_name`, `config_value`, `description`)
SELECT 'shop_block_sold_out_access', '1', '已售罄商品禁止访问'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `__PREFIX__config` WHERE `config_name` = 'shop_block_sold_out_access'
);

-- 本版本发布：迁移时 bump 一次以触发 Swoole 热重载
UPDATE `__PREFIX__config`
SET `config_value` = CAST(UNIX_TIMESTAMP() AS CHAR)
WHERE `config_name` IN ('server_file_version_pending', 'new_swoole_file_version');
