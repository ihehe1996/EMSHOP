<?php
declare(strict_types=1);

require __DIR__ . '/global.php';

// 后台登录校验
adminRequireLogin();

$action = $_GET['_action'] ?? $_POST['_action'] ?? '';

// 非 list 操作统一验证 CSRF
if ($action !== 'list' && $action !== '') {
    $csrf = (string)(($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? ''));
    if (!Csrf::validate($csrf)) {
        Response::error('请求已失效，请刷新页面后重试');
    }
}

// 列表接口（供 layui table 使用）
if ($action === 'list') {
    $page    = (int)($_POST['page'] ?? 1);
    $limit   = (int)($_POST['limit'] ?? 20);
    $keyword = trim($_POST['keyword'] ?? '');
    $status  = isset($_POST['status']) && $_POST['status'] !== '' ? (int)$_POST['status'] : null;

    // 主站后台只看主站文章下的评论
    $where = ['merchant_id' => 0];
    if ($keyword !== '')   $where['keyword'] = $keyword;
    if ($status !== null)  $where['status'] = $status;

    $result = BlogCommentModel::getAdminList($where, $page, $limit);
    $counts = BlogCommentModel::getStatusCounts(0);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'code'       => 0,
        'msg'        => '',
        'count'      => $result['total'],
        'data'       => $result['list'],
        'csrf_token' => Csrf::token(),
        'tab_counts' => $counts,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 主站只能操作 merchant_id=0 文章下的评论：通过 JOIN blog 表过滤
$assertCommentBelongsToMain = function (int $commentId): array {
    $prefix = Database::prefix();
    $row = Database::fetchOne(
        "SELECT c.id, b.merchant_id FROM {$prefix}blog_comment c
         LEFT JOIN {$prefix}blog b ON c.blog_id = b.id
         WHERE c.id = ? LIMIT 1",
        [$commentId]
    );
    if (!$row) {
        Response::error('评论不存在');
    }
    if ((int) $row['merchant_id'] !== 0) {
        Response::error('无权操作商户文章下的评论');
    }
    return $row;
};

// 审核通过
if ($action === 'approve') {
    $id = (int)($_POST['id'] ?? 0);
    $assertCommentBelongsToMain($id);
    BlogCommentModel::updateStatus($id, 1);
    Response::success('已通过', ['csrf_token' => Csrf::token()]);
}

// 拒绝
if ($action === 'reject') {
    $id = (int)($_POST['id'] ?? 0);
    $assertCommentBelongsToMain($id);
    BlogCommentModel::updateStatus($id, 2);
    Response::success('已拒绝', ['csrf_token' => Csrf::token()]);
}

// 删除评论（逻辑删除）
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $assertCommentBelongsToMain($id);
    if (BlogCommentModel::delete($id)) {
        Response::success('删除成功', ['csrf_token' => Csrf::token()]);
    } else {
        Response::error('删除失败');
    }
}

// 批量操作
if ($action === 'batch') {
    $batchAction = $_POST['batch_action'] ?? '';
    $ids = is_array($_POST['ids'] ?? null)
        ? array_map('intval', $_POST['ids'])
        : (json_decode($_POST['ids'] ?? '[]', true) ?: []);
    if (empty($ids)) {
        Response::error('请选择评论');
    }

    // 限定主站文章下的评论
    $prefix = Database::prefix();
    $allowedRows = Database::query(
        "SELECT c.id FROM {$prefix}blog_comment c
         LEFT JOIN {$prefix}blog b ON c.blog_id = b.id
         WHERE b.merchant_id = 0 AND c.id IN (" . implode(',', array_map('intval', $ids)) . ")"
    );
    $allowedIds = array_map(fn($r) => (int) $r['id'], $allowedRows);
    if (empty($allowedIds)) {
        Response::error('所选评论均无权操作');
    }

    if ($batchAction === 'approve') {
        BlogCommentModel::batchUpdateStatus($allowedIds, 1);
    } elseif ($batchAction === 'reject') {
        BlogCommentModel::batchUpdateStatus($allowedIds, 2);
    } elseif ($batchAction === 'delete') {
        BlogCommentModel::batchDelete($allowedIds);
    } else {
        Response::error('未知操作');
    }

    Response::success('批量操作成功', ['csrf_token' => Csrf::token()]);
}

// 默认：显示列表页面
if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/blog_comment.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/blog_comment.php';
    require __DIR__ . '/index.php';
}
