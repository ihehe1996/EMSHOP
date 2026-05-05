<?php

declare(strict_types=1);

require_once __DIR__ . '/global.php';

/**
 * 商户后台 —— 媒体库选择器（iframe 弹窗内使用）。
 *
 * 和主站 admin/media.php 结构一致，关键差异：
 *   - 身份：merchantRequireLogin（用 $frontUser['id'] 作 attachment.user_id 过滤条件）
 *   - view：user/merchant/view/media.php（主站版本 URL 硬编码指向 /admin/...，商户版独立一份）
 */
merchantRequireLogin();

if (Request::isGet()) {
    include __DIR__ . '/view/media.php';
    return;
}

if (Request::isPost()) {
    try {
        $action = (string) Input::post('action', '');

        switch ($action) {
            case 'list':
                $page = max(1, (int) Input::post('page', 1));
                $pageSize = max(1, min(100, (int) Input::post('limit', 20)));
                $context = (string) Input::post('context', 'default');

                $userId = (int) ($frontUser['id'] ?? 0);
                if ($userId <= 0) Response::error('未登录');

                $offset = ($page - 1) * $pageSize;
                $attTable = Database::prefix() . 'attachment';
                if ($context === 'all') {
                    $rows = Database::query(
                        "SELECT * FROM `{$attTable}` WHERE `user_id` = :user_id ORDER BY `created_at` DESC LIMIT :limit OFFSET :offset",
                        ['user_id' => $userId, 'limit' => $pageSize, 'offset' => $offset]
                    );
                    $total = (int) Database::fetchOne(
                        "SELECT COUNT(*) AS total FROM `{$attTable}` WHERE `user_id` = :user_id",
                        ['user_id' => $userId]
                    )['total'];
                } else {
                    $rows = Database::query(
                        "SELECT * FROM `{$attTable}` WHERE `user_id` = :user_id AND `context` = :context ORDER BY `created_at` DESC LIMIT :limit OFFSET :offset",
                        ['user_id' => $userId, 'context' => $context, 'limit' => $pageSize, 'offset' => $offset]
                    );
                    $total = (int) Database::fetchOne(
                        "SELECT COUNT(*) AS total FROM `{$attTable}` WHERE `user_id` = :user_id AND `context` = :context",
                        ['user_id' => $userId, 'context' => $context]
                    )['total'];
                }

                Response::success('', [
                    'data'  => $rows,
                    'total' => $total,
                    'page'  => $page,
                    'limit' => $pageSize,
                ]);
                break;

            default:
                Response::error('未知操作');
        }
    } catch (RuntimeException $e) {
        Response::error($e->getMessage());
    } catch (Throwable $e) {
        Response::error('系统繁忙，请稍后再试');
    }
}

Response::error('仅支持 POST 请求');
