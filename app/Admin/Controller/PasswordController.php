<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 修改密码弹窗（/admin/password）。
 *
 * GET: 渲染修改密码视图
 * POST: 校验旧密码后更新密码，并刷新当前会话
 *
 * 与旧 admin/password.php 行为一致。
 */
final class PasswordController extends BaseAdminController
{
    public function index(): void
    {
        if ($this->request->isPost()) {
            $this->handlePost();
            return;
        }

        $this->render('popup/password');
    }

    /**
     * 处理修改密码请求。
     */
    private function handlePost(): void
    {
        try {
            $csrf = (string) $this->input->post('csrf_token', '');
            if (!$this->csrf->validate($csrf)) {
                $this->response->error('请求已失效，请刷新页面后重试');
            }

            $userModel = new \App\Model\UserModel();
            $admin = $this->user();
            $userId = (int) ($admin['id'] ?? 0);
            $oldPassword = (string) $this->input->post('old_password', '');
            $newPassword = (string) $this->input->post('new_password', '');
            $confirmPassword = (string) $this->input->post('confirm_password', '');

            if ($oldPassword === '' || $newPassword === '') {
                $this->response->error('密码不能为空');
            }
            if ($newPassword !== $confirmPassword) {
                $this->response->error('两次输入的新密码不一致');
            }
            if (mb_strlen($newPassword) < 6) {
                $this->response->error('新密码长度不能少于6位');
            }

            $currentUser = $userModel->findById($userId);
            if ($currentUser === null) {
                $this->response->error('用户不存在');
            }

            $hasher = new \PasswordHash(8, true);
            if (!$hasher->CheckPassword($oldPassword, (string) $currentUser['password'])) {
                $this->response->error('旧密码不正确');
            }

            $userModel->updatePassword($userId, $hasher->HashPassword($newPassword));

            // 密码指纹变了，刷新当前会话，避免把自己也踢下线；其它终端会自动失效
            $freshUser = $userModel->findById($userId);
            if ($freshUser !== null) {
                $auth = new \App\Service\AuthService();
                $auth->refreshSession($freshUser);
            }

            $newToken = $this->csrf->refresh();
            $this->response->success('密码修改成功', ['csrf_token' => $newToken]);
        } catch (\RuntimeException $e) {
            $this->response->error($e->getMessage());
        } catch (\Throwable $e) {
            $this->response->error('系统繁忙，请稍后再试');
        }
    }
}
