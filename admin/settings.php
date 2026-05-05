<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * 后台系统设置页。
 *
 * 通过 action 参数切换不同设置选项卡：
 * base      - 基础设置
 * user      - 用户设置
 * shop      - 商城设置
 * currency  - 货币配置
 * blog      - 博客设置
 * mail      - 邮箱配置
 * substation - 分站配置
 */
adminRequireLogin();
$user = $adminUser;
$siteName = Config::get('sitename', 'EMSHOP');

// 邮箱配置"发送测试"相关 action 独立分支（GET 渲染弹窗；POST 执行发送）
// 放在 adminRequireLogin 之后、业务 tab 逻辑之前，避免被其它分支覆盖
if ((string) Input::get('_popup', '') === 'smtp_test') {
    // 弹窗里的 smtp 配置来自 URL query（用户在邮箱配置页填写的即时值），不从 DB 读
    $smtpTestCfg = [
        'from_email' => (string) Input::get('from_email', ''),
        'from_name'  => (string) Input::get('from_name', ''),
        'host'       => (string) Input::get('host', ''),
        'password'   => (string) Input::get('password', ''),
        'port'       => (string) Input::get('port', '465'),
    ];
    $csrfToken = Csrf::token();
    include __DIR__ . '/view/popup/smtp_test.php';
    return;
}
if (Request::isPost() && (string) Input::post('_action', '') === 'smtp_test') {
    if (!Csrf::validate((string) Input::post('csrf_token', ''))) {
        Response::error('请求已失效，请刷新页面后重试');
    }
    $to = trim((string) Input::post('to', ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        Response::error('请填写正确的接收邮箱');
    }
    // 测试配置来自当前页面表单（用户还未保存也能验证），不读 DB
    $cfg = [
        'from_email' => (string) Input::post('from_email', ''),
        'from_name'  => (string) Input::post('from_name', ''),
        'host'       => (string) Input::post('host', ''),
        'password'   => (string) Input::post('password', ''),
        'port'       => (int) Input::post('port', 465),
    ];
    $bodyHtml = '<p style="font-size:14px;color:#374151;">欢迎使用 EMSHOP 免费开源程序 · 基于 PHP 的商城程序及 CMS 建站系统</p>';
    $ok = Mailer::sendWith($cfg, $to, '[EMSHOP] SMTP 测试邮件', $bodyHtml);
    if ($ok) {
        Response::success('发送成功，请查收');
    }
    Response::error(Mailer::lastError() ?: '发送失败');
}

$currentTab = (string) Input::get('action', 'base');

// 仅允许的白名单 tab（guest_find = 游客查单、rebate = 推广返佣，独立选项卡）
$allowedTabs = ['base', 'security', 'seo', 'user', 'shop', 'guest_find', 'rebate', 'blog', 'mail', 'substation'];
if (!in_array($currentTab, $allowedTabs, true)) {
    $currentTab = 'base';
}

// POST 保存
if (Request::isPost()) {
    try {
        $csrf = (string) Input::post('csrf_token', '');
        if (!Csrf::validate($csrf)) {
            Response::error('请求已失效，请刷新页面后重试');
        }

        $tab = (string) Input::post('_tab', '');
        $saved = 0;

        switch ($tab) {
            // SEO 设置
            case 'seo':
                $format = (string) Input::post('url_format', 'default');
                if (!in_array($format, ['default', 'file', 'dir1', 'dir2'], true)) {
                    $format = 'default';
                }
                Config::set('url_format', $format);
                $saved++;

                foreach (['seo_title', 'seo_keywords', 'seo_description'] as $field) {
                    Config::set($field, trim((string) Input::post($field, '')));
                    $saved++;
                }
                break;

            // 安全设置
            case 'security':
                // 安全入口：正则限制为 [a-zA-Z0-9_-] 的短串，防路径穿越或注入
                $raw = trim((string) Input::post('admin_entry_key', ''));
                if ($raw !== '' && !preg_match('/^[a-zA-Z0-9_\-]{1,32}$/', $raw)) {
                    Response::error('安全入口仅允许字母/数字/下划线/短横线，长度 1~32');
                }
                Config::set('admin_entry_key', $raw);
                $saved++;
                break;

            // 基础设置
            case 'base':
                $fields = [
                    'site_enabled',
                    'sitename', 'site_url', 'site_keywords', 'site_description',
                    'site_logo', 'site_logo_type', 'site_icp', 'site_statistical_code',
                    'site_rewrite', 'site_timezone', 'homepage_mode',
                ];
                foreach ($fields as $field) {
                    $val = (string) Input::post($field, '');
                    Config::set($field, $val);
                    $saved++;
                }

                // Swoole API 地址：用于后台监控联通检查，也作为 server.php 的监听端口来源（取 URL 中 port）
                // 允许留空（回退默认）；非空时要求 http(s) 且必须显式包含端口。
                $swooleApiUrl = rtrim(trim((string) Input::post('swoole_api_url', '')), '/');
                if ($swooleApiUrl !== '') {
                    if (!preg_match('#^https?://#i', $swooleApiUrl)) {
                        Response::error('Swoole API 地址需以 http:// 或 https:// 开头');
                    }
                    $parts = @parse_url($swooleApiUrl);
                    $host = is_array($parts) ? trim((string) ($parts['host'] ?? '')) : '';
                    $port = is_array($parts) ? (int) ($parts['port'] ?? 0) : 0;
                    $path = is_array($parts) ? trim((string) ($parts['path'] ?? '')) : '';
                    if ($host === '' || $port < 1 || $port > 65535) {
                        Response::error('Swoole API 地址必须包含有效端口，例如 http://127.0.0.1:9601');
                    }
                    if ($path !== '' && $path !== '/') {
                        Response::error('Swoole API 地址无需携带路径，例如 http://127.0.0.1:9601');
                    }
                }
                Config::set('swoole_api_url', $swooleApiUrl);
                $saved++;
                break;

            // 用户设置
            case 'user':
                $fields = [
                    'user_register', 'user_verify_email',
                    'user_credit_name', 'user_credit_initial',
                ];
                foreach ($fields as $field) {
                    $val = (string) Input::post($field, '');
                    Config::set($field, $val);
                    $saved++;
                }
                break;

            // 商城设置
            case 'shop':
                // ① 开关字段（未勾选时 POST 不传，统一置 '0'）
                $switchFields = [
                    'shop_balance_enabled', 'shop_guest_balance_enabled', 'shop_enable_coupon',
                ];
                foreach ($switchFields as $sw) {
                    $val = Input::post($sw, '');
                    Config::set($sw, $val === '' ? '0' : $val);
                    $saved++;
                }

                // ② 金额字段：统一 ×1,000,000 存 BIGINT
                // 用 bcmul 避免 (float * 1000000) 的精度丢失（如 0.01 会变 9999）
                $amountFields = [
                    'shop_min_recharge', 'shop_max_recharge',
                    'shop_withdraw_min', 'shop_withdraw_max',
                ];
                foreach ($amountFields as $field) {
                    $raw = trim((string) Input::post($field, '0'));
                    if ($raw === '' || !is_numeric($raw)) {
                        $raw = '0';
                    }
                    Config::set($field, bcmul($raw, '1000000', 0));
                    $saved++;
                }

                // ③ 基础商城字段
                Config::set('shop_order_expire_minutes', (string) Input::post('shop_order_expire_minutes', '30'));
                $saved++;

                // ④ 店铺公告（富文本 HTML，无长度截断 —— 支持长公告 / 嵌入图片）
                Config::set('shop_announcement', (string) Input::post('shop_announcement', ''));
                $saved++;

                // ⑤ 公告显示位置：checkbox group → 逗号分隔；只放白名单值进 DB
                $rawPositions = $_POST['shop_announcement_positions'] ?? [];
                if (!is_array($rawPositions)) $rawPositions = [];
                $allowedPositions = ['home', 'goods_list'];
                $positions = array_values(array_intersect($allowedPositions, array_map('strval', $rawPositions)));
                Config::set('shop_announcement_positions', implode(',', $positions));
                $saved++;
                break;

            // 查单模式（独立选项卡：游客查单模式配置）
            case 'guest_find':
                // ① 开关字段 —— 联系方式查单与订单密码查单必须至少开启一种
                // 所有校验都在写库前完成，失败直接返回，不写任何字段
                $contactOn = Input::post('guest_find_contact_enabled', '') !== '';
                $passwordOn = Input::post('guest_find_password_enabled', '') !== '';
                if (!$contactOn && !$passwordOn) {
                    Response::error('查单模式：联系方式查单与订单密码查单必须开启至少一种');
                }
                Config::set('guest_find_contact_enabled', $contactOn ? '1' : '0');
                $saved++;
                Config::set('guest_find_password_enabled', $passwordOn ? '1' : '0');
                $saved++;

                // ③ 联系方式类型（默认 any 表示不限）
                // 白名单校验，避免写入非法值
                $allowedTypes = ['any', 'phone', 'email', 'qq'];
                $postedType = (string) Input::post('guest_find_contact_type', 'any');
                if (!in_array($postedType, $allowedTypes, true)) {
                    $postedType = 'any';
                }
                Config::set('guest_find_contact_type', $postedType);
                $saved++;

                // ④ 占位提示（checkout = 下单页；lookup = 查单页）
                $placeholderFields = [
                    'guest_find_contact_checkout_placeholder',
                    'guest_find_contact_lookup_placeholder',
                    'guest_find_password_checkout_placeholder',
                    'guest_find_password_lookup_placeholder',
                ];
                foreach ($placeholderFields as $field) {
                    Config::set($field, (string) Input::post($field, ''));
                    $saved++;
                }
                break;

            // 推广返佣（独立选项卡）
            case 'rebate':
                // 开关
                $enabled = Input::post('shop_enable_rebate', '') !== '';
                Config::set('shop_enable_rebate', $enabled ? '1' : '0');
                $saved++;

                // 全局 2 级比例：表单按百分比录入（5 = 5%），按万分位落库（500）
                foreach (['rebate_level1_rate', 'rebate_level2_rate'] as $field) {
                    $pct = (float) Input::post($field, 0);
                    if ($pct < 0)   $pct = 0;
                    if ($pct > 100) $pct = 100; // 最高 100%
                    $val = (int) round($pct * 100);
                    Config::set($field, (string) $val);
                    $saved++;
                }

                // 计算方式：amount / profit
                $calcMode = (string) Input::post('rebate_calculate_mode', 'amount');
                if (!in_array($calcMode, ['amount', 'profit'], true)) {
                    $calcMode = 'amount';
                }
                Config::set('rebate_calculate_mode', $calcMode);
                $saved++;

                // 冷却天数
                $freezeDays = (int) Input::post('rebate_freeze_days', 7);
                if ($freezeDays < 0) $freezeDays = 0;
                Config::set('rebate_freeze_days', (string) $freezeDays);
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
                    $val = (string) Input::post($field, '');
                    Config::set($field, $val);
                    $saved++;
                }
                break;

            // 邮箱配置：只保留 5 个字段（发送人邮箱 / SMTP服务器 / SMTP密码 / 端口 / 发送人名称）
            case 'mail':
                $fields = [
                    'mail_from_address', 'mail_host', 'mail_password', 'mail_port', 'mail_from_name',
                ];
                foreach ($fields as $field) {
                    $val = (string) Input::post($field, '');
                    Config::set($field, $val);
                    $saved++;
                }
                break;

            // 商户（分站）配置
            // 大部分字段已迁移到"商户等级"（em_merchant_level）：
            //   - 开通费用 → merchant_level.price
            //   - 允许自定义域名 → merchant_level.allow_custom_domain + max_custom_domain
            // 这里仅保留全站级开关与识别相关配置
            case 'substation':
                $fields = [
                    'substation_enabled',            // 总开关（MerchantContext::resolve 用）
                    'main_domain',                   // 主站根域名（二级域名识别必需）
                    'merchant_enable_self_open',     // 允许用户自助开通
                    'merchant_default_theme',        // 新商户默认模板（v1 未生效）
                    'merchant_custom_domain_tip',    // 自定义域名引导文案
                ];
                foreach ($fields as $field) {
                    $val = (string) Input::post($field, '');
                    Config::set($field, $val);
                    $saved++;
                }
                break;

            default:
                Response::error('未知设置分类');
        }

        Config::reload();
        GuestFindModel::clearCache();
        $csrfToken = Csrf::refresh();
        Response::success("已保存 {$saved} 项配置", ['csrf_token' => $csrfToken]);
    } catch (RuntimeException $e) {
        Response::error($e->getMessage());
    } catch (Throwable $e) {
        Response::error('系统繁忙，请稍后再试');
    }
}

$csrfToken = Csrf::token();

if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/settings.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/settings.php';
    require __DIR__ . '/index.php';
}
