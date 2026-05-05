<?php

declare(strict_types=1);

/**
 * 永久性发货异常：Swoole 发货队列捕获到这类异常时，
 * 会立即把任务标记为 failed，不再重试。
 *
 * 使用场景（由商品类型插件的 `goods_type_*_order_paid` 钩子抛出）：
 *   - 上游账户余额不足
 *   - 上游商品已下架 / 不存在
 *   - 签名错误 / 账户被封禁
 *   - 订单金额/SKU 不匹配等业务校验失败
 *
 * 相对的"可重试异常"：网络超时、临时 5xx 等用普通 RuntimeException 抛即可，
 * 队列会按 max_attempts 做指数退避重试。
 */
class PermanentDeliveryException extends RuntimeException
{
}
