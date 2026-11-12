<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 评论管理（{入口}/blog_comment）。
 *
 * 行为约定：
 *   - _action=list 分页列表 + 状态计数
 *   - approve / reject / delete / batch
 *   - 主站后台只能操作主站文章（merchant_id=0）下的评论
 *
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class BlogCommentController extends BaseAdminController
{
    public function index(): void
    {
        $action = (string) $this->input->get('_action', '');
        if ($action === '') {
            $action = (string) $this->input->post('_action', '');
        }

        // 非 list 操作统一校验 CSRF
        if ($action !== 'list' && $action !== '') {
            $csrf = (string) $this->input->post('csrf_token', '');
            if ($csrf === '') {
                $csrf = (string) $this->input->get('csrf_token', '');
            }
            if (!$this->csrf->validate($csrf)) {
                $this->response->error('请求已失效，请刷新页面后重试');
            }
        }

        // 列表接口（供 layui table 使用）
        if ($action === 'list') {
            $page = (int) $this->input->post('page', 1);
            $limit = (int) $this->input->post('limit', 20);
            $keyword = trim((string) $this->input->post('keyword', ''));
            $statusRaw = (string) $this->input->post('status', '');
            $status = $statusRaw !== '' ? (int) $statusRaw : null;

            // 主站后台只看主站文章下的评论
            $where = ['merchant_id' => 0];
            if ($keyword !== '') {
                $where['keyword'] = $keyword;
            }
            if ($status !== null) {
                $where['status'] = $status;
            }

            $result = app(\App\Model\BlogCommentModel::class)->getAdminList($where, $page, $limit);
            $counts = app(\App\Model\BlogCommentModel::class)->getStatusCounts(0);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'code'       => 0,
                'msg'        => '',
                'count'      => $result['total'],
                'data'       => $result['list'],
                'csrf_token' => $this->csrf->token(),
                'tab_counts' => $counts,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 审核通过
        if ($action === 'approve') {
            $id = (int) $this->input->post('id', 0);
            $this->assertMainComment($id);
            app(\App\Model\BlogCommentModel::class)->updateStatus($id, 1);
            $this->response->success('已通过', ['csrf_token' => $this->csrf->token()]);
        }

        // 拒绝
        if ($action === 'reject') {
            $id = (int) $this->input->post('id', 0);
            $this->assertMainComment($id);
            app(\App\Model\BlogCommentModel::class)->updateStatus($id, 2);
            $this->response->success('已拒绝', ['csrf_token' => $this->csrf->token()]);
        }

        // 删除评论（逻辑删除）
        if ($action === 'delete') {
            $id = (int) $this->input->post('id', 0);
            $this->assertMainComment($id);
            if (app(\App\Model\BlogCommentModel::class)->delete($id)) {
                $this->response->success('删除成功', ['csrf_token' => $this->csrf->token()]);
            } else {
                $this->response->error('删除失败');
            }
        }

        // 批量操作
        if ($action === 'batch') {
            $batchAction = (string) $this->input->post('batch_action', '');
            $rawIds = $this->input->post('ids', null);
            $ids = is_array($rawIds)
                ? array_map('intval', $rawIds)
                : (json_decode((string) $rawIds, true) ?: []);
            if (empty($ids)) {
                $this->response->error('请选择评论');
            }

            // 限定主站文章下的评论
            $prefix = $this->db->prefix();
            $allowedRows = $this->db->query(
                "SELECT c.id FROM {$prefix}blog_comment c
                 LEFT JOIN {$prefix}blog b ON c.blog_id = b.id
                 WHERE b.merchant_id = 0 AND c.id IN (" . implode(',', array_map('intval', $ids)) . ")"
            );
            $allowedIds = array_map(fn($r) => (int) $r['id'], $allowedRows);
            if (empty($allowedIds)) {
                $this->response->error('所选评论均无权操作');
            }

            if ($batchAction === 'approve') {
                app(\App\Model\BlogCommentModel::class)->batchUpdateStatus($allowedIds, 1);
            } elseif ($batchAction === 'reject') {
                app(\App\Model\BlogCommentModel::class)->batchUpdateStatus($allowedIds, 2);
            } elseif ($batchAction === 'delete') {
                app(\App\Model\BlogCommentModel::class)->batchDelete($allowedIds);
            } else {
                $this->response->error('未知操作');
            }

            $this->response->success('批量操作成功', ['csrf_token' => $this->csrf->token()]);
        }

        // 默认：显示列表页面
        $this->renderList();
    }

    /**
     * 主站只能操作 merchant_id=0 文章下的评论：通过 JOIN blog 表过滤。
     */
    private function assertMainComment(int $commentId): void
    {
        $prefix = $this->db->prefix();
        $row = $this->db->fetchOne(
            "SELECT c.id, b.merchant_id FROM {$prefix}blog_comment c
             LEFT JOIN {$prefix}blog b ON c.blog_id = b.id
             WHERE c.id = ? LIMIT 1",
            [$commentId]
        );
        if (!$row) {
            $this->response->error('评论不存在');
        }
        if ((int) $row['merchant_id'] !== 0) {
            $this->response->error('无权操作商户文章下的评论');
        }
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套后台框架壳。
     */
    private function renderList(): void
    {
        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('blog_comment');
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/blog_comment.php', $this->user(), get_defined_vars());
    }
}
