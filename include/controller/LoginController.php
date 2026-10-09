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
 *
 * 页面动作名是 `display()`（不是 `_index`），入口登记在 Dispatcher::DEFAULT_ACTIONS。
 * 三个页面视图（login / forgot_password / reset_password）都是**公共视图**，放在
 * user/view/ 全站共用，不跟着主题走，所以它们自己不依赖主题 module.php 取数。
 *
 * display() 只留「发 POST 就转交处理器」和几个必须在输出前完成的判断（已登录跳转、
 * 功能未开启），数据由视图自己取。
 */
class LoginController extends BaseController
{
    /**
     * 登录页：GET 显示表单，POST 转交登录处理。
     */
    public function display(): void
    {
        if (Request::isPost()) {
            $this->handleLogin();
            return;
        }

        // 已登录则跳转到用户中心（重定向必须在任何输出之前，所以留在控制器）
        if (!empty($_SESSION['em_front_user'])) {
            header('Location: ?c=user');
            exit;
        }

        // 未开放登录时直接提示
        if ((string) Config::get('user_login', '1') !== '1') {
            Response::error('当前站点已关闭登录功能');
        }

        $this->view->setTitle('登录');
        // 独立页：不套主题 header/footer（见 View::renderStandalone）
        $this->view->renderStandalone('login');
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

        // 登录前置拦截：登录频率限制等插件在此「计一次请求 / 判断是否封禁」，
        // 返回非空字符串即视为拦截（文案直接回给用户）。
        // 放在其它校验之前，让所有尝试都进入插件计数，被拦截时连数据库都不查。
        // 作用域传 'front'，与后台登录分开计数 —— 前台被爆破锁住时不能连带把管理员
        // 挡在后台之外（管理账号也能在前台登录的场景尤其重要）。
        $blocked = (string) applyFilter('login_before_attempt', '', 'front', $account);
        if ($blocked !== '') {
            Response::error($blocked);
        }

        if ($account === '' || $password === '') {
            Response::error('请输入账号和密码');
        }

        // 查找账号（支持账号、手机号、邮箱登录）。
        // 不按 role 过滤：管理账号（role='admin'）也能在前台登录，前台只当它是普通用户。
        //
        // 为什么要取多行再逐个验密码，而不是 LIMIT 1 取一行：username 有全局唯一键，
        // 但 email/mobile 只在 role='user' 范围内去重，放开后同一邮箱/手机可能同时
        // 命中管理员和普通用户两行——此时「取哪一行」绝不能靠排序偏好角色（那是权限
        // 提升隐患），而要由密码决定。
        // ORDER BY 让精确用户名匹配优先（该列唯一、语义最明确），其余按 id 兜底使顺序确定。
        // LIMIT 20 是防御性上界：bcrypt cost 8 约 10ms/次，把最坏耗时封在 ~200ms。
        $table = Database::prefix() . 'user';
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE (`username` = ? OR `email` = ? OR `mobile` = ?)
              ORDER BY (`username` = ?) DESC, `id` ASC LIMIT 20',
            $table
        );
        $rows = Database::query($sql, [$account, $account, $account, $account]);

        $hasher = new PasswordHash(8, true);
        $user = null;
        foreach ($rows as $row) {
            if ($hasher->CheckPassword($password, (string) $row['password'])) {
                $user = $row;
                break;
            }
        }

        if ($user === null) {
            doAction('login_attempt_failed', 'front', $account);
            Response::error('账号或密码错误');
        }

        // 检查账号状态。放在验密通过之后按行判断：否则密码输错也会拿到「已被禁用」，
        // 等于把「这个账号存在且被禁用」白送给攻击者，扩大账号枚举面。
        if ((int) $user['status'] !== 1) {
            // 禁用也计一次失败（与后台一致：后台把 status=1 写进登录 SQL，禁用即算失败），
            // 否则「禁用账号的密码对不对」能从是否被限流侧信道读出来。
            doAction('login_attempt_failed', 'front', $account);
            Response::error('账号已被禁用，请联系管理员');
        }

        doAction('login_attempt_succeeded', 'front', $account);

        // 写入 session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);

        // 刻意只存用户字段、**不存 role**：前台登录态是纯用户态，session 里不放任何
        // 特权信息（管理账号在前台也就是个普通用户），后台登录态另存 em_admin_auth。
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
        // 独立页：不套主题 header/footer（与登录/注册同一套）
        $this->view->renderStandalone('forgot_password');
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

        $this->view->setTitle('重置密码');
        // 独立页：不套主题 header/footer（与登录/注册同一套）
        $this->view->renderStandalone('reset_password');
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
