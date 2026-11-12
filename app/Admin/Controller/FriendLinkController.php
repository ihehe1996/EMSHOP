<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 友情链接管理（{入口}/friend_link）。
 *
 * 行为约定：
 *   - POST 处理列表 / 创建 / 更新 / 删除 / 状态切换 / 批量删除 / 图片上传
 *   - ?_popup=1 渲染添加/编辑弹窗
 *   - 普通请求渲染列表页
 *
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class FriendLinkController extends BaseAdminController
{
    public function index(): void
    {
        if ($this->request->isPost()) {
            $this->handlePost();
            return;
        }

        if ((string) $this->input->get('_popup', '') === '1') {
            $this->renderPopup();
            return;
        }

        $this->renderList();
    }

    /**
     * 处理后台 AJAX 动作；除 list 外都校验 CSRF。
     */
    private function handlePost(): void
    {
        try {
            $action = (string) $this->input->post('_action', '');

            // list 请求不需要 CSRF 验证
            if ($action !== 'list') {
                $csrf = (string) $this->input->post('csrf_token', '');
                if (!$this->csrf->validate($csrf)) {
                    $this->response->error('请求已失效，请刷新页面后重试');
                }
            }

            $model = app(\App\Model\FriendLinkModel::class);

            switch ($action) {
                case 'list':
                    $keyword = trim((string) $this->input->post('keyword', ''));
                    $links = $model->getAllForAdmin($keyword ?: null);

                    $this->response->success('', [
                        'data' => array_values($links),
                        'total' => count($links),
                        'csrf_token' => $this->csrf->token(),
                    ]);
                    break;

                case 'create':
                    $name = trim((string) $this->input->post('name', ''));
                    if ($name === '') {
                        $this->response->error('链接名称不能为空');
                    }
                    if (mb_strlen($name) > 100) {
                        $this->response->error('链接名称最多100个字符');
                    }

                    $url = trim((string) $this->input->post('url', ''));
                    if ($url === '') {
                        $this->response->error('链接地址不能为空');
                    }
                    if (mb_strlen($url) > 500) {
                        $this->response->error('链接地址最多500个字符');
                    }

                    // 简单 URL 格式校验
                    if (!preg_match('/^https?:\/\//i', $url)) {
                        $this->response->error('链接地址必须以 http:// 或 https:// 开头');
                    }

                    $expireTime = trim((string) $this->input->post('expire_time', ''));
                    $expireTimeFormatted = null;
                    if ($expireTime !== '') {
                        $ts = strtotime($expireTime);
                        if ($ts === false) {
                            $this->response->error('过期时间格式不正确');
                        }
                        $expireTimeFormatted = date('Y-m-d H:i:s', $ts);
                    }

                    $sort = (int) $this->input->post('sort', 0);

                    $model->create([
                        'name' => $name,
                        'url' => $url,
                        'image' => trim((string) $this->input->post('image', '')),
                        'enabled' => $this->input->post('enabled', 'y') === 'y' ? 'y' : 'n',
                        'expire_time' => $expireTimeFormatted,
                        'description' => trim((string) $this->input->post('description', '')),
                        'sort' => $sort,
                    ]);

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('友链创建成功', ['csrf_token' => $csrfToken]);
                    break;

                case 'update':
                    $id = (int) $this->input->post('id', 0);
                    if ($id <= 0) {
                        $this->response->error('无效的友链ID');
                    }

                    $existing = $model->findById($id);
                    if ($existing === null) {
                        $this->response->error('友链不存在');
                    }

                    $name = trim((string) $this->input->post('name', ''));
                    if ($name === '') {
                        $this->response->error('链接名称不能为空');
                    }
                    if (mb_strlen($name) > 100) {
                        $this->response->error('链接名称最多100个字符');
                    }

                    $url = trim((string) $this->input->post('url', ''));
                    if ($url === '') {
                        $this->response->error('链接地址不能为空');
                    }
                    if (mb_strlen($url) > 500) {
                        $this->response->error('链接地址最多500个字符');
                    }
                    if (!preg_match('/^https?:\/\//i', $url)) {
                        $this->response->error('链接地址必须以 http:// 或 https:// 开头');
                    }

                    $expireTime = trim((string) $this->input->post('expire_time', ''));
                    $expireTimeFormatted = null;
                    if ($expireTime !== '') {
                        $ts = strtotime($expireTime);
                        if ($ts === false) {
                            $this->response->error('过期时间格式不正确');
                        }
                        $expireTimeFormatted = date('Y-m-d H:i:s', $ts);
                    }

                    $sort = (int) $this->input->post('sort', 0);

                    $model->update($id, [
                        'name' => $name,
                        'url' => $url,
                        'image' => trim((string) $this->input->post('image', '')),
                        'enabled' => $this->input->post('enabled', 'y') === 'y' ? 'y' : 'n',
                        'expire_time' => $expireTimeFormatted,
                        'description' => trim((string) $this->input->post('description', '')),
                        'sort' => $sort,
                    ]);

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('友链更新成功', ['csrf_token' => $csrfToken]);
                    break;

                case 'delete':
                    $id = (int) $this->input->post('id', 0);
                    if ($id <= 0) {
                        $this->response->error('无效的友链ID');
                    }

                    if ($model->findById($id) === null) {
                        $this->response->error('友链不存在');
                    }

                    $model->delete($id);

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('删除成功', ['csrf_token' => $csrfToken]);
                    break;

                case 'toggle':
                    $id = (int) $this->input->post('id', 0);
                    if ($id <= 0) {
                        $this->response->error('无效的友链ID');
                    }

                    if ($model->findById($id) === null) {
                        $this->response->error('友链不存在');
                    }

                    $model->toggle($id);

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('状态已更新', ['csrf_token' => $csrfToken]);
                    break;

                case 'batchDelete':
                    $idsRaw = $this->input->post('ids', '');
                    if ($idsRaw === '') {
                        $this->response->error('请选择要删除的友链');
                    }
                    $ids = array_filter(array_map('intval', explode(',', $idsRaw)));
                    if ($ids === []) {
                        $this->response->error('无效的友链ID');
                    }

                    $deleted = $model->batchDelete($ids);

                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('已删除 ' . $deleted . ' 条友链', ['csrf_token' => $csrfToken]);
                    break;

                case 'image':
                    if (empty($_FILES['file'])) {
                        $this->response->error('请选择图片文件');
                    }
                    $uploader = app(\App\Service\UploadService::class);
                    $result = $uploader->upload($_FILES['file'], ['jpg', 'jpeg', 'png', 'gif', 'webp'], 'friend_link');
                    $csrfToken = $this->csrf->refresh();
                    $this->response->success('上传成功', [
                        'csrf_token' => $csrfToken,
                        'url' => $result['url'],
                    ]);
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
     * 添加/编辑弹窗（iframe）。
     */
    private function renderPopup(): void
    {
        $editId = (int) $this->input->get('id', 0);
        $editLink = null;

        if ($editId > 0) {
            $editLink = app(\App\Model\FriendLinkModel::class)->findById($editId);
        }

        $this->render('popup/friend_link', [
            'editLink'  => $editLink,
            'pageTitle' => $editLink !== null ? '编辑友链' : '添加友链',
        ]);
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套后台框架壳。
     */
    private function renderList(): void
    {
        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('friend_link');
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/friend_link.php', $this->user(), get_defined_vars());
    }
}
