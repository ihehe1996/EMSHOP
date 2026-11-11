<?php

declare(strict_types=1);

/**
 * 富文本 HTML 清洗（兼容门面）。
 *
 * 实际实现已迁到 App\Support\HtmlSanitizer 实例服务；这里保留原有静态 API，
 * 商户公告、商品详情、页面、文章等调用点都不用改。
 */
final class HtmlSanitizer
{
    /** @var \App\Support\HtmlSanitizer|null */
    private static $service;

    /**
     * 获取内部 HTML 清洗服务实例。
     */
    public static function instance(): \App\Support\HtmlSanitizer
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\HtmlSanitizer::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/HtmlSanitizer.php';
            }
            self::$service = new \App\Support\HtmlSanitizer();
        }

        return self::$service;
    }

    public static function clean(string $html): string
    {
        return self::instance()->clean($html);
    }
}
