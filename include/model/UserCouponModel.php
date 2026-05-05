<?php

declare(strict_types=1);

/**
 * 用户持有的优惠券模型。
 *
 * 数据表：em_user_coupon
 * 约束：(user_id, coupon_id) 唯一 —— 同一人同一张券只能领一次
 *
 * status 只落表 unused/used；过期/失效通过 join em_coupon 动态判断。
 */
class UserCouponModel
{
    private string $table;
    private string $couponTable;

    public const STATUS_UNUSED = 'unused';
    public const STATUS_USED   = 'used';

    // 视图 tab 分类（动态判断）
    public const VIEW_UNUSED  = 'unused';
    public const VIEW_USED    = 'used';
    public const VIEW_EXPIRED = 'expired';
    public const VIEW_INVALID = 'invalid';   // 总次数耗尽

    public function __construct()
    {
        $this->table = Database::prefix() . 'user_coupon';
        $this->couponTable = Database::prefix() . 'coupon';
    }

    /**
     * 用户是否已领过该券。
     */
    public function hasClaimed(int $userId, int $couponId): bool
    {
        $row = Database::fetchOne(
            "SELECT id FROM {$this->table} WHERE user_id = ? AND coupon_id = ? LIMIT 1",
            [$userId, $couponId]
        );
        return $row !== null && $row !== false;
    }

    /**
     * 用户领取一张券。已领过则抛 RuntimeException。
     */
    public function claim(int $userId, int $couponId): int
    {
        if ($this->hasClaimed($userId, $couponId)) {
            throw new RuntimeException('您已领取过该优惠券');
        }
        return (int) Database::insert('user_coupon', [
            'user_id'     => $userId,
            'coupon_id'   => $couponId,
            'status'      => self::STATUS_UNUSED,
            'obtained_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 按 tab 查询用户的券（带 coupon 详情 + 动态状态）。
     *
     * @param string $view unused / used / expired / invalid
     */
    public function listByView(int $userId, string $view, int $limit = 100): array
    {
        $now = date('Y-m-d H:i:s');
        $prefix = Database::prefix();

        // LEFT JOIN coupon 保留已删除券的记录（若券被软删仍在用户包里按过期处理）
        $sql = "SELECT uc.id AS user_coupon_id, uc.status, uc.obtained_at, uc.used_at, uc.order_id,
                       c.*
                FROM {$this->table} uc
                LEFT JOIN {$this->couponTable} c ON uc.coupon_id = c.id
                WHERE uc.user_id = ?";
        $params = [$userId];

        switch ($view) {
            case self::VIEW_USED:
                $sql .= " AND uc.status = 'used'";
                break;
            case self::VIEW_EXPIRED:
                $sql .= " AND uc.status = 'unused'
                          AND c.end_at IS NOT NULL AND c.end_at < ?
                          AND (c.total_usage_limit = -1 OR c.used_count < c.total_usage_limit)";
                $params[] = $now;
                break;
            case self::VIEW_INVALID:
                // 未过期但总次数已耗尽
                $sql .= " AND uc.status = 'unused'
                          AND c.total_usage_limit != -1 AND c.used_count >= c.total_usage_limit
                          AND (c.end_at IS NULL OR c.end_at >= ?)";
                $params[] = $now;
                break;
            case self::VIEW_UNUSED:
            default:
                // 未使用 + 未过期 + 未耗尽
                $sql .= " AND uc.status = 'unused'
                          AND (c.end_at IS NULL OR c.end_at >= ?)
                          AND (c.total_usage_limit = -1 OR c.used_count < c.total_usage_limit)";
                $params[] = $now;
                break;
        }

        $sql .= " ORDER BY uc.id DESC LIMIT {$limit}";

        $rows = Database::query($sql, $params);
        $couponModel = new CouponModel();

        // 复用 CouponModel 的转换逻辑（借反射）—— 简单起见，直接调用其 public 查询会多一次 DB
        // 这里手动还原 value/min_amount/max_discount
        foreach ($rows as &$r) {
            $type = $r['type'] ?? '';
            if ($type !== CouponModel::TYPE_PERCENT) {
                $r['value']        = bcdiv((string) ($r['value'] ?? 0), '1000000', 2);
            } else {
                $r['value'] = (int) ($r['value'] ?? 0);
            }
            $r['min_amount']   = bcdiv((string) ($r['min_amount'] ?? 0), '1000000', 2);
            $r['max_discount'] = bcdiv((string) ($r['max_discount'] ?? 0), '1000000', 2);
            $r['apply_ids']    = json_decode((string) ($r['apply_ids'] ?? ''), true) ?: [];
        }
        return $rows;
    }

    /**
     * 统计 4 个 tab 的数量。
     */
    public function countByViews(int $userId): array
    {
        $now = date('Y-m-d H:i:s');
        $prefix = Database::prefix();

        $rows = Database::query(
            "SELECT
                SUM(CASE WHEN uc.status = 'unused'
                         AND (c.end_at IS NULL OR c.end_at >= ?)
                         AND (c.total_usage_limit = -1 OR c.used_count < c.total_usage_limit)
                    THEN 1 ELSE 0 END) AS unused_cnt,
                SUM(CASE WHEN uc.status = 'used' THEN 1 ELSE 0 END) AS used_cnt,
                SUM(CASE WHEN uc.status = 'unused'
                         AND c.end_at IS NOT NULL AND c.end_at < ?
                         AND (c.total_usage_limit = -1 OR c.used_count < c.total_usage_limit)
                    THEN 1 ELSE 0 END) AS expired_cnt,
                SUM(CASE WHEN uc.status = 'unused'
                         AND c.total_usage_limit != -1 AND c.used_count >= c.total_usage_limit
                         AND (c.end_at IS NULL OR c.end_at >= ?)
                    THEN 1 ELSE 0 END) AS invalid_cnt
             FROM {$this->table} uc
             LEFT JOIN {$this->couponTable} c ON uc.coupon_id = c.id
             WHERE uc.user_id = ?",
            [$now, $now, $now, $userId]
        );
        $r = $rows[0] ?? [];
        return [
            'unused'  => (int) ($r['unused_cnt'] ?? 0),
            'used'    => (int) ($r['used_cnt'] ?? 0),
            'expired' => (int) ($r['expired_cnt'] ?? 0),
            'invalid' => (int) ($r['invalid_cnt'] ?? 0),
        ];
    }

    /**
     * 标记为已使用（订单支付时调用）。
     */
    public function markUsed(int $userCouponId, int $orderId): bool
    {
        $n = Database::execute(
            "UPDATE {$this->table}
             SET status = 'used', used_at = NOW(), order_id = ?
             WHERE id = ? AND status = 'unused'",
            [$orderId, $userCouponId]
        );
        return $n > 0;
    }

    /**
     * 按 user_id + coupon_id 找已领取的券。
     */
    public function findByUserAndCoupon(int $userId, int $couponId): ?array
    {
        $row = Database::fetchOne(
            "SELECT * FROM {$this->table} WHERE user_id = ? AND coupon_id = ? LIMIT 1",
            [$userId, $couponId]
        );
        return $row ?: null;
    }
}
