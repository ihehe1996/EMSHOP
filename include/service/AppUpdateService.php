<?php

declare(strict_types=1);

/**
 * 已装应用（插件 / 模板）的新版本检测。
 *
 * 后台的插件管理与模板管理页都要做同一件事：把本机磁盘上扫到的应用清单报给授权服务器，
 * 问一句"哪些有新版本"。这段流程原先在两个页面脚本里各写了一遍（连字段名转换都一样），
 * 容易漂移，因此收在这里统一实现。
 *
 * 与 LicenseClient::appUpdateCheck() 的分工：本类负责"组装清单 / 分片 / 字段适配 / 降级"，
 * 网络与信封解析留在 LicenseClient。
 */
final class AppUpdateService
{
    /** app-update-check 单次请求的 apps 上限（服务端约定 1~100 条） */
    private const BATCH_SIZE = 100;

    /**
     * 检查已装应用的新版本。
     *
     * 返回结构刻意与视图和 admin/appstore.php?_action=update 的既有契约对齐
     * （version / file_path / min_version），调用方拿到即可直接喂给模板；
     * 服务端的 package_url 在这里映射成 file_path，页面与更新 action 都不用改。
     *
     * 降级：中心不可达 / 超时 / 返回异常 → 返回空数组，调用方照常展示已装列表
     * （与改造前"授权服务器挂了也要能看插件列表"的行为一致）。
     *
     * @param array<string, array{version?:string}> $installed 以 name_en（slug = 目录名）为 key
     * @param string $type 'plugin' / 'template'
     * @return array<string, array{version:string, file_path:string, min_version:string}>
     *         只含"有更新"的条目，key 与入参一致
     */
    public static function checkInstalled(array $installed, string $type): array
    {
        if (!in_array($type, ['plugin', 'template'], true) || $installed === []) {
            return [];
        }

        // 组装上报清单：name_en 就是本地目录名；版本缺省 1.0.0，与 PluginModel/TemplateModel
        // parseHeader() 的默认值保持一致，避免"没写 Version 头"的应用被当成比 0 旧
        $apps = [];
        foreach ($installed as $slug => $info) {
            $slug = (string) $slug;
            if ($slug === '') continue;
            $apps[] = [
                'name_en' => $slug,
                'version' => (string) ($info['version'] ?? '1.0.0'),
            ];
        }
        if ($apps === []) {
            return [];
        }

        // 身份口径与 app-list 一致：未激活时 code 为空串，服务端按未授权算
        $licenseRow = LicenseService::currentLicense();
        $code = $licenseRow ? (string) ($licenseRow['license_code'] ?? '') : '';
        $domain = LicenseService::effectiveHost();

        $out = [];
        foreach (array_chunk($apps, self::BATCH_SIZE) as $batch) {
            try {
                $res = LicenseClient::appUpdateCheck($batch, $domain, $code);
            } catch (Throwable $e) {
                // 中心不可达：静默降级，别让插件/模板列表页整体报错
                continue;
            }

            // 这次检测是带着本地激活码去问的：服务端明确回未授权 → 清空本地授权
            // （口径与 license/status、应用商店列表一致）。前提是**确实带了码** ——
            // 没带码时服务端照样回 false，那不是「码失效」，不能拿它清本地
            if ($code !== '' && ($res['license']['authorized'] ?? null) === false) {
                LicenseService::clearLocalAuthorization();
            }

            foreach ($res['data'] as $row) {
                if (!is_array($row)) continue;
                $slug = (string) ($row['name_en'] ?? '');
                if ($slug === '') continue;
                // 接口没有 type 参数，按 slug 查可能返回同名的另一种类型，只认本页的类型
                if ((string) ($row['type'] ?? '') !== $type) continue;
                $filePath = (string) ($row['package_url'] ?? '');
                // 未授权 / 授权与域名不匹配时，服务端只回"有新版本"但不给下载地址
                // （package_url 为空串）。这种条目不能当成"可更新"：页面会画出更新按钮，
                // 点下去却因为 file_path 为空被 admin/appstore.php 的 update 分支拒绝
                // （"EM官方未提供下载地址"）。宁可这条不显示更新，也不给一个必然失败的按钮。
                if ($filePath === '') continue;

                $out[$slug] = [
                    'version'     => (string) ($row['version'] ?? ''),
                    'file_path'   => $filePath,
                    'min_version' => (string) ($row['min_version'] ?? ''),
                ];
            }
        }

        return $out;
    }
}
