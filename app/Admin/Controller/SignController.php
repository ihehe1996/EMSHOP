<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 新后台登录页（/admin/sign）。
 *
 *   - 已登录直接进后台首页
 *   - POST 校验 CSRF 后交给 app(\App\Service\AuthService::class)->attemptAdminLogin()
 *   - 登录成功进入 /admin/home，失败时重新渲染页面并显示错误
 *
 * 旧的 admin_entry_key 安全入口已废弃，本页不校验 ?s= 参数。
 */
final class SignController extends AbstractAdminController
{
    public function index(): void
    {
        $auth = new \App\Service\AuthService();

        if ($auth->check()) {
            $this->response->redirect(EM_ADMIN_PATH . '/home');
        }

        $error = '';
        if ($this->request->isPost()) {
            // 登录页的 sign.js 用 AJAX 提交，返回 JSON；禁用 JS 时走普通表单（回显错误 / 302）
            $isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

            try {
                if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
                    throw new \RuntimeException('请求已失效，请刷新页面后重试');
                }

                $auth->attemptAdminLogin(
                    (string) $this->input->post('account', ''),
                    (string) $this->input->post('password', ''),
                    (string) $this->input->post('remember', '0') === '1'
                );

                $this->csrf->refresh();

                if ($isAjax) {
                    $this->response->success('登录成功', ['redirect' => EM_ADMIN_PATH . '/home']);
                    exit;
                }

                $this->response->redirect(EM_ADMIN_PATH . '/home');
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
            } catch (\RuntimeException $e) {
                $error = $e->getMessage();
            } catch (\Throwable $e) {
                $error = '系统繁忙，请稍后再试';
            }

            if ($isAjax) {
                $this->response->error($error);
                exit;
            }
        }

        $this->render('sign', [
            'siteName'  => (string) $this->config->get('sitename', 'EMSHOP'),
            'csrfToken' => $this->csrf->token(),
            'error'     => $error,
            'account'   => (string) $this->input->post('account', ''),
            'remember'  => (string) $this->input->post('remember', '0') === '1',
        ]);
    }
}
