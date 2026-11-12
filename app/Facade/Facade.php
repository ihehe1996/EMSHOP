<?php

declare(strict_types=1);

namespace App\Facade;

/**
 * 门面基类（Laravel 风格）。
 *
 * 子类实现 service() 返回容器里的服务单例，静态调用和门面实例调用都转发过去：
 *   Cache::get('k')          → App\Support\Cache::get('k')
 *   View::getInstance()->render(...) → App\Support\View->render(...)
 *
 * 全局短名（Cache / Config / DB 用法等）由 bootstrap/aliases.php 统一注册成类别名，
 * 模板、插件和旧代码不需要改调用方式。
 */
abstract class Facade
{
    /**
     * 返回被代理的服务实例（子类实现）。
     */
    abstract public static function service(): object;

    /**
     * 静态调用转发到服务实例。
     *
     * @param array<int, mixed> $arguments
     * @return mixed
     */
    public static function __callStatic(string $method, array $arguments)
    {
        return static::forward($method, $arguments);
    }

    /**
     * 门面实例上的调用同样转发（例如 View::getInstance()->setData()）。
     *
     * @param array<int, mixed> $arguments
     * @return mixed
     */
    public function __call(string $method, array $arguments)
    {
        return static::forward($method, $arguments);
    }

    /**
     * @param array<int, mixed> $arguments
     * @return mixed
     */
    private static function forward(string $method, array $arguments)
    {
        $service = static::service();

        if (!method_exists($service, $method)) {
            throw new \BadMethodCallException(static::class . '::' . $method . '() 未定义');
        }

        return $service->{$method}(...$arguments);
    }
}
