<?php

declare(strict_types=1);

require_once __DIR__ . '/global.php';

/**
 * 商户后台 - 导航管理
 *
 * 商户只能维护本店自定义导航（is_system=0 + merchant_id=本店）。
 * 系统导航（is_system=1）由主站统一管理，本店看得到但改不了。
 *
 * 支持类型：
 *   custom    自定义链接（手输 URL）
 *   blog_cat  本店博客分类（type_ref_id 指向 em_blog_category.id 且 merchant_id 必须本店）
 *   page      本店页面（type_ref_id 指向 em_page.id 且 merchant_id 必须本店）
 *
 * 系统导航（is_system=1）只能"隐藏 / 显示"——不能改名、不能删除、不能改状态。
 */
merchantRequireLogin();

$merchantId = (int) $currentMerchant['id'];
$model = new NaviModel();

if (Request::isPost()) {
    try {
        $action = (string) Input::post('_action', '');
        if ($action !== 'list') {
            if (!Csrf::validate((string) Input::post('csrf_token', ''))) {
                Response::error('请求已失效，请刷新页面后重试');
            }
        }

        switch ($action) {
            // 列表 —— 系统导航（is_system=1）+ 本店自定义导航
            case 'list': {
                $rows = $model->getAll($merchantId);
                Response::success('', ['data' => $rows, 'total' => count($rows), 'csrf_token' => Csrf::token()]);
                break;
            }

            // 创建（仅自定义类型）
            case 'create': {
                $name = trim((string) Input::post('name', ''));
                if ($name === '' || mb_strlen($name) > 100) {
                    Response::error('导航名称需在 1~100 字符');
                }
                $type = (string) Input::post('type', 'custom');
                if (!in_array($type, ['custom', 'blog_cat', 'page'], true)) {
                    $type = 'custom';
                }

                $parentId = (int) Input::post('parent_id', 0);
                if ($parentId > 0) {
                    // 父导航必须是本店或系统的顶级
                    $parent = $model->findById($parentId);
                    if ($parent === null
                        || (int) $parent['parent_id'] !== 0
                        || ((int) $parent['is_system'] !== 1 && (int) $parent['merchant_id'] !== $merchantId)) {
                        Response::error('父导航无效');
                    }
                }

                $link = trim((string) Input::post('link', ''));
                $typeRefId = 0;

                if ($type === 'blog_cat') {
                    $catId = (int) Input::post('type_ref_id', 0);
                    if ($catId <= 0) Response::error('请选择博客分类');
                    $cat = (new BlogCategoryModel())->findById($catId);
                    if ($cat === null || (int) $cat['merchant_id'] !== $merchantId) {
                        Response::error('博客分类无效或不属于本店');
                    }
                    $typeRefId = $catId;
                    $link = '?c=blog_list&category_id=' . $catId;
                } elseif ($type === 'page') {
                    $pageId = (int) Input::post('type_ref_id', 0);
                    if ($pageId <= 0) Response::error('请选择页面');
                    $pageRow = PageModel::getById($pageId);
                    if ($pageRow === null
                        || (int) $pageRow['status'] !== 1
                        || (int) $pageRow['merchant_id'] !== $merchantId) {
                        Response::error('页面不存在或不属于本店或未发布');
                    }
                    $typeRefId = $pageId;
                    $link = '/p/' . $pageRow['slug'];
                } elseif ($link === '') {
                    Response::error('请输入链接地址');
                }

                $model->create([
                    'parent_id'   => $parentId,
                    'merchant_id' => $merchantId,
                    'name'        => $name,
                    'type'        => $type,
                    'type_ref_id' => $typeRefId,
                    'link'        => $link,
                    'icon'        => (string) Input::post('icon', ''),
                    'target'      => in_array((string) Input::post('target', '_self'), ['_self', '_blank'], true)
                        ? (string) Input::post('target', '_self') : '_self',
                    'sort'        => (int) Input::post('sort', 100),
                    'status'      => Input::post('status', '1') === '1' ? 1 : 0,
                    'is_system'   => 0, // 商户后台始终非系统
                ]);
                Response::success('已添加', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            // 更新
            case 'update': {
                $id = (int) Input::post('id', 0);
                $existing = $model->findById($id);
                if ($existing === null) Response::error('导航不存在');
                if ((int) $existing['is_system'] === 1) {
                    Response::error('系统导航不可修改');
                }
                if ((int) $existing['merchant_id'] !== $merchantId) {
                    Response::error('无权操作其它店铺的导航');
                }

                $name = trim((string) Input::post('name', ''));
                if ($name === '' || mb_strlen($name) > 100) {
                    Response::error('导航名称需在 1~100 字符');
                }

                $type = (string) Input::post('type', 'custom');
                if (!in_array($type, ['custom', 'blog_cat', 'page'], true)) {
                    $type = 'custom';
                }

                $parentId = (int) Input::post('parent_id', 0);
                if ($parentId > 0) {
                    $parent = $model->findById($parentId);
                    if ($parent === null
                        || (int) $parent['parent_id'] !== 0
                        || ((int) $parent['is_system'] !== 1 && (int) $parent['merchant_id'] !== $merchantId)) {
                        Response::error('父导航无效');
                    }
                    if ($parentId === $id) {
                        Response::error('不能选择自己作为父导航');
                    }
                }

                $link = trim((string) Input::post('link', ''));
                $typeRefId = 0;

                if ($type === 'blog_cat') {
                    $catId = (int) Input::post('type_ref_id', 0);
                    if ($catId <= 0) Response::error('请选择博客分类');
                    $cat = (new BlogCategoryModel())->findById($catId);
                    if ($cat === null || (int) $cat['merchant_id'] !== $merchantId) {
                        Response::error('博客分类无效或不属于本店');
                    }
                    $typeRefId = $catId;
                    $link = '?c=blog_list&category_id=' . $catId;
                } elseif ($type === 'page') {
                    $pageId = (int) Input::post('type_ref_id', 0);
                    if ($pageId <= 0) Response::error('请选择页面');
                    $pageRow = PageModel::getById($pageId);
                    if ($pageRow === null
                        || (int) $pageRow['status'] !== 1
                        || (int) $pageRow['merchant_id'] !== $merchantId) {
                        Response::error('页面不存在或不属于本店或未发布');
                    }
                    $typeRefId = $pageId;
                    $link = '/p/' . $pageRow['slug'];
                } elseif ($link === '') {
                    Response::error('请输入链接地址');
                }

                $model->update($id, [
                    'parent_id'   => $parentId,
                    'name'        => $name,
                    'type'        => $type,
                    'type_ref_id' => $typeRefId,
                    'link'        => $link,
                    'icon'        => (string) Input::post('icon', ''),
                    'target'      => in_array((string) Input::post('target', '_self'), ['_self', '_blank'], true)
                        ? (string) Input::post('target', '_self') : '_self',
                    'sort'        => (int) Input::post('sort', 100),
                    'status'      => Input::post('status', '1') === '1' ? 1 : 0,
                ]);
                Response::success('已保存', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            // 删除
            case 'delete': {
                $id = (int) Input::post('id', 0);
                $existing = $model->findById($id);
                if ($existing === null) Response::error('导航不存在');
                if ((int) $existing['is_system'] === 1) Response::error('系统导航不可删除');
                if ((int) $existing['merchant_id'] !== $merchantId) {
                    Response::error('无权操作其它店铺的导航');
                }
                if ($model->hasChildren($id)) {
                    Response::error('请先删除子导航');
                }
                if (!$model->delete($id)) {
                    Response::error('删除失败');
                }
                Response::success('已删除', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            // 状态切换（仅自定义导航）
            case 'toggle_status': {
                $id = (int) Input::post('id', 0);
                $existing = $model->findById($id);
                if ($existing === null) Response::error('导航不存在');
                if ((int) $existing['is_system'] === 1) {
                    Response::error('系统导航请使用"在本店隐藏"');
                }
                if ((int) $existing['merchant_id'] !== $merchantId) {
                    Response::error('无权操作其它店铺的导航');
                }
                $next = ((int) $existing['status'] === 1) ? 0 : 1;
                $model->update($id, ['status' => (string) $next]);
                Response::success($next === 1 ? '已启用' : '已禁用', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            // 隐藏 / 显示 系统导航（不改主站记录，仅写本店"隐藏名单"）
            case 'toggle_hide_system': {
                $id = (int) Input::post('id', 0);
                $existing = $model->findById($id);
                if ($existing === null) Response::error('导航不存在');
                if ((int) $existing['is_system'] !== 1) {
                    Response::error('该操作仅对系统导航生效');
                }
                $next = $model->toggleHideSystem($merchantId, $id);
                Response::success($next === 1 ? '已在本店隐藏' : '已在本店恢复显示', [
                    'is_hidden' => $next,
                    'csrf_token' => Csrf::refresh(),
                ]);
                break;
            }

            default:
                Response::error('未知操作');
        }
    } catch (Throwable $e) {
        Response::error($e->getMessage() ?: '系统繁忙，请稍后再试');
    }
}

// 渲染页面 —— 提前把本店博客分类、页面传给 view（用于"博客分类 / 页面"类型下拉）
$blogCategories = (new BlogCategoryModel())->getAll($merchantId);
$publishedPages = PageModel::getPublishedSimple($merchantId);

merchantRenderPage(__DIR__ . '/view/navi.php', [
    'blogCategories' => $blogCategories,
    'publishedPages' => $publishedPages,
]);
