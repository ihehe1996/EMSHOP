<?php

declare(strict_types=1);

/**
 * 友情链接数据模型。
 *
 * 数据存储于 friend_link 表。
 */
final class FriendLinkModel
{
    private string $table;

    public function __construct()
    {
        $this->table = Database::prefix() . 'friend_link';
    }

    /**
     * 获取所有友链，按排序降序。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(?string $keyword = null): array
    {
        $sql = sprintf('SELECT * FROM `%s`', $this->table);

        if ($keyword !== null && $keyword !== '') {
            $sql .= ' WHERE `name` LIKE ? OR `url` LIKE ? OR `description` LIKE ?';
            $kw = '%' . $keyword . '%';
            $params = [$kw, $kw, $kw];
        } else {
            $params = [];
        }

        $sql .= ' ORDER BY `sort` DESC, `id` ASC';

        return Database::query($sql, $params);
    }

    /**
     * 获取已启用的友链，按排序降序。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEnabled(): array
    {
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `enabled` = \'y\' AND (`expire_time` IS NULL OR `expire_time` >= NOW()) ORDER BY `sort` DESC, `id` ASC',
            $this->table
        );
        return Database::query($sql, []);
    }

    /**
     * 获取所有友链（含已过期的），用于后台管理。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllForAdmin(?string $keyword = null): array
    {
        $sql = sprintf('SELECT * FROM `%s`', $this->table);

        if ($keyword !== null && $keyword !== '') {
            $sql .= ' WHERE `name` LIKE ? OR `url` LIKE ? OR `description` LIKE ?';
            $kw = '%' . $keyword . '%';
            $params = [$kw, $kw, $kw];
        } else {
            $params = [];
        }

        $sql .= ' ORDER BY `sort` DESC, `id` ASC';

        return Database::query($sql, $params);
    }

    /**
     * 按 ID 获取单条。
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE `id` = ? LIMIT 1', $this->table);
        $row = Database::fetchOne($sql, [$id]);
        return $row !== false ? $row : null;
    }

    /**
     * 检查名称是否已存在（排除自身）。
     */
    public function existsName(string $name, int $excludeId = 0): bool
    {
        if ($excludeId > 0) {
            $sql = sprintf('SELECT `id` FROM `%s` WHERE `name` = ? AND `id` != ? LIMIT 1', $this->table);
            $row = Database::fetchOne($sql, [$name, $excludeId]);
        } else {
            $sql = sprintf('SELECT `id` FROM `%s` WHERE `name` = ? LIMIT 1', $this->table);
            $row = Database::fetchOne($sql, [$name]);
        }
        return $row !== null;
    }

    /**
     * 创建友链。
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $fields = ['name', 'url', 'image', 'enabled', 'expire_time', 'description', 'sort'];

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
     * 更新友链。
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $fields = ['name', 'url', 'image', 'enabled', 'expire_time', 'description', 'sort'];

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
     * 删除友链。
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

    /**
     * 批量删除友链。
     *
     * @param array<int> $ids
     * @return int 删除了多少条
     */
    public function batchDelete(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = sprintf('DELETE FROM `%s` WHERE `id` IN (%s)', $this->table, $placeholders);
        return Database::execute($sql, $ids);
    }

    /**
     * 获取友链总数。
     */
    public function count(): int
    {
        $sql = sprintf('SELECT COUNT(*) AS cnt FROM `%s`', $this->table);
        $row = Database::fetchOne($sql, []);
        return (int) ($row['cnt'] ?? 0);
    }
}
