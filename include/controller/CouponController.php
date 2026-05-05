<?php

declare(strict_types=1);

/**
 * 前台优惠券控制器。
 *
 * 路由：
 *   _index()  领券中心 → coupon.php 模板
 *   receive() AJAX 领取（登录用户）
 *   check()   AJAX 校验券码 + 返回折扣预估（供下单页）
 *   mine()    AJAX 获取当前用户的可用券列表（下单页"选择"弹窗用）
 */
class CouponController extends BaseController
{
    /**
     * 获取当前用户身份（登录状态 + guest_token）。
     */
    private function getIdentity(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $frontUser = $_SESSION['em_front_user'] ?? null;
        return [
            'user_id' => !empty($frontUser['id']) ? (int) $frontUser['id'] : 0,
        ];
    }

    /**
     * 领券中心（前台页面）。
     */
    public function _index(): void
    {
        $this->view->setTitle('领券中心');

        $couponModel = new CouponModel();
        $coupons = $couponModel->getPubliclyClaimable(100);

        $identity = $this->getIdentity();
        $isLoggedIn = $identity['user_id'] > 0;

        // 已登录：查出哪些券已领取，用于按钮状态
        $claimedIds = [];
        if ($isLoggedIn) {
            $prefix = Database::prefix();
            $rows = Database::query(
                "SELECT coupon_id FROM {$prefix}user_coupon WHERE user_id = ?",
                [$identity['user_id']]
            );
            $claimedIds = array_map(fn($r) => (int) $r['coupon_id'], $rows);
        }

        $this->view->setData([
            'coupons'     => $coupons,
            'is_logged_in' => $isLoggedIn,
            'claimed_ids' => $claimedIds,
        ]);
        $this->view->render('coupon');
    }

    /**
     * AJAX：用户领取一张券。
     */
    public function receive(): void
    {
        if (!Request::isPost()) Response::error('无效请求');

        $identity = $this->getIdentity();
        if ($identity['user_id'] <= 0) {
            Response::error('请先登录');
        }

        $couponId = (int) Input::post('coupon_id', 0);
        if ($couponId <= 0) Response::error('参数错误');

        $couponModel = new CouponModel();
        $coupon = $couponModel->findById($couponId);
        if (!$coupon) Response::error('优惠券不存在');
        if (!$coupon['is_enabled']) Response::error('优惠券已下架');

        $now = time();
        if (!empty($coupon['start_at']) && strtotime((string) $coupon['start_at']) > $now) {
            Response::error('优惠券尚未开始');
        }
        if (!empty($coupon['end_at']) && strtotime((string) $coupon['end_at']) < $now) {
            Response::error('优惠券已过期');
        }

        $total = (int) $coupon['total_usage_limit'];
        if ($total !== -1 && (int) $coupon['used_count'] >= $total) {
            Response::error('优惠券已被领完');
        }

        $userCouponModel = new UserCouponModel();
        try {
            $userCouponModel->claim($identity['user_id'], $couponId);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage());
        }

        Response::success('领取成功');
    }

    /**
     * AJAX：按 code 校验券 + 预估折扣。
     *
     * 入参：code、goods_amount（实际金额字符串，如"100.00"）、可选的 goods_items（JSON）
     */
    public function check(): void
    {
        if (!Request::isPost()) Response::error('无效请求');

        $code = trim((string) Input::post('code', ''));
        $goodsAmount = trim((string) Input::post('goods_amount', '0'));
        $goodsItemsJson = (string) Input::post('goods_items', '[]');

        if ($code === '') Response::error('请输入优惠券码');
        if (!is_numeric($goodsAmount) || (float) $goodsAmount <= 0) Response::error('订单金额无效');

        $goodsAmountRaw = (int) bcmul($goodsAmount, '1000000', 0);
        $goodsItems = json_decode($goodsItemsJson, true) ?: [];

        $service = new CouponService();
        try {
            $result = $service->check($code, [
                'goods_amount_raw' => $goodsAmountRaw,
                'goods_items'      => $goodsItems,
                'user_id'          => $this->getIdentity()['user_id'],
            ]);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage());
        }

        $coupon = $result['coupon'];
        Response::success('可用', [
            'coupon'   => [
                'id'    => (int) $coupon['id'],
                'code'  => $coupon['code'],
                'title' => $coupon['title'] ?: $coupon['name'],
                'type'  => $coupon['type'],
            ],
            'discount' => $result['discount'],
        ]);
    }

    /**
     * AJAX：获取当前用户**未使用、未过期、未失效**的券（下单页"选择"弹窗）。
     */
    public function mine(): void
    {
        $identity = $this->getIdentity();
        if ($identity['user_id'] <= 0) {
            Response::success('', ['coupons' => []]);
        }

        $userCouponModel = new UserCouponModel();
        $list = $userCouponModel->listByView($identity['user_id'], UserCouponModel::VIEW_UNUSED, 50);

        $out = [];
        foreach ($list as $row) {
            $out[] = [
                'user_coupon_id' => (int) $row['user_coupon_id'],
                'id'             => (int) $row['id'],
                'code'           => $row['code'],
                'name'           => $row['name'],
                'title'          => $row['title'] ?: $row['name'],
                'type'           => $row['type'],
                'value'          => $row['value'],
                'min_amount'     => $row['min_amount'],
                'max_discount'   => $row['max_discount'],
                'end_at'         => $row['end_at'],
            ];
        }
        Response::success('', ['coupons' => $out]);
    }
}
