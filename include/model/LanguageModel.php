<?php

declare(strict_types=1);

/**
 * 语言数据模型。
 *
 * 数据存储于 language 表。
 */
final class LanguageModel
{
    private string $table;

    public function __construct()
    {
        $this->table = Database::prefix() . 'language';
    }

    /**
     * 获取所有语言，按ID升序。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $sql = sprintf('SELECT * FROM `%s` ORDER BY `id` ASC', $this->table);
        return Database::query($sql, []);
    }

    /**
     * 获取启用的语言，按ID升序。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEnabled(): array
    {
        return Cache::remember('language:list', 'language', function (): array {
            $sql = sprintf(
                'SELECT * FROM `%s` WHERE `enabled` = \'y\' ORDER BY `id` ASC',
                $this->table
            );
            return Database::query($sql, []);
        }, 86400);
    }

    /**
     * 按 ID 获取单条语言。
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE `id` = ? LIMIT 1', $this->table);
        $row = Database::fetchOne($sql, [$id]);
        return $row !== false ? $row : null;
    }

    /**
     * 按语言码获取。
     *
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE `code` = ? LIMIT 1', $this->table);
        $row = Database::fetchOne($sql, [$code]);
        return $row !== false ? $row : null;
    }

    /**
     * 检查语言码是否已存在（排除自身）。
     */
    public function existsCode(string $code, int $excludeId = 0): bool
    {
        if ($excludeId > 0) {
            $sql = sprintf('SELECT `id` FROM `%s` WHERE `code` = ? AND `id` != ? LIMIT 1', $this->table);
            $row = Database::fetchOne($sql, [$code, $excludeId]);
        } else {
            $sql = sprintf('SELECT `id` FROM `%s` WHERE `code` = ? LIMIT 1', $this->table);
            $row = Database::fetchOne($sql, [$code]);
        }
        return $row !== null;
    }

    /**
     * 检查语言名称是否已存在（排除自身）。
     */
    public function existsName(string $name, int $excludeId = 0): bool
    {
        if ($excludeId > 0) {
            $sql = sprintf('SELECT `id` FROM `%s` WHERE `name` = ? AND `id` != ? LIMIT 1', $this->table);
            $row = Database::fetchOne($sql, [$name, $excludeId]);
        } else {
            $sql = sprintf('SELECT `id` FROM `%s` WHERE `name` = ? LIMIT 1', $this->table);
            $row = Database::fetchOne($sql, [$name]);
        }
        return $row !== null;
    }

    /**
     * 创建语言。
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $fields = ['name', 'code', 'icon', 'enabled', 'is_default'];

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

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->table,
            implode(', ', $cols),
            implode(', ', $placeholders)
        );

        Database::execute($sql, $params);

        $row = Database::fetchOne('SELECT LAST_INSERT_ID() AS id', []);
        $newId = (int) ($row['id'] ?? 0);

        // 如果设为默认，先清除其他默认
        if ($newId > 0 && isset($data['is_default']) && $data['is_default'] === 'y') {
            $this->clearDefaultExcept($newId);
        }

        Cache::deleteGroup('language');

        return $newId;
    }

    /**
     * 更新语言。
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $fields = ['name', 'code', 'icon', 'enabled', 'is_default'];

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

        $params[] = $id;

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `id` = ? LIMIT 1',
            $this->table,
            implode(', ', $sets)
        );

        $result = Database::execute($sql, $params) > 0;

        // 如果设为默认，先清除其他默认
        if ($result && isset($data['is_default']) && $data['is_default'] === 'y') {
            $this->clearDefaultExcept($id);
        }

        Cache::deleteGroup('language');

        return $result;
    }

    /**
     * 清除所有语言的默认标记（排除指定ID）。
     */
    private function clearDefaultExcept(int $excludeId): void
    {
        $sql = sprintf(
            'UPDATE `%s` SET `is_default` = \'n\' WHERE `id` != ? AND `is_default` = \'y\'',
            $this->table
        );
        Database::execute($sql, [$excludeId]);
    }

    /**
     * 获取默认语言。
     *
     * @return array<string, mixed>|null
     */
    public function getDefault(): ?array
    {
        return Cache::remember('language:default', 'language', function (): ?array {
            $sql = sprintf('SELECT * FROM `%s` WHERE `is_default` = \'y\' LIMIT 1', $this->table);
            $row = Database::fetchOne($sql, []);
            return $row !== false ? $row : null;
        }, 86400);
    }

    /**
     * 删除语言。
     */
    public function delete(int $id): bool
    {
        // 外键级联删除 lang 表中的翻译
        $sql = sprintf('DELETE FROM `%s` WHERE `id` = ? LIMIT 1', $this->table);
        $result = Database::execute($sql, [$id]) > 0;
        if ($result) {
            Cache::deleteGroup('language');
            Cache::deleteGroup('lang');
        }
        return $result;
    }

    /**
     * 切换启用状态。
     */
    public function toggle(int $id): bool
    {
        $sql = sprintf(
            'UPDATE `%s` SET `enabled` = IF(`enabled` = \'y\', \'n\', \'y\') WHERE `id` = ? LIMIT 1',
            $this->table
        );
        $result = Database::execute($sql, [$id]) > 0;
        if ($result) {
            Cache::deleteGroup('language');
        }
        return $result;
    }

    /**
     * 获取语言总数。
     */
    public function count(): int
    {
        $sql = sprintf('SELECT COUNT(*) AS cnt FROM `%s`', $this->table);
        $row = Database::fetchOne($sql, []);
        return (int) ($row['cnt'] ?? 0);
    }
}
