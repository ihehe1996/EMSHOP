<?php
defined('EM_ROOT') || exit('access denied!');

/**
 * 虚拟卡密插件 · 前台展示入口
 *
 * 访问路径：/?plugin=virtual_card[&action=...]
 *   - action=export_order_cards  按订单 ID 导出已发卡密为 txt
 *   - 不带 action                展示占位说明（本插件不对外暴露主页面）
 *
 * 权限检查：
 *   - 登录用户：订单 user_id 必须匹配 session 里的 em_front_user.id
 *   - 游客：订单 guest_token 必须匹配浏览器的 GuestToken::get()
 *   满足任一即放行；都不满足返回 403
 */

require_once __DIR__ . '/../../../user/global_public.php';

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
        "SELECT id, order_no, user_id, guest_token FROM {$prefix}order WHERE id = ?",
        [$orderId]
    );
    if (!$order) {
        http_response_code(404);
        exit('订单不存在');
    }

    // 权限校验：和 user/order_detail.php 同款两种路径
    $isOwner = false;
    if (!empty($frontUser) && (int) $order['user_id'] === (int) $frontUser['id']) {
        $isOwner = true;
    } elseif (empty($frontUser) && !empty($order['guest_token']) && $order['guest_token'] === GuestToken::get()) {
        $isOwner = true;
    }
    if (!$isOwner) {
        http_response_code(403);
        exit('无权访问该订单');
    }

    // 取出该订单所有 virtual_card 行（goods_type 是 order_goods 的快照字段）
    $rows = Database::query(
        "SELECT goods_title, spec_name, delivery_content
         FROM {$prefix}order_goods
         WHERE order_id = ? AND goods_type = 'virtual_card'
         ORDER BY id",
        [$orderId]
    );

    // 组装 txt：每商品前 # 注释行 + 内容，商品之间空行
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

    $filename = 'order-' . (string) $order['order_no'] . '-cards.txt';

    // 清空可能的 output buffer 防止污染
    while (ob_get_level() > 0) { ob_end_clean(); }

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($body));
    header('Cache-Control: no-store');
    echo $body;
    exit;
}

// 默认：插件不对外暴露主页面，给一个最简提示
http_response_code(404);
echo '此插件无独立展示页';
