<?php

declare(strict_types=1);

/**
 * 系统日志数据模型。
 *
 * 数据存储于 em_system_log 表。
 *
 * 使用示例：
 *   $log = new SystemLogModel();
 *   $log->info('admin_operation', '创建商品', '商品「T恤」创建成功', ['goods_id' => 5]);
 *   $log->error('system', '数据库连接失败', 'MySQL server has gone away');
 */
final class SystemLogModel
{
    private string $table;

    public function __construct()
    {
        $this->table = Database::prefix() . 'system_log';
    }

    /**
     * 记录普通日志。
     */
    public function info(string $type, string $action, string $message, array $detail = [], ?int $userId = null, ?string $username = null): int
    {
        return $this->write('info', $type, $action, $message, $detail, $userId, $username);
    }

    /**
     * 记录警告日志。
     */
    public function warning(string $type, string $action, string $message, array $detail = [], ?int $userId = null, ?string $username = null): int
    {
        return $this->write('warning', $type, $action, $message, $detail, $userId, $username);
    }

    /**
     * 记录错误日志。
     */
    public function error(string $type, string $action, string $message, array $detail = [], ?int $userId = null, ?string $username = null): int
    {
        return $this->write('error', $type, $action, $message, $detail, $userId, $username);
    }

    /**
     * 写入日志。
     */
    private function write(string $level, string $type, string $action, string $message, array $detail, ?int $userId, ?string $username): int
    {
        $userId = $userId ?? ($GLOBALS['adminUser']['id'] ?? 0);
        $username = $username ?? ($GLOBALS['adminUser']['username'] ?? ($GLOBALS['user']['username'] ?? 'system'));

        $sql = sprintf(
            'INSERT INTO `%s` (`level`, `type`, `action`, `message`, `detail`, `user_id`, `username`, `ip`, `user_agent`, `created_at`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            $this->table
        );

        Database::execute($sql, [
            $level,
            $type,
            mb_substr($action, 0, 100),
            mb_substr($message, 0, 500),
            $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
            $userId,
            mb_substr((string) $username, 0, 64),
            $this->getClientIp(),
            mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
        ]);

        $row = Database::fetchOne('SELECT LAST_INSERT_ID() AS id', []);
        return (int) ($row['id'] ?? 0);
    }

    /**
     * 获取客户端 IP。
     */
    private function getClientIp(): string
    {
        $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // 取第一个 IP（代理模式）
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    /**
     * 分页查询日志列表。
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(
        ?string $level = null,
        ?string $type = null,
        ?string $keyword = null,
        int $page = 1,
        int $pageSize = 20
    ): array {
        $conditions = [];
        $params = [];

        if ($level !== null && $level !== '' && $level !== 'all') {
            $conditions[] = '`level` = ?';
            $params[] = $level;
        }

        if ($type !== null && $type !== '' && $type !== 'all') {
            $conditions[] = '`type` = ?';
            $params[] = $type;
        }

        if ($keyword !== null && $keyword !== '') {
            $conditions[] = '(`action` LIKE ? OR `message` LIKE ? OR `username` LIKE ?)';
            $kw = '%' . $keyword . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $offset = ($page - 1) * $pageSize;
        $params[] = $pageSize;
        $params[] = $offset;

        $sql = sprintf(
            'SELECT * FROM `%s` %s ORDER BY `created_at` DESC LIMIT ? OFFSET ?',
            $this->table,
            $where
        );

        return Database::query($sql, $params);
    }

    /**
     * 统计日志总数。
     */
    public function count(?string $level = null, ?string $type = null, ?string $keyword = null): int
    {
        $conditions = [];
        $params = [];

        if ($level !== null && $level !== '' && $level !== 'all') {
            $conditions[] = '`level` = ?';
            $params[] = $level;
        }

        if ($type !== null && $type !== '' && $type !== 'all') {
            $conditions[] = '`type` = ?';
            $params[] = $type;
        }

        if ($keyword !== null && $keyword !== '') {
            $conditions[] = '(`action` LIKE ? OR `message` LIKE ? OR `username` LIKE ?)';
            $kw = '%' . $keyword . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = sprintf('SELECT COUNT(*) AS cnt FROM `%s` %s', $this->table, $where);
        $row = Database::fetchOne($sql, $params);
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * 按 ID 获取单条。
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
     * 删除日志。
     */
    public function delete(int $id): bool
    {
        $sql = sprintf('DELETE FROM `%s` WHERE `id` = ? LIMIT 1', $this->table);
        return Database::execute($sql, [$id]) > 0;
    }

    /**
     * 批量删除日志。
     *
     * @param array<int> $ids
     * @return int 删除了多少条
     */
    public function batchDelete(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = sprintf('DELETE FROM `%s` WHERE `id` IN (%s)', $this->table, $placeholders);
        return Database::execute($sql, $ids);
    }

    /**
     * 按日期清理日志。
     *
     * @param int $days 删除多少天之前的日志
     * @return int 删除了多少条
     */
    public function cleanup(int $days = 30): int
    {
        $sql = sprintf(
            'DELETE FROM `%s` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL ? DAY)',
            $this->table
        );
        return Database::execute($sql, [$days]);
    }

    /**
     * 获取各类型的日志统计。
     *
     * @return array<string, int>
     */
    public function statsByType(): array
    {
        $sql = sprintf(
            'SELECT `type`, COUNT(*) AS cnt FROM `%s` GROUP BY `type` ORDER BY cnt DESC',
            $this->table
        );
        $rows = Database::query($sql, []);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['type']] = (int) $row['cnt'];
        }
        return $result;
    }
}
