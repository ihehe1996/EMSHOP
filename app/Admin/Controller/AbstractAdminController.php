<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 后台控制器公共基类（不含登录校验）。
 *
 * 只提供视图渲染，供登录页等公开页面复用；需要登录的后台页面继承 BaseAdminController。
 */
abstract class AbstractAdminController extends \App\Core\AbstractController
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

        // 同一次请求会多次 render（header / 设置页 / footer）。设置页输出超过 PHP
        // 输出缓冲区大小后 headers 已经发出，再调 header() 会报
        // "Cannot modify header information" 警告（所有插件设置页都会触发）。
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        extract($data, EXTR_SKIP);
        require $file;
    }
}
