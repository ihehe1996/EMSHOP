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
 * 过渡期列表页仍套用旧后台框架壳（admin/view/index.php）。
 */
final class LanguageController extends BaseAdminController
{
    public function index(): void
    {
        if (\Request::isPost()) {
            $this->handlePost();
            return;
        }

        if ((string) \Input::get('_popup', '') === '1') {
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
            $action = (string) \Input::post('_action', '');

            if ($action !== 'list') {
                $csrf = (string) \Input::post('csrf_token', '');
                if (!\Csrf::validate($csrf)) {
                    \Response::error('请求已失效，请刷新页面后重试');
                }
            }

            switch ($action) {
                case 'list':
                    $model = new \LanguageModel();
                    $keyword = trim((string) \Input::post('keyword', ''));

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

                    \Response::success('', [
                        'data' => array_values($languages),
                        'total' => count($languages),
                        'csrf_token' => \Csrf::token(),
                    ]);
                    break;

                case 'create':
                    $name = trim((string) \Input::post('name', ''));
                    if ($name === '') {
                        \Response::error('语言名称不能为空');
                    }
                    if (mb_strlen($name) > 50) {
                        \Response::error('语言名称最多50个字符');
                    }

                    $code = trim((string) \Input::post('code', ''));
                    if ($code === '') {
                        \Response::error('语言代码不能为空');
                    }
                    if (mb_strlen($code) > 20) {
                        \Response::error('语言代码最多20个字符');
                    }

                    $model = new \LanguageModel();
                    if ($model->existsName($name)) {
                        \Response::error('语言名称已被占用');
                    }
                    if ($model->existsCode($code)) {
                        \Response::error('语言代码已被占用');
                    }

                    // 表单里的 is_default / enabled 都是原生 checkbox，未勾选时不提交该字段，
                    // 因此取不到时必须按「否」处理（is_default 是 NOT NULL 列）。
                    $model->create([
                        'name' => $name,
                        'code' => $code,
                        'icon' => trim((string) \Input::post('icon', '')),
                        'enabled' => \Input::post('enabled', 'n') === 'y' ? 'y' : 'n',
                        'is_default' => \Input::post('is_default', 'n') === 'y' ? 'y' : 'n',
                    ]);

                    $csrfToken = \Csrf::refresh();
                    \Response::success('语言创建成功', ['csrf_token' => $csrfToken]);
                    break;

                case 'update':
                    $id = (int) \Input::post('id', 0);
                    if ($id <= 0) {
                        \Response::error('无效的语言ID');
                    }

                    $model = new \LanguageModel();
                    $existing = $model->findById($id);
                    if ($existing === null) {
                        \Response::error('语言不存在');
                    }

                    $name = trim((string) \Input::post('name', ''));
                    if ($name === '') {
                        \Response::error('语言名称不能为空');
                    }
                    if (mb_strlen($name) > 50) {
                        \Response::error('语言名称最多50个字符');
                    }

                    $code = trim((string) \Input::post('code', ''));
                    if ($code === '') {
                        \Response::error('语言代码不能为空');
                    }
                    if (mb_strlen($code) > 20) {
                        \Response::error('语言代码最多20个字符');
                    }

                    if ($model->existsName($name, $id)) {
                        \Response::error('语言名称已被占用');
                    }
                    if ($model->existsCode($code, $id)) {
                        \Response::error('语言代码已被占用');
                    }

                    $isDefault = \Input::post('is_default', 'n') === 'y' ? 'y' : 'n';

                    // enabled 同样必须按「未勾选 = 否」处理。
                    $model->update($id, [
                        'name' => $name,
                        'code' => $code,
                        'icon' => trim((string) \Input::post('icon', '')),
                        'enabled' => \Input::post('enabled', 'n') === 'y' ? 'y' : 'n',
                        'is_default' => $isDefault,
                    ]);

                    $csrfToken = \Csrf::refresh();
                    \Response::success('语言更新成功', ['csrf_token' => $csrfToken]);
                    break;

                case 'delete':
                    $id = (int) \Input::post('id', 0);
                    if ($id <= 0) {
                        \Response::error('无效的语言ID');
                    }

                    $model = new \LanguageModel();
                    if ($model->findById($id) === null) {
                        \Response::error('语言不存在');
                    }

                    // 禁止删除最后一个语言
                    if ($model->count() <= 1) {
                        \Response::error('至少需要保留一种语言');
                    }

                    $model->delete($id);

                    $csrfToken = \Csrf::refresh();
                    \Response::success('删除成功', ['csrf_token' => $csrfToken]);
                    break;

                case 'toggle':
                    $id = (int) \Input::post('id', 0);
                    if ($id <= 0) {
                        \Response::error('无效的语言ID');
                    }

                    $model = new \LanguageModel();
                    $lang = $model->findById($id);
                    if ($lang === null) {
                        \Response::error('语言不存在');
                    }

                    // 禁止禁用最后一个**启用中**的语言。
                    if ($lang['enabled'] === 'y' && count($model->getEnabled()) <= 1) {
                        \Response::error('至少需要保留一种启用语言');
                    }

                    $model->toggle($id);

                    $csrfToken = \Csrf::refresh();
                    \Response::success('状态已更新', ['csrf_token' => $csrfToken]);
                    break;

                default:
                    \Response::error('未知操作');
            }
        } catch (\RuntimeException $e) {
            \Response::error($e->getMessage());
        } catch (\Throwable $e) {
            \Response::error('系统繁忙，请稍后再试');
        }
    }

    /**
     * 添加/编辑弹窗（iframe）。
     */
    private function renderPopup(): void
    {
        $editId = (int) \Input::get('id', 0);
        $editLang = null;

        if ($editId > 0) {
            $editLang = (new \LanguageModel())->findById($editId);
        }

        $this->render('popup/language', [
            'editLang'  => $editLang,
            'pageTitle' => $editLang !== null ? '编辑语言' : '添加语言',
        ]);
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套旧后台框架壳。
     */
    private function renderList(): void
    {
        if (\Request::isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('language');
            echo '</div>';
            return;
        }

        require_once EM_ROOT . '/include/lib/AdminLang.php';

        $user = $this->user();
        $siteName = (string) \Config::get('sitename', 'EMSHOP');
        $languages = (new \LanguageModel())->getEnabled();
        $emServerHardRestartPending = is_file(EM_ROOT . '/.server') && \WorkerHeartbeat::hostAlive();
        $adminContentView = dirname(__DIR__) . '/View/language.php';

        require EM_ROOT . '/admin/view/index.php';
    }
}
