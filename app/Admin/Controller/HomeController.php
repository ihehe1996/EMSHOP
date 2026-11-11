<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 新后台首页（过渡期最小实现）。
 *
 * 目前只用于验证 /admin 与 /admin/home 的路由、登录守卫和视图渲染已经接通；
 * 完整布局、菜单与数据卡片在后续切片中迁移。
 */
final class HomeController extends BaseAdminController
{
    public function index(): void
    {
        $this->render('home', [
            'siteName' => (string) \Config::get('sitename', 'EMSHOP'),
            'user'     => $this->user(),
        ]);
    }
}
