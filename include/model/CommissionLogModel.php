<?php

declare(strict_types=1);

/**
 * 佣金流水模型（em_commission_log）。
 *
 * 状态：
 *   frozen    订单完成后入账，处于冷却期内
 *   available 冷却期结束后可提现
 *   withdrawn 已随一笔提现划出
 *   reverted  订单退款时倒扣
 *
 * frozen 到期后不靠定时任务，由"访问佣金相关页面"时 promoteMatured() 惰性扫描迁移。
 */
class CommissionLogModel
{
    private string $table;

    public const STATUS_FROZEN    = 'frozen';
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_REVERTED  = 'reverted';

    public function __construct()
    {
        $this->table = Database::prefix() . 'commission_log';
    }

    /**
     * 插入一条佣金记录（冻结态）。
     *
     * @return int 新记录 id
     */
    public function createFrozen(array $data): int
    {
        $row = [
            'user_id'      => (int) $data['user_id'],
            'order_id'     => (int) $data['order_id'],
            'order_no'     => (string) $data['order_no'],
            'from_user_id' => (int) ($data['from_user_id'] ?? 0),
            'level'        => (int) $data['level'],
            'amount'       => (int) $data['amount'],
            'rate'         => (int) $data['rate'],
            'basis_amount' => (int) ($data['basis_amount'] ?? 0),
            'status'       => self::STATUS_FROZEN,
            'frozen_until' => (string) $data['frozen_until'],
            'remark'       => (string) ($data['remark'] ?? ''),
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ];
        return (int) Database::insert('commission_log', $row);
    }

    /**
     * 直接以"可提现"状态插入一条佣金记录（冷却天数 = 0 时使用）。
     * frozen_until 置空，避免后续 promoteMatured 误命中。
     */
    public function createAvailable(array $data): int
    {
        $row = [
            'user_id'      => (int) $data['user_id'],
            'order_id'     => (int) $data['order_id'],
            'order_no'     => (string) $data['order_no'],
            'from_user_id' => (int) ($data['from_user_id'] ?? 0),
            'level'        => (int) $data['level'],
            'amount'       => (int) $data['amount'],
            'rate'         => (int) $data['rate'],
            'basis_amount' => (int) ($data['basis_amount'] ?? 0),
            'status'       => self::STATUS_AVAILABLE,
            'frozen_until' => null,
            'remark'       => (string) ($data['remark'] ?? ''),
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ];
        return (int) Database::insert('commission_log', $row);
    }

