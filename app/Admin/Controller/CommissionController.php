<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 推广返佣（/admin/commission）。
 *
 * 只读页面：
 *   ?tab=log      (默认) 佣金流水
 *   ?tab=withdraw        提现记录
 *   POST _action=list 返回表格数据
 *
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class CommissionController extends BaseAdminController
{
    public function index(): void
    {
        $tab = (string) $this->input->get('tab', 'log');
        if (!in_array($tab, ['log', 'withdraw'], true)) {
            $tab = 'log';
        }

        if ($this->request->isPost()) {
            $this->handlePost();
            return;
        }

        $this->renderList($tab);
    }

    /**
     * 表格数据（佣金流水 / 提现记录）。
     */
    private function handlePost(): void
    {
        try {
            $action = (string) $this->input->post('_action', '');
            if ($action === 'list') {
                $type = (string) $this->input->post('tab', 'log');
                $page = max(1, (int) $this->input->post('page', 1));
                $perPage = max(1, min(100, (int) $this->input->post('limit', 20)));

                if ($type === 'withdraw') {
                    $filter = [];
                    $uid = (int) $this->input->post('user_id', 0);
                    if ($uid > 0) {
                        $filter['user_id'] = $uid;
                    }

                    $result = (new \App\Model\CommissionWithdrawModel())->paginate($filter, $page, $perPage);
                } else {
                    // 佣金流水：按 user_id / status / level 过滤
                    $status = (string) $this->input->post('status', '');
                    $level = (int) $this->input->post('level', 0);
                    $uid = (int) $this->input->post('user_id', 0);
                    $where = [];
                    $params = [];
                    if ($uid > 0) {
                        $where[] = 'user_id = ?';
                        $params[] = $uid;
                    }
                    if ($status) {
                        $where[] = 'status = ?';
                        $params[] = $status;
                    }
                    if ($level > 0) {
                        $where[] = 'level = ?';
                        $params[] = $level;
                    }
                    $whereSql = $where ? implode(' AND ', $where) : '1=1';

                    $prefix = $this->db->prefix();
                    $countRow = $this->db->fetchOne("SELECT COUNT(*) AS cnt FROM {$prefix}commission_log WHERE {$whereSql}", $params);
                    $total = (int) ($countRow['cnt'] ?? 0);
                    $totalPages = max(1, (int) ceil($total / $perPage));
                    if ($page > $totalPages) {
                        $page = $totalPages;
                    }
                    $offset = ($page - 1) * $perPage;

                    $rows = $this->db->query(
                        "SELECT * FROM {$prefix}commission_log WHERE {$whereSql} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}",
                        $params
                    );
                    foreach ($rows as &$r) {
                        $r['amount_display'] = bcdiv((string) $r['amount'], '1000000', 2);
                        $r['basis_amount_display'] = bcdiv((string) ($r['basis_amount'] ?? 0), '1000000', 2);
                    }
                    unset($r);
                    $result = ['list' => $rows, 'total' => $total];
                }

                $this->response->success('', [
                    'data' => $result['list'],
                    'total' => $result['total'],
                    'csrf_token' => $this->csrf->token(),
                ]);
            }
            $this->response->error('未知操作');
        } catch (\Throwable $e) {
            $this->response->error('系统繁忙');
        }
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套后台框架壳。
     */
    private function renderList(string $tab): void
    {
        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('commission', ['tab' => $tab]);
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/commission.php', $this->user(), get_defined_vars());
    }
}
