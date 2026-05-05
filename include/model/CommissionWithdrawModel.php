<?php

declare(strict_types=1);

/**
 * 佣金提现记录模型（em_commission_withdraw）。
 */
class CommissionWithdrawModel
{
    private string $table;

    public function __construct()
    {
        $this->table = Database::prefix() . 'commission_withdraw';
    }

    public function insert(array $data): int
    {
        $row = [
            'user_id'        => (int) $data['user_id'],
            'amount'         => (int) $data['amount'],
            'before_balance' => (int) ($data['before_balance'] ?? 0),
            'after_balance'  => (int) ($data['after_balance'] ?? 0),
            'status'         => (string) ($data['status'] ?? 'done'),
            'remark'         => (string) ($data['remark'] ?? ''),
            'created_at'     => date('Y-m-d H:i:s'),
        ];
        return (int) Database::insert('commission_withdraw', $row);
    }

    /**
     * 分页查询（后台/用户用）。
     */
    public function paginate(array $filter, int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filter['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = (int) $filter['user_id'];
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
            $r['amount_display'] = bcdiv((string) $r['amount'], '1000000', 2);
        }
        unset($r);

        return [
            'list' => $rows, 'total' => $total, 'page' => $page,
            'per_page' => $perPage, 'total_pages' => $totalPages,
        ];
    }
}
