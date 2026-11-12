<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 系统配置服务（实例实现）。
 *
 * 有状态：进程内缓存 em_config 表内容。兼容门面见 app/Facade/Config.php，
 * 门面持有单例，缓存语义与原来一致。
 */
final class Config
{
    /**
     * @var array<string, string>|null
     */
    private $items = null;

    /**
     * 读取全部系统配置。
     *
     * @return array<string, string>
     */
    public function load(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }

        $this->items = [];

        try {
            $table = \Database::prefix() . 'config';
            $sql = sprintf('SELECT `config_name`, `config_value` FROM `%s`', $table);
            $rows = \Database::query($sql);

            foreach ($rows as $row) {
                $name = isset($row['config_name']) ? (string) $row['config_name'] : '';
                if ($name === '') {
                    continue;
                }

                $this->items[$name] = isset($row['config_value']) ? (string) $row['config_value'] : '';
            }
        } catch (\Throwable $e) {
            // CLI 启动阶段需要感知数据库故障，不能静默吞掉。
            if (PHP_SAPI === 'cli') {
                throw $e;
            }
            $this->items = [];
        }

        return $this->items;
    }

    /**
     * 按名称读取系统配置。
     */
    public function get(string $name, string $default = ''): string
    {
        $items = $this->load();
        return array_key_exists($name, $items) ? $items[$name] : $default;
    }

    /**
     * 保存或更新一条系统配置（不存在则添加，存在则更新）。
     */
    public function set(string $name, string $value): bool
    {
        $table = \Database::prefix() . 'config';
        $sqlCheck = sprintf('SELECT COUNT(*) AS cnt FROM `%s` WHERE `config_name` = ?', $table);
        $row = \Database::fetchOne($sqlCheck, [$name]);
        $exists = $row !== null && (int) $row['cnt'] > 0;

        if ($exists) {
            $sql = sprintf('UPDATE `%s` SET `config_value` = ? WHERE `config_name` = ?', $table);
            $affected = \Database::execute($sql, [$value, $name]);
        } else {
            $sql = sprintf('INSERT INTO `%s` (`config_name`, `config_value`) VALUES (?, ?)', $table);
            $affected = \Database::execute($sql, [$name, $value]);
        }

        // 同步更新内存缓存
        if ($this->items !== null) {
            $this->items[$name] = $value;
        }

        return $affected > 0;
    }

    /**
     * 刷新当前请求内的配置缓存。
     *
     * @return array<string, string>
     */
    public function reload(): array
    {
        $this->items = null;
        return $this->load();
    }
}
