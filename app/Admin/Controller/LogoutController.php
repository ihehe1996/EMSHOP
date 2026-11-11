<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 退出登录（/admin/logout）。
 *
 * 不需要登录守卫：未登录误入时同样跳回登录页。
 * 已登录时跳转带回安全入口参数（?s=），未登录跳转不带，避免 Location 头泄露入口密钥。
 */
final class LogoutController extends AbstractAdminController
{
    public function index(): void
    {
        $auth = new \AuthService();
        $wasLoggedIn = $auth->check();
        $auth->logout();

        \Response::redirect(self::signUrl($wasLoggedIn));
    }

    /**
     * 登录页地址；带上安全入口参数（仅在已登录场景使用）。
     */
    private static function signUrl(bool $includeEntryKey): string
    {
        if (!$includeEntryKey) {
            return '/admin/sign';
        }

        $key = trim((string) \Config::get('admin_entry_key', ''));

        return $key === '' ? '/admin/sign' : '/admin/sign?s=' . urlencode($key);
    }
}
