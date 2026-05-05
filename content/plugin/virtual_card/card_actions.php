<?php
/**
 * 虚拟商品（自动发货）插件 — 卡密库存 AJAX 接口
 *
 * 通过 admin_plugin_action 钩子由插件自行注册路由，不放在核心代码中。
 * 支持的 action：card_list / card_import / card_delete / card_export / card_manager
 */
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

$action = (string)($_GET['_action'] ?? '');

// ================================================================
// 卡密列表（供 AJAX 分页查询）
// ================================================================
if ($action === 'card_list') {
    $goodsId = (int)($_POST['goods_id'] ?? 0);
    if (!$goodsId) {
        Response::error('商品ID不能为空');
    }

    $page = max(1, (int)($_POST['page'] ?? 1));
    $limit = min(100, max(10, (int)($_POST['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $status = $_POST['status'] ?? '';
    $keyword = trim($_POST['keyword'] ?? '');

    $conditions = ['goods_id = ?'];
    $params = [$goodsId];

    if ($status !== '' && $status !== 'all') {
        $conditions[] = 'status = ?';
        $params[] = (int)$status;
    }

    if ($keyword !== '') {
        $conditions[] = '(card_no LIKE ? OR remark LIKE ?)';
        $kw = '%' . $keyword . '%';
        $params[] = $kw;
        $params[] = $kw;
    }

    $specIdFilter = (int)($_POST['spec_id'] ?? 0);
    if ($specIdFilter > 0) {
        $conditions[] = 'spec_id = ?';
        $params[] = $specIdFilter;
    }

    $whereSql = 'WHERE ' . implode(' AND ', $conditions);

    $total = Database::query(
        "SELECT COUNT(*) as cnt FROM " . Database::prefix() . "goods_virtual_card {$whereSql}",
        $params
    );
    $totalCount = (int)($total[0]['cnt'] ?? 0);

    $cards = Database::query(
        "SELECT * FROM " . Database::prefix() . "goods_virtual_card {$whereSql} ORDER BY id DESC LIMIT {$offset}, {$limit}",
        $params
    );

    // 统计各状态数量（供前端选项卡和规格库存卡片刷新）
    $statsRows = Database::query(
        "SELECT status, COUNT(*) as cnt FROM " . Database::prefix() . "goods_virtual_card WHERE goods_id = ? GROUP BY status",
        [$goodsId]
    );
    $statsMap = ['available' => 0, 'sold' => 0, 'marked' => 0, 'total' => 0];
    foreach ($statsRows as $sr) {
        $cnt = (int)$sr['cnt'];
        $statsMap['total'] += $cnt;
        switch ((int)$sr['status']) {
            case 1: $statsMap['available'] = $cnt; break;
            case 0: $statsMap['sold'] = $cnt; break;
            case 2: $statsMap['marked'] = $cnt; break;
        }
    }

    // 每个规格的可用卡密数（供前端规格库存概览卡片刷新）
    $specStatsRows = Database::query(
        "SELECT spec_id, COUNT(*) as cnt FROM " . Database::prefix() . "goods_virtual_card WHERE goods_id = ? AND status = 1 AND spec_id IS NOT NULL GROUP BY spec_id",
        [$goodsId]
    );
    $specStats = [];
    foreach ($specStatsRows as $ssr) {
        $specStats[] = ['id' => (int)$ssr['spec_id'], 'available' => (int)$ssr['cnt']];
    }
    $statsMap['specs'] = $specStats;

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'code' => 0,
        'msg' => '',
        'count' => $totalCount,
        'data' => $cards,
        'stats' => $statsMap,
        'csrf_token' => Csrf::token(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ================================================================
// 导入卡密弹窗页面
// ================================================================
if ($action === 'card_import_page') {
    $goodsId = (int)($_GET['goods_id'] ?? 0);
    if (!$goodsId) {
        exit('商品ID不能为空');
    }
    $goods = GoodsModel::getById($goodsId);
    if (!$goods) {
        exit('商品不存在');
    }
    $specs = GoodsModel::getSpecsByGoodsId($goodsId);
    $csrfToken = Csrf::token();
    $pageTitle = '导入卡密';

    include EM_ROOT . '/admin/view/popup/header.php';
    include __DIR__ . '/card_import_page.php';
    include EM_ROOT . '/admin/view/popup/footer.php';
    exit;
}

// ================================================================
// 导入卡密（高性能批量导入）
// ================================================================
if ($action === 'card_import') {
    $goodsId = (int)($_POST['goods_id'] ?? 0);
    if (!$goodsId) {
        Response::error('商品ID不能为空');
    }
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!Csrf::validate($csrf)) {
        Response::error('请求已失效，请刷新页面后重试');
    }

    $specId = (int)($_POST['spec_id'] ?? 0);
    if (!$specId) {
        Response::error('请选择规格');
    }

    $content = trim($_POST['cards'] ?? '');
    if ($content === '') {
        Response::error('请输入卡密内容');
    }

    $goods = GoodsModel::getById($goodsId);
    if (!$goods) {
        Response::error('商品不存在');
    }

    $order = $_POST['order'] ?? 'asc';    // asc / desc / shuffle
    $dedup = !empty($_POST['dedup']);       // 是否去重
    $remark = trim($_POST['remark'] ?? '');
    $prefix = Database::prefix();
    $now = date('Y-m-d H:i:s');

    // 1. 解析所有行，提取卡号和密码
    $lines = explode("\n", $content);
    $parsed = []; // [{card_no, card_pwd}, ...]
    $cardNos = []; // 用于去重查询
    foreach ($lines as $line) {
        $raw = trim($line);
        if ($raw === '') continue;

        $cardNo = $raw;
        $cardPwd = null;
        if (strpos($raw, ':') !== false) {
            [$cardNo, $cardPwd] = explode(':', $raw, 2);
        } elseif (strpos($raw, '|') !== false) {
            [$cardNo, $cardPwd] = explode('|', $raw, 2);
        }
        $cardNo = trim($cardNo);
        $cardPwd = ($cardPwd !== null && trim($cardPwd) !== '') ? trim($cardPwd) : null;

        if ($cardNo === '') continue;

        // 输入内去重（仅勾选去重时，相同卡号取第一条）
        if ($dedup) {
            if (isset($cardNos[$cardNo])) continue;
            $cardNos[$cardNo] = true;
        }

        $parsed[] = ['card_no' => $cardNo, 'card_pwd' => $cardPwd];
    }

    if (empty($parsed)) {
        Response::error('未检测到有效的卡密内容');
    }

    // 2. 排序处理
    if ($order === 'desc') {
        $parsed = array_reverse($parsed);
    } elseif ($order === 'shuffle') {
        shuffle($parsed);
    }

    // 3. 数据库去重：批量查询已存在的卡号（分批查，每批 5000 条）
    $existingSet = [];
    if ($dedup) {
        $allCardNos = array_column($parsed, 'card_no');
        $chunks = array_chunk($allCardNos, 5000);
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $rows = Database::query(
                "SELECT card_no FROM {$prefix}goods_virtual_card WHERE goods_id = ? AND card_no IN ({$placeholders})",
                array_merge([$goodsId], $chunk)
            );
            foreach ($rows as $r) {
                $existingSet[$r['card_no']] = true;
            }
        }
    }

    // 4. 过滤已存在的卡密
    $toInsert = [];
    $skipped = 0;
    foreach ($parsed as $item) {
        if ($dedup && isset($existingSet[$item['card_no']])) {
            $skipped++;
            continue;
        }
        $toInsert[] = $item;
    }

    if (empty($toInsert)) {
        Response::success("导入完成：成功 0 条，跳过 {$skipped} 条（全部重复）", [
            'imported' => 0,
            'skipped' => $skipped,
            'csrf_token' => Csrf::token(),
        ]);
    }

    // 5. 批量插入（每批 1000 条，拼接 VALUES 多行 INSERT）
    $imported = 0;
    $batchSize = 1000;
    $batches = array_chunk($toInsert, $batchSize);

    foreach ($batches as $batch) {
        $valuesParts = [];
        $params = [];
        foreach ($batch as $item) {
            $valuesParts[] = '(?, ?, ?, ?, 1, ?, ?)';
            $params[] = $goodsId;
            $params[] = $specId;
            $params[] = $item['card_no'];
            $params[] = $item['card_pwd'];
            $params[] = $remark !== '' ? $remark : null;
            $params[] = $now;
        }
        $sql = "INSERT INTO {$prefix}goods_virtual_card (goods_id, spec_id, card_no, card_pwd, status, remark, created_at) VALUES "
             . implode(',', $valuesParts);
        Database::execute($sql, $params);
        $imported += count($batch);
    }

    // 6. 同步卡密数量到规格库存
    virtualCardSyncCardStock($goodsId);

    $msg = "导入完成：成功 {$imported} 条";
    if ($skipped > 0) {
        $msg .= "，跳过重复 {$skipped} 条";
    }
    Response::success($msg, [
        'imported' => $imported,
        'skipped' => $skipped,
        'csrf_token' => Csrf::token(),
    ]);
}

// ================================================================
// 删除卡密
// ================================================================
if ($action === 'card_delete') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!Csrf::validate($csrf)) {
        Response::error('请求已失效，请刷新页面后重试');
    }
    $ids = $_POST['ids'] ?? [];
    if (empty($ids)) {
        Response::error('请选择要删除的卡密');
    }

    if (!is_array($ids)) {
        $ids = [(int)$ids];
    }
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids);

    if (empty($ids)) {
        Response::error('无效的ID');
    }

    // 删除前先获取 goods_id，用于后续同步库存
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $goodsIds = Database::query(
        "SELECT DISTINCT goods_id FROM " . Database::prefix() . "goods_virtual_card WHERE id IN ({$placeholders})",
        $ids
    );

    $result = Database::execute(
        "DELETE FROM " . Database::prefix() . "goods_virtual_card WHERE id IN ({$placeholders})",
        $ids
    );

    // 同步卡密数量到规格库存
    foreach ($goodsIds as $row) {
        virtualCardSyncCardStock((int)$row['goods_id']);
    }

    Response::success("已删除 {$result} 条卡密", [
        'deleted' => $result,
        'csrf_token' => Csrf::token(),
    ]);
}

// ================================================================
// 导出卡密
// ================================================================
if ($action === 'card_export') {
    $goodsId = (int)($_GET['goods_id'] ?? 0);
    if (!$goodsId) {
        exit('商品ID不能为空');
    }

    $status = $_GET['status'] ?? '';
    $conditions = ['goods_id = ?'];
    $params = [$goodsId];

    if ($status !== '' && $status !== 'all') {
        $conditions[] = 'status = ?';
        $params[] = (int)$status;
    }

    $whereSql = 'WHERE ' . implode(' AND ', $conditions);
    $cards = Database::query(
        "SELECT card_no, status, order_id, sold_at FROM " . Database::prefix() . "goods_virtual_card {$whereSql} ORDER BY id ASC",
        $params
    );

    $filename = 'cards_goods' . $goodsId . '_' . date('Ymd_His') . '.txt';
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $statusLabels = [1 => '未售', 0 => '已售', 2 => '标记售出'];
    $exportTime = date('Y-m-d H:i:s');
    $output = "卡密列表（商品ID:{$goodsId}，导出时间:{$exportTime}）\n";
    $output .= str_repeat('-', 60) . "\n";

    foreach ($cards as $card) {
        $label = $statusLabels[$card['status']] ?? '未知';
        $output .= $card['card_no'];
        if ($card['status'] != 1) {
            $output .= ' [' . $label . ']';
        }
        $output .= "\n";
    }

    echo $output;
    exit;
}

// ================================================================
// 保存卡密（编辑单条卡密）
// ================================================================
if ($action === 'card_save') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!Csrf::validate($csrf)) {
        Response::error('请求已失效，请刷新页面后重试');
    }
    $id = (int)($_POST['id'] ?? 0);
    $goodsId = (int)($_POST['goods_id'] ?? 0);
    if (!$id || !$goodsId) {
        Response::error('参数不完整');
    }

    $cardNo = trim($_POST['card_no'] ?? '');
    if ($cardNo === '') {
        Response::error('卡号不能为空');
    }

    $cardPwd = trim($_POST['card_pwd'] ?? '');
    $specId  = (int)($_POST['spec_id'] ?? 0);
    $remark  = trim($_POST['remark'] ?? '');

    // 仅允许编辑未售卡密
    $card = Database::fetchOne(
        "SELECT id, status FROM " . Database::prefix() . "goods_virtual_card WHERE id = ? AND goods_id = ?",
        [$id, $goodsId]
    );
    if (!$card) {
        Response::error('卡密不存在');
    }
    if ((int)$card['status'] !== 1) {
        Response::error('仅未售卡密可编辑');
    }

    Database::update('goods_virtual_card', [
        'card_no'  => $cardNo,
        'card_pwd' => $cardPwd !== '' ? $cardPwd : null,
        'spec_id'  => $specId > 0 ? $specId : null,
        'remark'   => $remark !== '' ? $remark : null,
    ], $id);

    // 规格变更可能影响库存统计
    virtualCardSyncCardStock($goodsId);

    Response::success('保存成功', ['csrf_token' => Csrf::token()]);
}

