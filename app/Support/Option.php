<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Key-Value 配置存储服务（实例实现）。
 *
 * 有状态：进程内缓存 + 延迟落库的脏标记。兼容门面见 include/lib/Option.php，
 * 门面单例保证「请求结束时 shutdown 里 flush」读写的是同一份内存状态。
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
    private $cache = null;

    /**
     * 脏标记，写入会延迟到请求结束时统一落库。
     *
     * @var array<string, string>
     */
    private $dirty = [];

    /**
     * 获取配置值。
     *
     * @param string $name 配置名
     * @param mixed $default 默认值（找不到时返回）
     * @return mixed
     */
    public function get(string $name, $default = null)
    {
        $this->ensureCache();
        if (array_key_exists($name, $this->cache)) {
            $val = $this->cache[$name];
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
    public function set(string $name, $value): bool
    {
        $this->ensureCache();

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->cache[$name] = (string) $value;
        $this->dirty[$name] = (string) $value;
        return true;
    }

    /**
     * 删除配置项。
     */
    public function delete(string $name): bool
    {
        $this->ensureCache();
        unset($this->cache[$name]);
        $this->dirty[$name] = ''; // 空值标记为删除
        return true;
    }

    /**
     * 批量获取。
     *
     * @param array<string, mixed> $names
     * @return array<string, mixed>
     */
    public function gets(array $names, $default = null): array
    {
        $result = [];
        foreach ($names as $name) {
            $result[$name] = $this->get($name, $default);
        }
        return $result;
    }

    /**
     * 刷新缓存。
     */
    public function flush(): void
    {
        if ($this->cache === null || $this->dirty === []) {
            $this->dirty = [];
            return;
        }

        $table = \Database::prefix() . 'config';
        foreach ($this->dirty as $name => $value) {
            if ($value === '') {
                // 删除
                $sql = sprintf('DELETE FROM `%s` WHERE `config_name` = ?', $table);
                \Database::execute($sql, [$name]);
            } else {
                // 存在则更新，不存在则插入
                $sqlCheck = sprintf('SELECT COUNT(*) AS cnt FROM `%s` WHERE `config_name` = ?', $table);
                $row = \Database::fetchOne($sqlCheck, [$name]);
                if ($row !== null && (int) $row['cnt'] > 0) {
                    $sql = sprintf('UPDATE `%s` SET `config_value` = ? WHERE `config_name` = ?', $table);
                    \Database::execute($sql, [$value, $name]);
                } else {
                    $sql = sprintf('INSERT INTO `%s` (`config_name`, `config_value`) VALUES (?, ?)', $table);
                    \Database::execute($sql, [$name, $value]);
                }
            }
        }
        $this->dirty = [];
    }

    /**
     * 确保缓存已加载。
     */
    private function ensureCache(): void
    {
        if ($this->cache !== null) {
            return;
        }

        $this->cache = [];
        $this->dirty = [];

        try {
            $table = \Database::prefix() . 'config';
            $sql = sprintf('SELECT `config_name`, `config_value` FROM `%s`', $table);
            $rows = \Database::query($sql);

            foreach ($rows as $row) {
                $name = isset($row['config_name']) ? (string) $row['config_name'] : '';
                if ($name === '') {
                    continue;
                }
                $this->cache[$name] = isset($row['config_value']) ? (string) $row['config_value'] : '';
            }
        } catch (\Throwable $e) {
            $this->cache = [];
        }
    }
}
