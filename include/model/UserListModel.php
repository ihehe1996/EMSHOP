<?php

declare(strict_types=1);

/**
 * 用户列表数据模型。
 *
 * 只操作 role='user' 的普通用户，不操作管理员账号。
 * 数据来源：em_user 表。
 */
final class UserListModel
{
    private string $table;

    public function __construct()
    {
        $this->table = Database::prefix() . 'user';
    }

    /**
     * 获取所有用户，支持分页和关键词搜索。
     *
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function getAll(int $page, int $limit, string $keyword = ''): array
    {
        $offset = ($page - 1) * $limit;

        $where = "u.`role` = 'user'";
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

        // 数据（左连商户 + 商户等级 + 用户等级；用户等级影响买家折扣）
        $merchantTable = Database::prefix() . 'merchant';
        $merchantLevelTable = Database::prefix() . 'merchant_level';
        $userLevelTable = Database::prefix() . 'user_levels';
        $sql = sprintf(
            'SELECT u.`id`, u.`username`, u.`email`, u.`mobile`, u.`nickname`, u.`avatar`, u.`money`,
                    u.`role`, u.`status`, u.`level_id`, u.`last_login_ip`, u.`last_login_at`, u.`created_at`,
                    m.`id` AS merchant_id, m.`name` AS merchant_name,
                    ml.`name` AS merchant_level_name,
                    ul.`name` AS user_level_name
             FROM `%s` u
             LEFT JOIN `%s` m ON m.`user_id` = u.`id` AND m.`deleted_at` IS NULL
             LEFT JOIN `%s` ml ON ml.`id` = m.`level_id`
             LEFT JOIN `%s` ul ON ul.`id` = u.`level_id` AND ul.`enabled` = \'y\'
             WHERE %s
             ORDER BY u.`id` DESC
             LIMIT %d OFFSET %d',
            $this->table,
            $merchantTable,
            $merchantLevelTable,
            $userLevelTable,
            $where,
            $limit,
            $offset
        );

        $rows = Database::query($sql, $params);

        return ['data' => $rows, 'total' => $total];
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
             WHERE `id` = ? AND `role` = \'user\' LIMIT 1',
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
     * 检查邮箱是否已被其他用户占用（排除自身）。
     */
    public function existsEmail(string $email, int $excludeId = 0): bool
    {
        if ($excludeId > 0) {
            $sql = sprintf(
                'SELECT `id` FROM `%s` WHERE `email` = ? AND `id` != ? AND `role` = \'user\' LIMIT 1',
                $this->table
            );
            $row = Database::fetchOne($sql, [$email, $excludeId]);
        } else {
            $sql = sprintf(
                'SELECT `id` FROM `%s` WHERE `email` = ? AND `role` = \'user\' LIMIT 1',
                $this->table
            );
            $row = Database::fetchOne($sql, [$email]);
        }
        return $row !== null;
    }

    /**
     * 检查手机号是否已被其他用户占用（排除自身）。
     */
    public function existsMobile(string $mobile, int $excludeId = 0): bool
    {
        if ($mobile === '') {
            return false;
        }
        if ($excludeId > 0) {
            $sql = sprintf(
                'SELECT `id` FROM `%s` WHERE `mobile` = ? AND `id` != ? AND `role` = \'user\' LIMIT 1',
                $this->table
            );
            $row = Database::fetchOne($sql, [$mobile, $excludeId]);
        } else {
            $sql = sprintf(
                'SELECT `id` FROM `%s` WHERE `mobile` = ? AND `role` = \'user\' LIMIT 1',
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
        $fields = ['nickname', 'email', 'mobile', 'avatar', 'status', 'secret', 'level_id'];

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

        $sets[] = '`updated_at` = NOW()';
        $params[] = $id;

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `id` = ? AND `role` = \'user\' LIMIT 1',
            $this->table,
            implode(', ', $sets)
        );

        return Database::execute($sql, $params) > 0;
    }

    /**
     * 切换用户状态。
     */
    public function toggleStatus(int $id): bool
    {
        $sql = sprintf(
            'UPDATE `%s` SET `status` = IF(`status` = 1, 0, 1), `updated_at` = NOW() WHERE `id` = ? AND `role` = \'user\' LIMIT 1',
            $this->table
        );
        return Database::execute($sql, [$id]) > 0;
    }

    /**
     * 删除用户。
     */
    public function delete(int $id): bool
    {
        $sql = sprintf(
            'DELETE FROM `%s` WHERE `id` = ? AND `role` = \'user\' LIMIT 1',
            $this->table
        );
        return Database::execute($sql, [$id]) > 0;
    }

    /**
     * 批量删除用户。
     *
     * @param array<int> $ids
     */
    public function deleteBatch(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = sprintf(
            'DELETE FROM `%s` WHERE `id` IN (%s) AND `role` = \'user\'',
            $this->table,
            $placeholders
        );
        return Database::execute($sql, $ids);
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
