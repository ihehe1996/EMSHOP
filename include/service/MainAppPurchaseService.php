<?php

declare(strict_types=1);

/**
 * 应用商店 · 主站采购服务。
 *
 * 业务流程:
 *   admin/appstore.php(tab=merchant)
 *     ── 调 LicenseClient 完成支付 + 拿下载地址(留给控制器)
 *     ── 下载 zip + 解压到 content/plugin|template/{name}/(留给控制器,与 tab=main 复用同一段)
 *     └─ registerPurchase()  ← 本 Service:把"已支付/已下载完成"的事实落到 em_app_market + em_app_market_log
 *
 * 之所以把"下载/解压"留给控制器、Service 只管"落库 + 配额",是因为:
 *   - 主站自买(tab=main)同样要走"下载/解压",那段代码可以复用
 *   - 落库逻辑需要事务,放 Service 干净
 */
final class MainAppPurchaseService
{
    private AppMarketModel $marketModel;

    public function __construct()
    {
        $this->marketModel = new AppMarketModel();
    }

    /**
     * 主站完成一次"分站货架"采购后调用 —— upsert market 行 + 增加配额 + 写流水。
     *
     * 整个动作在一个事务里。任意步骤失败都会回滚,避免"扣了配额但没记流水"等脏数据。
     *
     * @param array{
     *   app_code:string, type:string, cost_per_unit:int, qty?:int,
     *   remote_app_id?:?int, title?:string, version?:string, category?:string,
     *   cover?:string, description?:string, retail_price?:int, is_listed?:int,
     *   remote_payload?:array<string,mixed>|null,
     *   remote_order_no?:string, remark?:string
     * } $data
     * @return array{market_id:int, log_id:int} 落地的 market id 和 流水 id
     */
    public function registerPurchase(array $data): array
    {
        $appCode     = (string) ($data['app_code'] ?? '');
        $type        = (string) ($data['type'] ?? '');
        $qty         = array_key_exists('qty', $data) ? (int) $data['qty'] : 1;
        $costPerUnit = (int)    ($data['cost_per_unit'] ?? 0);
        $remoteOrderNo = (string) ($data['remote_order_no'] ?? '');
        $remark      = (string) ($data['remark'] ?? '');

        if ($appCode === '' || !in_array($type, ['plugin', 'template'], true)) {
            throw new InvalidArgumentException('app_code/type 非法');
        }
        if ($qty <= 0) {
            throw new InvalidArgumentException('qty 必须为正整数');
        }
        if ($costPerUnit < 0) {
            throw new InvalidArgumentException('cost_per_unit 不能为负');
        }

        Database::begin();
        try {
            // 1. upsert market 行(只动元数据 / 价格 / 上下架,不动配额)
            $marketId = $this->marketModel->upsert([
                'app_code'       => $appCode,
                'type'           => $type,
                'remote_app_id'  => $data['remote_app_id'] ?? null,
                'title'          => (string) ($data['title'] ?? $appCode),
                'version'        => (string) ($data['version'] ?? ''),
                'category'       => (string) ($data['category'] ?? ''),
                'cover'          => (string) ($data['cover'] ?? ''),
                'description'    => (string) ($data['description'] ?? ''),
                // 主站若未指定 retail_price → 默认按当前 cost_per_unit 兜底(可后续在 UI 改)
                'retail_price'   => isset($data['retail_price']) ? (int) $data['retail_price'] : $costPerUnit,
                'is_listed'      => isset($data['is_listed']) ? (int) $data['is_listed'] : 1,
                'remote_payload' => $data['remote_payload'] ?? null,
                'cost_price'     => $costPerUnit, // 缓存最近单价(addQuota 也会更新一次,这里先写好兼容首次插入)
            ]);

            // 2. 加配额 + 写流水(addQuota 内部会再次更新 market.cost_price 和 last_purchased_at)
            $logId = $this->marketModel->addQuota(
                $marketId,
                $qty,
                $costPerUnit,
                $remoteOrderNo,
                $remark !== '' ? $remark : '主站采购'
            );

            Database::commit();
            return ['market_id' => $marketId, 'log_id' => $logId];
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * 修改分站售价(只动 market.retail_price,不影响已购分站的 paid_amount)。
     */
    public function updateRetailPrice(int $marketId, int $retailPrice): bool
    {
        if ($retailPrice < 0) {
            throw new InvalidArgumentException('售价不能为负');
        }
        return $this->marketModel->setRetailPrice($marketId, $retailPrice);
    }

    /**
     * 上下架(下架后已购分站不受影响,但库存不再卖出 / 分站市场不可见)。
     */
    public function setListed(int $marketId, bool $listed): bool
    {
        return $this->marketModel->setListed($marketId, $listed);
    }
}
