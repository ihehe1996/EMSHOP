<?php

declare(strict_types=1);

/**
 * 导航数据模型。
 *
 * 支持2级导航：顶级(parent_id=0) 和 二级(parent_id>0)。
 * 类型：system(系统导航) / custom(自定义) / goods_cat(商品分类) / blog_cat(博客分类)
 *
 * 多租户：
 *   is_system=1 的"系统导航"全站共享（merchant_id=0，主站和所有商户站都会看到）；
 *   is_system=0 的"自定义导航"按 merchant_id 隔离（0=主站自定义；>0=商户自定义）。
 */
final class NaviModel
{
    private string $table;
    private string $hiddenTable;

    public function __construct()
    {
        $this->table = Database::prefix() . 'navi';
        $this->hiddenTable = Database::prefix() . 'merchant_navi_hidden';
    }

    /**
     * 后台列表：按 merchant_id 过滤（仅展示该 scope 下的导航 + 系统导航）。
     * 当 $merchantId > 0 时，对系统导航行附加 is_hidden_for_me 字段，便于商户后台显示"已隐藏"标签。
     */
    public function getAll(int $merchantId = 0): array
    {
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `is_system` = 1 OR `merchant_id` = ? ORDER BY `sort` ASC, `id` ASC',
            $this->table
        );
        $rows = Database::query($sql, [$merchantId]);

        // 仅商户视角需要标"我隐藏了哪条系统导航"
        $hidden = $merchantId > 0 ? array_flip($this->getHiddenSystemIds($merchantId)) : [];
        foreach ($rows as &$r) {
            $r['is_hidden_for_me'] = ($merchantId > 0 && (int) $r['is_system'] === 1 && isset($hidden[(int) $r['id']]))
                ? 1 : 0;
        }
        unset($r);

