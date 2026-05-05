<?php

declare(strict_types=1);

/**
 * 文件缓存类。
 *
 * 支持：
 * - get / set / delete / clear 基本操作
 * - 分组（group）批量失效
 * - TTL 过期时间
 * - remember() 一行搞定「查缓存/查库/写缓存」
 *
 * 存储格式：每个缓存项一个 PHP 文件（return 序列化数据），避免被直接访问。
 */
final class Cache
{
    /** 默认缓存目录 */
    private static string $directory;

    /** 缓存前缀 */
    private static string $prefix = 'em_cache_';

    /** 默认 TTL（秒），0 表示不过期 */
    private static int $defaultTtl = 86400;

    /**
     * 初始化缓存目录。
     */
    public static function init(string $directory = null): void
    {
        self::$directory = rtrim($directory ?? EM_ROOT . '/content/cache', '/\\');

        if (!is_dir(self::$directory)) {
            mkdir(self::$directory, 0755, true);
        }

        // 确保 .gitkeep 存在
        $gitkeep = self::$directory . '/.gitkeep';
        if (!is_file($gitkeep)) {
            file_put_contents($gitkeep, '');
        }
    }

    /**
     * 设置缓存前缀（用于多项目隔离）。
     */
    public static function setPrefix(string $prefix): void
    {
        self::$prefix = $prefix;
    }

    /**
     * 设置默认 TTL。
     */
    public static function setDefaultTtl(int $seconds): void
    {
        self::$defaultTtl = $seconds;
    }

    /**
     * 获取缓存。未命中或已过期返回 null。
     *
     * @return mixed|null
     */
    public static function get(string $key)
    {
        self::ensureInit();
        $file = self::path($key);

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
     * @param mixed $data  缓存数据（必须可序列化）
     * @param int  $ttl   有效期（秒），0 不过期
     * @param int  $ttlArg  alias for $ttl for backward compat
     */
    public static function set(string $key, $data, $ttlArg = 0, string $group = ''): bool
    {
        self::ensureInit();
        $file = self::path($key);

        // $ttlArg may be int or string depending on call site
        $ttl = is_int($ttlArg) ? $ttlArg : (is_numeric($ttlArg) ? (int) $ttlArg : 0);
        if ($ttl <= 0) {
            $ttl = self::$defaultTtl;
        }
        $expires = $ttl > 0 ? time() + $ttl : 0;

        $content = sprintf("<?php\n// %s\nreturn ['data' => %s, 'expires' => %d];\n",
            $key,
            var_export($data, true),
            $expires
        );

        $dir = dirname($file);
        if (!is_dir($dir) || !is_writable($dir)) {
            Emmsg::error('缓存目录无写入权限', '请设置目录权限为755：' . $dir);
        }

        $result = file_put_contents($file, $content, LOCK_EX) !== false;

        if ($result && $group !== '') {
            self::addToGroup($key, $group);
        }

        return $result;
    }

    /**
     * 删除指定缓存。
     */
    public static function delete(string $key): bool
    {
        self::ensureInit();
        $file = self::path($key);

        if (is_file($file)) {
            @unlink($file);
        }

        return true;
    }

    /**
     * 删除指定分组下的所有缓存。
     */
    public static function deleteGroup(string $group): bool
    {
        self::ensureInit();
        $indexFile = self::groupIndexPath($group);

        if (!is_file($indexFile)) {
            return true;
        }

        /** @var array<string> $keys */
        $keys = require $indexFile;

        foreach ($keys as $key) {
            $file = self::path($key);
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
    public static function clear(): bool
    {
        self::ensureInit();

        $files = glob(self::$directory . '/*.php');
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
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * 记忆模式：缓存存在则返回，不存在则执行回调并缓存结果。
     *
     * @param string   $key      缓存键
     * @param string   $group    分组
     * @param callable $callback  回调查库，返回缓存数据
     * @param int      $ttl      有效期（秒）
     * @return mixed
     */
    public static function remember(string $key, string $group, callable $callback, int $ttl = 0)
    {
        $data = self::get($key);

        if ($data !== null) {
            return $data;
        }

        $data = $callback();
        self::set($key, $data, $ttl, $group);

        return $data;
    }

    /**
     * 获取缓存文件路径。
     */
    private static function path(string $key): string
    {
        // 安全处理：替换 Windows 不允许的非法字符
        $safePrefix = preg_replace('/[^a-zA-Z0-9_.-]/', '_', self::$prefix);
        $safeKey = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $key);
        return self::$directory . '/' . $safePrefix . $safeKey . '.php';
    }

    /**
     * 获取分组索引文件路径。
     */
    private static function groupIndexPath(string $group): string
    {
        return self::$directory . '/.group_' . md5($group) . '.php';
    }

    /**
     * 将 key 加入分组索引。
     */
    private static function addToGroup(string $key, string $group): void
    {
        $indexFile = self::groupIndexPath($group);
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
    private static function ensureInit(): void
    {
        if (!isset(self::$directory)) {
            self::init();
        }
    }
}
