<?php

declare(strict_types=1);

/**
 * 系统配置类。
 *
 * 负责把 config 表中的配置一次性读出并缓存，后续统一通过 Config::get() 读取。
 */
final class Config
{
    /**
     * @var array<string, string>|null
     */
    private static $items = null;

    /**
     * 读取全部系统配置。
     *
     * @return array<string, string>
     */
    public static function load(): array
    {
        if (self::$items !== null) {
            return self::$items;
        }

        self::$items = [];

        try {
            $table = Database::prefix() . 'config';
            $sql = sprintf('SELECT `config_name`, `config_value` FROM `%s`', $table);
            $rows = Database::query($sql);

            foreach ($rows as $row) {
                $name = isset($row['config_name']) ? (string) $row['config_name'] : '';
                if ($name === '') {
                    continue;
                }

                self::$items[$name] = isset($row['config_value']) ? (string) $row['config_value'] : '';
            }
        } catch (Throwable $e) {
            self::$items = [];
        }

        return self::$items;
    }

    /**
     * 按名称读取系统配置。
     */
    public static function get(string $name, string $default = ''): string
    {
        $items = self::load();
        return array_key_exists($name, $items) ? $items[$name] : $default;
    }


    /**
     * 保存或更新一条系统配置（不存在则添加，存在则更新）。
     */
    public static function set(string $name, string $value): bool
    {
        $table = Database::prefix() . 'config';
        $sqlCheck = sprintf('SELECT COUNT(*) AS cnt FROM `%s` WHERE `config_name` = ?', $table);
        $row = Database::fetchOne($sqlCheck, [$name]);
        $exists = $row !== null && (int) $row['cnt'] > 0;

        if ($exists) {
            $sql = sprintf('UPDATE `%s` SET `config_value` = ? WHERE `config_name` = ?', $table);
            $affected = Database::execute($sql, [$value, $name]);
        } else {
            $sql = sprintf('INSERT INTO `%s` (`config_name`, `config_value`) VALUES (?, ?)', $table);
            $affected = Database::execute($sql, [$name, $value]);
        }

        // 同步更新内存缓存
        if (self::$items !== null) {
            self::$items[$name] = $value;
        }

        return $affected > 0;
    }

    /**
     * 刷新当前请求内的配置缓存。
     *
     * @return array<string, string>
     */
    public static function reload(): array
    {
        self::$items = null;
        return self::load();
    }
}
