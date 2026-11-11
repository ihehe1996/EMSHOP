<?php

declare(strict_types=1);

/**
 * 自动加载器。
 *
 * 按类名到约定目录中查找同名文件，避免每个入口文件手动 require_once。
 */
final class Autoloader
{
    /**
     * @var array<int, string>
     */
    private static $directories = [];

    /**
     * PSR-4 命名空间映射：前缀 => 基础目录。
     *
     * @var array<string, string>
     */
    private static $namespaces = [];

    /**
     * @var bool
     */
    private static $registered = false;

    /**
     * 注册自动加载。
     *
     * $namespaces 是可选的 PSR-4 映射；不传时默认把 App\ 映射到项目根目录的 app/。
     *
     * @param array<int, string> $directories
     * @param array<string, string> $namespaces
     */
    public static function register(array $directories, array $namespaces = []): void
    {
        self::$directories = $directories;
        self::$namespaces = $namespaces + self::$namespaces;

        if (defined('EM_ROOT')) {
            // 新内核使用 App\ 命名空间；旧的全局类仍按类名找文件。
            self::$namespaces += ['App\\' => EM_ROOT . '/app'];
        }

        if (self::$registered) {
            return;
        }

        spl_autoload_register([self::class, 'load']);
        self::$registered = true;
    }

    /**
     * 根据类名加载文件。
     */
    public static function load(string $class): void
    {
        // PSR-4：App\Core\Application -> app/Core/Application.php
        foreach (self::$namespaces as $prefix => $baseDir) {
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                continue;
            }

            $relative = substr($class, strlen($prefix));
            if ($relative === '') {
                return;
            }

            $file = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
            return;
        }

        // 旧规则：全局类名 = 文件名
        foreach (self::$directories as $directory) {
            $file = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $class . '.php';
            if (is_file($file)) {
                require_once $file;
                return;
            }
        }
    }
}
