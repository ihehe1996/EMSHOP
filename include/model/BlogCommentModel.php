<?php

if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

/**
 * 博客评论模型
 *
 * 支持二级评论结构：顶级评论（parent_id=0）和回复（parent_id=顶级评论ID）。
 * 回复可以指定被回复人 reply_user_id，但 parent_id 始终指向顶级评论。
 */
class BlogCommentModel
{
    /**
     * 获取文章的顶级评论（分页），附带每条评论的回复数量和用户信息。
     *
     * @param int    $blogId  文章 ID
     * @param int    $page    页码
     * @param int    $limit   每页条数
     * @param string $sort    排序：newest / oldest / hot
     * @return array{total:int, page:int, limit:int, list:array}
     */
    public static function getTopComments(int $blogId, int $page = 1, int $limit = 10, string $sort = 'newest'): array
    {
        $prefix = Database::prefix();
        $params = [$blogId];

        // 统计顶级评论总数
        $total = Database::fetchOne(
            "SELECT COUNT(*) as count FROM {$prefix}blog_comment
             WHERE blog_id = ? AND parent_id = 0 AND status = 1 AND deleted_at IS NULL",
            $params
        );
        $totalCount = (int) ($total['count'] ?? 0);

        // 排序
        switch ($sort) {
            case 'oldest': $orderBy = 'c.created_at ASC'; break;
            case 'hot':    $orderBy = 'reply_count DESC, c.created_at DESC'; break;
            default:       $orderBy = 'c.created_at DESC'; break;
        }

        $offset = ($page - 1) * $limit;
        $sql = "SELECT c.*, u.nickname, u.username, u.avatar,
                       (SELECT COUNT(*) FROM {$prefix}blog_comment r
                        WHERE r.parent_id = c.id AND r.status = 1 AND r.deleted_at IS NULL) as reply_count
                FROM {$prefix}blog_comment c
                LEFT JOIN {$prefix}user u ON c.user_id = u.id
                WHERE c.blog_id = ? AND c.parent_id = 0 AND c.status = 1 AND c.deleted_at IS NULL
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
     * 获取某条顶级评论的回复列表（分页），附带用户信息和被回复用户信息。
     *
     * @param int $parentId 顶级评论 ID
     * @param int $page     页码
     * @param int $limit    每页条数
     * @return array{total:int, page:int, limit:int, list:array}
     */
    public static function getReplies(int $parentId, int $page = 1, int $limit = 5): array
    {
        $prefix = Database::prefix();

        $total = Database::fetchOne(
            "SELECT COUNT(*) as count FROM {$prefix}blog_comment
             WHERE parent_id = ? AND status = 1 AND deleted_at IS NULL",
            [$parentId]
        );
        $totalCount = (int) ($total['count'] ?? 0);

        $offset = ($page - 1) * $limit;
        $sql = "SELECT c.*, u.nickname, u.username, u.avatar,
                       ru.nickname as reply_nickname, ru.username as reply_username
                FROM {$prefix}blog_comment c
                LEFT JOIN {$prefix}user u ON c.user_id = u.id
                LEFT JOIN {$prefix}user ru ON c.reply_user_id = ru.id
                WHERE c.parent_id = ? AND c.status = 1 AND c.deleted_at IS NULL
                ORDER BY c.created_at ASC
                LIMIT {$offset}, {$limit}";
        $list = Database::query($sql, [$parentId]);

        return [
            'total' => $totalCount,
            'page'  => $page,
            'limit' => $limit,
            'list'  => $list,
        ];
    }

    /**
     * 获取文章的评论总数（已通过）
     */
    public static function getCountByBlog(int $blogId): int
    {
        $prefix = Database::prefix();
        $row = Database::fetchOne(
            "SELECT COUNT(*) as count FROM {$prefix}blog_comment
             WHERE blog_id = ? AND status = 1 AND deleted_at IS NULL",
            [$blogId]
        );
        return (int) ($row['count'] ?? 0);
    }

    /**
     * 创建评论
     *
     * @return int 新评论 ID
     */
    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Database::insert('blog_comment', $data);
    }

