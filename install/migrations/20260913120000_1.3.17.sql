-- 1.3.17：插件事件表
--
-- 背景：后台任务里的跨插件通知原本用 doAction() 在内存里直接调用订阅方的闭包。
-- 这要求订阅方的代码必须存在于通知方的进程里 —— 于是更新订阅方插件，
-- 就必须重启"别人的" worker（而且要重载全部 worker 才能保证不漏）。
--
-- 改成事件落库：通知方只写一行，订阅方用自己的 worker 按游标取走处理。
-- 这样每个插件的代码只在自己进程里跑，更新/启停只影响它自己的 worker。

SET @has_plugin_event := (
    SELECT COUNT(*)
    FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = '__PREFIX__plugin_event'
);

SET @ddl_create_plugin_event := IF(
    @has_plugin_event > 0,
    'SELECT 1',
    'CREATE TABLE `__PREFIX__plugin_event` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT \'自增主键（同时是订阅方的游标值）\',
        `event` VARCHAR(64) NOT NULL COMMENT \'事件名，如 order_goods_delivery_queued_success\',
        `args` TEXT COMMENT \'JSON：事件的位置参数数组，与订阅方钩子签名一一对应\',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT \'创建时间\',
        PRIMARY KEY (`id`),
        KEY `idx_event_id` (`event`, `id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'插件事件表（发布-订阅，订阅方自行记录游标）\''
);

PREPARE stmt_create_plugin_event FROM @ddl_create_plugin_event;
EXECUTE stmt_create_plugin_event;
DEALLOCATE PREPARE stmt_create_plugin_event;

-- 本版本发布：迁移时 bump 一次以触发 worker 热重载
-- （新增/修改的代码要被 worker 加载，必须让管家重启它们）
UPDATE `__PREFIX__config`
SET `config_value` = CAST(UNIX_TIMESTAMP() AS CHAR)
WHERE `config_name` IN ('server_file_version_pending', 'new_swoole_file_version');
