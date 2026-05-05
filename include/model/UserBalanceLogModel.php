<?php

declare(strict_types=1);

/**
 * 用户余额变动记录模型。
 */
class UserBalanceLogModel
{
    private string $table;
    private string $userTable;

    public function __construct()
    {
        $this->table = Database::prefix() . 'user_balance_log';
        $this->userTable = Database::prefix() . 'user';
    }

    /**
     * 增加余额并记录日志。
     *
     * @param int    $userId      用户ID
     * @param int    $amount      变动金额（已乘以1000000的整数）
     * @param string $remark      备注
     * @param int    $operatorId  操作人ID
     * @param string $operatorName 操作人名称
     * @return bool
     */
    public function increase(int $userId, int $amount, string $remark, int $operatorId = 0, string $operatorName = ''): bool
    {
        if ($amount <= 0) {
            return false;
        }

        Database::begin();
        try {
            // 查询当前余额（加锁）
            $row = Database::fetchOne(
                "SELECT money FROM {$this->userTable} WHERE id = ? FOR UPDATE",
                [$userId]
            );
            if ($row === null) {
                Database::rollBack();
                return false;
            }

            $before = (int) $row['money'];
            $after = $before + $amount;

            // 更新余额
            Database::execute(
                "UPDATE {$this->userTable} SET money = ? WHERE id = ?",
                [$after, $userId]
            );

            // 写入日志
            $this->insertLog($userId, 'increase', $amount, $before, $after, $remark, $operatorId, $operatorName);

            Database::commit();
            return true;
        } catch (Throwable $e) {
            Database::rollBack();
            return false;
        }
    }

    /**
     * 减少余额并记录日志。
     *
     * @return bool
     */
    public function decrease(int $userId, int $amount, string $remark, int $operatorId = 0, string $operatorName = ''): bool
    {
        if ($amount <= 0) {
            return false;
        }

        Database::begin();
        try {
            $row = Database::fetchOne(
                "SELECT money FROM {$this->userTable} WHERE id = ? FOR UPDATE",
                [$userId]
            );
            if ($row === null) {
                Database::rollBack();
                return false;
            }

            $before = (int) $row['money'];
            $after = $before - $amount;
            if ($after < 0) {
                Database::rollBack();
                return false;
            }

            Database::execute(
                "UPDATE {$this->userTable} SET money = ? WHERE id = ?",
                [$after, $userId]
            );

            $this->insertLog($userId, 'decrease', $amount, $before, $after, $remark, $operatorId, $operatorName);

            Database::commit();
            return true;
        } catch (Throwable $e) {
            Database::rollBack();
            return false;
        }
    }

    /**
     * 查询用户的余额变动记录（分页）。
     *
     * @return array{list: array, total: int, page: int, total_pages: int}
     */
    public function getListByUser(int $userId, int $page = 1, int $perPage = 15): array
    {
        $countRow = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM {$this->table} WHERE user_id = ?",
            [$userId]
        );
        $total = (int) ($countRow['cnt'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $rows = Database::query(
            "SELECT * FROM {$this->table} WHERE user_id = ? ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}",
            [$userId]
        );

        return [
            'list'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * 写入余额变动日志。
     */
    private function insertLog(int $userId, string $type, int $amount, int $before, int $after, string $remark, int $operatorId, string $operatorName): void
    {
        $sql = "INSERT INTO {$this->table} (user_id, type, amount, before_balance, after_balance, remark, operator_id, operator_name, ip)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        Database::execute($sql, [
            $userId, $type, $amount, $before, $after,
            $remark, $operatorId, $operatorName,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ]);
    }
}
