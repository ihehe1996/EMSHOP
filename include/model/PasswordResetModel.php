<?php

declare(strict_types=1);

/**
 * 密码重置令牌数据模型。
 */
final class PasswordResetModel
{
    private string $table;

    public function __construct()
    {
        $this->table = Database::prefix() . 'password_reset';
    }

    /**
     * 写入一条重置令牌记录。
     */
    public function create(int $userId, string $email, string $tokenHash, string $expiresAt): int
    {
        $sql = sprintf(
            'INSERT INTO `%s` (`user_id`, `email`, `token_hash`, `expires_at`, `created_at`)
             VALUES (?, ?, ?, ?, NOW())',
            $this->table
        );
        Database::execute($sql, [$userId, $email, $tokenHash, $expiresAt]);

        $row = Database::fetchOne('SELECT LAST_INSERT_ID() AS id', []);
        return (int) ($row['id'] ?? 0);
    }

    /**
     * 按令牌哈希查找未使用且未过期的记录。
     *
     * @return array<string, mixed>|null
     */
    public function findValidByTokenHash(string $tokenHash): ?array
    {
        $sql = sprintf(
            'SELECT * FROM `%s`
             WHERE `token_hash` = ?
               AND `used_at` IS NULL
               AND `expires_at` > NOW()
             LIMIT 1',
            $this->table
        );

        return Database::fetchOne($sql, [$tokenHash]);
    }

    /**
     * 标记令牌已使用。
     */
    public function markUsed(int $id): void
    {
        $sql = sprintf(
            'UPDATE `%s` SET `used_at` = NOW() WHERE `id` = ? AND `used_at` IS NULL LIMIT 1',
            $this->table
        );
        Database::execute($sql, [$id]);
    }

    /**
     * 作废某用户所有未使用的重置令牌。
     */
    public function invalidatePendingForUser(int $userId): void
    {
        $sql = sprintf(
            'UPDATE `%s` SET `used_at` = NOW()
             WHERE `user_id` = ? AND `used_at` IS NULL',
            $this->table
        );
        Database::execute($sql, [$userId]);
    }
}
