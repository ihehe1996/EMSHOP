<?php

declare(strict_types=1);

/**
 * 钱包提现申请模型。
 *
 * 状态机：
 *   pending  → approved / rejected   (管理员审核)
 *   approved → paid                   (管理员线下打款后标记)
 *   rejected 终态，提现金额退回 user.money
 *   paid     终态
 *
 * 创建时立即从 user.money 扣款（等于冻结），rejected 时退回。
 */
class UserWithdrawModel
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID     = 'paid';
    public const STATUS_REJECTED = 'rejected';

    public const ALLOWED_CHANNELS = ['alipay', 'wxpay', 'bank'];

    private string $table;

    public function __construct()
    {
        $this->table = Database::prefix() . 'user_withdraw';
    }

    /**
     * 创建一条提现申请；立即从 user.money 扣款（冻结）。
     * 余额不足会抛 RuntimeException。
     */
    public function create(int $userId, int $amountRaw, string $channel, string $accountName, string $accountNo, string $bankName = ''): int
    {
        if (!in_array($channel, self::ALLOWED_CHANNELS, true)) {
            throw new RuntimeException('不支持的收款方式');
        }
        if ($amountRaw <= 0) throw new RuntimeException('提现金额错误');
        if ($accountName === '' || $accountNo === '') throw new RuntimeException('请填写收款账号和姓名');
        if ($channel === 'bank' && $bankName === '') throw new RuntimeException('请填写开户行');

        Database::begin();
        try {
            // 扣用户余额（同时写 balance_log，方便对账）
            $ok = (new UserBalanceLogModel())->decrease($userId, $amountRaw, '提现申请（冻结）');
            if (!$ok) throw new RuntimeException('余额不足');

            $id = (int) Database::insert('user_withdraw', [
                'user_id'      => $userId,
                'amount'       => $amountRaw,
                'channel'      => $channel,
                'account_name' => $accountName,
                'account_no'   => $accountNo,
                'bank_name'    => $bankName,
                'status'       => self::STATUS_PENDING,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
            Database::commit();
            return $id;
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public function findById(int $id): ?array
    {
        return Database::find('user_withdraw', $id);
    }

    /**
     * 管理员审核通过（从 pending 转 approved）。
     */
    public function approve(int $id, int $adminId, string $remark = ''): bool
    {
        return $this->transition($id, self::STATUS_PENDING, self::STATUS_APPROVED, $adminId, $remark);
    }

    /**
     * 管理员驳回（从 pending 或 approved 转 rejected）；退回冻结的余额。
     */
    public function reject(int $id, int $adminId, string $remark): bool
    {
        if ($remark === '') throw new RuntimeException('请填写驳回理由');
        $row = $this->findById($id);
        if (!$row) return false;
        if (!in_array($row['status'], [self::STATUS_PENDING, self::STATUS_APPROVED], true)) {
            throw new RuntimeException('当前状态不可驳回');
        }

        Database::begin();
        try {
            $affected = Database::execute(
                "UPDATE {$this->table} SET status = ?, admin_id = ?, admin_remark = ?, processed_at = NOW()
                 WHERE id = ? AND status IN (?, ?)",
                [self::STATUS_REJECTED, $adminId, $remark, $id, self::STATUS_PENDING, self::STATUS_APPROVED]
            );
            if ($affected === 0) { Database::rollBack(); return false; }

            // 退回余额
            $ok = (new UserBalanceLogModel())->increase(
                (int) $row['user_id'],
                (int) $row['amount'],
                '提现驳回退回（#' . $id . '）',
                $adminId
            );
            if (!$ok) throw new RuntimeException('退回余额失败');

            Database::commit();
            return true;
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * 标记已打款（approved → paid）。不动余额（创建时已扣过）。
     */
    public function markPaid(int $id, int $adminId, string $remark = ''): bool
    {
        return $this->transition($id, self::STATUS_APPROVED, self::STATUS_PAID, $adminId, $remark);
    }

    private function transition(int $id, string $from, string $to, int $adminId, string $remark): bool
    {
        $affected = Database::execute(
            "UPDATE {$this->table} SET status = ?, admin_id = ?, admin_remark = ?, processed_at = NOW()
             WHERE id = ? AND status = ?",
            [$to, $adminId, $remark, $id, $from]
        );
        return $affected > 0;
    }

    public function paginateByUser(int $userId, int $page = 1, int $perPage = 15): array
    {
        $countRow = Database::fetchOne("SELECT COUNT(*) AS cnt FROM {$this->table} WHERE user_id = ?", [$userId]);
        $total = (int) ($countRow['cnt'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        if ($page < 1) $page = 1;
        $offset = ($page - 1) * $perPage;

        $list = Database::query(
            "SELECT * FROM {$this->table} WHERE user_id = ? ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}",
            [$userId]
        );
        return ['list' => $list, 'total' => $total, 'total_pages' => $totalPages];
    }

    public function paginate(array $filter, int $page = 1, int $perPage = 20): array
    {
        $prefix = Database::prefix();
        $where = ['1=1'];
        $params = [];

        if (!empty($filter['status'])) {
            $where[] = 'w.status = ?';
            $params[] = (string) $filter['status'];
        }
        if (!empty($filter['keyword'])) {
            $kw = '%' . $filter['keyword'] . '%';
            $where[] = '(w.account_name LIKE ? OR w.account_no LIKE ? OR u.username LIKE ? OR u.nickname LIKE ?)';
            $params[] = $kw; $params[] = $kw; $params[] = $kw; $params[] = $kw;
        }
        $whereSql = implode(' AND ', $where);

        $countRow = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM {$this->table} w
             LEFT JOIN {$prefix}user u ON u.id = w.user_id
             WHERE {$whereSql}",
            $params
        );
        $total = (int) ($countRow['cnt'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        if ($page < 1) $page = 1;
        $offset = ($page - 1) * $perPage;

        $list = Database::query(
            "SELECT w.*, u.username, u.nickname
             FROM {$this->table} w
             LEFT JOIN {$prefix}user u ON u.id = w.user_id
             WHERE {$whereSql}
             ORDER BY w.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['list' => $list, 'total' => $total, 'total_pages' => $totalPages, 'page' => $page];
    }
}
