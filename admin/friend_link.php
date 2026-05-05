<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * 友情链接管理控制器。
 *
 * 友链数据存储于 friend_link 表。
 */
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
        if ($action !== 'list') {
            $csrf = (string) Input::post('csrf_token', '');
            if (!Csrf::validate($csrf)) {
                Response::error('请求已失效，请刷新页面后重试');
            }
        }

        require EM_ROOT . '/include/model/FriendLinkModel.php';
        $model = new FriendLinkModel();

        switch ($action) {
            case 'list':
                $keyword = trim((string) Input::post('keyword', ''));
                $links = $model->getAllForAdmin($keyword ?: null);

                Response::success('', [
                    'data' => array_values($links),
                    'total' => count($links),
                    'csrf_token' => Csrf::token(),
                ]);
                break;

            case 'create':
                $name = trim((string) Input::post('name', ''));
                if ($name === '') {
                    Response::error('链接名称不能为空');
                }
                if (mb_strlen($name) > 100) {
                    Response::error('链接名称最多100个字符');
                }

                $url = trim((string) Input::post('url', ''));
                if ($url === '') {
                    Response::error('链接地址不能为空');
                }
                if (mb_strlen($url) > 500) {
                    Response::error('链接地址最多500个字符');
                }

                // 简单 URL 格式校验
                if (!preg_match('/^https?:\/\//i', $url)) {
                    Response::error('链接地址必须以 http:// 或 https:// 开头');
                }

                $expireTime = trim((string) Input::post('expire_time', ''));
                $expireTimeFormatted = null;
                if ($expireTime !== '') {
                    $ts = strtotime($expireTime);
                    if ($ts === false) {
                        Response::error('过期时间格式不正确');
                    }
                    $expireTimeFormatted = date('Y-m-d H:i:s', $ts);
                }

                $sort = (int) Input::post('sort', 0);

                $model->create([
                    'name' => $name,
                    'url' => $url,
                    'image' => trim((string) Input::post('image', '')),
                    'enabled' => Input::post('enabled', 'y') === 'y' ? 'y' : 'n',
                    'expire_time' => $expireTimeFormatted,
                    'description' => trim((string) Input::post('description', '')),
                    'sort' => $sort,
                ]);

                $csrfToken = Csrf::refresh();
                Response::success('友链创建成功', ['csrf_token' => $csrfToken]);
                break;

            case 'update':
                $id = (int) Input::post('id', 0);
                if ($id <= 0) {
                    Response::error('无效的友链ID');
                }

                $existing = $model->findById($id);
                if ($existing === null) {
                    Response::error('友链不存在');
                }

                $name = trim((string) Input::post('name', ''));
                if ($name === '') {
                    Response::error('链接名称不能为空');
                }
                if (mb_strlen($name) > 100) {
                    Response::error('链接名称最多100个字符');
                }

                $url = trim((string) Input::post('url', ''));
                if ($url === '') {
                    Response::error('链接地址不能为空');
                }
                if (mb_strlen($url) > 500) {
                    Response::error('链接地址最多500个字符');
                }
                if (!preg_match('/^https?:\/\//i', $url)) {
                    Response::error('链接地址必须以 http:// 或 https:// 开头');
                }

                $expireTime = trim((string) Input::post('expire_time', ''));
                $expireTimeFormatted = null;
                if ($expireTime !== '') {
                    $ts = strtotime($expireTime);
                    if ($ts === false) {
                        Response::error('过期时间格式不正确');
                    }
                    $expireTimeFormatted = date('Y-m-d H:i:s', $ts);
                }

                $sort = (int) Input::post('sort', 0);

                $model->update($id, [
                    'name' => $name,
                    'url' => $url,
                    'image' => trim((string) Input::post('image', '')),
                    'enabled' => Input::post('enabled', 'y') === 'y' ? 'y' : 'n',
                    'expire_time' => $expireTimeFormatted,
                    'description' => trim((string) Input::post('description', '')),
                    'sort' => $sort,
                ]);

                $csrfToken = Csrf::refresh();
                Response::success('友链更新成功', ['csrf_token' => $csrfToken]);
                break;

            case 'delete':
                $id = (int) Input::post('id', 0);
                if ($id <= 0) {
                    Response::error('无效的友链ID');
                }

                if ($model->findById($id) === null) {
                    Response::error('友链不存在');
                }

                $model->delete($id);

                $csrfToken = Csrf::refresh();
                Response::success('删除成功', ['csrf_token' => $csrfToken]);
                break;

            case 'toggle':
                $id = (int) Input::post('id', 0);
                if ($id <= 0) {
                    Response::error('无效的友链ID');
                }

                if ($model->findById($id) === null) {
                    Response::error('友链不存在');
                }

                $model->toggle($id);

                $csrfToken = Csrf::refresh();
                Response::success('状态已更新', ['csrf_token' => $csrfToken]);
                break;

            case 'batchDelete':
                $idsRaw = Input::post('ids', '');
                if ($idsRaw === '') {
                    Response::error('请选择要删除的友链');
                }
                $ids = array_filter(array_map('intval', explode(',', $idsRaw)));
                if ($ids === []) {
                    Response::error('无效的友链ID');
                }

                $deleted = $model->batchDelete($ids);

                $csrfToken = Csrf::refresh();
                Response::success('已删除 ' . $deleted . ' 条友链', ['csrf_token' => $csrfToken]);
                break;

            case 'image':
                if (empty($_FILES['file'])) {
                    Response::error('请选择图片文件');
                }
                $uploader = new UploadService();
                $result = $uploader->upload($_FILES['file'], ['jpg', 'jpeg', 'png', 'gif', 'webp'], 'friend_link');
                $csrfToken = Csrf::refresh();
                Response::success('上传成功', [
                    'csrf_token' => $csrfToken,
                    'url' => $result['url'],
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

// ============================================================
// 弹窗模式：渲染添加/编辑弹窗
// ============================================================
$isPopup = Input::get('_popup', '') === '1';
if ($isPopup) {
    $editId = (int) Input::get('id', 0);
    $editLink = null;

    if ($editId > 0) {
        require EM_ROOT . '/include/model/FriendLinkModel.php';
        $model = new FriendLinkModel();
        $editLink = $model->findById($editId);
    }

    $isEdit = $editLink !== null;
    $pageTitle = $isEdit ? '编辑友链' : '添加友链';

    $esc = function (string $str): string {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    };

    include __DIR__ . '/view/popup/friend_link.php';
    return;
}

// ============================================================
// 正常模式：渲染完整后台页面
// ============================================================
$csrfToken = Csrf::token();

if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/friend_link.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/friend_link.php';
    require __DIR__ . '/index.php';
}