// ================================================================
// 优先销售（切换销售优先级）
// ================================================================
if ($action === 'card_priority') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!Csrf::validate($csrf)) {
        Response::error('请求已失效，请刷新页面后重试');
    }
    $id = (int)($_POST['id'] ?? 0);
    $goodsId = (int)($_POST['goods_id'] ?? 0);
    if (!$id || !$goodsId) {
        Response::error('参数不完整');
    }

    $card = Database::fetchOne(
        "SELECT id, sell_priority FROM " . Database::prefix() . "goods_virtual_card WHERE id = ? AND goods_id = ? AND status = 1",
        [$id, $goodsId]
    );
    if (!$card) {
        Response::error('卡密不存在或不可操作');
    }

    // 切换优先状态：已设优先则取消，未设则设置为当前时间戳
    $newPriority = ((int)($card['sell_priority'] ?? 0) > 0) ? 0 : time();
    Database::update('goods_virtual_card', ['sell_priority' => $newPriority], $id);

    $msg = $newPriority > 0 ? '已设为优先销售' : '已取消优先销售';
    Response::success($msg, ['csrf_token' => Csrf::token()]);
}

// ================================================================
// 标记售出（手动将卡密标记为已售出）
// ================================================================
if ($action === 'card_mark_sold') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!Csrf::validate($csrf)) {
        Response::error('请求已失效，请刷新页面后重试');
    }
    $id = (int)($_POST['id'] ?? 0);
    $goodsId = (int)($_POST['goods_id'] ?? 0);
    if (!$id || !$goodsId) {
        Response::error('参数不完整');
    }

    $card = Database::fetchOne(
        "SELECT id, status FROM " . Database::prefix() . "goods_virtual_card WHERE id = ? AND goods_id = ?",
        [$id, $goodsId]
    );
    if (!$card) {
        Response::error('卡密不存在');
    }
    if ((int)$card['status'] !== 1) {
        Response::error('仅未售卡密可标记为售出');
    }

    Database::update('goods_virtual_card', [
        'status'  => 2,
        'sold_at' => date('Y-m-d H:i:s'),
    ], $id);

    // 标记售出后同步规格库存
    virtualCardSyncCardStock($goodsId);

    Response::success('已标记为售出', ['csrf_token' => Csrf::token()]);
}