    /**
     * 根据 ID 获取评论
     */
    public static function getById(int $id): ?array
    {
        $prefix = Database::prefix();
        $row = Database::fetchOne(
            "SELECT * FROM {$prefix}blog_comment WHERE id = ? LIMIT 1",
            [$id]
        );
        return $row ?: null;
    }

    /**
     * 逻辑删除评论
     */
    public static function delete(int $id): bool
    {
        return Database::update('blog_comment', [
            'deleted_at' => date('Y-m-d H:i:s'),
        ], $id) !== false;
    }

    /**
     * 更新评论状态
     */
    public static function updateStatus(int $id, int $status): bool
    {
        return Database::update('blog_comment', [
            'status' => $status,
        ], $id) !== false;
    }

    /**
     * 批量更新状态
     */
    public static function batchUpdateStatus(array $ids, int $status): int
    {
        if (empty($ids)) return 0;
        $prefix = Database::prefix();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge($ids, [$status]);
        return Database::execute(
            "UPDATE {$prefix}blog_comment SET status = ? WHERE id IN ({$placeholders})",
            array_merge([$status], $ids)
        );
    }

    /**
     * 批量逻辑删除
     */
    public static function batchDelete(array $ids): int
    {
        if (empty($ids)) return 0;
        $prefix = Database::prefix();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $now = date('Y-m-d H:i:s');
        return Database::execute(
            "UPDATE {$prefix}blog_comment SET deleted_at = ? WHERE id IN ({$placeholders})",
            array_merge([$now], $ids)
        );
    }

    /**
     * 后台评论列表（分页 + 筛选）。评论按其归属博客的 merchant_id 隔离。
     *
     * @param array  $where  筛选条件：blog_id / status / keyword / merchant_id
     * @param int    $page   页码
     * @param int    $limit  每页条数
     * @return array{total:int, page:int, limit:int, list:array}
     */
    public static function getAdminList(array $where = [], int $page = 1, int $limit = 20): array
    {
        $prefix = Database::prefix();
        $conditions = ['c.deleted_at IS NULL'];
        $params = [];

        // merchant_id 过滤：通过 blog 表 JOIN 实现
        if (array_key_exists('merchant_id', $where)) {
            $conditions[] = 'b.merchant_id = ?';
            $params[] = (int) $where['merchant_id'];
        }
        if (isset($where['status']) && $where['status'] !== '') {
            $conditions[] = 'c.status = ?';
            $params[] = (int) $where['status'];
        }
        if (!empty($where['blog_id'])) {
            $conditions[] = 'c.blog_id = ?';
            $params[] = (int) $where['blog_id'];
        }
        if (!empty($where['keyword'])) {
            $conditions[] = 'c.content LIKE ?';
            $params[] = '%' . $where['keyword'] . '%';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $conditions);

        $total = Database::fetchOne(
            "SELECT COUNT(*) as count
             FROM {$prefix}blog_comment c
             LEFT JOIN {$prefix}blog b ON c.blog_id = b.id
             {$whereSql}",
            $params
        );
        $totalCount = (int) ($total['count'] ?? 0);

        $offset = ($page - 1) * $limit;
        $sql = "SELECT c.*, u.nickname, u.username, u.avatar,
                       b.title as blog_title
                FROM {$prefix}blog_comment c
                LEFT JOIN {$prefix}user u ON c.user_id = u.id
                LEFT JOIN {$prefix}blog b ON c.blog_id = b.id
                {$whereSql}
                ORDER BY c.id DESC
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
     * 按状态统计评论数量（限定 merchant_id 范围）
     *
     * @return array{all:int, pending:int, approved:int, rejected:int}
     */
    public static function getStatusCounts(int $merchantId = 0): array
    {
        $prefix = Database::prefix();
        $rows = Database::query(
            "SELECT c.status, COUNT(*) as cnt
             FROM {$prefix}blog_comment c
             LEFT JOIN {$prefix}blog b ON c.blog_id = b.id
             WHERE c.deleted_at IS NULL AND b.merchant_id = ?
             GROUP BY c.status",
            [$merchantId]
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['status']] = (int) $r['cnt'];
        }
        return [
            'all'      => array_sum($map),
            'pending'  => $map[0] ?? 0,
            'approved' => $map[1] ?? 0,
            'rejected' => $map[2] ?? 0,
        ];
    }
}
