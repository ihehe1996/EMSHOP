<?php

declare(strict_types=1);

namespace App\Model;

/**
 * 模型基类（新写法）。
 *
 * 模型是实例类：数据访问走注入的 Database，不再直接调 `\Database::`。
 * 实例由容器创建（`app(PageModel::class)`），构造函数参数可自动注入。
 */
abstract class Model
{
    /** @var \App\Support\Database */
    protected $db;

    /** @var \App\Support\Config */
    protected $config;

    /** @var \App\Support\Cache */
    protected $cache;

    /** @var \App\Support\Currency */
    protected $currency;

    /** @var \App\Support\Money */
    protected $money;

    /** @var \App\Support\MerchantContext */
    protected $merchantContext;

    public function __construct(
        ?\App\Support\Database $db = null,
        ?\App\Support\Config $config = null,
        ?\App\Support\Cache $cache = null,
        ?\App\Support\Currency $currency = null,
        ?\App\Support\Money $money = null,
        ?\App\Support\MerchantContext $merchantContext = null
    ) {
        $container = \App\Core\Application::instance()->container();

        $this->db              = $db ?? $container->make(\App\Support\Database::class);
        $this->config          = $config ?? $container->make(\App\Support\Config::class);
        $this->cache           = $cache ?? $container->make(\App\Support\Cache::class);
        $this->currency        = $currency ?? $container->make(\App\Support\Currency::class);
        $this->money           = $money ?? $container->make(\App\Support\Money::class);
        $this->merchantContext = $merchantContext ?? $container->make(\App\Support\MerchantContext::class);
    }
}
