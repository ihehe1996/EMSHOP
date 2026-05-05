<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * 后台登录入口。
 *
 * 负责处理登录请求与视图渲染，具体 HTML 模板放在 admin/view 中。
 */
$csrfToken = Csrf::token();
$siteName = Config::get('sitename', 'EMSHOP');

if ($auth->check()) {
    Response::redirect('/admin/index.php');
}

// 安全入口校验：配置了 admin_entry_key 时必须带正确的 ?s=xxx 才放行
// 不通过会直接 403 + 提示页 + exit（提示页不回显 key，防侧信道泄露）
adminEnforceEntryKey();

if (Request::isPost()) {
    try {
        $account = (string) Input::post('account', '');
        $password = (string) Input::post('password', '');
        $remember = (string) Input::post('remember', '0') === '1';
        $csrf = (string) Input::post('csrf_token', '');

        if (!Csrf::validate($csrf)) {
            Response::error('请求已失效，请刷新页面后重试');
        }

        $user = $auth->attemptAdminLogin($account, $password, $remember);
        $csrfToken = Csrf::refresh();

        Response::success('登录成功', [
            'redirect' => '/admin/index.php',
            'user' => $user,
        ]);
    } catch (InvalidArgumentException $e) {
        Response::error($e->getMessage());
    } catch (RuntimeException $e) {
        Response::error($e->getMessage());
    } catch (Throwable $e) {
        Response::error('系统繁忙，请稍后再试');
    }
}

/** @var string $viewFile */
$viewFile = __DIR__ . '/view/sign.php';
require $viewFile;
