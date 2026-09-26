-- 1.3.17：商户余额流水按 (order_id, type) 唯一化 —— 结算幂等的数据库层兜底
--
-- 背景：MerchantLedgerService::settleOrder 的幂等原先只靠「事务外 SELECT 一次」，
-- 是典型的 check-then-write 竞态。并发场景（支付网关重投、后台与发货队列同时
-- 触发订单完成）会写出两条 increase 流水，等于对同一订单重复分账。
-- 代码侧已把守卫挪进事务并对订单行加锁，这里再加唯一索引做兜底。
--
-- 可行性前提：withdraw / withdraw_fee 两类流水的 order_id 为 NULL，而 MySQL 的
-- 唯一索引把 NULL 视为互不相同 —— 所以它们不受该约束。
-- 切勿把 order_id 改成 NOT NULL，否则提现流水会互相冲突。
--
-- 若库里已存在重复行（历史竞态留下的），本迁移会**刻意中断**而不是静默跳过 ——
-- 静默跳过等于把重复分账的 bug 留在线上。中断后请人工核对并删除多余的流水行，
-- 再重新执行升级（失败的文件不会记入 em_migrations，会整文件重跑，语句均可重入）。

SET @dup_groups := (
    SELECT COUNT(*) FROM (
        SELECT 1
        FROM `__PREFIX__merchant_balance_log`
        WHERE `order_id` IS NOT NULL
        GROUP BY `order_id`, `type`
        HAVING COUNT(*) > 1
    ) AS d
);

SET @has_uk := (
    SELECT COUNT(*)
    FROM `information_schema`.`STATISTICS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = '__PREFIX__merchant_balance_log'
      AND `INDEX_NAME` = 'uk_order_type'
);

-- 有重复数据时故意查一个不存在的列，让迁移带着明确的原因失败，
-- 而不是静默跳过（列名即错误信息，长度控制在 MySQL 标识符 64 字符上限内）
SET @ddl_add_uk := IF(
    @dup_groups > 0,
    'SELECT `MERCHANT_BALANCE_LOG_DUPLICATE_ORDER_TYPE_CLEANUP_REQUIRED` FROM `__PREFIX__merchant_balance_log` LIMIT 1',
    IF(
        @has_uk > 0,
        'SELECT 1',
        'ALTER TABLE `__PREFIX__merchant_balance_log` ADD UNIQUE KEY `uk_order_type` (`order_id`, `type`)'
    )
);

PREPARE stmt_add_uk FROM @ddl_add_uk;
EXECUTE stmt_add_uk;
DEALLOCATE PREPARE stmt_add_uk;

-- 本版本发布：迁移时 bump 一次以触发 worker 热重载
UPDATE `__PREFIX__config`
SET `config_value` = CAST(UNIX_TIMESTAMP() AS CHAR)
WHERE `config_name` IN ('server_file_version_pending', 'new_swoole_file_version');
