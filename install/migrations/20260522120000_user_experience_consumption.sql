-- 用户：总消费、经验值（标准 MySQL 语法，经 em_migrations 仅执行一次）
ALTER TABLE `__PREFIX__user`
    ADD COLUMN `total_consumption` BIGINT NOT NULL DEFAULT 0 COMMENT '累计消费金额×1000000' AFTER `level_id`;

ALTER TABLE `__PREFIX__user`
    ADD COLUMN `experience` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '经验值' AFTER `total_consumption`;

-- 订单经验结算记录（幂等：同一 order_id 仅结算一次）
CREATE TABLE IF NOT EXISTS `__PREFIX__user_experience_order` (
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '订单ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `pay_amount` BIGINT NOT NULL DEFAULT 0 COMMENT '计入累计消费的实付×1000000',
    `exp_gained` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '本单赠送经验',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`order_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单经验/消费结算记录';

-- 用户设置：每消费 1 元赠送经验（缺省 0）
INSERT INTO `__PREFIX__config` (`config_name`, `config_value`, `description`)
SELECT 'user_exp_per_yuan', '0', '每消费1元赠送经验值'
WHERE NOT EXISTS (
    SELECT 1 FROM `__PREFIX__config` WHERE `config_name` = 'user_exp_per_yuan'
);

-- 注册初始经验 / 经验名称（老站可能尚无，补默认）
INSERT INTO `__PREFIX__config` (`config_name`, `config_value`, `description`)
SELECT 'user_credit_name', '经验', '前台经验名称展示'
WHERE NOT EXISTS (
    SELECT 1 FROM `__PREFIX__config` WHERE `config_name` = 'user_credit_name'
);

INSERT INTO `__PREFIX__config` (`config_name`, `config_value`, `description`)
SELECT 'user_credit_initial', '0', '注册赠送初始经验值'
WHERE NOT EXISTS (
    SELECT 1 FROM `__PREFIX__config` WHERE `config_name` = 'user_credit_initial'
);
