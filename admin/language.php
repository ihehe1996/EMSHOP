<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * 语言管理控制器。
 *
 * 语言数据存储于 language 表。
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

        if ($action !== 'list') {
            $csrf = (string) Input::post('csrf_token', '');
            if (!Csrf::validate($csrf)) {
                Response::error('请求已失效，请刷新页面后重试');
            }
        }

        switch ($action) {
            case 'list':
                $model = new LanguageModel();
                $keyword = trim((string) Input::post('keyword', ''));
                
                // 获取所有语言数据
                $languages = $model->getAll();
                
                // 如果有关键词，进行过滤
                if ($keyword !== '') {
                    $keywordLower = mb_strtolower($keyword, 'UTF-8');
                    $languages = array_filter($languages, function($item) use ($keywordLower) {
                        $name = mb_strtolower($item['name'] ?? '', 'UTF-8');
                        $code = mb_strtolower($item['code'] ?? '', 'UTF-8');
                        return strpos($name, $keywordLower) !== false || strpos($code, $keywordLower) !== false;
                    });
                }

                Response::success('', [
                    'data' => array_values($languages),
                    'total' => count($languages),
                    'csrf_token' => Csrf::token(),
                ]);
                break;

            case 'create':
                $name = trim((string) Input::post('name', ''));
                if ($name === '') {
                    Response::error('语言名称不能为空');
                }
                if (mb_strlen($name) > 50) {
                    Response::error('语言名称最多50个字符');
                }

                $code = trim((string) Input::post('code', ''));
                if ($code === '') {
                    Response::error('语言代码不能为空');
                }
                if (mb_strlen($code) > 20) {
                    Response::error('语言代码最多20个字符');
                }

                $model = new LanguageModel();
                if ($model->existsName($name)) {
                    Response::error('语言名称已被占用');
                }
                if ($model->existsCode($code)) {
                    Response::error('语言代码已被占用');
                }

                // 表单里的 is_default / enabled 都是原生 checkbox，未勾选时**根本不会提交**该字段，
                // 因此取不到时必须按「否」处理。
                //
                // 此前 create 分支直接引用了 $isDefault —— 它只在 update 分支被赋值过，
                // 这里从未定义（PHP 8 下为 null），而 is_default 是 NOT NULL 列，
                // 严格模式下 INSERT 直接被 MySQL 拒绝，于是「添加语言」完全不可用。
                $model->create([
                    'name' => $name,
                    'code' => $code,
                    'icon' => trim((string) Input::post('icon', '')),
                    'enabled' => Input::post('enabled', 'n') === 'y' ? 'y' : 'n',
                    'is_default' => Input::post('is_default', 'n') === 'y' ? 'y' : 'n',
                ]);

                $csrfToken = Csrf::refresh();
                Response::success('语言创建成功', ['csrf_token' => $csrfToken]);
                break;

            case 'update':
                $id = (int) Input::post('id', 0);
                if ($id <= 0) {
                    Response::error('无效的语言ID');
                }

                $model = new LanguageModel();
                $existing = $model->findById($id);
                if ($existing === null) {
                    Response::error('语言不存在');
                }

                $name = trim((string) Input::post('name', ''));
                if ($name === '') {
                    Response::error('语言名称不能为空');
                }
                if (mb_strlen($name) > 50) {
                    Response::error('语言名称最多50个字符');
                }

                $code = trim((string) Input::post('code', ''));
                if ($code === '') {
                    Response::error('语言代码不能为空');
                }
                if (mb_strlen($code) > 20) {
                    Response::error('语言代码最多20个字符');
                }

                if ($model->existsName($name, $id)) {
                    Response::error('语言名称已被占用');
                }
                if ($model->existsCode($code, $id)) {
                    Response::error('语言代码已被占用');
                }

                $isDefault = Input::post('is_default', 'n') === 'y' ? 'y' : 'n';

                // enabled 同样必须按「未勾选 = 否」处理。原先默认值是 'y'，
                // 导致后台取消勾选「启用」后语言仍然是启用状态（改了等于没改）。
                $model->update($id, [
                    'name' => $name,
                    'code' => $code,
                    'icon' => trim((string) Input::post('icon', '')),
                    'enabled' => Input::post('enabled', 'n') === 'y' ? 'y' : 'n',
                    'is_default' => $isDefault,
                ]);

                $csrfToken = Csrf::refresh();
                Response::success('语言更新成功', ['csrf_token' => $csrfToken]);
                break;

            case 'delete':
                $id = (int) Input::post('id', 0);
                if ($id <= 0) {
                    Response::error('无效的语言ID');
                }

                $model = new LanguageModel();
                if ($model->findById($id) === null) {
                    Response::error('语言不存在');
                }

                // 禁止删除最后一个语言
                if ($model->count() <= 1) {
                    Response::error('至少需要保留一种语言');
                }

                $model->delete($id);

                $csrfToken = Csrf::refresh();
                Response::success('删除成功', ['csrf_token' => $csrfToken]);
                break;

            case 'toggle':
                $id = (int) Input::post('id', 0);
                if ($id <= 0) {
                    Response::error('无效的语言ID');
                }

                $model = new LanguageModel();
                $lang = $model->findById($id);
                if ($lang === null) {
                    Response::error('语言不存在');
                }

                // 禁止禁用最后一个**启用中**的语言。
                //
                // 原判断用的是 $model->count()（语言总数），所以「3 种语言里 2 种已禁用、
                // 1 种启用」时总数是 3 > 1，守卫放行 —— 于是最后一个启用语言也能被禁掉，
                // 站点将没有任何可用语言。必须查启用数量。
                if ($lang['enabled'] === 'y' && count($model->getEnabled()) <= 1) {
                    Response::error('至少需要保留一种启用语言');
                }

                $model->toggle($id);

                $csrfToken = Csrf::refresh();
                Response::success('状态已更新', ['csrf_token' => $csrfToken]);
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

    if ($editId > 0) {
        $model = new LanguageModel();
        $editLang = $model->findById($editId);
    }

    $isEdit = $editLang !== null;
    $pageTitle = $isEdit ? '编辑语言' : '添加语言';

    $esc = function (string $str): string {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    };

    include __DIR__ . '/view/popup/language.php';
    return;
}

// ============================================================
// 正常模式：渲染完整后台页面
// ============================================================
$csrfToken = Csrf::token();

if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/language.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/language.php';
    require __DIR__ . '/index.php';
}
