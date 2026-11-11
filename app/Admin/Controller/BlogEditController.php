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
        $action = (string) \Input::get('_action', '');
        if ($action === '') {
            $action = (string) \Input::post('_action', '');
        }

        if ($action === 'save') {
            $this->handleSave();
            return;
        }

        $id = (int) \Input::get('id', 0);
        $article = null;
        if ($id) {
            $article = \BlogModel::getById($id);
            if ($article && (int) $article['merchant_id'] !== 0) {
                $article = null; // 主站后台不展示商户文章
            }
        }

        $categories = \Database::query(
            'SELECT * FROM ' . \Database::prefix() . 'blog_category WHERE status = 1 AND merchant_id = 0 ORDER BY parent_id ASC, sort ASC'
        );

        // 弹窗模式；非弹窗时旧页面输出空白
        if ((string) \Input::get('_popup', '') === '1') {
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
        $csrf = (string) \Input::post('csrf_token', '');
        if (!\Csrf::validate($csrf)) {
            \Response::error('请求已失效，请刷新页面后重试');
        }

        $id = (int) \Input::post('id', 0);
        $title = trim((string) \Input::post('title', ''));
        $categoryId = (int) \Input::post('category_id', 0);
        $excerpt = trim((string) \Input::post('excerpt', ''));
        $content = \Input::post('content', '');
        $coverImage = trim((string) \Input::post('cover_image', ''));
        $sort = (int) \Input::post('sort', 0);
        $isTop = \Input::post('is_top', null) !== null ? 1 : 0;
        $status = (int) \Input::post('status', 1);

        // 验证必填
        if ($title === '') {
            \Response::error('文章标题不能为空');
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
                $existing = \BlogModel::getById($id);
                if (!$existing) {
                    \Response::error('文章不存在');
                }
                if ((int) $existing['merchant_id'] !== 0) {
                    \Response::error('无权操作商户文章');
                }
                \BlogModel::update($id, $data);
                $articleId = $id;
            } else {
                $data['user_id'] = $_SESSION['em_admin_auth']['id'] ?? 0;
                $data['created_by'] = $data['user_id'];
                $data['merchant_id'] = 0; // 主站后台创建的文章固定归主站
                $articleId = \BlogModel::create($data);
            }

            if (!$articleId) {
                \Response::error('保存失败');
            }

            // 保存标签关联（主站标签池）
            $tagsStr = trim((string) \Input::post('tags', ''));
            $tagNames = array_filter(array_map('trim', explode(',', $tagsStr)));
            $tagIds = [];
            foreach ($tagNames as $tagName) {
                if ($tagName !== '') {
                    $tagIds[] = \BlogTagModel::findOrCreate($tagName, 0);
                }
            }
            \BlogTagModel::syncBlogTags($articleId, $tagIds);
            \BlogTagModel::refreshAllCounts();
        } catch (\Throwable $e) {
            \Response::error('保存失败：' . $e->getMessage());
        }

        $newToken = \Csrf::refresh();
        \Response::success('保存成功', ['id' => $articleId, 'csrf_token' => $newToken]);
    }
}
