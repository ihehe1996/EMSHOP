<?php

declare(strict_types=1);

/**
 * 容器解析助手（Laravel 的 app() 对应物）。
 *
 * 模型 / 服务改成实例类后，调用点用 `app(GoodsModel::class)->getById($id)` 取实例；
 * 同一请求内按类缓存，避免循环里反复构造。
 */

if (!function_exists('app')) {
    /**
     * 从容器解析服务 / 模型实例（请求内单例）。
     *
     * @param string $class 类名
     * @return object
     */
    function app(string $class)
    {
        static $instances = [];

        if (!isset($instances[$class])) {
            $instances[$class] = \App\Core\Application::instance()->container()->make($class);
        }

        return $instances[$class];
    }
}
