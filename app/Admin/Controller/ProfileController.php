<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 后台个人信息（/admin/profile）。
 *
 * 支持修改账号/昵称/邮箱/手机号、上传头像、选择头像、恢复默认头像。
 * 与旧 admin/profile.php 行为一致（动作参数是 POST 的 action，不是 _action）。
 *
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class ProfileController extends BaseAdminController
{
    public function index(): void
    {
        if ($this->request->isPost()) {
            $this->handlePost();
            return;
        }

        $this->renderPage();
    }

    /**
     * 保存资料 / 头像相关操作。
     */
    private function handlePost(): void
    {
        try {
            $action = (string) $this->input->post('action', '');
            $csrf = (string) $this->input->post('csrf_token', '');

            if (!$this->csrf->validate($csrf)) {
                $this->response->error('请求已失效，请刷新页面后重试');
            }

            $userModel = new \App\Model\UserModel();
            $admin = $this->user();
            $userId = (int) ($admin['id'] ?? 0);
            $auth = new \App\Service\AuthService();

            switch ($action) {
                case 'profile':
                    $username = (string) $this->input->post('username', '');
                    $nickname = (string) $this->input->post('nickname', '');
                    $email = (string) $this->input->post('email', '');
                    $mobile = trim((string) $this->input->post('mobile', ''));

                    if ($username === '') {
                        $this->response->error('账号不能为空');
                    }

                    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
                        $this->response->error('账号只能包含字母、数字和下划线，长度3-30个字符');
                    }

                    if ($userModel->isUsernameTaken($username, $userId)) {
                        $this->response->error('该账号已被其他用户使用');
                    }

                    $nicknameErr = \DisplayName::validate($nickname, '昵称');
                    if ($nicknameErr !== null) {
                        $this->response->error($nicknameErr);
                    }

                    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $this->response->error('请输入有效的邮箱地址');
                    }

                    if ($userModel->isEmailTaken($email, $userId)) {
                        $this->response->error('该邮箱已被其他用户使用');
                    }

                    if ($userModel->isMobileTaken($mobile, $userId)) {
                        $this->response->error('该手机号已被其他用户使用');
                    }

                    $userModel->updateProfile($userId, [
                        'username' => $username,
                        'nickname' => $nickname,
                        'email' => $email,
                        'mobile' => $mobile,
                    ]);

                    $freshUser = $userModel->findById($userId);
                    if ($freshUser !== null) {
                        $auth->refreshSession($freshUser);
                    }

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('资料更新成功', [
                        'csrf_token' => $csrfToken,
                        'user' => [
                            'username' => $freshUser !== null ? $freshUser['username'] : $username,
                            'nickname' => $freshUser !== null ? $freshUser['nickname'] : $nickname,
                            'email' => $freshUser !== null ? $freshUser['email'] : $email,
                            'mobile' => $freshUser !== null ? ($freshUser['mobile'] ?? '') : $mobile,
                            'avatar' => $freshUser !== null ? $freshUser['avatar'] : null,
                        ],
                    ]);
                    break;

                case 'avatar':
                    if (empty($_FILES['avatar_file'])) {
                        $this->response->error('请选择头像文件');
                    }

                    $uploader = new \App\Service\UploadService();
                    $result = $uploader->upload($_FILES['avatar_file'], ['jpg', 'jpeg', 'png', 'gif', 'webp'], 'avatar');

                    $userModel->updateProfile($userId, [
                        'avatar' => $result['url'],
                    ]);

                    $freshUser = $userModel->findById($userId);
                    if ($freshUser !== null) {
                        $auth->refreshSession($freshUser);
                    }

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('头像更新成功', [
                        'csrf_token' => $csrfToken,
                        'avatar' => $result['url'],
                    ]);
                    break;

                case 'reset_avatar':
                    // avatar 列是 NOT NULL DEFAULT ''：旧代码写 null 在严格模式下必然失败，
                    // 这里用空串表示"没有自定义头像"，视图会回落到默认头像。
                    $userModel->updateProfile($userId, [
                        'avatar' => '',
                    ]);

                    $freshUser = $userModel->findById($userId);
                    if ($freshUser !== null) {
                        $auth->refreshSession($freshUser);
                    }

                    $csrfToken = $this->csrf->refresh();
                    $defaultAvatar = defined('EM_AVATAR') ? EM_AVATAR : '/content/static/img/avatar.jpeg';
                    $this->response->success('已恢复默认头像', [
                        'csrf_token' => $csrfToken,
                        'avatar' => $defaultAvatar,
                    ]);
                    break;

                case 'avatar_pick':
                    $avatarUrl = (string) $this->input->post('avatar_url', '');
                    if ($avatarUrl === '') {
                        $this->response->error('请选择头像地址');
                    }

                    $userModel->updateProfile($userId, [
                        'avatar' => $avatarUrl,
                    ]);

                    $freshUser = $userModel->findById($userId);
                    if ($freshUser !== null) {
                        $auth->refreshSession($freshUser);
                    }

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('头像已更新', [
                        'csrf_token' => $csrfToken,
                        'avatar' => $avatarUrl,
                    ]);
                    break;

                default:
                    $this->response->error('未知操作');
            }
        } catch (\RuntimeException $e) {
            $this->response->error($e->getMessage());
        } catch (\Throwable $e) {
            $this->response->error('系统繁忙，请稍后再试');
        }
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套后台框架壳。
     */
    private function renderPage(): void
    {
        $admin = $this->user();
        $userFull = (new \App\Model\UserModel())->findById((int) ($admin['id'] ?? 0));

        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('profile', [
                'userFull'  => $userFull,
                'csrfToken' => $this->csrf->token(),
            ]);
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/profile.php', $this->user(), get_defined_vars());
    }
}
