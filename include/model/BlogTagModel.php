<?php

if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

/**
 * 博客标签模型
 *
 * 管理标签 CRUD 和文章-标签关联关系。
 * article_count 为冗余字段，通过 refreshAllCounts() 刷新。
 *
 * 多租户：标签按 merchant_id 分池，主站和商户可以同名（uk_merchant_name）。
 */
class BlogTagModel
{
    // ============================================================
    // 标签 CRUD
    // ============================================================

    /**
     * 获取指定 scope 下的所有标签（按 sort ASC, id ASC 排序）
     */
    public static function getAll(int $merchantId = 0): array
    {
        $prefix = Database::prefix();
        return Database::query(
            "SELECT * FROM {$prefix}blog_tag WHERE merchant_id = ? ORDER BY sort ASC, id ASC",
            [$merchantId]
        );
    }

    /**
     * 后台标签列表（分页 + 搜索）
     */
    public static function getAdminList(array $where = [], int $page = 1, int $limit = 20): array
    {
        $prefix = Database::prefix();
        $conditions = [];
        $params = [];

        if (array_key_exists('merchant_id', $where)) {
            $conditions[] = 'merchant_id = ?';
            $params[] = (int) $where['merchant_id'];
        }

        if (!empty($where['keyword'])) {
            $conditions[] = 'name LIKE ?';
            $params[] = '%' . $where['keyword'] . '%';
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $total = Database::fetchOne(
            "SELECT COUNT(*) as count FROM {$prefix}blog_tag {$whereSql}",
            $params
        );
        $totalCount = (int) ($total['count'] ?? 0);

        $offset = ($page - 1) * $limit;
        $list = Database::query(
            "SELECT * FROM {$prefix}blog_tag {$whereSql} ORDER BY sort ASC, id ASC LIMIT {$offset}, {$limit}",
            $params
        );

        return [
            'total' => $totalCount,
            'page'  => $page,
            'limit' => $limit,
            'list'  => $list,
        ];
    }

    /**
     * 根据 ID 获取标签（不带 ACL，调用方负责）
     */
    public static function getById(int $id): ?array
    {
        $prefix = Database::prefix();
        $row = Database::fetchOne("SELECT * FROM {$prefix}blog_tag WHERE id = ?", [$id]);
        return $row ?: null;
    }

    /**
     * 在指定 scope 下按名称查找标签
     */
    public static function getByName(string $name, int $merchantId = 0): ?array
    {
        $prefix = Database::prefix();
        $row = Database::fetchOne(
            "SELECT * FROM {$prefix}blog_tag WHERE name = ? AND merchant_id = ?",
            [$name, $merchantId]
        );
        return $row ?: null;
    }

    /**
     * 创建标签。$data 必须包含 merchant_id（调用方决定 0 还是商户 ID）。
     *
     * @return int 新标签 ID
     */
    public static function create(array $data): int
    {
        if (!array_key_exists('merchant_id', $data)) {
            $data['merchant_id'] = 0;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('blog_tag', $data);
    }

    /**
     * 更新标签。merchant_id 不允许通过 update 修改。
     */
    public static function update(int $id, array $data): bool
    {
        unset($data['merchant_id']);
        return Database::update('blog_tag', $data, $id) !== false;
    }

    /**
     * 删除标签（同时删除关联）
     */
    public static function delete(int $id): bool
    {
        $prefix = Database::prefix();
        Database::execute("DELETE FROM {$prefix}blog_tag_relation WHERE tag_id = ?", [$id]);
        return Database::execute("DELETE FROM {$prefix}blog_tag WHERE id = ?", [$id]) > 0;
    }

    /**
     * 在指定 scope 下检查标签名是否已存在
     */
    public static function nameExists(string $name, int $excludeId = 0, int $merchantId = 0): bool
    {
        $prefix = Database::prefix();
        $sql = "SELECT id FROM {$prefix}blog_tag WHERE name = ? AND merchant_id = ?";
        $params = [$name, $merchantId];
        if ($excludeId > 0) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        return Database::fetchOne($sql, $params) !== null;
    }

    /**
     * 在指定 scope 下按名称查找或创建标签，返回标签 ID
     */
    public static function findOrCreate(string $name, int $merchantId = 0): int
    {
        $name = trim($name);
        $existing = self::getByName($name, $merchantId);
        if ($existing) {
            return (int) $existing['id'];
        }
        return self::create(['name' => $name, 'merchant_id' => $merchantId]);
    }

    // ============================================================
    // 文章-标签关联
    // ============================================================

    /**
     * 获取文章的所有标签
     */
    public static function getTagsByBlogId(int $blogId): array
    {
        $prefix = Database::prefix();
        return Database::query(
            "SELECT t.* FROM {$prefix}blog_tag t
             INNER JOIN {$prefix}blog_tag_relation r ON t.id = r.tag_id
             WHERE r.blog_id = ?
             ORDER BY t.sort ASC, t.id ASC",
            [$blogId]
        );
    }

    /**
     * 获取多篇文章的标签（批量，返回 blog_id => [tags] 映射）
     */
    public static function getTagsByBlogIds(array $blogIds): array
    {
        if (empty($blogIds)) return [];
        $prefix = Database::prefix();
        $placeholders = implode(',', array_fill(0, count($blogIds), '?'));
        $rows = Database::query(
            "SELECT r.blog_id, t.id, t.name, t.color FROM {$prefix}blog_tag t
             INNER JOIN {$prefix}blog_tag_relation r ON t.id = r.tag_id
             WHERE r.blog_id IN ({$placeholders})
             ORDER BY t.sort ASC, t.id ASC",
            $blogIds
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['blog_id']][] = $row;
        }
        return $map;
    }

    /**
     * 同步文章的标签（先删旧关联，再插入新关联）
     *
     * @param int   $blogId 文章 ID
     * @param array $tagIds 标签 ID 数组
     */
    public static function syncBlogTags(int $blogId, array $tagIds): void
    {
        $prefix = Database::prefix();
        // 删除旧关联
        Database::execute("DELETE FROM {$prefix}blog_tag_relation WHERE blog_id = ?", [$blogId]);
        // 插入新关联
        foreach (array_unique(array_filter($tagIds)) as $tagId) {
            Database::insert('blog_tag_relation', [
                'blog_id' => $blogId,
                'tag_id'  => (int) $tagId,
            ]);
        }
    }

    /**
     * 刷新所有标签的 article_count
     */
    public static function refreshAllCounts(): void
    {
        $prefix = Database::prefix();
        Database::execute(
            "UPDATE {$prefix}blog_tag t SET t.article_count = (
                SELECT COUNT(DISTINCT r.blog_id)
                FROM {$prefix}blog_tag_relation r
                INNER JOIN {$prefix}blog b ON r.blog_id = b.id
                WHERE r.tag_id = t.id AND b.status = 1 AND b.deleted_at IS NULL
            )"
        );
    }

    /**
     * 获取指定 scope 下的热门标签（按文章数量排序）
     */
    public static function getPopularTags(int $limit = 20, int $merchantId = 0): array
    {
        $prefix = Database::prefix();
        return Database::query(
            "SELECT * FROM {$prefix}blog_tag
             WHERE article_count > 0 AND merchant_id = ?
             ORDER BY article_count DESC, sort ASC, id ASC
             LIMIT ?",
            [$merchantId, $limit]
        );
    }

    /**
     * 根据标签 ID 获取关联的文章 ID 列表
     */
    public static function getBlogIdsByTagId(int $tagId): array
    {
        $prefix = Database::prefix();
        $rows = Database::query(
            "SELECT blog_id FROM {$prefix}blog_tag_relation WHERE tag_id = ?",
            [$tagId]
        );
        return array_column($rows, 'blog_id');
    }
}
