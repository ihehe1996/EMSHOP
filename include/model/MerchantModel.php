<?php

declare(strict_types=1);

/**
 * 商户（分站）主数据模型。
 *
 * 店铺余额（shop_balance）不落在本表，而是在 em_user.shop_balance ——
 * 这样商户主、普通用户共用一张用户表，余额字段自然共享账务视图。
 */
final class MerchantModel
{
    /** @var string 带前缀完整表名 */
    private string $table;

    public function __construct()
    {
        $this->table = Database::prefix() . 'merchant';
    }

    /**
     * 列表（联查 level / 商户主账号）。
     *
     * @param array{keyword?:string,status?:int|string,level_id?:int,page?:int,page_size?:int} $filter
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function paginate(array $filter): array
    {
        $page = max(1, (int) ($filter['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($filter['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        [$where, $params] = $this->buildWhere($filter);

        $userTable = Database::prefix() . 'user';
        $levelTable = Database::prefix() . 'merchant_level';

        $countSql = 'SELECT COUNT(*) AS c FROM `' . $this->table . '` m ' . $where;
        $total = (int) (Database::fetchOne($countSql, $params)['c'] ?? 0);

        $sql = 'SELECT m.*,
                       u.username AS user_username,
                       u.nickname AS user_nickname,
                       u.shop_balance AS user_shop_balance,
                       l.name AS level_name,
                       p.name AS parent_name
                  FROM `' . $this->table . '` m
             LEFT JOIN `' . $userTable . '` u ON u.id = m.user_id
             LEFT JOIN `' . $levelTable . '` l ON l.id = m.level_id
             LEFT JOIN `' . $this->table . '` p ON p.id = m.parent_id
                ' . $where . '
                 ORDER BY m.created_at DESC
                 LIMIT ' . $pageSize . ' OFFSET ' . $offset;
        $rows = Database::query($sql, $params);
        return ['data' => $rows, 'total' => $total];
    }

    /**
     * 按 id 查商户（联查 level / 商户主）。
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $userTable = Database::prefix() . 'user';
        $levelTable = Database::prefix() . 'merchant_level';
        $sql = 'SELECT m.*,
                       u.username AS user_username,
                       u.nickname AS user_nickname,
                       u.email AS user_email,
                       l.name AS level_name,
                       p.name AS parent_name
                  FROM `' . $this->table . '` m
             LEFT JOIN `' . $userTable . '` u ON u.id = m.user_id
             LEFT JOIN `' . $levelTable . '` l ON l.id = m.level_id
             LEFT JOIN `' . $this->table . '` p ON p.id = m.parent_id
                 WHERE m.id = ? AND m.deleted_at IS NULL
                 LIMIT 1';
        return Database::fetchOne($sql, [$id]);
    }

    /**
     * 按 user_id 查商户（用于判断某用户是否已开店）。
     *
     * @return array<string, mixed>|null
     */
    public function findByUserId(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        $sql = 'SELECT * FROM `' . $this->table . '`
                 WHERE `user_id` = ? AND `deleted_at` IS NULL LIMIT 1';
        return Database::fetchOne($sql, [$userId]);
    }

    /**
     * 二级域名唯一性校验。
     */
    public function existsSubdomain(string $sub, int $excludeId = 0): bool
    {
        return $this->fieldExists('subdomain', $sub, $excludeId);
    }

    /**
     * 自定义顶级域名唯一性校验。
     */
    public function existsCustomDomain(string $domain, int $excludeId = 0): bool
    {
        return $this->fieldExists('custom_domain', $domain, $excludeId);
    }

