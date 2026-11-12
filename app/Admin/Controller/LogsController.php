<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 后台系统日志页（占位，/admin/logs）。
 *
 * 当前只渲染占位卡片，真实日志见 /admin/system_log。
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class LogsController extends BaseAdminController
{
    public function index(): void
    {
        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('logs');
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/logs.php', $this->user(), get_defined_vars());
    }
}
