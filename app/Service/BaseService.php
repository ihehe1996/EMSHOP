<?php

declare(strict_types=1);

namespace App\Service;

/**
 * 服务基类（新写法）。
 *
 * 服务是实例类：数据库、配置、缓存、货币等通过容器注入，不再直接调全局门面。
 * 实例由容器创建（`app(PaymentService::class)`），调用点按需取用。
 */
abstract class BaseService
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

    /** @var \App\Support\LicenseClient */
    protected $licenseClient;

    /** @var \App\Support\RateLimit */
    protected $rateLimit;

    /** @var \App\Support\Captcha */
    protected $captcha;

    /** @var \App\Support\Mailer */
    protected $mailer;

    /** @var \App\Support\CsrfSecret */
    protected $csrfSecret;

    /** @var \App\Support\GoodsTypeManager */
    protected $goodsTypeManager;

    /** @var \App\Http\Response */
    protected $response;

    /** @var \App\Http\Request */
    protected $request;

    public function __construct(
        ?\App\Support\Database $db = null,
        ?\App\Support\Config $config = null,
        ?\App\Support\Cache $cache = null,
        ?\App\Support\Currency $currency = null,
        ?\App\Support\Money $money = null,
        ?\App\Support\MerchantContext $merchantContext = null,
        ?\App\Support\LicenseClient $licenseClient = null,
        ?\App\Support\RateLimit $rateLimit = null,
        ?\App\Support\Captcha $captcha = null,
        ?\App\Support\Mailer $mailer = null,
        ?\App\Support\CsrfSecret $csrfSecret = null,
        ?\App\Support\GoodsTypeManager $goodsTypeManager = null,
        ?\App\Http\Response $response = null,
        ?\App\Http\Request $request = null
    ) {
        $container = \App\Core\Application::instance()->container();

        $this->db               = $db ?? $container->make(\App\Support\Database::class);
        $this->config           = $config ?? $container->make(\App\Support\Config::class);
        $this->cache            = $cache ?? $container->make(\App\Support\Cache::class);
        $this->currency         = $currency ?? $container->make(\App\Support\Currency::class);
        $this->money            = $money ?? $container->make(\App\Support\Money::class);
        $this->merchantContext  = $merchantContext ?? $container->make(\App\Support\MerchantContext::class);
        $this->licenseClient    = $licenseClient ?? $container->make(\App\Support\LicenseClient::class);
        $this->rateLimit        = $rateLimit ?? $container->make(\App\Support\RateLimit::class);
        $this->captcha          = $captcha ?? $container->make(\App\Support\Captcha::class);
        $this->mailer           = $mailer ?? $container->make(\App\Support\Mailer::class);
        $this->csrfSecret       = $csrfSecret ?? $container->make(\App\Support\CsrfSecret::class);
        $this->goodsTypeManager = $goodsTypeManager ?? $container->make(\App\Support\GoodsTypeManager::class);
        $this->response         = $response ?? $container->make(\App\Http\Response::class);
        $this->request          = $request ?? $container->make(\App\Http\Request::class);
    }
}
