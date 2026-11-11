<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 后台插件动作入口（/admin/plugin_action）。
 *
 * 插件通过 `addAction('admin_plugin_action_{action}', ...)` 注册自己的后台 AJAX 入口，
 * 处理完自行输出并 exit；本控制器只负责登录守卫与钩子分发。
 *
 * 过渡期旧入口 /admin/index.php?_action=... 仍在，两者分发到同一批钩子。
 */
final class PluginActionController extends BaseAdminController
{
    public function index(): void
    {
        // 与旧入口一致：GET 优先，没有再取 POST
        $action = (string) (\Input::get('_action', null) ?? \Input::post('_action', ''));

        if ($action !== '') {
            doAction('admin_plugin_action_' . $action);
        }

        \Response::error('未知操作');
    }
}
