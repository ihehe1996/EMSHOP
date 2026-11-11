<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 媒体选择器（/admin/media）。
 *
 * GET 渲染选择器弹窗；POST action=list 返回当前管理员的附件列表（只读）。
 * 弹窗视图通过 window.TEMPLATE_MEDIA_URL 取本地址。
 */
final class MediaController extends BaseAdminController
{
    public function index(): void
    {
        if (\Request::isGet()) {
            $this->render('popup/media');
            return;
        }

        if (\Request::isPost()) {
            try {
                $action = (string) \Input::post('action', '');

                switch ($action) {
                    case 'list':
                        // list 是只读查询，不需要 CSRF 校验
                        $page = max(1, (int) \Input::post('page', 1));
                        $pageSize = max(1, min(100, (int) \Input::post('limit', 20)));
                        $context = (string) \Input::post('context', 'default');

                        $offset = ($page - 1) * $pageSize;

                        $admin = $this->user();
                        $userId = (int) ($admin['id'] ?? 0);

                        if ($context === 'all') {
                            $sql = sprintf(
                                'SELECT * FROM `%s` WHERE `user_id` = :user_id ORDER BY `created_at` DESC LIMIT :limit OFFSET :offset',
                                \Database::prefix() . 'attachment'
                            );
                            $rows = \Database::query($sql, ['user_id' => $userId, 'limit' => $pageSize, 'offset' => $offset]);

                            $countSql = sprintf(
                                'SELECT COUNT(*) as total FROM `%s` WHERE `user_id` = :user_id',
                                \Database::prefix() . 'attachment'
                            );
                            $total = (int) \Database::fetchOne($countSql, ['user_id' => $userId])['total'];
                        } else {
                            $sql = sprintf(
                                'SELECT * FROM `%s` WHERE `user_id` = :user_id AND `context` = :context ORDER BY `created_at` DESC LIMIT :limit OFFSET :offset',
                                \Database::prefix() . 'attachment'
                            );
                            $rows = \Database::query($sql, ['user_id' => $userId, 'context' => $context, 'limit' => $pageSize, 'offset' => $offset]);

                            $countSql = sprintf(
                                'SELECT COUNT(*) as total FROM `%s` WHERE `user_id` = :user_id AND `context` = :context',
                                \Database::prefix() . 'attachment'
                            );
                            $total = (int) \Database::fetchOne($countSql, ['user_id' => $userId, 'context' => $context])['total'];
                        }

                        \Response::success('', [
                            'data' => $rows,
                            'total' => $total,
                            'page' => $page,
                            'limit' => $pageSize,
                        ]);
                        break;

                    default:
                        \Response::error('未知操作');
                }
            } catch (\RuntimeException $e) {
                \Response::error($e->getMessage());
            } catch (\Throwable $e) {
                \Response::error('系统繁忙，请稍后再试');
            }
        }

        \Response::error('仅支持 POST 请求');
    }
}
