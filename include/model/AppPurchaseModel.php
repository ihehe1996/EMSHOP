<?php

declare(strict_types=1);

/**
 * 应用商店 · 分站已购记录模型(em_app_purchase)。
 *
 * 一行 = 一个分站对一个应用的已购授权;market_id NULL = 重构前免费授权(老数据回填)。
 *
 * 事务边界:
 *   create() 在分站购买流程里需要和"扣 em_user.money + market.consumed_quota +1"合并成一个事务,
 *   由调用 Service(目前暂未上线)控制,本类不开事务。
 *
 * 当前用途(分站购买暂未上线):
 *   - listByMerchant / purchasedCodes / isPurchased — 商户后台读已购清单
 *   - create — 留给后续购买功能实现时调
 */
final class AppPurchaseModel
{
    private string $table;

    public function __construct()
    {
        $this->table = Database::prefix() . 'app_purchase';
    }

    /**
     * 写入一行 purchase 记录。
     *
     * @param array{
     *   merchant_id:int, user_id:int, app_code:string, type:string,
     *   market_id?:?int, paid_amount?:int, balance_log_id?:int
     * } $data
     * @return int 新行 id
     */
    public function create(array $data): int
    {
        $merchantId = (int) ($data['merchant_id'] ?? 0);
        $userId     = (int) ($data['user_id'] ?? 0);
        $appCode    = (string) ($data['app_code'] ?? '');
        $type       = (string) ($data['type'] ?? '');
        if ($merchantId <= 0 || $userId <= 0 || $appCode === ''
            || !in_array($type, ['plugin', 'template'], true)) {
            throw new InvalidArgumentException('purchase 字段非法');
        }

        $marketId      = isset($data['market_id']) && $data['market_id'] !== null
            ? (int) $data['market_id'] : null;
        $paidAmount    = (int) ($data['paid_amount'] ?? 0);
        $balanceLogId  = (int) ($data['balance_log_id'] ?? 0);

        Database::execute(
            'INSERT INTO `' . $this->table . '`
                (`merchant_id`, `user_id`, `app_code`, `type`,
                 `market_id`, `paid_amount`, `balance_log_id`, `purchased_at`)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $merchantId, $userId, $appCode, $type,
                $marketId, $paidAmount, $balanceLogId,
            ]
        );
        return (int) (Database::fetchOne('SELECT LAST_INSERT_ID() AS id', [])['id'] ?? 0);
    }

    /**
     * 按 (merchant_id, app_code, type) 查一行 —— 分站买之前判重 / 已购详情查询。
     *
     * @return array<string, mixed>|null
     */
    public function findOne(int $merchantId, string $appCode, string $type): ?array
    {
        if ($merchantId <= 0 || $appCode === ''
            || !in_array($type, ['plugin', 'template'], true)) {
            return null;
        }
        $sql = sprintf(
            'SELECT * FROM `%s`
              WHERE `merchant_id` = ? AND `app_code` = ? AND `type` = ?
              LIMIT 1',
            $this->table
        );
        return Database::fetchOne($sql, [$merchantId, $appCode, $type]);
    }

    /**
     * 该分站是否已购买指定应用。
     */
    public function isPurchased(int $merchantId, string $appCode, string $type): bool
    {
        return $this->findOne($merchantId, $appCode, $type) !== null;
    }

    /**
     * 列出某分站的所有已购应用。
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByMerchant(int $merchantId, ?string $type = null): array
    {
        if ($merchantId <= 0) return [];

        $params = [$merchantId];
        $sql = 'SELECT * FROM `' . $this->table . '` WHERE `merchant_id` = ?';
        if ($type !== null && in_array($type, ['plugin', 'template'], true)) {
            $sql .= ' AND `type` = ?';
            $params[] = $type;
        }
        $sql .= ' ORDER BY `purchased_at` DESC';
        return Database::query($sql, $params);
    }

    /**
     * 取某分站某类的已购应用 code 列表(快速判存在用,比 listByMerchant 轻)。
     *
     * @return array<int, string>
     */
    public function purchasedCodes(int $merchantId, string $type): array
    {
        if ($merchantId <= 0 || !in_array($type, ['plugin', 'template'], true)) {
            return [];
        }
        $rows = Database::query(
            'SELECT `app_code` FROM `' . $this->table . '`
              WHERE `merchant_id` = ? AND `type` = ?',
            [$merchantId, $type]
        );
        return array_map(static fn ($r) => (string) $r['app_code'], $rows);
    }

    /**
     * 某 market 行已被多少个分站购买 —— 主站后台展示"已售 = N 家"。
     */
    public function countByMarket(int $marketId): int
    {
        if ($marketId <= 0) return 0;
        $row = Database::fetchOne(
            'SELECT COUNT(*) AS c FROM `' . $this->table . '` WHERE `market_id` = ?',
            [$marketId]
        );
        return (int) ($row['c'] ?? 0);
    }
}
