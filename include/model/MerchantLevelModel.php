<?php

declare(strict_types=1);

/**
 * 商户等级模型。
 *
 * 字段说明详见 a 系统文档/分站功能方案.md §3.1.1。
 * 费率类字段（self_goods_fee_rate、withdraw_fee_rate）均为整数万分位：
 *   500  = 5%
 *   100  = 1%
 *   0    = 0%
 * price 字段为 BIGINT ×1000000（与项目整体金额约定一致）。
 */
final class MerchantLevelModel
{
    /** @var string 带前缀完整表名 */
    private string $table;

    public function __construct()
    {
        $this->table = Database::prefix() . 'merchant_level';
    }

    /**
     * 返回全部等级，支持按名称模糊搜索。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(string $keyword = ''): array
    {
        if ($keyword === '') {
            $sql = 'SELECT * FROM `' . $this->table . '`
                     WHERE `deleted_at` IS NULL
                     ORDER BY `sort` ASC, `id` ASC';
            return Database::query($sql);
        }

        $sql = 'SELECT * FROM `' . $this->table . '`
                 WHERE `deleted_at` IS NULL AND `name` LIKE ?
                 ORDER BY `sort` ASC, `id` ASC';
        return Database::query($sql, ['%' . $keyword . '%']);
    }

    /**
     * 返回所有启用的等级（供下拉选择使用）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEnabledList(): array
    {
        $sql = 'SELECT `id`, `name`, `price`, `self_goods_fee_rate`, `withdraw_fee_rate`
                  FROM `' . $this->table . '`
                 WHERE `deleted_at` IS NULL AND `is_enabled` = 1
                 ORDER BY `sort` ASC, `id` ASC';
        return Database::query($sql);
    }

    /**
     * 按 id 查单条。
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $sql = 'SELECT * FROM `' . $this->table . '`
                 WHERE `id` = ? AND `deleted_at` IS NULL LIMIT 1';
        return Database::fetchOne($sql, [$id]);
    }

    /**
     * 等级名唯一校验。
     */
    public function existsName(string $name, int $excludeId = 0): bool
    {
        if ($excludeId > 0) {
            $sql = 'SELECT `id` FROM `' . $this->table . '`
                     WHERE `name` = ? AND `id` != ? AND `deleted_at` IS NULL LIMIT 1';
            $row = Database::fetchOne($sql, [$name, $excludeId]);
        } else {
            $sql = 'SELECT `id` FROM `' . $this->table . '`
                     WHERE `name` = ? AND `deleted_at` IS NULL LIMIT 1';
            $row = Database::fetchOne($sql, [$name]);
        }
        return $row !== null;
    }

    /**
     * 新建等级，返回自增 id。
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        return Database::insert('merchant_level', $this->pickFields($data));
    }

    /**
     * 更新指定等级。
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        if ($id <= 0) {
            return false;
        }
        return Database::update('merchant_level', $this->pickFields($data), $id) > 0;
    }

    /**
     * 软删除。
     */
    public function softDelete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        return Database::update('merchant_level', ['deleted_at' => date('Y-m-d H:i:s')], $id) > 0;
    }

    /**
     * 切换启用状态，返回新值（1/0）。
     */
    public function toggle(int $id): int
    {
        $row = $this->findById($id);
        if ($row === null) {
            return -1;
        }
        $newVal = ((int) $row['is_enabled']) === 1 ? 0 : 1;
        Database::update('merchant_level', ['is_enabled' => $newVal], $id);
        return $newVal;
    }

    /**
     * 统计某个等级下的商户数（用于删除前校验）。
     */
    public function countMerchants(int $levelId): int
    {
        if ($levelId <= 0) {
            return 0;
        }
        $sql = 'SELECT COUNT(*) AS `c` FROM `' . Database::prefix() . 'merchant`
                 WHERE `level_id` = ? AND `deleted_at` IS NULL';
        $row = Database::fetchOne($sql, [$levelId]);
        return (int) ($row['c'] ?? 0);
    }

    /**
     * 从传入 $data 中挑出表允许的字段。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function pickFields(array $data): array
    {
        $allowed = [
            'name', 'price',
            'self_goods_fee_rate', 'withdraw_fee_rate',
            'allow_subdomain', 'allow_custom_domain',
            'allow_self_goods',
            'sort', 'is_enabled',
        ];
        $out = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $out[$f] = $data[$f];
            }
        }
        return $out;
    }
}
