-- 兜底迁移：确保订单商品发货明细表存在
CREATE TABLE IF NOT EXISTS `__PREFIX__order_goods_delivery_item` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增主键',
    `order_goods_id` BIGINT UNSIGNED NOT NULL COMMENT '订单商品ID',
    `content` TEXT NOT NULL COMMENT '单条发货内容',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_order_goods_sort` (`order_goods_id`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单商品发货明细表';
