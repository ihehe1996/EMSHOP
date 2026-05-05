<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * 翻译管理控制器。
 *
 * 翻译数据存储于 lang 表，语言数据存储于 language 表。
 */
adminRequireLogin();
$user = $adminUser;
$siteName = Config::get('sitename', 'EMSHOP');


// ============================================================
// POST 请求处理
// ============================================================
if (Request::isPost()) {
    try {
        $action = (string) Input::post('_action', '');

        if ($action !== 'list' && $action !== 'languages') {
            $csrf = (string) Input::post('csrf_token', '');
            if (!Csrf::validate($csrf)) {
                Response::error('请求已失效，请刷新页面后重试');
            }
        }

        switch ($action) {
            case 'languages':
                // 获取所有启用的语言
                $model = new LanguageModel();
                $languages = $model->getEnabled();
                Response::success('', [
                    'data' => $languages,
                    'csrf_token' => Csrf::token(),
                ]);
                break;

            case 'list':
                $langModel = new LangModel();
                $langId = (int) Input::post('langId', 0);
                $keyword = trim((string) Input::post('keyword', ''));
                
                // 分页参数
                $page = max(1, (int) Input::post('page', 1));
                $limit = max(1, min(100, (int) Input::post('limit', 10)));
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

                Response::success('', [
                    'data' => $translations,
                    'total' => $total,
                    'csrf_token' => Csrf::token(),
                ]);
                break;

            case 'batchCreate':
                // 批量创建/更新翻译（一个translate对应所有语言的content）
                $translate = trim((string) Input::post('translate', ''));
                if ($translate === '') {
                    Response::error('翻译语句不能为空');
                }
                if (mb_strlen($translate) > 200) {
                    Response::error('翻译语句最多200个字符');
                }

                $translations = Input::post('translations', []);
                if (!is_array($translations)) {
                    $translations = [];
                }

                $langModel = new LangModel();
                $count = 0;
                foreach ($translations as $langIdStr => $content) {
                    $langId = (int) $langIdStr;
                    if ($langId <= 0) continue;
                    $content = trim((string) $content);
                    if ($content === '') continue;

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

                $csrfToken = Csrf::refresh();
                Response::success('批量创建成功，已处理 ' . $count . ' 条记录', ['csrf_token' => $csrfToken]);
                break;

            case 'update':
                $id = (int) Input::post('id', 0);
                if ($id <= 0) {
                    Response::error('无效的翻译ID');
                }

                $langModel = new LangModel();
                $existing = $langModel->findById($id);
                if ($existing === null) {
                    Response::error('翻译不存在');
                }

                $translate = trim((string) Input::post('translate', ''));
                if ($translate === '') {
                    Response::error('翻译语句不能为空');
                }
                if (mb_strlen($translate) > 200) {
                    Response::error('翻译语句最多200个字符');
                }

                $content = (string) Input::post('content', '');

                if ($langModel->existsLangIdAndTranslate($existing['lang_id'], $translate, $id)) {
                    Response::error('该翻译已存在');
                }

                $langModel->update($id, [
                    'translate' => $translate,
                    'content' => $content,
                ]);

                $csrfToken = Csrf::refresh();
                Response::success('翻译更新成功', ['csrf_token' => $csrfToken]);
                break;

            case 'delete':
                $id = (int) Input::post('id', 0);
                if ($id <= 0) {
                    Response::error('无效的翻译ID');
                }

                $langModel = new LangModel();
                if ($langModel->findById($id) === null) {
                    Response::error('翻译不存在');
                }

                $langModel->delete($id);

                $csrfToken = Csrf::refresh();
                Response::success('删除成功', ['csrf_token' => $csrfToken]);
                break;

            case 'batchSave':
                // 批量保存翻译
                $pairs = Input::post('pairs', []);
                if (!is_array($pairs)) {
                    $pairs = [];
                }

                $langModel = new LangModel();
                $count = 0;
                foreach ($pairs as $id => $value) {
                    $id = (int) $id;
                    if ($id <= 0) continue;
                    $existing = $langModel->findById($id);
                    if ($existing === null) continue;
                    $langModel->update($id, ['content' => (string) $value]);
                    $count++;
                }

                $csrfToken = Csrf::refresh();
                Response::success('批量保存成功', ['csrf_token' => $csrfToken]);
                break;

            case 'batchDelete':
                // 批量删除翻译
                $ids = Input::post('ids', []);
                if (!is_array($ids) || empty($ids)) {
                    Response::error('请选择要删除的翻译');
                }

                $langModel = new LangModel();
                $count = 0;
                foreach ($ids as $id) {
                    $id = (int) $id;
                    if ($id <= 0) continue;
                    $existing = $langModel->findById($id);
                    if ($existing === null) continue;
                    $langModel->delete($id);
                    $count++;
                }

                if ($count === 0) {
                    Response::error('没有可删除的翻译');
                }

                $csrfToken = Csrf::refresh();
                Response::success('成功删除 ' . $count . ' 条翻译', ['csrf_token' => $csrfToken]);
                break;

            default:
                Response::error('未知操作');
        }
    } catch (RuntimeException $e) {
        Response::error($e->getMessage());
    } catch (Throwable $e) {
        Response::error('系统繁忙，请稍后再试');
    }
}

// ============================================================
// 弹窗模式：渲染添加/编辑弹窗
// ============================================================
$isPopup = Input::get('_popup', '') === '1';
if ($isPopup) {
    $editId = (int) Input::get('id', 0);
    $editLang = null;
    $editLangName = null;

    if ($editId > 0) {
        $langModel = new LangModel();
        $editLang = $langModel->findById($editId);
        if ($editLang !== null) {
            $langM = new LanguageModel();
            $langInfo = $langM->findById((int) $editLang['lang_id']);
            $editLangName = $langInfo ? $langInfo['name'] : null;
        }
    }

    $isEdit = $editLang !== null;
    $pageTitle = $isEdit ? '编辑翻译' : '添加翻译';

    // 获取语言列表供弹窗
    $langModel = new LanguageModel();
    $languages = $langModel->getEnabled();

    $esc = function (string $str): string {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    };

    include __DIR__ . '/view/popup/lang.php';
    return;
}

// ============================================================
// 正常模式：渲染完整后台页面
// ============================================================
$csrfToken = Csrf::token();

// 获取语言列表供页面初始化
$langModel = new LanguageModel();
$languages = $langModel->getEnabled();

if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/lang.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/lang.php';
    require __DIR__ . '/index.php';
}
