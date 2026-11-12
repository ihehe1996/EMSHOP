<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 翻译管理（{入口}/lang）。
 *
 * 翻译数据存于 lang 表，语言数据存于 language 表。
 * 行为约定：
 *   - languages / list / batchCreate / update / delete / batchSave / batchDelete
 *   - ?_popup=1 添加/编辑弹窗
 *
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class LangController extends BaseAdminController
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
     * 处理后台 AJAX 动作；list / languages 免 CSRF。
     */
    private function handlePost(): void
    {
        try {
            $action = (string) $this->input->post('_action', '');

            if ($action !== 'list' && $action !== 'languages') {
                $csrf = (string) $this->input->post('csrf_token', '');
                if (!$this->csrf->validate($csrf)) {
                    $this->response->error('请求已失效，请刷新页面后重试');
                }
            }

            switch ($action) {
                case 'languages':
                    // 获取所有启用的语言
                    $model = app(\App\Model\LanguageModel::class);
                    $languages = $model->getEnabled();
                    $this->response->success('', [
                        'data' => $languages,
                        'csrf_token' => $this->csrf->token(),
                    ]);
                    break;

                case 'list':
                    $langModel = app(\App\Model\LangModel::class);
                    $langId = (int) $this->input->post('langId', 0);
                    $keyword = trim((string) $this->input->post('keyword', ''));

                    // 分页参数
                    $page = max(1, (int) $this->input->post('page', 1));
                    $limit = max(1, min(100, (int) $this->input->post('limit', 10)));
                    $offset = ($page - 1) * $limit;

                    // 获取总数
                    $total = $langModel->countWithFilters($langId > 0 ? $langId : null, $keyword);

                    // 获取分页数据
                    $translations = $langModel->getPageWithLanguage(
                        $langId > 0 ? $langId : null,
                        $keyword,
                        $offset,
                        $limit
                    );

                    $this->response->success('', [
                        'data' => $translations,
                        'total' => $total,
                        'csrf_token' => $this->csrf->token(),
                    ]);
                    break;

                case 'batchCreate':
                    // 批量创建/更新翻译（一个translate对应所有语言的content）
                    $translate = trim((string) $this->input->post('translate', ''));
                    if ($translate === '') {
                        $this->response->error('翻译语句不能为空');
                    }
                    if (mb_strlen($translate) > 200) {
                        $this->response->error('翻译语句最多200个字符');
                    }

                    $translations = $this->input->post('translations', []);
                    if (!is_array($translations)) {
                        $translations = [];
                    }

                    $langModel = app(\App\Model\LangModel::class);
                    $count = 0;
                    foreach ($translations as $langIdStr => $content) {
                        $langId = (int) $langIdStr;
                        if ($langId <= 0) {
                            continue;
                        }
                        $content = trim((string) $content);
                        if ($content === '') {
                            continue;
                        }

                        $existing = $langModel->findByLangIdAndTranslate($langId, $translate);
                        if ($existing !== null) {
                            if ($existing['content'] !== $content) {
                                $langModel->update($existing['id'], ['content' => $content]);
                                $count++;
                            }
                        } else {
                            $langModel->create([
                                'lang_id' => $langId,
                                'translate' => $translate,
                                'content' => $content,
                            ]);
                            $count++;
                        }
                    }

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('批量创建成功，已处理 ' . $count . ' 条记录', ['csrf_token' => $csrfToken]);
                    break;

                case 'update':
                    $id = (int) $this->input->post('id', 0);
                    if ($id <= 0) {
                        $this->response->error('无效的翻译ID');
                    }

                    $langModel = app(\App\Model\LangModel::class);
                    $existing = $langModel->findById($id);
                    if ($existing === null) {
                        $this->response->error('翻译不存在');
                    }

                    $translate = trim((string) $this->input->post('translate', ''));
                    if ($translate === '') {
                        $this->response->error('翻译语句不能为空');
                    }
                    if (mb_strlen($translate) > 200) {
                        $this->response->error('翻译语句最多200个字符');
                    }

                    $content = (string) $this->input->post('content', '');

                    if ($langModel->existsLangIdAndTranslate($existing['lang_id'], $translate, $id)) {
                        $this->response->error('该翻译已存在');
                    }

                    $langModel->update($id, [
                        'translate' => $translate,
                        'content' => $content,
                    ]);

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('翻译更新成功', ['csrf_token' => $csrfToken]);
                    break;

                case 'delete':
                    $id = (int) $this->input->post('id', 0);
                    if ($id <= 0) {
                        $this->response->error('无效的翻译ID');
                    }

                    $langModel = app(\App\Model\LangModel::class);
                    if ($langModel->findById($id) === null) {
                        $this->response->error('翻译不存在');
                    }

                    $langModel->delete($id);

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('删除成功', ['csrf_token' => $csrfToken]);
                    break;

                case 'batchSave':
                    // 批量保存翻译
                    $pairs = $this->input->post('pairs', []);
                    if (!is_array($pairs)) {
                        $pairs = [];
                    }

                    $langModel = app(\App\Model\LangModel::class);
                    $count = 0;
                    foreach ($pairs as $id => $value) {
                        $id = (int) $id;
                        if ($id <= 0) {
                            continue;
                        }
                        $existing = $langModel->findById($id);
                        if ($existing === null) {
                            continue;
                        }
                        $langModel->update($id, ['content' => (string) $value]);
                        $count++;
                    }

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('批量保存成功', ['csrf_token' => $csrfToken]);
                    break;

                case 'batchDelete':
                    // 批量删除翻译
                    $ids = $this->input->post('ids', []);
                    if (!is_array($ids) || empty($ids)) {
                        $this->response->error('请选择要删除的翻译');
                    }

                    $langModel = app(\App\Model\LangModel::class);
                    $count = 0;
                    foreach ($ids as $id) {
                        $id = (int) $id;
                        if ($id <= 0) {
                            continue;
                        }
                        $existing = $langModel->findById($id);
                        if ($existing === null) {
                            continue;
                        }
                        $langModel->delete($id);
                        $count++;
                    }

                    if ($count === 0) {
                        $this->response->error('没有可删除的翻译');
                    }

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('成功删除 ' . $count . ' 条翻译', ['csrf_token' => $csrfToken]);
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
        $editLangName = null;

        if ($editId > 0) {
            $langModel = app(\App\Model\LangModel::class);
            $editLang = $langModel->findById($editId);
            if ($editLang !== null) {
                $langM = app(\App\Model\LanguageModel::class);
                $langInfo = $langM->findById((int) $editLang['lang_id']);
                $editLangName = $langInfo ? $langInfo['name'] : null;
            }
        }

        $this->render('popup/lang', [
            'editLang'     => $editLang,
            'editLangName' => $editLangName,
            'languages'    => app(\App\Model\LanguageModel::class)->getEnabled(),
            'pageTitle'    => $editLang !== null ? '编辑翻译' : '添加翻译',
        ]);
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套后台框架壳。
     */
    private function renderList(): void
    {
        $languages = app(\App\Model\LanguageModel::class)->getEnabled();

        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('lang', ['languages' => $languages]);
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/lang.php', $this->user(), get_defined_vars());
    }
}
