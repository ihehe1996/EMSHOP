-- 1.3.17：发货队列索引补全 + 卡密表订单外键列加宽
--
-- ① delivery_queue 缺 (order_goods_id, status) 索引。
--    triggerDelivery() 现在按商品行做幂等短路（查该行是否已有活跃任务），
--    该查询在每次 paid → delivering 时按商品行数执行 N 次。没有索引就是每行
--    一次全表扫描，而队列表随发货量持续增长，会成为下单链路上的热点。
--
-- ② goods_virtual_card.order_id / order_goods_id 宽度不足。
--    这两列原是 INT UNSIGNED（上限约 42.9 亿），而 em_order.id / em_order_goods.id
--    是 BIGINT UNSIGNED。订单 ID 超过上限时会截断或报错。加宽不丢数据。
--
-- 本文件只做「无条件安全」的变更。需要按 (order_id,type) 去重的唯一索引放在
-- 20260926121000_1.3.17.sql —— 单独成文是为了让它万一因脏数据中断时，
-- 不会挡住这里的两项修复（migrate() 遇到失败会立即返回，不再执行后续文件）。

SET @has_idx := (
    SELECT COUNT(*)
    FROM `information_schema`.`STATISTICS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = '__PREFIX__delivery_queue'
      AND `INDEX_NAME` = 'idx_order_goods_status'
);

SET @ddl_add_queue_idx := IF(
    @has_idx > 0,
    'SELECT 1',
    'ALTER TABLE `__PREFIX__delivery_queue` ADD KEY `idx_order_goods_status` (`order_goods_id`, `status`)'
);

PREPARE stmt_add_queue_idx FROM @ddl_add_queue_idx;
EXECUTE stmt_add_queue_idx;
DEALLOCATE PREPARE stmt_add_queue_idx;

-- 卡密表可能尚未创建（虚拟卡插件从未启用过），所以先判表存在、再判列是否还是 INT
SET @card_table_exists := (
    SELECT COUNT(*)
    FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = '__PREFIX__goods_virtual_card'
);

SET @card_needs_widen := (
    SELECT COUNT(*)
    FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = '__PREFIX__goods_virtual_card'
      AND `COLUMN_NAME` = 'order_id'
      AND `COLUMN_TYPE` NOT LIKE 'bigint%'
);

SET @ddl_widen_card := IF(
    @card_table_exists = 0 OR @card_needs_widen = 0,
    'SELECT 1',
    'ALTER TABLE `__PREFIX__goods_virtual_card`
        MODIFY `order_id` BIGINT UNSIGNED DEFAULT NULL COMMENT ''关联订单ID（与 em_order.id 对齐，避免订单ID超过42.9亿时截断）'',
        MODIFY `order_goods_id` BIGINT UNSIGNED DEFAULT NULL COMMENT ''关联订单商品记录ID（与 em_order_goods.id 对齐）'''
);

PREPARE stmt_widen_card FROM @ddl_widen_card;
EXECUTE stmt_widen_card;
DEALLOCATE PREPARE stmt_widen_card;

-- 本版本发布：迁移时 bump 一次以触发 worker 热重载
-- （新增/修改的代码要被 worker 加载，必须让管家重启它们）
UPDATE `__PREFIX__config`
SET `config_value` = CAST(UNIX_TIMESTAMP() AS CHAR)
WHERE `config_name` IN ('server_file_version_pending', 'new_swoole_file_version');
