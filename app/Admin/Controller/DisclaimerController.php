<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 免责协议查看页（/admin/disclaimer）。
 *
 * 正文 + 当前签署记录；数据由 DisclaimerService 提供。
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class DisclaimerController extends BaseAdminController
{
    public function index(): void
    {
        // 框架壳上的签署弹窗提交到这里（旧入口是 admin/index.php?_action=disclaimer_accept）
        if ($this->request->isPost()) {
            $this->handleAccept();
            return;
        }

        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('disclaimer');
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/disclaimer.php', $this->user(), get_defined_vars());
    }

    /**
     * 记录协议版本、签署管理员、时间与 IP。
     */
    private function handleAccept(): void
    {
        if ((string) $this->input->post('_action', '') !== 'disclaimer_accept') {
            $this->response->error('未知操作');
        }

        if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
            $this->response->error('请求已失效，请刷新页面后重试');
        }
        if ((string) $this->input->post('agree', '0') !== '1') {
            $this->response->error('请先阅读并同意免责协议');
        }

        $admin = $this->user();
        $saved = app(\App\Service\DisclaimerService::class)->accept(
            (int) $admin['id'],
            (string) $admin['username'],
            $this->rateLimit->clientIp()
        );
        if (!$saved) {
            $this->response->error('签署记录保存失败，请稍后重试');
        }

        $this->response->success('已签署');
    }
}
