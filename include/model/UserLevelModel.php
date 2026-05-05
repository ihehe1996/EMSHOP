<?php

declare(strict_types=1);

/**
 * 用户等级数据模型。
 *
 * 数据存储于 user_levels 表（已在数据库中创建）。
 */
final class UserLevelModel
{
    private string $table;

    /** 存储乘数：存储时乘以 1000000，取出时除以 1000000，避免精度丢失 */
    private const MULTIPLIER = 1000000;

    public function __construct()
    {
        $this->table = Database::prefix() . 'user_levels';
    }

    /**
     * 将前端传入的值转换为数据库存储格式（乘以 1000000）。
     */
    private function toDb(?float $value): ?int
    {
        if ($value === null) {
            return null;
        }
        return (int) round($value * self::MULTIPLIER);
    }

    /**
     * 将数据库值转换回前端显示格式（除以 1000000）。
     */
    private function fromDb(?int $value): float
    {
        if ($value === null) {
            return 0.0;
        }
        return $value / self::MULTIPLIER;
    }

    /**
     * 转换单条数据的 discount 和 self_open_price 从数据库格式到显示格式。
     */
    private function transformFromDb(array &$row): void
    {
        if (isset($row['discount'])) {
            $row['discount'] = $this->fromDb((int) $row['discount']);
        }
        if (isset($row['self_open_price'])) {
            $row['self_open_price'] = $this->fromDb((int) $row['self_open_price']);
        }
    }

    /**
     * 转换单条数据的 discount 和 self_open_price 从显示格式到数据库格式。
     */
    private function transformToDb(array $data): array
    {
        if (isset($data['discount'])) {
            $data['discount'] = $this->toDb((float) $data['discount']);
        }
        if (isset($data['self_open_price'])) {
            $data['self_open_price'] = $this->toDb((float) $data['self_open_price']);
        }
        return $data;
    }

    /**
     * 获取所有等级，支持关键词搜索。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(string $keyword = ''): array
    {
        if ($keyword === '') {
            $sql = sprintf(
                'SELECT * FROM `%s` ORDER BY `level` ASC, `id` ASC',
                $this->table
            );
            $rows = Database::query($sql, []);
        } else {
            $sql = sprintf(
                'SELECT * FROM `%s` WHERE `name` LIKE ? ORDER BY `level` ASC, `id` ASC',
                $this->table
            );
            $rows = Database::query($sql, ['%' . $keyword . '%']);
        }

        foreach ($rows as &$row) {
            $this->transformFromDb($row);
        }
        return $rows;
    }

    /**
     * 按 ID 获取单条等级。
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE `id` = ? LIMIT 1', $this->table);
        $row = Database::fetchOne($sql, [$id]);
        if ($row === false) {
            return null;
        }
        $this->transformFromDb($row);
        return $row;
    }

    /**
     * 检查等级名称是否已存在（排除自身）。
     */
    public function existsName(string $name, int $excludeId = 0): bool
    {
        if ($excludeId > 0) {
            $sql = sprintf(
                'SELECT `id` FROM `%s` WHERE `name` = ? AND `id` != ? LIMIT 1',
                $this->table
            );
            $row = Database::fetchOne($sql, [$name, $excludeId]);
        } else {
            $sql = sprintf('SELECT `id` FROM `%s` WHERE `name` = ? LIMIT 1', $this->table);
            $row = Database::fetchOne($sql, [$name]);
        }
        return $row !== null;
    }

    /**
     * 检查等级数值是否已存在（排除自身）。
     */
    public function existsLevel(int $level, int $excludeId = 0): bool
    {
        if ($excludeId > 0) {
            $sql = sprintf(
                'SELECT `id` FROM `%s` WHERE `level` = ? AND `id` != ? LIMIT 1',
                $this->table
            );
            $row = Database::fetchOne($sql, [$level, $excludeId]);
        } else {
            $sql = sprintf('SELECT `id` FROM `%s` WHERE `level` = ? LIMIT 1', $this->table);
            $row = Database::fetchOne($sql, [$level]);
        }
        return $row !== null;
    }

    /**
     * 创建等级。
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $fields = ['name', 'level', 'discount', 'self_open_price', 'unlock_exp', 'remark', 'enabled'];

        $data = $this->transformToDb($data);

        $cols = [];
        $placeholders = [];
        $params = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $cols[] = '`' . $field . '`';
                $placeholders[] = '?';
                $params[] = $data[$field];
            }
        }

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->table,
            implode(', ', $cols),
            implode(', ', $placeholders)
        );

        Database::execute($sql, $params);

        $row = Database::fetchOne('SELECT LAST_INSERT_ID() AS id', []);
        return (int) ($row['id'] ?? 0);
    }

    /**
     * 更新等级。
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $fields = ['name', 'level', 'discount', 'self_open_price', 'unlock_exp', 'remark', 'enabled'];

        $data = $this->transformToDb($data);

        $sets = [];
        $params = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = '`' . $field . '` = ?';
                $params[] = $data[$field];
            }
        }

        if ($sets === []) {
            return false;
        }

        $params[] = $id;

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `id` = ? LIMIT 1',
            $this->table,
            implode(', ', $sets)
        );

        return Database::execute($sql, $params) > 0;
    }

    /**
     * 删除等级。
     */
    public function delete(int $id): bool
    {
        $sql = sprintf('DELETE FROM `%s` WHERE `id` = ? LIMIT 1', $this->table);
        return Database::execute($sql, [$id]) > 0;
    }

    /**
     * 切换启用状态。
     */
    public function toggle(int $id): bool
    {
        $sql = sprintf(
            'UPDATE `%s` SET `enabled` = IF(`enabled` = \'y\', \'n\', \'y\') WHERE `id` = ? LIMIT 1',
            $this->table
        );
        return Database::execute($sql, [$id]) > 0;
    }
}
