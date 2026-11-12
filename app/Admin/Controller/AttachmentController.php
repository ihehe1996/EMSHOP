<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 资源管理（/admin/attachment）。
 *
 * 统一管理当前管理员上传的附件（图片、文件等），数据存于 em_attachment 表。
 * 与旧 admin/attachment.php 行为一致：
 *   - _action=list 分页列表（按 user_id 隔离）
 *   - _action=stats 统计
 *   - _action=delete / batchDelete 删除记录与物理文件
 *
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class AttachmentController extends BaseAdminController
{
    public function index(): void
    {
        if ($this->request->isPost()) {
            $this->handlePost();
            return;
        }

        $this->renderList();
    }

    /**
     * 列表、统计与删除。
     */
    private function handlePost(): void
    {
        try {
            $action = (string) $this->input->post('_action', '');

            // list 请求不需要 CSRF 验证
            if ($action !== 'list' && $action !== 'stats') {
                $csrf = (string) $this->input->post('csrf_token', '');
                if (!$this->csrf->validate($csrf)) {
                    $this->response->error('请求已失效，请刷新页面后重试');
                }
            }

            $model = new \AttachmentModel();
            $admin = $this->user();
            $userId = (int) ($admin['id'] ?? 0);

            switch ($action) {
                case 'list':
                    $page = max(1, (int) $this->input->post('page', 1));
                    $pageSize = max(1, min(100, (int) $this->input->post('limit', 24)));
                    $keyword = trim((string) $this->input->post('keyword', ''));
                    $context = trim((string) $this->input->post('context', ''));
                    $fileType = trim((string) $this->input->post('file_type', '')); // image / file / all

                    $offset = ($page - 1) * $pageSize;

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
                        $this->db->prefix() . 'attachment',
                        $where
                    );
                    $rows = $this->db->query($sql, $params);

                    $countSql = sprintf(
                        'SELECT COUNT(*) as total FROM `%s` WHERE %s',
                        $this->db->prefix() . 'attachment',
                        $where
                    );
                    $total = (int) $this->db->fetchOne($countSql, $params)['total'];

                    // 计算统计数据
                    $totalSize = (int) $this->db->fetchOne(
                        sprintf('SELECT COALESCE(SUM(`file_size`), 0) as total_size FROM `%s` WHERE `user_id` = :uid', $this->db->prefix() . 'attachment'),
                        ['uid' => $userId]
                    )['total_size'];

                    $this->response->success('', [
                        'data' => $rows,
                        'total' => $total,
                        'page' => $page,
                        'limit' => $pageSize,
                        'csrf_token' => $this->csrf->token(),
                        'stats' => [
                            'total' => $total,
                            'total_size' => $totalSize,
                            'total_size_fmt' => $this->formatFileSize($totalSize),
                        ],
                    ]);
                    break;

                case 'stats':
                    $prefix = $this->db->prefix() . 'attachment';

                    $total = (int) $this->db->fetchOne(
                        sprintf('SELECT COUNT(*) as cnt FROM `%s` WHERE `user_id` = :uid', $prefix),
                        ['uid' => $userId]
                    )['cnt'];

                    $totalSize = (int) $this->db->fetchOne(
                        sprintf('SELECT COALESCE(SUM(`file_size`), 0) as sz FROM `%s` WHERE `user_id` = :uid', $prefix),
                        ['uid' => $userId]
                    )['sz'];

                    $imageCount = (int) $this->db->fetchOne(
                        sprintf("SELECT COUNT(*) as cnt FROM `%s` WHERE `user_id` = :uid AND `mime_type` LIKE 'image/%%'", $prefix),
                        ['uid' => $userId]
                    )['cnt'];

                    $this->response->success('', [
                        'total' => $total,
                        'total_size' => $totalSize,
                        'total_size_fmt' => $this->formatFileSize($totalSize),
                        'image_count' => $imageCount,
                    ]);
                    break;

                case 'delete':
                    $id = (int) $this->input->post('id', 0);
                    if ($id <= 0) {
                        $this->response->error('无效的附件ID');
                    }

                    $attachment = $model->findById($id);
                    if ($attachment === null) {
                        $this->response->error('附件不存在');
                    }

                    // 验证归属
                    if ((int) $attachment['user_id'] !== $userId) {
                        $this->response->error('无权限操作该附件');
                    }

                    // 删除物理文件
                    $physicalPath = EM_ROOT . $attachment['file_path'];
                    if (file_exists($physicalPath)) {
                        @unlink($physicalPath);
                    }

                    // 删除数据库记录
                    $model->delete($id);

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('删除成功', ['csrf_token' => $csrfToken]);
                    break;

                case 'batchDelete':
                    $idsRaw = $this->input->post('ids', '');
                    if ($idsRaw === '') {
                        $this->response->error('请选择要删除的附件');
                    }
                    $ids = array_filter(array_map('intval', explode(',', (string) $idsRaw)));
                    if ($ids === []) {
                        $this->response->error('无效的附件ID');
                    }

                    $prefix = $this->db->prefix() . 'attachment';

                    // 查询待删除的附件
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $attachments = $this->db->query(
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
                    $this->db->execute(
                        sprintf('DELETE FROM `%s` WHERE `id` IN (%s) AND `user_id` = ?', $prefix, $placeholders),
                        array_merge($ids, [$userId])
                    );

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('已删除 ' . count($attachments) . ' 个附件', ['csrf_token' => $csrfToken]);
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

    /**
     * 列表页：Pjax 输出片段，直接访问套旧后台框架壳。
     */
    private function renderList(): void
    {
        $admin = $this->user();
        $userId = (int) ($admin['id'] ?? 0);
        $prefix = $this->db->prefix() . 'attachment';

        $total = (int) $this->db->fetchOne(
            sprintf('SELECT COUNT(*) as cnt FROM `%s` WHERE `user_id` = ?', $prefix),
            [$userId]
        )['cnt'];
        $totalSize = (int) $this->db->fetchOne(
            sprintf('SELECT COALESCE(SUM(`file_size`), 0) as sz FROM `%s` WHERE `user_id` = ?', $prefix),
            [$userId]
        )['sz'];
        $imageCount = (int) $this->db->fetchOne(
            sprintf("SELECT COUNT(*) as cnt FROM `%s` WHERE `user_id` = ? AND `mime_type` LIKE 'image/%%'", $prefix),
            [$userId]
        )['cnt'];

        $stats = [
            'total' => $total,
            'total_size' => $totalSize,
            'total_size_fmt' => $this->formatFileSize($totalSize),
            'image_count' => $imageCount,
        ];

        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('attachment', ['stats' => $stats]);
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/attachment.php', $this->user(), get_defined_vars());
    }

    /**
     * 格式化文件大小。
     */
    private function formatFileSize(int $bytes): string
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
}
