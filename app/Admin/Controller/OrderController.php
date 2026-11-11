<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 后台订单管理（/admin/order）。
 *
 * 动作：list / delete / batch_delete / clear_by_status / detail / mark_paid / ship
 * 弹窗：?_popup=detail / ship
 *
 * 过渡期列表页仍套用旧后台框架壳（admin/view/index.php）。
 */
final class OrderController extends BaseAdminController
{
    public function index(): void
    {
        if (\Request::isPost()) {
            $this->handlePost();
            return;
        }

        $popup = (string) \Input::get('_popup', '');
        if ($popup === 'detail') {
            $this->renderDetailPopup();
            return;
        }
        if ($popup === 'ship') {
            $this->renderShipPopup();
            return;
        }

        $primaryCurrency = \Currency::getInstance()->getPrimary();
        $currencySymbol = $primaryCurrency ? ($primaryCurrency['symbol'] ?? '¥') : '¥';

        if (\Request::isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('order', ['currencySymbol' => $currencySymbol]);
            echo '</div>';
            return;
        }

        require_once EM_ROOT . '/include/lib/AdminLang.php';

        $user = $this->user();
        $siteName = (string) \Config::get('sitename', 'EMSHOP');
        $languages = (new \LanguageModel())->getEnabled();
        $emServerHardRestartPending = is_file(EM_ROOT . '/.server') && \WorkerHeartbeat::hostAlive();
        $adminContentView = dirname(__DIR__) . '/View/order.php';

        require EM_ROOT . '/admin/view/index.php';
    }

    /**
     * POST 处理。
     */
    private function handlePost(): void
    {
        try {
            $action = (string) \Input::post('_action', '');

            if ($action !== 'list') {
                $csrf = (string) \Input::post('csrf_token', '');
                if (!\Csrf::validate($csrf)) {
                    \Response::error('请求已失效，请刷新页面后重试');
                }
            }

            $prefix = \Database::prefix();

            switch ($action) {
                case 'list':
                    $this->handleList($prefix);
                    break;

                // 单条删除 / 批量删除
                // 级联清理 order + order_goods + delivery_queue；用事务保证原子性
                case 'delete':
                case 'batch_delete':
                    $this->handleDelete($prefix, $action);
                    break;

                // 按状态清空：仅允许 pending（未支付）/ expired（已过期）
                case 'clear_by_status':
                    $this->handleClearByStatus($prefix);
                    break;

                // 查询订单详情（AJAX）
                case 'detail':
                    $this->handleDetail($prefix);
                    break;

                // 管理员手工标记已支付（待支付 / 已过期订单补单）
                case 'mark_paid':
                    $this->handleMarkPaid($prefix);
                    break;

                // 管理员手动发货：单条 order_goods
                case 'ship':
                    $this->handleShip();
                    break;

                default:
                    \Response::error('未知操作');
            }
        } catch (\Throwable $e) {
            \Response::error($e->getMessage());
        }
    }

