<?php

declare(strict_types=1);

/**
 * 数据库访问（兼容门面）。
 *
 * 实际实现已迁到 App\Support\Database 实例服务；这里保留原有静态 API 和常量，
 * 现有调用点、插件和模板都不用改。
 */
final class Database
{
    /** 建表所需的最低 MySQL 版本。 */
    public const MIN_MYSQL_VERSION = \App\Support\Database::MIN_MYSQL_VERSION;

    /** @var \App\Support\Database|null */
    private static $service;

    /**
     * 获取内部数据库服务实例。
     */
    public static function instance(): \App\Support\Database
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\Database::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/Database.php';
            }
            self::$service = new \App\Support\Database();
        }

        return self::$service;
    }

    /**
     * 返回数据库配置。
     *
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return self::instance()->config();
    }

    /**
     * 返回表前缀。
     */
    public static function prefix(): string
    {
        return self::instance()->prefix();
    }

    /**
     * 返回当前使用的驱动名称。
     */
    public static function driver(): string
    {
        return self::instance()->driver();
    }

    /**
     * 返回 MySQL/MariaDB 服务端版本号。
     */
    public static function serverVersion(): string
    {
        return self::instance()->serverVersion();
    }

    /**
     * 判断当前数据库是否满足建表所需的最低 MySQL 版本。
     */
    public static function mysqlVersionOk(): bool
    {
        return self::instance()->mysqlVersionOk();
    }

    /**
     * 获取数据库连接。
     *
     * @return mysqli|PDO
     */
    public static function connect(bool $withoutDatabase = false)
    {
        return self::instance()->connect($withoutDatabase);
    }

    /**
     * 执行查询并返回一行结果。
     *
     * @return array<string, mixed>|null
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        return self::instance()->fetchOne($sql, $params);
    }

    /**
     * 按主键查单条记录（自动加前缀）。
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $table, int $id): ?array
    {
        return self::instance()->find($table, $id);
    }

    /**
     * 执行查询并返回全部结果。
     *
     * @return array<int, array<string, mixed>>
     */
    public static function query(string $sql, array $params = []): array
    {
        return self::instance()->query($sql, $params);
    }

    /**
     * 执行写入、更新、删除语句。
     */
    public static function execute(string $sql, array $params = []): int
    {
        return self::instance()->execute($sql, $params);
    }

    /**
     * 执行不带绑定参数的原始 SQL。
     */
    public static function statement(string $sql, bool $withoutDatabase = false): bool
    {
        return self::instance()->statement($sql, $withoutDatabase);
    }

    /**
     * 插入数据到指定表。
     */
    public static function insert(string $table, array $data): int
    {
        return self::instance()->insert($table, $data);
    }

    /**
     * 更新指定表的数据。
     */
    public static function update(string $table, array $data, $where, array $whereParams = []): int
    {
        return self::instance()->update($table, $data, $where, $whereParams);
    }

    /**
     * 开启事务；嵌套调用时在当前事务内打 SAVEPOINT。
     */
    public static function begin(): void
    {
        self::instance()->begin();
    }

    /**
     * 提交事务。
     */
    public static function commit(): void
    {
        self::instance()->commit();
    }

    /**
     * 回滚事务。
     */
    public static function rollBack(): void
    {
        self::instance()->rollBack();
    }

    /**
     * 当前是否处于事务中（含嵌套层）。
     */
    public static function inTransaction(): bool
    {
        return self::instance()->inTransaction();
    }

    /**
     * 返回底层原始连接，仅在必须时使用。
     *
     * @return mysqli|PDO
     */
    public static function rawConnection(bool $withoutDatabase = false)
    {
        return self::instance()->rawConnection($withoutDatabase);
    }

    /**
     * 返回最近一次执行的 SQL 上下文（用于安装器输出）。
     *
     * @return array<string, mixed>
     */
    public static function lastSqlContext(): array
    {
        return self::instance()->lastSqlContext();
    }

    /**
     * 是否为唯一键冲突（MySQL errno 1062）。
     */
    public static function isDuplicateKeyError(Throwable $e): bool
    {
        return self::instance()->isDuplicateKeyError($e);
    }

    /**
     * 判断表字段是否已存在（用于兼容未跑迁移的老库）。
     */
    public static function columnExists(string $table, string $column): bool
    {
        return self::instance()->columnExists($table, $column);
    }
}
