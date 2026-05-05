<?php

declare(strict_types=1);

/**
 * 优惠券服务 —— 校验可用性、计算折扣、执行"使用"动作。
 *
 * 对外提供的方法:
 *   - check($code, $orderData)   校验 + 折扣预计算（下单前调用）
 *   - apply($code, $userId, $orderId, $goodsAmountBigint)  订单创建时真正扣减
 *
 * 业务约定：
 *   - 金额统一 BIGINT ×1000000
 *   - 折扣返回金额 BIGINT，不能超过订单商品总额
 *   - 使用次数用 UPDATE 条件扣减保证并发安全（参见 CouponModel::incrementUsedCount）
 */
class CouponService
{
    /**
     * 校验一个券码能否用于指定订单上下文；返回折扣金额 BIGINT 与简要信息。
     *
     * @param string $code 券码
     * @param array  $context {
     *     goods_amount_raw: int,         订单商品总额 BIGINT
     *     goods_items: array,            商品项列表 [{goods_id, spec_id, quantity, category_id?, goods_type?}]
     *     user_id: int,                  登录用户 id，0 为游客
     * }
     * @return array ['coupon' => ..., 'discount_raw' => int, 'discount' => '0.00']
     * @throws RuntimeException 校验失败
     */
    public function check(string $code, array $context): array
    {
        $code = trim($code);
        if ($code === '') {
            throw new RuntimeException('请输入优惠券码');
        }

        $couponModel = new CouponModel();
        $coupon = $couponModel->findByCode($code);
        if (!$coupon) {
            throw new RuntimeException('优惠券不存在');
        }
        if (!$coupon['is_enabled']) {
            throw new RuntimeException('优惠券已下架');
        }

        $now = time();
        if (!empty($coupon['start_at']) && strtotime((string) $coupon['start_at']) > $now) {
            throw new RuntimeException('优惠券尚未开始使用');
        }
        if (!empty($coupon['end_at']) && strtotime((string) $coupon['end_at']) < $now) {
            throw new RuntimeException('优惠券已过期');
        }

        $total = (int) $coupon['total_usage_limit'];
        if ($total !== -1 && (int) $coupon['used_count'] >= $total) {
            throw new RuntimeException('优惠券已被领完');
        }

        $goodsAmountRaw = (int) ($context['goods_amount_raw'] ?? 0);
        $minAmountRaw   = (int) ($coupon['min_amount_raw'] ?? 0);
        if ($goodsAmountRaw < $minAmountRaw) {
            $minDisplay = bcdiv((string) $minAmountRaw, '1000000', 2);
            throw new RuntimeException('订单满 ' . $minDisplay . ' 元可用');
        }

        // 适用范围校验
        if (!$this->matchScope($coupon, $context['goods_items'] ?? [])) {
            throw new RuntimeException('订单中无适用的商品');
        }

        // 计算折扣
        $discountRaw = $this->calcDiscount($coupon, $goodsAmountRaw);
        if ($discountRaw <= 0) {
            throw new RuntimeException('本次订单无可抵扣金额');
        }

        return [
            'coupon'       => $coupon,
            'discount_raw' => $discountRaw,
            'discount'     => bcdiv((string) $discountRaw, '1000000', 2),
        ];
    }

    /**
     * 订单创建时扣减券次数 + 标记用户券为 used（如果是已领取使用）。
     *
     * 应在订单创建事务中调用。扣减失败（超上限）时抛异常中断下单。
     *
     * @param array $coupon  已校验过的券数据（check() 返回的 coupon）
     * @param int   $userId  登录用户 id，0=游客
     * @param int   $orderId 订单 id
     */
    public function apply(array $coupon, int $userId, int $orderId): void
    {
        $couponModel = new CouponModel();
        if (!$couponModel->incrementUsedCount((int) $coupon['id'])) {
            throw new RuntimeException('优惠券已被领完');
        }

        // 若用户已领取该券，标记为已使用
        if ($userId > 0) {
            $userCouponModel = new UserCouponModel();
            $row = $userCouponModel->findByUserAndCoupon($userId, (int) $coupon['id']);
            if ($row && ($row['status'] ?? '') === 'unused') {
                $userCouponModel->markUsed((int) $row['id'], $orderId);
            }
        }
    }

    /**
     * 按适用范围判断券是否匹配本订单商品。
     */
    private function matchScope(array $coupon, array $goodsItems): bool
    {
        $scope = (string) ($coupon['apply_scope'] ?? 'all');
        if ($scope === CouponModel::SCOPE_ALL) return true;

        $ids = is_array($coupon['apply_ids'] ?? null) ? $coupon['apply_ids'] : [];
        if (empty($ids)) return false; // 指定范围但无目标 = 无可用

        foreach ($goodsItems as $item) {
            switch ($scope) {
                case CouponModel::SCOPE_GOODS:
                    if (in_array((int) ($item['goods_id'] ?? 0), $ids, true)) return true;
                    break;
                case CouponModel::SCOPE_CATEGORY:
                    if (in_array((int) ($item['category_id'] ?? 0), $ids, true)) return true;
                    break;
                case CouponModel::SCOPE_GOODS_TYPE:
                    if (in_array((string) ($item['goods_type'] ?? ''), array_map('strval', $ids), true)) return true;
                    break;
            }
        }
        return false;
    }

    /**
     * 按券类型计算折扣金额（BIGINT ×1000000）。
     * 打折券带 max_discount 封顶。最终折扣不超过订单商品总额。
     */
    private function calcDiscount(array $coupon, int $goodsAmountRaw): int
    {
        $type = (string) ($coupon['type'] ?? '');
        $discountRaw = 0;

        if ($type === CouponModel::TYPE_FIXED_AMOUNT) {
            $discountRaw = (int) ($coupon['value_raw'] ?? 0);
        } elseif ($type === CouponModel::TYPE_PERCENT) {
            // value 存整数 0-100（如 85 = 8.5折，即减 15%）
            $percent = (int) ($coupon['value_raw'] ?? 0);
            if ($percent >= 100) return 0;
            // 减免比例 = (100 - percent) / 100
            $discountRaw = (int) ((100 - $percent) * $goodsAmountRaw / 100);
            $maxRaw = (int) ($coupon['max_discount_raw'] ?? 0);
            if ($maxRaw > 0 && $discountRaw > $maxRaw) {
                $discountRaw = $maxRaw;
            }
        } elseif ($type === CouponModel::TYPE_FREE_SHIPPING) {
            // 免邮券：暂不涉及运费，留作插件扩展接入；这里按 value 当作抵扣金额处理（若有）
            $discountRaw = (int) ($coupon['value_raw'] ?? 0);
        }

        // 不能超过订单商品总额
        if ($discountRaw > $goodsAmountRaw) $discountRaw = $goodsAmountRaw;
        if ($discountRaw < 0) $discountRaw = 0;
        return $discountRaw;
    }
}
