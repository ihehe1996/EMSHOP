<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * 后台 - 分站订单（商户订单）只读监控。
 *
 * em_order.merchant_id > 0 的订单都属于这里；主站后台只能看不能改 ——
 * 商户订单的发货 / 退款由商户自己在 user/merchant/order.php 操作。
 *
 * POST 端点：
 *   _action = list      列表（merchant_id 过滤 / 状态 / 关键字）
 *   _action = summary   今日/本月/累计 已支付订单的笔数和金额
 *   _action = merchants 拉所有有订单的商户给筛选下拉
 */
adminRequireLogin();
$user = $adminUser;

if (Request::isPost()) {
    try {
        $action = (string) Input::post('_action', '');
        $prefix = Database::prefix();

        // 列表
        if ($action === 'list') {
            $page    = max(1, (int) Input::post('page', 1));
            $limit   = (int) Input::post('limit', 15);
            if ($limit < 1 || $limit > 100) $limit = 15;

            $keyword     = trim((string) Input::post('keyword', ''));
            $status      = trim((string) Input::post('status', ''));
            $merchantId  = (int) Input::post('merchant_id', 0);

            // 强制 merchant_id > 0（这是分站订单页的核心约束）
            $where  = 'o.merchant_id > 0';
            $params = [];

            if ($merchantId > 0) {
                $where .= ' AND o.merchant_id = ?';
                $params[] = $merchantId;
            }
            if ($status !== '') {
                $where .= ' AND o.status = ?';
                $params[] = $status;
            }
            if ($keyword !== '') {
                $kw = '%' . $keyword . '%';
                $where .= ' AND ('
                        . 'o.order_no LIKE ? '
                        . 'OR u.username LIKE ? '
                        . 'OR u.nickname LIKE ? '
                        . 'OR m.name LIKE ? '
                        . 'OR EXISTS (SELECT 1 FROM ' . $prefix . 'order_goods og WHERE og.order_id = o.id AND og.goods_title LIKE ?)'
                        . ')';
                $params[] = $kw; $params[] = $kw; $params[] = $kw; $params[] = $kw; $params[] = $kw;
            }

            $countRow = Database::fetchOne(
                "SELECT COUNT(*) AS cnt
                   FROM {$prefix}order o
                   LEFT JOIN {$prefix}user u ON o.user_id = u.id
                   LEFT JOIN {$prefix}merchant m ON o.merchant_id = m.id
                  WHERE {$where}",
                $params
            );
            $total = (int) ($countRow['cnt'] ?? 0);

            $offset = ($page - 1) * $limit;
            $rows = Database::query(
                "SELECT o.*,
                        u.username, u.nickname, u.avatar,
                        m.name AS merchant_name
                   FROM {$prefix}order o
                   LEFT JOIN {$prefix}user u ON o.user_id = u.id
                   LEFT JOIN {$prefix}merchant m ON o.merchant_id = m.id
                  WHERE {$where}
                  ORDER BY o.id DESC
                  LIMIT {$limit} OFFSET {$offset}",
                $params
            );

            // 支付方式 code → image 映射（订单表存了 payment_name 但没存 image，得走 PaymentService 取）
            $paymentImageMap = [];
            try {
                foreach (PaymentService::getMethods() as $m) {
                    $code = (string) ($m['code'] ?? '');
                    if ($code !== '') $paymentImageMap[$code] = (string) ($m['image'] ?? '');
                }
            } catch (Throwable $e) {
                // 拿不到不影响列表展示
            }

            // 金额格式化 + 状态名 + 商品占位
            foreach ($rows as &$row) {
                $row['goods_amount_fmt'] = number_format((float) bcdiv((string) ($row['goods_amount'] ?? 0), '1000000', 2), 2, '.', ',');
                $row['pay_amount_fmt']   = number_format((float) bcdiv((string) ($row['pay_amount']   ?? 0), '1000000', 2), 2, '.', ',');
                $row['status_name']      = OrderModel::statusName((string) $row['status']);
                $row['payment_image']    = $paymentImageMap[(string) ($row['payment_code'] ?? '')] ?? '';
                $row['goods'] = [];
                $row['goods_count'] = 0;
            }
            unset($row);

            // 批量补商品（避免 N+1）—— 复用 admin/order.php 的同款逻辑
            if ($rows) {
                $orderIds = array_map(static fn($r) => (int) $r['id'], $rows);
                $ph = implode(',', array_fill(0, count($orderIds), '?'));
                $goodsRows = Database::query(
                    "SELECT order_id, goods_title, spec_name, quantity, cover_image
                       FROM {$prefix}order_goods
                      WHERE order_id IN ({$ph})
                      ORDER BY id",
                    $orderIds
                );
                $goodsByOrder = [];
                foreach ($goodsRows as $g) {
                    $goodsByOrder[(int) $g['order_id']][] = [
                        'title'    => (string) $g['goods_title'],
                        'spec'     => (string) $g['spec_name'],
                        'quantity' => (int) $g['quantity'],
                        'cover'    => (string) $g['cover_image'],
                    ];
                }
                foreach ($rows as &$row) {
                    $list = $goodsByOrder[(int) $row['id']] ?? [];
                    $row['goods'] = $list;
                    $row['goods_count'] = count($list);
                }
                unset($row);
            }

            // 顺手算分桶（chips 上的徽章数字）—— 仅 merchant_id>0
            $countRows = Database::query(
                "SELECT status, COUNT(*) AS cnt FROM {$prefix}order WHERE merchant_id > 0 GROUP BY status"
            );
            $statusCounts = ['all' => 0];
            foreach ($countRows as $r) {
                $s = (string) $r['status'];
                $c = (int) $r['cnt'];
                $statusCounts['all'] += $c;
                $statusCounts[$s] = $c;
            }

            Response::success('', [
                'data'          => array_values($rows),
                'total'         => $total,
                'status_counts' => $statusCounts,
                'csrf_token'    => Csrf::token(),
            ]);
        }

        // 顶部数据卡：今日笔数 / 今日金额 / 本月金额 / 累计已支付（仅商户订单 + paid 类状态）
        if ($action === 'summary') {
            $today      = date('Y-m-d');
            $monthStart = date('Y-m-01 00:00:00');

            // "成功支付"约定：status IN (paid, delivering, delivered, completed)
            // refunding/refunded/cancelled/expired/failed/delivery_failed 不算
            $paidStatuses = ['paid', 'delivering', 'delivered', 'completed'];
            $ph = implode(',', array_fill(0, count($paidStatuses), '?'));

            $todayRow = Database::fetchOne(
                "SELECT COUNT(*) AS cnt, COALESCE(SUM(pay_amount), 0) AS amt
                   FROM {$prefix}order
                  WHERE merchant_id > 0 AND status IN ({$ph}) AND DATE(pay_time) = ?",
                array_merge($paidStatuses, [$today])
            );
            $monthRow = Database::fetchOne(
                "SELECT COALESCE(SUM(pay_amount), 0) AS amt
                   FROM {$prefix}order
                  WHERE merchant_id > 0 AND status IN ({$ph}) AND pay_time >= ?",
                array_merge($paidStatuses, [$monthStart])
            );
            $totalRow = Database::fetchOne(
                "SELECT COALESCE(SUM(pay_amount), 0) AS amt
                   FROM {$prefix}order
                  WHERE merchant_id > 0 AND status IN ({$ph})",
                $paidStatuses
            );

            $fmt = static fn(int $v): string => bcdiv((string) $v, '1000000', 2);

            Response::success('', ['data' => [
                'today_count'  => (int) ($todayRow['cnt'] ?? 0),
                'today_amount' => $fmt((int) ($todayRow['amt'] ?? 0)),
                'month_amount' => $fmt((int) ($monthRow['amt'] ?? 0)),
                'total_amount' => $fmt((int) ($totalRow['amt'] ?? 0)),
            ]]);
        }

        // 筛选下拉用：所有"有订单的"商户列表
        if ($action === 'merchants') {
            $rows = Database::query(
                "SELECT m.id, m.name
                   FROM {$prefix}merchant m
                  WHERE EXISTS (SELECT 1 FROM {$prefix}order o WHERE o.merchant_id = m.id LIMIT 1)
                  ORDER BY m.id ASC"
            );
            Response::success('', ['data' => $rows]);
        }

        Response::error('未知操作');
    } catch (Throwable $e) {
        Response::error('系统繁忙：' . $e->getMessage());
    }
}

$csrfToken = Csrf::token();

if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/merchant_order.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/merchant_order.php';
    require __DIR__ . '/index.php';
}
