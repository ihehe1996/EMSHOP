<?php

declare(strict_types=1);

namespace App\Merchant\Controller;

/**
 * 商户后台 - 店铺设置（/user/merchant/settings）。
 */
final class SettingsController extends MerchantBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        $currentMerchant = $this->merchant();
        $merchantLevel = $this->level();
        $merchantId = (int) $currentMerchant['id'];

        if ($this->request->isPost()) {
            try {
                if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
                    $this->response->error('请求已失效，请刷新页面后重试');
                }
                $action = (string) $this->input->post('_action', '');

                switch ($action) {
                    case 'save_profile': {
                        $name = trim((string) $this->input->post('name', ''));
                        $nameErr = DisplayName::validate($name, '店铺名');
                        if ($nameErr !== null) {
                            $this->response->error($nameErr);
                        }

                        // 默认加价率：表单按百分比录入（10 = 10%），落库万分位（1000）
                        $markupPct = (float) $this->input->post('default_markup_rate', 10);
                        if ($markupPct < 0)   $markupPct = 0;
                        if ($markupPct > 1000) $markupPct = 1000; // 最高 1000%（保险用）
                        $defaultMarkup = (int) round($markupPct * 100);

                        // 公告显示位置：checkbox group → 逗号分隔字符串；仅允许白名单值
                        $rawPositions = $_POST['announcement_positions'] ?? [];
                        if (!is_array($rawPositions)) $rawPositions = [];
                        $allowedPositions = ['home', 'goods_list'];
                        $positions = array_values(array_intersect($allowedPositions, array_map('strval', $rawPositions)));

                        $data = [
                            'name' => $name,
                            'logo' => trim((string) $this->input->post('logo', '')),
                            'slogan' => trim((string) $this->input->post('slogan', '')),
                            'description' => trim((string) $this->input->post('description', '')),
                            'icp' => trim((string) $this->input->post('icp', '')),
                            // 富文本，不 trim（trim 会破坏富文本里嵌入的换行 / 空白结构）
                            'announcement' => (string) $this->input->post('announcement', ''),
                            'announcement_positions' => implode(',', $positions),
                            'default_markup_rate' => $defaultMarkup,
                        ];
                        $this->db->update('merchant', $data, $merchantId);
                        $this->response->success('已保存', ['csrf_token' => $this->csrf->refresh()]);
                        break;
                    }

                    case 'save_domain': {
                        $allowSubdomain = (int) ($merchantLevel['allow_subdomain'] ?? 0) === 1;
                        $allowCustom = (int) ($merchantLevel['allow_custom_domain'] ?? 0) === 1;

                        $subdomain = strtolower(trim((string) $this->input->post('subdomain', '')));
                        $customDomain = strtolower(trim((string) $this->input->post('custom_domain', '')));

                        $data = [];

                        // 二级域名
                        if ($allowSubdomain) {
                            if ($subdomain !== '') {
                                if (!preg_match('/^[a-z0-9]([a-z0-9\-]{0,30}[a-z0-9])?$/', $subdomain)) {
                                    $this->response->error('二级域名格式不合法（仅字母/数字/短横线）');
                                }
                                // 唯一性
                                $merchantModel = app(\App\Model\MerchantModel::class);
                                if ($merchantModel->existsSubdomain($subdomain, $merchantId)) {
                                    $this->response->error('二级域名已被占用');
                                }
                                $domainErr = MerchantDomainGuard::subdomainError($subdomain, $merchantId);
                                if ($domainErr !== '') {
                                    $this->response->error($domainErr);
                                }
                            }
                            $data['subdomain'] = $subdomain !== '' ? $subdomain : null;
                        } else {
                            if ($subdomain !== '') {
                                $this->response->error('当前等级不允许二级域名');
                            }
                        }

                        // 自定义顶级域名
                        if ($allowCustom) {
                            if ($customDomain !== '') {
                                if (!preg_match('/^[a-z0-9]([a-z0-9\-\.]{1,199})$/', $customDomain)) {
                                    $this->response->error('自定义域名格式不合法');
                                }
                                $merchantModel = app(\App\Model\MerchantModel::class);
                                if ($merchantModel->existsCustomDomain($customDomain, $merchantId)) {
                                    $this->response->error('该域名已被占用');
                                }
                                $domainErr = MerchantDomainGuard::customDomainError($customDomain, $merchantId);
                                if ($domainErr !== '') {
                                    $this->response->error($domainErr);
                                }
                            }
                            $data['custom_domain'] = $customDomain !== '' ? $customDomain : null;
                        } else {
                            if ($customDomain !== '') {
                                $this->response->error('当前等级不允许自定义顶级域名');
                            }
                        }

                        if ($data !== []) {
                            $this->db->update('merchant', $data, $merchantId);
                        }
                        $this->response->success('域名已保存', ['csrf_token' => $this->csrf->refresh()]);
                        break;
                    }

                    default:
                        $this->response->error('未知操作');
                }
            } catch (RuntimeException $e) {
                $this->response->error($e->getMessage());
            } catch (\Throwable $e) {
                $this->response->error($e->getMessage() ?: '系统繁忙，请稍后再试');
            }
        }

        // 主站根域名（二级域名拼接预览用）
        $mainDomain = (string) ($this->config->get('main_domain') ?? '');

        \App\Merchant\MerchantShell::render(dirname(__DIR__) . '/View/settings.php', $frontUser, $currentMerchant, $merchantLevel, [
            'mainDomain' => $mainDomain,
            'customDomainTip' => (string) ($this->config->get('merchant_custom_domain_tip') ?? ''),
        ]);
    }
}
