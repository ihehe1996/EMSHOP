<?php

declare(strict_types=1);

namespace App\User\Controller;

/**
 * 用户中心页面（/user/coupon）。
 */
final class CouponController extends UserBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        if (!shop_coupon_enabled()) {
            if ($this->request->isPjax() || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->response->error('优惠券功能未启用');
            }
            header('Location: /user/home');
            exit;
        }

        $siteName = $this->config->get('sitename', 'EMSHOP');
        $csrfToken = $this->csrf->token();
        $displayMoney = '0.00';
        if (!empty($frontUser['money'])) {
            $displayMoney = bcdiv((string) $frontUser['money'], '1000000', 2);
        }
        // tab 白名单
        $allowed = [
            \App\Model\UserCouponModel::VIEW_UNUSED,
            \App\Model\UserCouponModel::VIEW_USED,
            \App\Model\UserCouponModel::VIEW_EXPIRED,
            \App\Model\UserCouponModel::VIEW_INVALID,
        ];
        $view = (string) $this->input->get('view', \App\Model\UserCouponModel::VIEW_UNUSED);
        if (!in_array($view, $allowed, true)) $view = \App\Model\UserCouponModel::VIEW_UNUSED;

        $userCouponModel = app(\App\Model\UserCouponModel::class);
        $coupons = $userCouponModel->listByView((int) $frontUser['id'], $view, 200);
        $counts  = $userCouponModel->countByViews((int) $frontUser['id']);

        $viewTabs = [
            \App\Model\UserCouponModel::VIEW_UNUSED  => '未使用',
            \App\Model\UserCouponModel::VIEW_USED    => '已使用',
            \App\Model\UserCouponModel::VIEW_EXPIRED => '已过期',
            \App\Model\UserCouponModel::VIEW_INVALID => '已失效',
        ];

        if ($this->request->isPjax()) {
            echo '<div id="userContent" class="uc-content">';
            $this->render('coupon', get_defined_vars());
            echo '</div>';
        } else {
            \App\User\UserShell::render(dirname(__DIR__) . '/View/coupon.php', $frontUser, get_defined_vars());
        }
    }
}
