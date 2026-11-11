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
        // 框架壳上的签署弹窗提交到这里（旧入口是 admin/index.php?_action=disclaimer_accept）
        if (\Request::isPost()) {
            $this->handleAccept();
            return;
        }

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

    /**
     * 记录协议版本、签署管理员、时间与 IP。
     */
    private function handleAccept(): void
    {
        if ((string) \Input::post('_action', '') !== 'disclaimer_accept') {
            \Response::error('未知操作');
        }

        if (!\Csrf::validate((string) \Input::post('csrf_token', ''))) {
            \Response::error('请求已失效，请刷新页面后重试');
        }
        if ((string) \Input::post('agree', '0') !== '1') {
            \Response::error('请先阅读并同意免责协议');
        }

        $admin = $this->user();
        $saved = \DisclaimerService::accept(
            (int) $admin['id'],
            (string) $admin['username'],
            \RateLimit::clientIp()
        );
        if (!$saved) {
            \Response::error('签署记录保存失败，请稍后重试');
        }

        \Response::success('已签署');
    }
}
