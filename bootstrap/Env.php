<?php

declare(strict_types=1);

/**
 * 轻量 .env 加载器（无 Composer 依赖）。
 *
 * 只用于开发环境覆盖 config.php 的默认值：
 *   - 只补「系统里还没设置」的变量，真实环境变量优先级最高
 *   - 支持 KEY=VALUE、export KEY=VALUE、单/双引号包裹的值
 *   - 不解析行内注释（值里的 # 原样保留）
 *
 * 注意：这个类在 init.php 注册自动加载器之前就会被 require，所以这里不依赖自动加载。
 */
final class Env
{
    /**
     * 读取配置值：系统环境变量 > 本次请求 .env > $default。
     *
     * 环境变量天然都是字符串，这里按 $default 的类型转换，
     * 让 config.php 不用到处写 (int) / (string)。
     *
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default)
    {
        $value = getenv($key);
        if ($value === false && isset($_ENV[$key])) {
            $value = $_ENV[$key];
        }
        if ($value === false && isset($_SERVER[$key])) {
            $value = $_SERVER[$key];
        }

        if ($value === false) {
            return $default;
        }

        if (is_int($default)) {
            return (int) $value;
        }
        if (is_float($default)) {
            return (float) $value;
        }
        if (is_bool($default)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return (string) $value;
    }

    /**
     * 读取 $root/.env 并写入本次请求的 $_ENV / $_SERVER；文件不存在时什么都不做。
     *
     * 不用 putenv()：FPM 是常驻进程，putenv 的值会残留到后续请求，
     * 改完 .env 必须重启 FPM 才生效，反而更坑。
     */
    public static function load(string $root): void
    {
        $file = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        $first = true;

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($first) {
                // 去掉 UTF-8 BOM
                $line = ltrim($line, "\xEF\xBB\xBF");
                $first = false;
            }
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (!preg_match('/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $m)) {
                continue;
            }

            $key = $m[1];
            $value = trim($m[2]);

            $len = strlen($value);
            if ($len >= 2) {
                $firstChar = $value[0];
                $lastChar = $value[$len - 1];
                if (($firstChar === '"' && $lastChar === '"') || ($firstChar === "'" && $lastChar === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            // 真实环境变量优先（getenv 读系统环境；$_ENV/$_SERVER 可能是本次请求已加载过的 .env）
            if (getenv($key) !== false || isset($_ENV[$key]) || isset($_SERVER[$key])) {
                continue;
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
