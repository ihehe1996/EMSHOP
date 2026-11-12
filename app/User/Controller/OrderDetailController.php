<?php

declare(strict_types=1);

namespace App\User\Controller;

/**
 * 用户中心公开页（/user/order_detail）。
 */
final class OrderDetailController extends UserPublicBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        /**
         * 订单详情页。
         * 支持登录用户和游客访问（通过 guest_token 校验权限）。
         */

        $siteName = $this->config->get('sitename', 'EMSHOP');

        // 获取订单
        $orderNo = (string) $this->input->get('order_no', '');
        $order = null;
        $orderGoods = [];

        if ($orderNo !== '') {
            $order = \OrderModel::getByOrderNo($orderNo);

            if ($order) {
                // 权限校验
                $isOwner = false;
                if ($frontUser && (int) $order['user_id'] === (int) $frontUser['id']) {
                    $isOwner = true;
                } elseif (empty($frontUser) && \GuestToken::hasValidCookie()
                    && (string) ($order['guest_token'] ?? '') !== ''
                    && hash_equals((string) $order['guest_token'], \GuestToken::get())) {
                    $isOwner = true;
                } elseif (empty($frontUser)) {
                    // 凭据查单换来的 60 秒 grant 也允许查看（导出时才消耗 grant）
                    if (session_status() === PHP_SESSION_NONE) @session_start();
                    $grant = (int) ($_SESSION['em_card_grant'][(int) ($order['id'] ?? 0)] ?? 0);
                    if ($grant >= time()) {
                        $isOwner = true;
                    }
                }

                if (!$isOwner) {
                    $order = null;
                } else {
                    $orderGoods = \OrderModel::getOrderGoods((int) $order['id']);
                    if (\OrderModel::isPurchasedStatus((string) $order['status'])) {
                        $orderGoods = \OrderModel::attachGoodsGuides($orderGoods);
                    }
                }
            }
        }

        if ($this->request->isPjax()) {
            echo '<div id="userContent" class="uc-content">';
            $this->render('order_detail', get_defined_vars());
            echo '</div>';
        } else {
            \App\User\UserShell::render(dirname(__DIR__) . '/View/order_detail.php', $frontUser, get_defined_vars());
        }
    }
}
