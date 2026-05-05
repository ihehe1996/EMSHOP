<?php

declare(strict_types=1);

require_once __DIR__ . '/global.php';

/**
 * 主站后台 · 模板管理控制器(配置启用版)。
 *
 * 设计原则与 admin/plugin.php 一致:磁盘=已装,启用状态走 config / em_merchant 字段。
 *   - 磁盘有目录 = 已装(无 install action)
 *   - 主站 PC/Mobile 启用模板存于 em_config.active_template_pc / active_template_mobile
 *   - 模板配置通过 TemplateStorage(em_options 表)存取
 *   - 元数据(title/version/author/template_url/description/preview/...)走 parseHeader 实时读
 *
 * 保留 actions:activate_pc / activate_mobile / save_config / uninstall(=清数据+删磁盘)
 * 删除 actions:install(磁盘=装) / delete(已并入 uninstall)
 */
adminRequireLogin();
$user      = $adminUser;
$siteName  = Config::get('sitename', 'EMSHOP');
$model     = new TemplateModel();
$scope     = 'main';
$isPopup   = Input::get('_popup', '') === '1';

/**
 * 校验模板目录名(避免目录穿越和非法路径操作)。
 */
function templateValidateName(string $name): string
{
    $name = trim($name);
    if ($name === '') throw new RuntimeException('模板名称不能为空');
    if (!preg_match('/^[A-Za-z0-9_\-]+$/', $name)) throw new RuntimeException('模板名称不合法');
    return $name;
}

/**
 * 调模板生命周期回调(callback.php 里的指定函数)。
 */
function templateCallCallback(TemplateModel $model, string $name, string $callback): void
{
    $callbackFile = $model->getCallbackFilePath($name);
    if (!is_file($callbackFile)) return;
    include_once $callbackFile;
    if (function_exists($callback)) call_user_func($callback);
}

/**
 * 递归删除磁盘目录,校验真实路径必须位于 content/template 内。
 */
function templateDeleteDirSafe(string $dir, string $root): void
{
    $realRoot = realpath($root);
    $realDir  = realpath($dir);
    if ($realRoot === false || $realDir === false || strpos($realDir, $realRoot . DIRECTORY_SEPARATOR) !== 0) {
        throw new RuntimeException('模板目录异常,无法删除');
    }
    foreach (scandir($realDir) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $p = $realDir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($p)) {
            templateDeleteDirSafe($p, $root);
            continue;
        }
        if (!unlink($p)) throw new RuntimeException('删除模板文件失败');
    }
    if (!rmdir($realDir)) throw new RuntimeException('删除模板目录失败');
}

