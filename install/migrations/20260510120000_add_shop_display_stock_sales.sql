-- 商城设置：前台库存/销量展示开关（缺省则插入，默认开启）
INSERT INTO `__PREFIX__config` (`config_name`, `config_value`, `description`)
SELECT 'shop_display_stock', '1', '前台显示库存'
WHERE NOT EXISTS (
    SELECT 1 FROM `__PREFIX__config` WHERE `config_name` = 'shop_display_stock'
);

INSERT INTO `__PREFIX__config` (`config_name`, `config_value`, `description`)
SELECT 'shop_display_sales', '1', '前台显示销量'
WHERE NOT EXISTS (
    SELECT 1 FROM `__PREFIX__config` WHERE `config_name` = 'shop_display_sales'
);

-- 本版本发布时尚未有「系统更新 finalize 写入 new_swoole_file_version」；迁移时 bump 一次以触发 Swoole 热重载（下版本可删除本段）
UPDATE `__PREFIX__config`
SET `config_value` = CAST(UNIX_TIMESTAMP() AS CHAR)
WHERE `config_name` = 'new_swoole_file_version';

-- 网站 favicon（缺省则插入，留空表示使用根目录 /favicon.ico）
INSERT INTO `__PREFIX__config` (`config_name`, `config_value`, `description`)
SELECT 'site_favicon', '', '网站 favicon（留空使用根目录 /favicon.ico）'
WHERE NOT EXISTS (
    SELECT 1 FROM `__PREFIX__config` WHERE `config_name` = 'site_favicon'
);
