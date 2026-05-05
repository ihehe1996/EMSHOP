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
     * @var bool
     */
    private static $registered = false;

    /**
     * 注册自动加载。
     *
     * @param array<int, string> $directories
     */
    public static function register(array $directories): void
    {
        self::$directories = $directories;

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
        foreach (self::$directories as $directory) {
            $file = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $class . '.php';
            if (is_file($file)) {
                require_once $file;
                return;
            }
        }
    }
}
