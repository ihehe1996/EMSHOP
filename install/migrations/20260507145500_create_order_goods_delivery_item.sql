-- 订单商品发货明细：支持一条订单商品对应多条发货内容（按行存储）
CREATE TABLE IF NOT EXISTS `__PREFIX__order_goods_delivery_item` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增主键',
    `order_goods_id` BIGINT UNSIGNED NOT NULL COMMENT '订单商品ID',
    `content` TEXT NOT NULL COMMENT '单条发货内容',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_order_goods_sort` (`order_goods_id`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单商品发货明细表';

-- 重置 Swoole 本地文件版本，并刷新最新文件版本为当前时间戳
UPDATE `__PREFIX__config`
SET `config_value` = '0'
WHERE `config_name` = 'local_swoole_file_version';

UPDATE `__PREFIX__config`
SET `config_value` = CAST(UNIX_TIMESTAMP() AS CHAR)
WHERE `config_name` = 'new_swoole_file_version';
