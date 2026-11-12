<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 钱包提现审核（/admin/withdraw）。
 *
 * POST _action：
 *   list     分页查询
 *   approve  pending → approved（审核通过，等待打款）
 *   reject   pending/approved → rejected（驳回，退回余额）
 *   paid     approved → paid（标记已打款）
 *
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class WithdrawController extends BaseAdminController
{
    public function index(): void
    {
        if ($this->request->isPost()) {
            $this->handlePost();
            return;
        }

        $this->renderList();
    }

    /**
     * 表格数据与审核动作。
     */
    private function handlePost(): void
    {
        try {
            $action = (string) $this->input->post('_action', '');
            $model = new \App\Model\UserWithdrawModel();

            if ($action === 'list') {
                $page = max(1, (int) $this->input->post('page', 1));
                $perPage = max(1, min(100, (int) $this->input->post('limit', 20)));
                $filter = [
                    'status'  => (string) $this->input->post('status', ''),
                    'keyword' => trim((string) $this->input->post('keyword', '')),
                ];
                $result = $model->paginate($filter, $page, $perPage);
                foreach ($result['list'] as &$r) {
                    // 金额千分位展示（大额提现更易读）
                    $r['amount_display'] = number_format((float) bcdiv((string) $r['amount'], '1000000', 2), 2, '.', ',');
                }
                unset($r);
                $this->response->success('', [
                    'data'       => $result['list'],
                    'total'      => $result['total'],
                    'csrf_token' => $this->csrf->token(),
                ]);
            }

            if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
                $this->response->error('令牌失效，请刷新后重试');
            }
            $id = (int) $this->input->post('id', 0);
            $remark = trim((string) $this->input->post('remark', ''));
            $admin = $this->user();
            $aid = (int) ($admin['id'] ?? 0);

            if ($action === 'approve') {
                if (!$model->approve($id, $aid, $remark)) {
                    $this->response->error('操作失败（状态可能已变）');
                }
                $this->response->success('已审核通过');
            }
            if ($action === 'reject') {
                $model->reject($id, $aid, $remark);
                $this->response->success('已驳回，金额已退回用户');
            }
            if ($action === 'paid') {
                if (!$model->markPaid($id, $aid, $remark)) {
                    $this->response->error('操作失败（状态可能已变）');
                }
                $this->response->success('已标记打款');
            }

            $this->response->error('未知操作');
        } catch (\RuntimeException $e) {
            $this->response->error($e->getMessage());
        } catch (\Throwable $e) {
            $this->response->error('系统繁忙：' . $e->getMessage());
        }
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套旧后台框架壳。
     */
    private function renderList(): void
    {
        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('withdraw');
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/withdraw.php', $this->user(), get_defined_vars());
    }
}
