<?php

declare(strict_types=1);

/**
 * 前台优惠券控制器。
 *
 * 路由：
 *   display() 领券中心 → coupon.php 模板
 *   receive() AJAX 领取（登录用户）
 *   check()   AJAX 校验券码 + 返回折扣预估（供下单页）
 *   mine()    AJAX 获取当前用户的可用券列表（下单页"选择"弹窗用）
 *
 * 与商品 / 文章 / 搜索侧同一套约定：页面动作**不取数**，数据由 coupon.php 开头调
 * module.php 的 template_coupon_data() 取，页面标题也归 module.php。
 * 控制器只留下「功能是否开启」这个必须在任何输出之前完成的守卫（未开启直接 404）。
 *
 * 动作名不叫 `_index`，入口登记在 Dispatcher::DEFAULT_ACTIONS（'coupon' → display），
 * pretty 路由（/coupon.html、/coupon/）也各自写死这个动作名。
 *
 * receive / check / mine 是 AJAX 接口（返回 JSON、不渲染模板），其中的入参校验与
 * 业务调用属于控制器本职，保持原样。
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
     *
     * 券列表与领取状态由 coupon.php 自己取（module.php 的 template_coupon_data()）。
     * 标题固定，就放在守卫之后由控制器设——不能放 module.php：功能未开启时走的是
     * render404()，module.php 会把「页面不存在」覆盖掉。
     */
    public function display(): void
    {
        // 「功能没开就不给进」必须在任何输出之前判断，所以留在控制器
        if (!shop_coupon_enabled()) {
            $this->dispatcher->render404('优惠券功能未启用');
            return;
        }

        $this->view->setTitle('领券中心');
        $this->view->render('coupon');
    }

    /**
     * AJAX：用户领取一张券。
     */
    public function receive(): void
    {
        if (!Request::isPost()) Response::error('无效请求');
        if (!shop_coupon_enabled()) {
            Response::error('优惠券功能未启用');
        }

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
        if ((int) ($coupon['show_on_front'] ?? 1) !== 1) {
            Response::error('该优惠券不可在领券中心领取');
        }

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
        if (!shop_coupon_enabled()) {
            Response::error('优惠券功能未启用');
        }

        $code = trim((string) Input::post('code', ''));
        $goodsItemsJson = (string) Input::post('goods_items', '[]');

        if ($code === '') Response::error('请输入优惠券码');

        // Money::parse 先做严格的十进制白名单校验，再换算成主货币 micro 整数。
        // 此处原先的 bcmul 还在 try 之外 —— 传 1e5 会抛出未捕获的 ValueError，
        // 使这个免登录接口直接 500（PHP 7.4 下则静默截断成错误金额）。
        try {
            $goodsAmountRaw = Money::parse(Input::post('goods_amount', '0'));
        } catch (InvalidArgumentException $e) {
            Response::error('订单金额无效');
        }
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
        if (!shop_coupon_enabled()) {
            Response::success('', ['coupons' => []]);
        }

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
