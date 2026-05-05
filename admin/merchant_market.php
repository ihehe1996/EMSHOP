<?php

declare(strict_types=1);

require_once __DIR__ . '/global.php';

/**
 * 主站后台 · 分站市场管理。
 *
 * 业务边界:
 *   - 列出 em_app_market 已上架的应用(主站为分站采购的清单)
 *   - 改售价 / 上下架 / 查采购流水(已售统计也在这里看)
 *   - 应用安装统一在 /admin/appstore.php?tab=merchant 首次采购上架
 *
 * 与 admin/appstore.php 的关系:
 *   appstore.php tab=merchant   → "采购新应用"(下载文件 + 落 market 行 + 加配额)
 *   merchant_market.php(本文件) → "管理已上架"(改售价 / 上下架 / 看流水 / 看已售给哪些分站)
 */
adminRequireLogin();
$user      = $adminUser;
$siteName  = Config::get('sitename', 'EMSHOP');
$csrfToken = Csrf::token();

$marketModel   = new AppMarketModel();
$purchaseModel = new AppPurchaseModel();

// ============================================================
// POST 处理
// ============================================================
if (Request::isPost()) {
    try {
        if (!Csrf::validate((string) Input::post('csrf_token', ''))) {
            Response::error('请求已失效,请刷新页面后重试');
        }

        $action = (string) Input::post('_action', '');

        switch ($action) {
            // 改分站售价
            case 'set_retail_price': {
                $marketId = (int) Input::post('market_id', 0);
                // 售价单位是微分(× 1,000,000),前端以"元"输入,这里乘转换 —— 前端先做精度处理也可
                // 为了避免双精度误差,前端建议直接传"微分"整数,这里保留两种入参
                if (Input::post('retail_price_micro', null) !== null) {
                    $price = max(0, (int) Input::post('retail_price_micro', 0));
                } else {
                    $yuan  = (string) Input::post('retail_price', '0');
                    $price = (int) round(((float) $yuan) * 1000000);
                    if ($price < 0) $price = 0;
                }

                if ($marketId <= 0) Response::error('market_id 非法');

                $service = new MainAppPurchaseService();
                $ok = $service->updateRetailPrice($marketId, $price);
                if (!$ok) Response::error('价格未改动或目标不存在');
                Response::success('售价已更新', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            // 上下架
            case 'toggle_list': {
                $marketId = (int) Input::post('market_id', 0);
                $listed   = (int) Input::post('is_listed', 0) === 1;
                if ($marketId <= 0) Response::error('market_id 非法');

                $service = new MainAppPurchaseService();
                $ok = $service->setListed($marketId, $listed);
                if (!$ok) Response::error('状态未改动或目标不存在');
                Response::success($listed ? '已上架' : '已下架', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            default:
                Response::error('未知操作');
        }
    } catch (RuntimeException $e) {
        Response::error($e->getMessage());
    } catch (Throwable $e) {
        Response::error('系统繁忙,请稍后再试');
    }
}

// ============================================================
// AJAX:分页列表
// ============================================================
if ((string) Input::get('_action', '') === 'list') {
    header('Content-Type: application/json; charset=utf-8');

    $filter = [
        'type'      => (string) Input::get('type', ''),
        'is_listed' => (string) Input::get('is_listed', ''),
        'keyword'   => (string) Input::get('keyword', ''),
        'page'      => max(1, (int) Input::get('page', 1)),
        'page_size' => min(100, max(1, (int) Input::get('limit', 20))),
    ];
    $result = $marketModel->paginate($filter);

    // 给每行补"已售给多少家分站"(em_app_purchase 中按 market_id 计数)
    $data = [];
    foreach ($result['data'] as $row) {
        $marketId = (int) $row['id'];
        $remaining = max(0, (int) $row['total_quota'] - (int) $row['consumed_quota']);
        $data[] = $row + [
            'remaining'      => $remaining,
            'sold_count'     => $purchaseModel->countByMarket($marketId),
            'is_in_stock'    => $remaining > 0 ? 1 : 0,
        ];
    }

    echo json_encode([
        'code'        => 0,
        'msg'         => '',
        'count'       => $result['total'],
        'data'        => $data,
        'csrf_token'  => Csrf::token(),
    ], JSON_UNESCAPED_UNICODE);
    return;
}

// ============================================================
// AJAX:某个 market 的采购流水(主站后台"看历史"用)
// ============================================================
if ((string) Input::get('_action', '') === 'logs') {
    $marketId = (int) Input::get('market_id', 0);
    if ($marketId <= 0) Response::error('market_id 非法');
    $logs = $marketModel->logsByMarket($marketId, 100);
    Response::success('', ['list' => $logs]);
}

// ============================================================
// 普通页面渲染
// ============================================================
if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/merchant_market.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/merchant_market.php';
    require __DIR__ . '/index.php';
}
