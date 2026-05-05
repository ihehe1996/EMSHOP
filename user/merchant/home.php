<?php

declare(strict_types=1);

require_once __DIR__ . '/global.php';

merchantRequireLogin();

/**
 * 概览数据：订单数 / 本月订单 / 待处理 / 店铺余额 / 已推送商品数
 * 查询都按 merchant_id 过滤，确保只看自己的数据。
 */
$merchantId = (int) $currentMerchant['id'];
$orderTable = Database::prefix() . 'order';

// 所有订单总数（不含退款）
$row = Database::fetchOne(
    'SELECT COUNT(*) AS c FROM `' . $orderTable . '`
      WHERE `merchant_id` = ? AND `status` <> \'refunded\'',
    [$merchantId]
);
$orderTotal = (int) ($row['c'] ?? 0);

// 本月订单
$monthStart = date('Y-m-01 00:00:00');
$row = Database::fetchOne(
    'SELECT COUNT(*) AS c FROM `' . $orderTable . '`
      WHERE `merchant_id` = ? AND `created_at` >= ? AND `status` <> \'refunded\'',
    [$merchantId, $monthStart]
);
$orderThisMonth = (int) ($row['c'] ?? 0);

// 待处理（已付款 + 未发货）
$row = Database::fetchOne(
    'SELECT COUNT(*) AS c FROM `' . $orderTable . '`
      WHERE `merchant_id` = ? AND `status` IN (\'paid\')',
    [$merchantId]
);
$orderPending = (int) ($row['c'] ?? 0);

// 本月已完成销售额（pay_amount 合计 ×1000000）
$row = Database::fetchOne(
    'SELECT COALESCE(SUM(`pay_amount`), 0) AS s FROM `' . $orderTable . '`
      WHERE `merchant_id` = ? AND `status` = \'completed\' AND `complete_time` >= ?',
    [$merchantId, $monthStart]
);
$salesThisMonth = bcdiv((string) ((int) ($row['s'] ?? 0)), '1000000', 2);

// 本店在售的主站商品数：默认全部上架，减去商户显式下架的数量
$refTable = Database::prefix() . 'goods_merchant_ref';
$goodsTable = Database::prefix() . 'goods';
$rowTotal = Database::fetchOne(
    'SELECT COUNT(*) AS c FROM `' . $goodsTable . '`
     WHERE `owner_id` = 0 AND `status` = 1 AND `deleted_at` IS NULL'
);
$rowOff = Database::fetchOne(
    'SELECT COUNT(*) AS c FROM `' . $refTable . '` WHERE `merchant_id` = ? AND `is_on_sale` = 0',
    [$merchantId]
);
$referencedGoods = (int) ($rowTotal['c'] ?? 0) - (int) ($rowOff['c'] ?? 0);

// 自建商品数（我的 owner_id）
$goodsTable = Database::prefix() . 'goods';
$row = Database::fetchOne(
    'SELECT COUNT(*) AS c FROM `' . $goodsTable . '` WHERE `owner_id` = ? AND `status` = 1',
    [(int) $frontUser['id']]
);
$selfGoods = (int) ($row['c'] ?? 0);

// 域名绑定状态：用于首页通知条
$domainAlerts = [];
$mainDomain = (string) (Config::get('main_domain') ?? '');
$subdomain = (string) ($currentMerchant['subdomain'] ?? '');
$customDomain = (string) ($currentMerchant['custom_domain'] ?? '');
if ($subdomain !== '' && $mainDomain === '') {
    $domainAlerts[] = ['type' => 'warning', 'msg' => '您已绑定二级域名 ' . $subdomain . '，但主站尚未配置根域名 —— 该域名暂不生效，请联系主站管理员配置'];
}
if ($customDomain !== '' && (int) $currentMerchant['domain_verified'] !== 1) {
    $domainAlerts[] = ['type' => 'info', 'msg' => '自定义域名 ' . $customDomain . ' 已提交，等待主站管理员审核后生效'];
}

merchantRenderPage(__DIR__ . '/view/home.php', [
    'orderTotal' => $orderTotal,
    'orderThisMonth' => $orderThisMonth,
    'orderPending' => $orderPending,
    'salesThisMonth' => $salesThisMonth,
    'referencedGoods' => $referencedGoods,
    'selfGoods' => $selfGoods,
    'domainAlerts' => $domainAlerts,
]);
