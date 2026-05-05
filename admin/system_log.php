<?php

declare(strict_types=1);

/**
 * 系统日志管理控制器。
 *
 * 数据存储于 em_system_log 表。
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

        // list/stats 请求不需要 CSRF 验证
        if (!in_array($action, ['list', 'stats'], true)) {
            $csrf = (string) Input::post('csrf_token', '');
            if (!Csrf::validate($csrf)) {
                Response::error('请求已失效，请刷新页面后重试');
            }
        }

        require EM_ROOT . '/include/model/SystemLogModel.php';
        $model = new SystemLogModel();

        switch ($action) {
            case 'list':
                $page = max(1, (int) Input::post('page', 1));
                $pageSize = max(1, min(100, (int) Input::post('limit', 20)));
                $level = trim((string) Input::post('level', ''));
                $type = trim((string) Input::post('type', ''));
                $keyword = trim((string) Input::post('keyword', ''));

                $rows = $model->list($level, $type, $keyword, $page, $pageSize);
                $total = $model->count($level, $type, $keyword);

                Response::success('', [
                    'data' => array_values($rows),
                    'total' => $total,
                    'page' => $page,
                    'limit' => $pageSize,
                    'csrf_token' => Csrf::token(),
                ]);
                break;

            case 'stats':
                $statsByType = $model->statsByType();
                $totalCount = $model->count();

                Response::success('', [
                    'total' => $totalCount,
                    'by_type' => $statsByType,
                ]);
                break;

            case 'view':
                $id = (int) Input::post('id', 0);
                if ($id <= 0) {
                    Response::error('无效的日志ID');
                }

                $log = $model->findById($id);
                if ($log === null) {
                    Response::error('日志不存在');
                }

                // 解析 detail JSON
                if (!empty($log['detail'])) {
                    $log['detail_parsed'] = json_decode($log['detail'], true);
                }

                Response::success('', ['data' => $log]);
                break;

            case 'delete':
                $id = (int) Input::post('id', 0);
                if ($id <= 0) {
                    Response::error('无效的日志ID');
                }

                $model->delete($id);

                $csrfToken = Csrf::refresh();
                Response::success('删除成功', ['csrf_token' => $csrfToken]);
                break;

            case 'batchDelete':
                $idsRaw = Input::post('ids', '');
                if ($idsRaw === '') {
                    Response::error('请选择要删除的日志');
                }
                $ids = array_filter(array_map('intval', explode(',', $idsRaw)));
                if ($ids === []) {
                    Response::error('无效的日志ID');
                }

                $deleted = $model->batchDelete($ids);

                $csrfToken = Csrf::refresh();
                Response::success('已删除 ' . $deleted . ' 条日志', ['csrf_token' => $csrfToken]);
                break;

            case 'cleanup':
                $days = max(1, (int) Input::post('days', 30));
                $deleted = $model->cleanup($days);

                $csrfToken = Csrf::refresh();
                Response::success('已清理 ' . $deleted . ' 条 ' . $days . ' 天前的日志', ['csrf_token' => $csrfToken]);
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
require EM_ROOT . '/include/model/SystemLogModel.php';
$model = new SystemLogModel();
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

if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/system_log.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/system_log.php';
    require __DIR__ . '/index.php';
}
