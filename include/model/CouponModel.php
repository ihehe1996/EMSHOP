<?php

declare(strict_types=1);

/**
 * 优惠券定义模型。
 *
 * 数据表：em_coupon
 * 金额字段：value / min_amount / max_discount 都按 BIGINT ×1000000 存储；
 * 打折券的 value 例外，存整数 0-100（如 85 代表 8.5 折）。
 */
class CouponModel
{
    private string $table;

    public const TYPE_FIXED_AMOUNT   = 'fixed_amount';
    public const TYPE_PERCENT        = 'percent';
    public const TYPE_FREE_SHIPPING  = 'free_shipping';

    public const SCOPE_ALL         = 'all';
    public const SCOPE_CATEGORY    = 'category';
    public const SCOPE_GOODS       = 'goods';
    public const SCOPE_GOODS_TYPE  = 'goods_type';

    public function __construct()
    {
        $this->table = Database::prefix() . 'coupon';
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_FIXED_AMOUNT  => '满减券',
            self::TYPE_PERCENT       => '折扣券',
            self::TYPE_FREE_SHIPPING => '免邮券',
        ];
    }

    public static function scopeOptions(): array
    {
        return [
            self::SCOPE_ALL        => '全场通用',
            self::SCOPE_CATEGORY   => '指定分类',
            self::SCOPE_GOODS      => '指定商品',
            self::SCOPE_GOODS_TYPE => '指定商品类型',
        ];
    }

    /**
     * 分页查询（后台列表用）。
     *
     * @param array $filter 过滤：keyword / type / enabled
     */
    public function paginate(array $filter = [], int $page = 1, int $perPage = 20): array
    {
        $where = ['deleted_at IS NULL'];
        $params = [];
        if (!empty($filter['keyword'])) {
            $where[] = '(name LIKE ? OR code LIKE ? OR title LIKE ?)';
            $kw = '%' . $filter['keyword'] . '%';
            $params[] = $kw; $params[] = $kw; $params[] = $kw;
        }
        if (!empty($filter['type'])) {
            $where[] = 'type = ?';
            $params[] = $filter['type'];
        }
        if (isset($filter['enabled']) && $filter['enabled'] !== '') {
            $where[] = 'is_enabled = ?';
            $params[] = (int) $filter['enabled'];
        }
        $whereSql = implode(' AND ', $where);

        $countRow = Database::fetchOne("SELECT COUNT(*) AS cnt FROM {$this->table} WHERE {$whereSql}", $params);
        $total = (int) ($countRow['cnt'] ?? 0);

        $page = max(1, $page);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;

        $rows = Database::query(
            "SELECT * FROM {$this->table} WHERE {$whereSql} ORDER BY sort ASC, id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        foreach ($rows as &$row) {
            $this->transformFromDb($row);
        }
        unset($row);

        return [
            'list'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    public function findById(int $id): ?array
    {
        $row = Database::fetchOne("SELECT * FROM {$this->table} WHERE id = ? AND deleted_at IS NULL LIMIT 1", [$id]);
        if (!$row) return null;
        $this->transformFromDb($row);
        return $row;
    }

    public function findByCode(string $code): ?array
    {
        if ($code === '') return null;
        $row = Database::fetchOne("SELECT * FROM {$this->table} WHERE code = ? AND deleted_at IS NULL LIMIT 1", [$code]);
        if (!$row) return null;
        $this->transformFromDb($row);
        return $row;
    }

    /**
     * 获取所有"公开可领取"的券（领券中心展示）。
     *
     * 条件：启用中 + 未过期 + 未超总次数
     */
    public function getPubliclyClaimable(int $limit = 100): array
    {
        $now = date('Y-m-d H:i:s');
        $rows = Database::query(
            "SELECT * FROM {$this->table}
             WHERE deleted_at IS NULL AND is_enabled = 1
               AND (start_at IS NULL OR start_at <= ?)
               AND (end_at IS NULL OR end_at >= ?)
               AND (total_usage_limit = -1 OR used_count < total_usage_limit)
             ORDER BY sort ASC, id DESC
             LIMIT {$limit}",
            [$now, $now]
        );
        foreach ($rows as &$row) $this->transformFromDb($row);
        return $rows;
    }

    public function create(array $data): int
    {
        $row = $this->transformToDb($data);
        $now = date('Y-m-d H:i:s');
        $row['created_at'] = $now;
        $row['updated_at'] = $now;
        return (int) Database::insert('coupon', $row);
    }

    public function update(int $id, array $data): bool
    {
        $row = $this->transformToDb($data);
        $row['updated_at'] = date('Y-m-d H:i:s');
        return Database::update('coupon', $row, $id) > 0;
    }

    public function softDelete(int $id): bool
    {
        return Database::update('coupon', ['deleted_at' => date('Y-m-d H:i:s')], $id) > 0;
    }

    public function toggle(int $id): bool
    {
        $current = $this->findById($id);
        if (!$current) return false;
        return Database::update('coupon', ['is_enabled' => $current['is_enabled'] ? 0 : 1], $id) > 0;
    }

    public function existsCode(string $code, int $excludeId = 0): bool
    {
        $sql = "SELECT id FROM {$this->table} WHERE code = ? AND deleted_at IS NULL";
        $params = [$code];
        if ($excludeId > 0) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $row = Database::fetchOne($sql . ' LIMIT 1', $params);
        return $row !== null && $row !== false;
    }

    /**
     * 数据库行 → PHP 数组：金额字段除以 1,000,000，apply_ids 解码 JSON
     */
    private function transformFromDb(array &$row): void
    {
        // 金额字段还原（打折券的 value 保持整数百分比）
        $moneyFields = ['min_amount', 'max_discount'];
        if (($row['type'] ?? '') !== self::TYPE_PERCENT) {
            $moneyFields[] = 'value';
        }
        foreach ($moneyFields as $f) {
            if (isset($row[$f])) {
                $row[$f . '_raw'] = (int) $row[$f];
                $row[$f] = bcdiv((string) $row[$f], '1000000', 2);
            }
        }
        if (($row['type'] ?? '') === self::TYPE_PERCENT && isset($row['value'])) {
            // 百分比直接整数
            $row['value_raw'] = (int) $row['value'];
            $row['value'] = (int) $row['value'];
        }
        // apply_ids JSON
        $row['apply_ids'] = json_decode((string) ($row['apply_ids'] ?? ''), true) ?: [];
    }

    /**
     * PHP 数组 → DB 行：金额乘 1,000,000，apply_ids 编码 JSON
     */
    private function transformToDb(array $data): array
    {
        $out = [];
        $passThrough = [
            'code', 'name', 'title', 'description',
            'type', 'apply_scope',
            'start_at', 'end_at',
            'total_usage_limit', 'is_enabled', 'owner_id', 'sort',
        ];
        foreach ($passThrough as $k) {
            if (array_key_exists($k, $data)) $out[$k] = $data[$k];
        }
        // 金额字段 ×1000000
        $moneyFields = ['min_amount', 'max_discount'];
        $type = $data['type'] ?? self::TYPE_FIXED_AMOUNT;
        if ($type !== self::TYPE_PERCENT) {
            $moneyFields[] = 'value';
        }
        foreach ($moneyFields as $f) {
            if (array_key_exists($f, $data)) {
                $v = trim((string) $data[$f]);
                $out[$f] = $v === '' ? 0 : (int) bcmul($v, '1000000', 0);
            }
        }
        if ($type === self::TYPE_PERCENT && array_key_exists('value', $data)) {
            // 百分比：整数 0-100
            $out['value'] = max(0, min(100, (int) $data['value']));
        }
        if (array_key_exists('apply_ids', $data)) {
            $ids = is_array($data['apply_ids']) ? $data['apply_ids'] : [];
            $ids = array_values(array_filter(array_map('intval', $ids), fn($x) => $x > 0));
            $out['apply_ids'] = $ids ? json_encode($ids, JSON_UNESCAPED_UNICODE) : null;
        }
        return $out;
    }

    /**
     * used_count 原子加 1（下单使用时调用）；并做总次数校验。
     * 失败（超上限）时返回 false。
     */
    public function incrementUsedCount(int $couponId): bool
    {
        // 条件更新：未超上限才能 +1
        $n = Database::execute(
            "UPDATE {$this->table}
             SET used_count = used_count + 1, updated_at = NOW()
             WHERE id = ? AND deleted_at IS NULL
               AND (total_usage_limit = -1 OR used_count < total_usage_limit)",
            [$couponId]
        );
        return $n > 0;
    }
}
