<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 文章分类管理（/admin/blog_category）。
 *
 * 仅支持 2 级分类：顶级 和 二级（归属某顶级）。
 * 与旧 admin/blog_category.php 行为一致：列表 / 创建 / 更新 / 删除 /
 * 批量删除 / 状态切换 / 图片上传，以及 ?_popup=1 编辑弹窗。
 *
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class BlogCategoryController extends BaseAdminController
{
    public function index(): void
    {
        $model = new \App\Model\BlogCategoryModel();

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
     * 处理后台 AJAX 动作；除 list 外都校验 CSRF。
     */
    private function handlePost(\App\Model\BlogCategoryModel $model): void
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
                case 'list':
                    $keyword = trim((string) $this->input->post('keyword', ''));
                    // 主站后台只看 merchant_id=0
                    $allCategories = $model->getAll(0);

                    $data = [];
                    foreach ($allCategories as $item) {
                        if ($keyword !== '' && stripos((string) $item['name'], $keyword) === false) {
                            continue;
                        }
                        $data[] = $item;
                    }

                    usort($data, function ($a, $b) {
                        return $a['sort'] - $b['sort'];
                    });

                    // treeTable 直接读取 data 数组，无需嵌套格式
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'code' => 0,
                        'msg' => '',
                        'data' => array_values($data),
                        'count' => count($data),
                        'csrf_token' => $this->csrf->token(),
                    ], JSON_UNESCAPED_UNICODE);
                    return;

                case 'create': {
                    $name = trim((string) $this->input->post('name', ''));
                    if ($name === '') {
                        $this->response->error('分类名称不能为空');
                    }
                    if (mb_strlen($name) > 100) {
                        $this->response->error('分类名称最多100个字符');
                    }

                    $parentId = (int) $this->input->post('parent_id', 0);

                    $slug = trim((string) $this->input->post('slug', ''));
                    if ($slug !== '' && $model->existsSlug($slug, 0, 0)) {
                        $this->response->error('别名已存在');
                    }

                    $sort = (int) $this->input->post('sort', 100);
                    $status = $this->input->post('status', '1') === '1' ? 1 : 0;

                    $model->create([
                        'parent_id' => $parentId,
                        'merchant_id' => 0, // 主站后台创建的分类固定归主站
                        'name' => $name,
                        'slug' => $slug,
                        'description' => (string) $this->input->post('description', ''),
                        'icon' => (string) $this->input->post('icon', ''),
                        'cover_image' => (string) $this->input->post('cover_image', ''),
                        'sort' => $sort,
                        'seo_title' => (string) $this->input->post('seo_title', ''),
                        'seo_keywords' => (string) $this->input->post('seo_keywords', ''),
                        'seo_description' => (string) $this->input->post('seo_description', ''),
                        'status' => $status,
                    ]);

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('分类创建成功', ['csrf_token' => $csrfToken]);
                    break;
                }

                case 'update': {
                    $id = (int) $this->input->post('id', 0);
                    if ($id <= 0) {
                        $this->response->error('无效的分类ID');
                    }

                    $existing = $model->findById($id);
                    if ($existing === null) {
                        $this->response->error('分类不存在');
                    }
                    if ((int) $existing['merchant_id'] !== 0) {
                        $this->response->error('无权操作商户分类');
                    }

                    $name = trim((string) $this->input->post('name', ''));
                    if ($name === '') {
                        $this->response->error('分类名称不能为空');
                    }
                    if (mb_strlen($name) > 100) {
                        $this->response->error('分类名称最多100个字符');
                    }

                    $parentId = (int) $this->input->post('parent_id', 0);

                    if ($existing['parent_id'] > 0 && $parentId === 0) {
                        if ($model->hasChildren($id)) {
                            $this->response->error('该分类下存在子分类，不能设为顶级');
                        }
                    }

                    $slug = trim((string) $this->input->post('slug', ''));
                    if ($slug !== '' && $model->existsSlug($slug, $id, 0)) {
                        $this->response->error('别名已存在');
                    }

                    $sort = (int) $this->input->post('sort', 100);
                    $status = $this->input->post('status', '1') === '1' ? 1 : 0;

                    $model->update($id, [
                        'parent_id' => $parentId,
                        'name' => $name,
                        'slug' => $slug,
                        'description' => (string) $this->input->post('description', ''),
                        'icon' => (string) $this->input->post('icon', ''),
                        'cover_image' => (string) $this->input->post('cover_image', ''),
                        'sort' => $sort,
                        'seo_title' => (string) $this->input->post('seo_title', ''),
                        'seo_keywords' => (string) $this->input->post('seo_keywords', ''),
                        'seo_description' => (string) $this->input->post('seo_description', ''),
                        'status' => $status,
                    ]);

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('分类更新成功', ['csrf_token' => $csrfToken]);
                    break;
                }

                case 'delete': {
                    $id = (int) $this->input->post('id', 0);
                    if ($id <= 0) {
                        $this->response->error('无效的分类ID');
                    }

                    $existing = $model->findById($id);
                    if ($existing === null) {
                        $this->response->error('分类不存在');
                    }
                    if ((int) $existing['merchant_id'] !== 0) {
                        $this->response->error('无权删除商户分类');
                    }

                    if ($model->hasChildren($id)) {
                        $this->response->error('该分类下存在子分类，请先删除子分类');
                    }

                    if (!$model->delete($id)) {
                        $this->response->error('删除失败');
                    }

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('删除成功', ['csrf_token' => $csrfToken]);
                    break;
                }

                case 'batch_delete': {
                    // 批量删除：支持 ids[] 或逗号分隔的 ids 字符串
                    $raw = $this->input->post('ids', '');
                    $ids = is_array($raw) ? $raw : array_filter(explode(',', (string) $raw));
                    $ids = array_values(array_unique(array_map('intval', $ids)));
                    $ids = array_filter($ids, fn($v) => $v > 0);
                    if (empty($ids)) {
                        $this->response->error('请选择要删除的分类');
                    }

                    // 策略：选中分类若有子分类且子分类也在选中列表中，允许（先删子再删父）
                    // 否则跳过并计数。返回 {deleted, skipped, fail}
                    $selectedSet = array_flip($ids);
                    $deleted = 0; $skipped = 0; $skippedNames = [];

                    // 取所有分类信息便于反查父子关系和名称（限定主站作用域）
                    $prefix = $this->db->prefix();
                    $all = $this->db->query("SELECT id, parent_id, name, merchant_id FROM {$prefix}blog_category WHERE id IN (" . implode(',', $ids) . ")");
                    $byId = [];
                    foreach ($all as $r) $byId[(int) $r['id']] = $r;
                    // 过滤掉非主站归属的 ID，防越权
                    $ids = array_values(array_filter($ids, fn($id) => isset($byId[$id]) && (int) $byId[$id]['merchant_id'] === 0));
                    if (empty($ids)) {
                        $this->response->error('所选分类均无权删除');
                    }

                    // 先按 parent_id 降序排：叶子先删，父后删
                    usort($ids, function ($a, $b) {
                        return 0; // 具体顺序靠循环多轮 —— 下面做 3 轮尝试
                    });

                    // 最多 3 轮尝试：每轮删能删的（无子，或子已被删）
                    for ($round = 0; $round < 5 && !empty($ids); $round++) {
                        $remain = [];
                        foreach ($ids as $id) {
                            if ($model->hasChildren($id)) {
                                $remain[] = $id;
                                continue;
                            }
                            if ($model->delete($id)) {
                                $deleted++;
                                unset($selectedSet[$id]);
                            } else {
                                $skipped++;
                                $skippedNames[] = $byId[$id]['name'] ?? ('#' . $id);
                            }
                        }
                        if (count($remain) === count($ids)) break; // 没有进展，剩下的都有非选中子分类
                        $ids = $remain;
                    }
                    foreach ($ids as $id) {
                        $skipped++;
                        $skippedNames[] = ($byId[$id]['name'] ?? ('#' . $id)) . '（含子分类）';
                    }

                    $msg = "删除成功 {$deleted} 个";
                    if ($skipped > 0) {
                        $msg .= "，跳过 {$skipped} 个：" . implode('、', array_slice($skippedNames, 0, 3)) . ($skipped > 3 ? '…' : '');
                    }
                    $csrfToken = $this->csrf->refresh();
                    $this->response->success($msg, ['csrf_token' => $csrfToken, 'deleted' => $deleted, 'skipped' => $skipped]);
                    break;
                }

                case 'toggle_status': {
                    $id = (int) $this->input->post('id', 0);
                    if ($id <= 0) {
                        $this->response->error('无效的分类ID');
                    }

                    $existing = $model->findById($id);
                    if ($existing === null) {
                        $this->response->error('分类不存在');
                    }
                    if ((int) $existing['merchant_id'] !== 0) {
                        $this->response->error('无权操作商户分类');
                    }

                    $newStatus = ((int) $existing['status'] === 1) ? 0 : 1;
                    $model->update($id, ['status' => (string) $newStatus]);

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success($newStatus === 1 ? '已启用' : '已禁用', ['csrf_token' => $csrfToken]);
                    break;
                }

                case 'image': {
                    if (empty($_FILES['file'])) {
                        $this->response->error('请选择图片文件');
                    }
                    $uploader = new \UploadService();
                    $result = $uploader->upload($_FILES['file'], ['jpg', 'jpeg', 'png', 'gif', 'webp'], 'blog_category');
                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('上传成功', [
                        'csrf_token' => $csrfToken,
                        'url' => $result['url'],
                    ]);
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
     * 添加/编辑弹窗（iframe）。
     */
    private function renderPopup(\App\Model\BlogCategoryModel $model): void
    {
        $editId = (int) $this->input->get('id', 0);
        $editCat = null;

        if ($editId > 0) {
            $editCat = $model->findById($editId);
            if ($editCat !== null && (int) $editCat['merchant_id'] !== 0) {
                $editCat = null; // 不展示商户分类
            }
        }

        $this->render('popup/blog_category', [
            'editCat'      => $editCat,
            'topLevelCats' => $model->getTopLevel(0),
        ]);
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套旧后台框架壳。
     */
    private function renderList(): void
    {
        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('blog_category');
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/blog_category.php', $this->user(), get_defined_vars());
    }
}
