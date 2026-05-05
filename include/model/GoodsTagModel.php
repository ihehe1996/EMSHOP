<?php

if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

/**
 * 商品标签模型
 *
 * 管理标签 CRUD 和商品-标签关联关系。
 * goods_count 为冗余字段，通过 refreshAllCounts() 刷新。
 */
class GoodsTagModel
{
    // ============================================================
    // 标签 CRUD
    // ============================================================

    /**
     * 获取所有标签（按 sort ASC, id ASC 排序）
     */
    public static function getAll(): array
    {
        $prefix = Database::prefix();
        return Database::query(
            "SELECT * FROM {$prefix}goods_tag ORDER BY sort ASC, id ASC"
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

        if (!empty($where['keyword'])) {
            $conditions[] = 'name LIKE ?';
            $params[] = '%' . $where['keyword'] . '%';
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $total = Database::fetchOne(
            "SELECT COUNT(*) as count FROM {$prefix}goods_tag {$whereSql}",
            $params
        );
        $totalCount = (int) ($total['count'] ?? 0);

        $offset = ($page - 1) * $limit;
        $list = Database::query(
            "SELECT * FROM {$prefix}goods_tag {$whereSql} ORDER BY sort ASC, id ASC LIMIT {$offset}, {$limit}",
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
     * 根据 ID 获取标签
     */
    public static function getById(int $id): ?array
    {
        $prefix = Database::prefix();
        $row = Database::fetchOne("SELECT * FROM {$prefix}goods_tag WHERE id = ?", [$id]);
        return $row ?: null;
    }

    /**
     * 根据名称获取标签
     */
    public static function getByName(string $name): ?array
    {
        $prefix = Database::prefix();
        $row = Database::fetchOne("SELECT * FROM {$prefix}goods_tag WHERE name = ?", [$name]);
        return $row ?: null;
    }

    /**
     * 创建标签
     *
     * @return int 新标签 ID
     */
    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return Database::insert('goods_tag', $data);
    }

    /**
     * 更新标签
     */
    public static function update(int $id, array $data): bool
    {
        return Database::update('goods_tag', $data, $id) !== false;
    }

    /**
     * 删除标签（同时删除关联）
     */
    public static function delete(int $id): bool
    {
        $prefix = Database::prefix();
        Database::execute("DELETE FROM {$prefix}goods_tag_relation WHERE tag_id = ?", [$id]);
        return Database::execute("DELETE FROM {$prefix}goods_tag WHERE id = ?", [$id]) > 0;
    }

    /**
     * 检查标签名是否已存在
     */
    public static function nameExists(string $name, int $excludeId = 0): bool
    {
        $prefix = Database::prefix();
        $sql = "SELECT id FROM {$prefix}goods_tag WHERE name = ?";
        $params = [$name];
        if ($excludeId > 0) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        return Database::fetchOne($sql, $params) !== null;
    }

    /**
     * 按名称查找或创建标签，返回标签 ID
     */
    public static function findOrCreate(string $name): int
    {
        $name = trim($name);
        $existing = self::getByName($name);
        if ($existing) {
            return (int) $existing['id'];
        }
        return self::create(['name' => $name]);
    }

    // ============================================================
    // 商品-标签关联
    // ============================================================

    /**
     * 获取商品的所有标签
     */
    public static function getTagsByGoodsId(int $goodsId): array
    {
        $prefix = Database::prefix();
        return Database::query(
            "SELECT t.* FROM {$prefix}goods_tag t
             INNER JOIN {$prefix}goods_tag_relation r ON t.id = r.tag_id
             WHERE r.goods_id = ?
             ORDER BY t.sort ASC, t.id ASC",
            [$goodsId]
        );
    }

    /**
     * 获取多个商品的标签（批量，返回 goods_id => [tags] 映射）
     */
    public static function getTagsByGoodsIds(array $goodsIds): array
    {
        if (empty($goodsIds)) return [];
        $prefix = Database::prefix();
        $placeholders = implode(',', array_fill(0, count($goodsIds), '?'));
        $rows = Database::query(
            "SELECT r.goods_id, t.id, t.name, t.color FROM {$prefix}goods_tag t
             INNER JOIN {$prefix}goods_tag_relation r ON t.id = r.tag_id
             WHERE r.goods_id IN ({$placeholders})
             ORDER BY t.sort ASC, t.id ASC",
            $goodsIds
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['goods_id']][] = $row;
        }
        return $map;
    }

    /**
     * 同步商品的标签（先删旧关联，再插入新关联）
     */
    public static function syncGoodsTags(int $goodsId, array $tagIds): void
    {
        $prefix = Database::prefix();
        Database::execute("DELETE FROM {$prefix}goods_tag_relation WHERE goods_id = ?", [$goodsId]);
        foreach (array_unique(array_filter($tagIds)) as $tagId) {
            Database::insert('goods_tag_relation', [
                'goods_id' => $goodsId,
                'tag_id'   => (int) $tagId,
            ]);
        }
    }

    /**
     * 刷新所有标签的 goods_count
     */
    public static function refreshAllCounts(): void
    {
        $prefix = Database::prefix();
        Database::execute(
            "UPDATE {$prefix}goods_tag t SET t.goods_count = (
                SELECT COUNT(DISTINCT r.goods_id)
                FROM {$prefix}goods_tag_relation r
                INNER JOIN {$prefix}goods g ON r.goods_id = g.id
                WHERE r.tag_id = t.id AND g.status = 1 AND g.deleted_at IS NULL
            )"
        );
    }

    /**
     * 获取热门标签（按商品数量排序）
     */
    public static function getPopularTags(int $limit = 20): array
    {
        $prefix = Database::prefix();
        return Database::query(
            "SELECT * FROM {$prefix}goods_tag WHERE goods_count > 0
             ORDER BY goods_count DESC, sort ASC, id ASC
             LIMIT ?",
            [$limit]
        );
    }
}
