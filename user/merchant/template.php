<?php

declare(strict_types=1);

require_once __DIR__ . '/global.php';

/**
 * 商户后台 · 模板管理(瘦身后版本)。
 *
 * 业务边界(详见 .claude/docs/应用商店重构方案.md):
 *   - 不再扫磁盘 / 不再调 LicenseClient / 不再调 AppLicenseGuard
 *   - 模板要么是「系统白名单」(default/babaili/zishen/test 等核心模板),
 *     要么是「分站从应用商店购买」的(em_app_purchase 中有记录)
 *   - 启用 = 写 em_merchant.active_template_pc / active_template_mobile;无 install 概念
 *   - callback_init 由模板作者保证幂等(对齐插件方向 3),核心不再调
 *
 * 操作能力:
 *   - 启用 / 取消启用 PC 端、手机端
 *   - 模板设置(若模板带 setting.php)
 *   - 商户购买入口暂未上线,付费模板由主站统一分发(走 em_app_purchase)
 */
merchantRequireLogin();

$csrfToken     = Csrf::token();
$merchantId    = (int) ($currentMerchant['id'] ?? 0);
$scope         = 'merchant_' . $merchantId;

$templateModel = new TemplateModel();
$purchaseModel = new AppPurchaseModel();

// 系统模板白名单 —— 这些模板对所有商户免费可用,首次启用时自动 install 到本 scope
// 不在白名单的模板,商户必须从插件市场购买后才能用
const MERCHANT_SYSTEM_TEMPLATES = ['default', 'babaili', 'zishen', 'test'];

// ============================================================
// POST 处理
// ============================================================
if (Request::isPost()) {
    try {
        if (!Csrf::validate((string) Input::post('csrf_token', ''))) {
            Response::error('请求已失效,请刷新页面后重试');
        }

        $action = (string) Input::post('_action', '');
        $name   = trim((string) Input::post('name', ''));
        if ($name === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $name)) {
            Response::error('模板名不合法');
        }

        // 商户能操作的范围 = 系统白名单 ∪ 自身已购
        $isSystem    = in_array($name, MERCHANT_SYSTEM_TEMPLATES, true);
        $isPurchased = $purchaseModel->isPurchased($merchantId, $name, 'template');
        if (!$isSystem && !$isPurchased) {
            Response::error('该模板未购买,请先到插件市场购买');
        }

        switch ($action) {
            case 'activate_pc':
            case 'activate_mobile': {
                $client = $action === 'activate_pc' ? 'pc' : 'mobile';

                if (!$templateModel->existsOnDisk($name)) {
                    Response::error('模板目录不存在或缺失 header.php');
                }

                // 再点已启用 → 取消(行为与主站一致)
                if ($templateModel->isActive($name, $client, $scope)) {
                    $templateModel->clearActiveTheme($client, $scope);
                    Response::success($client === 'pc' ? '已取消 PC 端模板' : '已取消手机端模板',
                        ['csrf_token' => Csrf::refresh()]);
                }
                $templateModel->setActiveTheme($client, $name, $scope);
                Response::success($client === 'pc' ? 'PC 端模板已切换' : '手机端模板已切换',
                    ['csrf_token' => Csrf::refresh()]);
                break;
            }

            case 'save_config': {
                // 配置统一走 TemplateStorage:由模板 setting.php 自带的 template_setting() / plugin_setting() 写入
                $settingFile = $templateModel->getSettingFilePath($name);
                if (is_file($settingFile)) {
                    include_once $settingFile;
                    if (function_exists('template_setting')) {
                        call_user_func('template_setting');
                        return;
                    }
                    if (function_exists('plugin_setting')) {
                        call_user_func('plugin_setting');
                        return;
                    }
                }
                Response::error('该模板未提供配置保存逻辑');
                break;
            }

            default:
                Response::error('未知操作');
        }
    } catch (RuntimeException $e) {
        Response::error($e->getMessage());
    } catch (Throwable $e) {
        Response::error('系统繁忙,请稍后再试');
    }
}

