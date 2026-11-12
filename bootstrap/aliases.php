<?php

declare(strict_types=1);

/**
 * 全局短名别名（Laravel 的 AliasLoader 对应物）。
 *
 * 模板 / 插件 / 旧代码仍写 `Cache::get()`、`Config::get()`；实现放在
 * app/Facade/*（命名空间 App\Facade），这里按需把短名注册成类别名。
 * 只有真正用到某个短名时才加载对应门面类，不增加启动开销。
 */

/**
 * 注册门面别名。
 */
function em_register_facade_aliases(): void
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    $aliases = [
        'Cache'               => \App\Facade\Cache::class,
        'Captcha'             => \App\Facade\Captcha::class,
        'Config'              => \App\Facade\Config::class,
        'Csrf'                => \App\Facade\Csrf::class,
        'CsrfSecret'          => \App\Facade\CsrfSecret::class,
        'Currency'            => \App\Facade\Currency::class,
        'Database'            => \App\Facade\Database::class,
        'Dispatcher'          => \App\Facade\Dispatcher::class,
        'DisplayName'         => \App\Facade\DisplayName::class,
        'DownloadUrlGuard'    => \App\Facade\DownloadUrlGuard::class,
        'Emmsg'               => \App\Facade\Emmsg::class,
        'GoodsTypeManager'    => \App\Facade\GoodsTypeManager::class,
        'GuestToken'          => \App\Facade\GuestToken::class,
        'Hooks'               => \App\Facade\Hooks::class,
        'HtmlSanitizer'       => \App\Facade\HtmlSanitizer::class,
        'Input'               => \App\Facade\Input::class,
        'InviteToken'         => \App\Facade\InviteToken::class,
        'LicenseClient'       => \App\Facade\LicenseClient::class,
        'LogSanitizer'        => \App\Facade\LogSanitizer::class,
        'Mailer'              => \App\Facade\Mailer::class,
        'MerchantContext'     => \App\Facade\MerchantContext::class,
        'MerchantDomainGuard' => \App\Facade\MerchantDomainGuard::class,
        'Money'               => \App\Facade\Money::class,
        'Option'              => \App\Facade\Option::class,
        'Output'              => \App\Facade\Output::class,
        'RateLimit'           => \App\Facade\RateLimit::class,
        'Request'             => \App\Facade\Request::class,
        'Response'            => \App\Facade\Response::class,
        'View'                => \App\Facade\View::class,
        'WorkerHeartbeat'     => \App\Facade\WorkerHeartbeat::class,

        // 不是门面、但仍是全局类名的基础设施
        'Storage'             => \App\Support\Storage::class,
        'TemplateStorage'     => \App\Support\TemplateStorage::class,
        'Smtp'                => \App\Vendor\Smtp::class,
        'PasswordHash'        => \App\Vendor\PasswordHash::class,
        'OrderStatusConflictException' => \App\Exception\OrderStatusConflictException::class,
        'PermanentDeliveryException'   => \App\Exception\PermanentDeliveryException::class,
        'StockShortageException'       => \App\Exception\StockShortageException::class,
    ];

    spl_autoload_register(static function (string $class) use ($aliases): void {
        if (isset($aliases[$class])) {
            class_alias($aliases[$class], $class);
        }
    });
}
