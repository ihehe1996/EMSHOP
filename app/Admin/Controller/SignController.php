<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 新后台登录页（/admin/sign）。
 *
 *   - 已登录直接进后台首页
 *   - POST 校验 CSRF 后交给 AuthService::attemptAdminLogin()
 *   - 登录成功进入 /admin/home，失败时重新渲染页面并显示错误
 *
 * 旧后台的 admin_entry_key 安全入口已随旧后台一起废弃，本页不再校验 ?s= 参数。
 */
final class SignController extends AbstractAdminController
{
    public function index(): void
    {
        $auth = new \AuthService();

        if ($auth->check()) {
            \Response::redirect(EM_ADMIN_PATH . '/home');
        }

        $error = '';
        if (\Request::isPost()) {
            // 登录页的 sign.js 用 AJAX 提交，返回 JSON；禁用 JS 时走普通表单（回显错误 / 302）
            $isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

            try {
                if (!\Csrf::validate((string) \Input::post('csrf_token', ''))) {
                    throw new \RuntimeException('请求已失效，请刷新页面后重试');
                }

                $auth->attemptAdminLogin(
                    (string) \Input::post('account', ''),
                    (string) \Input::post('password', ''),
                    (string) \Input::post('remember', '0') === '1'
                );

                \Csrf::refresh();

                if ($isAjax) {
                    \Response::success('登录成功', ['redirect' => EM_ADMIN_PATH . '/home']);
                    exit;
                }

                \Response::redirect(EM_ADMIN_PATH . '/home');
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
            } catch (\RuntimeException $e) {
                $error = $e->getMessage();
            } catch (\Throwable $e) {
                $error = '系统繁忙，请稍后再试';
            }

            if ($isAjax) {
                \Response::error($error);
                exit;
            }
        }

        $this->render('sign', [
            'siteName'  => (string) \Config::get('sitename', 'EMSHOP'),
            'csrfToken' => \Csrf::token(),
            'error'     => $error,
            'account'   => (string) \Input::post('account', ''),
            'remember'  => (string) \Input::post('remember', '0') === '1',
        ]);
    }
}
