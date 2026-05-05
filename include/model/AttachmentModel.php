<?php

declare(strict_types=1);

/**
 * 附件数据模型。
 *
 * 统一管理所有上传文件的数据库记录，支持场景分类、去重查询、关联查询。
 */
final class AttachmentModel
{
    /** @var string */
    private $table;

    public function __construct()
    {
        $this->table = Database::prefix() . 'attachment';
    }

    /**
     * 插入附件记录。
     *
     * @param array<string, mixed> $data
     * @return int 新记录ID
     */
    public function insert(array $data): int
    {
        $allowed = ['user_id', 'file_name', 'file_path', 'file_url', 'file_size',
            'file_ext', 'mime_type', 'md5', 'driver', 'context', 'context_id'];

        $fields = [];
        $placeholders = [];
        $params = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = sprintf('`%s`', $field);
                $placeholders[] = sprintf(':%s', $field);
                $params[$field] = $data[$field];
            }
        }

        if ($fields === []) {
            return 0;
        }

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );

        return Database::execute($sql, $params) > 0 ? (int) Database::fetchOne(
            'SELECT LAST_INSERT_ID() as id'
        )['id'] : 0;
    }

    /**
     * 通过 MD5 查找已有附件（用于去重）。
     *
     * @return array<string, mixed>|null
     */
    public function findByMd5(string $md5): ?array
    {
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `md5` = :md5 LIMIT 1',
            $this->table
        );

        return Database::fetchOne($sql, ['md5' => $md5]);
    }

    /**
     * 按 ID 查找。
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `id` = :id LIMIT 1',
            $this->table
        );

        return Database::fetchOne($sql, ['id' => $id]);
    }

    /**
     * 统计指定场景的附件数。
     */
    public function countByContext(string $context, ?int $contextId = null): int
    {
        if ($contextId !== null) {
            $sql = sprintf(
                'SELECT COUNT(*) as total FROM `%s` WHERE `context` = :context AND `context_id` = :context_id',
                $this->table
            );

            return (int) Database::fetchOne($sql, [
                'context' => $context,
                'context_id' => $contextId,
            ])['total'];
        }

        $sql = sprintf(
            'SELECT COUNT(*) as total FROM `%s` WHERE `context` = :context',
            $this->table
        );

        return (int) Database::fetchOne($sql, ['context' => $context])['total'];
    }

    /**
     * 分页查询指定场景的附件列表。
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByContext(string $context, ?int $contextId = null, int $page = 1, int $pageSize = 20): array
    {
        $offset = ($page - 1) * $pageSize;

        if ($contextId !== null) {
            $sql = sprintf(
                'SELECT * FROM `%s` WHERE `context` = :context AND `context_id` = :context_id ORDER BY `created_at` DESC LIMIT :limit OFFSET :offset',
                $this->table
            );

            return Database::query($sql, [
                'context' => $context,
                'context_id' => $contextId,
                'limit' => $pageSize,
                'offset' => $offset,
            ]);
        }

        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `context` = :context ORDER BY `created_at` DESC LIMIT :limit OFFSET :offset',
            $this->table
        );

        return Database::query($sql, [
            'context' => $context,
            'limit' => $pageSize,
            'offset' => $offset,
        ]);
    }

    /**
     * 更新附件的 context_id（关联到具体业务记录后调用）。
     */
    public function bindContext(int $id, string $context, int $contextId): bool
    {
        $sql = sprintf(
            'UPDATE `%s` SET `context` = :context, `context_id` = :context_id WHERE `id` = :id LIMIT 1',
            $this->table
        );

        return Database::execute($sql, [
            'id' => $id,
            'context' => $context,
            'context_id' => $contextId,
        ]) > 0;
    }

    /**
     * 删除附件记录。
     */
    public function delete(int $id): bool
    {
        $sql = sprintf(
            'DELETE FROM `%s` WHERE `id` = :id LIMIT 1',
            $this->table
        );

        return Database::execute($sql, ['id' => $id]) > 0;
    }
}
