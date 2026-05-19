INSERT INTO `__PREFIX__config` (`config_name`, `config_value`, `description`)
SELECT 'site_url', '', '站点地址（含 http(s)）'
WHERE NOT EXISTS (
    SELECT 1 FROM `__PREFIX__config` WHERE `config_name` = 'site_url'
);
