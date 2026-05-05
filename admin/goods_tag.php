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

    $where = [];
    if ($keyword !== '') $where['keyword'] = $keyword;

    $result = GoodsTagModel::getAdminList($where, $page, $limit);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'code'       => 0,
        'msg'        => '',
        'count'      => $result['total'],
        'data'       => $result['list'],
        'csrf_token' => Csrf::token(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 保存标签（新增/编辑）
if ($action === 'save') {
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $sort = (int)($_POST['sort'] ?? 0);

    if ($name === '') {
        Response::error('标签名不能为空');
    }

    if (GoodsTagModel::nameExists($name, $id)) {
        Response::error('标签名已存在');
    }

    $data = [
        'name' => $name,
        'sort' => $sort,
    ];

    if ($id > 0) {
        GoodsTagModel::update($id, $data);
    } else {
        $id = GoodsTagModel::create($data);
    }

    Response::success('保存成功', ['id' => $id, 'csrf_token' => Csrf::refresh()]);
}

// 删除标签
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        Response::error('参数错误');
    }
    if (GoodsTagModel::delete($id)) {
        Response::success('删除成功', ['csrf_token' => Csrf::token()]);
    } else {
        Response::error('删除失败');
    }
}

// 批量删除
if ($action === 'batch_delete') {
    $ids = is_array($_POST['ids'] ?? null)
        ? array_map('intval', $_POST['ids'])
        : (json_decode($_POST['ids'] ?? '[]', true) ?: []);
    if (empty($ids)) {
        Response::error('请选择标签');
    }
    foreach ($ids as $id) {
        GoodsTagModel::delete($id);
    }
    Response::success('批量删除成功', ['csrf_token' => Csrf::token()]);
}

// 刷新商品计数
if ($action === 'refresh_counts') {
    GoodsTagModel::refreshAllCounts();
    Response::success('计数已刷新', ['csrf_token' => Csrf::token()]);
}

// 默认：显示列表页面
if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/goods_tag.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/goods_tag.php';
    require __DIR__ . '/index.php';
}
