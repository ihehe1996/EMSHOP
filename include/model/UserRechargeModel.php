<?php

declare(strict_types=1);

/**
 * 钱包充值订单模型。
 *
 * 用户发起充值 → 创建一行 pending 记录 → 第三方支付回调 → markPaid 给用户 money 加钱。
 */
class UserRechargeModel
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_PAID      = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    private string $table;

    public function __construct()
    {
        $this->table = Database::prefix() . 'user_recharge';
    }

    /**
     * 创建一条 pending 充值单。
     *
     * @return array{id:int, order_no:string}
     */
    public function create(int $userId, int $amountRaw, string $paymentCode, string $paymentPlugin): array
    {
        $orderNo = $this->generateOrderNo();
        $id = (int) Database::insert('user_recharge', [
            'user_id'        => $userId,
            'order_no'       => $orderNo,
            'amount'         => $amountRaw,
            'payment_code'   => $paymentCode,
            'payment_plugin' => $paymentPlugin,
            'trade_no'       => '',
            'status'         => self::STATUS_PENDING,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
        return ['id' => $id, 'order_no' => $orderNo];
    }

    public function findByOrderNo(string $orderNo): ?array
    {
        return Database::fetchOne("SELECT * FROM {$this->table} WHERE order_no = ?", [$orderNo]) ?: null;
    }

    public function findById(int $id): ?array
    {
        return Database::find('user_recharge', $id);
    }

    /**
     * 标记已支付并给用户加余额（幂等：已 paid 的直接返回 true）。
     */
    public function markPaid(int $id, string $tradeNo): bool
    {
        Database::begin();
        try {
            // 先锁充值单，保证并发回调幂等。
            $row = Database::fetchOne(
                "SELECT * FROM {$this->table} WHERE id = ? FOR UPDATE",
                [$id]
            );
            if ($row === null) {
                Database::rollBack();
                return false;
            }
            if ((string) $row['status'] === self::STATUS_PAID) {
                Database::rollBack();
                return true;
            }
            if ((string) $row['status'] !== self::STATUS_PENDING) {
                Database::rollBack();
                return false;
            }

            // 锁用户余额并加钱。
            $userTable = Database::prefix() . 'user';
            $user = Database::fetchOne(
                "SELECT money FROM `{$userTable}` WHERE id = ? FOR UPDATE",
                [(int) $row['user_id']]
            );
            if ($user === null) {
                throw new RuntimeException('充值用户不存在');
            }

            $before = (int) ($user['money'] ?? 0);
            $amount = (int) ($row['amount'] ?? 0);
            $after = $before + $amount;

            Database::execute(
                "UPDATE `{$userTable}` SET money = ? WHERE id = ?",
                [$after, (int) $row['user_id']]
            );

            // 写余额日志（与充值状态变更保持同一事务）。
            $logTable = Database::prefix() . 'user_balance_log';
            Database::execute(
                "INSERT INTO `{$logTable}`
                 (user_id, type, amount, before_balance, after_balance, remark, operator_id, operator_name, ip)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    (int) $row['user_id'],
                    'increase',
                    $amount,
                    $before,
                    $after,
                    '钱包充值 #' . (string) ($row['order_no'] ?? ''),
                    0,
                    '',
                    (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
                ]
            );

            // 最后把充值单置为已支付。
            Database::execute(
                "UPDATE {$this->table} SET status = ?, trade_no = ?, paid_at = NOW()
                 WHERE id = ? AND status = ?",
                [self::STATUS_PAID, $tradeNo, $id, self::STATUS_PENDING]
            );

            Database::commit();
            return true;
        } catch (Throwable $e) {
            Database::rollBack();
            return false;
        }
    }

    /**
     * 用户侧分页：只查自己的充值记录。
     *
     * @return array{list:array, total:int, total_pages:int}
     */
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

    /**
     * 管理员侧分页：可按状态筛选、关键字（单号/用户名）搜索。
     */
    public function paginate(array $filter, int $page = 1, int $perPage = 20): array
    {
        $prefix = Database::prefix();
        $where = ['1=1'];
        $params = [];

        if (!empty($filter['status'])) {
            $where[] = 'r.status = ?';
            $params[] = (string) $filter['status'];
        }
        if (!empty($filter['keyword'])) {
            $kw = '%' . $filter['keyword'] . '%';
            $where[] = '(r.order_no LIKE ? OR u.username LIKE ? OR u.nickname LIKE ?)';
            $params[] = $kw; $params[] = $kw; $params[] = $kw;
        }
        $whereSql = implode(' AND ', $where);

        $countRow = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM {$this->table} r
             LEFT JOIN {$prefix}user u ON u.id = r.user_id
             WHERE {$whereSql}",
            $params
        );
        $total = (int) ($countRow['cnt'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        if ($page < 1) $page = 1;
        $offset = ($page - 1) * $perPage;

        $list = Database::query(
            "SELECT r.*, u.username, u.nickname
             FROM {$this->table} r
             LEFT JOIN {$prefix}user u ON u.id = r.user_id
             WHERE {$whereSql}
             ORDER BY r.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['list' => $list, 'total' => $total, 'total_pages' => $totalPages, 'page' => $page];
    }

    /**
     * 生成唯一充值单号：R + yyyymmddHHiiss + 4 位随机。
     */
    private function generateOrderNo(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $no = 'R' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $exist = Database::fetchOne("SELECT id FROM {$this->table} WHERE order_no = ?", [$no]);
            if (!$exist) return $no;
        }
        throw new RuntimeException('生成充值单号失败');
    }
}
