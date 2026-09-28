<?php

declare(strict_types=1);

/**
 * 用户列表数据模型。
 *
 * 数据来源：em_user 表。本模型**同时服务前台与后台**，且不再按 role 区分：
 * 管理账号（role='admin'）也能在前台登录并按普通用户使用前台功能，因此
 * 读写要覆盖两种 role。这是刻意的——前台走的就是这个模型（findById/update）。
 *
 * 例外：create() 强制写入 role='user'，后台新增用户不会造出管理员账号。
 * 破坏性方法（toggleStatus/delete/deleteBatch）用 $protectId 兜住「不能删/禁自己」。
 */
final class UserListModel
{
    private string $table;

    /** @var bool|null */
    private static $hasExperienceFields = null;

    public function __construct()
    {
        $this->table = Database::prefix() . 'user';
    }

    /**
     * 列表查询用：未迁移时回退为 0，避免 Unknown column。
     */
    private function userStatsSelectSql(): string
    {
        if (self::$hasExperienceFields === null) {
            self::$hasExperienceFields = Database::columnExists($this->table, 'total_consumption')
                && Database::columnExists($this->table, 'experience');
        }
        if (self::$hasExperienceFields) {
            return 'u.`total_consumption`, u.`experience`,';
        }
        return '0 AS `total_consumption`, 0 AS `experience`,';
    }

    /**
     * 获取所有用户，支持分页、关键词搜索与服务端排序。
     *
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function getAll(int $page, int $limit, string $keyword = '', string $sortField = 'id', string $sortOrder = 'desc'): array
    {
        $offset = ($page - 1) * $limit;

        // 管理员账号也会被列出（后台用户管理要能管理其它管理账号）。
        // 注意这里必须是恒真表达式而不是空串：下面用 sprintf('... WHERE %s ...') 拼 SQL，
        // 空串会拼出 `WHERE ` 直接语法错误、列表整页空白。
        $where = '1';
        $params = [];

        if ($keyword !== '') {
            $where .= ' AND (u.`username` LIKE ? OR u.`nickname` LIKE ? OR u.`email` LIKE ? OR u.`mobile` LIKE ?)';
            $params[] = '%' . $keyword . '%';
            $params[] = '%' . $keyword . '%';
            $params[] = '%' . $keyword . '%';
            $params[] = '%' . $keyword . '%';
        }

        // 计数（只查用户表，无需 JOIN）
        $countSql = sprintf(
            'SELECT COUNT(*) AS cnt FROM `%s` u WHERE %s',
            $this->table,
            $where
        );
        $countRow = Database::fetchOne($countSql, $params);
        $total = $countRow !== null ? (int) $countRow['cnt'] : 0;

        $orderBy = $this->buildListOrderBy($sortField, $sortOrder);

        // 数据（左连商户 + 商户等级 + 用户等级；用户等级影响买家折扣）
        $merchantTable = Database::prefix() . 'merchant';
        $merchantLevelTable = Database::prefix() . 'merchant_level';
        $userLevelTable = Database::prefix() . 'user_levels';
        $sql = sprintf(
            'SELECT u.`id`, u.`username`, u.`email`, u.`mobile`, u.`nickname`, u.`avatar`, u.`money`,
                    %s
                    u.`role`, u.`status`, u.`level_id`, u.`last_login_ip`, u.`last_login_at`, u.`created_at`,
                    m.`id` AS merchant_id, m.`name` AS merchant_name,
                    ml.`name` AS merchant_level_name,
                    ul.`name` AS user_level_name
             FROM `%s` u
             LEFT JOIN `%s` m ON m.`user_id` = u.`id` AND m.`deleted_at` IS NULL
             LEFT JOIN `%s` ml ON ml.`id` = m.`level_id`
             LEFT JOIN `%s` ul ON ul.`id` = u.`level_id` AND ul.`enabled` = \'y\'
             WHERE %s
             ORDER BY %s
             LIMIT %d OFFSET %d',
            $this->userStatsSelectSql(),
            $this->table,
            $merchantTable,
            $merchantLevelTable,
            $userLevelTable,
            $where,
            $orderBy,
            $limit,
            $offset
        );

        $rows = Database::query($sql, $params);

        return ['data' => $rows, 'total' => $total];
    }

    /**
     * 列表排序白名单（仅允许 u 表字段，防 SQL 注入）。
     */
    private function buildListOrderBy(string $field, string $order): string
    {
        $dir = strtolower($order) === 'asc' ? 'ASC' : 'DESC';
        $hasStats = self::$hasExperienceFields;
        if ($hasStats === null) {
            $hasStats = Database::columnExists($this->table, 'total_consumption')
                && Database::columnExists($this->table, 'experience');
            self::$hasExperienceFields = $hasStats;
        }

        $allowed = [
            'id' => 'u.`id`',
            'total_consumption' => $hasStats ? 'u.`total_consumption`' : '0',
            'experience' => $hasStats ? 'u.`experience`' : '0',
            'created_at' => 'u.`created_at`',
        ];

        if (!isset($allowed[$field])) {
            $field = 'id';
        }

        $col = $allowed[$field];
        if ($field !== 'id') {
            return sprintf('%s %s, u.`id` DESC', $col, $dir);
        }

        return sprintf('%s %s', $col, $dir);
    }

