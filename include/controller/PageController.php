<?php

declare(strict_types=1);

/**
 * 自定义页面控制器（WordPress 式 Pages）。
 *
 * 路由：/p/{slug}  （由 Dispatcher::tryPrettyRoute 识别）
 *
 * 模板优先级（优先用专属模板，次选"指定模板"，最后通用 page.php）：
 *   1. content/template/<current_theme>/page-{slug}.php           —— 针对某个特定页的专属模板
 *   2. content/template/<current_theme>/page-{template_name}.php  —— 后台填了 template_name 就用
 *   3. content/template/<current_theme>/page.php                  —— 主题自带的通用模板
 *   4. content/template/default/page.php                          —— 内置兜底
 */
class PageController extends BaseController
{
    /**
     * 页面详情。
     */
    public function _detail(): void
    {
        $slug = (string) $this->getArg('slug', '');
        $slug = strtolower(trim($slug));

        $page = null;
        if ($slug !== '' && preg_match('/^[a-z0-9_\-]+$/', $slug)) {
            // 限定到当前 scope：主站只能看主站页面，商户只能看自己的页面
            $page = PageModel::getBySlug($slug, MerchantContext::currentId());
        }

        if ($page === null) {
            // 未找到 / 未发布 → 404
            $this->dispatcher->render404('页面不存在: /p/' . $slug);
            return;
        }

        // 浏览量 +1
        PageModel::incrementViews((int) $page['id']);

        // 确定模板名（View::renderBody 会先查主题目录，找不到再查 default/）
        $templateName = $this->resolveTemplate(
            $slug,
            (string) ($page['template_name'] ?? '')
        );

        // SEO 标题；seo_keywords / seo_description 随 $page 数组传给模板，
        // 由主题的 header.php 按需读取渲染 <meta>
        $seoTitle = trim((string) ($page['seo_title'] ?? ''));
        $this->view->setTitle($seoTitle !== '' ? $seoTitle : (string) $page['title']);

        $this->view->setData([
            'page' => [
                'id'              => (int) $page['id'],
                'title'           => (string) $page['title'],
                'slug'            => (string) $page['slug'],
                'content'         => (string) ($page['content'] ?? ''),
                'template_name'   => (string) ($page['template_name'] ?? ''),
                'seo_title'       => (string) ($page['seo_title'] ?? ''),
                'seo_keywords'    => (string) ($page['seo_keywords'] ?? ''),
                'seo_description' => (string) ($page['seo_description'] ?? ''),
                'views'           => (int) ($page['views_count'] ?? 0) + 1,
                'created_at'      => (string) $page['created_at'],
                'updated_at'      => (string) $page['updated_at'],
            ],
        ]);

        $this->view->render($templateName);
    }

    /**
     * 按优先级返回第一个存在的模板名（不含 .php 扩展名）。
     * 查主题目录 + default 目录都算存在。
     */
    private function resolveTemplate(string $slug, string $templateName): string
    {
        $theme = $this->view->getTheme() ?: 'default';

        $candidates = ['page-' . $slug];
        if ($templateName !== '') {
            $candidates[] = 'page-' . $templateName;
        }
        $candidates[] = 'page';

        foreach ($candidates as $name) {
            $themePath   = EM_ROOT . '/content/template/' . $theme   . '/' . $name . '.php';
            $defaultPath = EM_ROOT . '/content/template/default/'          . $name . '.php';
            if (is_file($themePath) || is_file($defaultPath)) {
                return $name;
            }
        }
        // 理论上不会走到这里（default/page.php 必存在，由本次改动一并落地）
        return 'page';
    }
}