    /**
     * 把用户的已到期冻结佣金转为可提现；同步更新 user 表的两个账户缓存。
     *
     * @return int 本次转出的金额（BIGINT）
     */
    public function promoteMatured(int $userId): int
    {
        $prefix = Database::prefix();
        $now = date('Y-m-d H:i:s');

        // 先取出要迁移的记录（用于统计总额）
        $rows = Database::query(
            "SELECT id, amount FROM {$this->table}
             WHERE user_id = ? AND status = ? AND frozen_until IS NOT NULL AND frozen_until <= ?",
            [$userId, self::STATUS_FROZEN, $now]
        );
        if (empty($rows)) return 0;

        $total = 0;
        $ids = [];
        foreach ($rows as $r) {
            $total += (int) $r['amount'];
            $ids[] = (int) $r['id'];
        }

        Database::begin();
        try {
            $in = implode(',', array_fill(0, count($ids), '?'));
            Database::execute(
                "UPDATE {$this->table} SET status = ?, frozen_until = NULL, updated_at = NOW()
                 WHERE id IN ({$in}) AND status = ?",
                array_merge([self::STATUS_AVAILABLE], $ids, [self::STATUS_FROZEN])
            );
            // 更新 user 缓存：frozen - total，available + total
            Database::execute(
                "UPDATE {$prefix}user
                 SET commission_frozen = GREATEST(commission_frozen - ?, 0),
                     commission_available = commission_available + ?
                 WHERE id = ?",
                [$total, $total, $userId]
            );
            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
        return $total;
    }

    /**
     * 分页查询用户的佣金流水。
     */
    public function paginateByUser(int $userId, array $filter, int $page = 1, int $perPage = 20): array
    {
        $where = ['user_id = ?'];
        $params = [$userId];
        if (!empty($filter['status'])) {
            $where[] = 'status = ?';
            $params[] = $filter['status'];
        }
        if (!empty($filter['level'])) {
            $where[] = 'level = ?';
            $params[] = (int) $filter['level'];
        }
        $whereSql = implode(' AND ', $where);

        $countRow = Database::fetchOne("SELECT COUNT(*) AS cnt FROM {$this->table} WHERE {$whereSql}", $params);
        $total = (int) ($countRow['cnt'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        if ($page < 1) $page = 1;
        $offset = ($page - 1) * $perPage;

        $rows = Database::query(
            "SELECT * FROM {$this->table} WHERE {$whereSql} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        foreach ($rows as &$r) {
            // 返回带货币符号的完整字符串（按访客当前币种换算）；前端直接输出不再拼 ¥
            $r['amount_display']       = Currency::displayAmount((int) $r['amount']);
            $r['basis_amount_display'] = Currency::displayAmount((int) ($r['basis_amount'] ?? 0));
        }
        unset($r);

        return [
            'list' => $rows, 'total' => $total, 'page' => $page,
            'per_page' => $perPage, 'total_pages' => $totalPages,
        ];
    }

    /**
     * 取用户可提现的佣金明细 ids（按创建时间升序，供提现时按顺序标记 withdrawn）。
     */
    public function listAvailableForWithdraw(int $userId, int $limit = 1000): array
    {
        return Database::query(
            "SELECT id, amount FROM {$this->table}
             WHERE user_id = ? AND status = ?
             ORDER BY id ASC LIMIT {$limit}",
            [$userId, self::STATUS_AVAILABLE]
        );
    }

    /**
     * 订单退款时：倒扣与该订单有关的全部佣金记录。
     * 策略：
     *   - frozen → reverted：直接把记录标记并扣 user.commission_frozen
     *   - available → reverted：扣 user.commission_available
     *   - withdrawn → 已提现的部分无法回滚（记为已消耗），仅标记一个 reverted 日志但不扣余额
     *
     * 返回倒扣的总佣金金额（BIGINT）。
     */
    public function revertByOrder(int $orderId): int
    {
        $prefix = Database::prefix();
        $rows = Database::query(
            "SELECT id, user_id, amount, status FROM {$this->table}
             WHERE order_id = ? AND status IN (?, ?, ?)",
            [$orderId, self::STATUS_FROZEN, self::STATUS_AVAILABLE, self::STATUS_WITHDRAWN]
        );
        if (empty($rows)) return 0;

        $totalReverted = 0;
        Database::begin();
        try {
            foreach ($rows as $r) {
                $id       = (int) $r['id'];
                $uid      = (int) $r['user_id'];
                $amt      = (int) $r['amount'];
                $status   = (string) $r['status'];

                // 先把记录标为 reverted
                Database::execute(
                    "UPDATE {$this->table} SET status = ?, updated_at = NOW() WHERE id = ?",
                    [self::STATUS_REVERTED, $id]
                );

                if ($status === self::STATUS_FROZEN) {
                    Database::execute(
                        "UPDATE {$prefix}user SET commission_frozen = GREATEST(commission_frozen - ?, 0) WHERE id = ?",
                        [$amt, $uid]
                    );
                    $totalReverted += $amt;
                } elseif ($status === self::STATUS_AVAILABLE) {
                    Database::execute(
                        "UPDATE {$prefix}user SET commission_available = GREATEST(commission_available - ?, 0) WHERE id = ?",
                        [$amt, $uid]
                    );
                    $totalReverted += $amt;
                }
                // withdrawn 不扣账户（钱已进余额，无法撤回）
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
        return $totalReverted;
    }
}