    /**
     * 按 ID 获取单条用户。
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = sprintf(
            'SELECT `id`, `username`, `email`, `mobile`, `nickname`, `avatar`, `money`, `secret`, `role`, `status`,
                    `merchant_id`, `shop_balance`, `level_id`,
                    `last_login_ip`, `last_login_at`, `created_at`
             FROM `%s`
             WHERE `id` = ? LIMIT 1',
            $this->table
        );

        return Database::fetchOne($sql, [$id]);
    }

    /**
     * 检查用户名是否已被占用。
     */
    public function existsUsername(string $username, int $excludeId = 0): bool
    {
        if ($excludeId > 0) {
            $sql = sprintf(
                'SELECT `id` FROM `%s` WHERE `username` = ? AND `id` != ? LIMIT 1',
                $this->table
            );
            $row = Database::fetchOne($sql, [$username, $excludeId]);
        } else {
            $sql = sprintf(
                'SELECT `id` FROM `%s` WHERE `username` = ? LIMIT 1',
                $this->table
            );
            $row = Database::fetchOne($sql, [$username]);
        }
        return $row !== null;
    }

    /**
     * 检查邮箱是否已被占用（排除自身）。
     *
     * 刻意**不按 role 过滤**：登录、找回密码都支持用邮箱作为账号，若允许普通用户
     * 占用管理员邮箱，就会在 em_user 里造出两行同邮箱，找回密码按邮箱取行时可能
     * 取中另一行（令牌绑到别的账号上，站长表现为「重置密码永远失败」）。
     * 管理员自己改资料走 UserModel::isEmailTaken，那边本来就不区分 role。
     */
    public function existsEmail(string $email, int $excludeId = 0): bool
    {
        if ($email === '') {
            return false;
        }
        if ($excludeId > 0) {
            $sql = sprintf(
                'SELECT `id` FROM `%s` WHERE `email` = ? AND `id` != ? LIMIT 1',
                $this->table
            );
            $row = Database::fetchOne($sql, [$email, $excludeId]);
        } else {
            $sql = sprintf(
                'SELECT `id` FROM `%s` WHERE `email` = ? LIMIT 1',
                $this->table
            );
            $row = Database::fetchOne($sql, [$email]);
        }
        return $row !== null;
    }