// ============================================================
// 弹窗模式:模板设置页(沿用)
// ============================================================
if (Input::get('_popup', '') === '1') {
    $name = trim((string) Input::get('name', ''));
    if ($name === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $name)) {
        exit('模板名不合法');
    }
    if (!$templateModel->existsOnDisk($name)) exit('模板目录不存在');
    $settingFile = $templateModel->getSettingFilePath($name);
    if (!is_file($settingFile)) exit('该模板暂无设置页面');

    // 标题走磁盘 parseHeader,不再依赖 DB 行
    $header    = $templateModel->parseHeader(EM_ROOT . '/content/template/' . $name . '/header.php');
    $csrfToken = Csrf::token();
    $pageTitle = '模板设置:' . htmlspecialchars((string) ($header['title'] ?? $name), ENT_QUOTES, 'UTF-8');
    // 让模板 setting.php 里的 AJAX 把 save_config 提交到商户路径(对齐重构后的新 URL)
    $popupTemplateSaveUrl = '/user/merchant/template.php';
    include __DIR__ . '/../../admin/view/popup/header.php';

    include_once $settingFile;
    if (function_exists('template_setting_view')) {
        call_user_func('template_setting_view');
    } elseif (function_exists('plugin_setting_view')) {
        call_user_func('plugin_setting_view');
    } else {
        echo '<div style="padding:16px;color:#6b7280;">该模板未提供可渲染的设置视图。</div>';
    }
    include __DIR__ . '/../../admin/view/popup/footer.php';
    return;
}

// ============================================================
// 普通页面:列表 = 系统白名单 + 已购模板
// ============================================================
try {
    $activeThemePc     = (string) ($templateModel->getActiveTheme('pc',     $scope) ?? '');
    $activeThemeMobile = (string) ($templateModel->getActiveTheme('mobile', $scope) ?? '');
} catch (Throwable $e) {
    $activeThemePc = '';
    $activeThemeMobile = '';
}

$scanned = $templateModel->scanTemplates();           // 磁盘元数据(给系统白名单 + 已购合并用)

// 1. 系统白名单(磁盘存在的才纳入;对所有商户免费可用)
$availableTemplates = [];
foreach (MERCHANT_SYSTEM_TEMPLATES as $sysName) {
    if (!isset($scanned[$sysName])) continue;
    $info = $scanned[$sysName];
    $info['name']             = $sysName;
    $info['is_system']        = true;
    $info['is_purchased']     = false;
    $info['is_installed']     = true;  // 列表里能看到的都是可用的(系统白名单 ∪ 已购)
    $info['is_active_pc']     = $activeThemePc     === $sysName;
    $info['is_active_mobile'] = $activeThemeMobile === $sysName;
    $info['has_setting']      = $templateModel->hasSettingFile($sysName);
    $availableTemplates[$sysName] = $info;
}

// 2. 已购模板(从 em_app_purchase 取,合并磁盘元数据)
foreach ($purchaseModel->purchasedCodes($merchantId, 'template') as $code) {
    if (isset($availableTemplates[$code])) continue;       // 系统白名单去重(理论不可能但防御)
    if (!isset($scanned[$code])) continue;                 // 磁盘文件丢失 → 不展示(避免 500)
    $info = $scanned[$code];
    $info['name']             = $code;
    $info['is_system']        = false;
    $info['is_purchased']     = true;
    $info['is_installed']     = true;
    $info['is_active_pc']     = $activeThemePc     === $code;
    $info['is_active_mobile'] = $activeThemeMobile === $code;
    $info['has_setting']      = $templateModel->hasSettingFile($code);
    $availableTemplates[$code] = $info;
}

merchantRenderPage(__DIR__ . '/view/template.php', [
    'availableTemplates' => $availableTemplates,
    'activeThemePc'      => $activeThemePc,
    'activeThemeMobile'  => $activeThemeMobile,
    'csrfToken'          => Csrf::token(),
]);
