<?php

declare(strict_types=1);

namespace App\User\Controller;

/**
 * 用户中心页面（/user/api）。
 */
final class ApiController extends UserBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        $siteName = $this->config->get('sitename', 'EMSHOP');
        $csrfToken = $this->csrf->token();
        $displayMoney = '0.00';
        if (!empty($frontUser['money'])) {
            $displayMoney = bcdiv((string) $frontUser['money'], '1000000', 2);
        }
        // 接口地址：当前网站链接
        $apiUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/';

        // APPID = 用户ID
        $appId = (string) $frontUser['id'];

        // SECRET
        $secret = $frontUser['secret'] ?? null;

        // 处理 POST 提交
        if ($this->request->isPost()) {
            try {
                $action = (string) $this->input->post('action', '');
                $csrf = (string) $this->input->post('csrf_token', '');

                if (!$this->csrf->validate($csrf)) {
                    $this->response->error('请求已失效，请刷新页面后重试');
                }

                $userModel = new \App\Model\UserListModel();
                $userId = (int) $frontUser['id'];

                switch ($action) {
                    case 'generate_secret':
                        // 生成 SECRET：md5(用户ID + 时间戳 + 随机数) 转大写
                        $raw = $userId . microtime(true) . random_int(100000, 999999);
                        $newSecret = strtoupper(md5($raw));

                        $userModel->update($userId, ['secret' => $newSecret]);

                        // 刷新 session
                        $frontUser['secret'] = $newSecret;
                        $_SESSION['em_front_user'] = $frontUser;

                        $csrfToken = $this->csrf->refresh();
                        $this->response->success('生成成功', [
                            'secret'     => $newSecret,
                            'csrf_token' => $csrfToken,
                        ]);
                        break;

                    default:
                        $this->response->error('未知操作');
                }
            } catch (\Throwable $e) {
                $this->response->error('操作失败：' . $e->getMessage());
            }
        }

        if ($this->request->isPjax()) {
            echo '<div id="userContent" class="uc-content">';
            $this->render('api', get_defined_vars());
            echo '</div>';
        } else {
            \App\User\UserShell::render(dirname(__DIR__) . '/View/api.php', $frontUser, get_defined_vars());
        }
    }
}
