<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 后台系统日志页（占位，/admin/logs）。
 *
 * 当前只渲染占位卡片，真实日志见 /admin/system_log。
 * 过渡期列表页仍套用旧后台框架壳（admin/view/index.php）。
 */
final class LogsController extends BaseAdminController
{
    public function index(): void
    {
        if (\Request::isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('logs');
            echo '</div>';
            return;
        }

        require_once EM_ROOT . '/include/lib/AdminLang.php';

        $user = $this->user();
        $siteName = (string) \Config::get('sitename', 'EMSHOP');
        $languages = (new \LanguageModel())->getEnabled();
        $emServerHardRestartPending = is_file(EM_ROOT . '/.server') && \WorkerHeartbeat::hostAlive();
        $adminContentView = dirname(__DIR__) . '/View/logs.php';

        require EM_ROOT . '/admin/view/index.php';
    }
}
