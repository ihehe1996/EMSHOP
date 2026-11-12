<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 导航管理（/admin/navi）。
 *
 * 与旧 admin/navi.php 行为一致：
 *   - POST 处理列表 / 创建 / 更新 / 删除 / 状态与打开方式切换 / 拖动排序
 *   - ?_popup=1 渲染编辑弹窗
 *   - 普通请求渲染列表页
 *
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php），
 * 等后台公共布局迁移到 app/Admin 后再替换。
 */
final class NaviController extends BaseAdminController
{
    public function index(): void
    {
        $model = new \App\Model\NaviModel();

        if ($this->request->isPost()) {
            $this->handlePost($model);
            return;
        }

        if ((string) $this->input->get('_popup', '') === '1') {
            $this->renderPopup($model);
            return;
        }

        $this->renderList();
    }

    /**
     * 处理后台 AJAX 动作；所有写操作校验 CSRF。
     */
    private function handlePost(\App\Model\NaviModel $model): void
    {
        try {
            $action = (string) $this->input->post('_action', '');

            if ($action !== 'list') {
                $csrf = (string) $this->input->post('csrf_token', '');
                if (!$this->csrf->validate($csrf)) {
                    $this->response->error('请求已失效，请刷新页面后重试');
                }
            }

            switch ($action) {
                // 列表 —— 主站后台只看 merchant_id=0 的记录（系统导航 + 主站自定义）
                case 'list':
                    $keyword = trim((string) $this->input->post('keyword', ''));
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
                        'csrf_token' => $this->csrf->token(),
                    ], JSON_UNESCAPED_UNICODE);
                    return;

                // 创建
                case 'create': {
                    $name = trim((string) $this->input->post('name', ''));
                    if ($name === '') {
                        $this->response->error('导航名称不能为空');
                    }

                    $type = (string) $this->input->post('type', 'custom');
                    if (!in_array($type, ['custom', 'goods_cat', 'blog_cat', 'page'], true)) {
                        $type = 'custom';
                    }

                    $parentId = (int) $this->input->post('parent_id', 0);
                    $link = trim((string) $this->input->post('link', ''));
                    $typeRefId = 0;

                    // 分类 / 页面 导航自动生成链接
                    if ($type === 'goods_cat') {
                        $catId = (int) $this->input->post('type_ref_id', 0);
                        if ($catId <= 0) {
                            $this->response->error('请选择商品分类');
                        }
                        $typeRefId = $catId;
                        $link = '?c=goods_list&category_id=' . $catId;
                    } elseif ($type === 'blog_cat') {
                        $catId = (int) $this->input->post('type_ref_id', 0);
                        if ($catId <= 0) {
                            $this->response->error('请选择博客分类');
                        }
                        $typeRefId = $catId;
                        $link = '?c=blog_list&category_id=' . $catId;
                    } elseif ($type === 'page') {
                        $pageId = (int) $this->input->post('type_ref_id', 0);
                        if ($pageId <= 0) {
                            $this->response->error('请选择要链接的页面');
                        }
                        $pageRow = app(\App\Model\PageModel::class)->getById($pageId);
                        if ($pageRow === null || (int) $pageRow['status'] !== 1 || (int) $pageRow['merchant_id'] !== 0) {
                            $this->response->error('所选页面不存在或不属于主站');
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
                        'icon'        => (string) $this->input->post('icon', ''),
                        'target'      => (string) $this->input->post('target', '_self'),
                        'sort'        => (int) $this->input->post('sort', 100),
                        'status'      => $this->input->post('status', '1') === '1' ? 1 : 0,
                    ]);

                    $this->response->success('导航创建成功', ['csrf_token' => $this->csrf->refresh()]);
                    break;
                }

                // 更新
                case 'update': {
                    $id = (int) $this->input->post('id', 0);
                    if ($id <= 0) {
                        $this->response->error('无效的导航ID');
                    }

                    $existing = $model->findById($id);
                    if ($existing === null) {
                        $this->response->error('导航不存在');
                    }
                    // ACL：主站后台只能改 is_system=1 或 merchant_id=0 的记录
                    if ((int) $existing['is_system'] !== 1 && (int) $existing['merchant_id'] !== 0) {
                        $this->response->error('无权操作商户自定义导航');
                    }

                    $name = trim((string) $this->input->post('name', ''));
                    if ($name === '') {
                        $this->response->error('导航名称不能为空');
                    }

                    $parentId = (int) $this->input->post('parent_id', 0);

                    // 系统导航只能改名称、排序、状态
                    if ((int) $existing['is_system'] === 1) {
                        $model->update($id, [
                            'name'   => $name,
                            'sort'   => (int) $this->input->post('sort', 100),
                            'status' => $this->input->post('status', '1') === '1' ? 1 : 0,
                            'target' => (string) $this->input->post('target', '_self'),
                        ]);
                        $this->response->success('导航更新成功', ['csrf_token' => $this->csrf->refresh()]);
                        break;
                    }

                    $type = (string) $this->input->post('type', 'custom');
                    if (!in_array($type, ['custom', 'goods_cat', 'blog_cat', 'page'], true)) {
                        $type = 'custom';
                    }

                    $link = trim((string) $this->input->post('link', ''));
                    $typeRefId = 0;

                    if ($type === 'goods_cat') {
                        $catId = (int) $this->input->post('type_ref_id', 0);
                        if ($catId <= 0) {
                            $this->response->error('请选择商品分类');
                        }
                        $typeRefId = $catId;
                        $link = '?c=goods_list&category_id=' . $catId;
                    } elseif ($type === 'blog_cat') {
                        $catId = (int) $this->input->post('type_ref_id', 0);
                        if ($catId <= 0) {
                            $this->response->error('请选择博客分类');
                        }
                        $typeRefId = $catId;
                        $link = '?c=blog_list&category_id=' . $catId;
                    } elseif ($type === 'page') {
                        $pageId = (int) $this->input->post('type_ref_id', 0);
                        if ($pageId <= 0) {
                            $this->response->error('请选择要链接的页面');
                        }
                        $pageRow = app(\App\Model\PageModel::class)->getById($pageId);
                        if ($pageRow === null || (int) $pageRow['status'] !== 1 || (int) $pageRow['merchant_id'] !== 0) {
                            $this->response->error('所选页面不存在或不属于主站');
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
                        'icon'        => (string) $this->input->post('icon', ''),
                        'target'      => (string) $this->input->post('target', '_self'),
                        'sort'        => (int) $this->input->post('sort', 100),
                        'status'      => $this->input->post('status', '1') === '1' ? 1 : 0,
                    ]);

                    $this->response->success('导航更新成功', ['csrf_token' => $this->csrf->refresh()]);
                    break;
                }

                // 删除
                case 'delete': {
                    $id = (int) $this->input->post('id', 0);
                    if ($id <= 0) {
                        $this->response->error('无效的导航ID');
                    }

                    $existing = $model->findById($id);
                    if ($existing === null) {
                        $this->response->error('导航不存在');
                    }
                    if ((int) $existing['is_system'] === 1) {
                        $this->response->error('系统导航不可删除');
                    }
                    if ((int) $existing['merchant_id'] !== 0) {
                        $this->response->error('无权删除商户自定义导航');
                    }
                    if ($model->hasChildren($id)) {
                        $this->response->error('请先删除子导航');
                    }

                    if (!$model->delete($id)) {
                        $this->response->error('删除失败');
                    }

                    $this->response->success('删除成功', ['csrf_token' => $this->csrf->refresh()]);
                    break;
                }

                // 状态切换
                case 'toggle_status': {
                    $id = (int) $this->input->post('id', 0);
                    $existing = $model->findById($id);
                    if ($existing === null) {
                        $this->response->error('导航不存在');
                    }
                    if ((int) $existing['is_system'] !== 1 && (int) $existing['merchant_id'] !== 0) {
                        $this->response->error('无权操作商户自定义导航');
                    }

                    $newStatus = ((int) $existing['status'] === 1) ? 0 : 1;
                    $model->update($id, ['status' => (string) $newStatus]);

                    $this->response->success($newStatus === 1 ? '已启用' : '已禁用', ['csrf_token' => $this->csrf->refresh()]);
                    break;
                }

                // 打开方式切换
                case 'update_target': {
                    $id = (int) $this->input->post('id', 0);
                    $existing = $model->findById($id);
                    if ($existing === null) {
                        $this->response->error('导航不存在');
                    }
                    if ((int) $existing['is_system'] !== 1 && (int) $existing['merchant_id'] !== 0) {
                        $this->response->error('无权操作商户自定义导航');
                    }
                    $target = (string) $this->input->post('target', '_self');
                    if (!in_array($target, ['_self', '_blank'], true)) {
                        $target = '_self';
                    }
                    $model->update($id, ['target' => $target]);
                    $this->response->success($target === '_blank' ? '已设为新窗口' : '已设为当前窗口', ['csrf_token' => $this->csrf->refresh()]);
                    break;
                }

                // 拖动排序
                case 'sort': {
                    $sortJson = (string) $this->input->post('sort_data', '');
                    $sortArr = json_decode($sortJson, true);
                    if (!is_array($sortArr) || empty($sortArr)) {
                        $this->response->error('排序数据无效');
                    }
                    $sortMap = [];
                    foreach ($sortArr as $item) {
                        if (isset($item['id'], $item['sort'])) {
                            $sortMap[(int) $item['id']] = (int) $item['sort'];
                        }
                    }
                    $model->batchUpdateSort($sortMap);
                    $this->response->success('排序已保存', ['csrf_token' => $this->csrf->refresh()]);
                    break;
                }

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
     * 编辑弹窗（iframe）。
     */
    private function renderPopup(\App\Model\NaviModel $model): void
    {
        $editId = (int) $this->input->get('id', 0);
        $editItem = null;
        if ($editId > 0) {
            $editItem = $model->findById($editId);
            // ACL：弹窗只允许编辑主站归属或系统导航
            if ($editItem !== null && (int) $editItem['is_system'] !== 1 && (int) $editItem['merchant_id'] !== 0) {
                $editItem = null;
            }
        }

        $this->render('popup/navi', [
            'editItem'        => $editItem,
            'topLevelItems'   => $model->getTopLevel(0),
            'goodsCategories' => (new \App\Model\GoodsCategoryModel())->getAll(),
            'blogCategories'  => (new \App\Model\BlogCategoryModel())->getAll(0),
            'publishedPages'  => app(\App\Model\PageModel::class)->getPublishedSimple(0),
        ]);
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套后台框架壳。
     */
    private function renderList(): void
    {
        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('navi');
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/navi.php', $this->user(), get_defined_vars());
    }
}
