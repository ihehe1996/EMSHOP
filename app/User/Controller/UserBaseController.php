<?php

declare(strict_types=1);

namespace App\User\Controller;

/**
 * 用户中心控制器基类。
 *
 * 与原 user/global.php 的 userRequireLogin() 行为一致：
 *   - 未登录：普通请求 302 到前台登录页，Pjax / AJAX 返回 JSON 错误
 *   - 已登录：从数据库刷新余额、昵称、头像等实时字段
 */
abstract class UserBaseController extends \App\Core\AbstractController
{
    /** @var array<string, mixed> */
    private $frontUser = [];

    public function __construct()
    {
        parent::__construct();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['em_front_user'])) {
            if ($this->request->isPjax() || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->response->error('请先登录');
            }
            $this->response->redirect('/?c=login');
        }

        $user = (array) $_SESSION['em_front_user'];

        $fresh = (new \UserListModel())->findById((int) ($user['id'] ?? 0));
        if ($fresh !== null) {
            $user['money']        = (int) ($fresh['money'] ?? 0);
            $user['nickname']     = (string) ($fresh['nickname'] ?: $fresh['username']);
            $user['avatar']       = (string) $fresh['avatar'];
            $user['email']        = (string) ($fresh['email'] ?? '');
            $user['mobile']       = (string) ($fresh['mobile'] ?? '');
            $user['secret']       = $fresh['secret'] ?? null;
            $user['merchant_id']  = (int) ($fresh['merchant_id'] ?? 0);
            $user['shop_balance'] = (int) ($fresh['shop_balance'] ?? 0);
            $_SESSION['em_front_user'] = $user;
        }

        $this->frontUser = $user;
    }

    /**
     * 当前登录的前台用户。
     *
     * @return array<string, mixed>
     */
    protected function user(): array
    {
        return $this->frontUser;
    }

    /**
     * 渲染 app/User/View 下的视图（不套框架壳）。
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
