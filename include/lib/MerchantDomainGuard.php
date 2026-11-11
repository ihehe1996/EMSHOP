<?php

declare(strict_types=1);

/**
 * 商户域名校验（兼容门面）。
 *
 * 实际实现已迁到 App\Support\MerchantDomainGuard 实例服务；这里保留原有静态 API，
 * 商户设置页、后台开通/编辑和 MerchantContext 的调用点都不用改。
 */
final class MerchantDomainGuard
{
    /** @var \App\Support\MerchantDomainGuard|null */
    private static $service;

    /**
     * 获取内部域名校验服务实例。
     */
    public static function instance(): \App\Support\MerchantDomainGuard
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\MerchantDomainGuard::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/MerchantDomainGuard.php';
            }
            self::$service = new \App\Support\MerchantDomainGuard();
        }

        return self::$service;
    }

    /**
     * 归一化域名：小写、去协议、路径、端口和通配符前缀。
     */
    public static function normalizeHost(string $value): string
    {
        return self::instance()->normalizeHost($value);
    }

    /**
     * 主站保留域名列表（site_url / main_domain，含 www 互转）。
     *
     * @return string[]
     */
    public static function mainHosts(): array
    {
        return self::instance()->mainHosts();
    }

    /**
     * host 是否属于主站。
     */
    public static function isMainHost(string $host): bool
    {
        return self::instance()->isMainHost($host);
    }

    /**
     * 自定义域名冲突检查；返回空串表示可用。
     */
    public static function customDomainError(string $domain, int $excludeId = 0): string
    {
        return self::instance()->customDomainError($domain, $excludeId);
    }

    /**
     * 二级域名冲突检查；返回空串表示可用。
     */
    public static function subdomainError(string $subdomain, int $excludeId = 0): string
    {
        return self::instance()->subdomainError($subdomain, $excludeId);
    }
}