        return $rows;
    }

    /**
     * 前台：当前租户能看到的所有已启用导航树。
     * 系统导航全部展示；自定义导航必须 merchant_id 匹配；商户额外排除自己隐藏的系统导航。
     *
     * @return array 顶级导航数组，每项含 children 子数组
     */
    public function getEnabledTree(int $merchantId = 0): array
    {
        // 商户站排除"自己隐藏的系统导航"
        $hiddenIds = $merchantId > 0 ? $this->getHiddenSystemIds($merchantId) : [];

        $sql = 'SELECT * FROM `' . $this->table . '`
                WHERE `status` = 1 AND (`is_system` = 1 OR `merchant_id` = ?)';
        $params = [$merchantId];
        if (!empty($hiddenIds)) {
            $placeholders = implode(',', array_fill(0, count($hiddenIds), '?'));
            $sql .= ' AND `id` NOT IN (' . $placeholders . ')';
            $params = array_merge($params, $hiddenIds);
        }
        $sql .= ' ORDER BY `sort` ASC, `id` ASC';

        $rows = Database::query($sql, $params);

        $top = [];
        $childMap = [];
        foreach ($rows as $row) {
            $row['children'] = [];
            if ((int) $row['parent_id'] === 0) {
                $top[$row['id']] = $row;
            } else {
                $childMap[(int) $row['parent_id']][] = $row;
            }
        }

        foreach ($top as &$item) {
            if (isset($childMap[$item['id']])) {
                $item['children'] = $childMap[$item['id']];
            }
        }
        unset($item);

        return array_values($top);
    }

    /**
     * 顶级导航（用于"父级导航"下拉框等）。
     */
    public function getTopLevel(int $merchantId = 0): array
    {
        $sql = sprintf(
            'SELECT * FROM `%s`
             WHERE `parent_id` = 0 AND (`is_system` = 1 OR `merchant_id` = ?)
             ORDER BY `sort` ASC, `id` ASC',
            $this->table
        );
        return Database::query($sql, [$merchantId]);
    }

    /**
     * 按 ID 获取单条。Controller 自行做 merchant_id ACL。
     */
    public function findById(int $id): ?array
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE `id` = ? LIMIT 1', $this->table);
        return Database::fetchOne($sql, [$id]);
    }

    /**
     * 创建导航。$data 必须包含 merchant_id（is_system=1 时由调用方强制写 0）。
     */
    public function create(array $data): int
    {
        $fields = ['parent_id', 'merchant_id', 'name', 'type', 'type_ref_id', 'link', 'icon', 'target', 'sort', 'status', 'is_system'];

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
     * 更新导航。merchant_id 不在白名单，迁移用例外（不允许通过普通 update 改归属）。
     */
    public function update(int $id, array $data): bool
    {
        $fields = ['parent_id', 'name', 'type', 'type_ref_id', 'link', 'icon', 'target', 'sort', 'status'];

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

        $sql = sprintf('UPDATE `%s` SET %s WHERE `id` = ? LIMIT 1', $this->table, implode(', ', $sets));
        return Database::execute($sql, $params) > 0;
    }

    /**
     * 删除导航（系统导航不可删除）。Controller 应先做 merchant_id ACL。
     */
    public function delete(int $id): bool
    {
        $sql = sprintf('DELETE FROM `%s` WHERE `id` = ? AND `is_system` = 0 LIMIT 1', $this->table);
        return Database::execute($sql, [$id]) > 0;
    }

    /**
     * 检查是否有子导航。
     */
    public function hasChildren(int $parentId): bool
    {
        $sql = sprintf('SELECT COUNT(*) AS cnt FROM `%s` WHERE `parent_id` = ?', $this->table);
        $row = Database::fetchOne($sql, [$parentId]);
        return $row !== null && (int) $row['cnt'] > 0;
    }

    /**
     * 批量更新排序。Controller 传入的 ID 必须已经做过归属校验。
     *
     * @param array $sortData [[id => sort], ...]
     */
    public function batchUpdateSort(array $sortData): void
    {
        $sql = sprintf('UPDATE `%s` SET `sort` = ?, `updated_at` = NOW() WHERE `id` = ? LIMIT 1', $this->table);
        foreach ($sortData as $id => $sort) {
            Database::execute($sql, [(int) $sort, (int) $id]);
        }
    }

    /**
     * 当前 scope 的导航总数（含系统导航）。
     */
    public function count(int $merchantId = 0): int
    {
        $sql = sprintf(
            'SELECT COUNT(*) AS cnt FROM `%s` WHERE `is_system` = 1 OR `merchant_id` = ?',
            $this->table
        );
        $row = Database::fetchOne($sql, [$merchantId]);
        return $row !== null ? (int) $row['cnt'] : 0;
    }

    // ============================================================
    // 商户隐藏系统导航（em_merchant_navi_hidden）
    // ============================================================

    /**
     * 取该商户隐藏掉的系统导航 ID 列表。
     *
     * @return array<int>
     */
    public function getHiddenSystemIds(int $merchantId): array
    {
        if ($merchantId <= 0) return [];
        $rows = Database::query(
            'SELECT `navi_id` FROM `' . $this->hiddenTable . '` WHERE `merchant_id` = ?',
            [$merchantId]
        );
        return array_map(static fn($r) => (int) $r['navi_id'], $rows);
    }

    /**
     * 切换"商户隐藏某条系统导航"。
     *
     * @return int 切换后的状态：1=已隐藏，0=已显示
     */
    public function toggleHideSystem(int $merchantId, int $naviId): int
    {
        // 仅允许针对系统导航
        $navi = $this->findById($naviId);
        if ($navi === null || (int) $navi['is_system'] !== 1) {
            throw new RuntimeException('仅可隐藏系统导航');
        }

        $existing = Database::fetchOne(
            'SELECT 1 FROM `' . $this->hiddenTable . '` WHERE `merchant_id` = ? AND `navi_id` = ? LIMIT 1',
            [$merchantId, $naviId]
        );
        if ($existing) {
            Database::execute(
                'DELETE FROM `' . $this->hiddenTable . '` WHERE `merchant_id` = ? AND `navi_id` = ?',
                [$merchantId, $naviId]
            );
            return 0;
        }
        Database::insert('merchant_navi_hidden', [
            'merchant_id' => $merchantId,
            'navi_id' => $naviId,
        ]);
        return 1;
    }
}
