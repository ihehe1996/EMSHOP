-- 1.3.17：把遗留的小数形式金额配置归一为 ×1000000 整数（micro）
--
-- 背景：后台「商城配置」里的金额项（充值/提现上下限）历史上可能以十进制字符串
-- （如 "10.00"）存在，也可能以 micro 整数（如 "10000000"）存在。读取端
-- admin/view/settings.php 的 formMoney() 用「是否 ≥7 位纯数字」来猜格式，
-- 于是小于 1.00 的 micro 值（例如 0.50 → "500000"，只有 6 位）会被当成十进制原样显示，
-- 管理员一旦再次保存，写入端会再乘一次 1000000 ——
--   最低充值 0.50 变成 500000.00（用户再也无法充值）
--   最低提现 0.50 变成 500000.00（用户再也无法提现）
--
-- 本迁移把这些值统一成 micro 整数，从此读取端可以无条件按整数处理，歧义消失。
-- 幂等：只处理含小数点、且去掉小数点后是纯数字的值；重复执行不会再改任何行。

UPDATE `__PREFIX__config`
SET `config_value` = CAST(ROUND(CAST(`config_value` AS DECIMAL(20,6)) * 1000000) AS CHAR)
WHERE `config_name` IN (
        'shop_min_recharge', 'shop_max_recharge',
        'shop_withdraw_min', 'shop_withdraw_max'
      )
  -- 只处理「普通十进制」形式（如 10 / 10.00 / 0.5）：
  -- 排除已是 micro 的纯整数（不含小数点即不匹配），也排除异常值
  -- （多个小数点、科学计数法、带单位等一律不匹配，留给人工处理）
  AND `config_value` REGEXP '^[0-9]+(\\.[0-9]+)?$'
  AND `config_value` LIKE '%.%';

-- 本版本发布：迁移时 bump 一次以触发 worker 热重载
UPDATE `__PREFIX__config`
SET `config_value` = CAST(UNIX_TIMESTAMP() AS CHAR)
WHERE `config_name` IN ('server_file_version_pending', 'new_swoole_file_version');
