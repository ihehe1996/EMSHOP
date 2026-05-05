<?php

declare(strict_types=1);

/**
 * 商户账务服务。
 *
 * 职责：订单完成 / 退款时的店铺余额分账。
 * 所有算法**依赖 em_order_goods 的快照字段**（goods_owner_id / cost_amount / fee_amount），
 * 不重算，保证下单时的费率即使事后变更也不会影响历史订单。
 *
 * 方案参考：a 系统文档/分站功能方案.md §6。
 *
 * 入参假设：
 *   - 进入 settleOrder 时，订单已是 completed 状态、merchant_id > 0、快照已落表
 *   - 调用方不开事务；本服务自己开事务保证 log 与 balance 原子写入
 */
final class MerchantLedgerService
{
    /**
     * 计算自建商品的主站手续费（×1000000）。
     * 公式：fee = price × qty × self_goods_fee_rate / 10000
     *
     * @param int $merchantId
     * @param int $priceRaw
     * @param int $quantity
     * @return int
     */
    public static function computeSelfFee(int $merchantId, int $priceRaw, int $quantity): int
    {
        $level = self::resolveMerchantLevel($merchantId);
        $feeRate = (int) ($level['self_goods_fee_rate'] ?? 0);
        if ($feeRate <= 0) return 0;
        return (int) floor($priceRaw * $quantity * $feeRate / 10000);
    }

    /**
     * 订单完成时结算：把商户实得入账到 em_user.shop_balance。
     *
     * 幂等：若该订单已有 increase 记录，直接跳过（防重入）。
     */
    public static function settleOrder(int $orderId): void
    {
        $order = Database::find('order', $orderId);
        if ($order === null) return;
        $merchantId = (int) ($order['merchant_id'] ?? 0);
        if ($merchantId <= 0) return;
        if (($order['status'] ?? '') !== 'completed') return;

        $logTable = Database::prefix() . 'merchant_balance_log';

        // 幂等：已结算过则跳
        $existing = Database::fetchOne(
            'SELECT `id` FROM `' . $logTable . '` WHERE `order_id` = ? AND `type` = ? LIMIT 1',
            [$orderId, 'increase']
        );
        if ($existing !== null) return;

        $merchant = Database::find('merchant', $merchantId);
        if ($merchant === null || (int) $merchant['status'] !== 1) return;

        $ownerUserId = (int) $merchant['user_id'];

        // 汇总商户实得：Σ(line_price − cost − fee)
        $items = Database::query(
            'SELECT `id`, `price`, `quantity`, `cost_amount`, `fee_amount`, `goods_owner_id`
               FROM `' . Database::prefix() . 'order_goods`
              WHERE `order_id` = ?',
            [$orderId]
        );

        $totalLine = 0;    // Σ(price × qty)
        $totalCost = 0;
        $totalFee = 0;
        foreach ($items as $it) {
            $totalLine += ((int) $it['price']) * ((int) $it['quantity']);
            $totalCost += (int) ($it['cost_amount'] ?? 0);
            $totalFee  += (int) ($it['fee_amount'] ?? 0);
        }

        // 商户实得 = Σ(每行 price × qty − cost − fee)，跟 pay_amount 解耦。
        // 优惠券（discount_amount）由主站承担 —— 主站做营销活动是它自己的策略，
        // 不把代价摊给商户。否则会出现"主站发券、商户卖货倒亏"的反直觉情况。
        $income = $totalLine - $totalCost - $totalFee;
        if ($income < 0) $income = 0; // 边界保护；正常不会负

        Database::begin();
        try {
            // 锁用户行
            $userTable = Database::prefix() . 'user';
            $userRow = Database::fetchOne(
                'SELECT `shop_balance` FROM `' . $userTable . '` WHERE `id` = ? FOR UPDATE',
                [$ownerUserId]
            );
            if ($userRow === null) {
                throw new RuntimeException('商户主不存在');
            }
            $before = (int) $userRow['shop_balance'];
            $after = $before + $income;

            Database::execute(
                'UPDATE `' . $userTable . '` SET `shop_balance` = ? WHERE `id` = ?',
                [$after, $ownerUserId]
            );

            Database::insert('merchant_balance_log', [
                'merchant_id' => $merchantId,
                'user_id' => $ownerUserId,
                'type' => 'increase',
                'amount' => $income,
                'before_balance' => $before,
                'after_balance' => $after,
                'order_id' => $orderId,
                'remark' => '订单完成入账 #' . ($order['order_no'] ?? $orderId),
                'operator_id' => 0,
            ]);

            // 子商户返佣已移除（规避传销风险）

            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * 订单退款时倒扣店铺余额入账。
     *
     * 幂等：每次 refund 根据已有 increase - refund 净额判断是否需要退回。
     * 若之前根本没 settle 过（如订单没到 completed 就退款），直接跳过。
     */
    public static function refundOrder(int $orderId): void
    {
        $order = Database::find('order', $orderId);
        if ($order === null) return;
        $merchantId = (int) ($order['merchant_id'] ?? 0);
        if ($merchantId <= 0) return;

        $logTable = Database::prefix() . 'merchant_balance_log';

        // 幂等：之前有过 refund → 跳过
        $refundExist = Database::fetchOne(
            'SELECT `id` FROM `' . $logTable . '` WHERE `order_id` = ? AND `type` = ? LIMIT 1',
            [$orderId, 'refund']
        );
        if ($refundExist !== null) return;

        // 取原入账记录
        $settle = Database::fetchOne(
            'SELECT * FROM `' . $logTable . '` WHERE `order_id` = ? AND `type` = ? LIMIT 1',
            [$orderId, 'increase']
        );
        if ($settle === null) {
            // 订单没 settle 过（可能 paid 阶段直接 refund），无需倒扣
            return;
        }

        $merchant = Database::find('merchant', $merchantId);
        if ($merchant === null) return;
        $ownerUserId = (int) $merchant['user_id'];
        $refundAmount = (int) $settle['amount'];

        Database::begin();
        try {
            $userTable = Database::prefix() . 'user';
            $userRow = Database::fetchOne(
                'SELECT `shop_balance` FROM `' . $userTable . '` WHERE `id` = ? FOR UPDATE',
                [$ownerUserId]
            );
            if ($userRow === null) {
                throw new RuntimeException('商户主不存在');
            }
            $before = (int) $userRow['shop_balance'];
            $after = $before - $refundAmount; // 允许为负数（已提现走掉的情况）

            Database::execute(
                'UPDATE `' . $userTable . '` SET `shop_balance` = ? WHERE `id` = ?',
                [$after, $ownerUserId]
            );

            Database::insert('merchant_balance_log', [
                'merchant_id' => $merchantId,
                'user_id' => $ownerUserId,
                'type' => 'refund',
                'amount' => $refundAmount,
                'before_balance' => $before,
                'after_balance' => $after,
                'order_id' => $orderId,
                'remark' => '订单退款倒扣 #' . ($order['order_no'] ?? $orderId),
                'operator_id' => 0,
            ]);

            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    // ------------------------------------------------------------
    // 私有
    // ------------------------------------------------------------

    /**
     * 解析商户的等级行。
     *
     * @return array<string, mixed>|null
     */
    private static function resolveMerchantLevel(int $merchantId): ?array
    {
        static $cache = [];
        if (array_key_exists($merchantId, $cache)) return $cache[$merchantId];

        $m = Database::find('merchant', $merchantId);
        if ($m === null) return $cache[$merchantId] = null;
        $l = Database::find('merchant_level', (int) $m['level_id']);
        return $cache[$merchantId] = $l;
    }
}
