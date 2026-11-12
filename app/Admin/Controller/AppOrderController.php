<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 应用订单（/admin/app_order）。
 *
 * 记录分站站长在商户端应用商店的购买订单（当前支付方式为余额扣款）。
 * GET _action=list 返回列表 JSON（只读）。
 *
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class AppOrderController extends BaseAdminController
{
    public function index(): void
    {
        if ((string) $this->input->get('_action', '') === 'list') {
            $this->outputList();
            return;
        }

        if ($this->request->isPjax()) {
            // 旧页面 Pjax 分支就是直接 include 视图（视图自带内容区结构），保持一致
            $this->render('app_order');
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/app_order.php', $this->user(), get_defined_vars());
    }

    /**
     * 列表 JSON。
     */
    private function outputList(): void
    {
        try {
            $model = new \AppOrderModel();
            $result = $model->paginateForAdmin([
                'page'        => max(1, (int) $this->input->get('page', 1)),
                'page_size'   => max(1, min(100, (int) $this->input->get('limit', 20))),
                'keyword'     => (string) $this->input->get('keyword', ''),
                'merchant_id' => (int) $this->input->get('merchant_id', 0),
                'type'        => (string) $this->input->get('type', ''),
                'status'      => (string) $this->input->get('status', ''),
            ]);

            $rows = $result['data'];
            foreach ($rows as &$row) {
                $row['amount_view'] = number_format(((int) ($row['amount'] ?? 0)) / 1000000, 2, '.', '');
                $row['before_balance_view'] = number_format(((int) ($row['before_balance'] ?? 0)) / 1000000, 2, '.', '');
                $row['after_balance_view'] = number_format(((int) ($row['after_balance'] ?? 0)) / 1000000, 2, '.', '');
            }
            unset($row);

            $this->response->success('', [
                'list'    => $rows,
                'count'   => (int) ($result['total'] ?? 0),
                'page'    => max(1, (int) $this->input->get('page', 1)),
                'pageNum' => max(1, min(100, (int) $this->input->get('limit', 20))),
            ]);
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage());
        }
    }
}
