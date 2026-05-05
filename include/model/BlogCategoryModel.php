<?php

declare(strict_types=1);

/**
 * 文章分类数据模型。
 *
 * 仅支持2级分类：顶级(parent_id=0) 和 二级(parent_id>0, 归属某顶级)。
 *
 * 多租户：merchant_id=0 主站；>0 商户。商户和主站完全分离。
 */
final class BlogCategoryModel
{
    private string $table;

    public function __construct()
    {
        $this->table = Database::prefix() . 'blog_category';
    }

    /**
     * 获取指定 scope 下的所有分类。
     */
    public function getAll(int $merchantId = 0): array
    {
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `merchant_id` = ? ORDER BY `sort` ASC, `id` ASC',
            $this->table
        );
        return Database::query($sql, [$merchantId]);
    }

    /**
     * 顶级分类（parent_id = 0）。
     */
    public function getTopLevel(int $merchantId = 0): array
    {
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `parent_id` = 0 AND `merchant_id` = ? ORDER BY `sort` ASC, `id` ASC',
            $this->table
        );
        return Database::query($sql, [$merchantId]);
    }

    /**
     * 按 ID 获取。Controller 自行做 merchant_id ACL。
     */
    public function findById(int $id): ?array
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE `id` = ? LIMIT 1', $this->table);
        return Database::fetchOne($sql, [$id]);
    }

    /**
     * 创建分类。$data 必须包含 merchant_id（调用方决定 0 还是商户 ID）。
     */
    public function create(array $data): int
    {
        $fields = [
            'parent_id', 'merchant_id', 'name', 'slug', 'description',
            'icon', 'cover_image', 'sort',
            'seo_title', 'seo_keywords', 'seo_description', 'status',
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
     * 更新分类。merchant_id 不在白名单（不允许通过 update 改归属）。
     */
    public function update(int $id, array $data): bool
    {
        $fields = [
            'parent_id', 'name', 'slug', 'description',
            'icon', 'cover_image', 'sort',
            'seo_title', 'seo_keywords', 'seo_description', 'status',
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

    public function delete(int $id): bool
    {
        $sql = sprintf('DELETE FROM `%s` WHERE `id` = ? LIMIT 1', $this->table);
        return Database::execute($sql, [$id]) > 0;
    }

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
     * 别名是否被占用（在同一 scope 内）。商户和主站可同名。
     */
    public function existsSlug(string $slug, int $excludeId = 0, int $merchantId = 0): bool
    {
        if ($slug === '') {
            return false;
        }
        $sql = sprintf(
            'SELECT `id` FROM `%s` WHERE `slug` = ? AND `merchant_id` = ? AND `id` != ? LIMIT 1',
            $this->table
        );
        return Database::fetchOne($sql, [$slug, $merchantId, $excludeId]) !== null;
    }

    public function count(int $merchantId = 0): int
    {
        $sql = sprintf('SELECT COUNT(*) AS cnt FROM `%s` WHERE `merchant_id` = ?', $this->table);
        $row = Database::fetchOne($sql, [$merchantId]);
        return $row !== null ? (int) $row['cnt'] : 0;
    }
}
