<?php
defined('EM_ROOT') || exit('access denied!');

/**
 * 虚拟卡密插件 · 前台展示入口
 *
 * 访问路径：/?plugin=virtual_card[&action=...]
 *   - action=export_order_cards  按订单 ID 导出已发卡密为 txt
 *   - 不带 action                展示占位说明（本插件不对外暴露主页面）
 *
 * 权限检查（与 user/order_detail.php、user/order_poll.php 一致）：
 *   - 登录用户：订单 user_id 匹配 em_front_user.id
 *   - 游客：订单 guest_token 匹配 GuestToken::get()
 *   - 查单页：POST 查单成功后写入 $_SESSION['em_find_order_visible'][order_no]，命中则放行
 *   满足任一即放行；都不满足返回 403
 */

require_once __DIR__ . '/../../../user/global_public.php';
require_once __DIR__ . '/inc_export_filename.php';

$action = (string) Input::get('action', '');

// ================================================================
// 导出订单卡密 txt
// ================================================================
if ($action === 'export_order_cards') {
    $orderId = (int) Input::get('order_id', 0);
    if ($orderId <= 0) {
        http_response_code(400);
        exit('订单ID不能为空');
    }

    $prefix = Database::prefix();
    $order = Database::fetchOne(
        "SELECT id, order_no, user_id, guest_token, created_at FROM {$prefix}order WHERE id = ?",
        [$orderId]
    );
    if (!$order) {
        http_response_code(404);
        exit('订单不存在');
    }

    // 权限校验：本人 / 同浏览器游客单 / 查单 session 白名单（见 user/find_order.php）
    $isOwner = false;
    if (!empty($frontUser) && (int) $order['user_id'] === (int) $frontUser['id']) {
        $isOwner = true;
    } elseif (empty($frontUser) && !empty($order['guest_token']) && $order['guest_token'] === GuestToken::get()) {
        $isOwner = true;
    } else {
        $orderNo = (string) ($order['order_no'] ?? '');
        if ($orderNo !== '') {
            $visible = $_SESSION['em_find_order_visible'] ?? [];
            if (isset($visible[$orderNo])) {
                $isOwner = true;
            }
        }
    }
    if (!$isOwner) {
        http_response_code(403);
        exit('无权访问该订单');
    }

    // 取出该订单所有 virtual_card 行（goods_type 是 order_goods 的快照字段）
    $rows = Database::query(
        "SELECT id, goods_title, delivery_content
         FROM {$prefix}order_goods
         WHERE order_id = ? AND goods_type = 'virtual_card'
         ORDER BY id",
        [$orderId]
    );

    // 仅输出卡密行（不含商品名 / 规格注释）
    $lines = [];
    $titleSet = [];
    foreach ($rows as $row) {
        $content = trim(OrderModel::getDeliveryContent((int) ($row['id'] ?? 0), (string) ($row['delivery_content'] ?? '')));
        if ($content === '') {
            continue;
        }
        $gt = trim((string) ($row['goods_title'] ?? ''));
        if ($gt !== '') {
            $titleSet[$gt] = true;
        }
        foreach (preg_split("/\r\n|\r|\n/", $content) as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }
    }
    $body = implode("\n", $lines);
    $count = count($lines);
    if (count($titleSet) === 1) {
        $nameBase = array_key_first($titleSet);
    } else {
        $nameBase = '订单' . (string) $order['order_no'];
    }
    $placeYmd = virtual_card_datetime_to_ymd((string) ($order['created_at'] ?? ''));
    $filename = virtual_card_build_export_filename($nameBase, $count, $placeYmd);

    // 清空可能的 output buffer 防止污染
    while (ob_get_level() > 0) { ob_end_clean(); }

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: ' . virtual_card_export_disposition($filename));
    header('Content-Length: ' . strlen($body));
    header('Cache-Control: no-store');
    echo $body;
    exit;
}

// 默认：插件不对外暴露主页面，给一个最简提示
http_response_code(404);
echo '此插件无独立展示页';
