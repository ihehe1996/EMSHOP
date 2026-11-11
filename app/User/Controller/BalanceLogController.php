<?php

declare(strict_types=1);

namespace App\User\Controller;

/**
 * 用户中心页面（/user/balance_log）。
 */
final class BalanceLogController extends UserBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        $siteName = \Config::get('sitename', 'EMSHOP');
        $csrfToken = \Csrf::token();
        $displayMoney = '0.00';
        if (!empty($frontUser['money'])) {
            $displayMoney = bcdiv((string) $frontUser['money'], '1000000', 2);
        }
        // 分页参数
        $page = max(1, (int) \Input::get('page', 1));
        $perPage = 5;

        // 查询余额变动记录
        $balanceLogModel = new \UserBalanceLogModel();
        $result = $balanceLogModel->getListByUser((int) $frontUser['id'], $page, $perPage);

        $logList    = $result['list'];
        $total      = $result['total'];
        $totalPages = $result['total_pages'];

        if (\Request::isPjax()) {
            echo '<div id="userContent" class="uc-content">';
            $this->render('balance_log', get_defined_vars());
            echo '</div>';
        } else {
            \App\User\UserShell::render(dirname(__DIR__) . '/View/balance_log.php', $frontUser, get_defined_vars());
        }
    }
}
