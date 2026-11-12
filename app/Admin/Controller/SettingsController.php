<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 系统设置（/admin/settings）。
 *
 * 通过 action 参数切换选项卡：
 *   base / security / seo / user / shop / guest_find / rebate / blog / mail / substation
 *
 * 邮箱测试：?_popup=smtp_test 渲染弹窗；POST _action=smtp_test 发送测试邮件。
 *
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class SettingsController extends BaseAdminController
{
    public function index(): void
    {
        $admin = $this->user();

        // 邮箱配置"发送测试"弹窗（GET 渲染）
        if ((string) $this->input->get('_popup', '') === 'smtp_test') {
            $smtpTestCfg = [
                'from_email' => (string) $this->input->get('from_email', ''),
                'from_name'  => (string) $this->input->get('from_name', ''),
                'host'       => (string) $this->input->get('host', ''),
                'password'   => (string) $this->input->get('password', ''),
                'port'       => (string) $this->input->get('port', '465'),
                // 收件邮箱默认值：当前登录管理员自己的邮箱
                'to'         => trim((string) ($admin['email'] ?? '')),
            ];

            $this->render('popup/smtp_test', [
                'smtpTestCfg' => $smtpTestCfg,
                'csrfToken'   => $this->csrf->token(),
            ]);
            return;
        }

        // 发送测试邮件
        if ($this->request->isPost() && (string) $this->input->post('_action', '') === 'smtp_test') {
            $this->handleSmtpTest();
            return;
        }

        $currentTab = (string) $this->input->get('action', 'base');
        $allowedTabs = ['base', 'security', 'seo', 'user', 'shop', 'guest_find', 'rebate', 'blog', 'mail', 'substation'];
        if (!in_array($currentTab, $allowedTabs, true)) {
            $currentTab = 'base';
        }

        // POST 保存
        if ($this->request->isPost()) {
            $this->handleSave();
            return;
        }

        $this->renderList($currentTab);
    }

    /**
     * SMTP 测试邮件。
     */
    private function handleSmtpTest(): void
    {
        if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
            $this->response->error('请求已失效，请刷新页面后重试');
        }
        $to = trim((string) $this->input->post('to', ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->response->error('请填写正确的接收邮箱');
        }
        // 测试配置来自当前页面表单（用户还未保存也能验证），不读 DB
        $cfg = [
            'from_email' => (string) $this->input->post('from_email', ''),
            'from_name'  => (string) $this->input->post('from_name', ''),
            'host'       => (string) $this->input->post('host', ''),
            'password'   => (string) $this->input->post('password', ''),
            'port'       => (int) $this->input->post('port', 465),
        ];
        $bodyHtml = '<p style="font-size:14px;color:#374151;">欢迎使用 EMSHOP 免费开源程序 · 基于 PHP 的商城程序及 CMS 建站系统</p>';
        $ok = \Mailer::sendWith($cfg, $to, '[EMSHOP] SMTP 测试邮件', $bodyHtml);
        if ($ok) {
            $this->response->success('发送成功，请查收');
        }
        $this->response->error(\Mailer::lastError() ?: '发送失败');
    }

    /**
     * 保存设置。
     */
    private function handleSave(): void
    {
        try {
            $csrf = (string) $this->input->post('csrf_token', '');
            if (!$this->csrf->validate($csrf)) {
                $this->response->error('请求已失效，请刷新页面后重试');
            }

            $tab = (string) $this->input->post('_tab', '');
            $saved = 0;

            switch ($tab) {
                // SEO 设置
                case 'seo':
                    $format = (string) $this->input->post('url_format', 'default');
                    if (!in_array($format, ['default', 'file', 'dir1', 'dir2'], true)) {
                        $format = 'default';
                    }
                    $this->config->set('url_format', $format);
                    $saved++;

                    foreach (['seo_title', 'seo_description'] as $field) {
                        $this->config->set($field, trim((string) $this->input->post($field, '')));
                        $saved++;
                    }
                    break;

                // 安全设置
                case 'security':
                    // 开发模式：未勾选时表单不传，按 0 存
                    $this->config->set('license_dev_mode', $this->input->post('license_dev_mode', '') === '' ? '0' : '1');
                    $saved++;
                    break;

                // 基础设置
                case 'base':
                    $fields = [
                        'site_enabled',
                        'sitename', 'site_keywords', 'site_description',
                        'site_favicon', 'site_logo', 'site_logo_type', 'site_icp', 'site_statistical_code',
                        'site_rewrite', 'site_timezone',
                    ];
                    foreach ($fields as $field) {
                        $val = (string) $this->input->post($field, '');
                        $this->config->set($field, $val);
                        $saved++;
                    }

                    // 首页入口：只允许这两档
                    $homepageMode = (string) $this->input->post('homepage_mode', 'mall');
                    if (!in_array($homepageMode, ['mall', 'goods_list'], true)) {
                        $homepageMode = 'mall';
                    }
                    $this->config->set('homepage_mode', $homepageMode);
                    $saved++;

                    // 站点地址：允许留空；非空时必须是 http(s) 且仅保留 origin
                    $siteUrl = rtrim(trim((string) $this->input->post('site_url', '')), '/');
                    if ($siteUrl !== '') {
                        if (!preg_match('#^https?://#i', $siteUrl)) {
                            $this->response->error('站点地址需以 http:// 或 https:// 开头');
                        }
                        $parts = @parse_url($siteUrl);
                        $scheme = strtolower((string) (is_array($parts) ? ($parts['scheme'] ?? '') : ''));
                        $host = trim((string) (is_array($parts) ? ($parts['host'] ?? '') : ''));
                        $port = (int) (is_array($parts) ? ($parts['port'] ?? 0) : 0);
                        $path = trim((string) (is_array($parts) ? ($parts['path'] ?? '') : ''));
                        $query = trim((string) (is_array($parts) ? ($parts['query'] ?? '') : ''));
                        $fragment = trim((string) (is_array($parts) ? ($parts['fragment'] ?? '') : ''));
                        if (($scheme !== 'http' && $scheme !== 'https') || $host === '') {
                            $this->response->error('站点地址格式无效，请填写完整 URL（如 https://example.com）');
                        }
                        if ($port > 0 && ($port < 1 || $port > 65535)) {
                            $this->response->error('站点地址端口无效');
                        }
                        if (($path !== '' && $path !== '/') || $query !== '' || $fragment !== '') {
                            $this->response->error('站点地址无需携带路径、参数或锚点（如 https://example.com）');
                        }
                        $siteUrl = $scheme . '://' . $host . ($port > 0 ? ':' . $port : '') . '/';
                    }
                    $this->config->set('site_url', $siteUrl);
                    $saved++;

                    break;

                // 用户设置
                case 'user':
                    $this->config->set('user_login', $this->input->post('user_login', '') === '' ? '0' : '1');
                    $this->config->set('user_register', $this->input->post('user_register', '') === '' ? '0' : '1');
                    $rawRegFields = $_POST['user_register_fields'] ?? [];
                    if (!is_array($rawRegFields)) {
                        $rawRegFields = [];
                    }
                    $allowedRegFields = ['mobile', 'email'];
                    $regFields = array_values(array_intersect($allowedRegFields, array_map('strval', $rawRegFields)));
                    $this->config->set('user_register_fields', implode(',', $regFields));
                    $this->config->set('user_credit_name', trim((string) $this->input->post('user_credit_name', '经验')));
                    $this->config->set('user_credit_initial', (string) max(0, (int) $this->input->post('user_credit_initial', 0)));
                    $this->config->set('user_exp_per_yuan', (string) max(0, (int) $this->input->post('user_exp_per_yuan', 0)));
                    $saved += 6;
                    break;

                // 商城设置
                case 'shop':
                    // ① 开关字段（未勾选时 POST 不传，统一置 '0'）
                    $switchFields = [
                        'shop_balance_enabled', 'shop_guest_balance_enabled', 'shop_enable_coupon',
                        'shop_display_stock', 'shop_display_sales', 'shop_block_sold_out_access',
                    ];
                    foreach ($switchFields as $sw) {
                        $val = $this->input->post($sw, '');
                        $this->config->set($sw, $val === '' ? '0' : $val);
                        $saved++;
                    }

                    // ② 金额字段：统一 ×1,000,000 存 BIGINT
                    $amountFields = [
                        'shop_min_recharge', 'shop_max_recharge',
                        'shop_withdraw_min', 'shop_withdraw_max',
                    ];
                    foreach ($amountFields as $field) {
                        $rawMicro = \Money::tryParse($this->input->post($field, '0'), true);
                        $this->config->set($field, (string) ($rawMicro ?? 0));
                        $saved++;
                    }

                    // ③ 基础商城字段
                    $this->config->set('shop_order_expire_minutes', (string) $this->input->post('shop_order_expire_minutes', '30'));
                    $saved++;

                    // ④ 店铺公告（富文本 HTML，无长度截断）
                    $this->config->set('shop_announcement', (string) $this->input->post('shop_announcement', ''));
                    $saved++;

                    // ⑤ 公告显示位置：checkbox group → 逗号分隔；只放白名单值进 DB
                    $rawPositions = $_POST['shop_announcement_positions'] ?? [];
                    if (!is_array($rawPositions)) {
                        $rawPositions = [];
                    }
                    $allowedPositions = ['home', 'goods_list'];
                    $positions = array_values(array_intersect($allowedPositions, array_map('strval', $rawPositions)));
                    $this->config->set('shop_announcement_positions', implode(',', $positions));
                    $saved++;
                    break;

                // 查单模式
                case 'guest_find':
                    // 联系方式查单与订单密码查单必须至少开启一种
                    $contactOn = $this->input->post('guest_find_contact_enabled', '') !== '';
                    $passwordOn = $this->input->post('guest_find_password_enabled', '') !== '';
                    if (!$contactOn && !$passwordOn) {
                        $this->response->error('查单模式：联系方式查单与订单密码查单必须开启至少一种');
                    }
                    $this->config->set('guest_find_contact_enabled', $contactOn ? '1' : '0');
                    $saved++;
                    $this->config->set('guest_find_password_enabled', $passwordOn ? '1' : '0');
                    $saved++;

                    // 联系方式类型（默认 any 表示不限）
                    $allowedTypes = ['any', 'phone', 'email', 'qq'];
                    $postedType = (string) $this->input->post('guest_find_contact_type', 'any');
                    if (!in_array($postedType, $allowedTypes, true)) {
                        $postedType = 'any';
                    }
                    $this->config->set('guest_find_contact_type', $postedType);
                    $saved++;

                    // 占位提示（checkout = 下单页；lookup = 查单页）
                    $placeholderFields = [
                        'guest_find_contact_checkout_placeholder',
                        'guest_find_contact_lookup_placeholder',
                        'guest_find_password_checkout_placeholder',
                        'guest_find_password_lookup_placeholder',
                    ];
                    foreach ($placeholderFields as $field) {
                        $this->config->set($field, (string) $this->input->post($field, ''));
                        $saved++;
                    }
                    break;

                // 推广返佣
                case 'rebate':
                    $enabled = $this->input->post('shop_enable_rebate', '') !== '';
                    $this->config->set('shop_enable_rebate', $enabled ? '1' : '0');
                    $saved++;

                    // 全局 2 级比例：表单按百分比录入（5 = 5%），按万分位落库（500）
                    foreach (['rebate_level1_rate', 'rebate_level2_rate'] as $field) {
                        $pct = (float) $this->input->post($field, 0);
                        if ($pct < 0) {
                            $pct = 0;
                        }
                        if ($pct > 100) {
                            $pct = 100;
                        }
                        $val = (int) round($pct * 100);
                        $this->config->set($field, (string) $val);
                        $saved++;
                    }

                    // 计算方式：amount / profit
                    $calcMode = (string) $this->input->post('rebate_calculate_mode', 'amount');
                    if (!in_array($calcMode, ['amount', 'profit'], true)) {
                        $calcMode = 'amount';
                    }
                    $this->config->set('rebate_calculate_mode', $calcMode);
                    $saved++;

                    // 冷却天数
                    $freezeDays = (int) $this->input->post('rebate_freeze_days', 7);
                    if ($freezeDays < 0) {
                        $freezeDays = 0;
                    }
                    $this->config->set('rebate_freeze_days', (string) $freezeDays);
                    $saved++;
                    break;

                // 博客设置
                case 'blog':
                    $fields = [
                        'blog_article_per_page', 'blog_comment_need_verify',
                        'blog_show_author', 'blog_show_views', 'blog_rss_enabled',
                        'blog_seo_title', 'blog_seo_keywords', 'blog_seo_description',
                    ];
                    foreach ($fields as $field) {
                        $val = (string) $this->input->post($field, '');
                        $this->config->set($field, $val);
                        $saved++;
                    }
                    break;

                // 邮箱配置：只保留 5 个字段
                case 'mail':
                    $fields = [
                        'mail_from_address', 'mail_host', 'mail_password', 'mail_port', 'mail_from_name',
                    ];
                    foreach ($fields as $field) {
                        $val = (string) $this->input->post($field, '');
                        $this->config->set($field, $val);
                        $saved++;
                    }
                    break;

                // 商户（分站）配置
                case 'substation':
                    $fields = [
                        'substation_enabled',
                        'main_domain',
                        'merchant_enable_self_open',
                        'merchant_default_theme',
                        'merchant_custom_domain_tip',
                    ];
                    foreach ($fields as $field) {
                        $val = (string) $this->input->post($field, '');
                        $this->config->set($field, $val);
                        $saved++;
                    }
                    break;

                default:
                    $this->response->error('未知设置分类');
            }

            $this->config->reload();
            \App\Model\GuestFindModel::clearCache();
            $csrfToken = $this->csrf->refresh();
            $this->response->success("已保存 {$saved} 项配置", ['csrf_token' => $csrfToken]);
        } catch (\RuntimeException $e) {
            $this->response->error($e->getMessage());
        } catch (\Throwable $e) {
            $this->response->error('系统繁忙，请稍后再试');
        }
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套旧后台框架壳。
     */
    private function renderList(string $currentTab): void
    {
        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('settings', [
                'currentTab' => $currentTab,
                'csrfToken'  => $this->csrf->token(),
            ]);
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/settings.php', $this->user(), get_defined_vars());
    }
}
