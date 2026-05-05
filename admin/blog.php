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

// 列表接口（供 layui table 使用，layui table 要求 code=0）
if ($action === 'list') {
    $page = (int)($_POST['page'] ?? 1);
    $limit = (int)($_POST['limit'] ?? 20);
    $keyword = trim($_POST['keyword'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $status = isset($_POST['status']) && $_POST['status'] !== '' ? (int)$_POST['status'] : null;

    // 主站后台只看 merchant_id=0
    $where = ['merchant_id' => 0];
    if ($keyword) $where['keyword'] = $keyword;
    if ($category_id) $where['category_id'] = $category_id;
    if ($status !== null) $where['status'] = $status;

    $result = BlogModel::getList($where, $page, $limit, 'a.is_top DESC, a.sort ASC, a.id DESC');

    // 统计各状态的文章数量
    $countWhere = ['merchant_id' => 0];
    if ($keyword) $countWhere['keyword'] = $keyword;
    if ($category_id) $countWhere['category_id'] = $category_id;

    $countAll = BlogModel::getList($countWhere, 1, 1)['total'];
    $countPublished = BlogModel::getList(array_merge($countWhere, ['status' => 1]), 1, 1)['total'];
    $countDraft = BlogModel::getList(array_merge($countWhere, ['status' => 0]), 1, 1)['total'];

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'code' => 0,
        'msg' => '',
        'count' => $result['total'],
        'data' => $result['list'],
        'csrf_token' => Csrf::token(),
        'tab_counts' => [
            'all' => $countAll,
            'published' => $countPublished,
            'draft' => $countDraft,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 切换发布状态
if ($action === 'toggle_status') {
    $id = (int)($_POST['id'] ?? 0);
    $article = BlogModel::getById($id);
    if (!$article) {
        Response::error('文章不存在');
    }
    if ((int) $article['merchant_id'] !== 0) {
        Response::error('无权操作商户文章');
    }
    $newStatus = (int)$article['status'] ? 0 : 1;
    BlogModel::update($id, ['status' => $newStatus]);
    // article_count 只统计已发布文章，状态切换后需刷新计数
    BlogTagModel::refreshAllCounts();
    Response::success('状态已更新', ['csrf_token' => Csrf::token()]);
}

// 切换置顶
if ($action === 'toggle_top') {
    $id = (int)($_POST['id'] ?? 0);
    $article = BlogModel::getById($id);
    if (!$article) {
        Response::error('文章不存在');
    }
    if ((int) $article['merchant_id'] !== 0) {
        Response::error('无权操作商户文章');
    }
    $newTop = (int)$article['is_top'] ? 0 : 1;
    BlogModel::update($id, ['is_top' => $newTop]);
    Response::success('状态已更新', ['csrf_token' => Csrf::token()]);
}

// 删除文章（逻辑删除）
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $article = BlogModel::getById($id);
    if (!$article) {
        Response::error('文章不存在');
    }
    if ((int) $article['merchant_id'] !== 0) {
        Response::error('无权删除商户文章');
    }
    if (BlogModel::delete($id)) {
        // 删除文章后同步刷新标签的 article_count
        BlogTagModel::refreshAllCounts();
        Response::success('删除成功', ['csrf_token' => Csrf::token()]);
    } else {
        Response::error('删除失败');
    }
}

// 批量操作
if ($action === 'batch') {
    $batchAction = $_POST['batch_action'] ?? '';
    $ids = is_array($_POST['ids'] ?? null) ? array_map('intval', $_POST['ids']) : (json_decode($_POST['ids'] ?? '[]', true) ?: []);
    if (empty($ids)) {
        Response::error('请选择文章');
    }
    // 主站批量操作只允许针对 merchant_id=0 的文章
    $prefix = Database::prefix();
    $allowedRows = Database::query(
        "SELECT id FROM {$prefix}blog WHERE merchant_id = 0 AND id IN (" . implode(',', array_map('intval', $ids)) . ")"
    );
    $allowedIds = array_map(fn($r) => (int) $r['id'], $allowedRows);
    if (empty($allowedIds)) {
        Response::error('所选文章均无权操作');
    }
    $failed = count($ids) - count($allowedIds);
    foreach ($allowedIds as $id) {
        try {
            if ($batchAction === 'publish') {
                BlogModel::update($id, ['status' => 1]);
            } elseif ($batchAction === 'draft') {
                BlogModel::update($id, ['status' => 0]);
            } elseif ($batchAction === 'delete') {
                BlogModel::delete($id);
            } else {
                $failed++;
                break;
            }
        } catch (\Throwable $e) {
            $failed++;
        }
    }
    // 任一批量动作（发布/转草稿/删除）都会影响标签的 article_count，统一刷一次
    BlogTagModel::refreshAllCounts();
    if ($failed === 0) {
        Response::success('批量操作成功', ['csrf_token' => Csrf::token()]);
    } else {
        Response::error('批量操作部分失败（' . $failed . '/' . count($ids) . '）');
    }
}

// 默认：显示列表页面（分类下拉只显示主站分类）
$categories = Database::query(
    "SELECT * FROM " . Database::prefix() . "blog_category WHERE status = 1 AND merchant_id = 0 ORDER BY parent_id ASC, sort ASC"
);

if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/blog.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/blog.php';
    require __DIR__ . '/index.php';
}
