<?php

declare(strict_types=1);

require_once __DIR__ . '/global.php';

/**
 * 商户后台 - 文章编辑（POST 保存 + GET 渲染弹窗）
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
    if ($title === '') Response::error('标题不能为空');
    if (mb_strlen($title) > 255) Response::error('标题最多 255 字符');

    $categoryId = (int) Input::post('category_id', 0);
    if ($categoryId > 0) {
        $cat = (new BlogCategoryModel())->findById($categoryId);
        if ($cat === null || (int) $cat['merchant_id'] !== $merchantId) {
            Response::error('请选择本店的分类');
        }
    }

    $data = [
        'title'       => $title,
        'category_id' => $categoryId,
        'excerpt'     => trim((string) Input::post('excerpt', '')),
        'content'     => (string) Input::post('content', ''),
        'cover_image' => trim((string) Input::post('cover_image', '')),
        'sort'        => (int) Input::post('sort', 0),
        'is_top'      => Input::post('is_top') !== null ? 1 : 0,
        'status'      => (int) Input::post('status', 1) === 1 ? 1 : 0,
    ];

    try {
        if ($id > 0) {
            $existing = BlogModel::getById($id);
            if (!$existing || (int) $existing['merchant_id'] !== $merchantId) {
                Response::error('文章不存在');
            }
            BlogModel::update($id, $data);
            $articleId = $id;
        } else {
            $data['user_id']     = (int) ($frontUser['id'] ?? 0);
            $data['created_by']  = (int) ($frontUser['id'] ?? 0);
            $data['merchant_id'] = $merchantId;
            $articleId = BlogModel::create($data);
        }

        if (!$articleId) Response::error('保存失败');

        // 标签同步（本店标签池）
        $tagsStr = trim((string) Input::post('tags', ''));
        $tagNames = array_filter(array_map('trim', explode(',', $tagsStr)));
        $tagIds = [];
        foreach ($tagNames as $name) {
            if ($name === '') continue;
            $tagIds[] = BlogTagModel::findOrCreate($name, $merchantId);
        }
        BlogTagModel::syncBlogTags($articleId, $tagIds);
        BlogTagModel::refreshAllCounts();
    } catch (Throwable $e) {
        Response::error('保存失败：' . $e->getMessage());
    }

    Response::success('保存成功', ['id' => $articleId, 'csrf_token' => Csrf::refresh()]);
}

// 渲染弹窗（弹窗里独立页面，不走 merchantRenderPage）
$id = (int) Input::get('id', 0);
$article = null;
if ($id > 0) {
    $article = BlogModel::getById($id);
    if ($article && (int) $article['merchant_id'] !== $merchantId) {
        $article = null;
    }
}
$categories = (new BlogCategoryModel())->getAll($merchantId);

if (Input::get('_popup', '') === '1') {
    include __DIR__ . '/view/popup/blog_edit.php';
    return;
}
