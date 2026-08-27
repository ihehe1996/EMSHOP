<?php

declare(strict_types=1);

/**
 * 前台登录控制器。
 *
 * GET  ?c=login              显示登录表单
 * POST ?c=login              处理登录请求（AJAX JSON）
 * GET  ?c=login&a=logout     退出登录
 * GET  ?c=login&a=forgot       找回密码（申请重置邮件）
 * POST ?c=login&a=forgot       发送重置邮件 / 刷新验证码
 * GET  ?c=login&a=reset&token  重置密码表单
 * POST ?c=login&a=reset         提交新密码
 */
class LoginController extends BaseController
{
    /**
     * 入口：根据请求方法分发。
     */
    public function _index(): void
    {
        if (Request::isPost()) {
            $this->handleLogin();
            return;
        }

        // 已登录则跳转到用户中心
        if (!empty($_SESSION['em_front_user'])) {
            header('Location: ?c=user');
            exit;
        }

        // 未开放登录时直接提示
        if ((string) Config::get('user_login', '1') !== '1') {
            Response::error('当前站点已关闭登录功能');
        }

        $this->view->setTitle('登录');
        $this->view->setData('csrf_token', Csrf::token());
        $this->view->render('login');
    }

    /**
     * 处理登录表单提交。
     */
    private function handleLogin(): void
    {
        if ((string) Config::get('user_login', '1') !== '1') {
            Response::error('当前站点已关闭登录功能');
        }

        $csrf = Input::post('csrf_token', '');
        if (!Csrf::validate((string) $csrf)) {
            Response::error('请求已失效，请刷新页面后重试');
        }

        $account  = trim(Input::post('account', ''));
        $password = (string) Input::post('password', '');

        if ($account === '' || $password === '') {
            Response::error('请输入账号和密码');
        }

        // 查找用户（支持账号、手机号、邮箱登录）
        $table = Database::prefix() . 'user';
        $sql = sprintf(
            "SELECT * FROM `%s` WHERE (`username` = ? OR `email` = ? OR `mobile` = ?) AND `role` = 'user' LIMIT 1",
            $table
        );
        $user = Database::fetchOne($sql, [$account, $account, $account]);

        if ($user === null) {
            Response::error('账号或密码错误');
        }

        // 验证密码
        $hasher = new PasswordHash(8, true);
        if (!$hasher->CheckPassword($password, (string) $user['password'])) {
            Response::error('账号或密码错误');
        }

        // 检查账号状态
        if ((int) $user['status'] !== 1) {
            Response::error('账号已被禁用，请联系管理员');
        }

        // 写入 session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);

        $_SESSION['em_front_user'] = [
            'id'       => (int) $user['id'],
            'username' => (string) $user['username'],
            'nickname' => (string) ($user['nickname'] ?: $user['username']),
            'email'    => (string) $user['email'],
            'mobile'   => (string) ($user['mobile'] ?? ''),
            'avatar'   => (string) $user['avatar'],
            'money'    => (int) ($user['money'] ?? 0),
        ];

        // 更新最后登录信息
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        Database::execute(
            sprintf("UPDATE `%s` SET `last_login_ip` = ?, `last_login_at` = NOW() WHERE `id` = ?", $table),
            [$ip, (int) $user['id']]
        );

        // 刷新 CSRF token
        Csrf::refresh();

        Response::success('登录成功');
    }

    /**
     * 退出登录。
     */
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['em_front_user']);

        // PJAX 请求返回 JSON，普通请求重定向首页
        if (Request::isPjax() || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            Response::success('已退出登录');
        }

        header('Location: ?');
        exit;
    }

    /**
     * 找回密码：申请发送重置邮件。
     */
    public function forgot(): void
    {
        if (Request::isPost()) {
            $action = (string) Input::post('action', '');
            if ($action === 'refresh_captcha') {
                Response::success('', ['expr' => Captcha::issue('forgot_password')]);
            }
            $this->handleForgotSendRequest();
            return;
        }

        if (!empty($_SESSION['em_front_user'])) {
            header('Location: ?c=user');
            exit;
        }

        if ((string) Config::get('user_login', '1') !== '1') {
            Response::error('当前站点已关闭登录功能');
        }

        $this->view->setTitle('找回密码');
        $this->view->setData([
            'csrf_token' => Csrf::token(),
            'captcha_expr' => Captcha::issue('forgot_password'),
        ]);
        $this->view->render('auth/forgot_password');
    }

    /**
     * 重置密码（邮件链接进入）。
     */
    public function reset(): void
    {
        if (Request::isPost()) {
            $this->handleResetSubmit();
            return;
        }

        $token = trim((string) $this->getArg('token', ''));
        $service = new PasswordResetService();
        $valid = $service->validateToken($token);

        $this->view->setTitle('重置密码');
        $this->view->setData([
            'csrf_token' => Csrf::token(),
            'token' => $token,
            'token_valid' => $valid !== null,
        ]);
        $this->view->render('auth/reset_password');
    }

    private function handleForgotSendRequest(): void
    {
        if ((string) Config::get('user_login', '1') !== '1') {
            Response::error('当前站点已关闭登录功能');
        }

        $csrf = Input::post('csrf_token', '');
        if (!Csrf::validate((string) $csrf)) {
            Response::error('请求已失效，请刷新页面后重试');
        }

        $email = trim(Input::post('email', ''));
        $captcha = trim(Input::post('captcha', ''));

        $service = new PasswordResetService();
        $result = $service->requestReset($email, $captcha);

        if (!$result['ok']) {
            $data = [];
            if (!empty($result['captcha_expr'])) {
                $data['captcha_expr'] = $result['captcha_expr'];
            }
            Response::error($result['msg'], $data);
        }

        Csrf::refresh();
        Response::success($result['msg']);
    }

    private function handleResetSubmit(): void
    {
        $csrf = Input::post('csrf_token', '');
        if (!Csrf::validate((string) $csrf)) {
            Response::error('请求已失效，请刷新页面后重试');
        }

        $token = trim(Input::post('token', ''));
        $password = (string) Input::post('password', '');
        $confirm = (string) Input::post('password_confirm', '');

        $service = new PasswordResetService();
        $result = $service->resetPassword($token, $password, $confirm);

        if (!$result['ok']) {
            Response::error($result['msg']);
        }

        Csrf::refresh();
        Response::success($result['msg']);
    }
}
