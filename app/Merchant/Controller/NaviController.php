<?php

declare(strict_types=1);

namespace App\Merchant\Controller;

/**
 * 商户后台页面（/user/merchant/navi）。
 */
final class NaviController extends MerchantBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        $currentMerchant = $this->merchant();
        $merchantLevel = $this->level();
        $merchantId = (int) $currentMerchant['id'];
        $model = new \App\Model\NaviModel();

        if ($this->request->isPost()) {
            try {
                $action = (string) $this->input->post('_action', '');
                if ($action !== 'list') {
                    if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
                        $this->response->error('请求已失效，请刷新页面后重试');
                    }
                }

                switch ($action) {
                    // 列表 —— 系统导航（is_system=1）+ 本店自定义导航
                    case 'list': {
                        $rows = $model->getAll($merchantId);
                        $this->response->success('', ['data' => $rows, 'total' => count($rows), 'csrf_token' => $this->csrf->token()]);
                        break;
                    }

                    // 创建（仅自定义类型）
                    case 'create': {
                        $name = trim((string) $this->input->post('name', ''));
                        if ($name === '' || mb_strlen($name) > 100) {
                            $this->response->error('导航名称需在 1~100 字符');
                        }
                        $type = (string) $this->input->post('type', 'custom');
                        if (!in_array($type, ['custom', 'blog_cat', 'page'], true)) {
                            $type = 'custom';
                        }

                        $parentId = (int) $this->input->post('parent_id', 0);
                        if ($parentId > 0) {
                            // 父导航必须是本店或系统的顶级
                            $parent = $model->findById($parentId);
                            if ($parent === null
                                || (int) $parent['parent_id'] !== 0
                                || ((int) $parent['is_system'] !== 1 && (int) $parent['merchant_id'] !== $merchantId)) {
                                $this->response->error('父导航无效');
                            }
                        }

                        $link = trim((string) $this->input->post('link', ''));
                        $typeRefId = 0;

                        if ($type === 'blog_cat') {
                            $catId = (int) $this->input->post('type_ref_id', 0);
                            if ($catId <= 0) $this->response->error('请选择博客分类');
                            $cat = (new \App\Model\BlogCategoryModel())->findById($catId);
                            if ($cat === null || (int) $cat['merchant_id'] !== $merchantId) {
                                $this->response->error('博客分类无效或不属于本店');
                            }
                            $typeRefId = $catId;
                            $link = '?c=blog_list&category_id=' . $catId;
                        } elseif ($type === 'page') {
                            $pageId = (int) $this->input->post('type_ref_id', 0);
                            if ($pageId <= 0) $this->response->error('请选择页面');
                            $pageRow = app(\App\Model\PageModel::class)->getById($pageId);
                            if ($pageRow === null
                                || (int) $pageRow['status'] !== 1
                                || (int) $pageRow['merchant_id'] !== $merchantId) {
                                $this->response->error('页面不存在或不属于本店或未发布');
                            }
                            $typeRefId = $pageId;
                            $link = '/p/' . $pageRow['slug'];
                        } elseif ($link === '') {
                            $this->response->error('请输入链接地址');
                        }

                        $model->create([
                            'parent_id'   => $parentId,
                            'merchant_id' => $merchantId,
                            'name'        => $name,
                            'type'        => $type,
                            'type_ref_id' => $typeRefId,
                            'link'        => $link,
                            'icon'        => (string) $this->input->post('icon', ''),
                            'target'      => in_array((string) $this->input->post('target', '_self'), ['_self', '_blank'], true)
                                ? (string) $this->input->post('target', '_self') : '_self',
                            'sort'        => (int) $this->input->post('sort', 100),
                            'status'      => $this->input->post('status', '1') === '1' ? 1 : 0,
                            'is_system'   => 0, // 商户后台始终非系统
                        ]);
                        $this->response->success('已添加', ['csrf_token' => $this->csrf->refresh()]);
                        break;
                    }

                    // 更新
                    case 'update': {
                        $id = (int) $this->input->post('id', 0);
                        $existing = $model->findById($id);
                        if ($existing === null) $this->response->error('导航不存在');
                        if ((int) $existing['is_system'] === 1) {
                            $this->response->error('系统导航不可修改');
                        }
                        if ((int) $existing['merchant_id'] !== $merchantId) {
                            $this->response->error('无权操作其它店铺的导航');
                        }

                        $name = trim((string) $this->input->post('name', ''));
                        if ($name === '' || mb_strlen($name) > 100) {
                            $this->response->error('导航名称需在 1~100 字符');
                        }

                        $type = (string) $this->input->post('type', 'custom');
                        if (!in_array($type, ['custom', 'blog_cat', 'page'], true)) {
                            $type = 'custom';
                        }

                        $parentId = (int) $this->input->post('parent_id', 0);
                        if ($parentId > 0) {
                            $parent = $model->findById($parentId);
                            if ($parent === null
                                || (int) $parent['parent_id'] !== 0
                                || ((int) $parent['is_system'] !== 1 && (int) $parent['merchant_id'] !== $merchantId)) {
                                $this->response->error('父导航无效');
                            }
                            if ($parentId === $id) {
                                $this->response->error('不能选择自己作为父导航');
                            }
                        }

                        $link = trim((string) $this->input->post('link', ''));
                        $typeRefId = 0;

                        if ($type === 'blog_cat') {
                            $catId = (int) $this->input->post('type_ref_id', 0);
                            if ($catId <= 0) $this->response->error('请选择博客分类');
                            $cat = (new \App\Model\BlogCategoryModel())->findById($catId);
                            if ($cat === null || (int) $cat['merchant_id'] !== $merchantId) {
                                $this->response->error('博客分类无效或不属于本店');
                            }
                            $typeRefId = $catId;
                            $link = '?c=blog_list&category_id=' . $catId;
                        } elseif ($type === 'page') {
                            $pageId = (int) $this->input->post('type_ref_id', 0);
                            if ($pageId <= 0) $this->response->error('请选择页面');
                            $pageRow = app(\App\Model\PageModel::class)->getById($pageId);
                            if ($pageRow === null
                                || (int) $pageRow['status'] !== 1
                                || (int) $pageRow['merchant_id'] !== $merchantId) {
                                $this->response->error('页面不存在或不属于本店或未发布');
                            }
                            $typeRefId = $pageId;
                            $link = '/p/' . $pageRow['slug'];
                        } elseif ($link === '') {
                            $this->response->error('请输入链接地址');
                        }

                        $model->update($id, [
                            'parent_id'   => $parentId,
                            'name'        => $name,
                            'type'        => $type,
                            'type_ref_id' => $typeRefId,
                            'link'        => $link,
                            'icon'        => (string) $this->input->post('icon', ''),
                            'target'      => in_array((string) $this->input->post('target', '_self'), ['_self', '_blank'], true)
                                ? (string) $this->input->post('target', '_self') : '_self',
                            'sort'        => (int) $this->input->post('sort', 100),
                            'status'      => $this->input->post('status', '1') === '1' ? 1 : 0,
                        ]);
                        $this->response->success('已保存', ['csrf_token' => $this->csrf->refresh()]);
                        break;
                    }

                    // 删除
                    case 'delete': {
                        $id = (int) $this->input->post('id', 0);
                        $existing = $model->findById($id);
                        if ($existing === null) $this->response->error('导航不存在');
                        if ((int) $existing['is_system'] === 1) $this->response->error('系统导航不可删除');
                        if ((int) $existing['merchant_id'] !== $merchantId) {
                            $this->response->error('无权操作其它店铺的导航');
                        }
                        if ($model->hasChildren($id)) {
                            $this->response->error('请先删除子导航');
                        }
                        if (!$model->delete($id)) {
                            $this->response->error('删除失败');
                        }
                        $this->response->success('已删除', ['csrf_token' => $this->csrf->refresh()]);
                        break;
                    }

                    // 状态切换（仅自定义导航）
                    case 'toggle_status': {
                        $id = (int) $this->input->post('id', 0);
                        $existing = $model->findById($id);
                        if ($existing === null) $this->response->error('导航不存在');
                        if ((int) $existing['is_system'] === 1) {
                            $this->response->error('系统导航请使用"在本店隐藏"');
                        }
                        if ((int) $existing['merchant_id'] !== $merchantId) {
                            $this->response->error('无权操作其它店铺的导航');
                        }
                        $next = ((int) $existing['status'] === 1) ? 0 : 1;
                        $model->update($id, ['status' => (string) $next]);
                        $this->response->success($next === 1 ? '已启用' : '已禁用', ['csrf_token' => $this->csrf->refresh()]);
                        break;
                    }

                    // 隐藏 / 显示 系统导航（不改主站记录，仅写本店"隐藏名单"）
                    case 'toggle_hide_system': {
                        $id = (int) $this->input->post('id', 0);
                        $existing = $model->findById($id);
                        if ($existing === null) $this->response->error('导航不存在');
                        if ((int) $existing['is_system'] !== 1) {
                            $this->response->error('该操作仅对系统导航生效');
                        }
                        $next = $model->toggleHideSystem($merchantId, $id);
                        $this->response->success($next === 1 ? '已在本店隐藏' : '已在本店恢复显示', [
                            'is_hidden' => $next,
                            'csrf_token' => $this->csrf->refresh(),
                        ]);
                        break;
                    }

                    default:
                        $this->response->error('未知操作');
                }
            } catch (\Throwable $e) {
                $this->response->error($e->getMessage() ?: '系统繁忙，请稍后再试');
            }
        }

        // 渲染页面 —— 提前把本店博客分类、页面传给 view（用于"博客分类 / 页面"类型下拉）
        $blogCategories = (new \App\Model\BlogCategoryModel())->getAll($merchantId);
        $publishedPages = app(\App\Model\PageModel::class)->getPublishedSimple($merchantId);

        \App\Merchant\MerchantShell::render(dirname(__DIR__) . '/View/navi.php', $frontUser, $currentMerchant, $merchantLevel, [
            'blogCategories' => $blogCategories,
            'publishedPages' => $publishedPages,
        ]);
    }
}