    /**
     * 手动 / 自助开通：新建一条商户。
     * 同时写入 em_user.merchant_id（关联绑定）。
     *
     * @param array<string, mixed> $data
     */
    public function openMerchant(array $data): int
    {
        $userId = (int) ($data['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new InvalidArgumentException('缺少 user_id');
        }
        if ($this->findByUserId($userId) !== null) {
            throw new InvalidArgumentException('该用户已开通商户');
        }

        $insertData = $this->pickFields($data);
        $insertData['user_id'] = $userId;
        $insertData['opened_at'] = $insertData['opened_at'] ?? date('Y-m-d H:i:s');
        $insertData['opened_via'] = $insertData['opened_via'] ?? 'admin';
        $insertData['status'] = $insertData['status'] ?? 1;

        Database::begin();
        try {
            $merchantId = Database::insert('merchant', $insertData);
            // 反向绑定到 em_user
            Database::update('user', ['merchant_id' => $merchantId], $userId);
            Database::commit();
            return $merchantId;
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * 编辑商户（user_id 不可改）。
     *
     * @param array<string, mixed> $data
     */
    public function updateMerchant(int $id, array $data): bool
    {
        if ($id <= 0) {
            return false;
        }
        $update = $this->pickFields($data);
        unset($update['user_id']); // 不允许换商户主
        if ($update === []) {
            return false;
        }
        return Database::update('merchant', $update, $id) > 0;
    }

    /**
     * 启禁商户。
     */
    public function setStatus(int $id, int $status): bool
    {
        if ($id <= 0) {
            return false;
        }
        return Database::update('merchant', ['status' => $status === 1 ? 1 : 0], $id) > 0;
    }

    /**
     * 软删除商户。
     *  - 反向解绑 em_user.merchant_id = 0
     *  - 下级 parent_id 置 0（一层返佣关系不继承）
     */
    public function softDelete(int $id): bool
    {
        $m = $this->findById($id);
        if ($m === null) {
            return false;
        }
        Database::begin();
        try {
            Database::update('merchant', ['deleted_at' => date('Y-m-d H:i:s')], $id);
            Database::update('user', ['merchant_id' => 0], (int) $m['user_id']);
            Database::execute(
                'UPDATE `' . $this->table . '` SET `parent_id` = 0 WHERE `parent_id` = ?',
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
     * 字段唯一性通用校验（排除软删）。
     */
    private function fieldExists(string $field, ?string $value, int $excludeId = 0): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        if ($excludeId > 0) {
            $sql = 'SELECT `id` FROM `' . $this->table . '`
                     WHERE `' . $field . '` = ? AND `id` != ? AND `deleted_at` IS NULL LIMIT 1';
            $row = Database::fetchOne($sql, [$value, $excludeId]);
        } else {
            $sql = 'SELECT `id` FROM `' . $this->table . '`
                     WHERE `' . $field . '` = ? AND `deleted_at` IS NULL LIMIT 1';
            $row = Database::fetchOne($sql, [$value]);
        }
        return $row !== null;
    }

    /**
     * 构造 WHERE（列表过滤）。
     *
     * @param array<string, mixed> $filter
     * @return array{0:string,1:array<int, mixed>}
     */
    private function buildWhere(array $filter): array
    {
        $conds = ['m.deleted_at IS NULL'];
        $params = [];

        $keyword = trim((string) ($filter['keyword'] ?? ''));
        if ($keyword !== '') {
            $conds[] = 'm.name LIKE ?';
            $params[] = '%' . $keyword . '%';
        }
        if (isset($filter['status']) && $filter['status'] !== '') {
            $conds[] = 'm.status = ?';
            $params[] = (int) $filter['status'];
        }
        if (!empty($filter['level_id'])) {
            $conds[] = 'm.level_id = ?';
            $params[] = (int) $filter['level_id'];
        }
        return ['WHERE ' . implode(' AND ', $conds), $params];
    }

    /**
     * 挑出允许写入 em_merchant 的字段。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function pickFields(array $data): array
    {
        $allowed = [
            'user_id', 'parent_id', 'level_id', 'name', 'logo', 'slogan',
            'description', 'icp',
            'subdomain', 'custom_domain', 'domain_verified',
            'theme', 'status', 'opened_at', 'opened_via',
        ];
        $out = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $out[$f] = $data[$f];
            }
        }
        return $out;
    }
}
