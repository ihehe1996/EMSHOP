<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * 正版授权 - 管理页
 *
 * 功能：
 *   - 显示当前站点授权状态
 *   - 粘贴激活码激活
 *   - 购买 VIP / SVIP / 至尊（跳转到授权服务器付款页）
 *   - 手动撤销当前授权（换码 / 迁站前用）
 *   - 手动触发"校验"（向服务器确认激活状态没被撤销）
 *   - 历史激活记录
 */
adminRequireLogin();
$user = $adminUser;
$siteName = Config::get('sitename', 'EMSHOP');

if (Request::isPost()) {
    try {
        $action = (string) Input::post('_action', '');

        switch ($action) {
            case 'activate':
                $code = trim((string) Input::post('license_code', ''));
                $result = LicenseService::activate($code);
                Response::success('激活成功：' . $result['level_label'], [
                    'level' => $result['level'],
                    'level_label' => $result['level_label'],
                    'csrf_token' => Csrf::refresh(),
                ]);
                break;

            case 'unbind':
                LicenseService::unbind();
                Response::success('已解绑主授权域名', ['csrf_token' => Csrf::refresh()]);
                break;

            case 'revalidate':
                LicenseService::revalidateCurrent();
                Response::success('已完成校验', ['csrf_token' => Csrf::refresh()]);
                break;

            case 'save_aliases':
                $raw = (string) Input::post('aliases', '');
                $saved = LicenseService::saveAliasHosts($raw);
                Response::success('已保存', [
                    'aliases'    => $saved,
                    'csrf_token' => Csrf::refresh(),
                ]);
                break;

            case 'buy':
                $level = (string) Input::post('level', 'vip');
                if (!in_array($level, ['vip', 'svip', 'supreme'], true)) {
                    Response::error('无效的授权等级');
                }
                $url = LicenseService::getBuyUrl(
                    $level,
                    (string) ($adminUser['email'] ?? '')
                );
                Response::success('', ['url' => $url]);
                break;

            case 'switch_line':
                $idx = (int) Input::post('index', 0);
                LicenseService::switchLine($idx);
                $lines = LicenseService::getAllLines();
                Response::success('已切换到：' . ($lines[$idx]['name'] ?? ''), [
                    'current_index' => $idx,
                    'current_name' => $lines[$idx]['name'] ?? '',
                    'current_url' => $lines[$idx]['url'] ?? '',
                    'csrf_token' => Csrf::refresh(),
                ]);
                break;

            case 'agent_config':
                $data = LicenseService::fetchAgentConfig();
                Response::success('', [
                    'agent' => $data,
                    'csrf_token' => Csrf::refresh(),
                ]);
                break;

            default:
                Response::error('未知操作');
        }
    } catch (RuntimeException $e) {
        Response::error($e->getMessage());
    } catch (Throwable $e) {
        Response::error($e->getMessage() ?: '系统繁忙');
    }
}

// ============================================================
// 弹窗：获取正版授权码（渲染完整 HTML，layer.open type:2 加载）
// ============================================================
if ((string) Input::get('_popup', '') === 'agent') {
    $agent = null;
    $agentError = null;
    try {
        $agent = LicenseService::fetchAgentConfig();
    } catch (Throwable $e) {
        $agentError = $e->getMessage() ?: '获取授权信息失败';
    }
    include __DIR__ . '/view/popup/agent_config.php';
    return;
}

// ============================================================
// 正常视图
// ============================================================
// 进入授权页前先跟服务端核对一次当前域名的授权状态
// 服务端明确判定未激活 → 自动删除本地记录；网络异常 → 保守保留
LicenseService::revalidateCurrent();

$current = LicenseService::currentLicense();

// var_dump($current);

// 线路列表 + 当前索引
$lines = LicenseService::getAllLines();
$currentLineIndex = LicenseService::currentLineIndex();

$csrfToken = Csrf::token();

if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/license.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/license.php';
    require __DIR__ . '/index.php';
}
