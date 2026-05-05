<?php

declare(strict_types=1);

/**
 * 应用商店 · 上架清单模型(em_app_market + em_app_market_log)。
 *
 * 业务背景:
 *   - 主站后台从服务端"分站货架"接口采购应用 → upsert 一行 em_app_market(总配额 += N)
 *   - 每次采购都写一行 em_app_market_log(对账 / 单价历史)
 *   - 分站后台"插件市场"读 em_app_market(is_listed=1)展示给商户购买
 *   - 库存 = total_quota - consumed_quota,耗尽时分站显示"缺货"
 *
 * 并发约定:
 *   分站购买扣库存的 SELECT … FOR UPDATE / 事务边界由调用 Service 控制(分站购买功能暂未上线),
 *   本类只提供原子的 incrementConsumed / addQuota 单 SQL 操作。
 */
final class AppMarketModel
{
    private string $table;
    private string $logTable;

    public function __construct()
    {
        $this->table    = Database::prefix() . 'app_market';
        $this->logTable = Database::prefix() . 'app_market_log';
    }

    // -----------------------------------------------------------------
    // 读
    // -----------------------------------------------------------------

    /**
     * 按 id 查一行。
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $sql = sprintf('SELECT * FROM `%s` WHERE `id` = ? LIMIT 1', $this->table);
        return Database::fetchOne($sql, [$id]);
    }

    /**
     * 按 (app_code, type) 唯一键查一行。
     *
     * @return array<string, mixed>|null
     */
    public function findByAppCode(string $appCode, string $type): ?array
    {
        if ($appCode === '' || !in_array($type, ['plugin', 'template'], true)) {
            return null;
        }
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `app_code` = ? AND `type` = ? LIMIT 1',
            $this->table
        );
        return Database::fetchOne($sql, [$appCode, $type]);
    }

    /**
     * 在事务中加行锁后取行 —— Service 层调,用于"分站买扣库存"防超卖。
     *
     * @return array<string, mixed>|null
     */
    public function lockById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $sql = sprintf('SELECT * FROM `%s` WHERE `id` = ? FOR UPDATE', $this->table);
        return Database::fetchOne($sql, [$id]);
    }

    /**
     * 主站后台列表(分页 + 过滤)。
     *
     * @param array{type?:string,is_listed?:int|string,keyword?:string,page?:int,page_size?:int} $filter
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function paginate(array $filter): array
    {
        $page = max(1, (int) ($filter['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($filter['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        [$where, $params] = $this->buildWhere($filter);

        $countSql = 'SELECT COUNT(*) AS c FROM `' . $this->table . '` ' . $where;
        $total = (int) (Database::fetchOne($countSql, $params)['c'] ?? 0);

        $sql = 'SELECT * FROM `' . $this->table . '` ' . $where
             . ' ORDER BY `updated_at` DESC LIMIT ' . $pageSize . ' OFFSET ' . $offset;
        $rows = Database::query($sql, $params);
        return ['data' => $rows, 'total' => $total];
    }

    /**
     * 分站市场用:列出所有"对分站可见"(is_listed=1)的应用。
     * Service 层会再合并 em_app_purchase 算出"已购/未购/缺货"状态。
     *
     * @return array<int, array<string, mixed>>
     */
    public function listListed(?string $type = null): array
    {
        $params = [];
        $sql = 'SELECT * FROM `' . $this->table . '` WHERE `is_listed` = 1';
        if ($type !== null && in_array($type, ['plugin', 'template'], true)) {
            $sql .= ' AND `type` = ?';
            $params[] = $type;
        }
        $sql .= ' ORDER BY `updated_at` DESC';
        return Database::query($sql, $params);
    }

    // -----------------------------------------------------------------
    // 写 —— upsert / 库存调整 / 上下架 / 改价
    // -----------------------------------------------------------------

    /**
     * 创建或更新 market 行(主站采购落库时调)。
     *
     * 不动 quota / consumed —— 那两个字段由 addQuota / incrementConsumed 单独维护。
     *
     * @param array<string, mixed> $data 至少含 app_code/type/title/version/cost_price/retail_price 等字段
     * @return int 受影响 market 行 id
     */
    public function upsert(array $data): int
    {
        $appCode = (string) ($data['app_code'] ?? '');
        $type    = (string) ($data['type'] ?? '');
        if ($appCode === '' || !in_array($type, ['plugin', 'template'], true)) {
            throw new InvalidArgumentException('app_code/type 非法');
        }

        $existing = $this->findByAppCode($appCode, $type);
        $payload = $this->pickEditableFields($data);

        if ($existing !== null) {
            // 已存在 → 更新元数据(不动配额);未提供的字段保留旧值
            if ($payload !== []) {
                $sets = [];
                $params = [];
                foreach ($payload as $col => $val) {
                    $sets[] = '`' . $col . '` = ?';
                    $params[] = $val;
                }
                $params[] = (int) $existing['id'];
                $sql = 'UPDATE `' . $this->table . '` SET ' . implode(', ', $sets)
                     . ' WHERE `id` = ? LIMIT 1';
                Database::execute($sql, $params);
            }
            return (int) $existing['id'];
        }

        // 不存在 → 全字段插入
        $payload['app_code'] = $appCode;
        $payload['type']     = $type;
        $cols = []; $placeholders = []; $params = [];
        foreach ($payload as $col => $val) {
            $cols[] = '`' . $col . '`';
            $placeholders[] = '?';
            $params[] = $val;
        }
        $sql = 'INSERT INTO `' . $this->table . '` (' . implode(',', $cols) . ')'
             . ' VALUES (' . implode(',', $placeholders) . ')';
        Database::execute($sql, $params);
        return (int) (Database::fetchOne('SELECT LAST_INSERT_ID() AS id', [])['id'] ?? 0);
    }

    /**
     * 给 market 增加配额(主站采购时调) + 写一行 market_log。
     *
     * Service 层负责开事务包裹"upsert + addQuota",保证半成功不会留下脏数据。
     *
     * @param int    $marketId
     * @param int    $qty            本次采购的配额数量
     * @param int    $costPerUnit    本次单价(微分)
     * @param string $remoteOrderNo  服务端订单号
     * @param string $remark         采购备注
     * @return int 写入的 market_log 行 id
     */
    public function addQuota(int $marketId, int $qty, int $costPerUnit, string $remoteOrderNo, string $remark): int
    {
        if ($marketId <= 0 || $qty <= 0 || $costPerUnit < 0) {
            throw new InvalidArgumentException('addQuota 参数非法');
        }

        $row = $this->findById($marketId);
        if ($row === null) {
            throw new RuntimeException('market 行不存在');
        }

        $totalCost = $costPerUnit * $qty;

        // 更新 market:total_quota 累加;cost_price 缓存最近单价;last_purchased_at 更新
        Database::execute(
            'UPDATE `' . $this->table . '`
                SET `total_quota` = `total_quota` + ?,
                    `cost_price` = ?,
                    `last_purchased_at` = NOW()
              WHERE `id` = ? LIMIT 1',
            [$qty, $costPerUnit, $marketId]
        );

        // 写流水
        Database::execute(
            'INSERT INTO `' . $this->logTable . '`
                (`market_id`, `app_code`, `type`, `purchase_qty`, `cost_per_unit`,
                 `total_cost`, `remote_order_no`, `remark`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $marketId,
                (string) $row['app_code'],
                (string) $row['type'],
                $qty,
                $costPerUnit,
                $totalCost,
                $remoteOrderNo,
                $remark,
            ]
        );
        return (int) (Database::fetchOne('SELECT LAST_INSERT_ID() AS id', [])['id'] ?? 0);
    }

    /**
     * 给 market 已售配额 +1(分站买时调)。
     *
     * 必须在事务内 + 已经 lockById($marketId) 的前提下调用,Model 不再做并发保护。
     */
    public function incrementConsumed(int $marketId): void
    {
        if ($marketId <= 0) {
            throw new InvalidArgumentException('marketId 非法');
        }
        Database::execute(
            'UPDATE `' . $this->table . '`
                SET `consumed_quota` = `consumed_quota` + 1
              WHERE `id` = ? LIMIT 1',
            [$marketId]
        );
    }

    /**
     * 修改分站售价(主站后台用)。
     */
    public function setRetailPrice(int $marketId, int $price): bool
    {
        if ($marketId <= 0 || $price < 0) {
            return false;
        }
        return Database::execute(
            'UPDATE `' . $this->table . '` SET `retail_price` = ? WHERE `id` = ? LIMIT 1',
            [$price, $marketId]
        ) > 0;
    }

    /**
     * 上下架(is_listed)。
     * 下架后:已购分站不受影响,但库存不再卖出,分站市场看不到该应用。
     */
    public function setListed(int $marketId, bool $listed): bool
    {
        if ($marketId <= 0) {
            return false;
        }
        return Database::execute(
            'UPDATE `' . $this->table . '` SET `is_listed` = ? WHERE `id` = ? LIMIT 1',
            [$listed ? 1 : 0, $marketId]
        ) > 0;
    }

    /**
     * 删除一条 market 记录(主站卸载分站货架应用时用)。
     *
     * @param bool $deleteLogs 是否一并删除采购流水
     */
    public function deleteById(int $marketId, bool $deleteLogs = true): bool
    {
        if ($marketId <= 0) {
            return false;
        }

        $affected = Database::execute(
            'DELETE FROM `' . $this->table . '` WHERE `id` = ? LIMIT 1',
            [$marketId]
        );
        if ($affected <= 0) {
            return false;
        }

        if ($deleteLogs) {
            Database::execute(
                'DELETE FROM `' . $this->logTable . '` WHERE `market_id` = ?',
                [$marketId]
            );
        }
        return true;
    }

    // -----------------------------------------------------------------
    // 流水查询
    // -----------------------------------------------------------------

    /**
     * 按 market_id 查采购流水(管理后台展示历史价 / 总采购量)。
     *
     * @return array<int, array<string, mixed>>
     */
    public function logsByMarket(int $marketId, int $limit = 50): array
    {
        if ($marketId <= 0) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT * FROM `' . $this->logTable . '`
                  WHERE `market_id` = ?
                  ORDER BY `id` DESC
                  LIMIT ' . $limit;
        return Database::query($sql, [$marketId]);
    }

    // -----------------------------------------------------------------
    // 内部辅助
    // -----------------------------------------------------------------

    /**
     * 构造主站后台分页 WHERE。
     *
     * @param array<string, mixed> $filter
     * @return array{0:string,1:array<int, mixed>}
     */
    private function buildWhere(array $filter): array
    {
        $conds = ['1=1'];
        $params = [];

        $type = (string) ($filter['type'] ?? '');
        if (in_array($type, ['plugin', 'template'], true)) {
            $conds[] = '`type` = ?';
            $params[] = $type;
        }
        if (isset($filter['is_listed']) && $filter['is_listed'] !== '') {
            $conds[] = '`is_listed` = ?';
            $params[] = (int) $filter['is_listed'];
        }
        $kw = trim((string) ($filter['keyword'] ?? ''));
        if ($kw !== '') {
            $conds[] = '(`title` LIKE ? OR `app_code` LIKE ?)';
            $params[] = '%' . $kw . '%';
            $params[] = '%' . $kw . '%';
        }
        return ['WHERE ' . implode(' AND ', $conds), $params];
    }

    /**
     * upsert 时允许的字段白名单。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function pickEditableFields(array $data): array
    {
        $allowed = [
            'remote_app_id', 'title', 'version', 'category',
            'cover', 'description', 'cost_price', 'retail_price',
            'is_listed', 'remote_payload',
        ];
        $out = [];
        foreach ($allowed as $f) {
            if (!array_key_exists($f, $data)) continue;
            $val = $data[$f];
            if ($f === 'remote_payload' && is_array($val)) {
                $val = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $out[$f] = $val;
        }
        return $out;
    }
}
