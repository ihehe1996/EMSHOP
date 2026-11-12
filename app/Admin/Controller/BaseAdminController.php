<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 新后台控制器基类（需要登录）。
 *
 * 负责所有后台页面共用的前置条件：登录校验、CSRF 密钥补齐、当前 scope 固定为 main。
 *
 * 子类动作方法直接输出页面，不返回值；由 AdminRouter 负责调用。
 */
abstract class BaseAdminController extends AbstractAdminController
{
    /**
     * 登录页地址。
     */
    protected const SIGN_URL = EM_ADMIN_PATH . '/sign';

    /** @var \AuthService */
    private $auth;

    /** @var array<string, mixed> */
    private $adminUser = [];

    public function __construct()
    {
        parent::__construct();

        $this->auth = app(\App\Service\AuthService::class);
        $user = $this->auth->check() ? $this->auth->user() : null;
        if (!is_array($user)) {
            $this->response->redirect(self::SIGN_URL);
        }

        $this->adminUser = $user;

        // config.php 缺少安装时生成的 auth.secret：补齐并写回，
        // 保证后续 CSRF 校验统一走带密钥的 cookie 兜底。
        \CsrfSecret::ensure();

        // 后台固定主站作用域：TemplateStorage / Storage / PluginModel 都读这个全局
        $GLOBALS['__em_current_scope'] = 'main';

        // 演示模式：登录放行，写操作一律拦掉。
        $this->enforceDemoGuard();
    }

    /**
     * 演示模式写操作守卫。
     *
     * 判据是"请求是否带 csrf_token"（GET 或 POST）：后台所有写入口都必须过 CSRF 校验，
     * 只读接口（layui list、summary、stats、trend、search_users 等）不带 token。
     * 少数只读接口会带 token（详情/弹窗类），列入白名单放行。
     */
    private function enforceDemoGuard(): void
    {
        if (!defined('EM_DEMO_MODE') || !EM_DEMO_MODE) {
            return;
        }

        $token = trim((string) $this->input->get('csrf_token', ''));
        if ($token === '') {
            $token = trim((string) $this->input->post('csrf_token', ''));
        }
        if ($token === '') {
            return;
        }

        $readOnlyActions = [
            'view',            // 系统日志详情
            'list',            // 弹窗里的列表接口
            'get_plugin_form', // 商品类型插件的配置表单
        ];
        $action = (string) $this->input->get('_action', '');
        if ($action === '') {
            $action = (string) $this->input->post('_action', '');
        }
        if (in_array(trim($action), $readOnlyActions, true)) {
            return;
        }

        $this->response->error('铁铁，演示模式下无权限执行该操作哦~');
    }

    /**
     * 当前登录管理员。
     *
     * @return array<string, mixed>
     */
    protected function user(): array
    {
        return $this->adminUser;
    }
}
