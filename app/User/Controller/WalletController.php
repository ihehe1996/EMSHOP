<?php

declare(strict_types=1);

namespace App\User\Controller;

/**
 * 用户中心页面（/user/wallet）。
 */
final class WalletController extends UserBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        $siteName = $this->config->get('sitename', 'EMSHOP');
        $csrfToken = $this->csrf->token();

        // 余额 / 限额全部换算到访客当前展示币种（数值，不带符号；view 里自行拼 $currencySymbol）
        $displayMoney = $this->currency->displayAmount((int) ($frontUser['money'] ?? 0), null, false);
        $currencySymbol = $this->currency->visitorSymbol();

        // 后台配置的充值/提现限额（×1000000 存储）；不带符号，交给 view 拼
        $cfgMinRecharge = (int) $this->config->get('shop_min_recharge', '1000000');      // 1.00
        $cfgMaxRecharge = (int) $this->config->get('shop_max_recharge', '1000000000000'); // 很大
        $cfgMinWithdraw = (int) $this->config->get('shop_withdraw_min', '10000000');      // 10.00
        $cfgMaxWithdraw = (int) $this->config->get('shop_withdraw_max', '5000000000');    // 5000
        $displayMinRecharge = $this->currency->displayAmount($cfgMinRecharge, null, false);
        $displayMaxRecharge = $this->currency->displayAmount($cfgMaxRecharge, null, false);
        $displayMinWithdraw = $this->currency->displayAmount($cfgMinWithdraw, null, false);
        $displayMaxWithdraw = $this->currency->displayAmount($cfgMaxWithdraw, null, false);

        // 最近 5 条余额变动记录
        $balanceLogModel = new \App\Model\UserBalanceLogModel();
        $recentLogsResult = $balanceLogModel->getListByUser((int) $frontUser['id'], 1, 5);
        $recentLogs = $recentLogsResult['list'];

        // 充值可用的支付方式（排除"余额支付"，不能自己给自己充值）
        $paymentMethods = array_values(array_filter(
            \PaymentService::getMethods(),
            static fn(array $m): bool => ($m['code'] ?? '') !== 'balance'
        ));

        if ($this->request->isPjax()) {
            echo '<div id="userContent" class="uc-content">';
            $this->render('wallet', get_defined_vars());
            echo '</div>';
        } else {
            \App\User\UserShell::render(dirname(__DIR__) . '/View/wallet.php', $frontUser, get_defined_vars());
        }
    }
}
