<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 语言管理（/admin/language）。
 *
 * 与旧 admin/language.php 行为一致：
 *   - _action=list / create / update / delete / toggle
 *   - ?_popup=1 添加/编辑弹窗
 *   - 至少保留一种语言；至少保留一种启用语言
 *
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class LanguageController extends BaseAdminController
{
    public function index(): void
    {
        if ($this->request->isPost()) {
            $this->handlePost();
            return;
        }

        if ((string) $this->input->get('_popup', '') === '1') {
            $this->renderPopup();
            return;
        }

        $this->renderList();
    }

    /**
     * 处理后台 AJAX 动作；除 list 外都校验 CSRF。
     */
    private function handlePost(): void
    {
        try {
            $action = (string) $this->input->post('_action', '');

            if ($action !== 'list') {
                $csrf = (string) $this->input->post('csrf_token', '');
                if (!$this->csrf->validate($csrf)) {
                    $this->response->error('请求已失效，请刷新页面后重试');
                }
            }

            switch ($action) {
                case 'list':
                    $model = new \App\Model\LanguageModel();
                    $keyword = trim((string) $this->input->post('keyword', ''));

                    // 获取所有语言数据
                    $languages = $model->getAll();

                    // 如果有关键词，进行过滤
                    if ($keyword !== '') {
                        $keywordLower = mb_strtolower($keyword, 'UTF-8');
                        $languages = array_filter($languages, function ($item) use ($keywordLower) {
                            $name = mb_strtolower($item['name'] ?? '', 'UTF-8');
                            $code = mb_strtolower($item['code'] ?? '', 'UTF-8');
                            return strpos($name, $keywordLower) !== false || strpos($code, $keywordLower) !== false;
                        });
                    }

                    $this->response->success('', [
                        'data' => array_values($languages),
                        'total' => count($languages),
                        'csrf_token' => $this->csrf->token(),
                    ]);
                    break;

                case 'create':
                    $name = trim((string) $this->input->post('name', ''));
                    if ($name === '') {
                        $this->response->error('语言名称不能为空');
                    }
                    if (mb_strlen($name) > 50) {
                        $this->response->error('语言名称最多50个字符');
                    }

                    $code = trim((string) $this->input->post('code', ''));
                    if ($code === '') {
                        $this->response->error('语言代码不能为空');
                    }
                    if (mb_strlen($code) > 20) {
                        $this->response->error('语言代码最多20个字符');
                    }

                    $model = new \App\Model\LanguageModel();
                    if ($model->existsName($name)) {
                        $this->response->error('语言名称已被占用');
                    }
                    if ($model->existsCode($code)) {
                        $this->response->error('语言代码已被占用');
                    }

                    // 表单里的 is_default / enabled 都是原生 checkbox，未勾选时不提交该字段，
                    // 因此取不到时必须按「否」处理（is_default 是 NOT NULL 列）。
                    $model->create([
                        'name' => $name,
                        'code' => $code,
                        'icon' => trim((string) $this->input->post('icon', '')),
                        'enabled' => $this->input->post('enabled', 'n') === 'y' ? 'y' : 'n',
                        'is_default' => $this->input->post('is_default', 'n') === 'y' ? 'y' : 'n',
                    ]);

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('语言创建成功', ['csrf_token' => $csrfToken]);
                    break;

                case 'update':
                    $id = (int) $this->input->post('id', 0);
                    if ($id <= 0) {
                        $this->response->error('无效的语言ID');
                    }

                    $model = new \App\Model\LanguageModel();
                    $existing = $model->findById($id);
                    if ($existing === null) {
                        $this->response->error('语言不存在');
                    }

                    $name = trim((string) $this->input->post('name', ''));
                    if ($name === '') {
                        $this->response->error('语言名称不能为空');
                    }
                    if (mb_strlen($name) > 50) {
                        $this->response->error('语言名称最多50个字符');
                    }

                    $code = trim((string) $this->input->post('code', ''));
                    if ($code === '') {
                        $this->response->error('语言代码不能为空');
                    }
                    if (mb_strlen($code) > 20) {
                        $this->response->error('语言代码最多20个字符');
                    }

                    if ($model->existsName($name, $id)) {
                        $this->response->error('语言名称已被占用');
                    }
                    if ($model->existsCode($code, $id)) {
                        $this->response->error('语言代码已被占用');
                    }

                    $isDefault = $this->input->post('is_default', 'n') === 'y' ? 'y' : 'n';

                    // enabled 同样必须按「未勾选 = 否」处理。
                    $model->update($id, [
                        'name' => $name,
                        'code' => $code,
                        'icon' => trim((string) $this->input->post('icon', '')),
                        'enabled' => $this->input->post('enabled', 'n') === 'y' ? 'y' : 'n',
                        'is_default' => $isDefault,
                    ]);

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('语言更新成功', ['csrf_token' => $csrfToken]);
                    break;

                case 'delete':
                    $id = (int) $this->input->post('id', 0);
                    if ($id <= 0) {
                        $this->response->error('无效的语言ID');
                    }

                    $model = new \App\Model\LanguageModel();
                    if ($model->findById($id) === null) {
                        $this->response->error('语言不存在');
                    }

                    // 禁止删除最后一个语言
                    if ($model->count() <= 1) {
                        $this->response->error('至少需要保留一种语言');
                    }

                    $model->delete($id);

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('删除成功', ['csrf_token' => $csrfToken]);
                    break;

                case 'toggle':
                    $id = (int) $this->input->post('id', 0);
                    if ($id <= 0) {
                        $this->response->error('无效的语言ID');
                    }

                    $model = new \App\Model\LanguageModel();
                    $lang = $model->findById($id);
                    if ($lang === null) {
                        $this->response->error('语言不存在');
                    }

                    // 禁止禁用最后一个**启用中**的语言。
                    if ($lang['enabled'] === 'y' && count($model->getEnabled()) <= 1) {
                        $this->response->error('至少需要保留一种启用语言');
                    }

                    $model->toggle($id);

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('状态已更新', ['csrf_token' => $csrfToken]);
                    break;

                default:
                    $this->response->error('未知操作');
            }
        } catch (\RuntimeException $e) {
            $this->response->error($e->getMessage());
        } catch (\Throwable $e) {
            $this->response->error('系统繁忙，请稍后再试');
        }
    }

    /**
     * 添加/编辑弹窗（iframe）。
     */
    private function renderPopup(): void
    {
        $editId = (int) $this->input->get('id', 0);
        $editLang = null;

        if ($editId > 0) {
            $editLang = (new \App\Model\LanguageModel())->findById($editId);
        }

        $this->render('popup/language', [
            'editLang'  => $editLang,
            'pageTitle' => $editLang !== null ? '编辑语言' : '添加语言',
        ]);
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套后台框架壳。
     */
    private function renderList(): void
    {
        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('language');
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/language.php', $this->user(), get_defined_vars());
    }
}
