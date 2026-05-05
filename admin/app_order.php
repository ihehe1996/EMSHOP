<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * 后台 - 应用订单。
 *
 * 记录分站站长在商户端应用商店的购买订单（当前支付方式为余额扣款）。
 */
adminRequireLogin();
$user = $adminUser;
$csrfToken = Csrf::token();

if ((string) Input::get('_action', '') === 'list') {
    try {
        $model = new AppOrderModel();
        $result = $model->paginateForAdmin([
            'page'        => max(1, (int) Input::get('page', 1)),
            'page_size'   => max(1, min(100, (int) Input::get('limit', 20))),
            'keyword'     => (string) Input::get('keyword', ''),
            'merchant_id' => (int) Input::get('merchant_id', 0),
            'type'        => (string) Input::get('type', ''),
            'status'      => (string) Input::get('status', ''),
        ]);

        $rows = $result['data'];
        foreach ($rows as &$row) {
            $row['amount_view'] = number_format(((int) ($row['amount'] ?? 0)) / 1000000, 2, '.', '');
            $row['before_balance_view'] = number_format(((int) ($row['before_balance'] ?? 0)) / 1000000, 2, '.', '');
            $row['after_balance_view'] = number_format(((int) ($row['after_balance'] ?? 0)) / 1000000, 2, '.', '');
        }
        unset($row);

        Response::success('', [
            'list'    => $rows,
            'count'   => (int) ($result['total'] ?? 0),
            'page'    => max(1, (int) Input::get('page', 1)),
            'pageNum' => max(1, min(100, (int) Input::get('limit', 20))),
        ]);
    } catch (Throwable $e) {
        Response::error($e->getMessage());
    }
}

if (Request::isPjax()) {
    include __DIR__ . '/view/app_order.php';
} else {
    $adminContentView = __DIR__ . '/view/app_order.php';
    require __DIR__ . '/index.php';
}

