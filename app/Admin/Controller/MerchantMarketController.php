<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 主站后台 · 分站市场管理（{入口}/merchant_market）。
 *
 * 列出 em_app_market 已上架的应用（主站为分站采购的清单），
 * 改售价 / 上下架 / 查采购流水。
 *
 * 动作：set_retail_price / toggle_list；GET _action=list / logs。
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class MerchantMarketController extends BaseAdminController
{
    public function index(): void
    {
        $marketModel = app(\App\Model\AppMarketModel::class);
        $purchaseModel = app(\App\Model\AppPurchaseModel::class);

        if ($this->request->isPost()) {
            $this->handlePost();
            return;
        }

        if ((string) $this->input->get('_action', '') === 'list') {
            $this->outputList($marketModel, $purchaseModel);
            return;
        }

        if ((string) $this->input->get('_action', '') === 'logs') {
            $marketId = (int) $this->input->get('market_id', 0);
            if ($marketId <= 0) {
                $this->response->error('market_id 非法');
            }
            $logs = $marketModel->logsByMarket($marketId, 100);
            $this->response->success('', ['list' => $logs]);
        }

        $this->renderList();
    }

    /**
     * 改售价 / 上下架。
     */
    private function handlePost(): void
    {
        try {
            if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
                $this->response->error('请求已失效,请刷新页面后重试');
            }

            $action = (string) $this->input->post('_action', '');

            switch ($action) {
                // 改分站售价
                case 'set_retail_price': {
                    $marketId = (int) $this->input->post('market_id', 0);
                    // 售价单位是微分（×1,000,000），前端以"元"输入，这里乘转换
                    if ($this->input->post('retail_price_micro', null) !== null) {
                        $price = max(0, (int) $this->input->post('retail_price_micro', 0));
                    } else {
                        $yuan = (string) $this->input->post('retail_price', '0');
                        $price = (int) round(((float) $yuan) * 1000000);
                        if ($price < 0) {
                            $price = 0;
                        }
                    }

                    if ($marketId <= 0) {
                        $this->response->error('market_id 非法');
                    }

                    $service = app(\App\Service\MainAppPurchaseService::class);
                    $ok = $service->updateRetailPrice($marketId, $price);
                    if (!$ok) {
                        $this->response->error('价格未改动或目标不存在');
                    }
                    $this->response->success('售价已更新', ['csrf_token' => $this->csrf->refresh()]);
                    break;
                }

                // 上下架
                case 'toggle_list': {
                    $marketId = (int) $this->input->post('market_id', 0);
                    $listed = (int) $this->input->post('is_listed', 0) === 1;
                    if ($marketId <= 0) {
                        $this->response->error('market_id 非法');
                    }

                    $service = app(\App\Service\MainAppPurchaseService::class);
                    $ok = $service->setListed($marketId, $listed);
                    if (!$ok) {
                        $this->response->error('状态未改动或目标不存在');
                    }
                    $this->response->success($listed ? '已上架' : '已下架', ['csrf_token' => $this->csrf->refresh()]);
                    break;
                }

                default:
                    $this->response->error('未知操作');
            }
        } catch (\RuntimeException $e) {
            $this->response->error($e->getMessage());
        } catch (\Throwable $e) {
            $this->response->error('系统繁忙,请稍后再试');
        }
    }

    /**
     * 分页列表。
     */
    private function outputList(\App\Model\AppMarketModel $marketModel, \App\Model\AppPurchaseModel $purchaseModel): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $filter = [
            'type'      => (string) $this->input->get('type', ''),
            'is_listed' => (string) $this->input->get('is_listed', ''),
            'keyword'   => (string) $this->input->get('keyword', ''),
            'page'      => max(1, (int) $this->input->get('page', 1)),
            'page_size' => min(100, max(1, (int) $this->input->get('limit', 20))),
        ];
        $result = $marketModel->paginate($filter);

        // 给每行补"已售给多少家分站"（em_app_purchase 中按 market_id 计数）
        $data = [];
        foreach ($result['data'] as $row) {
            $marketId = (int) $row['id'];
            $remaining = max(0, (int) $row['total_quota'] - (int) $row['consumed_quota']);
            $data[] = $row + [
                'remaining'   => $remaining,
                'sold_count'  => $purchaseModel->countByMarket($marketId),
                'is_in_stock' => $remaining > 0 ? 1 : 0,
            ];
        }

        echo json_encode([
            'code'       => 0,
            'msg'        => '',
            'count'      => $result['total'],
            'data'       => $data,
            'csrf_token' => $this->csrf->token(),
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套后台框架壳。
     */
    private function renderList(): void
    {
        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('merchant_market');
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/merchant_market.php', $this->user(), get_defined_vars());
    }
}
