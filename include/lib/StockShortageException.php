<?php

declare(strict_types=1);

/**
 * 库存不足异常。
 *
 * 携带商品标题与剩余数量，用法：
 *   - $e->getMessage()    返回简短消息（"库存不足：仅剩 N 件"），用于单商品场景如商品详情页下单
 *   - $e->getFullMessage() 返回带商品名前缀的消息，用于多商品场景
 *
 * 这样调用方可按场景决定展示形式，不必通过正则解析消息，避免商品名含特殊字符（【】等）出错。
 */
class StockShortageException extends RuntimeException
{
    public string $goodsTitle;
    public int $remaining;

    public function __construct(string $goodsTitle, int $remaining)
    {
        $this->goodsTitle = $goodsTitle;
        $this->remaining = $remaining;
        parent::__construct('库存不足:仅剩 ' . $remaining . ' 件');
    }

    /**
     * 带商品名前缀的完整消息，多商品场景使用。
     */
    public function getFullMessage(): string
    {
        return '【' . $this->goodsTitle . '】库存不足:仅剩 ' . $this->remaining . ' 件';
    }
}
