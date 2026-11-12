<?php

declare(strict_types=1);

namespace App\Home\Controller;

/**
 * 博客评论控制器（前台 API）。
 *
 * URL: ?c=blog_comment
 * 通过 POST _action 参数分发：
 *   list          获取顶级评论列表（分页）
 *   replies       获取某条评论的回复列表（分页）
 *   post          发表评论/回复（需登录）
 *   delete        删除自己的评论（需登录）
 */
class BlogCommentController extends BaseController
{
    public function _index(): void
    {
        $action = trim($_POST['_action'] ?? $_GET['_action'] ?? '');

        switch ($action) {
            case 'list':    $this->listComments(); break;
            case 'replies': $this->listReplies(); break;
            case 'post':    $this->postComment(); break;
            case 'delete':  $this->deleteComment(); break;
            default:        $this->response->error('未知操作'); break;
        }
    }

    /**
     * 获取当前登录的前台用户，未登录返回 null
     */
    private function getFrontUser(): ?array
    {
        return $_SESSION['em_front_user'] ?? null;
    }

    /**
     * 获取顶级评论列表
     */
    private function listComments(): void
    {
        $blogId = (int) ($_POST['blog_id'] ?? $_GET['blog_id'] ?? 0);
        $page   = max(1, (int) ($_POST['page'] ?? $_GET['page'] ?? 1));
        $limit  = min(50, max(1, (int) ($_POST['limit'] ?? $_GET['limit'] ?? 10)));
        $sort   = $_POST['sort'] ?? $_GET['sort'] ?? 'newest';

        if ($blogId <= 0) {
            $this->response->error('参数错误');
            return;
        }

        // 验证文章属于当前 scope
        $blog = app(\App\Model\BlogModel::class)->getByIdForScope($blogId, $this->merchantContext->currentId());
        if (!$blog) {
            $this->response->error('文章不存在');
            return;
        }

        $result = app(\App\Model\BlogCommentModel::class)->getTopComments($blogId, $page, $limit, $sort);

        // 为每条顶级评论加载前3条回复
        foreach ($result['list'] as &$comment) {
            $replies = app(\App\Model\BlogCommentModel::class)->getReplies((int) $comment['id'], 1, 3);
            $comment['replies'] = $replies['list'];
            $comment['reply_total'] = $replies['total'];
        }
        unset($comment);

        $this->response->success('', $result);
    }

    /**
     * 获取某条评论的回复列表
     */
    private function listReplies(): void
    {
        $parentId = (int) ($_POST['parent_id'] ?? $_GET['parent_id'] ?? 0);
        $page     = max(1, (int) ($_POST['page'] ?? $_GET['page'] ?? 1));
        $limit    = min(50, max(1, (int) ($_POST['limit'] ?? $_GET['limit'] ?? 5)));

        if ($parentId <= 0) {
            $this->response->error('参数错误');
            return;
        }

        $result = app(\App\Model\BlogCommentModel::class)->getReplies($parentId, $page, $limit);
        $this->response->success('', $result);
    }

    /**
     * 发表评论/回复
     */
    private function postComment(): void
    {
        $user = $this->getFrontUser();
        if (!$user) {
            $this->response->error('请先登录后再评论');
            return;
        }

        $blogId      = (int) ($_POST['blog_id'] ?? 0);
        $parentId    = (int) ($_POST['parent_id'] ?? 0);
        $replyUserId = (int) ($_POST['reply_user_id'] ?? 0);
        $content     = trim($_POST['content'] ?? '');

        if ($blogId <= 0) {
            $this->response->error('参数错误');
            return;
        }
        // 必须给当前 scope 的文章发评论
        $blog = app(\App\Model\BlogModel::class)->getByIdForScope($blogId, $this->merchantContext->currentId());
        if (!$blog) {
            $this->response->error('文章不存在');
            return;
        }
        if ($content === '') {
            $this->response->error('评论内容不能为空');
            return;
        }
        if (mb_strlen($content) > 1000) {
            $this->response->error('评论内容不能超过1000字');
            return;
        }

        // 如果是回复，验证父评论存在
        if ($parentId > 0) {
            $parent = app(\App\Model\BlogCommentModel::class)->getById($parentId);
            if (!$parent || (int) $parent['status'] !== 1 || $parent['deleted_at'] !== null) {
                $this->response->error('被回复的评论不存在');
                return;
            }
            // 确保 parent_id 始终指向顶级评论
            if ((int) $parent['parent_id'] > 0) {
                $parentId = (int) $parent['parent_id'];
            }
        }

        $newId = app(\App\Model\BlogCommentModel::class)->create([
            'blog_id'       => $blogId,
            'user_id'       => (int) $user['id'],
            'parent_id'     => $parentId,
            'reply_user_id' => $replyUserId,
            'content'       => htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
            'status'        => 1, // 默认通过
        ]);

        if (!$newId) {
            $this->response->error('评论失败，请稍后重试');
            return;
        }

        // 返回新评论数据（含用户信息）
        $comment = app(\App\Model\BlogCommentModel::class)->getById($newId);
        $comment['nickname'] = $user['nickname'] ?? $user['username'] ?? '用户';
        $comment['username'] = $user['username'] ?? '';
        $comment['avatar']   = $user['avatar'] ?? '';

        $this->response->success('评论成功', ['comment' => $comment]);
    }

    /**
     * 删除自己的评论
     */
    private function deleteComment(): void
    {
        $user = $this->getFrontUser();
        if (!$user) {
            $this->response->error('请先登录');
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->response->error('参数错误');
            return;
        }

        $comment = app(\App\Model\BlogCommentModel::class)->getById($id);
        if (!$comment || $comment['deleted_at'] !== null) {
            $this->response->error('评论不存在');
            return;
        }

        // 只能删除自己的评论
        if ((int) $comment['user_id'] !== (int) $user['id']) {
            $this->response->error('无权删除该评论');
            return;
        }

        app(\App\Model\BlogCommentModel::class)->delete($id);
        $this->response->success('删除成功');
    }
}
