<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * 后台订单管理。
 */
adminRequireLogin();
$user = $adminUser;

// POST 请求
if (Request::isPost()) {
    try {
        $action = (string) Input::post('_action', '');

        if ($action !== 'list') {
            $csrf = (string) Input::post('csrf_token', '');
            if (!Csrf::validate($csrf)) {
                Response::error('请求已失效，请刷新页面后重试');
            }
        }

        $prefix = Database::prefix();

        switch ($action) {
            case 'list':
                $page = max(1, (int) Input::post('page', 1));
                $limit = (int) Input::post('limit', 15);
                if ($limit < 1 || $limit > 100) $limit = 15;

                $keyword = trim((string) Input::post('keyword', ''));
                $status = trim((string) Input::post('status', ''));

                $where = '1=1';
                $params = [];

                if ($keyword !== '') {
                    // 快捷搜索：订单号 / 商品名 / 用户名 / 昵称 / 邮箱 / 手机
                    // 商品名用 EXISTS 子查询，避免 LEFT JOIN 导致一单多商品时主表行重复
                    $kw = '%' . $keyword . '%';
                    $where .= ' AND ('
                            . 'o.order_no LIKE ? '
                            . 'OR u.username LIKE ? '
                            . 'OR u.nickname LIKE ? '
                            . 'OR u.email LIKE ? '
                            . 'OR u.mobile LIKE ? '
                            . 'OR EXISTS (SELECT 1 FROM ' . $prefix . 'order_goods og2 WHERE og2.order_id = o.id AND og2.goods_title LIKE ?)'
                            . ')';
                    $params[] = $kw;
                    $params[] = $kw;
                    $params[] = $kw;
                    $params[] = $kw;
                    $params[] = $kw;
                    $params[] = $kw;
                }
                if ($status !== '') {
                    $where .= ' AND o.status = ?';
                    $params[] = $status;
                }

                // 总数
                $countSql = "SELECT COUNT(*) as cnt FROM {$prefix}order o LEFT JOIN {$prefix}user u ON o.user_id = u.id WHERE {$where}";
                $total = (int) (Database::fetchOne($countSql, $params)['cnt'] ?? 0);

                // 数据
                $offset = ($page - 1) * $limit;
                $sql = "SELECT o.*, u.username, u.nickname, u.avatar
                        FROM {$prefix}order o
                        LEFT JOIN {$prefix}user u ON o.user_id = u.id
                        WHERE {$where}
                        ORDER BY o.id DESC
                        LIMIT {$limit} OFFSET {$offset}";
                $rows = Database::query($sql, $params);

                // 支付方式 code → image 映射（PaymentService::getMethods 拿当前可用的支付方式
                // 元数据，含 image 字段；订单表本身只存 payment_code/payment_name，没存 image）
                $paymentImageMap = [];
                try {
                    foreach (PaymentService::getMethods() as $m) {
                        $code = (string) ($m['code'] ?? '');
                        if ($code !== '') $paymentImageMap[$code] = (string) ($m['image'] ?? '');
                    }
                } catch (Throwable $e) {
                    // 拿不到支付方式列表也不影响列表展示，image 默认为空即可
                }

                // 金额转换 + 预置 goods 字段（金额用千分位展示，小数保留 2 位）
                foreach ($rows as &$row) {
                    $row['goods_amount_fmt'] = number_format((float) bcdiv((string) ($row['goods_amount'] ?? 0), '1000000', 2), 2, '.', ',');
                    $row['pay_amount_fmt']   = number_format((float) bcdiv((string) ($row['pay_amount']   ?? 0), '1000000', 2), 2, '.', ',');
                    $row['status_name'] = OrderModel::statusName($row['status']);
                    $row['payment_image'] = $paymentImageMap[(string) ($row['payment_code'] ?? '')] ?? '';
                    $row['goods'] = [];
                    $row['goods_count'] = 0;
                }
                unset($row);

                // 一次性批量捞商品列表（避免 N+1）：WHERE order_id IN (...) 按 order 分组
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

                Response::success('', [
                    'data' => array_values($rows),
                    'total' => $total,
                    'csrf_token' => Csrf::token(),
                ]);
                break;

            // 单条删除 / 批量删除
            // 级联清理 order + order_goods + delivery_queue；用事务保证原子性
            case 'delete':
            case 'batch_delete':
                if ($action === 'batch_delete') {
                    $raw = Input::post('ids', '');
                    $ids = [];
                    if (is_array($raw)) {
                        foreach ($raw as $v) {
                            $v = (int) $v;
                            if ($v > 0) $ids[] = $v;
                        }
                    } else {
                        foreach (explode(',', (string) $raw) as $v) {
                            $v = (int) trim($v);
                            if ($v > 0) $ids[] = $v;
                        }
                    }
                    $ids = array_values(array_unique($ids));
                } else {
                    $id = (int) Input::post('id', 0);
                    $ids = $id > 0 ? [$id] : [];
                }

                if (!$ids) {
                    Response::error('请选择要删除的订单');
                }

                $placeholders = implode(',', array_fill(0, count($ids), '?'));

                Database::begin();
                try {
                    Database::execute("DELETE FROM {$prefix}delivery_queue WHERE order_id IN ({$placeholders})", $ids);
                    Database::execute("DELETE FROM {$prefix}order_goods WHERE order_id IN ({$placeholders})", $ids);
                    $deleted = Database::execute("DELETE FROM {$prefix}order WHERE id IN ({$placeholders})", $ids);
                    Database::commit();
                } catch (Throwable $e) {
                    Database::rollBack();
                    Response::error('删除失败：' . $e->getMessage());
                }

                Response::success('已删除 ' . (int) $deleted . ' 条订单', [
                    'csrf_token' => Csrf::token(),
                ]);
                break;

            // 查询订单详情（AJAX）
            case 'detail':
                $orderId = (int) Input::post('id', 0);
                $order = OrderModel::getById($orderId);
                if (!$order) {
                    Response::error('订单不存在');
                }

                $order['goods_amount_fmt'] = bcdiv((string) ($order['goods_amount'] ?? 0), '1000000', 2);
                $order['pay_amount_fmt'] = bcdiv((string) ($order['pay_amount'] ?? 0), '1000000', 2);
                $order['status_name'] = OrderModel::statusName($order['status']);

                // 订单商品
                $orderGoods = OrderModel::getOrderGoods($orderId);
                foreach ($orderGoods as &$og) {
                    $og['price_fmt'] = bcdiv((string) ($og['price'] ?? 0), '1000000', 2);
                }
                unset($og);

                // 用户信息
                $buyer = null;
                if ((int) $order['user_id'] > 0) {
                    $buyer = (new UserListModel())->findById((int) $order['user_id']);
                }

                // 队列任务状态
                $queueTasks = Database::query(
                    "SELECT * FROM {$prefix}delivery_queue WHERE order_id = ? ORDER BY id",
                    [$orderId]
                );

                Response::success('', [
                    'order'       => $order,
                    'order_goods' => $orderGoods,
                    'buyer'       => $buyer,
                    'queue_tasks' => $queueTasks,
                    'csrf_token'  => Csrf::token(),
                ]);
                break;

            // 管理员手工标记已支付（仅待支付订单）
            // 行为对齐正常支付回调：
            //   1) pending -> paid
            //   2) 写一条 order_payment 成功流水（manual）
            //   3) 触发发货队列
            case 'mark_paid': {
                $orderId = (int) Input::post('id', 0);
                if ($orderId <= 0) {
                    Response::error('参数错误');
                }

                $order = OrderModel::getById($orderId);
                if (!$order) {
                    Response::error('订单不存在');
                }
                if ((string) ($order['status'] ?? '') !== 'pending') {
                    Response::error('仅待付款订单可执行此操作');
                }

                Database::begin();
                try {
                    OrderModel::changeStatus($orderId, 'paid');

                    $hasSuccessPayment = Database::fetchOne(
                        "SELECT id FROM {$prefix}order_payment WHERE order_id = ? AND status = 'success' LIMIT 1",
                        [$orderId]
                    );
                    if (!$hasSuccessPayment) {
                        Database::execute(
                            "INSERT INTO {$prefix}order_payment
                             (order_id, payment_code, payment_plugin, trade_no, amount, status, paid_at, created_at)
                             VALUES (?, ?, ?, ?, ?, 'success', NOW(), NOW())",
                            [
                                $orderId,
                                (string) ($order['payment_code'] ?? 'manual'),
                                (string) ($order['payment_plugin'] ?? 'manual'),
                                'MANUAL-' . (string) ($order['order_no'] ?? (string) $orderId),
                                (int) ($order['pay_amount'] ?? 0),
                            ]
                        );
                    }

                    Database::commit();
                } catch (Throwable $e) {
                    Database::rollBack();
                    Response::error('标记失败：' . $e->getMessage());
                }

                try {
                    OrderModel::triggerDelivery($orderId);
                } catch (Throwable $e) {
                    // 发货触发失败不影响"已支付"结果，提示管理员后续手工处理
                    Response::success('订单已标记为已支付，但触发发货失败：' . $e->getMessage(), [
                        'csrf_token' => Csrf::refresh(),
                    ]);
                }

                Response::success('订单已标记为已支付', [
                    'csrf_token' => Csrf::refresh(),
                ]);
                break;
            }

            // 管理员手动发货：单条 order_goods
            //   - 调 goods_type_{type}_manual_delivery_submit filter 让插件准备 delivery_content / plugin_data
            //   - 插件返回字符串 = 校验失败消息；返回数组 = 成功 payload
            //   - 状态流转由 OrderModel::manualShipOrderGoods 统一处理
            case 'ship': {
                $ogId = (int) Input::post('order_goods_id', 0);
                if ($ogId <= 0) Response::error('参数错误');

                $ogRow = Database::fetchOne(
                    "SELECT og.*, o.status AS order_status FROM {$prefix}order_goods og
                     LEFT JOIN {$prefix}order o ON o.id = og.order_id
                     WHERE og.id = ?",
                    [$ogId]
                );
                if (!$ogRow) Response::error('订单商品不存在');
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
    } catch (Throwable $e) {
        Response::error($e->getMessage());
    }
}

// 订单详情弹窗（iframe 内独立页面）
// 列表页用 layer.open type:2 加载 /admin/order.php?_popup=detail&id=X
if ((string) Input::get('_popup', '') === 'detail') {
    $csrfToken = Csrf::token();
    $primaryCurrency = Currency::getInstance()->getPrimary();
    $currencySymbol = $primaryCurrency ? ($primaryCurrency['symbol'] ?? '¥') : '¥';

    $orderId = (int) Input::get('id', 0);
    $order = OrderModel::getById($orderId);
    if (!$order) {
        http_response_code(404);
        echo '订单不存在';
        return;
    }
    // 订单详情金额统一千分位
    $order['goods_amount_fmt'] = number_format((float) bcdiv((string) ($order['goods_amount'] ?? 0), '1000000', 2), 2, '.', ',');
    $order['pay_amount_fmt']   = number_format((float) bcdiv((string) ($order['pay_amount']   ?? 0), '1000000', 2), 2, '.', ',');
    $order['status_name']      = OrderModel::statusName($order['status']);

    $orderGoods = OrderModel::getOrderGoods($orderId);
    foreach ($orderGoods as &$og) {
        $og['price_fmt'] = number_format((float) bcdiv((string) ($og['price'] ?? 0), '1000000', 2), 2, '.', ',');
    }
    unset($og);

    $buyer = null;
    if ((int) $order['user_id'] > 0) {
        $buyer = (new UserListModel())->findById((int) $order['user_id']);
    }

    $queueTasks = Database::query(
        "SELECT * FROM " . Database::prefix() . "delivery_queue WHERE order_id = ? ORDER BY id",
        [$orderId]
    );

    $pageTitle = '订单详情 #' . $order['order_no'];
    include __DIR__ . '/view/popup/order_detail.php';
    return;
}

// 发货 popup（iframe）：只展示订单里"未发货"的 order_goods，每条调 filter 让插件返回自己的表单 HTML
if ((string) Input::get('_popup', '') === 'ship') {
    $csrfToken = Csrf::token();
    $orderId = (int) Input::get('id', 0);
    $order = OrderModel::getById($orderId);
    if (!$order) {
        http_response_code(404);
        echo '订单不存在';
        return;
    }

    // 仅已付款/发货中的订单允许手动发货；状态机由核心掌控，避免对已完成 / 已退款订单操作
    $allowedShipStatus = ['paid', 'delivering', 'delivery_failed'];
    if (!in_array((string) $order['status'], $allowedShipStatus, true)) {
        $pageTitle = '无法发货';
        $shipError = '当前订单状态（' . OrderModel::statusName($order['status']) . '）不允许手动发货';
        include __DIR__ . '/view/popup/order_ship.php';
        return;
    }

    // 列出未发货的 order_goods（已发货的不再出现在发货页）
    $shippableGoods = array_values(array_filter(
        OrderModel::getOrderGoods($orderId),
        static fn($og) => empty($og['delivery_content'])
    ));

    $pageTitle = '发货 - ' . $order['order_no'];
    include __DIR__ . '/view/popup/order_ship.php';
    return;
}

// 页面渲染
$csrfToken = Csrf::token();
$primaryCurrency = Currency::getInstance()->getPrimary();
$currencySymbol = $primaryCurrency ? ($primaryCurrency['symbol'] ?? '¥') : '¥';

if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/order.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/order.php';
    require __DIR__ . '/index.php';
}
