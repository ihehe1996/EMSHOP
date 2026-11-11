<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 后台个人信息（/admin/profile）。
 *
 * 支持修改账号/昵称/邮箱/手机号、上传头像、选择头像、恢复默认头像。
 * 与旧 admin/profile.php 行为一致（动作参数是 POST 的 action，不是 _action）。
 *
 * 过渡期列表页仍套用旧后台框架壳（admin/view/index.php）。
 */
final class ProfileController extends BaseAdminController
{
    public function index(): void
    {
        if (\Request::isPost()) {
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
            $action = (string) \Input::post('action', '');
            $csrf = (string) \Input::post('csrf_token', '');

            if (!\Csrf::validate($csrf)) {
                \Response::error('请求已失效，请刷新页面后重试');
            }

            $userModel = new \UserModel();
            $admin = $this->user();
            $userId = (int) ($admin['id'] ?? 0);
            $auth = new \AuthService();

            switch ($action) {
                case 'profile':
                    $username = (string) \Input::post('username', '');
                    $nickname = (string) \Input::post('nickname', '');
                    $email = (string) \Input::post('email', '');
                    $mobile = trim((string) \Input::post('mobile', ''));

                    if ($username === '') {
                        \Response::error('账号不能为空');
                    }

                    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
                        \Response::error('账号只能包含字母、数字和下划线，长度3-30个字符');
                    }

                    if ($userModel->isUsernameTaken($username, $userId)) {
                        \Response::error('该账号已被其他用户使用');
                    }

                    $nicknameErr = \DisplayName::validate($nickname, '昵称');
                    if ($nicknameErr !== null) {
                        \Response::error($nicknameErr);
                    }

                    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        \Response::error('请输入有效的邮箱地址');
                    }

                    if ($userModel->isEmailTaken($email, $userId)) {
                        \Response::error('该邮箱已被其他用户使用');
                    }

                    if ($userModel->isMobileTaken($mobile, $userId)) {
                        \Response::error('该手机号已被其他用户使用');
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

                    $csrfToken = \Csrf::refresh();
                    \Response::success('资料更新成功', [
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
                        \Response::error('请选择头像文件');
                    }

                    $uploader = new \UploadService();
                    $result = $uploader->upload($_FILES['avatar_file'], ['jpg', 'jpeg', 'png', 'gif', 'webp'], 'avatar');

                    $userModel->updateProfile($userId, [
                        'avatar' => $result['url'],
                    ]);

                    $freshUser = $userModel->findById($userId);
                    if ($freshUser !== null) {
                        $auth->refreshSession($freshUser);
                    }

                    $csrfToken = \Csrf::refresh();
                    \Response::success('头像更新成功', [
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

                    $csrfToken = \Csrf::refresh();
                    $defaultAvatar = defined('EM_AVATAR') ? EM_AVATAR : '/content/static/img/avatar.jpeg';
                    \Response::success('已恢复默认头像', [
                        'csrf_token' => $csrfToken,
                        'avatar' => $defaultAvatar,
                    ]);
                    break;

                case 'avatar_pick':
                    $avatarUrl = (string) \Input::post('avatar_url', '');
                    if ($avatarUrl === '') {
                        \Response::error('请选择头像地址');
                    }

                    $userModel->updateProfile($userId, [
                        'avatar' => $avatarUrl,
                    ]);

                    $freshUser = $userModel->findById($userId);
                    if ($freshUser !== null) {
                        $auth->refreshSession($freshUser);
                    }

                    $csrfToken = \Csrf::refresh();
                    \Response::success('头像已更新', [
                        'csrf_token' => $csrfToken,
                        'avatar' => $avatarUrl,
                    ]);
                    break;

                default:
                    \Response::error('未知操作');
            }
        } catch (\RuntimeException $e) {
            \Response::error($e->getMessage());
        } catch (\Throwable $e) {
            \Response::error('系统繁忙，请稍后再试');
        }
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套旧后台框架壳。
     */
    private function renderPage(): void
    {
        $admin = $this->user();
        $userFull = (new \UserModel())->findById((int) ($admin['id'] ?? 0));

        if (\Request::isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('profile', [
                'userFull'  => $userFull,
                'csrfToken' => \Csrf::token(),
            ]);
            echo '</div>';
            return;
        }

        require_once EM_ROOT . '/include/lib/AdminLang.php';

        $user = $this->user();
        $siteName = (string) \Config::get('sitename', 'EMSHOP');
        $languages = (new \LanguageModel())->getEnabled();
        $emServerHardRestartPending = is_file(EM_ROOT . '/.server') && \WorkerHeartbeat::hostAlive();
        $csrfToken = \Csrf::token();
        $adminContentView = dirname(__DIR__) . '/View/profile.php';

        require EM_ROOT . '/admin/view/index.php';
    }
}
