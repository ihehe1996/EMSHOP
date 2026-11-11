<?php

declare(strict_types=1);

namespace App\Core;

/**
 * 极简服务容器（内部基础设施）。
 *
 * 故意保持小而明确：只负责注册和解析服务，不关心业务。
 * 旧代码仍然使用全局类 / 静态门面，这个容器只给新模块使用。
 */
final class Container
{
    /** @var array<string, mixed> 已解析的实例 */
    private $instances = [];

    /** @var array<string, callable> 每次 make 都新建的绑定 */
    private $bindings = [];

    /** @var array<string, callable> 首次 make 后缓存的单例 */
    private $singletons = [];

    /**
     * 注册一个现成实例。
     *
     * @param mixed $instance
     */
    public function instance(string $id, $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /**
     * 注册一个工厂，每次 make 都调用一次。
     */
    public function bind(string $id, callable $factory): void
    {
        $this->bindings[$id] = $factory;
        unset($this->instances[$id], $this->singletons[$id]);
    }

    /**
     * 注册一个工厂，只解析一次。
     */
    public function singleton(string $id, callable $factory): void
    {
        $this->singletons[$id] = $factory;
        unset($this->instances[$id], $this->bindings[$id]);
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id])
            || isset($this->bindings[$id])
            || isset($this->singletons[$id]);
    }

    /**
     * @return mixed
     */
    public function make(string $id)
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (isset($this->singletons[$id])) {
            $this->instances[$id] = ($this->singletons[$id])($this);
            return $this->instances[$id];
        }

        if (isset($this->bindings[$id])) {
            return ($this->bindings[$id])($this);
        }

        throw new \RuntimeException('Container: 未注册的服务 ' . $id);
    }
}
