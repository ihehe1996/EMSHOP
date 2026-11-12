<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 模板管理（{入口}/template）。
 *
 * 设计原则与后台插件页一致：磁盘=已装，启用状态走 config。
 *   - 磁盘有目录 = 已装（无 install action）
 *   - 主站 PC/Mobile 启用模板存于 em_config.active_template_pc / active_template_mobile
 *   - 模板配置通过 TemplateStorage（em_options 表）存取
 *
 * 动作：activate_pc / activate_mobile / save_config / uninstall
 * GET _action=list 返回模板列表 JSON；?_popup=1&name=xxx 渲染模板设置页。
 *
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class TemplateController extends BaseAdminController
{
    /** 当前作用域（主站后台固定 main） */
    private const SCOPE = 'main';

    public function index(): void
    {
        $model = app(\App\Model\TemplateModel::class);

        if ($this->request->isPost()) {
            $this->handlePost($model);
            return;
        }

        $isPopup = (string) $this->input->get('_popup', '') === '1';

        // AJAX 列表（GET）：扫磁盘 + 左联 DB 状态，并问一次中心"哪些有新版本"
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
     * 启用/配置/卸载。
     */
    private function handlePost(\App\Model\TemplateModel $model): void
    {
        try {
            if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
                $this->response->error('请求已失效,请刷新页面后重试');
            }
            $action = (string) $this->input->post('_action', '');
            $name = $this->validateName((string) $this->input->post('name', ''));

            if (!$model->existsOnDisk($name)) {
                $this->response->error('磁盘上未找到该模板');
            }

            $scope = self::SCOPE;

            switch ($action) {
                // 启用/取消 PC、手机端 —— 已启用再点则取消（写空字符串）
                case 'activate_pc':
                case 'activate_mobile': {
                    $client = $action === 'activate_pc' ? 'pc' : 'mobile';

                    if ($model->isActive($name, $client, $scope)) {
                        $model->clearActiveTheme($client, $scope);
                        $this->response->success($client === 'pc' ? '已取消PC端模板' : '已取消手机端模板',
                            ['csrf_token' => $this->csrf->refresh()]);
                    }

                    $model->setActiveTheme($client, $name, $scope);
                    $this->response->success($client === 'pc' ? 'PC端模板已切换' : '手机端模板已切换',
                        ['csrf_token' => $this->csrf->refresh()]);
                    break;
                }

                case 'save_config': {
                    // 模板配置统一走 TemplateStorage（em_options 表）
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
                    $this->response->error('该模板未提供配置保存逻辑');
                    break;
                }

                // 卸载 —— callback_rm 清模板私有数据 + 清 TemplateStorage 配置 + 递归删磁盘目录
                // 当前启用中（PC 或 Mobile）的模板必须先切到别的再卸载
                case 'uninstall': {
                    if ($model->isActive($name, 'pc', $scope) || $model->isActive($name, 'mobile', $scope)) {
                        $this->response->error('当前启用中的模板不允许卸载,请先切换到其它模板');
                    }

                    // 触发模板 callback_rm 清理它自己的私有数据（建表/资源等）
                    $this->callCallback($model, $name, 'callback_rm');
                    // 清掉 TemplateStorage 里的所有模板配置（per-template per-scope）
                    app(\App\Support\TemplateStorage::class)->forTemplate($name)->deleteAllName('YES');
                    // 递归删磁盘目录
                    $dir = EM_ROOT . '/content/template/' . $name;
                    if (is_dir($dir)) {
                        $this->deleteDirSafe($dir, EM_ROOT . '/content/template');
                    }
                    $this->response->success('模板已卸载', ['csrf_token' => $this->csrf->refresh()]);
                    break;
                }

                default:
                    $this->response->error('未知操作');
            }
        } catch (\RuntimeException $e) {
            $this->response->error($e->getMessage());
        } catch (\Throwable $e) {
            $this->response->error('系统繁忙,请稍后再试');
        }
    }

    /**
     * 输出模板列表 JSON。
     */
    private function outputList(\App\Model\TemplateModel $model): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $scanned = $model->scanWithStatus(self::SCOPE);

        // 已装模板问一次中心"哪些有新版本"；中心不可达时 service 内已降级成空数组
        $latestVersions = app(\App\Service\AppUpdateService::class)->checkInstalled($scanned, 'template');

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
            if ($latest && !empty($latest['version']) && version_compare((string) $latest['version'], $version, '>')) {
                $row['has_update'] = true;
                $row['latest_version'] = (string) $latest['version'];
                $row['latest_file_path'] = (string) ($latest['file_path'] ?? '');
            } else {
                $row['has_update'] = false;
                $row['latest_version'] = '';
                $row['latest_file_path'] = '';
            }

            $data[] = $row;
        }

        echo json_encode([
            'code'           => 0,
            'msg'            => '',
            'count'          => count($data),
            'data'           => $data,
            'csrf_token'     => $this->csrf->token(),
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    /**
     * 模板设置弹窗：header + 模板 setting.php 渲染 + footer。
     */
    private function renderPopup(\App\Model\TemplateModel $model): void
    {
        $name = $this->validateName((string) $this->input->get('name', ''));
        if (!$model->existsOnDisk($name)) {
            exit('磁盘上未找到该模板');
        }
        $settingFile = $model->getSettingFilePath($name);
        if (!is_file($settingFile)) {
            exit('该模板暂无设置页面');
        }

        $csrfToken = $this->csrf->token();
        $scanned = $model->scanTemplates();
        $titleStr = (string) ($scanned[$name]['title'] ?? $name);
        $pageTitle = '模板设置：' . htmlspecialchars($titleStr, ENT_QUOTES, 'UTF-8');

        // 弹窗里的保存地址改为新路由（媒体/上传仍指向旧地址，等对应页面迁移）
        $popupTemplateSaveUrl = EM_ADMIN_PATH . '/template';

        $this->render('popup/header', [
            'pageTitle'              => $pageTitle,
            'popupTemplateSaveUrl'   => $popupTemplateSaveUrl,
        ]);

        include_once $settingFile;
        if (function_exists('template_setting_view')) {
            call_user_func('template_setting_view');
        } elseif (function_exists('plugin_setting_view')) {
            call_user_func('plugin_setting_view');
        } else {
            echo '<div style="padding:16px;color:#6b7280;">该模板未提供可渲染的设置视图。</div>';
        }

        $this->render('popup/footer');
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套后台框架壳。
     */
    private function renderList(): void
    {
        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('template');
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/template.php', $this->user(), get_defined_vars());
    }

    /**
     * 校验模板目录名（避免目录穿越和非法路径操作）。
     */
    private function validateName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new \RuntimeException('模板名称不能为空');
        }
        if (!preg_match('/^[A-Za-z0-9_\-]+$/', $name)) {
            throw new \RuntimeException('模板名称不合法');
        }
        return $name;
    }

    /**
     * 调模板生命周期回调（callback.php 里的指定函数）。
     */
    private function callCallback(\App\Model\TemplateModel $model, string $name, string $callback): void
    {
        $callbackFile = $model->getCallbackFilePath($name);
        if (!is_file($callbackFile)) {
            return;
        }
        include_once $callbackFile;
        if (function_exists($callback)) {
            call_user_func($callback);
        }
    }

    /**
     * 递归删除磁盘目录，校验真实路径必须位于 content/template 内。
     */
    private function deleteDirSafe(string $dir, string $root): void
    {
        $realRoot = realpath($root);
        $realDir = realpath($dir);
        if ($realRoot === false || $realDir === false || strpos($realDir, $realRoot . DIRECTORY_SEPARATOR) !== 0) {
            throw new \RuntimeException('模板目录异常,无法删除');
        }
        foreach (scandir($realDir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $p = $realDir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($p)) {
                $this->deleteDirSafe($p, $root);
                continue;
            }
            if (!unlink($p)) {
                throw new \RuntimeException('删除模板文件失败');
            }
        }
        if (!rmdir($realDir)) {
            throw new \RuntimeException('删除模板目录失败');
        }
    }
}
