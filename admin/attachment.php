<?php

declare(strict_types=1);

/**
 * 资源管理控制器。
 *
 * 统一管理所有上传的附件（图片、文件等）。
 * 数据存储于 em_attachment 表。
 */
require __DIR__ . '/global.php';

adminRequireLogin();

$user = $adminUser;
$siteName = Config::get('sitename', 'EMSHOP');

// ============================================================
// POST 请求处理
// ============================================================
if (Request::isPost()) {
    try {
        $action = (string) Input::post('_action', '');

        // list 请求不需要 CSRF 验证
        if ($action !== 'list' && $action !== 'stats') {
            $csrf = (string) Input::post('csrf_token', '');
            if (!Csrf::validate($csrf)) {
                Response::error('请求已失效，请刷新页面后重试');
            }
        }

        require EM_ROOT . '/include/model/AttachmentModel.php';
        $model = new AttachmentModel();

        switch ($action) {
            case 'list':
                $page = max(1, (int) Input::post('page', 1));
                $pageSize = max(1, min(100, (int) Input::post('limit', 24)));
                $keyword = trim((string) Input::post('keyword', ''));
                $context = trim((string) Input::post('context', ''));
                $fileType = trim((string) Input::post('file_type', '')); // image / file / all

                $offset = ($page - 1) * $pageSize;

                $userId = (int) $adminUser['id'];

                // 构造 WHERE 条件
                $conditions = ['`user_id` = :user_id'];
                $params = ['user_id' => $userId, 'limit' => $pageSize, 'offset' => $offset];

                if ($keyword !== '') {
                    $conditions[] = '`file_name` LIKE :keyword';
                    $params['keyword'] = '%' . $keyword . '%';
                }

                if ($context !== '' && $context !== 'all') {
                    $conditions[] = '`context` = :context';
                    $params['context'] = $context;
                }

                if ($fileType === 'image') {
                    $conditions[] = '`mime_type` LIKE :mime_img';
                    $params['mime_img'] = 'image/%';
                } elseif ($fileType === 'file') {
                    $conditions[] = '`mime_type` NOT LIKE :mime_img';
                    $params['mime_img'] = 'image/%';
                }

                $where = implode(' AND ', $conditions);

                $sql = sprintf(
                    'SELECT * FROM `%s` WHERE %s ORDER BY `created_at` DESC LIMIT :limit OFFSET :offset',
                    Database::prefix() . 'attachment',
                    $where
                );
                $rows = Database::query($sql, $params);

                $countSql = sprintf(
                    'SELECT COUNT(*) as total FROM `%s` WHERE %s',
                    Database::prefix() . 'attachment',
                    $where
                );
                $total = (int) Database::fetchOne($countSql, $params)['total'];

                // 计算统计数据
                $totalSize = (int) Database::fetchOne(
                    sprintf('SELECT COALESCE(SUM(`file_size`), 0) as total_size FROM `%s` WHERE `user_id` = :uid', Database::prefix() . 'attachment'),
                    ['uid' => $userId]
                )['total_size'];

                Response::success('', [
                    'data' => $rows,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $pageSize,
                    'csrf_token' => Csrf::token(),
                    'stats' => [
                        'total' => $total,
                        'total_size' => $totalSize,
                        'total_size_fmt' => formatFileSize($totalSize),
                    ],
                ]);
                break;

            case 'stats':
                $userId = (int) $adminUser['id'];
                $prefix = Database::prefix() . 'attachment';

                $total = (int) Database::fetchOne(
                    sprintf('SELECT COUNT(*) as cnt FROM `%s` WHERE `user_id` = :uid', $prefix),
                    ['uid' => $userId]
                )['cnt'];

                $totalSize = (int) Database::fetchOne(
                    sprintf('SELECT COALESCE(SUM(`file_size`), 0) as sz FROM `%s` WHERE `user_id` = :uid', $prefix),
                    ['uid' => $userId]
                )['sz'];

                $imageCount = (int) Database::fetchOne(
                    sprintf("SELECT COUNT(*) as cnt FROM `%s` WHERE `user_id` = :uid AND `mime_type` LIKE 'image/%%'", $prefix),
                    ['uid' => $userId]
                )['cnt'];

                Response::success('', [
                    'total' => $total,
                    'total_size' => $totalSize,
                    'total_size_fmt' => formatFileSize($totalSize),
                    'image_count' => $imageCount,
                ]);
                break;

            case 'delete':
                $id = (int) Input::post('id', 0);
                if ($id <= 0) {
                    Response::error('无效的附件ID');
                }

                $attachment = $model->findById($id);
                if ($attachment === null) {
                    Response::error('附件不存在');
                }

                // 验证归属
                if ((int) $attachment['user_id'] !== (int) $adminUser['id']) {
                    Response::error('无权限操作该附件');
                }

                // 删除物理文件
                $physicalPath = EM_ROOT . $attachment['file_path'];
                if (file_exists($physicalPath)) {
                    @unlink($physicalPath);
                }

                // 删除数据库记录
                $model->delete($id);

                $csrfToken = Csrf::refresh();
                Response::success('删除成功', ['csrf_token' => $csrfToken]);
                break;

            case 'batchDelete':
                $idsRaw = Input::post('ids', '');
                if ($idsRaw === '') {
                    Response::error('请选择要删除的附件');
                }
                $ids = array_filter(array_map('intval', explode(',', $idsRaw)));
                if ($ids === []) {
                    Response::error('无效的附件ID');
                }

                $userId = (int) $adminUser['id'];
                $prefix = Database::prefix() . 'attachment';

                // 查询待删除的附件
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $attachments = Database::query(
                    sprintf('SELECT * FROM `%s` WHERE `id` IN (%s) AND `user_id` = ?', $prefix, $placeholders),
                    array_merge($ids, [$userId])
                );

                // 删除物理文件
                foreach ($attachments as $att) {
                    $physicalPath = EM_ROOT . $att['file_path'];
                    if (file_exists($physicalPath)) {
                        @unlink($physicalPath);
                    }
                }

                // 批量删除数据库记录
                Database::execute(
                    sprintf('DELETE FROM `%s` WHERE `id` IN (%s) AND `user_id` = ?', $prefix, $placeholders),
                    array_merge($ids, [$userId])
                );

                $csrfToken = Csrf::refresh();
                Response::success('已删除 ' . count($attachments) . ' 个附件', ['csrf_token' => $csrfToken]);
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

// ============================================================
// 正常模式：渲染完整后台页面
// ============================================================
$csrfToken = Csrf::token();

// 获取统计数据
$prefix = Database::prefix() . 'attachment';
$userId = (int) $adminUser['id'];
$total = (int) Database::fetchOne(
    sprintf('SELECT COUNT(*) as cnt FROM `%s` WHERE `user_id` = ?', $prefix),
    [$userId]
)['cnt'];
$totalSize = (int) Database::fetchOne(
    sprintf('SELECT COALESCE(SUM(`file_size`), 0) as sz FROM `%s` WHERE `user_id` = ?', $prefix),
    [$userId]
)['sz'];
$imageCount = (int) Database::fetchOne(
    sprintf("SELECT COUNT(*) as cnt FROM `%s` WHERE `user_id` = ? AND `mime_type` LIKE 'image/%%'", $prefix),
    [$userId]
)['cnt'];

$stats = [
    'total' => $total,
    'total_size' => $totalSize,
    'total_size_fmt' => formatFileSize($totalSize),
    'image_count' => $imageCount,
];

if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/attachment.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/attachment.php';
    require __DIR__ . '/index.php';
}

/**
 * 格式化文件大小。
 */
function formatFileSize(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    if ($bytes < 1024 * 1024 * 1024) {
        return round($bytes / 1024 / 1024, 2) . ' MB';
    }
    return round($bytes / 1024 / 1024 / 1024, 2) . ' GB';
}
