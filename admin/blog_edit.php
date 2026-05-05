<?php
declare(strict_types=1);

require __DIR__ . '/global.php';

// 后台登录校验
adminRequireLogin();

$action = $_GET['_action'] ?? $_POST['_action'] ?? '';

// 保存文章
if ($action === 'save') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!Csrf::validate($csrf)) {
        Response::error('请求已失效，请刷新页面后重试');
    }

    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $excerpt = trim($_POST['excerpt'] ?? '');
    $content = $_POST['content'] ?? '';
    $cover_image = trim($_POST['cover_image'] ?? '');
    $sort = (int)($_POST['sort'] ?? 0);
    $is_top = isset($_POST['is_top']) ? 1 : 0;
    $status = (int)($_POST['status'] ?? 1);

    // 验证必填
    if (empty($title)) {
        Response::error('文章标题不能为空');
    }

    $data = [
        'title'       => $title,
        'category_id' => $category_id,
        'excerpt'     => $excerpt,
        'content'     => $content,
        'cover_image' => $cover_image,
        'sort'        => $sort,
        'is_top'      => $is_top,
        'status'      => $status,
    ];

    try {
        if ($id) {
            // 更新前 ACL 校验
            $existing = BlogModel::getById($id);
            if (!$existing) {
                Response::error('文章不存在');
            }
            if ((int) $existing['merchant_id'] !== 0) {
                Response::error('无权操作商户文章');
            }
            BlogModel::update($id, $data);
            $articleId = $id;
        } else {
            $data['user_id'] = $_SESSION['em_admin_auth']['id'] ?? 0;
            $data['created_by'] = $data['user_id'];
            $data['merchant_id'] = 0; // 主站后台创建的文章固定归主站
            $articleId = BlogModel::create($data);
        }

        if (!$articleId) {
            Response::error('保存失败');
        }

        // 保存标签关联（主站标签池）
        $tagsStr = trim($_POST['tags'] ?? '');
        $tagNames = array_filter(array_map('trim', explode(',', $tagsStr)));
        $tagIds = [];
        foreach ($tagNames as $tagName) {
            if ($tagName !== '') {
                $tagIds[] = BlogTagModel::findOrCreate($tagName, 0);
            }
        }
        BlogTagModel::syncBlogTags($articleId, $tagIds);
        BlogTagModel::refreshAllCounts();
    } catch (Throwable $e) {
        Response::error('保存失败：' . $e->getMessage());
    }

    $newToken = Csrf::refresh();
    Response::success('保存成功', ['id' => $articleId, 'csrf_token' => $newToken]);
}

// 默认：显示编辑页面
$id = (int)($_GET['id'] ?? 0);
$article = null;
if ($id) {
    $article = BlogModel::getById($id);
    if ($article && (int) $article['merchant_id'] !== 0) {
        $article = null; // 主站后台不展示商户文章
    }
}

$categories = Database::query(
    "SELECT * FROM " . Database::prefix() . "blog_category WHERE status = 1 AND merchant_id = 0 ORDER BY parent_id ASC, sort ASC"
);

// 弹窗模式
$isPopup = Input::get('_popup', '') === '1';
if ($isPopup) {
    include __DIR__ . '/view/popup/blog_edit.php';
    return;
}
