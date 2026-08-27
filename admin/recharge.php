<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * 后台 - 钱包充值订单。
 *
 * POST _action = list / summary / cancel / delete / batch_delete
 */
adminRequireLogin();
$user = $adminUser;
$siteName = Config::get('sitename', 'EMSHOP');

if (Request::isPost()) {
    try {
        $action = (string) Input::post('_action', '');
        $model = new UserRechargeModel();
        $rechargeTable = Database::prefix() . 'user_recharge';

        // 写操作校验 CSRF（list / summary 只读，免校验）
        if (!in_array($action, ['list', 'summary'], true)) {
            if (!Csrf::validate((string) Input::post('csrf_token', ''))) {
                Response::error('令牌失效，请刷新后重试');
            }
        }

        if ($action === 'list') {
            $page    = max(1, (int) Input::post('page', 1));
            $perPage = max(1, min(100, (int) Input::post('limit', 20)));
            $filter  = [
                'status'  => (string) Input::post('status', ''),
                'keyword' => trim((string) Input::post('keyword', '')),
            ];
            $result = $model->paginate($filter, $page, $perPage);

            // payment_code → {name, image} 映射（em_user_recharge 没存 payment_name/image，
            // 走 PaymentService 拿当前可用支付方式元数据去解析）
            $paymentMap = [];
            try {
                foreach (PaymentService::getMethods() as $m) {
                    $code = (string) ($m['code'] ?? '');
                    if ($code !== '') {
                        $paymentMap[$code] = [
                            'name'  => (string) ($m['name'] ?? $code),
                            'image' => (string) ($m['image'] ?? ''),
                        ];
                    }
                }
            } catch (Throwable $e) {
                // 拿不到就 fallback 到 code 自身，不影响列表展示
            }

            foreach ($result['list'] as &$r) {
                $r['amount_display'] = bcdiv((string) $r['amount'], '1000000', 2);
                $code = (string) ($r['payment_code'] ?? '');
                $r['payment_name']  = $paymentMap[$code]['name']  ?? $code;
                $r['payment_image'] = $paymentMap[$code]['image'] ?? '';
            }
            unset($r);

            // 顺手把状态分桶计数也算了，省一次往返（前端 tabs 上显示徽章）
            $countRows = Database::query(
                "SELECT status, COUNT(*) AS cnt FROM `{$rechargeTable}` GROUP BY status"
            );
            $statusCounts = ['all' => 0, 'pending' => 0, 'paid' => 0, 'cancelled' => 0];
            foreach ($countRows as $row) {
                $s = (string) $row['status'];
                $c = (int) $row['cnt'];
                $statusCounts['all'] += $c;
                if (isset($statusCounts[$s])) $statusCounts[$s] = $c;
            }

            Response::success('', [
                'data'          => $result['list'],
                'total'         => $result['total'],
                'status_counts' => $statusCounts,
                'csrf_token'    => Csrf::token(),
            ]);
        }

        // 顶部数据卡：今日笔数 / 今日金额 / 本月金额 / 累计已充值（仅统计 paid）
        if ($action === 'summary') {
            $today = date('Y-m-d');
            $monthStart = date('Y-m-01 00:00:00');

            $todayRow = Database::fetchOne(
                "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS amt
                   FROM `{$rechargeTable}`
                  WHERE status = ? AND DATE(paid_at) = ?",
                [UserRechargeModel::STATUS_PAID, $today]
            );
            $monthRow = Database::fetchOne(
                "SELECT COALESCE(SUM(amount), 0) AS amt
                   FROM `{$rechargeTable}`
                  WHERE status = ? AND paid_at >= ?",
                [UserRechargeModel::STATUS_PAID, $monthStart]
            );
            $totalRow = Database::fetchOne(
                "SELECT COALESCE(SUM(amount), 0) AS amt
                   FROM `{$rechargeTable}`
                  WHERE status = ?",
                [UserRechargeModel::STATUS_PAID]
            );

            $fmt = static fn(int $bigint): string => bcdiv((string) $bigint, '1000000', 2);

            Response::success('', ['data' => [
                'today_count'   => (int) ($todayRow['cnt'] ?? 0),
                'today_amount'  => $fmt((int) ($todayRow['amt'] ?? 0)),
                'month_amount'  => $fmt((int) ($monthRow['amt'] ?? 0)),
                'total_amount'  => $fmt((int) ($totalRow['amt'] ?? 0)),
            ]]);
        }

        if ($action === 'cancel') {
            // 管理员手动取消 pending 单（只改状态，不退款——pending 单本就没扣过钱）
            $id = (int) Input::post('id', 0);
            $row = $model->findById($id);
            if (!$row) Response::error('记录不存在');
            if ($row['status'] !== UserRechargeModel::STATUS_PENDING) {
                Response::error('仅待支付的充值单可取消');
            }
            Database::execute(
                'UPDATE ' . Database::prefix() . 'user_recharge SET status = ? WHERE id = ? AND status = ?',
                [UserRechargeModel::STATUS_CANCELLED, $id, UserRechargeModel::STATUS_PENDING]
            );
            Response::success('已取消', ['csrf_token' => Csrf::refresh()]);
        }

        // 删除单条：仅删充值记录，已到账余额不回滚
        if ($action === 'delete') {
            $id = (int) Input::post('id', 0);
            if ($id <= 0) Response::error('无效的记录');
            if ($model->findById($id) === null) Response::error('记录不存在');
            if (!$model->delete($id)) Response::error('删除失败');
            Response::success('删除成功', ['csrf_token' => Csrf::refresh()]);
        }

        // 批量删除
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
            if ($ids === []) Response::error('请选择要删除的充值单');

            $deleted = $model->deleteBatch($ids);
            Response::success('已删除 ' . $deleted . ' 条', [
                'deleted'    => $deleted,
                'csrf_token' => Csrf::refresh(),
            ]);
        }

        Response::error('未知操作');
    } catch (Throwable $e) {
        Response::error('系统繁忙：' . $e->getMessage());
    }
}

$csrfToken = Csrf::token();

if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/recharge.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/recharge.php';
    require __DIR__ . '/index.php';
}
