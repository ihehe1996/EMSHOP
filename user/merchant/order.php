<?php

declare(strict_types=1);

require_once __DIR__ . '/global.php';

/**
 * 商户后台 - 订单管理
 *
 * 商户能看自家订单（按 merchant_id 过滤），看到每项的拿货价 / 手续费 快照、净收入。
 * 自家订单的"手动发货"由商户自己操作（同 admin 走 goods_type_{type}_manual_delivery_submit
 * 钩子，由插件处理）。状态机切换交给 OrderModel 统一掌控。
 */
merchantRequireLogin();

$merchantId = (int) $currentMerchant['id'];

if (Request::isPost()) {
    try {
        $action = (string) Input::post('_action', '');
        if ($action !== 'list' && $action !== 'detail') {
            if (!Csrf::validate((string) Input::post('csrf_token', ''))) {
                Response::error('请求已失效，请刷新页面后重试');
            }
        }

        $orderTable = Database::prefix() . 'order';
        $orderGoodsTable = Database::prefix() . 'order_goods';

        switch ($action) {
            case 'list': {
                $keyword = trim((string) Input::post('keyword', ''));
                $status = (string) Input::post('status', '');
                $page = max(1, (int) Input::post('page', 1));
                $pageSize = max(1, min(100, (int) Input::post('limit', 20)));
                $offset = ($page - 1) * $pageSize;

                $conds = ['o.merchant_id = ?'];
                $params = [$merchantId];

                if ($keyword !== '') {
                    $conds[] = '(o.order_no LIKE ? OR o.contact_info LIKE ?)';
                    $params[] = '%' . $keyword . '%';
                    $params[] = '%' . $keyword . '%';
                }
                // status 支持合并：paid 含 paid+delivering、refunded 含 refunded+refunding
                if ($status !== '' && $status !== 'all') {
                    if ($status === 'paid') {
                        $conds[] = 'o.status IN (?, ?)';
                        $params[] = 'paid'; $params[] = 'delivering';
                    } elseif ($status === 'refunded') {
                        $conds[] = 'o.status IN (?, ?)';
                        $params[] = 'refunded'; $params[] = 'refunding';
                    } else {
                        $conds[] = 'o.status = ?';
                        $params[] = $status;
                    }
                }
                $whereSql = 'WHERE ' . implode(' AND ', $conds);

                $count = Database::fetchOne(
                    'SELECT COUNT(*) AS c FROM `' . $orderTable . '` o ' . $whereSql,
                    $params
                );
                $total = (int) ($count['c'] ?? 0);

                // JOIN user 表拿昵称/账号；每单 cost + fee 合计从 order_goods 聚合
                // 多 SELECT 几个字段：订单下单时冻结的展示币种 / 汇率，供 displaySnapshot 用
                $userTable = Database::prefix() . 'user';
                $sql = 'SELECT o.id, o.order_no, o.user_id, o.guest_token, o.pay_amount, o.status,
                               o.payment_name, o.payment_code, o.pay_time, o.delivery_time, o.complete_time,
                               o.created_at, o.display_currency_code, o.display_rate,
                               u.username, u.nickname,
                               (SELECT SUM(og.cost_amount) FROM `' . $orderGoodsTable . '` og WHERE og.order_id = o.id) AS total_cost,
                               (SELECT SUM(og.fee_amount)  FROM `' . $orderGoodsTable . '` og WHERE og.order_id = o.id) AS total_fee,
                               (SELECT COUNT(*)            FROM `' . $orderGoodsTable . '` og WHERE og.order_id = o.id) AS items_count,
                               (SELECT COUNT(*)            FROM `' . $orderGoodsTable . '` og WHERE og.order_id = o.id AND (og.delivery_content IS NULL OR og.delivery_content = "")) AS pending_ship_count
                          FROM `' . $orderTable . '` o
                          LEFT JOIN `' . $userTable . '` u ON u.id = o.user_id ' . $whereSql . '
                         ORDER BY o.created_at DESC
                         LIMIT ' . $pageSize . ' OFFSET ' . $offset;
                $rows = Database::query($sql, $params);

                // 收集订单 ID，批量补"前 N 件商品"列表（避免 N+1）
                $orderIds = array_map(static fn($r) => (int) $r['id'], $rows);
                $goodsByOrder = [];
                if ($orderIds) {
                    $ph = implode(',', array_fill(0, count($orderIds), '?'));
                    $goodsRows = Database::query(
                        "SELECT order_id, goods_title, spec_name, quantity, cover_image, delivery_content
                           FROM `{$orderGoodsTable}`
                          WHERE order_id IN ({$ph})
                          ORDER BY id",
                        $orderIds
                    );
                    foreach ($goodsRows as $g) {
                        $goodsByOrder[(int) $g['order_id']][] = [
                            'title'       => (string) $g['goods_title'],
                            'spec'        => (string) $g['spec_name'],
                            'quantity'    => (int) $g['quantity'],
                            'cover'       => (string) $g['cover_image'],
                            'shipped'     => !empty($g['delivery_content']),
                        ];
                    }
                }

                foreach ($rows as &$r) {
                    $pay = (int) $r['pay_amount'];
                    $cost = (int) ($r['total_cost'] ?? 0);
                    $fee = (int) ($r['total_fee'] ?? 0);
                    $net = $pay - $cost - $fee;
                    // 金额按下单时快照币种展示 —— 和买家看到的金额完全一致，不受访客当前切币种影响
                    $dispCode = (string) ($r['display_currency_code'] ?? '');
                    $dispRate = (int) ($r['display_rate'] ?? 0);
                    $r['pay_amount_view'] = Currency::displaySnapshot($pay, $dispCode, $dispRate);
                    $r['total_cost_view'] = Currency::displaySnapshot($cost, $dispCode, $dispRate);
                    $r['total_fee_view']  = Currency::displaySnapshot($fee, $dispCode, $dispRate);
                    $r['net_income_view'] = Currency::displaySnapshot($net, $dispCode, $dispRate);
                    $r['buyer_label'] = (int) $r['user_id'] > 0
                        ? ($r['nickname'] ?: $r['username'] ?: ('用户 #' . $r['user_id']))
                        : ('游客 ' . substr((string) ($r['guest_token'] ?? ''), 0, 8));
                    $r['is_guest'] = (int) $r['user_id'] === 0;
                    $r['status_name'] = OrderModel::statusName((string) $r['status']);
                    $r['pending_ship_count'] = (int) ($r['pending_ship_count'] ?? 0);
                    // 是否允许在该状态下手动发货（与 admin 同款）
                    $r['can_ship'] = in_array((string) $r['status'], ['paid', 'delivering', 'delivery_failed'], true)
                        && $r['pending_ship_count'] > 0;
                    $r['goods'] = $goodsByOrder[(int) $r['id']] ?? [];
                    $r['goods_count'] = count($r['goods']);
                }
                unset($r);

                // 顺便算 Tab 计数
                $tabCounts = [];
                $statusList = ['pending', 'paid', 'delivering', 'delivered', 'completed', 'refunding', 'refunded', 'cancelled'];
                foreach ($statusList as $st) {
                    $cRow = Database::fetchOne(
                        'SELECT COUNT(*) AS c FROM `' . $orderTable . '` WHERE `merchant_id` = ? AND `status` = ?',
                        [$merchantId, $st]
                    );
                    $tabCounts[$st] = (int) ($cRow['c'] ?? 0);
                }
                $all = Database::fetchOne(
                    'SELECT COUNT(*) AS c FROM `' . $orderTable . '` WHERE `merchant_id` = ?',
                    [$merchantId]
                );
                $tabCounts['all'] = (int) ($all['c'] ?? 0);

                Response::success('', [
                    'data' => $rows,
                    'total' => $total,
                    'tab_counts' => $tabCounts,
                    'csrf_token' => Csrf::token(),
                ]);
                break;
            }

            case 'detail': {
                $id = (int) Input::post('id', 0);
                $order = Database::fetchOne(
                    'SELECT * FROM `' . $orderTable . '` WHERE `id` = ? AND `merchant_id` = ? LIMIT 1',
                    [$id, $merchantId]
                );
                if ($order === null) {
                    Response::error('订单不存在');
                }
                $items = Database::query(
                    'SELECT * FROM `' . $orderGoodsTable . '` WHERE `order_id` = ? ORDER BY `id` ASC',
                    [$id]
                );
                // 订单详情所有金额都按下单时快照币种展示
                $dispCode = (string) ($order['display_currency_code'] ?? '');
                $dispRate = (int) ($order['display_rate'] ?? 0);
                foreach ($items as &$it) {
                    $it['price_view']       = Currency::displaySnapshot((int) $it['price'], $dispCode, $dispRate);
                    $it['cost_amount_view'] = Currency::displaySnapshot((int) ($it['cost_amount'] ?? 0), $dispCode, $dispRate);
                    $it['fee_amount_view']  = Currency::displaySnapshot((int) ($it['fee_amount'] ?? 0), $dispCode, $dispRate);
                    $line = ((int) $it['price']) * ((int) $it['quantity']);
                    $lineCost = (int) ($it['cost_amount'] ?? 0) * (int) $it['quantity'];
                    $lineFee  = (int) ($it['fee_amount'] ?? 0);
                    $it['line_net_view'] = Currency::displaySnapshot($line - $lineCost - $lineFee, $dispCode, $dispRate);
                }
                unset($it);

                $order['pay_amount_view']      = Currency::displaySnapshot((int) $order['pay_amount'], $dispCode, $dispRate);
                $order['goods_amount_view']    = Currency::displaySnapshot((int) $order['goods_amount'], $dispCode, $dispRate);
                $order['discount_amount_view'] = Currency::displaySnapshot((int) $order['discount_amount'], $dispCode, $dispRate);

                Response::success('', [
                    'order' => $order,
                    'items' => $items,
                    'csrf_token' => Csrf::token(),
                ]);
                break;
            }

            // 商户手动发货（与 admin/order.php 同款流程，但严格按 merchant_id 校验归属）
            //   - 调 goods_type_{type}_manual_delivery_submit filter 让插件准备 delivery_content / plugin_data
            //   - 插件返回字符串 = 校验失败消息；返回数组 = 成功 payload
            //   - 状态流转由 OrderModel::manualShipOrderGoods 统一处理
            case 'ship': {
                $ogId = (int) Input::post('order_goods_id', 0);
                if ($ogId <= 0) Response::error('参数错误');

                // ACL：order_goods 必须属于本商户的订单
                $ogRow = Database::fetchOne(
                    "SELECT og.*, o.status AS order_status, o.merchant_id AS order_merchant_id
                       FROM `{$orderGoodsTable}` og
                       LEFT JOIN `{$orderTable}` o ON o.id = og.order_id
                      WHERE og.id = ?",
                    [$ogId]
                );
                if (!$ogRow) Response::error('订单商品不存在');
                if ((int) ($ogRow['order_merchant_id'] ?? 0) !== $merchantId) {
                    Response::error('无权操作其它店铺的订单');
                }
                if (!empty($ogRow['delivery_content'])) Response::error('该商品已发货');

                $goodsType = (string) ($ogRow['goods_type'] ?? '');
                if ($goodsType === '') Response::error('商品类型缺失，无法发货');

                $result = applyFilter(
                    "goods_type_{$goodsType}_manual_delivery_submit",
                    null,
                    ['order_goods' => $ogRow, 'post' => $_POST]
                );

                if (is_string($result)) {
                    Response::error($result);
                }
                if (!is_array($result) || !isset($result['delivery_content'])) {
                    Response::error('该商品类型暂未提供发货处理，请联系插件开发者');
                }

                $deliveryContent = (string) $result['delivery_content'];
                $pluginData = isset($result['plugin_data']) && is_array($result['plugin_data']) ? $result['plugin_data'] : null;

                OrderModel::manualShipOrderGoods($ogId, $deliveryContent, $pluginData);

                Response::success('发货成功', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            default:
                Response::error('未知操作');
        }
    } catch (RuntimeException $e) {
        Response::error($e->getMessage());
    } catch (Throwable $e) {
        Response::error($e->getMessage() ?: '系统繁忙，请稍后再试');
    }
}

// ============================================================
// 弹窗：订单详情（iframe 内独立页）
// ============================================================
if ((string) Input::get('_popup', '') === 'detail') {
    $orderId = (int) Input::get('id', 0);
    $orderTable = Database::prefix() . 'order';
    $orderGoodsTable = Database::prefix() . 'order_goods';

    $order = Database::fetchOne(
        "SELECT o.*, u.username, u.nickname
           FROM `{$orderTable}` o
           LEFT JOIN `" . Database::prefix() . "user` u ON u.id = o.user_id
          WHERE o.id = ? AND o.merchant_id = ? LIMIT 1",
        [$orderId, $merchantId]
    );
    if (!$order) {
        http_response_code(404);
        echo '订单不存在';
        return;
    }
    $order['status_name'] = OrderModel::statusName((string) $order['status']);

    $items = Database::query(
        "SELECT * FROM `{$orderGoodsTable}` WHERE order_id = ? ORDER BY id",
        [$orderId]
    );
    // 金额按下单快照币种展示
    $dispCode = (string) ($order['display_currency_code'] ?? '');
    $dispRate = (int) ($order['display_rate'] ?? 0);
    foreach ($items as &$it) {
        $it['price_view']       = Currency::displaySnapshot((int) $it['price'], $dispCode, $dispRate);
        $it['cost_amount_view'] = Currency::displaySnapshot((int) ($it['cost_amount'] ?? 0), $dispCode, $dispRate);
        $it['fee_amount_view']  = Currency::displaySnapshot((int) ($it['fee_amount'] ?? 0), $dispCode, $dispRate);
        $line = ((int) $it['price']) * ((int) $it['quantity']);
        $lineCost = ((int) ($it['cost_amount'] ?? 0)) * ((int) $it['quantity']);
        $lineFee  = (int) ($it['fee_amount'] ?? 0);
        $it['line_net_view'] = Currency::displaySnapshot($line - $lineCost - $lineFee, $dispCode, $dispRate);
        $it['line_subtotal_view'] = Currency::displaySnapshot($line, $dispCode, $dispRate);
    }
    unset($it);

    $order['pay_amount_view']      = Currency::displaySnapshot((int) $order['pay_amount'], $dispCode, $dispRate);
    $order['goods_amount_view']    = Currency::displaySnapshot((int) $order['goods_amount'], $dispCode, $dispRate);
    $order['discount_amount_view'] = Currency::displaySnapshot((int) $order['discount_amount'], $dispCode, $dispRate);
    $totalCost = 0; $totalFee = 0;
    foreach ($items as $it) {
        $totalCost += ((int) ($it['cost_amount'] ?? 0)) * ((int) $it['quantity']);
        $totalFee  += (int) ($it['fee_amount'] ?? 0);
    }
    $netIncome = ((int) $order['pay_amount']) - $totalCost - $totalFee;
    $order['total_cost_view'] = Currency::displaySnapshot($totalCost, $dispCode, $dispRate);
    $order['total_fee_view']  = Currency::displaySnapshot($totalFee, $dispCode, $dispRate);
    $order['net_income_view'] = Currency::displaySnapshot($netIncome, $dispCode, $dispRate);

    $csrfToken = Csrf::token();
    $pageTitle = '订单详情 #' . $order['order_no'];
    include __DIR__ . '/view/popup/order_detail.php';
    return;
}

// ============================================================
// 弹窗：发货（iframe，复用 admin/view/popup/order_ship.php，参数化提交 URL 即可）
// ============================================================
if ((string) Input::get('_popup', '') === 'ship') {
    $orderId = (int) Input::get('id', 0);
    $orderTable = Database::prefix() . 'order';

    $order = Database::fetchOne(
        "SELECT * FROM `{$orderTable}` WHERE id = ? AND merchant_id = ? LIMIT 1",
        [$orderId, $merchantId]
    );
    if (!$order) {
        http_response_code(404);
        echo '订单不存在';
        return;
    }

    $allowedShipStatus = ['paid', 'delivering', 'delivery_failed'];
    if (!in_array((string) $order['status'], $allowedShipStatus, true)) {
        $pageTitle = '无法发货';
        $shipError = '当前订单状态（' . OrderModel::statusName($order['status']) . '）不允许手动发货';
        $csrfToken = Csrf::token();
        $shipSubmitUrl = '/user/merchant/order.php';
        include EM_ROOT . '/admin/view/popup/order_ship.php';
        return;
    }

    $shippableGoods = array_values(array_filter(
        OrderModel::getOrderGoods($orderId),
        static fn($og) => empty($og['delivery_content'])
    ));

    $csrfToken = Csrf::token();
    $shipSubmitUrl = '/user/merchant/order.php';   // ← 关键：让发货 form 提交到商户路径，不走 admin
    $pageTitle = '发货 - ' . $order['order_no'];
    include EM_ROOT . '/admin/view/popup/order_ship.php';
    return;
}

merchantRenderPage(__DIR__ . '/view/order.php');
