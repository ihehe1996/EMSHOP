<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 授权接口 / 弹窗入口（{入口}/license）。
 *
 * 直接访问回后台首页；POST 处理授权相关动作；?_popup= 渲染各类授权弹窗。
 */
final class LicenseController extends BaseAdminController
{
    public function index(): void
    {
        $admin = $this->user();

if ($this->request->isPost()) {
    try {
        // 本分支的动作全是写操作（激活 / 解绑 / 重新校验 / 保存别名 / 购买 / 切换线路 /
        // 代理配置），都会改动授权状态，必须校验 CSRF。
        // 前端（旧 license 视图的 post() 包装、框架壳的线路切换）
        // 都已经在请求里带上 csrf_token。
        if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
            $this->response->error('请求已失效，请刷新页面后重试');
        }

        $action = (string) $this->input->post('_action', '');

        switch ($action) {
            case 'activate':
                $code = trim((string) $this->input->post('license_code', ''));
                $result = app(\App\Service\LicenseService::class)->activate($code);
                $this->response->success('激活成功：' . $result['level_label'], [
                    'level' => $result['level'],
                    'level_label' => $result['level_label'],
                    'csrf_token' => $this->csrf->refresh(),
                ]);
                break;

            case 'unbind':
                app(\App\Service\LicenseService::class)->unbind();
                $this->response->success('已解绑主授权域名', ['csrf_token' => $this->csrf->refresh()]);
                break;

            case 'revalidate':
                app(\App\Service\LicenseService::class)->revalidateCurrent();
                $this->response->success('已完成校验', ['csrf_token' => $this->csrf->refresh()]);
                break;

            case 'save_aliases':
                $raw = (string) $this->input->post('aliases', '');
                $saved = app(\App\Service\LicenseService::class)->saveAliasHosts($raw);
                $this->response->success('已保存', [
                    'aliases'    => $saved,
                    'csrf_token' => $this->csrf->refresh(),
                ]);
                break;

            case 'buy':
                $level = (string) $this->input->post('level', 'vip');
                if (!in_array($level, ['vip', 'svip', 'supreme'], true)) {
                    $this->response->error('无效的授权等级');
                }
                $url = app(\App\Service\LicenseService::class)->getBuyUrl(
                    $level,
                    (string) ($admin['email'] ?? '')
                );
                $this->response->success('', ['url' => $url]);
                break;

            case 'switch_line':
                $idx = (int) $this->input->post('index', 0);
                app(\App\Service\LicenseService::class)->switchLine($idx);
                $lines = app(\App\Service\LicenseService::class)->getAllLines();
                $this->response->success('已切换到：' . ($lines[$idx]['name'] ?? ''), [
                    'current_index' => $idx,
                    'current_name' => $lines[$idx]['name'] ?? '',
                    'current_url' => $lines[$idx]['url'] ?? '',
                    'csrf_token' => $this->csrf->refresh(),
                ]);
                break;

            // 官方账号：登录官方站前台账号（与激活码无关），只存 token 与展示信息
            case 'official_login':
                $account  = trim((string) $this->input->post('account', ''));
                $password = (string) $this->input->post('password', '');
                $profile  = app(\App\Service\LicenseService::class)->loginOfficialAccount($account, $password);
                $this->response->success('登录成功', [
                    'user'       => $profile,
                    'csrf_token' => $this->csrf->refresh(),
                ]);
                break;

            // 验证码登录：邮箱 + 邮箱验证码（免密码）
            case 'official_login_code':
                $profile = app(\App\Service\LicenseService::class)->loginOfficialAccountByCode(
                    (string) $this->input->post('email', ''),
                    (string) $this->input->post('code', '')
                );
                $this->response->success('登录成功', [
                    'user'       => $profile,
                    'csrf_token' => $this->csrf->refresh(),
                ]);
                break;

            case 'official_logout':
                app(\App\Service\LicenseService::class)->logoutOfficialAccount();
                $this->response->success('已退出官方账号', ['csrf_token' => $this->csrf->refresh()]);
                break;

            // 官方账号验证码：scene=register（注册）/ login（验证码登录）
            case 'official_email_code':
                app(\App\Service\LicenseService::class)->sendOfficialEmailCode(
                    (string) $this->input->post('email', ''),
                    (string) $this->input->post('scene', 'register')
                );
                $this->response->success('验证码已发送，请查收邮箱', ['csrf_token' => $this->csrf->refresh()]);
                break;

            case 'official_register':
                $profile = app(\App\Service\LicenseService::class)->registerOfficialAccount(
                    (string) $this->input->post('email', ''),
                    (string) $this->input->post('password', ''),
                    (string) $this->input->post('password_confirmation', '')
                );
                $this->response->success('注册成功', [
                    'user'       => $profile,
                    'csrf_token' => $this->csrf->refresh(),
                ]);
                break;

            // 购买渠道 + 联系方式：给应用商店的「获取正版激活码」弹窗用（与首页同一个数据源）
            case 'agent_links':
                $base = app(\App\Service\LicenseService::class)->fetchBaseData();
                $this->response->success('', [
                    'buy_links'     => $base['buy_links'] ?? [],
                    'used_fallback' => !empty($base['used_fallback']),
                    'contact'       => $base['contact'] ?? [],
                    'csrf_token'    => $this->csrf->refresh(),
                ]);
                break;

            // 官方账号名下的激活码列表：实时向官方站 /api/my/licenses 拉取
            case 'official_licenses':
                $this->response->success('', [
                    'licenses'   => app(\App\Service\LicenseService::class)->officialAccountLicenses(),
                    'csrf_token' => $this->csrf->refresh(),
                ]);
                break;

            // 官方账号个人资料：实时向官方站 /api/auth/me 拉取（只读，不轮换 CSRF）
            case 'official_profile':
                $this->response->success('', [
                    'user' => app(\App\Service\LicenseService::class)->officialAccountProfile(),
                ]);
                break;

            // 官方账号概况：资料 + 激活码数量一次拿齐（宫格页只发这一个请求）
            case 'official_overview':
                $overview = app(\App\Service\LicenseService::class)->officialAccountOverview();
                $this->response->success('', [
                    'user'           => $overview['user'] ?? [],
                    'licenses_count' => (int) ($overview['licenses_count'] ?? 0),
                ]);
                break;

            // 余额变动明细（只读，余额和列表同一次返回）
            case 'official_balance_logs':
                $this->response->success('', app(\App\Service\LicenseService::class)->officialAccountBalanceLogs(
                    (int) $this->input->post('page', 1),
                    (int) $this->input->post('per_page', 10)
                ));
                break;

            // 添加激活码：绑定到当前官方账号（已被其他账号绑定的由上游拒绝）
            case 'official_add_license':
                $result = app(\App\Service\LicenseService::class)->officialAccountBindLicense((string) $this->input->post('code', ''));
                $this->response->success('绑定成功', [
                    'license'    => $result['license'] ?? [],
                    'csrf_token' => $this->csrf->refresh(),
                ]);
                break;

            // 移除授权：清空上游该激活码的域名，并清掉本机对应的授权状态
            case 'official_remove_license':
                $result = app(\App\Service\LicenseService::class)->officialAccountRemoveLicense((string) $this->input->post('code', ''));
                $this->response->success('已移除授权', [
                    'license'       => $result['license'] ?? [],
                    'local_cleared' => !empty($result['local_cleared']),
                    'csrf_token'    => $this->csrf->refresh(),
                ]);
                break;

            // 重置授权码：换新码（旧码立即失效），本机在用的码同步更新
            case 'official_reset_license':
                $result = app(\App\Service\LicenseService::class)->officialAccountResetLicense((string) $this->input->post('code', ''));
                $this->response->success('已重置授权码', [
                    'license'       => $result['license'] ?? [],
                    'local_updated' => !empty($result['local_updated']),
                    'csrf_token'    => $this->csrf->refresh(),
                ]);
                break;

            // 代理配置：实时向官方站 /api/my/config 拉取，本站不保存
            case 'official_agent_config':
                $this->response->success('', [
                    'config' => app(\App\Service\LicenseService::class)->officialAccountAgentConfig(),
                ]);
                break;

            // 激活码仓库：列表 + 价格表 + 余额（代理专属，角色由上游校验）
            case 'official_em_codes':
                $this->response->success('', app(\App\Service\LicenseService::class)->officialAccountEmCodes(
                    (int) $this->input->post('page', 1),
                    (int) $this->input->post('per_page', 10),
                    (string) $this->input->post('usage', 'unused')
                ));
                break;

            // 生成激活码：按档位单价从上游账号余额扣费
            case 'official_em_codes_generate':
                $generated = app(\App\Service\LicenseService::class)->officialAccountGenerateEmCodes(
                    (string) $this->input->post('type', ''),
                    (int) $this->input->post('count', 1)
                );
                $this->response->success('已生成激活码', [
                    'codes'      => $generated['codes'],
                    'cost'       => $generated['cost'],
                    'balance'    => $generated['balance'],
                    'csrf_token' => $this->csrf->refresh(),
                ]);
                break;

            // 代理版本安装包：只列 EMSHOP 已发布版本
            case 'official_versions':
                $this->response->success('', app(\App\Service\LicenseService::class)->officialAccountVersions(
                    (int) $this->input->post('page', 1),
                    (int) $this->input->post('per_page', 10)
                ));
                break;

            // 代理订单记录（只读，归属由上游按 token 过滤）
            case 'official_orders':
                $this->response->success('', app(\App\Service\LicenseService::class)->officialAccountOrders([
                    'page'     => (int) $this->input->post('page', 1),
                    'per_page' => (int) $this->input->post('per_page', 10),
                    'keyword'  => (string) $this->input->post('keyword', ''),
                    /* 只做 EMSHOP：上游这一页支持 em / tt，这里固定 em */
                    'project'  => 'em',
                    'state'    => (string) $this->input->post('state', ''),
                ]));
                break;

            // 我的广告位（只做 EMSHOP）
            case 'official_ad_slots':
                $this->response->success('', app(\App\Service\LicenseService::class)->officialAccountAdSlots([
                    'page'     => (int) $this->input->post('page', 1),
                    'per_page' => (int) $this->input->post('per_page', 10),
                    'keyword'  => (string) $this->input->post('keyword', ''),
                    'state'    => (string) $this->input->post('state', ''),
                ]));
                break;

            case 'official_ad_slot_detail':
                $this->response->success('', [
                    'slot' => app(\App\Service\LicenseService::class)->officialAccountAdSlotDetail((int) $this->input->post('id', 0)),
                ]);
                break;

            case 'official_ad_slot_save':
                $slotId = (int) $this->input->post('id', 0);
                $savedSlot = app(\App\Service\LicenseService::class)->officialAccountAdSlotSave($slotId > 0 ? $slotId : null, [
                    'title'       => (string) $this->input->post('title', ''),
                    'label'       => (string) $this->input->post('label', ''),
                    'label_color' => (string) $this->input->post('label_color', ''),
                    'content'     => (string) $this->input->post('content', ''),
                    'link_url'    => (string) $this->input->post('link_url', ''),
                    'expires_on'  => (string) $this->input->post('expires_on', ''),
                    'status'      => (int) $this->input->post('status', 1),
                ]);

                $this->response->success('已保存', [
                    'slot'       => $savedSlot,
                    'csrf_token' => $this->csrf->refresh(),
                ]);
                break;

            case 'official_ad_slot_status':
                app(\App\Service\LicenseService::class)->officialAccountAdSlotStatus(
                    (int) $this->input->post('id', 0),
                    (int) $this->input->post('status', 1)
                );
                $this->response->success('已更新', ['csrf_token' => $this->csrf->refresh()]);
                break;

            case 'official_ad_slot_delete':
                app(\App\Service\LicenseService::class)->officialAccountAdSlotDelete((int) $this->input->post('id', 0));
                $this->response->success('已删除', ['csrf_token' => $this->csrf->refresh()]);
                break;

            case 'official_ad_slot_move':
                app(\App\Service\LicenseService::class)->officialAccountAdSlotMove(
                    (int) $this->input->post('id', 0),
                    (string) $this->input->post('direction', '')
                );
                $this->response->success('顺序已调整', ['csrf_token' => $this->csrf->refresh()]);
                break;

            // 安装记录（只读，归属由上游按 token 过滤）
            case 'official_install_records':
                $this->response->success('', app(\App\Service\LicenseService::class)->officialAccountInstallRecords([
                    'page'     => (int) $this->input->post('page', 1),
                    'per_page' => (int) $this->input->post('per_page', 10),
                    'keyword'  => (string) $this->input->post('keyword', ''),
                    /* 只做 EMSHOP */
                    'project'  => 'em',
                ]));
                break;

            // 代理配置保存：同步写上游，必须校验登录密码
            case 'official_agent_config_save':
                $saved = app(\App\Service\LicenseService::class)->officialAccountSaveAgentConfig([
                    'identity'          => (string) $this->input->post('identity', ''),
                    'qq_service'        => (string) $this->input->post('qq_service', ''),
                    'qq_group'          => (string) $this->input->post('qq_group', ''),
                    'wechat_service'    => (string) $this->input->post('wechat_service', ''),
                    'wechat_qr'         => (string) $this->input->post('wechat_qr', ''),
                    'tg_service'        => (string) $this->input->post('tg_service', ''),
                    'tg_group_url'      => (string) $this->input->post('tg_group_url', ''),
                    'em_buy_links'      => (string) $this->input->post('em_buy_links', ''),
                    'em_download_links' => (string) $this->input->post('em_download_links', ''),
                ], (string) $this->input->post('password', ''));

                $this->response->success('已保存', [
                    'config'     => $saved,
                    'csrf_token' => $this->csrf->refresh(),
                ]);
                break;

            // 只读：后台线路列表展开时并发测一遍各线路延迟（不轮换 CSRF）
            case 'line_ping':
                $this->response->success('', [
                    'lines'         => $this->licenseClient->measureLatency(),
                    'current_index' => app(\App\Service\LicenseService::class)->currentLineIndex(),
                ]);
                break;

            default:
                $this->response->error('未知操作');
        }
    } catch (\RuntimeException $e) {
        $this->response->error($e->getMessage());
    } catch (\Throwable $e) {
        $this->response->error($e->getMessage() ?: '系统繁忙');
    }
}

// ============================================================
// 版本源码包下载（GET）：本地把上游的 zip 代理下来，token 不出服务端。
// 下载不能带请求体，CSRF 令牌放查询串里校验。
// ============================================================
if ((string) $this->input->get('_download', '') === 'version') {
    if (!$this->csrf->validate((string) $this->input->get('csrf_token', ''))) {
        $this->response->error('请求已失效，请刷新页面后重试');
    }

    try {
        $source = app(\App\Service\LicenseService::class)->officialAccountVersionSource((int) $this->input->get('id', 0));
    } catch (\Throwable $e) {
        $this->response->error($e->getMessage() ?: '下载失败');
    }

    $body = (string) ($source['body'] ?? '');
    $disposition = str_replace(["", "
"], '', (string) ($source['disposition'] ?? ''));

    if ($disposition === '') {
        $disposition = 'attachment; filename="EMSHOP-source.zip"';
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: ' . $disposition);
    header('Content-Length: ' . strlen($body));
    header('Cache-Control: no-store');
    echo $body;
    return;
}

// ============================================================
// 弹窗（渲染完整 HTML，layer.open type:2 加载），数据源都是 base-data：
//   ?_popup=agent    获取正版激活码 —— 只放购买渠道 buy_links
//   ?_popup=contact  联系客服       —— 只放联系方式 contact
// 两者内容刻意不重叠：授权页上这是两个语义不同的按钮，早先共用一份内容时
// 「联系客服」点开看到的是购买链接，属于用错内容。
// ============================================================
$popupMode = (string) $this->input->get('_popup', '');
if ($popupMode === 'agent' || $popupMode === 'contact') {
    $agent = null;
    $agentError = null;
    try {
        $agent = app(\App\Service\LicenseService::class)->fetchBaseData();
    } catch (\Throwable $e) {
        $agentError = $e->getMessage() ?: '获取授权信息失败';
    }
    include dirname(__DIR__) . '/View/popup/agent_config.php';
    return;
}

// 官方账号管理（大 iframe）：账号概览 + 功能宫格（个人资料 / 退出登录在卡片里）
if ($popupMode === 'account') {
    $officialAccount = app(\App\Service\LicenseService::class)->officialAccount();
    $csrfToken       = $this->csrf->token();
    include dirname(__DIR__) . '/View/popup/official_account.php';
    return;
}

// 官方账号登录 / 注册（应用商店等需要账号身份的入口自动打开）
if ($popupMode === 'official_login') {
    $csrfToken   = $this->csrf->token();
    $initialTab  = (string) $this->input->get('tab', 'login');
    include dirname(__DIR__) . '/View/popup/official_login.php';
    return;
}

// 官方账号 · 我的激活码（宫格入口打开的功能窗口）
if ($popupMode === 'licenses') {
    $csrfToken        = $this->csrf->token();
    $officialLoggedIn = app(\App\Service\LicenseService::class)->officialAccount() !== [];
    include dirname(__DIR__) . '/View/popup/official_licenses.php';
    return;
}

// 官方账号 · 余额变动明细（宫格「账户余额」打开的功能窗口）
if ($popupMode === 'balance_logs') {
    $csrfToken        = $this->csrf->token();
    $officialLoggedIn = app(\App\Service\LicenseService::class)->officialAccount() !== [];
    include dirname(__DIR__) . '/View/popup/official_balance_logs.php';
    return;
}

// 官方账号 · 添加激活码（宫格「添加激活码」打开的输入窗口）
if ($popupMode === 'add_license') {
    $csrfToken        = $this->csrf->token();
    $officialLoggedIn = app(\App\Service\LicenseService::class)->officialAccount() !== [];
    include dirname(__DIR__) . '/View/popup/official_add_license.php';
    return;
}

// 官方账号 · 代理配置（代理 / 管理账号才显示这张卡）
if ($popupMode === 'agent_settings') {
    $csrfToken        = $this->csrf->token();
    $officialLoggedIn = app(\App\Service\LicenseService::class)->officialAccount() !== [];
    include dirname(__DIR__) . '/View/popup/official_agent_config.php';
    return;
}

// 官方账号 · 激活码仓库（代理 / 管理账号）
if ($popupMode === 'code_warehouse') {
    $csrfToken        = $this->csrf->token();
    $officialLoggedIn = app(\App\Service\LicenseService::class)->officialAccount() !== [];
    include dirname(__DIR__) . '/View/popup/official_code_warehouse.php';
    return;
}

// 官方账号 · 版本安装包（代理 / 管理账号，只列 EMSHOP）
if ($popupMode === 'versions') {
    $csrfToken        = $this->csrf->token();
    $officialLoggedIn = app(\App\Service\LicenseService::class)->officialAccount() !== [];
    include dirname(__DIR__) . '/View/popup/official_versions.php';
    return;
}

// 官方账号 · 用户订单记录（代理 / 管理账号）
if ($popupMode === 'orders') {
    $csrfToken        = $this->csrf->token();
    $officialLoggedIn = app(\App\Service\LicenseService::class)->officialAccount() !== [];
    include dirname(__DIR__) . '/View/popup/official_orders.php';
    return;
}

// 官方账号 · 广告位管理（代理 / 管理账号，只做 EMSHOP）
if ($popupMode === 'ad_slots') {
    $csrfToken        = $this->csrf->token();
    $officialLoggedIn = app(\App\Service\LicenseService::class)->officialAccount() !== [];
    include dirname(__DIR__) . '/View/popup/official_ad_slots.php';
    return;
}

// 官方账号 · 广告位表单（新建 / 编辑，独立 iframe 弹窗）
if ($popupMode === 'ad_slot_form') {
    $csrfToken        = $this->csrf->token();
    $officialLoggedIn = app(\App\Service\LicenseService::class)->officialAccount() !== [];
    $slotId           = (int) $this->input->get('id', 0);
    include dirname(__DIR__) . '/View/popup/official_ad_slot_form.php';
    return;
}

// 官方账号 · 安装记录（代理 / 管理账号）
if ($popupMode === 'install_records') {
    $csrfToken        = $this->csrf->token();
    $officialLoggedIn = app(\App\Service\LicenseService::class)->officialAccount() !== [];
    include dirname(__DIR__) . '/View/popup/official_install_records.php';
    return;
}

// ============================================================
// 独立页面已下线
// ============================================================
// 激活、获取激活码、官方账号管理都改成了后台首页 / 应用商店里的弹窗，
// 这个入口只保留 POST 动作和 ?_popup= 弹窗（上面），直接访问就回首页。
$this->response->redirect(EM_ADMIN_PATH . '/home');

    }
}
