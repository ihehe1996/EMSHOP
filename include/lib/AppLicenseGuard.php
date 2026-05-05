<?php

declare(strict_types=1);

/**
 * 应用授权过滤器(主站专用)。
 *
 * 把"本地扫到的插件/模板列表"按中心服务的购买/注册记录裁剪:
 *   - 系统内置(SYSTEM_APPS,如 default 模板)→ 直接放行
 *   - 主站(scope=1):只走 /api/app_purchased.php
 *       服务端会把"未在 app 表里注册的应用名"也归类为"已购买"返回 —— 这是有意设计的
 *       fallback:让站长本地自定义/内部应用也能跑。主站维持这一行为。
 *
 * 调用方(应用商店重构后只剩主站):
 *   /admin/plugin.php          member_code = '', scope = 1, type = 'plugin'
 *   /admin/template.php        member_code = '', scope = 1, type = 'template'
 *
 * 商户侧(分站)在重构后不再直连服务端 —— 计划走本地 em_app_market / em_app_purchase
 * (分站购买流程暂未上线;主站为分站采购上架走 MainAppPurchaseService 已实现)。
 * 因此 scope=2 / memberCodeForMerchant 已经废弃。
 *
 * 失败策略:
 *   - 中心服务不可达 / 超时 / 报错 → 不吃异常,错误消息通过 out-param $errorOut 透出;
 *     此时除 SYSTEM_APPS 外都暂不放行,避免误把未授权的应用露出来
 *   - 调用方决定上层展示(通常是返回空列表 + 顶部告警条)
 */
final class AppLicenseGuard
{
    /**
     * 系统内置应用白名单：不送中心服务校验，所有租户永远可见。
     * 目前只有 "default" 模板 —— 是系统默认模板、非购买产品。
     */
    public const SYSTEM_APPS = ['default'];

    /**
     * appLatestVersions 单次请求最多 50 个 name，超出需分批。
     */
    private const VERSIONS_BATCH_SIZE = 50;

    

    /**
     * 调 appLatestVersions 拿"服务端注册过的应用名集合"。超过 50 个分批，结果合并。
     *
     * @param array<int,string> $names
     * @param string $type 'plugin' / 'template'
     * @return array<int,string> 注册过的 name_en 列表（未注册的不会出现）
     */
    private static function queryRegisteredNames(array $names, string $type): array
    {
        if (!in_array($type, ['plugin', 'template'], true)) {
            // 类型非法直接当全不存在，避免误放行
            return [];
        }
        $registered = [];
        foreach (array_chunk($names, self::VERSIONS_BATCH_SIZE) as $batch) {
            $map = LicenseClient::appLatestVersions($batch, $type);
            foreach ($map as $name => $_meta) {
                $registered[] = (string) $name;
            }
        }
        return $registered;
    }
}
