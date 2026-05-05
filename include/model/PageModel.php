<?php

if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

/**
 * 自定义页面模型（WordPress 式 Pages）。
 *
 * 数据表：em_page
 * 多租户：merchant_id=0 主站；>0 商户。slug 在同 scope 内唯一（uk_merchant_slug）。
 *
 * @package EM\Core\Model
 */
class PageModel
{
    /**
     * 获取页面列表（后台管理：含草稿 / 软删除按条件过滤）
     *
     * @param array  $where   筛选：status / keyword / merchant_id
     */
    public static function getList(array $where = [], int $page = 1, int $limit = 20, string $orderBy = 'sort ASC, id DESC'): array
    {
        $prefix = Database::prefix();
        $conditions = ['deleted_at IS NULL'];
        $params = [];

        if (array_key_exists('merchant_id', $where)) {
            $conditions[] = 'merchant_id = ?';
            $params[] = (int) $where['merchant_id'];
        }

        if (isset($where['status']) && $where['status'] !== '') {
            $conditions[] = 'status = ?';
            $params[] = (int) $where['status'];
        }

        if (!empty($where['keyword'])) {
            $conditions[] = '(title LIKE ? OR slug LIKE ?)';
            $kw = '%' . $where['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $conditions);

        $total = Database::query("SELECT COUNT(*) as count FROM {$prefix}page {$whereSql}", $params);
        $totalCount = (int) ($total[0]['count'] ?? 0);

        $offset = ($page - 1) * $limit;
        $sql = "SELECT id, merchant_id, title, slug, status, is_homepage, template_name, sort, views_count, created_at, updated_at
                FROM {$prefix}page
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
     * 按 ID 获取页面（含 content）。Controller 自行做 merchant_id ACL。
     */
    public static function getById(int $id): ?array
    {
        $prefix = Database::prefix();
        $row = Database::fetchOne(
            "SELECT * FROM {$prefix}page WHERE id = ? AND deleted_at IS NULL",
            [$id]
        );
        return $row ?: null;
    }

    /**
     * 按 slug 获取"已发布"页面（限定 scope）—— 前台路由使用
     */
    public static function getBySlug(string $slug, int $merchantId = 0): ?array
    {
        $prefix = Database::prefix();
        $row = Database::fetchOne(
            "SELECT * FROM {$prefix}page
             WHERE slug = ? AND merchant_id = ? AND status = 1 AND deleted_at IS NULL LIMIT 1",
            [$slug, $merchantId]
        );
        return $row ?: null;
    }

    /**
     * slug 在指定 scope 内是否被占用（排除自身）
     */
    public static function slugExists(string $slug, int $excludeId = 0, int $merchantId = 0): bool
    {
        $prefix = Database::prefix();
        if ($excludeId > 0) {
            $row = Database::fetchOne(
                "SELECT id FROM {$prefix}page
                 WHERE slug = ? AND merchant_id = ? AND id != ? AND deleted_at IS NULL LIMIT 1",
                [$slug, $merchantId, $excludeId]
            );
        } else {
            $row = Database::fetchOne(
                "SELECT id FROM {$prefix}page
                 WHERE slug = ? AND merchant_id = ? AND deleted_at IS NULL LIMIT 1",
                [$slug, $merchantId]
            );
        }
        return $row !== null;
    }

    /**
     * 创建页面。$data 必须包含 merchant_id（调用方决定 0 还是商户 ID）。
     */
    public static function create(array $data): int
    {
        $fields = ['merchant_id', 'title', 'slug', 'content', 'status', 'template_name',
                   'seo_title', 'seo_keywords', 'seo_description', 'sort'];
        $insert = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $insert[$f] = $data[$f];
            }
        }
        if (!array_key_exists('merchant_id', $insert)) {
            $insert['merchant_id'] = 0;
        }
        $id = Database::insert('page', $insert);
        return (int) $id;
    }

    /**
     * 更新页面。merchant_id 不在白名单（不允许通过 update 改归属）。
     */
    public static function update(int $id, array $data): bool
    {
        $fields = ['title', 'slug', 'content', 'status', 'template_name',
                   'seo_title', 'seo_keywords', 'seo_description', 'sort'];
        $sets = [];
        $params = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "`{$f}` = ?";
                $params[] = $data[$f];
            }
        }
        if ($sets === []) {
            return false;
        }
        $params[] = $id;
        $prefix = Database::prefix();
        return Database::execute(
            "UPDATE {$prefix}page SET " . implode(', ', $sets) . " WHERE id = ?",
            $params
        ) > 0;
    }

    /**
     * 软删除页面
     */
    public static function delete(int $id): bool
    {
        $prefix = Database::prefix();
        return Database::execute(
            "UPDATE {$prefix}page SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL",
            [$id]
        ) > 0;
    }

    /**
     * 浏览量 +1（前台访问时调用）
     */
    public static function incrementViews(int $id): void
    {
        $prefix = Database::prefix();
        Database::execute(
            "UPDATE {$prefix}page SET views_count = views_count + 1 WHERE id = ?",
            [$id]
        );
    }

    /**
     * 获取指定 scope 下所有已发布页面的简要列表 —— 供导航管理选择页面时用
     *
     * @return array<int, array{id:int, title:string, slug:string}>
     */
    public static function getPublishedSimple(int $merchantId = 0): array
    {
        $prefix = Database::prefix();
        return Database::query(
            "SELECT id, title, slug FROM {$prefix}page
             WHERE status = 1 AND merchant_id = ? AND deleted_at IS NULL
             ORDER BY sort ASC, id DESC",
            [$merchantId]
        );
    }

    // ============================================================
    // 站点首页（页面首页）—— 同 scope 内最多一条 is_homepage=1
    // ============================================================

    /**
     * 取该 scope 当前设为站点首页的页面（必须已发布且未删除）。
     * Dispatcher 在 / 入口处用它判断是否走 PageController。
     *
     * @return array<string,mixed>|null 找不到返回 null
     */
    public static function getHomepage(int $merchantId): ?array
    {
        $prefix = Database::prefix();
        $row = Database::fetchOne(
            "SELECT * FROM {$prefix}page
             WHERE merchant_id = ? AND is_homepage = 1 AND status = 1 AND deleted_at IS NULL
             LIMIT 1",
            [$merchantId]
        );
        return $row ?: null;
    }

    /**
     * 把指定页设为该 scope 的站点首页：
     *   - 校验 page 存在且属于该 scope
     *   - 强制 status=1（首页必须已发布）
     *   - 同 scope 内其它页 is_homepage 清 0，再把目标页置 1（事务保护）
     *
     * @return bool 成功 true，找不到 page 或归属不符返回 false
     */
    public static function setHomepage(int $id, int $merchantId): bool
    {
        $prefix = Database::prefix();
        $page = Database::fetchOne(
            "SELECT id FROM {$prefix}page
             WHERE id = ? AND merchant_id = ? AND deleted_at IS NULL LIMIT 1",
            [$id, $merchantId]
        );
        if (!$page) return false;

        Database::begin();
        try {
            // 同 scope 其它页 is_homepage 清零
            Database::execute(
                "UPDATE {$prefix}page SET is_homepage = 0
                 WHERE merchant_id = ? AND is_homepage = 1 AND id != ?",
                [$merchantId, $id]
            );
            // 目标页 is_homepage = 1，同时强制发布状态
            Database::execute(
                "UPDATE {$prefix}page SET is_homepage = 1, status = 1 WHERE id = ?",
                [$id]
            );
            Database::commit();
            return true;
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * 取消该 scope 的站点首页（清零，回退到 settings 的 homepage_mode）。
     * 不指定 id 时清整个 scope；指定 id 时只清该页（用于"取消首页"按钮）。
     */
    public static function clearHomepage(int $merchantId, ?int $id = null): bool
    {
        $prefix = Database::prefix();
        if ($id !== null) {
            return Database::execute(
                "UPDATE {$prefix}page SET is_homepage = 0
                 WHERE id = ? AND merchant_id = ? AND is_homepage = 1",
                [$id, $merchantId]
            ) >= 0;
        }
        return Database::execute(
            "UPDATE {$prefix}page SET is_homepage = 0
             WHERE merchant_id = ? AND is_homepage = 1",
            [$merchantId]
        ) >= 0;
    }
}
