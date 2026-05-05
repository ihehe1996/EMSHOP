<?php

declare(strict_types=1);

require_once __DIR__ . '/global.php';

/**
 * 商户后台 - 页面编辑（POST 保存 + GET 渲染弹窗）
 */
merchantRequireLogin();

$merchantId = (int) $currentMerchant['id'];
$action = (string) (Input::post('_action', '') ?: Input::get('_action', ''));

// 保存
if ($action === 'save') {
    if (!Csrf::validate((string) Input::post('csrf_token', ''))) {
        Response::error('请求已失效，请刷新页面后重试');
    }

    $id = (int) Input::post('id', 0);
    $title = trim((string) Input::post('title', ''));
    $slug = trim((string) Input::post('slug', ''));
    $content = (string) Input::post('content', '');
    $status = (int) Input::post('status', 1) === 1 ? 1 : 0;
    $templateName = trim((string) Input::post('template_name', ''));
    $sort = (int) Input::post('sort', 100);
    $seoTitle = trim((string) Input::post('seo_title', ''));
    $seoKeywords = trim((string) Input::post('seo_keywords', ''));
    $seoDescription = trim((string) Input::post('seo_description', ''));

    if ($title === '') Response::error('页面标题不能为空');
    if (mb_strlen($title) > 200) Response::error('标题最多 200 字符');

    if ($slug === '') {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9\-]+/u', '-', $slug);
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'page-' . time();
        }
    } else {
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
    if (PageModel::slugExists($slug, $id, $merchantId)) {
        Response::error('URL 别名 "' . $slug . '" 在本店已被占用');
    }
    if ($templateName !== '' && !preg_match('/^[a-z0-9_\-]+$/', $templateName)) {
        Response::error('模板名只能包含小写字母、数字、中划线和下划线');
    }

    $data = [
        'title'           => $title,
        'slug'            => $slug,
        'content'         => $content,
        'status'          => $status,
        'template_name'   => $templateName,
        'sort'            => $sort,
        'seo_title'       => $seoTitle,
        'seo_keywords'    => $seoKeywords,
        'seo_description' => $seoDescription,
    ];

    try {
        if ($id) {
            $existing = PageModel::getById($id);
            if (!$existing || (int) $existing['merchant_id'] !== $merchantId) {
                Response::error('页面不存在');
            }
            PageModel::update($id, $data);
            $pageId = $id;
        } else {
            $data['merchant_id'] = $merchantId;
            $pageId = PageModel::create($data);
        }
        if (!$pageId) Response::error('保存失败');
    } catch (Throwable $e) {
        Response::error('保存失败：' . $e->getMessage());
    }

    Response::success('保存成功', ['id' => $pageId, 'csrf_token' => Csrf::refresh()]);
}

// 渲染弹窗
$id = (int) Input::get('id', 0);
$pageRow = $id ? PageModel::getById($id) : null;
if ($pageRow && (int) $pageRow['merchant_id'] !== $merchantId) {
    $pageRow = null;
}

if (Input::get('_popup', '') === '1') {
    include __DIR__ . '/view/popup/page_edit.php';
    return;
}
