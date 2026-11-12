<?php

declare(strict_types=1);

namespace App\User\Controller;

/**
 * 用户中心公开页（/user/order_poll）。
 */
final class OrderPollController extends UserPublicBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        /**
         * 订单状态轮询（供前端检测异步发货是否完成）。
         *
         * POST：
         *   - csrf_token（必填）
         *   - action 为空或省略：order_no + 单条订单状态（需有查看权限）
         *   - action=pending_snapshot：仅登录用户，返回当前账号下「待异步发货」订单快照 hash
         *   - action=find_pending_snapshot：查单结果列表用，POST order_nos（JSON 数组或逗号分隔），
         *     对当前客户端有权查看的订单重算 paid/delivering 快照 hash（最多 50 个单号）
         */

        if (!$this->request->isPost()) {
            $this->response->error('仅支持 POST');
        }

        if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
            $this->response->error('验证失败，请刷新页面后重试');
        }

        $action = trim((string) $this->input->post('action', ''));
        $prefix = $this->db->prefix();

        /**
         * 是否与单条订单轮询接口相同的「可查看」判定（登录本人 / 同 guest_token / 查单白名单）。
         *
         * @param array<string, mixed> $order
         */
        $orderPollAllowed = static function (array $order): bool {
            global $frontUser;
            if (!empty($frontUser['id']) && (int) ($order['user_id'] ?? 0) === (int) $frontUser['id']) {
                return true;
            }
            if (empty($frontUser) && (string) ($order['guest_token'] ?? '') !== ''
                && (string) $order['guest_token'] === \GuestToken::get()) {
                return true;
            }
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $no = (string) ($order['order_no'] ?? '');
            $visible = $_SESSION['em_find_order_visible'] ?? [];
            return $no !== '' && isset($visible[$no]);
        };

        if ($action === 'find_pending_snapshot') {
            $raw = $this->input->post('order_nos', '');
            $list = [];
            if (is_array($raw)) {
                $list = $raw;
            } else {
                $decoded = json_decode((string) $raw, true);
                if (is_array($decoded)) {
                    $list = $decoded;
                } elseif ((string) $raw !== '') {
                    $list = array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
                }
            }
            $list = array_values(array_unique(array_map('strval', $list)));
            if (count($list) > 50) {
                $list = array_slice($list, 0, 50);
            }
            $pairs = [];
            foreach ($list as $orderNo) {
                if ($orderNo === '') {
                    continue;
                }
                $order = app(\App\Model\OrderModel::class)->getByOrderNo($orderNo);
                if (!$order || !$orderPollAllowed($order)) {
                    continue;
                }
                $st = (string) ($order['status'] ?? '');
                if (in_array($st, ['paid', 'delivering'], true)) {
                    $pairs[] = $orderNo . '|' . $st;
                }
            }
            sort($pairs);
            $hash = $pairs === [] ? 'empty' : md5(implode("\n", $pairs));
            $this->response->success('', ['hash' => $hash, 'pending_count' => count($pairs)]);
        }

        if ($action === 'pending_snapshot') {
            if (empty($frontUser['id'])) {
                $this->response->error('请先登录');
            }
            $uid = (int) $frontUser['id'];
            $rows = $this->db->query(
                "SELECT `order_no`, `status` FROM `{$prefix}order`
                 WHERE `user_id` = ? AND `status` IN ('paid','delivering')
                 ORDER BY `id` ASC",
                [$uid]
            );
            $pairs = [];
            foreach ($rows as $r) {
                $pairs[] = (string) ($r['order_no'] ?? '') . '|' . (string) ($r['status'] ?? '');
            }
            $hash = $pairs === [] ? 'empty' : md5(implode("\n", $pairs));
            $this->response->success('', ['hash' => $hash, 'pending_count' => count($pairs)]);
        }

        $orderNo = trim((string) $this->input->post('order_no', ''));
        if ($orderNo === '') {
            $this->response->error('缺少订单号');
        }

        $order = app(\App\Model\OrderModel::class)->getByOrderNo($orderNo);
        if (!$order) {
            $this->response->error('订单不存在');
        }

        if (!$orderPollAllowed($order)) {
            $this->response->error('无权查看该订单');
        }

        $this->response->success('', [
            'status'              => (string) ($order['status'] ?? ''),
            'status_name'         => app(\App\Model\OrderModel::class)->statusName((string) ($order['status'] ?? '')),
            'awaiting_delivery'   => in_array((string) ($order['status'] ?? ''), ['paid', 'delivering'], true),
        ]);
    }
}