// ============================================================
// POST 处理
// ============================================================
if (Request::isPost()) {
    try {
        if (!Csrf::validate((string) Input::post('csrf_token', ''))) {
            Response::error('请求已失效,请刷新页面后重试');
        }
        $action = (string) Input::post('_action', '');
        $name   = templateValidateName((string) Input::post('name', ''));

        if (!$model->existsOnDisk($name)) {
            Response::error('磁盘上未找到该模板');
        }

        switch ($action) {
            // 启用/取消 PC、手机端 —— 已启用再点则取消(写空字符串)
            // 不再做"首次启用"判断,callback_init 由模板作者保证幂等(对齐插件方向 3)
            case 'activate_pc':
            case 'activate_mobile': {
                $client = $action === 'activate_pc' ? 'pc' : 'mobile';

                if ($model->isActive($name, $client, $scope)) {
                    $model->clearActiveTheme($client, $scope);
                    Response::success($client === 'pc' ? '已取消PC端模板' : '已取消手机端模板',
                        ['csrf_token' => Csrf::refresh()]);
                }

                $model->setActiveTheme($client, $name, $scope);
                Response::success($client === 'pc' ? 'PC端模板已切换' : '手机端模板已切换',
                    ['csrf_token' => Csrf::refresh()]);
                break;
            }

            case 'save_config': {
                // 模板配置统一走 TemplateStorage(em_options 表)
                // 调用 setting.php 里的 template_setting() / plugin_setting() —— 由模板作者实现写入
                $settingFile = $model->getSettingFilePath($name);
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

            // 卸载 —— callback_rm 清模板私有数据 + 清 TemplateStorage 配置 + 递归删磁盘目录
            // 当前启用中(PC 或 Mobile)的模板必须先切到别的再卸载
            case 'uninstall': {
                if ($model->isActive($name, 'pc', $scope) || $model->isActive($name, 'mobile', $scope)) {
                    Response::error('当前启用中的模板不允许卸载,请先切换到其它模板');
                }

                // 触发模板 callback_rm 清理它自己的私有数据(建表/资源等)
                templateCallCallback($model, $name, 'callback_rm');
                // 清掉 TemplateStorage 里的所有模板配置(per-template per-scope)
                TemplateStorage::getInstance($name)->deleteAllName('YES');
                // 递归删磁盘目录
                $dir = EM_ROOT . '/content/template/' . $name;
                if (is_dir($dir)) {
                    templateDeleteDirSafe($dir, EM_ROOT . '/content/template');
                }
                Response::success('模板已卸载', ['csrf_token' => Csrf::refresh()]);
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
// AJAX 列表 —— 扫磁盘 + 左联 DB 状态,经 AppLicenseGuard 过滤
// ============================================================
if (!$isPopup && Input::get('_action', '') === 'list') {
    header('Content-Type: application/json; charset=utf-8');

    $scanned = $model->scanWithStatus($scope);


    // 已装模板批量查最新版本 —— 用磁盘 header 当前版本对比远端
    $latestVersions = [];
    $names = array_keys($scanned);
    if ($names !== []) {
        try {
            $latestVersions = LicenseClient::mainAppLatestVersions($names, 'template');
        } catch (Throwable $e) {
            // 中心不可达静默降级:已装信息仍可正常展示
        }
    }

    $data = [];
    foreach ($scanned as $name => $info) {
        $version = (string) ($info['version'] ?? '1.0.0');
        $row = [
            'name'             => $name,
            'title'            => (string) ($info['title']        ?? $name),
            'version'          => $version,
            'author'           => (string) ($info['author']       ?? ''),
            'author_url'       => (string) ($info['author_url']   ?? ''),
            'template_url'     => (string) ($info['template_url'] ?? ''),
            'description'      => (string) ($info['description']  ?? ''),
            'preview'          => (string) ($info['preview']      ?? ''),
            'setting_file'     => $info['has_setting'] ? 'setting.php' : '',
            'plugin_file'      => (string) ($info['plugin_file']   ?? ''),
            'callback_file'    => (string) ($info['callback_file'] ?? ''),
            'is_installed'     => 1, // 磁盘有就是已装
            'is_active_pc'     => !empty($info['is_active_pc']),
            'is_active_mobile' => !empty($info['is_active_mobile']),
            'id'               => (int) ($info['state_id'] ?? 0),
            'config'           => (string) ($info['config'] ?? '{}'),
        ];

        // 比对远端版本判定有无更新
        $latest = $latestVersions[$name] ?? null;
        if ($latest && !empty($latest['version']) && (string) $latest['version'] > $version) {
            $row['has_update']       = true;
            $row['latest_version']   = (string) $latest['version'];
            $row['latest_file_path'] = (string) ($latest['file_path'] ?? '');
        } else {
            $row['has_update']       = false;
            $row['latest_version']   = '';
            $row['latest_file_path'] = '';
        }

        $data[] = $row;
    }

    echo json_encode([
        'code'           => 0,
        'msg'            => '',
        'count'          => count($data),
        'data'           => $data,
        'csrf_token'     => Csrf::token(),
    ], JSON_UNESCAPED_UNICODE);
    return;
}

// ============================================================
// 弹窗模式:模板设置页
// ============================================================
if ($isPopup) {
    $name = templateValidateName((string) Input::get('name', ''));
    if (!$model->existsOnDisk($name)) exit('磁盘上未找到该模板');
    $settingFile = $model->getSettingFilePath($name);
    if (!is_file($settingFile)) exit('该模板暂无设置页面');

    $csrfToken = Csrf::token();
    $scanned   = $model->scanTemplates();
    $titleStr  = (string) ($scanned[$name]['title'] ?? $name);
    $pageTitle = '模板设置:' . htmlspecialchars($titleStr, ENT_QUOTES, 'UTF-8');

    include __DIR__ . '/view/popup/header.php';
    include_once $settingFile;
    if (function_exists('template_setting_view'))        call_user_func('template_setting_view');
    elseif (function_exists('plugin_setting_view'))      call_user_func('plugin_setting_view');
    else                                                  echo '<div style="padding:16px;color:#6b7280;">该模板未提供可渲染的设置视图。</div>';
    include __DIR__ . '/view/popup/footer.php';
    return;
}

// ============================================================
// 普通页面渲染
// ============================================================
$csrfToken = Csrf::token();
if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/template.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/template.php';
    require __DIR__ . '/index.php';
}
