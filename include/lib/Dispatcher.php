<?php

declare(strict_types=1);

/**
 * 前台路由分发（兼容门面）。
 *
 * 实际实现已迁到 App\Http\Dispatcher 实例服务；这里保留 getInstance() 和原有
 * 实例 API，index.php、控制器、模板和插件的调用点都不用改。
 */
final class Dispatcher
{
    /** @var Dispatcher */
    private static $instance;

    /** @var \App\Http\Dispatcher */
    private $service;

    private function __construct()
    {
        if (!class_exists(\App\Http\Dispatcher::class)) {
            $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
            require_once $root . '/app/Http/Dispatcher.php';
        }
        $this->service = new \App\Http\Dispatcher();
    }

    /**
     * 获取单例实例。
     */
    public static function getInstance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 获取内部调度服务实例（供容器注册使用）。
     */
    public static function service(): \App\Http\Dispatcher
    {
        return self::getInstance()->service;
    }

    /**
     * 执行路由分发。
     */
    public function dispatch(): void
    {
        $this->service->dispatch();
    }

    /**
     * 渲染 404 页面。
     */
    public function render404(string $reason = ''): void
    {
        $this->service->render404($reason);
    }

    /**
     * 获取当前控制器名（URL 中的 c 参数）。
     */
    public function getController(): string
    {
        return $this->service->getController();
    }

    /**
     * 获取当前动作名。
     */
    public function getAction(): string
    {
        return $this->service->getAction();
    }

    /**
     * 获取 URL 路径参数。
     *
     * @return array<string, mixed>
     */
    public function getPathArgs(): array
    {
        return $this->service->getPathArgs();
    }

    /**
     * 获取指定路径参数。
     *
     * @param string|int $key
     * @param mixed $default
     * @return mixed
     */
    public function getArg($key, $default = null)
    {
        return $this->service->getArg($key, $default);
    }

    /**
     * 获取当前主题名。
     */
    public function getTheme(): string
    {
        return $this->service->getTheme();
    }

    /**
     * 获取当前视图实例。
     */
    public function getView(): View
    {
        return $this->service->getView();
    }
}
