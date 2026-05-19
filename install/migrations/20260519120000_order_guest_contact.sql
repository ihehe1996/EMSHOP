-- v1.3.01版本改动
ALTER TABLE `__PREFIX__order` ADD COLUMN `guest_contact` VARCHAR(255) DEFAULT NULL COMMENT '游客联系方式（查单凭据）' AFTER `contact_info`;
