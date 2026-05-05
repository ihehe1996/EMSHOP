<?php

declare(strict_types=1);

/**
 * 应用订单模型(em_app_order)。
 *
 * 记录分站站长在商户端应用商店的购买流水（当前仅余额支付）。
 */
final class AppOrderModel
{
    private string $table;

    public function __construct()
    {
        $this->table = Database::prefix() . 'app_order';
    }

    /**
     * 创建一条应用订单。
     *
     * @param array{
     *   order_no:string, merchant_id:int, user_id:int, market_id:int, app_code:string, type:string,
     *   app_title?:string, amount?:int, pay_method?:string, status?:string,
     *   balance_log_id?:int, before_balance?:int, after_balance?:int, note?:string
     * } $data
     * @return int 新行 id
     */
    public function create(array $data): int
    {
        $orderNo      = trim((string) ($data['order_no'] ?? ''));
        $merchantId   = (int) ($data['merchant_id'] ?? 0);
        $userId       = (int) ($data['user_id'] ?? 0);
        $marketId     = (int) ($data['market_id'] ?? 0);
        $appCode      = trim((string) ($data['app_code'] ?? ''));
        $type         = (string) ($data['type'] ?? '');
        $appTitle     = (string) ($data['app_title'] ?? $appCode);
        $amount       = max(0, (int) ($data['amount'] ?? 0));
        $payMethod    = (string) ($data['pay_method'] ?? 'balance');
        $status       = (string) ($data['status'] ?? 'paid');
        $balanceLogId = max(0, (int) ($data['balance_log_id'] ?? 0));
        $before       = max(0, (int) ($data['before_balance'] ?? 0));
        $after        = max(0, (int) ($data['after_balance'] ?? 0));
        $note         = (string) ($data['note'] ?? '');

        if ($orderNo === '' || $merchantId <= 0 || $userId <= 0 || $marketId <= 0 || $appCode === '') {
            throw new InvalidArgumentException('应用订单参数非法');
        }
        if (!in_array($type, ['plugin', 'template'], true)) {
            throw new InvalidArgumentException('应用类型非法');
        }

        Database::execute(
            'INSERT INTO `' . $this->table . '`
                (`order_no`, `merchant_id`, `user_id`, `market_id`, `app_code`, `type`,
                 `app_title`, `amount`, `pay_method`, `status`,
                 `balance_log_id`, `before_balance`, `after_balance`, `note`,
                 `paid_at`, `created_at`, `updated_at`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())',
            [
                $orderNo, $merchantId, $userId, $marketId, $appCode, $type,
                $appTitle, $amount, $payMethod, $status,
                $balanceLogId, $before, $after, $note,
            ]
        );

        return (int) (Database::fetchOne('SELECT LAST_INSERT_ID() AS id', [])['id'] ?? 0);
    }

    /**
     * 后台分页列表。
     *
     * @param array{page?:int,page_size?:int,keyword?:string,merchant_id?:int,type?:string,status?:string} $filter
     * @return array{data:array<int,array<string,mixed>>,total:int}
     */
    public function paginateForAdmin(array $filter): array
    {
        $page = max(1, (int) ($filter['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($filter['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        [$whereSql, $params] = $this->buildWhere($filter);

        $merchantTable = Database::prefix() . 'merchant';
        $userTable = Database::prefix() . 'user';

        $count = (int) (Database::fetchOne(
            'SELECT COUNT(*) AS c
               FROM `' . $this->table . '` ao
               LEFT JOIN `' . $merchantTable . '` m ON m.id = ao.merchant_id
               LEFT JOIN `' . $userTable . '` u ON u.id = ao.user_id
              ' . $whereSql,
            $params
        )['c'] ?? 0);

        $rows = Database::query(
            'SELECT ao.*,
                    m.name AS merchant_name,
                    u.username, u.nickname
               FROM `' . $this->table . '` ao
               LEFT JOIN `' . $merchantTable . '` m ON m.id = ao.merchant_id
               LEFT JOIN `' . $userTable . '` u ON u.id = ao.user_id
              ' . $whereSql . '
              ORDER BY ao.id DESC
              LIMIT ' . $pageSize . ' OFFSET ' . $offset,
            $params
        );

        return ['data' => $rows, 'total' => $count];
    }

    /**
     * 构造后台列表 where 条件。
     *
     * @param array<string,mixed> $filter
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildWhere(array $filter): array
    {
        $conds = ['1=1'];
        $params = [];

        $merchantId = (int) ($filter['merchant_id'] ?? 0);
        if ($merchantId > 0) {
            $conds[] = 'ao.`merchant_id` = ?';
            $params[] = $merchantId;
        }

        $type = (string) ($filter['type'] ?? '');
        if (in_array($type, ['plugin', 'template'], true)) {
            $conds[] = 'ao.`type` = ?';
            $params[] = $type;
        }

        $status = trim((string) ($filter['status'] ?? ''));
        if ($status !== '') {
            $conds[] = 'ao.`status` = ?';
            $params[] = $status;
        }

        $kw = trim((string) ($filter['keyword'] ?? ''));
        if ($kw !== '') {
            $conds[] = '(ao.`order_no` LIKE ? OR ao.`app_code` LIKE ? OR ao.`app_title` LIKE ? OR m.`name` LIKE ? OR u.`username` LIKE ? OR u.`nickname` LIKE ?)';
            $like = '%' . $kw . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        return ['WHERE ' . implode(' AND ', $conds), $params];
    }
}