// ================================================================
// 卡密管理弹窗页面（独立弹窗方式访问时使用）
// ================================================================
if ($action === 'card_manager') {
    $goodsId = (int)($_GET['goods_id'] ?? 0);
    if (!$goodsId) {
        exit('商品ID不能为空');
    }

    $goods = GoodsModel::getById($goodsId);
    if (!$goods) {
        exit('商品不存在');
    }

    $specs = GoodsModel::getSpecsByGoodsId($goodsId);
    $csrfToken = Csrf::token();
    $pageTitle = '卡密管理';

    // 构建 spec_id => name 映射
    $specMap = [];
    foreach ($specs as $s) {
        $specMap[(int)$s['id']] = $s['name'];
    }

    // 统计信息
    $totalCards = (int)(Database::fetchOne(
        "SELECT COUNT(*) as cnt FROM " . Database::prefix() . "goods_virtual_card WHERE goods_id = ?",
        [$goodsId]
    )['cnt'] ?? 0);
    $availableCards = (int)(Database::fetchOne(
        "SELECT COUNT(*) as cnt FROM " . Database::prefix() . "goods_virtual_card WHERE goods_id = ? AND status = 1",
        [$goodsId]
    )['cnt'] ?? 0);
    $soldCards = $totalCards - $availableCards;

    // 每个规格的可用卡密数（供库存概览卡片展示）
    $specStockMap = [];
    $specCardRows = Database::query(
        "SELECT spec_id, COUNT(*) as cnt FROM " . Database::prefix() . "goods_virtual_card WHERE goods_id = ? AND status = 1 AND spec_id IS NOT NULL GROUP BY spec_id",
        [$goodsId]
    );
    foreach ($specCardRows as $r) {
        $specStockMap[(int)$r['spec_id']] = (int)$r['cnt'];
    }

    include EM_ROOT . '/admin/view/popup/header.php';
    include __DIR__ . '/inventory.php';
    include EM_ROOT . '/admin/view/popup/footer.php';
    exit;
}

