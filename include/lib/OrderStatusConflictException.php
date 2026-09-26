<?php

declare(strict_types=1);

/**
 * 订单状态流转冲突（乐观锁未命中）。
 *
 * `OrderModel::changeStatus()` 用 CAS 更新（`WHERE id = ? AND status = ?`）防止并发下
 * 同一订单被重复推进。当受影响行数为 0，说明状态已被另一个请求改走，此时抛本异常。
 *
 * 为什么是抛异常而不是 `return false`：
 *   调用方普遍是「先查状态 → 再改状态 → 再写副作用」的非原子序列。以支付回调为例
 *   （`content/plugin/alipay/alipay.php`），若返回 false 让流程继续，它会紧接着
 *   `INSERT INTO order_payment`，**写出第二条支付流水**。抛异常能让调用方既有的
 *   `catch → Database::rollBack()` 把整个事务撤掉，网关重投时再走幂等分支。
 *
 * 因此调用方应把本异常视为「本次操作无效，事务已回滚」，而不是内部错误。
 */
class OrderStatusConflictException extends RuntimeException
{
    public int $orderId;

    /** 调用方认为订单当前所处的状态。 */
    public string $expectedStatus;

    /** 本次想要流转到的目标状态。 */
    public string $targetStatus;

    public function __construct(int $orderId, string $expected, string $target)
    {
        $this->orderId = $orderId;
        $this->expectedStatus = $expected;
        $this->targetStatus = $target;

        parent::__construct(
            "订单 {$orderId} 状态已变更（期望 {$expected} → {$target}），本次操作未生效"
        );
    }
}
