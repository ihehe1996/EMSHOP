<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 前台视图渲染服务（实例实现）。
 *
 * 有状态：主题、视图数据、标题、是否已渲染、PJAX 标记。兼容门面见
 * app/Facade/View.php，门面保留 getInstance() 并代理实例方法。
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
    /** @var string 当前主题 */
    private $theme = '';

    /** @var array<string, mixed> 视图数据（渲染时 extract 为变量） */
    private $data = [];

    /** @var string 页面标题 */
    private $pageTitle = '';

    /** @var bool 是否已输出，防止重复渲染 */
    private $rendered = false;

    /** @var bool 是否为 PJAX 请求 */
    private $isPjax = false;

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
        $this->isPjax = \Request::isPjax();

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
     * 渲染「独立页」：模板自带完整 HTML 骨架，不套主题的 header/footer。
     *
     * 给登录 / 注册这类**公共账号页**用：它们自成一套外观（见 content/static/css/auth.css），
     * 不跟主题走 —— 所以既不渲染主题的 header/footer，也不加载主题 module.php
     * （顺带省掉为这两页查导航），页面要的变量自己从核心配置里读。
     *
     * @param string $bodyTemplate body 模板名（不含 .php）
     * @param array<string, mixed> $bodyData
     */
    public function renderStandalone(string $bodyTemplate, array $bodyData = []): void
    {
        if ($this->rendered) {
            return;
        }
        $this->rendered = true;

        $allData = array_merge($this->data, $bodyData, [
            'page_title' => $this->pageTitle,
            'site_name'  => $this->data['site_name'] ?? 'EMSHOP',
            'site_url'   => $this->data['site_url'] ?? '',
            '_theme'     => $this->theme,
        ]);

        // PJAX 请求不能拿整页文档去替换 #main（响应里没有 #main，会把页面清空）：
        // 回一小段脚本让它整页跳转，等价于「这条链接不做 PJAX」。
        if (\Request::isPjax()) {
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=utf-8');
            }
            echo '<script>window.location.href=' . json_encode($this->getCurrentUrl(), JSON_UNESCAPED_SLASHES) . ';</script>';
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        // 独立页也带完整 HTML 骨架，同样在 <body> 之后注入未授权横幅
        ob_start();
        $this->renderBody($bodyTemplate, $allData);
        $html = $this->injectLicenseBanner((string) ob_get_clean());
        echo $this->injectVersionLog($html);
    }

    /**
     * 渲染完整页面（header + body + footer）。
     *
     * @param string $bodyTemplate
     * @param array<string, mixed> $data
     */
    private function renderFullPage(string $bodyTemplate, array $data): void
    {
        // 1. 渲染头部布局（未授权横幅要插在 <body> 之后、站点 header 之前，
        //    所以先把 header 单独缓冲一下再注入）
        ob_start();
        $this->renderLayout('header', $data);
        $headerHtml = $this->injectLicenseBanner((string) ob_get_clean());
        echo $this->injectVersionLog($headerHtml);

        // 2. 头部后置挂载点：适合插入全站浮层、全局脚本等
        \doAction('front_header');

        // 3. 渲染 body 内容
        $this->renderBody($bodyTemplate, $data);

        // 4. 底部前挂载点：适合插入悬浮按钮、统计脚本等
        \doAction('front_footer');

        // 5. 渲染底部布局
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
        // PJAX 只替换 #main 的 innerHTML：需要随片段一起下发的内容（例如演示工具条）挂到这里，
        // 避免整页运行时输出在 #main 外、PJAX 后丢失或导致样式注入顺序漂移。
        \doAction('front_pjax_main_inner');
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
     * 把未授权横幅插到 HTML 的 `<body ...>` 之后（没有 body 时追加到末尾）。
     */
    private function injectLicenseBanner(string $html): string
    {
        $banner = $this->licenseBannerHtml();
        if ($banner === '') {
            return $html;
        }

        $pos = stripos($html, '<body');
        if ($pos === false) {
            return $html . $banner;
        }
        $gt = strpos($html, '>', $pos);
        if ($gt === false) {
            return $html . $banner;
        }

        return substr($html, 0, $gt + 1) . $banner . substr($html, $gt + 1);
    }

    /**
     * 在 `</head>` 前插入版本号 + 授权等级日志，前台浏览器控制台可看到。
     *
     * 纯前端展示用，不参与任何业务判断；没有 EM_VERSION（极老的包）时直接跳过。
     * 等级只读本地 Config（currentLevel() 不联网），不拖慢前台。
     */
    private function injectVersionLog(string $html): string
    {
        if (!defined('EM_VERSION') || (string) EM_VERSION === '') {
            return $html;
        }

        $level      = 'none';
        $levelLabel = '未授权';
        if (class_exists(\App\Service\LicenseService::class)) {
            $level      = app(\App\Service\LicenseService::class)->currentLevel();
            $levelLabel = app(\App\Service\LicenseService::class)->levelLabel($level);
        }

        $levelStyles = [
            'none'    => 'background:#fef2f2;color:#dc2626',
            'vip'     => 'background:#eff6ff;color:#2563eb',
            'svip'    => 'background:#f5f3ff;color:#7c3aed',
            'supreme' => 'background:linear-gradient(135deg,#fbbf24,#d97706);color:#fff',
        ];
        $levelStyle = ($levelStyles[$level] ?? $levelStyles['none'])
            . ';border-radius:0 3px 3px 0;padding:2px 6px;font-weight:600';

        $badge = 'background:#4f46e5;color:#fff;border-radius:3px 0 0 3px;padding:2px 6px;font-weight:600';
        $ver   = 'background:#eef2ff;color:#4f46e5;padding:2px 6px;font-weight:600';

        $script = '<script>console.log('
            . json_encode('%c EMSHOP %c v' . (string) EM_VERSION . ' %c ' . $levelLabel . ' ', JSON_UNESCAPED_SLASHES)
            . ', ' . json_encode($badge, JSON_UNESCAPED_SLASHES)
            . ', ' . json_encode($ver, JSON_UNESCAPED_SLASHES)
            . ', ' . json_encode($levelStyle, JSON_UNESCAPED_SLASHES)
            . ');</script>';

        $pos = stripos($html, '</head>');
        if ($pos === false) {
            return $html . $script;
        }

        return substr($html, 0, $pos) . $script . substr($html, $pos);
    }

    /**
     * 未授权横幅的 HTML；已授权 / 用户今天已关闭 / 本次会话已确认时返回空串。
     *
     * 隐藏状态放在 Cookie 里（而不是 localStorage），这样服务端能直接判断，
     * 刷新时横幅根本不会输出，不会出现「先闪一下再消失」。
     */
    private function licenseBannerHtml(): string
    {
        // isActivated() 只读本地 Config（init.php 已经预热过），
        // 不发任何远程请求、不触发授权核对 —— 前台每次渲染都走这里，必须零网络开销。
        if (!class_exists(\App\Service\LicenseService::class) || app(\App\Service\LicenseService::class)->isActivated()) {
            return '';
        }

        $hidden = (string) ($_COOKIE['em_license_notice'] ?? '');
        if ($hidden === 'session') {
            return '';
        }
        if (strncmp($hidden, 'today:', 6) === 0 && substr($hidden, 6) === date('Y-m-d')) {
            return '';
        }

        ob_start();
        include EM_ROOT . '/app/Home/View/partials/license_banner.php';
        return (string) ob_get_clean();
    }

    /**
     * 模板文件名的历史别名：新名 => [旧名, ...]。
     *
     * 已改过名的：首页 goods_index.php → index.php、商品详情 goods.php → goods_content.php。
     * 自带旧名模板的主题（含已分发到应用商店的）不能因此失效，所以每层里**先找新名、再回落旧名**——
     * 关键是"同一层内回落"，旧主题自己的定制才不会被 default 的新模板顶掉。
     */
    private const TEMPLATE_ALIASES = [
        'index.php'         => ['goods_index.php'],
        'goods_content.php' => ['goods.php'],
        'blog_content.php'  => ['blog.php'],
        // 找回/重置密码原先在 include/view/auth/ 下，主题按老路径覆盖的仍能找到
        'forgot_password.php' => ['auth/forgot_password.php'],
        'reset_password.php'  => ['auth/reset_password.php'],
    ];

    /**
     * 查找模板文件，优先当前主题，降级到 default 主题，最后回落核心视图。
     *
     * @return string|null 文件路径，未找到返回 null
     */
    private function findTemplate(string $file): ?string
    {
        $names = array_merge([$file], self::TEMPLATE_ALIASES[$file] ?? []);

        $dirs = [
            EM_ROOT . '/content/template/' . $this->theme . '/',
            EM_ROOT . '/content/template/default/',
            // 公共视图（app/User/View/public/）：登录 / 注册 / 找回密码等账号页，全站共用一份。
            // 放在主题目录之后：主题里自带同名文件时仍优先用主题的（老主题定制不失效）。
            EM_ROOT . '/app/User/View/public/',
        ];

        foreach ($dirs as $dir) {
            foreach ($names as $name) {
                if (is_file($dir . $name)) {
                    return $dir . $name;
                }
            }
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
    public function jsonSuccess(string $msg = '操作成功', array $data = []): void
    {
        \Response::success($msg, $data);
    }

    /**
     * 输出 JSON 失败响应并结束。
     *
     * @param array<string, mixed> $data
     */
    public function jsonError(string $msg = '操作失败', array $data = []): void
    {
        \Response::error($msg, $data);
    }
}
