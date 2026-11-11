<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 免责协议查看页（/admin/disclaimer）。
 *
 * 正文 + 当前签署记录；数据由 DisclaimerService 提供。
 * 过渡期列表页仍套用旧后台框架壳（admin/view/index.php）。
 */
final class DisclaimerController extends BaseAdminController
{
    public function index(): void
    {
        if (\Request::isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('disclaimer');
            echo '</div>';
            return;
        }

        require_once EM_ROOT . '/include/lib/AdminLang.php';

        $user = $this->user();
        $siteName = (string) \Config::get('sitename', 'EMSHOP');
        $languages = (new \LanguageModel())->getEnabled();
        $emServerHardRestartPending = is_file(EM_ROOT . '/.server') && \WorkerHeartbeat::hostAlive();
        $adminContentView = dirname(__DIR__) . '/View/disclaimer.php';

        require EM_ROOT . '/admin/view/index.php';
    }
}
