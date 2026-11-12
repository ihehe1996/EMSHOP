<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 商品标签管理（/admin/goods_tag）。
 *
 * 与旧 admin/goods_tag.php 行为一致：
 *   - _action=list 分页列表（layui table）
 *   - _action=save 新增/编辑
 *   - _action=delete / batch_delete / refresh_counts
 *   - 无 action 时渲染列表页
 *
 * 动作参数同时兼容 GET 与 POST（旧页面用 ?_action=list）。
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class GoodsTagController extends BaseAdminController
{
    public function index(): void
    {
        $action = (string) $this->input->get('_action', '');
        if ($action === '') {
            $action = (string) $this->input->post('_action', '');
        }

        // 非 list 操作统一验证 CSRF（与旧页面一致，GET/POST 都接受）
        if ($action !== 'list' && $action !== '') {
            $csrf = (string) $this->input->post('csrf_token', '');
            if ($csrf === '') {
                $csrf = (string) $this->input->get('csrf_token', '');
            }
            if (!$this->csrf->validate($csrf)) {
                $this->response->error('请求已失效，请刷新页面后重试');
            }
        }

        // 列表接口（供 layui table 使用）
        if ($action === 'list') {
            $page    = (int) $this->input->post('page', 1);
            $limit   = (int) $this->input->post('limit', 20);
            $keyword = trim((string) $this->input->post('keyword', ''));

            $where = [];
            if ($keyword !== '') {
                $where['keyword'] = $keyword;
            }

            $result = app(\App\Model\GoodsTagModel::class)->getAdminList($where, $page, $limit);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'code'       => 0,
                'msg'        => '',
                'count'      => $result['total'],
                'data'       => $result['list'],
                'csrf_token' => $this->csrf->token(),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 保存标签（新增/编辑）
        if ($action === 'save') {
            $id   = (int) $this->input->post('id', 0);
            $name = trim((string) $this->input->post('name', ''));
            $sort = (int) $this->input->post('sort', 0);

            if ($name === '') {
                $this->response->error('标签名不能为空');
            }

            if (app(\App\Model\GoodsTagModel::class)->nameExists($name, $id)) {
                $this->response->error('标签名已存在');
            }

            $data = [
                'name' => $name,
                'sort' => $sort,
            ];

            if ($id > 0) {
                app(\App\Model\GoodsTagModel::class)->update($id, $data);
            } else {
                $id = app(\App\Model\GoodsTagModel::class)->create($data);
            }

            $this->response->success('保存成功', ['id' => $id, 'csrf_token' => $this->csrf->refresh()]);
        }

        // 删除标签
        if ($action === 'delete') {
            $id = (int) $this->input->post('id', 0);
            if ($id <= 0) {
                $this->response->error('参数错误');
            }
            if (app(\App\Model\GoodsTagModel::class)->delete($id)) {
                $this->response->success('删除成功', ['csrf_token' => $this->csrf->token()]);
            } else {
                $this->response->error('删除失败');
            }
        }

        // 批量删除
        if ($action === 'batch_delete') {
            $ids = is_array($this->input->post('ids', null))
                ? array_map('intval', $this->input->post('ids', []))
                : (json_decode((string) $this->input->post('ids', '[]'), true) ?: []);
            if (empty($ids)) {
                $this->response->error('请选择标签');
            }
            foreach ($ids as $id) {
                app(\App\Model\GoodsTagModel::class)->delete($id);
            }
            $this->response->success('批量删除成功', ['csrf_token' => $this->csrf->token()]);
        }

        // 刷新商品计数
        if ($action === 'refresh_counts') {
            app(\App\Model\GoodsTagModel::class)->refreshAllCounts();
            $this->response->success('计数已刷新', ['csrf_token' => $this->csrf->token()]);
        }

        // 默认：显示列表页面
        $this->renderList();
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套后台框架壳。
     */
    private function renderList(): void
    {
        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('goods_tag');
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/goods_tag.php', $this->user(), get_defined_vars());
    }
}
