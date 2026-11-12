<?php

declare(strict_types=1);

namespace App\Merchant\Controller;

/**
 * 商户后台 - template（/user/merchant/template）。
 */
final class TemplateController extends MerchantBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        $currentMerchant = $this->merchant();
        $merchantLevel = $this->level();
        $csrfToken     = $this->csrf->token();
        $merchantId    = (int) ($currentMerchant['id'] ?? 0);
        $scope         = 'merchant_' . $merchantId;

        $templateModel = new \App\Model\TemplateModel();
        $purchaseModel = new \App\Model\AppPurchaseModel();

        // 系统模板白名单 —— 这些模板对所有商户免费可用,首次启用时自动 install 到本 scope
        // 不在白名单的模板,商户必须从插件市场购买后才能用
        $merchantSystemTemplates = ['default', 'babaili', 'zishen', 'test'];

        // ============================================================
        // POST 处理
        // ============================================================
        if ($this->request->isPost()) {
            try {
                if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
                    $this->response->error('请求已失效,请刷新页面后重试');
                }

                $action = (string) $this->input->post('_action', '');
                $name   = trim((string) $this->input->post('name', ''));
                if ($name === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $name)) {
                    $this->response->error('模板名不合法');
                }

                // 商户能操作的范围 = 系统白名单 ∪ 自身已购
                $isSystem    = in_array($name, $merchantSystemTemplates, true);
                $isPurchased = $purchaseModel->isPurchased($merchantId, $name, 'template');
                if (!$isSystem && !$isPurchased) {
                    $this->response->error('该模板未购买,请先到插件市场购买');
                }

                switch ($action) {
                    case 'activate_pc':
                    case 'activate_mobile': {
                        $client = $action === 'activate_pc' ? 'pc' : 'mobile';

                        if (!$templateModel->existsOnDisk($name)) {
                            $this->response->error('模板目录不存在或缺失 header.php');
                        }

                        // 再点已启用 → 取消(行为与主站一致)
                        if ($templateModel->isActive($name, $client, $scope)) {
                            $templateModel->clearActiveTheme($client, $scope);
                            $this->response->success($client === 'pc' ? '已取消 PC 端模板' : '已取消手机端模板',
                                ['csrf_token' => $this->csrf->refresh()]);
                        }
                        $templateModel->setActiveTheme($client, $name, $scope);
                        $this->response->success($client === 'pc' ? 'PC 端模板已切换' : '手机端模板已切换',
                            ['csrf_token' => $this->csrf->refresh()]);
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
                        $this->response->error('该模板未提供配置保存逻辑');
                        break;
                    }

                    default:
                        $this->response->error('未知操作');
                }
            } catch (RuntimeException $e) {
                $this->response->error($e->getMessage());
            } catch (\Throwable $e) {
                $this->response->error('系统繁忙,请稍后再试');
            }
        }

        // ============================================================
        // 弹窗模式:模板设置页(沿用)
        // ============================================================
        if ($this->input->get('_popup', '') === '1') {
            $name = trim((string) $this->input->get('name', ''));
            if ($name === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $name)) {
                exit('模板名不合法');
            }
            if (!$templateModel->existsOnDisk($name)) exit('模板目录不存在');
            $settingFile = $templateModel->getSettingFilePath($name);
            if (!is_file($settingFile)) exit('该模板暂无设置页面');

            // 标题走磁盘 parseHeader,不再依赖 DB 行
            $header    = $templateModel->parseHeader(EM_ROOT . '/content/template/' . $name . '/header.php');
            $csrfToken = $this->csrf->token();
            $pageTitle = '模板设置:' . htmlspecialchars((string) ($header['title'] ?? $name), ENT_QUOTES, 'UTF-8');
            // 让模板 setting.php 里的 AJAX 把 save_config 提交到商户路径(对齐重构后的新 URL)
            $popupTemplateSaveUrl = '/user/merchant/template';
            $popupTemplateMediaUrl = '/user/merchant/media';
            $popupTemplateUploadUrl = '/user/merchant/upload';
            include dirname(__DIR__, 2) . '/Admin/View/popup/header.php';

            include_once $settingFile;
            if (function_exists('template_setting_view')) {
                call_user_func('template_setting_view');
            } elseif (function_exists('plugin_setting_view')) {
                call_user_func('plugin_setting_view');
            } else {
                echo '<div style="padding:16px;color:#6b7280;">该模板未提供可渲染的设置视图。</div>';
            }
            include dirname(__DIR__, 2) . '/Admin/View/popup/footer.php';
            return;
        }

        // ============================================================
        // 普通页面:列表 = 系统白名单 + 已购模板
        // ============================================================
        try {
            $activeThemePc     = (string) ($templateModel->getActiveTheme('pc',     $scope) ?? '');
            $activeThemeMobile = (string) ($templateModel->getActiveTheme('mobile', $scope) ?? '');
        } catch (\Throwable $e) {
            $activeThemePc = '';
            $activeThemeMobile = '';
        }

        $scanned = $templateModel->scanTemplates();           // 磁盘元数据(给系统白名单 + 已购合并用)

        // 1. 系统白名单(磁盘存在的才纳入;对所有商户免费可用)
        $availableTemplates = [];
        foreach ($merchantSystemTemplates as $sysName) {
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

        \App\Merchant\MerchantShell::render(dirname(__DIR__) . '/View/template.php', $frontUser, $currentMerchant, $merchantLevel, [
            'availableTemplates' => $availableTemplates,
            'activeThemePc'      => $activeThemePc,
            'activeThemeMobile'  => $activeThemeMobile,
            'csrfToken'          => $this->csrf->token(),
        ]);
    }
}
