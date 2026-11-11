<?php

declare(strict_types=1);

/**
 * 前台视图渲染（兼容门面）。
 *
 * 实际实现已迁到 App\Support\View 实例服务；这里保留 getInstance() 和原有实例
 * API，控制器、模板和既有调用点都不用改。
 */
final class View
{
    /** @var View */
    private static $instance;

    /** @var \App\Support\View */
    private $service;

    private function __construct()
    {
        if (!class_exists(\App\Support\View::class)) {
            $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
            require_once $root . '/app/Support/View.php';
        }
        $this->service = new \App\Support\View();
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
     * 获取内部视图服务实例（供容器注册使用）。
     */
    public static function service(): \App\Support\View
    {
        return self::getInstance()->service;
    }

    /**
     * 设置视图数据。
     *
     * @param string|array<string, mixed> $key
     * @param mixed $value
     */
    public function setData($key, $value = null): void
    {
        $this->service->setData($key, $value);
    }

    /**
     * 获取视图数据。
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->service->getData();
    }

    /**
     * 批量设置视图数据。
     *
     * @param array<string, mixed> $data
     */
    public function assign(array $data): void
    {
        $this->service->assign($data);
    }

    /**
     * 设置页面标题。
     */
    public function setTitle(string $title): void
    {
        $this->service->setTitle($title);
    }

    /**
     * 获取页面标题。
     */
    public function getTitle(): string
    {
        return $this->service->getTitle();
    }

    /**
     * 设置当前主题。
     */
    public function setTheme(string $theme): void
    {
        $this->service->setTheme($theme);
    }

    /**
     * 获取当前主题。
     */
    public function getTheme(): string
    {
        return $this->service->getTheme();
    }

    /**
     * 渲染并输出完整页面。
     *
     * @param array<string, mixed> $bodyData
     */
    public function render(string $bodyTemplate, array $bodyData = []): void
    {
        $this->service->render($bodyTemplate, $bodyData);
    }

    /**
     * 渲染「独立页」：不套主题 header/footer。
     *
     * @param array<string, mixed> $bodyData
     */
    public function renderStandalone(string $bodyTemplate, array $bodyData = []): void
    {
        $this->service->renderStandalone($bodyTemplate, $bodyData);
    }

    /**
     * 输出完整页面（已渲染则直接返回）。
     */
    public function output(): void
    {
        $this->service->output();
    }

    /**
     * 渲染指定模板并返回 HTML 字符串（不输出）。
     *
     * @param array<string, mixed> $data
     */
    public function fetch(string $template, array $data = []): string
    {
        return $this->service->fetch($template, $data);
    }

    /**
     * 输出 JSON 成功响应并结束。
     *
     * @param array<string, mixed> $data
     */
    public static function jsonSuccess(string $msg = '操作成功', array $data = []): void
    {
        Response::success($msg, $data);
    }

    /**
     * 输出 JSON 失败响应并结束。
     *
     * @param array<string, mixed> $data
     */
    public static function jsonError(string $msg = '操作失败', array $data = []): void
    {
        Response::error($msg, $data);
    }
}
