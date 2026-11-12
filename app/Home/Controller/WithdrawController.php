<?php

declare(strict_types=1);

namespace App\Home\Controller;

/**
 * 前台"钱包提现"控制器。
 *
 * 路由：
 *   create()  AJAX 提交提现申请（登录用户限定）
 */
class WithdrawController extends BaseController
{
    private function requireLogin(): array
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $user = $_SESSION['em_front_user'] ?? null;
        if (empty($user['id'])) {
            $this->response->error('请先登录');
        }
        return $user;
    }

    /**
     * AJAX：创建提现申请，立即扣 user.money 并写 pending 记录。
     *
     * POST: amount, channel (alipay/wxpay/bank),
     *       account_name, account_no, bank_name (仅 channel=bank)
     */
    public function create(): void
    {
        if (!$this->request->isPost()) $this->response->error('无效请求');

        if (!$this->csrf->validateOrSameOrigin((string) $this->input->post('csrf_token', ''))) {
            $this->response->error('请求已失效，请刷新页面后重试');
        }

        $user   = $this->requireLogin();
        $userId = (int) $user['id'];

        // Money::parse 先做严格的十进制白名单校验，再换算成主货币 micro 整数。
        // 原先的 is_numeric + bcmul 会放行 1e5 这类科学计数法字符串，而 bcmul 在
        // PHP 8 遇到它会抛未捕获的 ValueError（接口直接 500），PHP 7.4 下则静默截断成错误金额。
        try {
            $amountRaw = \Money::parse($this->input->post('amount', ''));
        } catch (\InvalidArgumentException $e) {
            $this->response->error('请输入正确的提现金额');
        }

        $minRaw = (int) $this->config->get('shop_withdraw_min', '10000000');
        $maxRaw = (int) $this->config->get('shop_withdraw_max', '5000000000');
        if ($amountRaw < $minRaw || $amountRaw > $maxRaw) {
            $sym = ($p = $this->currency->getPrimary()) ? ($p['symbol'] ?? '¥') : '¥';
            $this->response->error(sprintf('提现金额须在 %s%s ~ %s%s 之间',
                $sym, bcdiv((string) $minRaw, '1000000', 2),
                $sym, bcdiv((string) $maxRaw, '1000000', 2)));
        }

        $channel      = trim((string) $this->input->post('channel', ''));
        $accountName  = trim((string) $this->input->post('account_name', ''));
        $accountNo    = trim((string) $this->input->post('account_no', ''));
        $bankName     = trim((string) $this->input->post('bank_name', ''));

        if (!in_array($channel, \App\Model\UserWithdrawModel::ALLOWED_CHANNELS, true)) {
            $this->response->error('请选择收款方式');
        }

        try {
            $id = (new \App\Model\UserWithdrawModel())->create(
                $userId,
                $amountRaw,
                $channel,
                $accountName,
                $accountNo,
                $channel === 'bank' ? $bankName : ''
            );
        } catch (\RuntimeException $e) {
            $this->response->error($e->getMessage());
        }

        $this->response->success('申请已提交，等待管理员审核', ['id' => $id]);
    }
}
