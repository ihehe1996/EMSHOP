<?php

declare(strict_types=1);

namespace App\Core;

/**
 * 核心服务注册（服务提供者）。
 *
 * 按框架惯例，容器只登记「怎么拿到服务」，第一次 make 时才真正创建（懒加载单例）；
 * 用不到的请求不会白白构造这些对象。
 *
 * 这里的服务由全局门面持有单例（模板 / 插件仍在直接调门面），
 * 容器登记的是同一个实例的取法，所以注入进控制器的对象和门面拿到的是同一份。
 */
final class ServiceProvider
{
    /**
     * 容器服务类 => [门面类, 取单例的静态方法名]。
     *
     * 新增服务时在这里加一行，不要再到 init.php 里逐个 instance() 注册。
     */
    private const BINDINGS = [
        \App\Support\Database::class          => [\Database::class, 'instance'],
        \App\Support\Config::class            => [\Config::class, 'instance'],
        \App\Support\Hooks::class             => [\Hooks::class, 'instance'],
        \App\Support\Cache::class             => [\Cache::class, 'instance'],
        \App\Support\Input::class             => [\Input::class, 'instance'],
        \App\Http\Request::class              => [\Request::class, 'instance'],
        \App\Http\Response::class             => [\Response::class, 'instance'],
        \App\Http\Output::class               => [\Output::class, 'instance'],
        \App\Http\Dispatcher::class           => [\Dispatcher::class, 'service'],
        \App\Support\GuestToken::class        => [\GuestToken::class, 'instance'],
        \App\Support\Csrf::class              => [\Csrf::class, 'instance'],
        \App\Support\CsrfSecret::class        => [\CsrfSecret::class, 'instance'],
        \App\Support\Currency::class          => [\Currency::class, 'service'],
        \App\Support\View::class              => [\View::class, 'service'],
        \App\Support\GoodsTypeManager::class  => [\GoodsTypeManager::class, 'instance'],
        \App\Support\RateLimit::class         => [\RateLimit::class, 'instance'],
        \App\Support\InviteToken::class       => [\InviteToken::class, 'instance'],
        \App\Support\WorkerHeartbeat::class   => [\WorkerHeartbeat::class, 'instance'],
        \App\Support\MerchantContext::class   => [\MerchantContext::class, 'instance'],
        \App\Support\MerchantDomainGuard::class => [\MerchantDomainGuard::class, 'instance'],
        \App\Support\Mailer::class            => [\Mailer::class, 'instance'],
        \App\Support\Money::class             => [\Money::class, 'instance'],
        \App\Support\Emmsg::class             => [\Emmsg::class, 'instance'],
        \App\Support\DisplayName::class       => [\DisplayName::class, 'instance'],
        \App\Support\LogSanitizer::class      => [\LogSanitizer::class, 'instance'],
        \App\Support\Captcha::class           => [\Captcha::class, 'instance'],
        \App\Support\DownloadUrlGuard::class  => [\DownloadUrlGuard::class, 'instance'],
        \App\Support\HtmlSanitizer::class     => [\HtmlSanitizer::class, 'instance'],
        \App\Support\Option::class            => [\Option::class, 'instance'],
        \App\Support\LicenseClient::class     => [\LicenseClient::class, 'instance'],
        \App\Admin\AdminRouter::class         => [\App\Admin\AdminRouter::class, 'instance'],
    ];

    /**
     * 注册全部核心服务（懒加载，不触发实例化）。
     */
    public static function register(Container $container): void
    {
        foreach (self::BINDINGS as $id => [$facade, $method]) {
            $container->singleton($id, static function () use ($facade, $method) {
                return $facade::$method();
            });
        }
    }
}
