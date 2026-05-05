<?php

declare(strict_types=1);

/**
 * 前台视图渲染类。
 *
 * 负责页面整体渲染：
 * - 加载 header.php / footer.php 布局文件
 * - 调用前台钩子（front_header / front_footer）
 * - 渲染 body 模板（由控制器指定）
 * - 支持 PJAX 局部刷新
 *
 * 使用方式（控制器中）：
 *   $this->view->setTitle('商品列表');
 *   $this->view->setData(['goods_list' => $goods]);
 *   $this->view->render('goods_list');
 */
final class View
{
    /** @var View */
    private static $instance;

    /** @var string 当前主题 */
    private string $theme = '';

    /** @var array<string, mixed> 视图数据（渲染时 extract 为变量） */
    private array $data = [];

    /** @var string 页面标题 */
    private string $pageTitle = '';

    /** @var bool 是否已输出，防止重复渲染 */
    private bool $rendered = false;

    /** @var bool 是否为 PJAX 请求 */
    private bool $isPjax = false;

    private function __construct()
    {
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

    // ============================================================
    // 数据设置
    // ============================================================

    /**
     * 设置视图数据。
     *
     * @param string|array<string, mixed> $key
     * @param mixed $value
     */
    public function setData($key, $value = null): void
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }
    }

    /**
     * 获取视图数据。
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * 批量设置视图数据。
     *
     * @param array<string, mixed> $data
     */
    public function assign(array $data): void
    {
        $this->data = array_merge($this->data, $data);
    }

    /**
     * 设置页面标题。
     */
    public function setTitle(string $title): void
    {
        $this->pageTitle = $title;
    }

    /**
     * 获取页面标题。
     */
    public function getTitle(): string
    {
        return $this->pageTitle;
    }

    /**
     * 设置当前主题。
     */
    public function setTheme(string $theme): void
    {
        $this->theme = $theme;
    }

    /**
     * 获取当前主题。
     */
    public function getTheme(): string
    {
        return $this->theme;
    }

    // ============================================================
    // 模块文件加载
    // ============================================================

    /**
     * 加载模板模块文件（module.php）。
     *
     * 该文件用于在模板渲染前执行模板专用逻辑，例如生成导航 HTML。
     * 模块文件可通过 $this（View 实例）访问以下方法：
     *   $this->assign($key, $value)  - 注入模板变量
     *   $this->getData()              - 获取已有数据
     *
     * 也可通过全局函数获取 Dispatcher：
     *   Dispatcher::getInstance()->getController()
     */
    private function loadModule(): void
    {
        $moduleFile = EM_ROOT . '/content/template/' . $this->theme . '/module.php';
        if (!is_file($moduleFile)) {
            return;
        }

        // 将 $this（View 实例）注入模块文件，使模块可调用 $this->assign() 等方法
        include $moduleFile;
    }

    // ============================================================
    // 渲染方法
    // ============================================================

    /**
     * 渲染并输出完整页面。
     *
     * 调用链：module 逻辑 → header 布局 → front_header 钩子 → body 模板 → front_footer 钩子 → footer 布局
     *
     * @param string $bodyTemplate body 模板文件名（不含 .php），如 'goods_list'、'page'
     * @param array<string, mixed> $bodyData body 模板的附加数据
     */
    public function render(string $bodyTemplate, array $bodyData = []): void
    {
        if ($this->rendered) {
            return;
        }
        $this->rendered = true;

        // 检测 PJAX 请求
        $this->isPjax = Request::isPjax();

        // 1. 加载模块文件（模板专用逻辑，可生成导航等变量）
        $this->loadModule();

        // 合并 body 数据
        $allData = array_merge($this->data, $bodyData, [
            'page_title' => $this->pageTitle,
            'site_name'  => $this->data['site_name'] ?? 'EMSHOP',
            'site_url'   => $this->data['site_url'] ?? '',
            '_theme'     => $this->theme,
        ]);

        // PJAX 请求只输出 body 内容
        if ($this->isPjax) {
            $this->renderPjax($bodyTemplate, $allData);
            return;
        }

        // AJAX 请求（业务 JSON 由具体控制器直接 Response::json() 返回，render 通常不会被调用）
        if ($this->isAjax()) {
            return;
        }

        // 完整页面输出
        $this->renderFullPage($bodyTemplate, $allData);
    }

    /**
     * 渲染完整页面（header + body + footer）。
     *
     * @param string $bodyTemplate
     * @param array<string, mixed> $data
     */
    private function renderFullPage(string $bodyTemplate, array $data): void
    {
        // 1. 渲染头部布局
        $this->renderLayout('header', $data);

        // 2. 渲染 body 内容
        $this->renderBody($bodyTemplate, $data);

        // 3. 渲染底部布局
        $this->renderLayout('footer', $data);
    }

    /**
     * 渲染 PJAX 内容（仅 body，带 PJAX 容器包裹）。
     *
     * 输出格式：<div id="main">body 内容</div>
     * 这样前端 jquery.pjax.js 收到后可直接替换掉页面上的 #main div，
     * 而无需关心 header/footer 是否在响应中。
     *
     * @param string $bodyTemplate
     * @param array<string, mixed> $data
     */
    private function renderPjax(string $bodyTemplate, array $data): void
    {
        if (!headers_sent()) {
            header('X-PJAX-URL: ' . $this->getCurrentUrl());
            header('X-PJAX-Title: ' . rawurlencode($this->pageTitle ?: ($data['site_name'] ?? 'EMSHOP')));
            // PJAX 只替换 #main 的 innerHTML，不会更新容器自身的属性；
            // 把 nav_id 通过响应头送到前端，让 JS 同步写回 #main[data-nav-id]。
            header('X-PJAX-Nav-Id: ' . rawurlencode((string) ($data['nav_id'] ?? '')));
            // 与 module.php 中 $currentPath 一致，供 PJAX 后按 data-nav-path 点亮「页面 / 伪静态」类导航
            header('X-PJAX-Current-Path: ' . rawurlencode((string) ($data['nav_current_path'] ?? '')));
            header('Content-Type: text/html; charset=utf-8');
        }

        echo '<div id="main">';
        $this->renderBody($bodyTemplate, $data);
        echo '</div>';
    }

    /**
     * 渲染 body 模板。
     *
     * @param string $template body 模板文件名（不含路径和 .php）
     * @param array<string, mixed> $data
     */
    private function renderBody(string $template, array $data): void
    {
        $tplFile = $this->findTemplate($template . '.php');
        if ($tplFile === null) {
            echo '<div style="padding:40px; text-align:center; color:#999;">模板不存在: ' . htmlspecialchars($template) . '</div>';
            return;
        }

        extract($data);
        include $tplFile;
    }

    /**
     * 渲染布局文件（header.php / footer.php）。
     *
     * @param string $layout header | footer
     * @param array<string, mixed> $data
     */
    private function renderLayout(string $layout, array $data): void
    {
        $tplFile = $this->findTemplate($layout . '.php');
        if ($tplFile === null) {
            return;
        }
        extract($data);
        include $tplFile;
    }

    /**
     * 查找模板文件，优先当前主题，降级到 default 主题。
     *
     * @return string|null 文件路径，未找到返回 null
     */
    private function findTemplate(string $file): ?string
    {
        $path = EM_ROOT . '/content/template/' . $this->theme . '/' . $file;
        if (is_file($path)) {
            return $path;
        }

        $path = EM_ROOT . '/content/template/default/' . $file;
        if (is_file($path)) {
            return $path;
        }

        return null;
    }

    /**
     * 判断是否为 AJAX 请求。
     */
    private function isAjax(): bool
    {
        $xRequestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return strtolower($xRequestedWith) === 'xmlhttprequest';
    }

    /**
     * 获取当前页面 URL。
     */
    private function getCurrentUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return $protocol . '://' . $host . $uri;
    }

    // ============================================================
    // 静态快捷方法
    // ============================================================

    /**
     * 输出完整页面。
     *
     * 在控制器已调用 render() 后再次调用则直接返回（防止重复输出）。
     * 在控制器未调用 render() 时，尝试渲染已设置的 body 模板。
     */
    public function output(): void
    {
        if ($this->rendered) {
            return;
        }
    }

    /**
     * 渲染指定模板并返回 HTML 字符串（不输出）。
     *
     * @param string $template 模板文件名
     * @param array<string, mixed> $data
     * @return string
     */
    public function fetch(string $template, array $data = []): string
    {
        ob_start();
        $this->render($template, $data);
        return ob_get_clean();
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
