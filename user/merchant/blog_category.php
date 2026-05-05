<?php

declare(strict_types=1);

require_once __DIR__ . '/global.php';

/**
 * 商户后台 - 文章分类管理
 *
 * 商户的文章分类与主站完全分离，merchant_id 锁定为本店。
 * 仅支持 2 级分类。
 */
merchantRequireLogin();

$merchantId = (int) $currentMerchant['id'];
$model = new BlogCategoryModel();

if (Request::isPost()) {
    try {
        $action = (string) Input::post('_action', '');
        if ($action !== 'list') {
            if (!Csrf::validate((string) Input::post('csrf_token', ''))) {
                Response::error('请求已失效，请刷新页面后重试');
            }
        }

        switch ($action) {
            case 'list': {
                $rows = $model->getAll($merchantId);
                Response::success('', ['data' => $rows, 'total' => count($rows), 'csrf_token' => Csrf::token()]);
                break;
            }

            case 'save': {
                $id = (int) Input::post('id', 0);
                $name = trim((string) Input::post('name', ''));
                if ($name === '' || mb_strlen($name) > 100) {
                    Response::error('分类名需在 1~100 字符');
                }

                $parentId = max(0, (int) Input::post('parent_id', 0));
                if ($parentId > 0) {
                    $parent = $model->findById($parentId);
                    if ($parent === null
                        || (int) $parent['merchant_id'] !== $merchantId
                        || (int) $parent['parent_id'] !== 0) {
                        Response::error('父分类无效或仅支持二级');
                    }
                }

                $slug = trim((string) Input::post('slug', ''));
                if ($slug !== '' && $model->existsSlug($slug, $id, $merchantId)) {
                    Response::error('别名在本店已被占用');
                }

                $data = [
                    'parent_id'   => $parentId,
                    'name'        => $name,
                    'slug'        => $slug,
                    'description' => (string) Input::post('description', ''),
                    'icon'        => (string) Input::post('icon', ''),
                    'sort'        => (int) Input::post('sort', 100),
                    'status'      => Input::post('status', '1') === '1' ? 1 : 0,
                ];

                if ($id > 0) {
                    $existing = $model->findById($id);
                    if ($existing === null || (int) $existing['merchant_id'] !== $merchantId) {
                        Response::error('分类不存在');
                    }
                    if ($parentId === $id) {
                        Response::error('不能选择自己作为父分类');
                    }
                    if ((int) $existing['parent_id'] === 0 && $parentId > 0 && $model->hasChildren($id)) {
                        Response::error('该分类下有子分类，不能改为子级');
                    }
                    $model->update($id, $data);
                    Response::success('已更新', ['csrf_token' => Csrf::refresh()]);
                } else {
                    $data['merchant_id'] = $merchantId;
                    $newId = $model->create($data);
                    Response::success('已添加', ['id' => $newId, 'csrf_token' => Csrf::refresh()]);
                }
                break;
            }

            case 'delete': {
                $id = (int) Input::post('id', 0);
                $existing = $model->findById($id);
                if ($existing === null || (int) $existing['merchant_id'] !== $merchantId) {
                    Response::error('分类不存在');
                }
                if ($model->hasChildren($id)) {
                    Response::error('请先删除子分类');
                }
                if (!$model->delete($id)) {
                    Response::error('删除失败');
                }
                Response::success('已删除', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            case 'toggle_status': {
                $id = (int) Input::post('id', 0);
                $existing = $model->findById($id);
                if ($existing === null || (int) $existing['merchant_id'] !== $merchantId) {
                    Response::error('分类不存在');
                }
                $next = ((int) $existing['status'] === 1) ? 0 : 1;
                $model->update($id, ['status' => (string) $next]);
                Response::success($next === 1 ? '已启用' : '已禁用', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            default:
                Response::error('未知操作');
        }
    } catch (Throwable $e) {
        Response::error($e->getMessage() ?: '系统繁忙，请稍后再试');
    }
}

merchantRenderPage(__DIR__ . '/view/blog_category.php');
