<?php

declare(strict_types=1);

/**
 * 商品分类数据模型。
 *
 * 仅支持2级分类：顶级(parent_id=0) 和 二级(parent_id>0, 归属某顶级)。
 */
final class GoodsCategoryModel
{
    private string $table;

    public function __construct()
    {
        $this->table = Database::prefix() . 'goods_category';
    }

    /**
     * 获取所有分类（扁平列表，按 sort 排序）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $sql = sprintf(
            'SELECT * FROM `%s` ORDER BY `sort` ASC, `id` ASC',
            $this->table
        );
        return Database::query($sql, []);
    }

    /**
     * 获取所有顶级分类（parent_id = 0）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTopLevel(): array
    {
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `parent_id` = 0 ORDER BY `sort` ASC, `id` ASC',
            $this->table
        );
        return Database::query($sql, []);
    }

    /**
     * 按 ID 获取单条分类。
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE `id` = ? LIMIT 1', $this->table);
        return Database::fetchOne($sql, [$id]);
    }

    /**
     * 创建分类。
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $fields = [
            'parent_id', 'name', 'slug', 'description',
            'icon', 'cover_image', 'sort',
            'seo_title', 'seo_keywords', 'seo_description', 'status',
            'rebate_config',
        ];

        $cols = [];
        $placeholders = [];
        $params = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $cols[] = '`' . $field . '`';
                $placeholders[] = '?';
                $params[] = (string) $data[$field];
            }
        }

        $cols[] = '`created_at`';
        $placeholders[] = 'NOW()';
        $cols[] = '`updated_at`';
        $placeholders[] = 'NOW()';

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->table,
            implode(', ', $cols),
            implode(', ', $placeholders)
        );

        Database::execute($sql, $params);
        return (int) Database::fetchOne('SELECT LAST_INSERT_ID() as id', [])['id'];
    }

    /**
     * 更新分类。
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $fields = [
            'parent_id', 'name', 'slug', 'description',
            'icon', 'cover_image', 'sort',
            'seo_title', 'seo_keywords', 'seo_description', 'status',
            'rebate_config',
        ];

        $sets = [];
        $params = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = '`' . $field . '` = ?';
                $params[] = (string) $data[$field];
            }
        }

        if ($sets === []) {
            return false;
        }

        $sets[] = '`updated_at` = NOW()';
        $params[] = $id;

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `id` = ? LIMIT 1',
            $this->table,
            implode(', ', $sets)
        );

        return Database::execute($sql, $params) > 0;
    }

    /**
     * 删除分类。
     */
    public function delete(int $id): bool
    {
        $sql = sprintf('DELETE FROM `%s` WHERE `id` = ? LIMIT 1', $this->table);
        return Database::execute($sql, [$id]) > 0;
    }

    /**
     * 检查指定分类下是否有子分类。
     */
    public function hasChildren(int $parentId): bool
    {
        $sql = sprintf(
            'SELECT COUNT(*) AS cnt FROM `%s` WHERE `parent_id` = ? LIMIT 1',
            $this->table
        );
        $row = Database::fetchOne($sql, [$parentId]);
        return $row !== null && (int) $row['cnt'] > 0;
    }

    /**
     * 检查别名是否被占用（排除自身）。
     */
    public function existsSlug(string $slug, int $excludeId = 0): bool
    {
        if ($slug === '') {
            return false;
        }
        $sql = sprintf(
            'SELECT `id` FROM `%s` WHERE `slug` = ? AND `id` != ? LIMIT 1',
            $this->table
        );
        return Database::fetchOne($sql, [$slug, $excludeId]) !== null;
    }

    /**
     * 获取分类总数。
     */
    public function count(): int
    {
        $sql = sprintf('SELECT COUNT(*) AS cnt FROM `%s`', $this->table);
        $row = Database::fetchOne($sql, []);
        return $row !== null ? (int) $row['cnt'] : 0;
    }
}
