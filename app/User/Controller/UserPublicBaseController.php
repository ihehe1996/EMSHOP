<?php

declare(strict_types=1);

namespace App\User\Controller;

/**
 * 用户中心公开页控制器基类（不强制登录）。
 *
 * 对应原 user/global_public.php：游客可访问订单详情 / 查单 / 货币切换 / 轮询接口，
 * 已登录时刷新当前用户资料。
 */
abstract class UserPublicBaseController extends \App\Core\AbstractController
{
    /** @var array<string, mixed>|null */
    private $frontUser;

    public function __construct()
    {
        parent::__construct();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $user = null;
        if (!empty($_SESSION['em_front_user'])) {
            $user = (array) $_SESSION['em_front_user'];

            $fresh = (new \UserListModel())->findById((int) ($user['id'] ?? 0));
            if ($fresh !== null) {
                $user['money']    = (int) ($fresh['money'] ?? 0);
                $user['nickname'] = (string) ($fresh['nickname'] ?: $fresh['username']);
                $user['avatar']   = (string) $fresh['avatar'];
                $user['email']    = (string) ($fresh['email'] ?? '');
                $user['mobile']   = (string) ($fresh['mobile'] ?? '');
                $user['secret']   = $fresh['secret'] ?? null;
                $_SESSION['em_front_user'] = $user;
            }
        }

        $this->frontUser = $user;
    }

    /**
     * 当前前台用户；游客为 null。
     *
     * @return array<string, mixed>|null
     */
    protected function user(): ?array
    {
        return $this->frontUser;
    }

    /**
     * 渲染 app/User/View 下的视图。
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $viewName, array $data = []): void
    {
        $relative = str_replace('\\', '/', trim($viewName, '/'));
        if ($relative === '' || preg_match('#[^a-zA-Z0-9_/-]#', $relative)) {
            throw new \InvalidArgumentException('非法视图名：' . $viewName);
        }

        $file = dirname(__DIR__) . '/View/' . $relative . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('视图不存在：' . $relative);
        }

        extract($data, EXTR_SKIP);
        require $file;
    }
}