    /**
     * 列表。
     */
    private function handleList(string $prefix): void
    {
        $page = max(1, (int) \Input::post('page', 1));
        $limit = (int) \Input::post('limit', 15);
        if ($limit < 1 || $limit > 100) {
            $limit = 15;
        }

        $keyword = trim((string) \Input::post('keyword', ''));
        $status = trim((string) \Input::post('status', ''));
        // 站点归属筛选：'' / 'main' / 'sub'
        $scope = trim((string) \Input::post('scope', ''));

        $where = '1=1';
        $params = [];

        if ($keyword !== '') {
            // 快捷搜索：订单号 / 商品名 / 用户名 / 昵称 / 邮箱 / 手机 / 游客联系方式 / 订单密码
            $kw = '%' . $keyword . '%';
            $where .= ' AND ('
                    . 'o.order_no LIKE ? '
                    . 'OR o.guest_contact LIKE ? '
                    . 'OR o.order_password LIKE ? '
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
            $params[] = $kw;
            $params[] = $kw;
        }
        if ($status !== '') {
            $where .= ' AND o.status = ?';
            $params[] = $status;
        }
        if ($scope === 'main') {
            $where .= ' AND o.merchant_id = 0';
        } elseif ($scope === 'sub') {
            $where .= ' AND o.merchant_id > 0';
        }

        // 总数
        $countSql = "SELECT COUNT(*) as cnt FROM {$prefix}order o LEFT JOIN {$prefix}user u ON o.user_id = u.id WHERE {$where}";
        $total = (int) (\Database::fetchOne($countSql, $params)['cnt'] ?? 0);

        // 数据
        $offset = ($page - 1) * $limit;
        $sql = "SELECT o.*, u.username, u.nickname, u.avatar
                FROM {$prefix}order o
                LEFT JOIN {$prefix}user u ON o.user_id = u.id
                WHERE {$where}
                ORDER BY o.id DESC
                LIMIT {$limit} OFFSET {$offset}";
        $rows = \Database::query($sql, $params);

        // 支付方式 code → image 映射
        $paymentImageMap = [];
        try {
            foreach (\PaymentService::getMethods() as $m) {
                $code = (string) ($m['code'] ?? '');
                if ($code !== '') {
                    $paymentImageMap[$code] = (string) ($m['image'] ?? '');
                }
            }
        } catch (\Throwable $e) {
            // 拿不到支付方式列表也不影响列表展示
        }

        // 金额转换 + 预置 goods 字段
        foreach ($rows as &$row) {
            $row['goods_amount_fmt'] = number_format((float) bcdiv((string) ($row['goods_amount'] ?? 0), '1000000', 2), 2, '.', ',');
            $row['pay_amount_fmt'] = number_format((float) bcdiv((string) ($row['pay_amount'] ?? 0), '1000000', 2), 2, '.', ',');
            $row['status_name'] = \OrderModel::statusName($row['status']);
            $row['payment_image'] = $paymentImageMap[(string) ($row['payment_code'] ?? '')] ?? '';
            $row['goods'] = [];
            $row['goods_count'] = 0;
        }
        unset($row);

        // 一次性批量捞商品列表（避免 N+1）
        if ($rows) {
            $orderIds = array_map(static fn($r) => (int) $r['id'], $rows);
            $ph = implode(',', array_fill(0, count($orderIds), '?'));
            $goodsRows = \Database::query(
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

        \Response::success('', [
            'data' => array_values($rows),
            'total' => $total,
            'csrf_token' => \Csrf::token(),
        ]);
    }

    /**
     * 单条 / 批量删除订单。
     */
    private function handleDelete(string $prefix, string $action): void
    {
        if ($action === 'batch_delete') {
            $raw = \Input::post('ids', '');
            $ids = [];
            if (is_array($raw)) {
                foreach ($raw as $v) {
                    $v = (int) $v;
                    if ($v > 0) {
                        $ids[] = $v;
                    }
                }
            } else {
                foreach (explode(',', (string) $raw) as $v) {
                    $v = (int) trim($v);
                    if ($v > 0) {
                        $ids[] = $v;
                    }
                }
            }
            $ids = array_values(array_unique($ids));
        } else {
            $id = (int) \Input::post('id', 0);
            $ids = $id > 0 ? [$id] : [];
        }

        if (!$ids) {
            \Response::error('请选择要删除的订单');
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        \Database::begin();
        try {
            \Database::execute("DELETE FROM {$prefix}delivery_queue WHERE order_id IN ({$placeholders})", $ids);
            \Database::execute("DELETE FROM {$prefix}order_goods WHERE order_id IN ({$placeholders})", $ids);
            $deleted = \Database::execute("DELETE FROM {$prefix}order WHERE id IN ({$placeholders})", $ids);
            \Database::commit();
        } catch (\Throwable $e) {
            \Database::rollBack();
            \Response::error('删除失败：' . $e->getMessage());
        }

        \Response::success('已删除 ' . (int) $deleted . ' 条订单', [
            'csrf_token' => \Csrf::token(),
        ]);
    }

    /**
     * 按状态清空（仅 pending / expired）。
     */
    private function handleClearByStatus(string $prefix): void
    {
        $status = trim((string) \Input::post('status', ''));
        $allowed = ['pending' => '未支付', 'expired' => '已过期'];
        if (!isset($allowed[$status])) {
            \Response::error('不支持清空该状态的订单');
        }

        $idRows = \Database::query(
            "SELECT id FROM {$prefix}order WHERE status = ?",
            [$status]
        );
        $ids = array_map(static fn($r) => (int) $r['id'], $idRows);
        if (!$ids) {
            \Response::success('没有可清空的' . $allowed[$status] . '订单', [
                'deleted' => 0,
                'csrf_token' => \Csrf::token(),
            ]);
        }

        $deleted = 0;
        \Database::begin();
        try {
            // 分批删除，避免一次 IN 列表过长
            foreach (array_chunk($ids, 500) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));

                // 事务内加锁重查：SELECT 与删除之间订单可能已被支付回调改走
                $locked = \Database::query(
                    "SELECT id FROM {$prefix}order
                      WHERE id IN ({$placeholders}) AND status = ?
                      FOR UPDATE",
                    array_merge($chunk, [$status])
                );
                $okIds = array_map(static fn($r) => (int) $r['id'], $locked);
                if (!$okIds) {
                    continue; // 本批已全部变状态，跳过
                }

                $okPlaceholders = implode(',', array_fill(0, count($okIds), '?'));
                \Database::execute("DELETE FROM {$prefix}delivery_queue WHERE order_id IN ({$okPlaceholders})", $okIds);
                \Database::execute("DELETE FROM {$prefix}order_goods WHERE order_id IN ({$okPlaceholders})", $okIds);
                $deleted += (int) \Database::execute("DELETE FROM {$prefix}order WHERE id IN ({$okPlaceholders})", $okIds);
            }
            \Database::commit();
        } catch (\Throwable $e) {
            \Database::rollBack();
            \Response::error('清空失败：' . $e->getMessage());
        }

        \Response::success('已清空 ' . $deleted . ' 条' . $allowed[$status] . '订单', [
            'deleted' => $deleted,
            'csrf_token' => \Csrf::token(),
        ]);
    }

    /**
     * 订单详情（AJAX）。
     */
    private function handleDetail(string $prefix): void
    {
        $orderId = (int) \Input::post('id', 0);
        $order = \OrderModel::getById($orderId);
        if (!$order) {
            \Response::error('订单不存在');
        }

        $order['goods_amount_fmt'] = bcdiv((string) ($order['goods_amount'] ?? 0), '1000000', 2);
        $order['pay_amount_fmt'] = bcdiv((string) ($order['pay_amount'] ?? 0), '1000000', 2);
        $order['status_name'] = \OrderModel::statusName($order['status']);

        // 订单商品
        $orderGoods = \OrderModel::getOrderGoods($orderId);
        foreach ($orderGoods as &$og) {
            $og['price_fmt'] = bcdiv((string) ($og['price'] ?? 0), '1000000', 2);
        }
        unset($og);

        // 用户信息
        $buyer = null;
        if ((int) $order['user_id'] > 0) {
            $buyer = (new \UserListModel())->findById((int) $order['user_id']);
        }

        // 队列任务状态
        $queueTasks = \Database::query(
            "SELECT * FROM {$prefix}delivery_queue WHERE order_id = ? ORDER BY id",
            [$orderId]
        );

        \Response::success('', [
            'order'       => $order,
            'order_goods' => $orderGoods,
            'buyer'       => $buyer,
            'queue_tasks' => $queueTasks,
            'csrf_token'  => \Csrf::token(),
        ]);
    }

    /**
     * 手工标记已支付。
     */
    private function handleMarkPaid(string $prefix): void
    {
        $orderId = (int) \Input::post('id', 0);
        if ($orderId <= 0) {
            \Response::error('参数错误');
        }

        $order = \OrderModel::getById($orderId);
        if (!$order) {
            \Response::error('订单不存在');
        }
        if (!in_array((string) ($order['status'] ?? ''), ['pending', 'expired'], true)) {
            \Response::error('仅待付款或已过期订单可执行此操作');
        }

        \Database::begin();
        try {
            \OrderModel::changeStatus($orderId, 'paid');

            $hasSuccessPayment = \Database::fetchOne(
                "SELECT id FROM {$prefix}order_payment WHERE order_id = ? AND status = 'success' LIMIT 1",
                [$orderId]
            );
            if (!$hasSuccessPayment) {
                \Database::execute(
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

            \Database::commit();
        } catch (\OrderStatusConflictException $e) {
            // 并发下的重复标记：若订单已被并发改成已支付，按成功返回
            \Database::rollBack();
            $nowRow = \Database::fetchOne(
                "SELECT status FROM {$prefix}order WHERE id = ?",
                [$orderId]
            );
            if ((string) ($nowRow['status'] ?? '') === 'paid') {
                \Response::success('订单已是已支付状态', ['csrf_token' => \Csrf::refresh()]);
            }
            \Response::error('订单状态已被其他操作变更，请刷新后重试');
        } catch (\Throwable $e) {
            \Database::rollBack();
            \Response::error('标记失败：' . $e->getMessage());
        }

        try {
            \OrderModel::triggerDelivery($orderId);
        } catch (\Throwable $e) {
            // 发货触发失败不影响"已支付"结果
            \Response::success('订单已标记为已支付，但触发发货失败：' . $e->getMessage(), [
                'csrf_token' => \Csrf::refresh(),
            ]);
        }

        \Response::success('订单已标记为已支付', [
            'csrf_token' => \Csrf::refresh(),
        ]);
    }

    /**
     * 手动发货。
     */
    private function handleShip(): void
    {
        $prefix = \Database::prefix();

        $ogId = (int) \Input::post('order_goods_id', 0);
        if ($ogId <= 0) {
            \Response::error('参数错误');
        }

        $ogRow = \Database::fetchOne(
            "SELECT og.*, o.status AS order_status FROM {$prefix}order_goods og
             LEFT JOIN {$prefix}order o ON o.id = og.order_id
             WHERE og.id = ?",
            [$ogId]
        );
        if (!$ogRow) {
            \Response::error('订单商品不存在');
        }
        if (\OrderModel::hasDeliveryContent($ogId, (string) ($ogRow['delivery_content'] ?? ''))) {
            \Response::error('该商品已发货');
        }

        $goodsType = (string) ($ogRow['goods_type'] ?? '');
        if ($goodsType === '') {
            \Response::error('商品类型缺失，无法发货');
        }

        $result = \applyFilter(
            "goods_type_{$goodsType}_manual_delivery_submit",
            null,
            ['order_goods' => $ogRow, 'post' => $_POST]
        );

        if (is_string($result)) {
            \Response::error($result);
        }
        if (!is_array($result) || !isset($result['delivery_content'])) {
            \Response::error('该商品类型暂未提供发货处理，请联系插件开发者');
        }

        $deliveryContent = (string) $result['delivery_content'];
        $pluginData = isset($result['plugin_data']) && is_array($result['plugin_data']) ? $result['plugin_data'] : null;

        \OrderModel::manualShipOrderGoods($ogId, $deliveryContent, $pluginData);

        \Response::success('发货成功', ['csrf_token' => \Csrf::refresh()]);
    }

    /**
     * 订单详情弹窗。
     */
    private function renderDetailPopup(): void
    {
        $prefix = \Database::prefix();
        $primaryCurrency = \Currency::getInstance()->getPrimary();
        $currencySymbol = $primaryCurrency ? ($primaryCurrency['symbol'] ?? '¥') : '¥';

        $orderId = (int) \Input::get('id', 0);
        $order = \OrderModel::getById($orderId);
        if (!$order) {
            http_response_code(404);
            echo '订单不存在';
            return;
        }
        // 订单详情金额统一千分位
        $order['goods_amount_fmt'] = number_format((float) bcdiv((string) ($order['goods_amount'] ?? 0), '1000000', 2), 2, '.', ',');
        $order['pay_amount_fmt'] = number_format((float) bcdiv((string) ($order['pay_amount'] ?? 0), '1000000', 2), 2, '.', ',');
        $order['status_name'] = \OrderModel::statusName($order['status']);

        $orderGoods = \OrderModel::getOrderGoods($orderId);
        foreach ($orderGoods as &$og) {
            $og['price_fmt'] = number_format((float) bcdiv((string) ($og['price'] ?? 0), '1000000', 2), 2, '.', ',');
        }
        unset($og);

        $buyer = null;
        if ((int) $order['user_id'] > 0) {
            $buyer = (new \UserListModel())->findById((int) $order['user_id']);
        }

        $queueTasks = \Database::query(
            "SELECT * FROM " . \Database::prefix() . "delivery_queue WHERE order_id = ? ORDER BY id",
            [$orderId]
        );

        $this->render('popup/order_detail', [
            'order'          => $order,
            'orderGoods'     => $orderGoods,
            'buyer'          => $buyer,
            'queueTasks'     => $queueTasks,
            'currencySymbol' => $currencySymbol,
            'csrfToken'      => \Csrf::token(),
            'pageTitle'      => '订单详情 #' . $order['order_no'],
        ]);
    }

    /**
     * 发货弹窗。
     */
    private function renderShipPopup(): void
    {
        $orderId = (int) \Input::get('id', 0);
        $order = \OrderModel::getById($orderId);
        if (!$order) {
            http_response_code(404);
            echo '订单不存在';
            return;
        }

        $shipSubmitUrl = '/admin/order';
        $data = [
            'order'         => $order,
            'shipSubmitUrl' => $shipSubmitUrl,
            'csrfToken'     => \Csrf::token(),
        ];

        // 仅已付款/发货中的订单允许手动发货；状态机由核心掌控
        $allowedShipStatus = ['paid', 'delivering', 'delivery_failed'];
        if (!in_array((string) $order['status'], $allowedShipStatus, true)) {
            $data['pageTitle'] = '无法发货';
            $data['shipError'] = '当前订单状态（' . \OrderModel::statusName($order['status']) . '）不允许手动发货';
            $this->render('popup/order_ship', $data);
            return;
        }

        // 列出未发货的 order_goods
        $data['shippableGoods'] = array_values(array_filter(
            \OrderModel::getOrderGoods($orderId),
            static fn($og) => !\OrderModel::hasDeliveryContent((int) ($og['id'] ?? 0), (string) ($og['delivery_content'] ?? ''))
        ));
        $data['pageTitle'] = '发货 - ' . $order['order_no'];

        $this->render('popup/order_ship', $data);
    }
}
