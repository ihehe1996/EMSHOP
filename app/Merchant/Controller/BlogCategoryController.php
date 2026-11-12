<?php

declare(strict_types=1);

namespace App\Merchant\Controller;

/**
 * 商户后台页面（/user/merchant/blog_category）。
 */
final class BlogCategoryController extends MerchantBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        $currentMerchant = $this->merchant();
        $merchantLevel = $this->level();
        $merchantId = (int) $currentMerchant['id'];
        $model = new \BlogCategoryModel();

        if ($this->request->isPost()) {
            try {
                $action = (string) $this->input->post('_action', '');
                if ($action !== 'list') {
                    if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
                        $this->response->error('请求已失效，请刷新页面后重试');
                    }
                }

                switch ($action) {
                    case 'list': {
                        $rows = $model->getAll($merchantId);
                        $this->response->success('', ['data' => $rows, 'total' => count($rows), 'csrf_token' => $this->csrf->token()]);
                        break;
                    }

                    case 'save': {
                        $id = (int) $this->input->post('id', 0);
                        $name = trim((string) $this->input->post('name', ''));
                        if ($name === '' || mb_strlen($name) > 100) {
                            $this->response->error('分类名需在 1~100 字符');
                        }

                        $parentId = max(0, (int) $this->input->post('parent_id', 0));
                        if ($parentId > 0) {
                            $parent = $model->findById($parentId);
                            if ($parent === null
                                || (int) $parent['merchant_id'] !== $merchantId
                                || (int) $parent['parent_id'] !== 0) {
                                $this->response->error('父分类无效或仅支持二级');
                            }
                        }

                        $slug = trim((string) $this->input->post('slug', ''));
                        if ($slug !== '' && $model->existsSlug($slug, $id, $merchantId)) {
                            $this->response->error('别名在本店已被占用');
                        }

                        $data = [
                            'parent_id'   => $parentId,
                            'name'        => $name,
                            'slug'        => $slug,
                            'description' => (string) $this->input->post('description', ''),
                            'icon'        => (string) $this->input->post('icon', ''),
                            'sort'        => (int) $this->input->post('sort', 100),
                            'status'      => $this->input->post('status', '1') === '1' ? 1 : 0,
                        ];

                        if ($id > 0) {
                            $existing = $model->findById($id);
                            if ($existing === null || (int) $existing['merchant_id'] !== $merchantId) {
                                $this->response->error('分类不存在');
                            }
                            if ($parentId === $id) {
                                $this->response->error('不能选择自己作为父分类');
                            }
                            if ((int) $existing['parent_id'] === 0 && $parentId > 0 && $model->hasChildren($id)) {
                                $this->response->error('该分类下有子分类，不能改为子级');
                            }
                            $model->update($id, $data);
                            $this->response->success('已更新', ['csrf_token' => $this->csrf->refresh()]);
                        } else {
                            $data['merchant_id'] = $merchantId;
                            $newId = $model->create($data);
                            $this->response->success('已添加', ['id' => $newId, 'csrf_token' => $this->csrf->refresh()]);
                        }
                        break;
                    }

                    case 'delete': {
                        $id = (int) $this->input->post('id', 0);
                        $existing = $model->findById($id);
                        if ($existing === null || (int) $existing['merchant_id'] !== $merchantId) {
                            $this->response->error('分类不存在');
                        }
                        if ($model->hasChildren($id)) {
                            $this->response->error('请先删除子分类');
                        }
                        if (!$model->delete($id)) {
                            $this->response->error('删除失败');
                        }
                        $this->response->success('已删除', ['csrf_token' => $this->csrf->refresh()]);
                        break;
                    }

                    case 'toggle_status': {
                        $id = (int) $this->input->post('id', 0);
                        $existing = $model->findById($id);
                        if ($existing === null || (int) $existing['merchant_id'] !== $merchantId) {
                            $this->response->error('分类不存在');
                        }
                        $next = ((int) $existing['status'] === 1) ? 0 : 1;
                        $model->update($id, ['status' => (string) $next]);
                        $this->response->success($next === 1 ? '已启用' : '已禁用', ['csrf_token' => $this->csrf->refresh()]);
                        break;
                    }

                    default:
                        $this->response->error('未知操作');
                }
            } catch (\Throwable $e) {
                $this->response->error($e->getMessage() ?: '系统繁忙，请稍后再试');
            }
        }

        \App\Merchant\MerchantShell::render(dirname(__DIR__) . '/View/blog_category.php', $frontUser, $currentMerchant, $merchantLevel);
    }
}
