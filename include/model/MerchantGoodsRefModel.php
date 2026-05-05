<?php

declare(strict_types=1);

/**
 * 主站商品到商户店铺的引用关系。
 *
 * 一条记录 = "某主站商品已推送到某商户"。
 * 主站推送时自动 is_on_sale=1；商户可在自己的店铺后台下架（改为 0）；
 * 主站删除 / 下架商品时，所有引用记录依赖外层查询时的 JOIN 过滤自动失效。
 */
final class MerchantGoodsRefModel
{
    /** @var string */
    private string $table;

    public function __construct()
    {
        $this->table = Database::prefix() . 'goods_merchant_ref';
    }

    /**
     * 列出某主站商品已推送的商户集合。
     *
     * @return array<int, int> merchant_id 数组
     */
    public function pushedMerchantIds(int $goodsId): array
    {
        if ($goodsId <= 0) {
            return [];
        }
        $rows = Database::query(
            'SELECT `merchant_id` FROM `' . $this->table . '` WHERE `goods_id` = ?',
            [$goodsId]
        );
        return array_map(static fn($r) => (int) $r['merchant_id'], $rows);
    }

    /**
     * 按 (merchant_id, goods_id) 查单条。
     *
     * @return array<string, mixed>|null
     */
    public function find(int $merchantId, int $goodsId): ?array
    {
        $sql = 'SELECT * FROM `' . $this->table . '`
                 WHERE `merchant_id` = ? AND `goods_id` = ? LIMIT 1';
        return Database::fetchOne($sql, [$merchantId, $goodsId]);
    }

    /**
     * 推送商品到指定商户列表。
     * - 已存在的记录：保留原 markup_rate，仅重置 is_on_sale=1 与 pushed_at
     * - 不存在的记录：新建
     *
     * @param array<int, int> $merchantIds
     * @return array{added:int, reactivated:int}
     */
    public function pushToMerchants(int $goodsId, array $merchantIds, int $defaultMarkupRate = 0): array
    {
        if ($goodsId <= 0 || $merchantIds === []) {
            return ['added' => 0, 'reactivated' => 0];
        }

        $now = date('Y-m-d H:i:s');
        $added = 0;
        $reactivated = 0;

        foreach ($merchantIds as $mid) {
            $mid = (int) $mid;
            if ($mid <= 0) continue;

            $existing = $this->find($mid, $goodsId);
            if ($existing === null) {
                Database::insert('goods_merchant_ref', [
                    'merchant_id' => $mid,
                    'goods_id' => $goodsId,
                    'markup_rate' => $defaultMarkupRate,
                    'is_on_sale' => 1,
                    'sort' => 100,
                    'pushed_at' => $now,
                ]);
                $added++;
            } else {
                Database::update('goods_merchant_ref',
                    ['is_on_sale' => 1, 'pushed_at' => $now],
                    (int) $existing['id']
                );
                $reactivated++;
            }
        }

        return ['added' => $added, 'reactivated' => $reactivated];
    }

    /**
     * 从指定商户列表中下架（软下架：is_on_sale=0；保留 markup_rate 以便重新推送后沿用）。
     *
     * @param array<int, int> $merchantIds  传空数组表示"从全部商户下架"
     */
    public function unshelve(int $goodsId, array $merchantIds = []): int
    {
        if ($goodsId <= 0) {
            return 0;
        }
        if ($merchantIds === []) {
            return Database::execute(
                'UPDATE `' . $this->table . '` SET `is_on_sale` = 0 WHERE `goods_id` = ?',
                [$goodsId]
            );
        }
        $placeholders = implode(',', array_fill(0, count($merchantIds), '?'));
        $params = array_map('intval', $merchantIds);
        array_unshift($params, $goodsId);
        return Database::execute(
            'UPDATE `' . $this->table . '` SET `is_on_sale` = 0
              WHERE `goods_id` = ? AND `merchant_id` IN (' . $placeholders . ')',
            $params
        );
    }

    /**
     * 统计某主站商品已推送（且上架中）的商户数。
     */
    public function countOnSale(int $goodsId): int
    {
        if ($goodsId <= 0) {
            return 0;
        }
        $row = Database::fetchOne(
            'SELECT COUNT(*) AS `c` FROM `' . $this->table . '`
              WHERE `goods_id` = ? AND `is_on_sale` = 1',
            [$goodsId]
        );
        return (int) ($row['c'] ?? 0);
    }
}
