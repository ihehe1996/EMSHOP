<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 博客标签管理（/admin/blog_tag）。
 *
 * 与旧 admin/blog_tag.php 行为一致：
 *   - 主站后台只操作 merchant_id=0 的主站标签池
 *   - _action=list 分页列表、save 新增/编辑、delete / batch_delete / refresh_counts
 *   - 无 action 时渲染列表页
 *
 * 动作参数同时兼容 GET 与 POST（旧页面用 ?_action=list）。
 * 过渡期列表页仍套用旧后台框架壳（admin/view/index.php）。
 */
final class BlogTagController extends BaseAdminController
{
    public function index(): void
    {
        $action = (string) \Input::get('_action', '');
        if ($action === '') {
            $action = (string) \Input::post('_action', '');
        }

        // 非 list 操作统一验证 CSRF
        if ($action !== 'list' && $action !== '') {
            $csrf = (string) \Input::post('csrf_token', '');
            if ($csrf === '') {
                $csrf = (string) \Input::get('csrf_token', '');
            }
            if (!\Csrf::validate($csrf)) {
                \Response::error('请求已失效，请刷新页面后重试');
            }
        }

        // 列表接口（供 layui table 使用）
        if ($action === 'list') {
            $page    = (int) \Input::post('page', 1);
            $limit   = (int) \Input::post('limit', 20);
            $keyword = trim((string) \Input::post('keyword', ''));

            // 主站后台只看主站标签池
            $where = ['merchant_id' => 0];
            if ($keyword !== '') {
                $where['keyword'] = $keyword;
            }

            $result = \BlogTagModel::getAdminList($where, $page, $limit);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'code'       => 0,
                'msg'        => '',
                'count'      => $result['total'],
                'data'       => $result['list'],
                'csrf_token' => \Csrf::token(),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 保存标签（新增/编辑）
        if ($action === 'save') {
            $id   = (int) \Input::post('id', 0);
            $name = trim((string) \Input::post('name', ''));
            $sort = (int) \Input::post('sort', 0);

            if ($name === '') {
                \Response::error('标签名不能为空');
            }

            // 检查重名（主站标签池内）
            if (\BlogTagModel::nameExists($name, $id, 0)) {
                \Response::error('标签名已存在');
            }

            $data = [
                'name' => $name,
                'sort' => $sort,
            ];

            if ($id > 0) {
                $existing = \BlogTagModel::getById($id);
                if (!$existing) {
                    \Response::error('标签不存在');
                }
                if ((int) $existing['merchant_id'] !== 0) {
                    \Response::error('无权操作商户标签');
                }
                \BlogTagModel::update($id, $data);
            } else {
                $data['merchant_id'] = 0; // 主站后台创建的标签固定归主站池
                $id = \BlogTagModel::create($data);
            }

            \Response::success('保存成功', ['id' => $id, 'csrf_token' => \Csrf::refresh()]);
        }

        // 删除标签
        if ($action === 'delete') {
            $id = (int) \Input::post('id', 0);
            if ($id <= 0) {
                \Response::error('参数错误');
            }
            $existing = \BlogTagModel::getById($id);
            if (!$existing) {
                \Response::error('标签不存在');
            }
            if ((int) $existing['merchant_id'] !== 0) {
                \Response::error('无权删除商户标签');
            }
            if (\BlogTagModel::delete($id)) {
                \Response::success('删除成功', ['csrf_token' => \Csrf::token()]);
            } else {
                \Response::error('删除失败');
            }
        }

        // 批量删除（仅主站标签池）
        if ($action === 'batch_delete') {
            $ids = is_array(\Input::post('ids', null))
                ? array_map('intval', \Input::post('ids', []))
                : (json_decode((string) \Input::post('ids', '[]'), true) ?: []);
            if (empty($ids)) {
                \Response::error('请选择标签');
            }
            $prefix = \Database::prefix();
            $allowedRows = \Database::query(
                "SELECT id FROM {$prefix}blog_tag WHERE merchant_id = 0 AND id IN (" . implode(',', array_map('intval', $ids)) . ")"
            );
            foreach ($allowedRows as $row) {
                \BlogTagModel::delete((int) $row['id']);
            }
            \Response::success('批量删除成功', ['csrf_token' => \Csrf::token()]);
        }

        // 刷新文章计数
        if ($action === 'refresh_counts') {
            \BlogTagModel::refreshAllCounts();
            \Response::success('计数已刷新', ['csrf_token' => \Csrf::token()]);
        }

        // 默认：显示列表页面
        $this->renderList();
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套旧后台框架壳。
     */
    private function renderList(): void
    {
        if (\Request::isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('blog_tag');
            echo '</div>';
            return;
        }

        require_once EM_ROOT . '/include/lib/AdminLang.php';

        $user = $this->user();
        $siteName = (string) \Config::get('sitename', 'EMSHOP');
        $languages = (new \LanguageModel())->getEnabled();
        $emServerHardRestartPending = is_file(EM_ROOT . '/.server') && \WorkerHeartbeat::hostAlive();
        $adminContentView = dirname(__DIR__) . '/View/blog_tag.php';

        require EM_ROOT . '/admin/view/index.php';
    }
}
