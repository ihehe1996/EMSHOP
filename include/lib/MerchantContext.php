<?php

declare(strict_types=1);

/**
 * 商户（分站）上下文（兼容门面）。
 *
 * 实际实现已迁到 App\Support\MerchantContext 实例服务；这里保留原有静态 API，
 * 现有调用点、插件和模板都不用改。
 */
final class MerchantContext
{
    /** @var \App\Support\MerchantContext|null */
    private static $service;

    /**
     * 获取内部商户上下文服务实例。
     */
    public static function instance(): \App\Support\MerchantContext
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\MerchantContext::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/MerchantContext.php';
            }
            self::$service = new \App\Support\MerchantContext();
        }

        return self::$service;
    }

    /**
     * 识别当前请求的商户上下文（幂等）。
     */
    public static function resolve(): void
    {
        self::instance()->resolve();
    }

    /**
     * 商户等级门控的拦截原因；未拦截返回空串。
     */
    public static function blockedReason(): string
    {
        return self::instance()->blockedReason();
    }

    /**
     * 当前商户 id；主站返回 0。
     */
    public static function currentId(): int
    {
        return self::instance()->currentId();
    }

    /**
     * 当前商户主 user_id；主站返回 0。
     */
    public static function currentOwnerId(): int
    {
        return self::instance()->currentOwnerId();
    }

    /**
     * 当前商户完整行；主站返回 null。
     *
     * @return array<string, mixed>|null
     */
    public static function current(): ?array
    {
        return self::instance()->current();
    }

    /**
     * 是否当前请求在主站上下文。
     */
    public static function isMaster(): bool
    {
        return self::instance()->isMaster();
    }

    /**
     * 判断商品记录是否对当前作用域可见。
     *
     * @param array<string, mixed> $goods
     */
    public static function isGoodsVisibleToCurrentScope(array $goods): bool
    {
        return self::instance()->isGoodsVisibleToCurrentScope($goods);
    }

    /**
     * 测试场景手动设置当前商户（生产代码不要用）。
     *
     * @param array<string, mixed>|null $merchant
     */
    public static function setCurrent(?array $merchant): void
    {
        self::instance()->setCurrent($merchant);
    }

    /**
     * 计算商户的店铺前台 URL。
     *
     * @param array<string, mixed> $merchant
     */
    public static function storefrontUrl(array $merchant): string
    {
        return self::instance()->storefrontUrl($merchant);
    }

    /**
     * 把"最近访问的店铺"写入 session。
     *
     * @param array<string, mixed> $merchant
     */
    public static function rememberLastMerchant(array $merchant): void
    {
        self::instance()->rememberLastMerchant($merchant);
    }

    /**
     * 读取 session 里最近访问过的店铺。
     *
     * @return array{id:int, name:string, url:string}|null
     */
    public static function lastMerchant(): ?array
    {
        return self::instance()->lastMerchant();
    }
}
