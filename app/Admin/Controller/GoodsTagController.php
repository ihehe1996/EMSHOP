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
        $action = (string) \Input::get('_action', '');
        if ($action === '') {
            $action = (string) \Input::post('_action', '');
        }

        // 非 list 操作统一验证 CSRF（与旧页面一致，GET/POST 都接受）
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

            $where = [];
            if ($keyword !== '') {
                $where['keyword'] = $keyword;
            }

            $result = \GoodsTagModel::getAdminList($where, $page, $limit);

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

            if (\GoodsTagModel::nameExists($name, $id)) {
                \Response::error('标签名已存在');
            }

            $data = [
                'name' => $name,
                'sort' => $sort,
            ];

            if ($id > 0) {
                \GoodsTagModel::update($id, $data);
            } else {
                $id = \GoodsTagModel::create($data);
            }

            \Response::success('保存成功', ['id' => $id, 'csrf_token' => \Csrf::refresh()]);
        }

        // 删除标签
        if ($action === 'delete') {
            $id = (int) \Input::post('id', 0);
            if ($id <= 0) {
                \Response::error('参数错误');
            }
            if (\GoodsTagModel::delete($id)) {
                \Response::success('删除成功', ['csrf_token' => \Csrf::token()]);
            } else {
                \Response::error('删除失败');
            }
        }

        // 批量删除
        if ($action === 'batch_delete') {
            $ids = is_array(\Input::post('ids', null))
                ? array_map('intval', \Input::post('ids', []))
                : (json_decode((string) \Input::post('ids', '[]'), true) ?: []);
            if (empty($ids)) {
                \Response::error('请选择标签');
            }
            foreach ($ids as $id) {
                \GoodsTagModel::delete($id);
            }
            \Response::success('批量删除成功', ['csrf_token' => \Csrf::token()]);
        }

        // 刷新商品计数
        if ($action === 'refresh_counts') {
            \GoodsTagModel::refreshAllCounts();
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
            $this->render('goods_tag');
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/goods_tag.php', $this->user(), get_defined_vars());
    }
}
