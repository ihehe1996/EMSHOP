<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 文件缓存服务（实例实现）。
 *
 * 原来 Cache 的静态状态（目录、前缀、默认 TTL）搬到这里成为实例属性。
 * 兼容门面见 include/lib/Cache.php；现有 Cache:: 调用点不需要改。
 */
final class Cache
{
    /** @var string|null 缓存目录（null = 尚未初始化） */
    private $directory;

    /** @var string 缓存前缀 */
    private $prefix = 'em_cache_';

    /** @var int 默认 TTL（秒），0 表示不过期 */
    private $defaultTtl = 86400;

    /**
     * 初始化缓存目录。
     */
    public function init(?string $directory = null): void
    {
        $base = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
        $this->directory = rtrim($directory ?? $base . '/content/cache', '/\\');

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }

        // 确保 .gitkeep 存在
        $gitkeep = $this->directory . '/.gitkeep';
        if (!is_file($gitkeep)) {
            file_put_contents($gitkeep, '');
        }
    }

    /**
     * 设置缓存前缀（用于多项目隔离）。
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    /**
     * 设置默认 TTL。
     */
    public function setDefaultTtl(int $seconds): void
    {
        $this->defaultTtl = $seconds;
    }

    /**
     * 获取缓存。未命中或已过期返回 null。
     *
     * @return mixed|null
     */
    public function get(string $key)
    {
        $this->ensureInit();
        $file = $this->path($key);

        if (!is_file($file)) {
            return null;
        }

        /** @var array{data: mixed, expires: int} */
        $cache = require $file;

        if ($cache['expires'] > 0 && $cache['expires'] < time()) {
            @unlink($file);
            return null;
        }

        return $cache['data'];
    }

    /**
     * 设置缓存。
     *
     * @param mixed $data
     * @param mixed $ttlArg
     */
    public function set(string $key, $data, $ttlArg = 0, string $group = ''): bool
    {
        $this->ensureInit();
        $file = $this->path($key);

        // $ttlArg may be int or string depending on call site
        $ttl = is_int($ttlArg) ? $ttlArg : (is_numeric($ttlArg) ? (int) $ttlArg : 0);
        if ($ttl <= 0) {
            $ttl = $this->defaultTtl;
        }
        $expires = $ttl > 0 ? time() + $ttl : 0;

        $content = sprintf("<?php\n// %s\nreturn ['data' => %s, 'expires' => %d];\n",
            $key,
            var_export($data, true),
            $expires
        );

        $dir = dirname($file);
        if (!is_dir($dir) || !is_writable($dir)) {
            \Emmsg::error('缓存目录无写入权限', '请设置目录权限为755：' . $dir);
        }

        $result = file_put_contents($file, $content, LOCK_EX) !== false;

        if ($result && $group !== '') {
            $this->addToGroup($key, $group);
        }

        return $result;
    }

    /**
     * 删除指定缓存。
     */
    public function delete(string $key): bool
    {
        $this->ensureInit();
        $file = $this->path($key);

        if (is_file($file)) {
            @unlink($file);
        }

        return true;
    }

    /**
     * 删除指定分组下的所有缓存。
     */
    public function deleteGroup(string $group): bool
    {
        $this->ensureInit();
        $indexFile = $this->groupIndexPath($group);

        if (!is_file($indexFile)) {
            return true;
        }

        /** @var array<string> $keys */
        $keys = require $indexFile;

        foreach ($keys as $key) {
            $file = $this->path($key);
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @unlink($indexFile);

        return true;
    }

    /**
     * 清空所有缓存。
     */
    public function clear(): bool
    {
        $this->ensureInit();

        $files = glob($this->directory . '/*.php');
        if ($files === false) {
            return true;
        }

        foreach ($files as $file) {
            if (basename($file) === '.gitkeep') {
                continue;
            }
            @unlink($file);
        }

        return true;
    }

    /**
     * 判断缓存是否存在且未过期。
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * 记忆模式：缓存存在则返回，不存在则执行回调并缓存结果。
     *
     * @param callable $callback
     * @return mixed
     */
    public function remember(string $key, string $group, callable $callback, int $ttl = 0)
    {
        $data = $this->get($key);

        if ($data !== null) {
            return $data;
        }

        $data = $callback();
        $this->set($key, $data, $ttl, $group);

        return $data;
    }

    /**
     * 获取缓存文件路径。
     */
    private function path(string $key): string
    {
        // 安全处理：替换 Windows 不允许的非法字符
        $safePrefix = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $this->prefix);
        $safeKey = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $key);
        return $this->directory . '/' . $safePrefix . $safeKey . '.php';
    }

    /**
     * 获取分组索引文件路径。
     */
    private function groupIndexPath(string $group): string
    {
        return $this->directory . '/.group_' . md5($group) . '.php';
    }

    /**
     * 将 key 加入分组索引。
     */
    private function addToGroup(string $key, string $group): void
    {
        $indexFile = $this->groupIndexPath($group);
        // 存储 sanitized key，与实际文件名保持一致
        $safeKey = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $key);
        $keys = [];

        if (is_file($indexFile)) {
            /** @var array<string> $keys */
            $keys = require $indexFile;
            // 避免重复
            $keys = array_unique(array_merge($keys, [$safeKey]));
        } else {
            $keys = [$safeKey];
        }

        file_put_contents($indexFile, "<?php\n// group: {$group}\nreturn " . var_export($keys, true) . ";\n", LOCK_EX);
    }

    /**
     * 确保已初始化。
     */
    private function ensureInit(): void
    {
        if ($this->directory === null) {
            $this->init();
        }
    }
}
