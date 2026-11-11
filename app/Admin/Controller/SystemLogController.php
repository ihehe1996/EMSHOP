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
 * 过渡期列表页仍套用旧后台框架壳（admin/view/index.php）。
 */
final class SystemLogController extends BaseAdminController
{
    public function index(): void
    {
        if (\Request::isPost()) {
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
            $action = (string) \Input::post('_action', '');

            // list/stats 请求不需要 CSRF 验证
            if (!in_array($action, ['list', 'stats'], true)) {
                $csrf = (string) \Input::post('csrf_token', '');
                if (!\Csrf::validate($csrf)) {
                    \Response::error('请求已失效，请刷新页面后重试');
                }
            }

            $model = new \SystemLogModel();

            switch ($action) {
                case 'list':
                    $page = max(1, (int) \Input::post('page', 1));
                    $pageSize = max(1, min(100, (int) \Input::post('limit', 20)));
                    $level = trim((string) \Input::post('level', ''));
                    $type = trim((string) \Input::post('type', ''));
                    $keyword = trim((string) \Input::post('keyword', ''));

                    $rows = $model->list($level, $type, $keyword, $page, $pageSize);
                    $total = $model->count($level, $type, $keyword);

                    \Response::success('', [
                        'data' => array_values($rows),
                        'total' => $total,
                        'page' => $page,
                        'limit' => $pageSize,
                        'csrf_token' => \Csrf::token(),
                    ]);
                    break;

                case 'stats':
                    $statsByType = $model->statsByType();
                    $totalCount = $model->count();

                    \Response::success('', [
                        'total' => $totalCount,
                        'by_type' => $statsByType,
                    ]);
                    break;

                case 'view':
                    $id = (int) \Input::post('id', 0);
                    if ($id <= 0) {
                        \Response::error('无效的日志ID');
                    }

                    $log = $model->findById($id);
                    if ($log === null) {
                        \Response::error('日志不存在');
                    }

                    // 解析 detail JSON
                    if (!empty($log['detail'])) {
                        $log['detail_parsed'] = json_decode($log['detail'], true);
                    }

                    \Response::success('', ['data' => $log]);
                    break;

                case 'delete':
                    $id = (int) \Input::post('id', 0);
                    if ($id <= 0) {
                        \Response::error('无效的日志ID');
                    }

                    $model->delete($id);

                    $csrfToken = \Csrf::refresh();
                    \Response::success('删除成功', ['csrf_token' => $csrfToken]);
                    break;

                case 'batchDelete':
                    $idsRaw = \Input::post('ids', '');
                    if ($idsRaw === '') {
                        \Response::error('请选择要删除的日志');
                    }
                    $ids = array_filter(array_map('intval', explode(',', (string) $idsRaw)));
                    if ($ids === []) {
                        \Response::error('无效的日志ID');
                    }

                    $deleted = $model->batchDelete($ids);

                    $csrfToken = \Csrf::refresh();
                    \Response::success('已删除 ' . $deleted . ' 条日志', ['csrf_token' => $csrfToken]);
                    break;

                case 'cleanup':
                    $days = max(1, (int) \Input::post('days', 30));
                    $deleted = $model->cleanup($days);

                    $csrfToken = \Csrf::refresh();
                    \Response::success('已清理 ' . $deleted . ' 条 ' . $days . ' 天前的日志', ['csrf_token' => $csrfToken]);
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

    /**
     * 列表页：Pjax 输出片段，直接访问套旧后台框架壳。
     */
    private function renderList(): void
    {
        $model = new \SystemLogModel();
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

        if (\Request::isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('system_log', ['stats' => $stats]);
            echo '</div>';
            return;
        }

        require_once EM_ROOT . '/include/lib/AdminLang.php';

        $user = $this->user();
        $siteName = (string) \Config::get('sitename', 'EMSHOP');
        $languages = (new \LanguageModel())->getEnabled();
        $emServerHardRestartPending = is_file(EM_ROOT . '/.server') && \WorkerHeartbeat::hostAlive();
        $adminContentView = dirname(__DIR__) . '/View/system_log.php';

        require EM_ROOT . '/admin/view/index.php';
    }
}
