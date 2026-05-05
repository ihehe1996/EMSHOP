<?php

declare(strict_types=1);

/**
 * Key-Value 配置存储类。
 *
 * 提供简单的 get/set/delete 接口，内部复用 config 表。
 * 插件可用此类存储自己的配置信息。
 */
final class Option
{
    /**
     * 内存缓存，避免同一请求内重复查询。
     *
     * @var array<string, string>|null
     */
    private static $cache = null;

    /**
     * 脏标记，写入会延迟到请求结束时统一落库。
     *
     * @var array<string, string>
     */
    private static $dirty = [];

    /**
     * 获取配置值。
     *
     * @param string $name 配置名
     * @param mixed $default 默认值（找不到时返回）
     * @return mixed
     */
    public static function get(string $name, $default = null)
    {
        self::ensureCache();
        if (array_key_exists($name, self::$cache)) {
            $val = self::$cache[$name];
            // 尝试 JSON 解码数组/对象
            $decoded = json_decode($val, true);
            if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
                return $decoded;
            }
            return $val;
        }
        return $default;
    }

    /**
     * 设置配置值。
     *
     * @param string $name 配置名
     * @param mixed $value 配置值（自动 JSON 编码数组/对象）
     */
    public static function set(string $name, $value): bool
    {
        self::ensureCache();

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        self::$cache[$name] = (string) $value;
        self::$dirty[$name] = (string) $value;
        return true;
    }

    /**
     * 删除配置项。
     */
    public static function delete(string $name): bool
    {
        self::ensureCache();
        unset(self::$cache[$name]);
        self::$dirty[$name] = ''; // 空值标记为删除
        return true;
    }

    /**
     * 批量获取。
     *
     * @param array<string, mixed> $names
     * @return array<string, mixed>
     */
    public static function gets(array $names, $default = null): array
    {
        $result = [];
        foreach ($names as $name) {
            $result[$name] = self::get($name, $default);
        }
        return $result;
    }

    /**
     * 刷新缓存。
     */
    public static function flush(): void
    {
        if (self::$cache === null || self::$dirty === []) {
            self::$dirty = [];
            return;
        }

        $table = Database::prefix() . 'config';
        foreach (self::$dirty as $name => $value) {
            if ($value === '') {
                // 删除
                $sql = sprintf('DELETE FROM `%s` WHERE `config_name` = ?', $table);
                Database::execute($sql, [$name]);
            } else {
                // 存在则更新，不存在则插入
                $sqlCheck = sprintf('SELECT COUNT(*) AS cnt FROM `%s` WHERE `config_name` = ?', $table);
                $row = Database::fetchOne($sqlCheck, [$name]);
                if ($row !== null && (int) $row['cnt'] > 0) {
                    $sql = sprintf('UPDATE `%s` SET `config_value` = ? WHERE `config_name` = ?', $table);
                    Database::execute($sql, [$value, $name]);
                } else {
                    $sql = sprintf('INSERT INTO `%s` (`config_name`, `config_value`) VALUES (?, ?)', $table);
                    Database::execute($sql, [$name, $value]);
                }
            }
        }
        self::$dirty = [];
    }

    /**
     * 确保缓存已加载。
     */
    private static function ensureCache(): void
    {
        if (self::$cache !== null) {
            return;
        }

        self::$cache = [];
        self::$dirty = [];

        try {
            $table = Database::prefix() . 'config';
            $sql = sprintf('SELECT `config_name`, `config_value` FROM `%s`', $table);
            $rows = Database::query($sql);

            foreach ($rows as $row) {
                $name = isset($row['config_name']) ? (string) $row['config_name'] : '';
                if ($name === '') {
                    continue;
                }
                self::$cache[$name] = isset($row['config_value']) ? (string) $row['config_value'] : '';
            }
        } catch (Throwable $e) {
            self::$cache = [];
        }
    }
}
