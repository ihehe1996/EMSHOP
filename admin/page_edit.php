<?php
declare(strict_types=1);

require __DIR__ . '/global.php';

adminRequireLogin();

$action = $_GET['_action'] ?? $_POST['_action'] ?? '';

// ========== 保存 ==========
if ($action === 'save') {
    $csrf = (string) ($_POST['csrf_token'] ?? '');
    if (!Csrf::validate($csrf)) {
        Response::error('请求已失效，请刷新页面后重试');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $slug = trim((string) ($_POST['slug'] ?? ''));
    $content = (string) ($_POST['content'] ?? '');
    $status = (int) ($_POST['status'] ?? 1);
    $templateName = trim((string) ($_POST['template_name'] ?? ''));
    $sort = (int) ($_POST['sort'] ?? 100);
    $seoTitle = trim((string) ($_POST['seo_title'] ?? ''));
    $seoKeywords = trim((string) ($_POST['seo_keywords'] ?? ''));
    $seoDescription = trim((string) ($_POST['seo_description'] ?? ''));

    // ---------- 校验 ----------
    if ($title === '') {
        Response::error('页面标题不能为空');
    }
    // slug 为空则自动从标题生成（只保留 a-z / 0-9 / 中划线）
    if ($slug === '') {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9\-]+/u', '-', $slug);
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'page-' . time();
        }
    } else {
        // slug 只允许字母数字/中划线/下划线
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $slug)) {
            Response::error('URL 别名只能包含字母、数字、中划线和下划线');
        }
        $slug = strtolower($slug);
    }
    // 保留字黑名单（避免和系统路由冲突）
    $reserved = ['admin', 'user', 'blog', 'goods', 'order', 'login', 'logout', 'api', 'install', 'content', 'p'];
    if (in_array($slug, $reserved, true)) {
        Response::error('URL 别名 "' . $slug . '" 是系统保留字，请换一个');
    }
    if (PageModel::slugExists($slug, $id, 0)) {
        Response::error('URL 别名 "' . $slug . '" 已被占用');
    }
    // template_name 只允许小写字母数字中划线下划线
    if ($templateName !== '' && !preg_match('/^[a-z0-9_\-]+$/', $templateName)) {
        Response::error('模板名只能包含小写字母、数字、中划线和下划线');
    }

    $data = [
        'title'           => $title,
        'slug'            => $slug,
        'content'         => $content,
        'status'          => $status === 1 ? 1 : 0,
        'template_name'   => $templateName,
        'sort'            => $sort,
        'seo_title'       => $seoTitle,
        'seo_keywords'    => $seoKeywords,
        'seo_description' => $seoDescription,
    ];

    try {
        if ($id) {
            $existing = PageModel::getById($id);
            if (!$existing) {
                Response::error('页面不存在');
            }
            if ((int) $existing['merchant_id'] !== 0) {
                Response::error('无权操作商户页面');
            }
            PageModel::update($id, $data);
            $pageId = $id;
        } else {
            $data['merchant_id'] = 0; // 主站后台创建的页面固定归主站
            $pageId = PageModel::create($data);
        }
        if (!$pageId) {
            Response::error('保存失败');
        }
    } catch (Throwable $e) {
        Response::error('保存失败：' . $e->getMessage());
    }

    Response::success('保存成功', ['id' => $pageId, 'csrf_token' => Csrf::refresh()]);
}

// ========== 默认：弹窗模式渲染编辑页 ==========
$id = (int) ($_GET['id'] ?? 0);
$pageRow = $id ? PageModel::getById($id) : null;
if ($pageRow && (int) $pageRow['merchant_id'] !== 0) {
    $pageRow = null; // 主站后台不展示商户页面
}

$isPopup = Input::get('_popup', '') === '1';
if ($isPopup) {
    include __DIR__ . '/view/popup/page_edit.php';
    return;
}
