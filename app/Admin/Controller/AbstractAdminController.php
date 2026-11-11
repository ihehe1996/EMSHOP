<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 后台控制器公共基类（不含登录校验）。
 *
 * 只提供视图渲染，供登录页等公开页面复用；需要登录的后台页面继承 BaseAdminController。
 */
abstract class AbstractAdminController
{
    /**
     * 渲染 app/Admin/View 下的模板。
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = []): void
    {
        $relative = str_replace('\\', '/', trim($view, '/'));
        if ($relative === '' || preg_match('#[^a-zA-Z0-9_/-]#', $relative)) {
            throw new \InvalidArgumentException('非法视图名：' . $view);
        }

        $file = dirname(__DIR__) . '/View/' . $relative . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('视图不存在：' . $relative);
        }

        header('Content-Type: text/html; charset=utf-8');
        extract($data, EXTR_SKIP);
        require $file;
    }
}