// ================================================================
// 按订单 ID 导出该订单里所有 virtual_card 商品的发货内容为 txt
// URL：/admin/index.php?_action=order_export_cards&order_id=X
// 订单详情 popup 的"导出全部 (TXT)"按钮调用
// ================================================================
if ($action === 'order_export_cards') {
    $orderId = (int) ($_GET['order_id'] ?? 0);
    if ($orderId <= 0) {
        http_response_code(400);
        exit('订单ID不能为空');
    }

    // 订单号用来做文件名；不存在直接 404
    $order = Database::fetchOne(
        "SELECT order_no FROM " . Database::prefix() . "order WHERE id = ?",
        [$orderId]
    );
    if (!$order) {
        http_response_code(404);
        exit('订单不存在');
    }

    // 只取该订单里 virtual_card 类型的商品；goods_type 是 order_goods 的快照字段，不用 JOIN goods
    $rows = Database::query(
        "SELECT goods_title, spec_name, delivery_content
         FROM " . Database::prefix() . "order_goods
         WHERE order_id = ? AND goods_type = 'virtual_card'
         ORDER BY id",
        [$orderId]
    );

    // 组装 txt 内容：每个商品前加标题注释行，商品之间空行分隔
    $chunks = [];
    foreach ($rows as $row) {
        $content = trim((string) ($row['delivery_content'] ?? ''));
        if ($content === '') continue;
        $header = '# ' . (string) $row['goods_title'];
        if (!empty($row['spec_name'])) {
            $header .= ' - ' . $row['spec_name'];
        }
        $chunks[] = $header . "\n" . $content;
    }
    $body = implode("\n\n", $chunks);

    $filename = 'order-' . $order['order_no'] . '-cards.txt';

    // 清空可能的 output buffer，避免前面 PHP 误输出污染文件
    while (ob_get_level() > 0) { ob_end_clean(); }

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($body));
    header('Cache-Control: no-store');
    echo $body;
    exit;
}
