<?php

declare(strict_types=1);

require_once __DIR__ . '/global.php';

/**
 * 商户后台 · 插件管理(瘦身后版本)。
 *
 * 业务边界(详见 .claude/docs/应用商店重构方案.md):
 *   - 不再扫描磁盘 / 不再调 LicenseClient / 不再调 AppLicenseGuard
 *   - 不能 install / uninstall / upgrade / delete(这些动作由主站后台统一负责)
 *   - 只能 enable / disable / save_config 自己已购的插件
 *
 * 列表来源:
 *   - 自身已购:em_app_purchase where merchant_id = {id} AND type = 'plugin'
 *   - 自身启用:em_merchant.enabled_plugins(逗号分隔 slug)
 *   - 系统统管插件(支付/商品类型/共享店铺)在商户端列表中隐藏
 *
 * 弹窗模式(?_popup=1)沿用:渲染 plugin 自带的 *_setting.php 视图,save_config 仍走本控制器。
 */
merchantRequireLogin();

$csrfToken    = Csrf::token();
$merchantId   = (int) ($currentMerchant['id'] ?? 0);
$scope        = 'merchant_' . $merchantId;
$pluginModel  = new PluginModel();

// ============================================================
// POST 处理:enable / disable / save_config
// ============================================================
if (Request::isPost()) {
    try {
        if (!Csrf::validate((string) Input::post('csrf_token', ''))) {
            Response::error('请求已失效,请刷新页面后重试');
        }

        $action = (string) Input::post('_action', '');
        $name   = trim((string) Input::post('name', ''));
        if ($name === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
            Response::error('非法插件名');
        }

        $owned = $pluginModel->isInstalled($name, $scope);
        $selfEnabled = $pluginModel->isEnabled($name, $scope);
        $header = $pluginModel->parseHeader(EM_ROOT . '/content/plugin/' . $name . '/' . $name . '.php');
        $isSystemPlugin = $header !== null
            && in_array((string) ($header['category'] ?? ''), array_values(PluginModel::SYSTEM_PLUGINS), true);

        // 兼容部分插件 setting.php 历史硬编码 name 错误:
        // save_config 时优先回退到弹窗 URL 的 ?name=xxx 再校验权限
        if ($action === 'save_config' && !$owned && !$selfEnabled) {
            $fallbackName = '';
            $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
            if ($ref !== '') {
                $query = parse_url($ref, PHP_URL_QUERY);
                if (is_string($query) && $query !== '') {
                    parse_str($query, $q);
                    $candidate = trim((string) ($q['name'] ?? ''));
                    if ($candidate !== '' && preg_match('/^[a-zA-Z0-9_\-]+$/', $candidate)) {
                        $fallbackName = $candidate;
                    }
                }
            }
            if ($fallbackName !== '' && $fallbackName !== $name) {
                $name = $fallbackName;
                $owned = $pluginModel->isInstalled($name, $scope);
                $selfEnabled = $pluginModel->isEnabled($name, $scope);
                $header = $pluginModel->parseHeader(EM_ROOT . '/content/plugin/' . $name . '/' . $name . '.php');
                $isSystemPlugin = $header !== null
                    && in_array((string) ($header['category'] ?? ''), array_values(PluginModel::SYSTEM_PLUGINS), true);
            }
        }

        if ($isSystemPlugin) {
            Response::error('该插件由主站统一管理,商户端不可操作');
        }

        if (!$owned && !$selfEnabled) {
            Response::error('该插件未在你的店铺中购买/启用,无法操作');
        }

        switch ($action) {
            case 'enable': {
                if (!$owned) Response::error('该插件未在你的店铺中购买,无法启用');
                if ($pluginModel->isEnabled($name, $scope)) Response::error('该插件已经是启用状态');
                $pluginModel->enable($name, $scope);
                Response::success('插件已启用', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            case 'disable': {
                if (!$pluginModel->isEnabled($name, $scope)) Response::error('该插件已经是禁用状态');
                $pluginModel->disable($name, $scope);
                Response::success('插件已禁用', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            case 'save_config': {
                // 优先调插件自带的 plugin_setting()(它内部用 Storage 写并自行 Response)
                $settingFile = EM_ROOT . '/content/plugin/' . $name . '/' . $name . '_setting.php';
                if (is_file($settingFile)) {
                    include_once $settingFile;
                    if (function_exists('plugin_setting')) {
                        call_user_func('plugin_setting');
                        return;
                    }
                }
                // 通用兜底:全部 POST 字段(除框架字段)逐个写 Storage
                // Storage 实例的 scope 自动从 $GLOBALS['__em_current_scope'](merchant_X)读
                $storage = Storage::getInstance($name);
                foreach (Input::allPost() as $k => $v) {
                    if (in_array($k, ['_action', 'csrf_token', 'name'], true)) continue;
                    $storage->setValue($k, $v);
                }
                Response::success('配置已保存', ['csrf_token' => Csrf::refresh()]);
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
// 弹窗模式:渲染插件设置页(沿用)
// ============================================================
if (Input::get('_popup', '') === '1') {
    $name = trim((string) Input::get('name', ''));
    if ($name === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
        exit('非法插件名');
    }
    $popupOwned = $pluginModel->isInstalled($name, $scope);
    $popupSelfEnabled = $pluginModel->isEnabled($name, $scope);
    $popupHeader = $pluginModel->parseHeader(EM_ROOT . '/content/plugin/' . $name . '/' . $name . '.php');
    $popupSystem = $popupHeader !== null
        && in_array((string) ($popupHeader['category'] ?? ''), array_values(PluginModel::SYSTEM_PLUGINS), true);
    if ($popupSystem) {
        exit('该插件由主站统一管理,商户端不可配置');
    }
    if (!$popupOwned && !$popupSelfEnabled) {
        exit('该插件未在你的店铺中购买/启用');
    }
    $settingFile = EM_ROOT . '/content/plugin/' . $name . '/' . $name . '_setting.php';
    if (!is_file($settingFile)) {
        exit('该插件暂无设置页面');
    }

    $csrfToken = Csrf::token();
    // 标题走磁盘 parseHeader,不再依赖 DB 行
    $header    = $pluginModel->parseHeader(EM_ROOT . '/content/plugin/' . $name . '/' . $name . '.php');
    $pageTitle = '插件设置:' . htmlspecialchars((string) ($header['title'] ?? $name), ENT_QUOTES, 'UTF-8');
    // 让插件 setting.php 里的 AJAX 把 save_config 提交到商户路径,而不是默认的 /admin/plugin.php
    $popupPluginSaveUrl = '/user/merchant/plugin.php';
    include __DIR__ . '/../../admin/view/popup/header.php';

    include_once $settingFile;
    if (function_exists('plugin_setting_view')) {
        call_user_func('plugin_setting_view');
    } else {
        echo '<div style="padding:16px;color:#6b7280;">该插件未提供可渲染的设置视图。</div>';
    }

    include __DIR__ . '/../../admin/view/popup/footer.php';
    return;
}

// ============================================================
// 普通页面:列表 = 自身已购(em_app_purchase)
//
// 元数据全部来自 parseHeader(磁盘),DB 只查 is_enabled / 已购
// ============================================================
$disk          = $pluginModel->scanPlugins();                        // 磁盘所有插件 + parseHeader 元数据
$purchaseModel = new AppPurchaseModel();
$ownedCodes    = $purchaseModel->purchasedCodes($merchantId, 'plugin'); // 商户已购 app_code 列表
$ownedSet      = array_flip($ownedCodes);

// 商户自身 scope 的启用名单(从 em_merchant.enabled_plugins 解析得到)
$selfEnabledSet = array_flip($pluginModel->getEnabledNames($scope));

$plugins = [];
foreach ($disk as $name => $info) {
    if (in_array((string) ($info['category'] ?? ''), array_values(PluginModel::SYSTEM_PLUGINS), true)) {
        continue;
    }
    $hasOwned = isset($ownedSet[$name]);
    $hasEnabled = isset($selfEnabledSet[$name]);
    if (!$hasOwned && !$hasEnabled) continue;

    $row = $info;
    $row['is_enabled']   = $hasEnabled;
    $row['has_setting']  = (string) ($info['setting_file'] ?? '') !== '';
    $plugins[] = $row;
}

merchantRenderPage(__DIR__ . '/view/plugin.php', [
    'plugins'   => $plugins,
    'csrfToken' => Csrf::token(),
]);
