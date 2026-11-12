<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 文章编辑弹窗（/admin/blog_edit）。
 *
 * 动作：_action=save 保存；?_popup=1 渲染编辑弹窗。
 * 与旧 admin/blog_edit.php 行为一致（主站后台不展示/操作商户文章）。
 */
final class BlogEditController extends BaseAdminController
{
    public function index(): void
    {
        $action = (string) $this->input->get('_action', '');
        if ($action === '') {
            $action = (string) $this->input->post('_action', '');
        }

        if ($action === 'save') {
            $this->handleSave();
            return;
        }

        $id = (int) $this->input->get('id', 0);
        $article = null;
        if ($id) {
            $article = \App\Model\BlogModel::getById($id);
            if ($article && (int) $article['merchant_id'] !== 0) {
                $article = null; // 主站后台不展示商户文章
            }
        }

        $categories = $this->db->query(
            'SELECT * FROM ' . $this->db->prefix() . 'blog_category WHERE status = 1 AND merchant_id = 0 ORDER BY parent_id ASC, sort ASC'
        );

        // 弹窗模式；非弹窗时旧页面输出空白
        if ((string) $this->input->get('_popup', '') === '1') {
            $this->render('popup/blog_edit', [
                'article'    => $article,
                'categories' => $categories,
            ]);
        }
    }

    /**
     * 保存文章。
     */
    private function handleSave(): void
    {
        $csrf = (string) $this->input->post('csrf_token', '');
        if (!$this->csrf->validate($csrf)) {
            $this->response->error('请求已失效，请刷新页面后重试');
        }

        $id = (int) $this->input->post('id', 0);
        $title = trim((string) $this->input->post('title', ''));
        $categoryId = (int) $this->input->post('category_id', 0);
        $excerpt = trim((string) $this->input->post('excerpt', ''));
        $content = $this->input->post('content', '');
        $coverImage = trim((string) $this->input->post('cover_image', ''));
        $sort = (int) $this->input->post('sort', 0);
        $isTop = $this->input->post('is_top', null) !== null ? 1 : 0;
        $status = (int) $this->input->post('status', 1);

        // 验证必填
        if ($title === '') {
            $this->response->error('文章标题不能为空');
        }

        $data = [
            'title'       => $title,
            'category_id' => $categoryId,
            'excerpt'     => $excerpt,
            'content'     => $content,
            'cover_image' => $coverImage,
            'sort'        => $sort,
            'is_top'      => $isTop,
            'status'      => $status,
        ];

        try {
            if ($id) {
                // 更新前 ACL 校验
                $existing = \App\Model\BlogModel::getById($id);
                if (!$existing) {
                    $this->response->error('文章不存在');
                }
                if ((int) $existing['merchant_id'] !== 0) {
                    $this->response->error('无权操作商户文章');
                }
                \App\Model\BlogModel::update($id, $data);
                $articleId = $id;
            } else {
                $data['user_id'] = $_SESSION['em_admin_auth']['id'] ?? 0;
                $data['created_by'] = $data['user_id'];
                $data['merchant_id'] = 0; // 主站后台创建的文章固定归主站
                $articleId = \App\Model\BlogModel::create($data);
            }

            if (!$articleId) {
                $this->response->error('保存失败');
            }

            // 保存标签关联（主站标签池）
            $tagsStr = trim((string) $this->input->post('tags', ''));
            $tagNames = array_filter(array_map('trim', explode(',', $tagsStr)));
            $tagIds = [];
            foreach ($tagNames as $tagName) {
                if ($tagName !== '') {
                    $tagIds[] = \App\Model\BlogTagModel::findOrCreate($tagName, 0);
                }
            }
            \App\Model\BlogTagModel::syncBlogTags($articleId, $tagIds);
            \App\Model\BlogTagModel::refreshAllCounts();
        } catch (\Throwable $e) {
            $this->response->error('保存失败：' . $e->getMessage());
        }

        $newToken = $this->csrf->refresh();
        $this->response->success('保存成功', ['id' => $articleId, 'csrf_token' => $newToken]);
    }
}
