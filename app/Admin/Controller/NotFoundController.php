<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 新后台 404 页面。
 *
 * 未在 AdminRouter 路由表登记的后台路径都落到这里。
 * 同样继承 BaseAdminController，未登录时先跳转登录页。
 */
final class NotFoundController extends BaseAdminController
{
    public function index(): void
    {
        http_response_code(404);
        $this->render('not_found');
    }
}
