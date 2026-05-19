-- 兼容历史订单表字段，按需补齐/修正（全部字段允许为空）
SET @order_table := '__PREFIX__order';
SET @has_order_table := (
    SELECT COUNT(*)
    FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = @order_table
);

SET @has_guest_token := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'guest_token'
);
SET @ddl := IF(@has_order_table = 0 OR @has_guest_token > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `guest_token` VARCHAR(64) NULL DEFAULT NULL'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_owner_id := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'owner_id'
);
SET @ddl := IF(@has_order_table = 0 OR @has_owner_id > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `owner_id` INT(10) NULL DEFAULT 0'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_merchant_id := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'merchant_id'
);
SET @ddl := IF(@has_order_table = 0 OR @has_merchant_id > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `merchant_id` INT(10) NULL DEFAULT 0'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_goods_amount := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'goods_amount'
);
SET @ddl := IF(@has_order_table = 0 OR @has_goods_amount > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `goods_amount` BIGINT(20) NULL DEFAULT 0'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_discount_amount := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'discount_amount'
);
SET @ddl := IF(@has_order_table = 0 OR @has_discount_amount > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `discount_amount` BIGINT(20) NULL DEFAULT 0'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_pay_amount := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'pay_amount'
);
SET @ddl := IF(@has_order_table = 0 OR @has_pay_amount > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `pay_amount` BIGINT(20) NULL DEFAULT 0'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_payment_code := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'payment_code'
);
SET @ddl := IF(@has_order_table = 0 OR @has_payment_code > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `payment_code` VARCHAR(30) NULL DEFAULT NULL'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_payment_name := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'payment_name'
);
SET @ddl := IF(@has_order_table = 0 OR @has_payment_name > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `payment_name` VARCHAR(30) NULL DEFAULT NULL'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_payment_plugin := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'payment_plugin'
);
SET @ddl := IF(@has_order_table = 0 OR @has_payment_plugin > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `payment_plugin` VARCHAR(30) NULL DEFAULT NULL'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_payment_plugin_name := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'payment_plugin_name'
);
SET @ddl := IF(@has_order_table = 0 OR @has_payment_plugin_name > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `payment_plugin_name` VARCHAR(50) NULL DEFAULT NULL'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_payment_channel := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'payment_channel'
);
SET @ddl := IF(@has_order_table = 0 OR @has_payment_channel > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `payment_channel` VARCHAR(50) NULL DEFAULT NULL'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_inviter_l1 := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'inviter_l1'
);
SET @ddl := IF(@has_order_table = 0 OR @has_inviter_l1 > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `inviter_l1` INT(10) NULL DEFAULT 0'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_inviter_l2 := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'inviter_l2'
);
SET @ddl := IF(@has_order_table = 0 OR @has_inviter_l2 > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `inviter_l2` INT(10) NULL DEFAULT 0'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_contact_info := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'contact_info'
);
SET @ddl := IF(@has_order_table = 0 OR @has_contact_info > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `contact_info` TEXT NULL'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_order_password := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'order_password'
);
SET @ddl := IF(@has_order_table = 0 OR @has_order_password > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `order_password` VARCHAR(20) NULL DEFAULT NULL'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_client_ip := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'client_ip'
);
SET @has_ip := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'ip'
);
SET @ddl := IF(
    @has_order_table = 0 OR @has_client_ip = 0 OR @has_ip > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @order_table, '` CHANGE COLUMN `client_ip` `ip` VARCHAR(45) NULL DEFAULT NULL')
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_source := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'source'
);
SET @ddl := IF(@has_order_table = 0 OR @has_source > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `source` VARCHAR(30) NULL DEFAULT NULL'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_display_currency_code := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'display_currency_code'
);
SET @ddl := IF(@has_order_table = 0 OR @has_display_currency_code > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `display_currency_code` VARCHAR(5) NULL DEFAULT NULL'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_display_rate := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'display_rate'
);
SET @ddl := IF(@has_order_table = 0 OR @has_display_rate > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `display_rate` BIGINT(20) NULL DEFAULT 0'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_shipping_address_snapshot := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'shipping_address_snapshot'
);
SET @ddl := IF(@has_order_table = 0 OR @has_shipping_address_snapshot > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `shipping_address_snapshot` TEXT NULL'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_delivery_callback_url := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'delivery_callback_url'
);
SET @ddl := IF(@has_order_table = 0 OR @has_delivery_callback_url > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `delivery_callback_url` VARCHAR(255) NULL DEFAULT NULL'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_created_at := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'created_at'
);
SET @ddl := IF(@has_order_table = 0 OR @has_created_at > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `created_at` DATETIME NULL DEFAULT NULL'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_updated_at := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'updated_at'
);
SET @ddl := IF(@has_order_table = 0 OR @has_updated_at > 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_status := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'status'
);
SET @ddl := IF(@has_order_table = 0 OR @has_status = 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` MODIFY COLUMN `status` VARCHAR(20) NULL DEFAULT ''pending'''));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_coupon_code := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'coupon_code'
);
SET @ddl := IF(@has_order_table = 0 OR @has_coupon_code = 0, 'SELECT 1', CONCAT('ALTER TABLE `', @order_table, '` MODIFY COLUMN `coupon_code` VARCHAR(32) NULL DEFAULT NULL'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_pay_time := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'pay_time'
);
SET @pay_time_is_datetime := (
    SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = @order_table AND `COLUMN_NAME` = 'pay_time' AND LOWER(`DATA_TYPE`) = 'datetime'
);
SET @need_recreate_pay_time := IF(
    @has_order_table = 0,
    0,
    IF(@has_pay_time = 0, 1, IF(@pay_time_is_datetime > 0, 0, 1))
);

SET @ddl := IF(
    @has_order_table = 0 OR @has_pay_time = 0 OR @pay_time_is_datetime > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @order_table, '` DROP COLUMN `pay_time`')
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl := IF(
    @need_recreate_pay_time = 0,
    'SELECT 1',
    CONCAT('ALTER TABLE `', @order_table, '` ADD COLUMN `pay_time` DATETIME NULL DEFAULT NULL')
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
