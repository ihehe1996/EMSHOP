<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 媒体选择器（{入口}/media）。
 *
 * GET 渲染选择器弹窗；POST action=list 返回当前管理员的附件列表（只读）。
 * 弹窗视图通过 window.TEMPLATE_MEDIA_URL 取本地址。
 */
final class MediaController extends BaseAdminController
{
    public function index(): void
    {
        if ($this->request->isGet()) {
            $this->render('popup/media');
            return;
        }

        if ($this->request->isPost()) {
            try {
                $action = (string) $this->input->post('action', '');

                switch ($action) {
                    case 'list':
                        // list 是只读查询，不需要 CSRF 校验
                        $page = max(1, (int) $this->input->post('page', 1));
                        $pageSize = max(1, min(100, (int) $this->input->post('limit', 20)));
                        $context = (string) $this->input->post('context', 'default');

                        $offset = ($page - 1) * $pageSize;

                        $admin = $this->user();
                        $userId = (int) ($admin['id'] ?? 0);

                        if ($context === 'all') {
                            $sql = sprintf(
                                'SELECT * FROM `%s` WHERE `user_id` = :user_id ORDER BY `created_at` DESC LIMIT :limit OFFSET :offset',
                                $this->db->prefix() . 'attachment'
                            );
                            $rows = $this->db->query($sql, ['user_id' => $userId, 'limit' => $pageSize, 'offset' => $offset]);

                            $countSql = sprintf(
                                'SELECT COUNT(*) as total FROM `%s` WHERE `user_id` = :user_id',
                                $this->db->prefix() . 'attachment'
                            );
                            $total = (int) $this->db->fetchOne($countSql, ['user_id' => $userId])['total'];
                        } else {
                            $sql = sprintf(
                                'SELECT * FROM `%s` WHERE `user_id` = :user_id AND `context` = :context ORDER BY `created_at` DESC LIMIT :limit OFFSET :offset',
                                $this->db->prefix() . 'attachment'
                            );
                            $rows = $this->db->query($sql, ['user_id' => $userId, 'context' => $context, 'limit' => $pageSize, 'offset' => $offset]);

                            $countSql = sprintf(
                                'SELECT COUNT(*) as total FROM `%s` WHERE `user_id` = :user_id AND `context` = :context',
                                $this->db->prefix() . 'attachment'
                            );
                            $total = (int) $this->db->fetchOne($countSql, ['user_id' => $userId, 'context' => $context])['total'];
                        }

                        $this->response->success('', [
                            'data' => $rows,
                            'total' => $total,
                            'page' => $page,
                            'limit' => $pageSize,
                        ]);
                        break;

                    default:
                        $this->response->error('未知操作');
                }
            } catch (\RuntimeException $e) {
                $this->response->error($e->getMessage());
            } catch (\Throwable $e) {
                $this->response->error('系统繁忙，请稍后再试');
            }
        }

        $this->response->error('仅支持 POST 请求');
    }
}
