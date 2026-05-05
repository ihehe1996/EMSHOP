<?php

if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

/**
 * 文章模型
 *
 * 多租户：merchant_id=0 主站；>0 商户。所有读写都按 merchant_id 隔离。
 *
 * @package EM\Core\Model
 */
class BlogModel
{
    /**
     * 获取文章列表（前台：仅已发布、未删除）
     *
     * @param array  $where   筛选条件：category_id / keyword / status / tag_id / merchant_id
     * @param int    $page    页码
     * @param int    $limit   每页条数
     * @param string $orderBy 排序
     * @return array{total:int, page:int, limit:int, list:array}
     */
    public static function getList(array $where = [], int $page = 1, int $limit = 20, string $orderBy = 'a.is_top DESC, a.sort ASC, a.id DESC'): array
    {
        $prefix = Database::prefix();
        $conditions = ['a.deleted_at IS NULL'];
        $params = [];

        // merchant_id 总是要传（即使是 0），调用方明确指定 scope
        if (array_key_exists('merchant_id', $where)) {
            $conditions[] = 'a.merchant_id = ?';
            $params[] = (int) $where['merchant_id'];
        }

        if (isset($where['status']) && $where['status'] !== '') {
            $conditions[] = 'a.status = ?';
            $params[] = (int) $where['status'];
        }

        if (!empty($where['category_id'])) {
            $conditions[] = 'a.category_id = ?';
            $params[] = (int) $where['category_id'];
        }

        if (!empty($where['keyword'])) {
            $conditions[] = '(a.title LIKE ? OR a.excerpt LIKE ?)';
            $kw = '%' . $where['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
        }

        // 标签筛选
        $joinTag = '';
        if (!empty($where['tag_id'])) {
            $joinTag = "INNER JOIN {$prefix}blog_tag_relation btr ON a.id = btr.blog_id AND btr.tag_id = ?";
            $params[] = (int) $where['tag_id'];
        }

        $whereSql = 'WHERE ' . implode(' AND ', $conditions);

        $total = Database::query(
            "SELECT COUNT(*) as count FROM {$prefix}blog a {$joinTag} {$whereSql}",
            $params
        );
        $totalCount = (int) ($total[0]['count'] ?? 0);

        $offset = ($page - 1) * $limit;
        $sql = "SELECT a.*, c.name as category_name,
                       COALESCE(u.nickname, u.username, '管理员') as author
                FROM {$prefix}blog a
                {$joinTag}
                LEFT JOIN {$prefix}blog_category c ON a.category_id = c.id
                LEFT JOIN {$prefix}user u ON a.user_id = u.id
                {$whereSql}
                ORDER BY {$orderBy}
                LIMIT {$offset}, {$limit}";
        $list = Database::query($sql, $params);

        return [
            'total' => $totalCount,
            'page'  => $page,
            'limit' => $limit,
            'list'  => $list,
        ];
    }

    /**
     * 获取文章详情（含分类名）—— 不带 merchant_id ACL，调用方负责
     */
    public static function getById(int $id): ?array
    {
        $prefix = Database::prefix();
        $result = Database::query(
            "SELECT a.*, c.name as category_name,
                    COALESCE(u.nickname, u.username, '管理员') as author
             FROM {$prefix}blog a
             LEFT JOIN {$prefix}blog_category c ON a.category_id = c.id
             LEFT JOIN {$prefix}user u ON a.user_id = u.id
             WHERE a.id = ? LIMIT 1",
            [$id]
        );
        return $result[0] ?? null;
    }

    /**
     * 获取文章详情（带 merchant_id ACL，前台用）
     */
    public static function getByIdForScope(int $id, int $merchantId): ?array
    {
        $prefix = Database::prefix();
        $result = Database::query(
            "SELECT a.*, c.name as category_name,
                    COALESCE(u.nickname, u.username, '管理员') as author
             FROM {$prefix}blog a
             LEFT JOIN {$prefix}blog_category c ON a.category_id = c.id
             LEFT JOIN {$prefix}user u ON a.user_id = u.id
             WHERE a.id = ? AND a.merchant_id = ? AND a.deleted_at IS NULL LIMIT 1",
            [$id, $merchantId]
        );
        return $result[0] ?? null;
    }

    /**
     * 获取热门文章（按浏览量排序）
     *
     * @return array<array{id:int, title:string}>
     */
    public static function getPopular(int $limit = 5, int $merchantId = 0): array
    {
        $prefix = Database::prefix();
        return Database::query(
            "SELECT id, title, views_count FROM {$prefix}blog
             WHERE status = 1 AND deleted_at IS NULL AND merchant_id = ?
             ORDER BY views_count DESC, id DESC
             LIMIT ?",
            [$merchantId, $limit]
        );
    }

    /**
     * 获取上一篇/下一篇文章 ID（在同一 merchant_id 范围内查找）
     *
     * @return array{prev_id:int|null, next_id:int|null}
     */
    public static function getPrevNextId(int $id, int $merchantId = 0): array
    {
        $prefix = Database::prefix();

        $prev = Database::fetchOne(
            "SELECT id, title FROM {$prefix}blog
             WHERE id < ? AND status = 1 AND deleted_at IS NULL AND merchant_id = ?
             ORDER BY id DESC LIMIT 1",
            [$id, $merchantId]
        );
        $next = Database::fetchOne(
            "SELECT id, title FROM {$prefix}blog
             WHERE id > ? AND status = 1 AND deleted_at IS NULL AND merchant_id = ?
             ORDER BY id ASC LIMIT 1",
            [$id, $merchantId]
        );

        return [
            'prev_id'    => $prev ? (int) $prev['id'] : null,
            'prev_title' => $prev['title'] ?? null,
            'next_id'    => $next ? (int) $next['id'] : null,
            'next_title' => $next['title'] ?? null,
        ];
    }

    /**
     * 递增文章浏览量
     */
    public static function incrementViews(int $id): void
    {
        $prefix = Database::prefix();
        Database::execute(
            "UPDATE {$prefix}blog SET views_count = views_count + 1 WHERE id = ?",
            [$id]
        );
    }

    /**
     * 创建文章。$data 必须包含 merchant_id（调用方决定 0 还是商户 ID）。
     *
     * @return int 新文章 ID
     */
    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Database::insert('blog', $data);
    }

    /**
     * 更新文章。merchant_id 不应通过 update 修改（调用方禁止传入）。
     */
    public static function update(int $id, array $data): bool
    {
        unset($data['merchant_id']); // 防越权改归属
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Database::update('blog', $data, $id) !== false;
    }

    /**
     * 逻辑删除文章
     */
    public static function delete(int $id): bool
    {
        return Database::update('blog', [
            'status'     => 0,
            'deleted_at' => date('Y-m-d H:i:s'),
        ], $id) !== false;
    }

    /**
     * 物理删除文章
     */
    public static function forceDelete(int $id): bool
    {
        $prefix = Database::prefix();
        return Database::execute("DELETE FROM {$prefix}blog WHERE id = ?", [$id]) > 0;
    }
}
