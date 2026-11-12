<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 页面管理（{入口}/page）。
 *
 * 行为约定：
 *   - _action=list 分页列表 + 状态计数
 *   - set_homepage / clear_homepage / toggle_status / delete / batch
 *   - 无 action 时渲染列表页
 *
 * 编辑弹窗由 {入口}/page_edit 提供。
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class PageController extends BaseAdminController
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

        // ========== 列表接口（给 layui table 用）==========
        if ($action === 'list') {
            $page = (int) $this->input->post('page', 1);
            $limit = (int) $this->input->post('limit', 20);
            $keyword = trim((string) $this->input->post('keyword', ''));
            $statusRaw = (string) $this->input->post('status', '');
            $status = $statusRaw !== '' ? (int) $statusRaw : null;

            // 主站后台只看 merchant_id=0
            $where = ['merchant_id' => 0];
            if ($keyword) {
                $where['keyword'] = $keyword;
            }
            if ($status !== null) {
                $where['status'] = $status;
            }

            $result = app(\App\Model\PageModel::class)->getList($where, $page, $limit);

            // 状态计数（供 em-tabs 计数徽章使用）—— 同样限定主站
            $countAll = app(\App\Model\PageModel::class)->getList(['merchant_id' => 0], 1, 1)['total'];
            $countPublished = app(\App\Model\PageModel::class)->getList(['merchant_id' => 0, 'status' => 1], 1, 1)['total'];
            $countDraft = app(\App\Model\PageModel::class)->getList(['merchant_id' => 0, 'status' => 0], 1, 1)['total'];

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

        // ========== 设为站点首页（主站作用域）==========
        if ($action === 'set_homepage') {
            $id = (int) $this->input->post('id', 0);
            $page = app(\App\Model\PageModel::class)->getById($id);
            if (!$page) {
                $this->response->error('页面不存在');
            }
            if ((int) $page['merchant_id'] !== 0) {
                $this->response->error('无权操作商户页面');
            }
            if (app(\App\Model\PageModel::class)->setHomepage($id, 0)) {
                $this->response->success('已设为主站首页', ['csrf_token' => $this->csrf->token()]);
            } else {
                $this->response->error('设置失败');
            }
        }

        // ========== 取消页面首页（回退到 homepage_mode）==========
        if ($action === 'clear_homepage') {
            $id = (int) $this->input->post('id', 0);
            $page = app(\App\Model\PageModel::class)->getById($id);
            if (!$page) {
                $this->response->error('页面不存在');
            }
            if ((int) $page['merchant_id'] !== 0) {
                $this->response->error('无权操作商户页面');
            }
            app(\App\Model\PageModel::class)->clearHomepage(0, $id);
            $this->response->success('已取消首页', ['csrf_token' => $this->csrf->token()]);
        }

        // ========== 切换发布状态 ==========
        if ($action === 'toggle_status') {
            $id = (int) $this->input->post('id', 0);
            $page = app(\App\Model\PageModel::class)->getById($id);
            if (!$page) {
                $this->response->error('页面不存在');
            }
            if ((int) $page['merchant_id'] !== 0) {
                $this->response->error('无权操作商户页面');
            }
            $newStatus = (int) $page['status'] ? 0 : 1;
            app(\App\Model\PageModel::class)->update($id, ['status' => $newStatus]);
            $this->response->success('状态已更新', ['csrf_token' => $this->csrf->token()]);
        }

        // ========== 删除 ==========
        if ($action === 'delete') {
            $id = (int) $this->input->post('id', 0);
            $page = app(\App\Model\PageModel::class)->getById($id);
            if (!$page) {
                $this->response->error('页面不存在');
            }
            if ((int) $page['merchant_id'] !== 0) {
                $this->response->error('无权操作商户页面');
            }
            if (app(\App\Model\PageModel::class)->delete($id)) {
                $this->response->success('删除成功', ['csrf_token' => $this->csrf->token()]);
            } else {
                $this->response->error('删除失败');
            }
        }

        // ========== 批量操作 ==========
        if ($action === 'batch') {
            $batchAction = (string) $this->input->post('batch_action', '');
            $rawIds = $this->input->post('ids', null);
            $ids = is_array($rawIds)
                ? array_map('intval', $rawIds)
                : (json_decode((string) $rawIds, true) ?: []);
            if (empty($ids)) {
                $this->response->error('请选择页面');
            }
            // 限定本批次只能是主站页面
            $prefix = $this->db->prefix();
            $allowed = $this->db->query(
                "SELECT id FROM {$prefix}page WHERE merchant_id = 0 AND id IN (" . implode(',', array_map('intval', $ids)) . ")"
            );
            $allowedIds = array_map(fn($r) => (int) $r['id'], $allowed);
            if (empty($allowedIds)) {
                $this->response->error('所选页面均无权操作');
            }
            $failed = count($ids) - count($allowedIds);
            foreach ($allowedIds as $id) {
                try {
                    if ($batchAction === 'publish') {
                        app(\App\Model\PageModel::class)->update($id, ['status' => 1]);
                    } elseif ($batchAction === 'draft') {
                        app(\App\Model\PageModel::class)->update($id, ['status' => 0]);
                    } elseif ($batchAction === 'delete') {
                        app(\App\Model\PageModel::class)->delete($id);
                    } else {
                        $failed++;
                        break;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                }
            }
            if ($failed === 0) {
                $this->response->success('批量操作成功', ['csrf_token' => $this->csrf->token()]);
            } else {
                $this->response->error('批量操作部分失败（' . $failed . '/' . count($ids) . '）');
            }
        }

        // ========== 默认：渲染列表页 ==========
        $this->renderList();
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套后台框架壳。
     */
    private function renderList(): void
    {
        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('page');
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/page.php', $this->user(), get_defined_vars());
    }
}
