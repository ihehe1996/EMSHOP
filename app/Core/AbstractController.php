<?php

declare(strict_types=1);

namespace App\Core;

/**
 * 控制器基类（新写法）。
 *
 * 请求/响应/配置这些基础服务通过容器注入成实例属性，控制器里用 `$this->input`、
 * `$this->response` 等访问，不再直接调 `\Input::`、`\Response::` 这些全局门面。
 * 门面仍然保留给模板 / 插件等外部代码使用。
 */
abstract class AbstractController
{
    /** @var \App\Support\Input */
    protected $input;

    /** @var \App\Http\Request */
    protected $request;

    /** @var \App\Http\Response */
    protected $response;

    /** @var \App\Support\Csrf */
    protected $csrf;

    /** @var \App\Support\Config */
    protected $config;

    /** @var \App\Support\Currency */
    protected $currency;

    /** @var \App\Support\Database */
    protected $db;

    /** @var \App\Support\Cache */
    protected $cache;

    /** @var \App\Support\LicenseClient */
    protected $licenseClient;

    /** @var \App\Support\MerchantContext */
    protected $merchantContext;

    /** @var \App\Support\Money */
    protected $money;

    /** @var \App\Support\RateLimit */
    protected $rateLimit;

    /** @var \App\Support\Captcha */
    protected $captcha;

    /** @var \App\Support\Mailer */
    protected $mailer;

    public function __construct(
        ?\App\Support\Input $input = null,
        ?\App\Http\Request $request = null,
        ?\App\Http\Response $response = null,
        ?\App\Support\Csrf $csrf = null,
        ?\App\Support\Config $config = null,
        ?\App\Support\Currency $currency = null,
        ?\App\Support\Database $db = null,
        ?\App\Support\Cache $cache = null,
        ?\App\Support\LicenseClient $licenseClient = null,
        ?\App\Support\MerchantContext $merchantContext = null,
        ?\App\Support\Money $money = null,
        ?\App\Support\RateLimit $rateLimit = null,
        ?\App\Support\Captcha $captcha = null,
        ?\App\Support\Mailer $mailer = null
    ) {
        $container = Application::instance()->container();

        $this->input           = $input ?? $container->make(\App\Support\Input::class);
        $this->request         = $request ?? $container->make(\App\Http\Request::class);
        $this->response        = $response ?? $container->make(\App\Http\Response::class);
        $this->csrf            = $csrf ?? $container->make(\App\Support\Csrf::class);
        $this->config          = $config ?? $container->make(\App\Support\Config::class);
        $this->currency        = $currency ?? $container->make(\App\Support\Currency::class);
        $this->db              = $db ?? $container->make(\App\Support\Database::class);
        $this->cache           = $cache ?? $container->make(\App\Support\Cache::class);
        $this->licenseClient   = $licenseClient ?? $container->make(\App\Support\LicenseClient::class);
        $this->merchantContext = $merchantContext ?? $container->make(\App\Support\MerchantContext::class);
        $this->money           = $money ?? $container->make(\App\Support\Money::class);
        $this->rateLimit       = $rateLimit ?? $container->make(\App\Support\RateLimit::class);
        $this->captcha         = $captcha ?? $container->make(\App\Support\Captcha::class);
        $this->mailer          = $mailer ?? $container->make(\App\Support\Mailer::class);
    }
}
