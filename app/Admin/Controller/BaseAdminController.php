<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 新后台控制器基类（需要登录）。
 *
 * 负责所有后台页面共用的前置条件：登录校验、CSRF 密钥补齐、当前 scope 固定为 main。
 * 与旧 admin/global.php 的 adminRequireLogin() 行为保持一致，便于过渡期两个后台并存。
 *
 * 子类动作方法直接输出页面，不返回值；由 AdminRouter 负责调用。
 */
abstract class BaseAdminController extends AbstractAdminController
{
    /**
     * 登录页地址。
     *
     * 已迁到新后台路由；旧 admin/sign.php 在过渡期仍然可用。
     */
    protected const SIGN_URL = '/admin/sign';

    /** @var \AuthService */
    private $auth;

    /** @var array<string, mixed> */
    private $adminUser = [];

    public function __construct()
    {
        $this->auth = new \AuthService();
        $user = $this->auth->check() ? $this->auth->user() : null;
        if (!is_array($user)) {
            \Response::redirect(self::SIGN_URL);
        }

        $this->adminUser = $user;

        // 老站 config.php 没有安装时生成的 auth.secret：补齐并写回，
        // 保证后续 CSRF 校验统一走带密钥的 cookie 兜底（与旧后台一致）。
        \CsrfSecret::ensure();

        // 后台固定主站作用域：TemplateStorage / Storage / PluginModel 都读这个全局
        $GLOBALS['__em_current_scope'] = 'main';

        // 注意：旧后台的演示模式写操作守卫（adminDemoGuard）尚未迁移。
        // 新后台落地第一个写接口前，必须先在这里补上等价拦截，否则演示模式下可写入。
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
