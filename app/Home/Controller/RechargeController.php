<?php

declare(strict_types=1);

namespace App\Home\Controller;

/**
 * 前台"钱包充值"控制器。
 *
 * 路由：
 *   create()  AJAX 创建充值单并返回 pay_url（登录用户限定）
 */
class RechargeController extends BaseController
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
     * AJAX：创建充值单，调用支付插件 payment_create filter 生成 pay_url。
     *
     * POST: amount (元，支持两位小数), payment_code
     */
    public function create(): void
    {
        if (!$this->request->isPost()) $this->response->error('无效请求');

        $user   = $this->requireLogin();
        $userId = (int) $user['id'];

        // —— 金额（用户填的是元）
        // Money::parse 先做严格的十进制白名单校验，再换算成主货币 micro 整数。
        // 原先的 is_numeric + bcmul 会放行 1e5 这类科学计数法字符串，而 bcmul 在
        // PHP 8 遇到它会抛未捕获的 ValueError（接口直接 500），PHP 7.4 下则静默截断成错误金额。
        try {
            $amountRaw = $this->money->parse($this->input->post('amount', ''));
        } catch (\InvalidArgumentException $e) {
            $this->response->error('请输入正确的充值金额');
        }

        // 后台配置的限额
        $minRaw = (int) $this->config->get('shop_min_recharge', '1000000');
        $maxRaw = (int) $this->config->get('shop_max_recharge', '1000000000000');
        if ($amountRaw < $minRaw || $amountRaw > $maxRaw) {
            $sym = ($p = $this->currency->getPrimary()) ? ($p['symbol'] ?? '¥') : '¥';
            $this->response->error(sprintf('充值金额须在 %s%s ~ %s%s 之间',
                $sym, bcdiv((string) $minRaw, '1000000', 2),
                $sym, bcdiv((string) $maxRaw, '1000000', 2)));
        }

        // —— 校验支付方式
        $paymentCode = trim((string) $this->input->post('payment_code', ''));
        if ($paymentCode === '') $this->response->error('请选择支付方式');
        if ($paymentCode === 'balance') $this->response->error('充值不能用余额支付');

        $methods = app(\App\Service\PaymentService::class)->getMethods();
        $payment = null;
        foreach ($methods as $m) {
            if (($m['code'] ?? '') === $paymentCode) { $payment = $m; break; }
        }
        if ($payment === null) $this->response->error('支付方式不可用');

        try {
            // —— 创建 pending 充值单
            $model = new \App\Model\UserRechargeModel();
            $created = $model->create($userId, $amountRaw, $paymentCode, (string) $payment['plugin']);

            // —— 组装"伪订单"结构给 payment_create filter（字段与 em_order 同名，
            //    插件里读的是 pay_amount / order_no / payment_code，能直接复用）
            $pseudoOrder = [
                'id'            => $created['id'],
                'order_no'      => $created['order_no'],
                'pay_amount'    => $amountRaw,
                'payment_code'  => $paymentCode,
                'user_id'       => $userId,
                'trade_type'    => 'recharge',   // 标记这是充值单，插件可按需区分
            ];

            // 走 PaymentService::createPayment 而不是直接 applyFilter —— 它会临时把 scope
            // 切到 'main' 让插件 Storage::getInstance 读到主站凭证（商户 scope 没存这些凭证）
            $payPayload = app(\App\Service\PaymentService::class)->createPaymentPayload($pseudoOrder, $payment);
            $payUrl = (string) ($payPayload['pay_url'] ?? '');
            if ($payUrl === '') {
                throw new \RuntimeException('支付方式未生成支付链接');
            }

            $this->response->success('', [
                'order_no'     => $created['order_no'],
                'pay_url'      => $payUrl,
                'qrcode'       => (string) ($payPayload['qrcode'] ?? ''),
            ]);
        } catch (\RuntimeException $e) {
            $this->response->error($e->getMessage());
        } catch (\Throwable $e) {
            $this->response->error('创建充值订单失败，请稍后重试');
        }
    }
}
