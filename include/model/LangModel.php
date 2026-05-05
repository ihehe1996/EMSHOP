<?php

declare(strict_types=1);

/**
 * 翻译数据模型。
 *
 * 数据存储于 lang 表。
 */
final class LangModel
{
    private string $table;

    public function __construct()
    {
        $this->table = Database::prefix() . 'lang';
    }

    /**
     * 获取某个语言的所有翻译。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByLangId(int $langId): array
    {
        return Cache::remember('lang_' . $langId, 'lang', function () use ($langId): array {
            $sql = sprintf(
                'SELECT * FROM `%s` WHERE `lang_id` = ? ORDER BY `translate` ASC',
                $this->table
            );
            return Database::query($sql, [$langId]);
        }, 86400);
    }

    /**
     * 获取所有翻译，带语言信息（name + code）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllWithLanguage(?int $langId = null): array
    {
        $sql = sprintf(
            'SELECT l.*, lang.name AS lang_name, lang.code AS lang_code
             FROM `%s` l
             LEFT JOIN `%s` lang ON l.lang_id = lang.id
             %s
             ORDER BY l.id DESC',
            $this->table,
            Database::prefix() . 'language',
            $langId !== null ? 'WHERE l.lang_id = ?' : ''
        );
        return Database::query($sql, $langId !== null ? [$langId] : []);
    }

    /**
     * 获取符合条件的总数。
     */
    public function countWithFilters(?int $langId = null, string $keyword = ''): int
    {
        $conditions = [];
        $params = [];

        if ($langId !== null) {
            $conditions[] = 'l.lang_id = ?';
            $params[] = $langId;
        }

        if ($keyword !== '') {
            $conditions[] = '(l.translate LIKE ? OR l.content LIKE ?)';
            $kw = '%' . $keyword . '%';
            $params[] = $kw;
            $params[] = $kw;
        }

        $whereClause = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = sprintf(
            'SELECT COUNT(*) as total FROM `%s` l %s',
            $this->table,
            $whereClause
        );

        $row = Database::fetchOne($sql, $params);
        return (int) ($row['total'] ?? 0);
    }

    /**
     * 分页获取翻译，带语言信息。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPageWithLanguage(?int $langId = null, string $keyword = '', int $offset = 0, int $limit = 10): array
    {
        $conditions = [];
        $params = [];

        if ($langId !== null) {
            $conditions[] = 'l.lang_id = ?';
            $params[] = $langId;
        }

        if ($keyword !== '') {
            $conditions[] = '(l.translate LIKE ? OR l.content LIKE ?)';
            $kw = '%' . $keyword . '%';
            $params[] = $kw;
            $params[] = $kw;
        }

        $whereClause = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = sprintf(
            'SELECT l.*, lang.name AS lang_name, lang.code AS lang_code
             FROM `%s` l
             LEFT JOIN `%s` lang ON l.lang_id = lang.id
             %s
             ORDER BY l.id DESC
             LIMIT ? OFFSET ?',
            $this->table,
            Database::prefix() . 'language',
            $whereClause
        );

        $params[] = $limit;
        $params[] = $offset;

        return Database::query($sql, $params);
    }

    /**
     * 获取翻译，支持关键词搜索 translate 或 content。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByLangIdWithSearch(int $langId, string $keyword = ''): array
    {
        if ($keyword === '') {
            return $this->getByLangId($langId);
        }
        $kw = '%' . $keyword . '%';
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `lang_id` = ? AND (`translate` LIKE ? OR `content` LIKE ?) ORDER BY `translate` ASC',
            $this->table
        );
        return Database::query($sql, [$langId, $kw, $kw]);
    }

    /**
     * 按 ID 获取单条翻译。
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
     * 按语言ID和translate获取。
     *
     * @return array<string, mixed>|null
     */
    public function findByLangIdAndTranslate(int $langId, string $translate): ?array
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE `lang_id` = ? AND `translate` = ? LIMIT 1', $this->table);
        $row = Database::fetchOne($sql, [$langId, $translate]);
        return $row !== false ? $row : null;
    }

    /**
     * 按 translate 获取所有语言的翻译（同一个 translate 在不同语言下的内容）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByTranslate(string $translate): array
    {
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `translate` = ? ORDER BY `lang_id` ASC',
            $this->table
        );
        return Database::query($sql, [$translate]);
    }

    /**
     * 检查语言+translate组合是否已存在（排除自身）。
     */
    public function existsLangIdAndTranslate(int $langId, string $translate, int $excludeId = 0): bool
    {
        if ($excludeId > 0) {
            $sql = sprintf(
                'SELECT `id` FROM `%s` WHERE `lang_id` = ? AND `translate` = ? AND `id` != ? LIMIT 1',
                $this->table
            );
            $row = Database::fetchOne($sql, [$langId, $translate, $excludeId]);
        } else {
            $sql = sprintf(
                'SELECT `id` FROM `%s` WHERE `lang_id` = ? AND `translate` = ? LIMIT 1',
                $this->table
            );
            $row = Database::fetchOne($sql, [$langId, $translate]);
        }
        return $row !== null;
    }

    /**
     * 创建翻译。
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $fields = ['lang_id', 'translate', 'content'];

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

        if ($newId > 0) {
            Cache::deleteGroup('lang');
        }

        return $newId;
    }

    /**
     * 更新翻译。
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $fields = ['translate', 'content'];

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

        if ($result) {
            Cache::deleteGroup('lang');
        }

        return $result;
    }

    /**
     * 删除翻译。
     */
    public function delete(int $id): bool
    {
        $sql = sprintf('DELETE FROM `%s` WHERE `id` = ? LIMIT 1', $this->table);
        $result = Database::execute($sql, [$id]) > 0;
        if ($result) {
            Cache::deleteGroup('lang');
        }
        return $result;
    }

    /**
     * 批量更新/创建翻译。
     * 传入 [translate => content] 格式的数据，自动新增或更新。
     *
     * @param array<string, string> $translations
     */
    public function upsertBatch(int $langId, array $translations): void
    {
        foreach ($translations as $translate => $content) {
            $existing = $this->findByLangIdAndTranslate($langId, $translate);
            if ($existing !== null) {
                if ($existing['content'] !== $content) {
                    $this->update($existing['id'], ['content' => $content]);
                }
            } else {
                $this->create(['lang_id' => $langId, 'translate' => $translate, 'content' => $content]);
            }
        }
    }
}
