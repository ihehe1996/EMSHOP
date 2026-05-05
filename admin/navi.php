<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * 导航管理控制器。
 */
adminRequireLogin();
$user = $adminUser;
$siteName = Config::get('sitename', 'EMSHOP');

$model = new NaviModel();

$isPopup = Input::get('_popup', '') === '1';

// POST 请求处理
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
            // 列表 —— 主站后台只看 merchant_id=0 的记录（系统导航 + 主站自定义）
            case 'list':
                $keyword = trim((string) Input::post('keyword', ''));
                $allItems = $model->getAll(0);

                $data = [];
                foreach ($allItems as $item) {
                    if ($keyword !== '' && stripos((string) $item['name'], $keyword) === false) {
                        continue;
                    }
                    $data[] = $item;
                }

                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'code' => 0,
                    'msg'  => '',
                    'data' => array_values($data),
                    'count' => count($data),
                    'csrf_token' => Csrf::token(),
                ], JSON_UNESCAPED_UNICODE);
                return;

            // 创建
            case 'create': {
                $name = trim((string) Input::post('name', ''));
                if ($name === '') {
                    Response::error('导航名称不能为空');
                }

                $type = (string) Input::post('type', 'custom');
                if (!in_array($type, ['custom', 'goods_cat', 'blog_cat', 'page'], true)) {
                    $type = 'custom';
                }

                $parentId = (int) Input::post('parent_id', 0);
                $link = trim((string) Input::post('link', ''));
                $typeRefId = 0;

                // 分类 / 页面 导航自动生成链接
                if ($type === 'goods_cat') {
                    $catId = (int) Input::post('type_ref_id', 0);
                    if ($catId <= 0) {
                        Response::error('请选择商品分类');
                    }
                    $typeRefId = $catId;
                    $link = '?c=goods_list&category_id=' . $catId;
                } elseif ($type === 'blog_cat') {
                    $catId = (int) Input::post('type_ref_id', 0);
                    if ($catId <= 0) {
                        Response::error('请选择博客分类');
                    }
                    $typeRefId = $catId;
                    $link = '?c=blog_list&category_id=' . $catId;
                } elseif ($type === 'page') {
                    $pageId = (int) Input::post('type_ref_id', 0);
                    if ($pageId <= 0) {
                        Response::error('请选择要链接的页面');
                    }
                    $pageRow = PageModel::getById($pageId);
                    if ($pageRow === null || (int) $pageRow['status'] !== 1 || (int) $pageRow['merchant_id'] !== 0) {
                        Response::error('所选页面不存在或不属于主站');
                    }
                    $typeRefId = $pageId;
                    $link = '/p/' . $pageRow['slug'];
                }

                $model->create([
                    'parent_id'   => $parentId,
                    'merchant_id' => 0, // 主站后台创建的自定义导航固定归属主站
                    'name'        => $name,
                    'type'        => $type,
                    'type_ref_id' => $typeRefId,
                    'link'        => $link,
                    'icon'        => (string) Input::post('icon', ''),
                    'target'      => (string) Input::post('target', '_self'),
                    'sort'        => (int) Input::post('sort', 100),
                    'status'      => Input::post('status', '1') === '1' ? 1 : 0,
                ]);

                Response::success('导航创建成功', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            // 更新
            case 'update': {
                $id = (int) Input::post('id', 0);
                if ($id <= 0) {
                    Response::error('无效的导航ID');
                }

                $existing = $model->findById($id);
                if ($existing === null) {
                    Response::error('导航不存在');
                }
                // ACL：主站后台只能改 is_system=1 或 merchant_id=0 的记录
                if ((int) $existing['is_system'] !== 1 && (int) $existing['merchant_id'] !== 0) {
                    Response::error('无权操作商户自定义导航');
                }

                $name = trim((string) Input::post('name', ''));
                if ($name === '') {
                    Response::error('导航名称不能为空');
                }

                $parentId = (int) Input::post('parent_id', 0);

                // 系统导航只能改名称、排序、状态
                if ((int) $existing['is_system'] === 1) {
                    $model->update($id, [
                        'name'   => $name,
                        'sort'   => (int) Input::post('sort', 100),
                        'status' => Input::post('status', '1') === '1' ? 1 : 0,
                        'target' => (string) Input::post('target', '_self'),
                    ]);
                    Response::success('导航更新成功', ['csrf_token' => Csrf::refresh()]);
                    break;
                }

                $type = (string) Input::post('type', 'custom');
                if (!in_array($type, ['custom', 'goods_cat', 'blog_cat', 'page'], true)) {
                    $type = 'custom';
                }

                $link = trim((string) Input::post('link', ''));
                $typeRefId = 0;

                if ($type === 'goods_cat') {
                    $catId = (int) Input::post('type_ref_id', 0);
                    if ($catId <= 0) {
                        Response::error('请选择商品分类');
                    }
                    $typeRefId = $catId;
                    $link = '?c=goods_list&category_id=' . $catId;
                } elseif ($type === 'blog_cat') {
                    $catId = (int) Input::post('type_ref_id', 0);
                    if ($catId <= 0) {
                        Response::error('请选择博客分类');
                    }
                    $typeRefId = $catId;
                    $link = '?c=blog_list&category_id=' . $catId;
                } elseif ($type === 'page') {
                    $pageId = (int) Input::post('type_ref_id', 0);
                    if ($pageId <= 0) {
                        Response::error('请选择要链接的页面');
                    }
                    $pageRow = PageModel::getById($pageId);
                    if ($pageRow === null || (int) $pageRow['status'] !== 1 || (int) $pageRow['merchant_id'] !== 0) {
                        Response::error('所选页面不存在或不属于主站');
                    }
                    $typeRefId = $pageId;
                    $link = '/p/' . $pageRow['slug'];
                }

                $model->update($id, [
                    'parent_id'   => $parentId,
                    'name'        => $name,
                    'type'        => $type,
                    'type_ref_id' => $typeRefId,
                    'link'        => $link,
                    'icon'        => (string) Input::post('icon', ''),
                    'target'      => (string) Input::post('target', '_self'),
                    'sort'        => (int) Input::post('sort', 100),
                    'status'      => Input::post('status', '1') === '1' ? 1 : 0,
                ]);

                Response::success('导航更新成功', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            // 删除
            case 'delete': {
                $id = (int) Input::post('id', 0);
                if ($id <= 0) {
                    Response::error('无效的导航ID');
                }

                $existing = $model->findById($id);
                if ($existing === null) {
                    Response::error('导航不存在');
                }
                if ((int) $existing['is_system'] === 1) {
                    Response::error('系统导航不可删除');
                }
                if ((int) $existing['merchant_id'] !== 0) {
                    Response::error('无权删除商户自定义导航');
                }
                if ($model->hasChildren($id)) {
                    Response::error('请先删除子导航');
                }

                if (!$model->delete($id)) {
                    Response::error('删除失败');
                }

                Response::success('删除成功', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            // 状态切换
            case 'toggle_status': {
                $id = (int) Input::post('id', 0);
                $existing = $model->findById($id);
                if ($existing === null) {
                    Response::error('导航不存在');
                }
                if ((int) $existing['is_system'] !== 1 && (int) $existing['merchant_id'] !== 0) {
                    Response::error('无权操作商户自定义导航');
                }

                $newStatus = ((int) $existing['status'] === 1) ? 0 : 1;
                $model->update($id, ['status' => (string) $newStatus]);

                Response::success($newStatus === 1 ? '已启用' : '已禁用', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            // 打开方式切换
            case 'update_target': {
                $id = (int) Input::post('id', 0);
                $existing = $model->findById($id);
                if ($existing === null) {
                    Response::error('导航不存在');
                }
                if ((int) $existing['is_system'] !== 1 && (int) $existing['merchant_id'] !== 0) {
                    Response::error('无权操作商户自定义导航');
                }
                $target = (string) Input::post('target', '_self');
                if (!in_array($target, ['_self', '_blank'], true)) {
                    $target = '_self';
                }
                $model->update($id, ['target' => $target]);
                Response::success($target === '_blank' ? '已设为新窗口' : '已设为当前窗口', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            // 拖动排序
            case 'sort': {
                $sortJson = (string) Input::post('sort_data', '');
                $sortArr = json_decode($sortJson, true);
                if (!is_array($sortArr) || empty($sortArr)) {
                    Response::error('排序数据无效');
                }
                $sortMap = [];
                foreach ($sortArr as $item) {
                    if (isset($item['id'], $item['sort'])) {
                        $sortMap[(int) $item['id']] = (int) $item['sort'];
                    }
                }
                $model->batchUpdateSort($sortMap);
                Response::success('排序已保存', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            default:
                Response::error('未知操作');
        }
    } catch (RuntimeException $e) {
        Response::error($e->getMessage());
    } catch (Throwable $e) {
        Response::error('系统繁忙，请稍后再试');
    }
}

// 弹窗模式
if ($isPopup) {
    $editId = (int) Input::get('id', 0);
    $editItem = null;
    if ($editId > 0) {
        $editItem = $model->findById($editId);
        // ACL：弹窗只允许编辑主站归属或系统导航
        if ($editItem !== null && (int) $editItem['is_system'] !== 1 && (int) $editItem['merchant_id'] !== 0) {
            $editItem = null;
        }
    }
    $topLevelItems = $model->getTopLevel(0);

    // 加载商品分类 / 博客分类 / 已发布页面 供选择（主站作用域）
    $goodsCategories = (new GoodsCategoryModel())->getAll();
    $blogCategories = (new BlogCategoryModel())->getAll(0);
    $publishedPages = PageModel::getPublishedSimple(0);

    $csrfToken = Csrf::token();
    include __DIR__ . '/view/popup/navi.php';
    return;
}

// 正常模式
$csrfToken = Csrf::token();

if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/navi.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/navi.php';
    require __DIR__ . '/index.php';
}
