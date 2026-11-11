<?php

declare(strict_types=1);

namespace App\Core;

/**
 * 应用内核骨架。
 *
 * 当前只负责持有容器，不接管现有请求流程；init.php 末尾创建一次。
 * 后续新模块通过 Application::instance()->container() 获取依赖。
 */
final class Application
{
    /** @var Application|null */
    private static $instance;

    /** @var Container */
    private $container;

    public function __construct(?Container $container = null)
    {
        $this->container = $container ?: new Container();
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(self::class, $this);

        if (self::$instance === null) {
            self::$instance = $this;
        }
    }

    /**
     * 获取当前进程内的应用实例；没有创建过时按需创建。
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function container(): Container
    {
        return $this->container;
    }
}
