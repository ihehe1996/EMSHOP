<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 后台插件动作入口（{入口}/plugin_action）。
 *
 * 插件通过 `addAction('admin_plugin_action_{action}', ...)` 注册自己的后台 AJAX 入口，
 * 处理完自行输出并 exit；本控制器只负责登录守卫与钩子分发。
 */
final class PluginActionController extends BaseAdminController
{
    public function index(): void
    {
        // 与旧入口一致：GET 优先，没有再取 POST
        $action = (string) ($this->input->get('_action', null) ?? $this->input->post('_action', ''));

        if ($action !== '') {
            doAction('admin_plugin_action_' . $action);
        }

        $this->response->error('未知操作');
    }
}
