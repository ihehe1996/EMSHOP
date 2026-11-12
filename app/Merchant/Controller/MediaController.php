<?php

declare(strict_types=1);

namespace App\Merchant\Controller;

/**
 * 商户后台 - 媒体库选择器（/user/merchant/media）。
 */
final class MediaController extends MerchantBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        $currentMerchant = $this->merchant();
        $merchantLevel = $this->level();
        if ($this->request->isGet()) {
            $this->render('media');
            return;
        }

        if ($this->request->isPost()) {
            try {
                $action = (string) $this->input->post('action', '');

                switch ($action) {
                    case 'list':
                        $page = max(1, (int) $this->input->post('page', 1));
                        $pageSize = max(1, min(100, (int) $this->input->post('limit', 20)));
                        $context = (string) $this->input->post('context', 'default');

                        $userId = (int) ($frontUser['id'] ?? 0);
                        if ($userId <= 0) $this->response->error('未登录');

                        $offset = ($page - 1) * $pageSize;
                        $attTable = $this->db->prefix() . 'attachment';
                        if ($context === 'all') {
                            $rows = $this->db->query(
                                "SELECT * FROM `{$attTable}` WHERE `user_id` = :user_id ORDER BY `created_at` DESC LIMIT :limit OFFSET :offset",
                                ['user_id' => $userId, 'limit' => $pageSize, 'offset' => $offset]
                            );
                            $total = (int) $this->db->fetchOne(
                                "SELECT COUNT(*) AS total FROM `{$attTable}` WHERE `user_id` = :user_id",
                                ['user_id' => $userId]
                            )['total'];
                        } else {
                            $rows = $this->db->query(
                                "SELECT * FROM `{$attTable}` WHERE `user_id` = :user_id AND `context` = :context ORDER BY `created_at` DESC LIMIT :limit OFFSET :offset",
                                ['user_id' => $userId, 'context' => $context, 'limit' => $pageSize, 'offset' => $offset]
                            );
                            $total = (int) $this->db->fetchOne(
                                "SELECT COUNT(*) AS total FROM `{$attTable}` WHERE `user_id` = :user_id AND `context` = :context",
                                ['user_id' => $userId, 'context' => $context]
                            )['total'];
                        }

                        $this->response->success('', [
                            'data'  => $rows,
                            'total' => $total,
                            'page'  => $page,
                            'limit' => $pageSize,
                        ]);
                        break;

                    default:
                        $this->response->error('未知操作');
                }
            } catch (RuntimeException $e) {
                $this->response->error($e->getMessage());
            } catch (\Throwable $e) {
                $this->response->error('系统繁忙，请稍后再试');
            }
        }

        $this->response->error('仅支持 POST 请求');
    }
}
