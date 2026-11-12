<?php

declare(strict_types=1);

namespace App\Merchant\Controller;

/**
 * 商户后台 - blog_edit 弹窗（/user/merchant/blog_edit）。
 */
final class BlogEditController extends MerchantBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        $currentMerchant = $this->merchant();
        $merchantLevel = $this->level();
        $merchantId = (int) $currentMerchant['id'];
        $action = (string) ($this->input->post('_action', '') ?: $this->input->get('_action', ''));

        // 保存
        if ($action === 'save') {
            if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
                $this->response->error('请求已失效，请刷新页面后重试');
            }

            $id = (int) $this->input->post('id', 0);
            $title = trim((string) $this->input->post('title', ''));
            if ($title === '') $this->response->error('标题不能为空');
            if (mb_strlen($title) > 255) $this->response->error('标题最多 255 字符');

            $categoryId = (int) $this->input->post('category_id', 0);
            if ($categoryId > 0) {
                $cat = app(\App\Model\BlogCategoryModel::class)->findById($categoryId);
                if ($cat === null || (int) $cat['merchant_id'] !== $merchantId) {
                    $this->response->error('请选择本店的分类');
                }
            }

            $data = [
                'title'       => $title,
                'category_id' => $categoryId,
                'excerpt'     => trim((string) $this->input->post('excerpt', '')),
                'content'     => (string) $this->input->post('content', ''),
                'cover_image' => trim((string) $this->input->post('cover_image', '')),
                'sort'        => (int) $this->input->post('sort', 0),
                'is_top'      => $this->input->post('is_top') !== null ? 1 : 0,
                'status'      => (int) $this->input->post('status', 1) === 1 ? 1 : 0,
            ];

            try {
                if ($id > 0) {
                    $existing = app(\App\Model\BlogModel::class)->getById($id);
                    if (!$existing || (int) $existing['merchant_id'] !== $merchantId) {
                        $this->response->error('文章不存在');
                    }
                    app(\App\Model\BlogModel::class)->update($id, $data);
                    $articleId = $id;
                } else {
                    $data['user_id']     = (int) ($frontUser['id'] ?? 0);
                    $data['created_by']  = (int) ($frontUser['id'] ?? 0);
                    $data['merchant_id'] = $merchantId;
                    $articleId = app(\App\Model\BlogModel::class)->create($data);
                }

                if (!$articleId) $this->response->error('保存失败');

                // 标签同步（本店标签池）
                $tagsStr = trim((string) $this->input->post('tags', ''));
                $tagNames = array_filter(array_map('trim', explode(',', $tagsStr)));
                $tagIds = [];
                foreach ($tagNames as $name) {
                    if ($name === '') continue;
                    $tagIds[] = app(\App\Model\BlogTagModel::class)->findOrCreate($name, $merchantId);
                }
                app(\App\Model\BlogTagModel::class)->syncBlogTags($articleId, $tagIds);
                app(\App\Model\BlogTagModel::class)->refreshAllCounts();
            } catch (\Throwable $e) {
                $this->response->error('保存失败：' . $e->getMessage());
            }

            $this->response->success('保存成功', ['id' => $articleId, 'csrf_token' => $this->csrf->refresh()]);
        }

        // 渲染弹窗（弹窗里独立页面，不走 merchantRenderPage）
        $id = (int) $this->input->get('id', 0);
        $article = null;
        if ($id > 0) {
            $article = app(\App\Model\BlogModel::class)->getById($id);
            if ($article && (int) $article['merchant_id'] !== $merchantId) {
                $article = null;
            }
        }
        $categories = app(\App\Model\BlogCategoryModel::class)->getAll($merchantId);

        if ($this->input->get('_popup', '') === '1') {
            $this->render('popup/blog_edit', get_defined_vars());
            return;
        }
    }
}