    /**
     * 检查手机号是否已被占用（排除自身）。
     *
     * 与 existsEmail 同理，不按 role 过滤。
     */
    public function existsMobile(string $mobile, int $excludeId = 0): bool
    {
        if ($mobile === '') {
            return false;
        }
        if ($excludeId > 0) {
            $sql = sprintf(
                'SELECT `id` FROM `%s` WHERE `mobile` = ? AND `id` != ? LIMIT 1',
                $this->table
            );
            $row = Database::fetchOne($sql, [$mobile, $excludeId]);
        } else {
            $sql = sprintf(
                'SELECT `id` FROM `%s` WHERE `mobile` = ? LIMIT 1',
                $this->table
            );
            $row = Database::fetchOne($sql, [$mobile]);
        }
        return $row !== null;
    }

    /**
     * 更新用户资料。
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $fields = ['nickname', 'email', 'mobile', 'avatar', 'status', 'secret', 'level_id', 'password'];

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

        // 改密码时必须同时吊销「记住我」令牌。
        // 否则旧令牌在库里一直有效（有效期可达 remember_days_checked 天），
        // 攻击者只要拿到过这个 cookie，改密之后依然能登进来 ——
        // 而「改密码」正是用户发现账号被盗后的第一反应。
        // 这里是改密的收口：找回密码（PasswordResetService）与后台改用户密码都走它。
        if (array_key_exists('password', $data)) {
            $sets[] = '`remember_token` = NULL';
        }

        $sets[] = '`updated_at` = NOW()';
        $params[] = $id;

        // 不按 role 过滤：前台改资料 / 找回密码对管理账号同样要生效（同一行数据，前后台同步）。
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `id` = ? LIMIT 1',
            $this->table,
            implode(', ', $sets)
        );

        return Database::execute($sql, $params) > 0;
    }

    /**
     * 切换用户状态（可禁用管理账号）。
     *
     * @param int $protectId 传入当前登录管理员的 id，则该行不会被改动（防自锁）
     */
    public function toggleStatus(int $id, int $protectId = 0): bool
    {
        $sql = sprintf(
            'UPDATE `%s` SET `status` = IF(`status` = 1, 0, 1), `updated_at` = NOW() WHERE `id` = ?%s LIMIT 1',
            $this->table,
            $protectId > 0 ? ' AND `id` != ?' : ''
        );
        $params = $protectId > 0 ? [$id, $protectId] : [$id];

        return Database::execute($sql, $params) > 0;
    }

    /**
     * 删除用户（可删除管理账号）。
     *
     * @param int $protectId 传入当前登录管理员的 id，则该行不会被删除（防自锁）
     */
    public function delete(int $id, int $protectId = 0): bool
    {
        $sql = sprintf(
            'DELETE FROM `%s` WHERE `id` = ?%s LIMIT 1',
            $this->table,
            $protectId > 0 ? ' AND `id` != ?' : ''
        );
        $params = $protectId > 0 ? [$id, $protectId] : [$id];

        return Database::execute($sql, $params) > 0;
    }

    /**
     * 批量删除用户（可删除管理账号）。
     *
     * @param array<int> $ids
     * @param int        $protectId 传入当前登录管理员的 id，则跳过该行（防自锁）
     */
    public function deleteBatch(array $ids, int $protectId = 0): int
    {
        if ($ids === []) {
            return 0;
        }
        $params = $ids;
        $sql = sprintf(
            'DELETE FROM `%s` WHERE `id` IN (%s)',
            $this->table,
            implode(',', array_fill(0, count($ids), '?'))
        );
        if ($protectId > 0) {
            $sql .= ' AND `id` != ?';
            $params[] = $protectId;
        }

        return Database::execute($sql, $params);
    }

    /**
     * 创建用户。
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        // 可写字段白名单；推广返佣 + 用户等级也允许在注册时一次性写入
        $fields = [
            'username', 'password', 'email', 'mobile', 'nickname', 'avatar', 'status',
            'invite_code', 'inviter_l1', 'inviter_l2', 'level_id',
        ];

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

        // role 固定为 user
        $cols[] = '`role`';
        $placeholders[] = '?';
        $params[] = 'user';

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

        $row = Database::fetchOne('SELECT LAST_INSERT_ID() AS id', []);
        return (int) ($row['id'] ?? 0);
    }
}
