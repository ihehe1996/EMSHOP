<?php

declare(strict_types=1);

namespace App\Merchant\Controller;

/**
 * 商户后台 - 概览（/user/merchant/home）。
 */
final class HomeController extends MerchantBaseController
{
    public function index(): void
    {
        $merchant = $this->merchant();
        $user = $this->user();
        $merchantId = (int) $merchant['id'];
        $orderTable = $this->db->prefix() . 'order';

        // 所有订单总数（不含退款）
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM `' . $orderTable . '`
              WHERE `merchant_id` = ? AND `status` <> \'refunded\'',
            [$merchantId]
        );
        $orderTotal = (int) ($row['c'] ?? 0);

        // 本月订单
        $monthStart = date('Y-m-01 00:00:00');
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM `' . $orderTable . '`
              WHERE `merchant_id` = ? AND `created_at` >= ? AND `status` <> \'refunded\'',
            [$merchantId, $monthStart]
        );
        $orderThisMonth = (int) ($row['c'] ?? 0);

        // 待处理（已付款 + 未发货）
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM `' . $orderTable . '`
              WHERE `merchant_id` = ? AND `status` IN (\'paid\')',
            [$merchantId]
        );
        $orderPending = (int) ($row['c'] ?? 0);

        // 本月已完成销售额（pay_amount 合计 ×1000000）
        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(`pay_amount`), 0) AS s FROM `' . $orderTable . '`
              WHERE `merchant_id` = ? AND `status` = \'completed\' AND `complete_time` >= ?',
            [$merchantId, $monthStart]
        );
        $salesThisMonth = bcdiv((string) ((int) ($row['s'] ?? 0)), '1000000', 2);

        // 本店在售的主站商品数：默认全部上架，减去商户显式下架的数量
        $refTable = $this->db->prefix() . 'goods_merchant_ref';
        $goodsTable = $this->db->prefix() . 'goods';
        $rowTotal = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM `' . $goodsTable . '`
             WHERE `owner_id` = 0 AND `status` = 1 AND `deleted_at` IS NULL'
        );
        $rowOff = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM `' . $refTable . '` WHERE `merchant_id` = ? AND `is_on_sale` = 0',
            [$merchantId]
        );
        $referencedGoods = (int) ($rowTotal['c'] ?? 0) - (int) ($rowOff['c'] ?? 0);

        // 自建商品数（我的 owner_id）
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM `' . $goodsTable . '` WHERE `owner_id` = ? AND `status` = 1',
            [(int) $user['id']]
        );
        $selfGoods = (int) ($row['c'] ?? 0);

        // 域名绑定状态：用于首页通知条
        $domainAlerts = [];
        $mainDomain = (string) ($this->config->get('main_domain') ?? '');
        $subdomain = (string) ($merchant['subdomain'] ?? '');
        if ($subdomain !== '' && $mainDomain === '') {
            $domainAlerts[] = ['type' => 'warning', 'msg' => '您已绑定二级域名 ' . $subdomain . '，但主站尚未配置根域名 —— 该域名暂不生效，请联系主站管理员配置'];
        }

        $vars = [
            'orderTotal'      => $orderTotal,
            'orderThisMonth'  => $orderThisMonth,
            'orderPending'    => $orderPending,
            'salesThisMonth'  => $salesThisMonth,
            'referencedGoods' => $referencedGoods,
            'selfGoods'       => $selfGoods,
            'domainAlerts'    => $domainAlerts,
        ];

        if ($this->request->isPjax()) {
            echo '<div id="merchantContent" class="mc-content">';
            $this->render('home', $vars + [
                'frontUser'       => $user,
                'currentMerchant' => $merchant,
                'merchantLevel'   => $this->level(),
                'uc'              => $this->viewContext(),
            ]);
            echo '</div>';
            return;
        }

        \App\Merchant\MerchantShell::render(
            dirname(__DIR__) . '/View/home.php',
            $user,
            $merchant,
            $this->level(),
            $vars
        );
    }

    /**
     * Pjax 片段里视图要用的通用变量（与框架壳同一口径）。
     *
     * @return array<string, mixed>
     */
    private function viewContext(): array
    {
        $user = $this->user();
        $raw = (int) ($user['shop_balance'] ?? 0);

        return [
            'siteName'       => (string) ($this->config->get('sitename') ?? 'EMSHOP'),
            'currencySymbol' => $this->currency->visitorSymbol(),
            'displayMoney'   => $this->currency->displayAmount((int) ($user['money'] ?? 0)),
            'shopBalance'    => $this->currency->displayAmount($raw),
            'shopBalanceRaw' => $raw,
        ];
    }
}
