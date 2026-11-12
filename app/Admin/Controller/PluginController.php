<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 插件管理（/admin/plugin）。
 *
 * 设计原则：磁盘是真理，启用列表是字符串。
 *   - 磁盘有目录 = 已装
 *   - 启用列表存于 em_config.enabled_plugins（逗号分隔 slug）
 *   - 元数据走 parseHeader 实时读
 *
 * 动作：enable / disable / uninstall / save_config / set_merchant_price / set_merchant_listed
 * GET _action=list 返回插件列表 JSON；?_popup=1&name=xxx 渲染插件设置页。
 *
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class PluginController extends BaseAdminController
{
    /** 当前作用域（主站后台固定 main） */
    private const SCOPE = 'main';

    public function index(): void
    {
        $model = new \App\Model\PluginModel();
        $isPopup = (string) $this->input->get('_popup', '') === '1';

        if ($this->request->isPost()) {
            $this->handlePost($model);
            return;
        }

        if (!$isPopup && (string) $this->input->get('_action', '') === 'list') {
            $this->outputList($model);
            return;
        }

        if ($isPopup) {
            $this->renderPopup($model);
            return;
        }

        $this->renderList();
    }

    /**
     * POST 处理。
     */
    private function handlePost(\App\Model\PluginModel $model): void
    {
        try {
            // 本分支下的动作全部是写操作，且卸载会递归删除插件目录 —— 必须校验 CSRF
            if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
                $this->response->error('请求已失效，请刷新页面后重试');
            }

            $action = (string) $this->input->post('_action', '');
            $scope = self::SCOPE;

            if ($action === 'set_merchant_price') {
                $marketId = (int) $this->input->post('market_id', 0);
                if ($this->input->post('retail_price_micro', null) !== null) {
                    $price = max(0, (int) $this->input->post('retail_price_micro', 0));
                } else {
                    $yuan = (string) $this->input->post('retail_price', '0');
                    $price = (int) round(((float) $yuan) * 1000000);
                    if ($price < 0) {
                        $price = 0;
                    }
                }
                if ($marketId <= 0) {
                    $this->response->error('market_id 非法');
                }

                $marketModel = new \App\Model\AppMarketModel();
                $market = $marketModel->findById($marketId);
                if ($market === null || (string) ($market['type'] ?? '') !== 'plugin') {
                    $this->response->error('分站插件不存在');
                }

                $service = new \App\Service\MainAppPurchaseService();
                $ok = $service->updateRetailPrice($marketId, $price);
                if (!$ok) {
                    $this->response->error('价格未改动或目标不存在');
                }
                $this->response->success('售价已更新', ['csrf_token' => $this->csrf->refresh()]);
            }

            if ($action === 'set_merchant_listed') {
                $marketId = (int) $this->input->post('market_id', 0);
                $listed = (int) $this->input->post('is_listed', 0) === 1;
                if ($marketId <= 0) {
                    $this->response->error('market_id 非法');
                }

                $marketModel = new \App\Model\AppMarketModel();
                $market = $marketModel->findById($marketId);
                if ($market === null || (string) ($market['type'] ?? '') !== 'plugin') {
                    $this->response->error('分站插件不存在');
                }
                $appCode = (string) ($market['app_code'] ?? '');
                $name = trim((string) $this->input->post('name', ''));
                if ($name !== '' && $appCode !== '' && $name !== $appCode) {
                    $this->response->error('参数不匹配,请刷新页面后重试');
                }

                $service = new \App\Service\MainAppPurchaseService();
                $ok = $service->setListed($marketId, $listed);
                if (!$ok) {
                    $this->response->error('状态未改动或目标不存在');
                }
                $this->response->success($listed ? '已上架' : '已下架', ['csrf_token' => $this->csrf->refresh()]);
            }

            $name = trim((string) $this->input->post('name', ''));
            if ($name === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
                $this->response->error('非法插件名');
            }

            if ($action === 'save_config') {
                // 保存配置 —— 优先调插件自带 plugin_setting()；无则把 POST 字段写入 Storage
                if (!$model->existsOnDisk($name)) {
                    $this->response->error('磁盘上未找到该插件');
                }

                $settingFile = EM_ROOT . '/content/plugin/' . $name . '/' . $name . '_setting.php';
                if (is_file($settingFile)) {
                    include_once $settingFile;
                    if (function_exists('plugin_setting')) {
                        // plugin_setting 内部用 Storage 写并自行 Response
                        call_user_func('plugin_setting');
                        return;
                    }
                }

                // 通用兜底：全部 POST 字段（除框架字段）逐个写 Storage
                $storage = \Storage::getInstance($name);
                foreach ($this->input->allPost() as $k => $v) {
                    if (in_array($k, ['_action', 'csrf_token', 'name'], true)) {
                        continue;
                    }
                    $storage->setValue($k, $v);
                }
                $this->response->success('配置已保存', ['csrf_token' => $this->csrf->refresh()]);
            }

            switch ($action) {
                // 启用 —— 每次启用都触发 callback_init，再加入 enabled_plugins 列表
                case 'enable': {
                    if (!$model->existsOnDisk($name)) {
                        $this->response->error('磁盘上未找到该插件');
                    }
                    if ($model->isEnabled($name, $scope)) {
                        $this->response->error('该插件已经是启用状态');
                    }

                    // 触发插件 callback_init 初始化私有数据（建表/资源等）
                    $callbackFile = EM_ROOT . '/content/plugin/' . $name . '/' . $name . '_callback.php';
                    if (is_file($callbackFile)) {
                        include_once $callbackFile;
                        if (function_exists('callback_init')) {
                            call_user_func('callback_init');
                        }
                    }

                    $model->enable($name, $scope);
                    $this->response->success('插件已启用', ['csrf_token' => $this->csrf->refresh()]);
                    break;
                }

                // 禁用 —— 若插件实现 callback_disable，则在禁用时触发
                case 'disable': {
                    if (!$model->isEnabled($name, $scope)) {
                        $this->response->error('该插件已经是禁用状态');
                    }
                    $callbackFile = EM_ROOT . '/content/plugin/' . $name . '/' . $name . '_callback.php';
                    if (is_file($callbackFile)) {
                        include_once $callbackFile;
                        if (function_exists('callback_disable')) {
                            call_user_func('callback_disable');
                        }
                    }
                    $model->disable($name, $scope);
                    $this->response->success('插件已禁用', ['csrf_token' => $this->csrf->refresh()]);
                    break;
                }

                // 卸载：
                //   1) 主站插件：callback_rm 清私有数据 + 清 Storage 配置 + 递归删磁盘目录
                //   2) 分站插件（传 market_id）：删除 em_app_market 记录（含流水）
                case 'uninstall': {
                    $marketId = (int) $this->input->post('market_id', 0);
                    if ($marketId > 0) {
                        $marketModel = new \App\Model\AppMarketModel();
                        $market = $marketModel->findById($marketId);
                        if ($market === null || (string) ($market['type'] ?? '') !== 'plugin') {
                            $this->response->error('分站插件不存在');
                        }
                        $appCode = (string) ($market['app_code'] ?? '');
                        if ($appCode !== '' && $appCode !== $name) {
                            $this->response->error('参数不匹配,请刷新页面后重试');
                        }

                        // 已售出（有购买记录/已扣配额）不允许卸载，避免已购分站数据悬空
                        $soldCount = (new \App\Model\AppPurchaseModel())->countByMarket($marketId);
                        $consumedQuota = (int) ($market['consumed_quota'] ?? 0);
                        if ($soldCount > 0 || $consumedQuota > 0) {
                            $this->response->error('该分站插件已有分站购买记录,请改为下架');
                        }

                        $ok = $marketModel->deleteById($marketId, true);
                        if (!$ok) {
                            $this->response->error('分站插件不存在或已卸载');
                        }

                        $this->response->success('分站插件已卸载', ['csrf_token' => $this->csrf->refresh()]);
                        break;
                    }

                    if (!$model->existsOnDisk($name)) {
                        $this->response->error('磁盘上未找到该插件');
                    }
                    if ($model->isEnabled($name, $scope)) {
                        $this->response->error('该插件正在启用中,请先禁用再卸载');
                    }

                    // 触发插件 callback_rm 清理它自己的私有数据
                    $callbackFile = EM_ROOT . '/content/plugin/' . $name . '/' . $name . '_callback.php';
                    if (is_file($callbackFile)) {
                        include_once $callbackFile;
                        if (function_exists('callback_rm')) {
                            call_user_func('callback_rm');
                        }
                    }

                    $model->uninstall($name, $scope);
                    // 清掉 Storage 里的所有插件配置（per-plugin per-scope）
                    \Storage::getInstance($name)->deleteAllName('YES');

                    // 递归删磁盘目录：卸载 = 彻底移除
                    $dir = EM_ROOT . '/content/plugin/' . $name;
                    if (is_dir($dir) && !$this->rmDir($dir)) {
                        $this->response->error('磁盘文件删除失败,请检查目录权限');
                    }

                    $this->response->success('插件已卸载', ['csrf_token' => $this->csrf->refresh()]);
                    break;
                }

                default:
                    $this->response->error('未知操作');
            }
        } catch (\RuntimeException $e) {
            $this->response->error($e->getMessage());
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage());
        }
    }

    /**
     * 输出插件列表 JSON。
     */
    private function outputList(\App\Model\PluginModel $model): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $merchantCodeSet = [];
        try {
            $rows = $this->db->query(
                'SELECT `app_code` FROM `' . $this->db->prefix() . 'app_market` WHERE `type` = ?',
                ['plugin']
            );
            foreach ($rows as $r) {
                $code = trim((string) ($r['app_code'] ?? ''));
                if ($code !== '') {
                    $merchantCodeSet[$code] = true;
                }
            }
        } catch (\Throwable $e) {
            $merchantCodeSet = [];
        }

        $listScope = (string) $this->input->get('scope', 'main');
        if (!in_array($listScope, ['main', 'merchant'], true)) {
            $listScope = 'main';
        }

        $merchantCount = 0;
        try {
            $row = $this->db->fetchOne(
                'SELECT COUNT(*) AS c FROM `' . $this->db->prefix() . 'app_market` WHERE `type` = ?',
                ['plugin']
            );
            $merchantCount = (int) ($row['c'] ?? 0);
        } catch (\Throwable $e) {
            $merchantCount = 0;
        }

        if ($listScope === 'merchant') {
            try {
                $result = (new \App\Model\AppMarketModel())->paginate([
                    'type'      => 'plugin',
                    'page'      => 1,
                    'page_size' => 100,
                ]);
            } catch (\Throwable $e) {
                $result = ['data' => [], 'total' => 0];
            }

            $data = [];
            foreach ($result['data'] as $row) {
                $appCode = (string) ($row['app_code'] ?? '');
                $data[] = [
                    'name'         => $appCode,
                    'title'        => (string) ($row['title'] ?: $appCode),
                    'version'      => (string) ($row['version'] ?? '1.0.0'),
                    'author'       => '',
                    'author_url'   => '',
                    'description'  => (string) ($row['description'] ?? ''),
                    'category'     => (string) ($row['category'] ?? ''),
                    'icon'         => (string) ($row['cover'] ?? ''),
                    'preview'      => (string) ($row['cover'] ?? ''),
                    'setting_file' => '',
                    'show_file'    => '',
                    'is_installed' => 1,
                    'is_enabled'   => ((int) ($row['is_listed'] ?? 0) === 1),
                    'id'           => (int) ($row['id'] ?? 0),
                    'market_id'    => (int) ($row['id'] ?? 0),
                    'retail_price' => (int) ($row['retail_price'] ?? 0),
                    'cost_price'   => (int) ($row['cost_price'] ?? 0),
                    'is_listed'    => (int) ($row['is_listed'] ?? 0),
                    'is_merchant'  => 1,
                    'config'       => '{}',
                ];
            }

            echo json_encode([
                'code'           => 0,
                'msg'            => '',
                'count'          => count($data),
                'data'           => $data,
                'csrf_token'     => $this->csrf->token(),
                'merchant_count' => $result['total'],
                '_license_error' => '',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $scanned = $model->scanWithStatus(self::SCOPE);
        $latestVersions = \App\Service\AppUpdateService::checkInstalled($scanned, 'plugin');
        $licenseError = '';

        $data = [];
        foreach ($scanned as $name => $info) {
            // 主站插件列表排除"分站货架插件"
            if (isset($merchantCodeSet[$name])) {
                continue;
            }
            $version = (string) ($info['version'] ?? '1.0.0');
            $latest = $latestVersions[$name] ?? null;
            $hasUpdate = (bool) ($latest && !empty($latest['version'])
                && version_compare((string) $latest['version'], $version, '>'));
            $data[] = [
                'name'         => $name,
                'title'        => (string) ($info['title']       ?? $name),
                'version'      => $version,
                'author'       => (string) ($info['author']      ?? ''),
                'author_url'   => (string) ($info['author_url']  ?? ''),
                'description'  => (string) ($info['description'] ?? ''),
                'category'     => (string) ($info['category']    ?? ''),
                'icon'         => (string) ($info['icon']        ?? ''),
                'preview'      => (string) ($info['preview']     ?? ''),
                'setting_file' => (string) ($info['setting_file'] ?? ''),
                'show_file'    => (string) ($info['show_file']    ?? ''),
                'is_installed' => 1,
                'is_enabled'   => !empty($info['is_enabled']),
                'id'           => (int) ($info['state_id'] ?? 0),
                'config'       => '{}',
                'has_update'       => $hasUpdate,
                'latest_version'   => $hasUpdate ? (string) ($latest['version'] ?? '') : '',
                'latest_file_path' => $hasUpdate ? (string) ($latest['file_path'] ?? '') : '',
            ];
        }

        echo json_encode([
            'code'           => 0,
            'msg'            => '',
            'count'          => count($data),
            'data'           => $data,
            'csrf_token'     => $this->csrf->token(),
            'merchant_count' => $merchantCount,
            '_license_error' => $licenseError,
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    /**
     * 插件设置弹窗：header + 插件 setting.php 渲染 + footer。
     */
    private function renderPopup(\App\Model\PluginModel $model): void
    {
        $pluginName = trim((string) $this->input->get('name', ''));
        if ($pluginName === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $pluginName)) {
            exit('非法插件名');
        }
        if (!$model->existsOnDisk($pluginName)) {
            exit('磁盘上未找到该插件');
        }

        $settingFile = EM_ROOT . '/content/plugin/' . $pluginName . '/' . $pluginName . '_setting.php';
        if (!is_file($settingFile)) {
            exit('该插件暂无设置页面');
        }

        $csrfToken = $this->csrf->token();
        $headerInfo = $model->parseHeader(EM_ROOT . '/content/plugin/' . $pluginName . '/' . $pluginName . '.php');
        $titleStr = (string) ($headerInfo['title'] ?? $pluginName);
        $pageTitle = '插件设置：' . htmlspecialchars($titleStr, ENT_QUOTES, 'UTF-8');

        $this->render('popup/header', [
            'pageTitle'            => $pageTitle,
            'popupPluginSaveUrl'   => EM_ADMIN_PATH . '/plugin',
        ]);

        include_once $settingFile;
        if (function_exists('plugin_setting_view')) {
            call_user_func('plugin_setting_view');
        }

        $this->render('popup/footer');
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套旧后台框架壳。
     */
    private function renderList(): void
    {
        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('plugin');
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/plugin.php', $this->user(), get_defined_vars());
    }

    /**
     * 递归删除插件目录。
     */
    private function rmDir(string $path): bool
    {
        if (!is_dir($path)) {
            return @unlink($path);
        }
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (!$this->rmDir($path . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        return @rmdir($path);
    }
}
