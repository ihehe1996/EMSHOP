<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 系统日志管理（/admin/system_log）。
 *
 * 与旧 admin/system_log.php 行为一致：
 *   - _action=list 分页列表、stats 统计、view 详情（只读）
 *   - delete / batchDelete / cleanup 清理
 *
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class SystemLogController extends BaseAdminController
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
     * 列表 / 统计 / 详情 / 删除。
     */
    private function handlePost(): void
    {
        try {
            $action = (string) $this->input->post('_action', '');

            // list/stats 请求不需要 CSRF 验证
            if (!in_array($action, ['list', 'stats'], true)) {
                $csrf = (string) $this->input->post('csrf_token', '');
                if (!$this->csrf->validate($csrf)) {
                    $this->response->error('请求已失效，请刷新页面后重试');
                }
            }

            $model = new \App\Model\SystemLogModel();

            switch ($action) {
                case 'list':
                    $page = max(1, (int) $this->input->post('page', 1));
                    $pageSize = max(1, min(100, (int) $this->input->post('limit', 20)));
                    $level = trim((string) $this->input->post('level', ''));
                    $type = trim((string) $this->input->post('type', ''));
                    $keyword = trim((string) $this->input->post('keyword', ''));

                    $rows = $model->list($level, $type, $keyword, $page, $pageSize);
                    $total = $model->count($level, $type, $keyword);

                    $this->response->success('', [
                        'data' => array_values($rows),
                        'total' => $total,
                        'page' => $page,
                        'limit' => $pageSize,
                        'csrf_token' => $this->csrf->token(),
                    ]);
                    break;

                case 'stats':
                    $statsByType = $model->statsByType();
                    $totalCount = $model->count();

                    $this->response->success('', [
                        'total' => $totalCount,
                        'by_type' => $statsByType,
                    ]);
                    break;

                case 'view':
                    $id = (int) $this->input->post('id', 0);
                    if ($id <= 0) {
                        $this->response->error('无效的日志ID');
                    }

                    $log = $model->findById($id);
                    if ($log === null) {
                        $this->response->error('日志不存在');
                    }

                    // 解析 detail JSON
                    if (!empty($log['detail'])) {
                        $log['detail_parsed'] = json_decode($log['detail'], true);
                    }

                    $this->response->success('', ['data' => $log]);
                    break;

                case 'delete':
                    $id = (int) $this->input->post('id', 0);
                    if ($id <= 0) {
                        $this->response->error('无效的日志ID');
                    }

                    $model->delete($id);

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('删除成功', ['csrf_token' => $csrfToken]);
                    break;

                case 'batchDelete':
                    $idsRaw = $this->input->post('ids', '');
                    if ($idsRaw === '') {
                        $this->response->error('请选择要删除的日志');
                    }
                    $ids = array_filter(array_map('intval', explode(',', (string) $idsRaw)));
                    if ($ids === []) {
                        $this->response->error('无效的日志ID');
                    }

                    $deleted = $model->batchDelete($ids);

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('已删除 ' . $deleted . ' 条日志', ['csrf_token' => $csrfToken]);
                    break;

                case 'cleanup':
                    $days = max(1, (int) $this->input->post('days', 30));
                    $deleted = $model->cleanup($days);

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('已清理 ' . $deleted . ' 条 ' . $days . ' 天前的日志', ['csrf_token' => $csrfToken]);
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
     * 列表页：Pjax 输出片段，直接访问套后台框架壳。
     */
    private function renderList(): void
    {
        $model = new \App\Model\SystemLogModel();
        $statsByType = $model->statsByType();
        $totalCount = $model->count();

        $stats = [
            'total' => $totalCount,
            'login' => $statsByType['login'] ?? 0,
            'admin_operation' => $statsByType['admin_operation'] ?? 0,
            'system' => $statsByType['system'] ?? 0,
            'error' => 0,
            'warning' => 0,
        ];

        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('system_log', ['stats' => $stats]);
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/system_log.php', $this->user(), get_defined_vars());
    }
}
