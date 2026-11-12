<?php

declare(strict_types=1);

namespace App\User\Controller;

/**
 * 用户中心页面（/user/profile）。
 */
final class ProfileController extends UserBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        $siteName = $this->config->get('sitename', 'EMSHOP');
        $csrfToken = $this->csrf->token();
        $displayMoney = '0.00';
        if (!empty($frontUser['money'])) {
            $displayMoney = bcdiv((string) $frontUser['money'], '1000000', 2);
        }
        // 处理 POST 提交
        if ($this->request->isPost()) {
            try {
                $action = (string) $this->input->post('action', '');
                $csrf = (string) $this->input->post('csrf_token', '');

                if (!$this->csrf->validate($csrf)) {
                    $this->response->error('请求已失效，请刷新页面后重试');
                }

                $userModel = app(\App\Model\UserListModel::class);
                $userId = (int) $frontUser['id'];

                switch ($action) {
                    case 'profile':
                        $nickname = trim($this->input->post('nickname', ''));
                        $nicknameErr = \DisplayName::validate($nickname, '昵称');
                        if ($nicknameErr !== null) {
                            $this->response->error($nicknameErr);
                        }
                        $email    = trim($this->input->post('email', ''));
                        $mobile   = trim($this->input->post('mobile', ''));

                        if ($nickname === '') {
                            $this->response->error('昵称不能为空');
                        }
                        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $this->response->error('请输入有效的邮箱地址');
                        }
                        if ($userModel->existsEmail($email, $userId)) {
                            $this->response->error('该邮箱已被其他用户使用');
                        }
                        if ($mobile !== '' && $userModel->existsMobile($mobile, $userId)) {
                            $this->response->error('该手机号已被其他用户使用');
                        }

                        $userModel->update($userId, [
                            'nickname' => $nickname,
                            'email'    => $email,
                            'mobile'   => $mobile,
                        ]);

                        // 刷新 session
                        $frontUser['nickname'] = $nickname;
                        $frontUser['email']    = $email;
                        $frontUser['mobile']   = $mobile;
                        $_SESSION['em_front_user'] = $frontUser;

                        $csrfToken = $this->csrf->refresh();
                        $this->response->success('保存成功', ['csrf_token' => $csrfToken]);
                        break;

                    default:
                        $this->response->error('未知操作');
                }
            } catch (\Throwable $e) {
                $this->response->error('操作失败：' . $e->getMessage());
            }
        }

        if ($this->request->isPjax()) {
            echo '<div id="userContent" class="uc-content">';
            $this->render('profile', get_defined_vars());
            echo '</div>';
        } else {
            \App\User\UserShell::render(dirname(__DIR__) . '/View/profile.php', $frontUser, get_defined_vars());
        }
    }
}
