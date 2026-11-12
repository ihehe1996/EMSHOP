<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 退出登录（{入口}/logout）。
 *
 * 不需要登录守卫：未登录误入时同样跳回登录页。
 */
final class LogoutController extends AbstractAdminController
{
    public function index(): void
    {
        app(\App\Service\AuthService::class)->logout();

        $this->response->redirect(EM_ADMIN_PATH . '/sign');
    }
}
