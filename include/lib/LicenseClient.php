<?php

declare(strict_types=1);

/**
 * 授权服务器客户端（兼容门面）。
 *
 * 实际实现已迁到 App\Support\LicenseClient 实例服务；这里保留原有静态 API 和
 * 线路常量，业务层与安装程序的调用点都不用改。
 */
final class LicenseClient
{
    public const OFFICIAL_BASE_URL = \App\Support\LicenseClient::OFFICIAL_BASE_URL;
    public const DEV_BASE_URL = \App\Support\LicenseClient::DEV_BASE_URL;

    /** @var \App\Support\LicenseClient|null */
    private static $service;

    /**
     * 获取内部授权客户端服务实例。
     */
    public static function instance(): \App\Support\LicenseClient
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\LicenseClient::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/LicenseClient.php';
            }
            self::$service = new \App\Support\LicenseClient();
        }

        return self::$service;
    }

    /**
     * 转发到 App\Support\LicenseClient::bind()。
     */
    public static function bind(string $code, string $domain): array
    {
        return self::instance()->bind($code, $domain);
    }

    /**
     * 转发到 App\Support\LicenseClient::getBuyUrl()。
     */
    public static function getBuyUrl(string $level, string $domain, string $adminEmail = '', string $returnUrl = ''): string
    {
        return self::instance()->getBuyUrl($level, $domain, $adminEmail, $returnUrl);
    }

    /**
     * 转发到 App\Support\LicenseClient::status()。
     */
    public static function status(string $domain, string $code = ''): array
    {
        return self::instance()->status($domain, $code);
    }

    /**
     * 转发到 App\Support\LicenseClient::unbind()。
     */
    public static function unbind(string $code, string $domain): array
    {
        return self::instance()->unbind($code, $domain);
    }

    /**
     * 转发到 App\Support\LicenseClient::reportUpdateSuccess()。
     */
    public static function reportUpdateSuccess(string $version): bool
    {
        return self::instance()->reportUpdateSuccess($version);
    }

    /**
     * 转发到 App\Support\LicenseClient::baseData()。
     */
    public static function baseData(string $identity, string $domain, string $version, string $code = ''): array
    {
        return self::instance()->baseData($identity, $domain, $version, $code);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountLogin()。
     */
    public static function accountLogin(string $account, string $password): array
    {
        return self::instance()->accountLogin($account, $password);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountEmailCode()。
     */
    public static function accountEmailCode(string $email, string $scene = 'register'): array
    {
        return self::instance()->accountEmailCode($email, $scene);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountLoginByCode()。
     */
    public static function accountLoginByCode(string $email, string $code): array
    {
        return self::instance()->accountLoginByCode($email, $code);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountRegister()。
     */
    public static function accountRegister(array $payload): array
    {
        return self::instance()->accountRegister($payload);
    }

    /**
     * 转发到 App\Support\LicenseClient::myLicenses()。
     */
    public static function myLicenses(string $token): array
    {
        return self::instance()->myLicenses($token);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountProfile()。
     */
    public static function accountProfile(string $token): array
    {
        return self::instance()->accountProfile($token);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountOverview()。
     */
    public static function accountOverview(string $token): array
    {
        return self::instance()->accountOverview($token);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountBalanceLogs()。
     */
    public static function accountBalanceLogs(string $token, int $page = 1, int $perPage = 10): array
    {
        return self::instance()->accountBalanceLogs($token, $page, $perPage);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountBindLicense()。
     */
    public static function accountBindLicense(string $token, string $code): array
    {
        return self::instance()->accountBindLicense($token, $code);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountRemoveLicense()。
     */
    public static function accountRemoveLicense(string $token, string $code): array
    {
        return self::instance()->accountRemoveLicense($token, $code);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountResetLicense()。
     */
    public static function accountResetLicense(string $token, string $code): array
    {
        return self::instance()->accountResetLicense($token, $code);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountConfig()。
     */
    public static function accountConfig(string $token): array
    {
        return self::instance()->accountConfig($token);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountConfigUpdateWithPassword()。
     */
    public static function accountConfigUpdateWithPassword(string $token, array $payload): array
    {
        return self::instance()->accountConfigUpdateWithPassword($token, $payload);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountEmCodes()。
     */
    public static function accountEmCodes(string $token, int $page = 1, int $perPage = 10, string $usage = 'unused'): array
    {
        return self::instance()->accountEmCodes($token, $page, $perPage, $usage);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountGenerateEmCodes()。
     */
    public static function accountGenerateEmCodes(string $token, string $type, int $count): array
    {
        return self::instance()->accountGenerateEmCodes($token, $type, $count);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountVersions()。
     */
    public static function accountVersions(string $token, int $page = 1, int $perPage = 10): array
    {
        return self::instance()->accountVersions($token, $page, $perPage);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountVersionSource()。
     */
    public static function accountVersionSource(string $token, int $id): array
    {
        return self::instance()->accountVersionSource($token, $id);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountOrders()。
     */
    public static function accountOrders(string $token, array $params): array
    {
        return self::instance()->accountOrders($token, $params);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountAdSlots()。
     */
    public static function accountAdSlots(string $token, array $params): array
    {
        return self::instance()->accountAdSlots($token, $params);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountAdSlotDetail()。
     */
    public static function accountAdSlotDetail(string $token, int $id): array
    {
        return self::instance()->accountAdSlotDetail($token, $id);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountAdSlotCreate()。
     */
    public static function accountAdSlotCreate(string $token, array $payload): array
    {
        return self::instance()->accountAdSlotCreate($token, $payload);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountAdSlotUpdate()。
     */
    public static function accountAdSlotUpdate(string $token, int $id, array $payload): array
    {
        return self::instance()->accountAdSlotUpdate($token, $id, $payload);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountAdSlotDelete()。
     */
    public static function accountAdSlotDelete(string $token, int $id): array
    {
        return self::instance()->accountAdSlotDelete($token, $id);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountAdSlotMove()。
     */
    public static function accountAdSlotMove(string $token, int $id, string $direction): array
    {
        return self::instance()->accountAdSlotMove($token, $id, $direction);
    }

    /**
     * 转发到 App\Support\LicenseClient::accountInstallRecords()。
     */
    public static function accountInstallRecords(string $token, array $params): array
    {
        return self::instance()->accountInstallRecords($token, $params);
    }

    /**
     * 转发到 App\Support\LicenseClient::appList()。
     */
    public static function appList(array $params, string $token = ''): array
    {
        return self::instance()->appList($params, $token);
    }

    /**
     * 转发到 App\Support\LicenseClient::appPurchasedList()。
     */
    public static function appPurchasedList(array $params, string $token = ''): array
    {
        return self::instance()->appPurchasedList($params, $token);
    }

    /**
     * 转发到 App\Support\LicenseClient::appDetail()。
     */
    public static function appDetail(int $appId, string $emkey, string $host, int $scope, string $memberCode = '', string $token = ''): array
    {
        return self::instance()->appDetail($appId, $emkey, $host, $scope, $memberCode, $token);
    }

    /**
     * 转发到 App\Support\LicenseClient::appUpdateCheck()。
     */
    public static function appUpdateCheck(array $apps, string $domain, string $code): array
    {
        return self::instance()->appUpdateCheck($apps, $domain, $code);
    }

    /**
     * 转发到 App\Support\LicenseClient::createOrder()。
     */
    public static function createOrder(string $code, string $domain, int $appId): array
    {
        return self::instance()->createOrder($code, $domain, $appId);
    }

    /**
     * 转发到 App\Support\LicenseClient::appOrderQrcode()。
     */
    public static function appOrderQrcode(string $orderNo, int $channelId = 0): array
    {
        return self::instance()->appOrderQrcode($orderNo, $channelId);
    }

    /**
     * 转发到 App\Support\LicenseClient::appOrderStatus()。
     */
    public static function appOrderStatus(string $orderNo): array
    {
        return self::instance()->appOrderStatus($orderNo);
    }

    /**
     * 转发到 App\Support\LicenseClient::currentBaseUrl()。
     */
    public static function currentBaseUrl(): string
    {
        return self::instance()->currentBaseUrl();
    }

    /**
     * 转发到 App\Support\LicenseClient::tlsOptions()。
     */
    public static function tlsOptions(): array
    {
        return self::instance()->tlsOptions();
    }

    /**
     * 转发到 App\Support\LicenseClient::officialLines()。
     */
    public static function officialLines(): array
    {
        return self::instance()->officialLines();
    }

    /**
     * 转发到 App\Support\LicenseClient::devLines()。
     */
    public static function devLines(): array
    {
        return self::instance()->devLines();
    }

    /**
     * 转发到 App\Support\LicenseClient::lines()。
     */
    public static function lines(): array
    {
        return self::instance()->lines();
    }

    /**
     * 转发到 App\Support\LicenseClient::measureLatency()。
     */
    public static function measureLatency(): array
    {
        return self::instance()->measureLatency();
    }

    /**
     * 转发到 App\Support\LicenseClient::currentLineIndex()。
     */
    public static function currentLineIndex(): int
    {
        return self::instance()->currentLineIndex();
    }

    /**
     * 转发到 App\Support\LicenseClient::defaultBaseUrl()。
     */
    public static function defaultBaseUrl(): string
    {
        return self::instance()->defaultBaseUrl();
    }

    /**
     * 转发到 App\Support\LicenseClient::mainAppList()。
     */
    public static function mainAppList(array $params, string $token = ''): array
    {
        return self::instance()->mainAppList($params, $token);
    }

    /**
     * 转发到 App\Support\LicenseClient::merchantAppList()。
     */
    public static function merchantAppList(array $params, string $token = ''): array
    {
        return self::instance()->merchantAppList($params, $token);
    }

    /**
     * 转发到 App\Support\LicenseClient::mainAppPurchasedList()。
     */
    public static function mainAppPurchasedList(array $params, string $token = ''): array
    {
        return self::instance()->mainAppPurchasedList($params, $token);
    }

    /**
     * 转发到 App\Support\LicenseClient::merchantAppPurchasedList()。
     */
    public static function merchantAppPurchasedList(array $params, string $token = ''): array
    {
        return self::instance()->merchantAppPurchasedList($params, $token);
    }

    /**
     * 转发到 App\Support\LicenseClient::mainAppDetail()。
     */
    public static function mainAppDetail(int $appId, string $emkey, string $host, string $token = ''): array
    {
        return self::instance()->mainAppDetail($appId, $emkey, $host, $token);
    }

    /**
     * 转发到 App\Support\LicenseClient::merchantAppDetail()。
     */
    public static function merchantAppDetail(int $appId, string $emkey, string $host, string $token = ''): array
    {
        return self::instance()->merchantAppDetail($appId, $emkey, $host, $token);
    }

    /**
     * 转发到 App\Support\LicenseClient::mainAppCreateOrder()。
     */
    public static function mainAppCreateOrder(string $code, string $domain, int $appId): array
    {
        return self::instance()->mainAppCreateOrder($code, $domain, $appId);
    }

    /**
     * 转发到 App\Support\LicenseClient::merchantAppCreateOrder()。
     */
    public static function merchantAppCreateOrder(string $code, string $domain, int $appId): array
    {
        return self::instance()->merchantAppCreateOrder($code, $domain, $appId);
    }

}
