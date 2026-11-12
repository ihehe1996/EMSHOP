<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 文章管理（/admin/blog）。
 *
 * 与旧 admin/blog.php 行为一致：
 *   - _action=list 分页列表 + 状态计数
 *   - toggle_status / toggle_top / delete / batch
 *   - 无 action 时渲染列表页
 *
 * 编辑弹窗仍指向旧 admin/blog_edit.php，等编辑器迁移时再切换。
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class BlogController extends BaseAdminController
{
    public function index(): void
    {
        $action = (string) $this->input->get('_action', '');
        if ($action === '') {
            $action = (string) $this->input->post('_action', '');
        }

        // 非 list 操作统一校验 CSRF
        if ($action !== 'list' && $action !== '') {
            $csrf = (string) $this->input->post('csrf_token', '');
            if ($csrf === '') {
                $csrf = (string) $this->input->get('csrf_token', '');
            }
            if (!$this->csrf->validate($csrf)) {
                $this->response->error('请求已失效，请刷新页面后重试');
            }
        }

        // 列表接口（供 layui table 使用，layui table 要求 code=0）
        if ($action === 'list') {
            $page = (int) $this->input->post('page', 1);
            $limit = (int) $this->input->post('limit', 20);
            $keyword = trim((string) $this->input->post('keyword', ''));
            $categoryId = (int) $this->input->post('category_id', 0);
            $statusRaw = (string) $this->input->post('status', '');
            $status = $statusRaw !== '' ? (int) $statusRaw : null;

            // 主站后台只看 merchant_id=0
            $where = ['merchant_id' => 0];
            if ($keyword) {
                $where['keyword'] = $keyword;
            }
            if ($categoryId) {
                $where['category_id'] = $categoryId;
            }
            if ($status !== null) {
                $where['status'] = $status;
            }

            $result = \BlogModel::getList($where, $page, $limit, 'a.is_top DESC, a.sort ASC, a.id DESC');

            // 统计各状态的文章数量
            $countWhere = ['merchant_id' => 0];
            if ($keyword) {
                $countWhere['keyword'] = $keyword;
            }
            if ($categoryId) {
                $countWhere['category_id'] = $categoryId;
            }

            $countAll = \BlogModel::getList($countWhere, 1, 1)['total'];
            $countPublished = \BlogModel::getList(array_merge($countWhere, ['status' => 1]), 1, 1)['total'];
            $countDraft = \BlogModel::getList(array_merge($countWhere, ['status' => 0]), 1, 1)['total'];

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'code' => 0,
                'msg' => '',
                'count' => $result['total'],
                'data' => $result['list'],
                'csrf_token' => $this->csrf->token(),
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
            $id = (int) $this->input->post('id', 0);
            $article = \BlogModel::getById($id);
            if (!$article) {
                $this->response->error('文章不存在');
            }
            if ((int) $article['merchant_id'] !== 0) {
                $this->response->error('无权操作商户文章');
            }
            $newStatus = (int) $article['status'] ? 0 : 1;
            \BlogModel::update($id, ['status' => $newStatus]);
            // article_count 只统计已发布文章，状态切换后需刷新计数
            \BlogTagModel::refreshAllCounts();
            $this->response->success('状态已更新', ['csrf_token' => $this->csrf->token()]);
        }

        // 切换置顶
        if ($action === 'toggle_top') {
            $id = (int) $this->input->post('id', 0);
            $article = \BlogModel::getById($id);
            if (!$article) {
                $this->response->error('文章不存在');
            }
            if ((int) $article['merchant_id'] !== 0) {
                $this->response->error('无权操作商户文章');
            }
            $newTop = (int) $article['is_top'] ? 0 : 1;
            \BlogModel::update($id, ['is_top' => $newTop]);
            $this->response->success('状态已更新', ['csrf_token' => $this->csrf->token()]);
        }

        // 删除文章（逻辑删除）
        if ($action === 'delete') {
            $id = (int) $this->input->post('id', 0);
            $article = \BlogModel::getById($id);
            if (!$article) {
                $this->response->error('文章不存在');
            }
            if ((int) $article['merchant_id'] !== 0) {
                $this->response->error('无权删除商户文章');
            }
            if (\BlogModel::delete($id)) {
                // 删除文章后同步刷新标签的 article_count
                \BlogTagModel::refreshAllCounts();
                $this->response->success('删除成功', ['csrf_token' => $this->csrf->token()]);
            } else {
                $this->response->error('删除失败');
            }
        }

        // 批量操作
        if ($action === 'batch') {
            $batchAction = (string) $this->input->post('batch_action', '');
            $rawIds = $this->input->post('ids', null);
            $ids = is_array($rawIds)
                ? array_map('intval', $rawIds)
                : (json_decode((string) $rawIds, true) ?: []);
            if (empty($ids)) {
                $this->response->error('请选择文章');
            }
            // 主站批量操作只允许针对 merchant_id=0 的文章
            $prefix = $this->db->prefix();
            $allowedRows = $this->db->query(
                "SELECT id FROM {$prefix}blog WHERE merchant_id = 0 AND id IN (" . implode(',', array_map('intval', $ids)) . ")"
            );
            $allowedIds = array_map(fn($r) => (int) $r['id'], $allowedRows);
            if (empty($allowedIds)) {
                $this->response->error('所选文章均无权操作');
            }
            $failed = count($ids) - count($allowedIds);
            foreach ($allowedIds as $id) {
                try {
                    if ($batchAction === 'publish') {
                        \BlogModel::update($id, ['status' => 1]);
                    } elseif ($batchAction === 'draft') {
                        \BlogModel::update($id, ['status' => 0]);
                    } elseif ($batchAction === 'delete') {
                        \BlogModel::delete($id);
                    } else {
                        $failed++;
                        break;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                }
            }
            // 任一批量动作（发布/转草稿/删除）都会影响标签的 article_count，统一刷一次
            \BlogTagModel::refreshAllCounts();
            if ($failed === 0) {
                $this->response->success('批量操作成功', ['csrf_token' => $this->csrf->token()]);
            } else {
                $this->response->error('批量操作部分失败（' . $failed . '/' . count($ids) . '）');
            }
        }

        // 默认：显示列表页面（分类下拉只显示主站分类）
        $this->renderList();
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套旧后台框架壳。
     */
    private function renderList(): void
    {
        $categories = $this->db->query(
            "SELECT * FROM " . $this->db->prefix() . "blog_category WHERE status = 1 AND merchant_id = 0 ORDER BY parent_id ASC, sort ASC"
        );

        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('blog', ['categories' => $categories]);
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/blog.php', $this->user(), get_defined_vars());
    }
}
