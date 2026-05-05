<?php

declare(strict_types=1);

require_once __DIR__ . '/global.php';

/**
 * 商户后台 - 店铺余额流水
 *
 * 仅读 em_merchant_balance_log，按当前商户过滤。
 * type 支持 increase / decrease / refund / withdraw / withdraw_fee / sub_rebate / adjust
 */
merchantRequireLogin();

$merchantId = (int) $currentMerchant['id'];

if (Request::isPost()) {
    try {
        $action = (string) Input::post('_action', '');
        if ($action !== 'list' && $action !== 'summary') {
            if (!Csrf::validate((string) Input::post('csrf_token', ''))) {
                Response::error('请求已失效，请刷新页面后重试');
            }
        }

        $logTable = Database::prefix() . 'merchant_balance_log';

        switch ($action) {
            case 'list': {
                $type = trim((string) Input::post('type', ''));
                $month = trim((string) Input::post('month', ''));  // 'YYYY-MM'
                $page = max(1, (int) Input::post('page', 1));
                $pageSize = max(1, min(100, (int) Input::post('limit', 20)));
                $offset = ($page - 1) * $pageSize;

                $conds = ['merchant_id = ?'];
                $params = [$merchantId];
                if ($type !== '') {
                    $conds[] = 'type = ?';
                    $params[] = $type;
                }
                if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
                    $start = $month . '-01 00:00:00';
                    $next = date('Y-m-01 00:00:00', strtotime($month . '-01 +1 month'));
                    $conds[] = 'created_at >= ? AND created_at < ?';
                    $params[] = $start;
                    $params[] = $next;
                }
                $whereSql = 'WHERE ' . implode(' AND ', $conds);

                $count = Database::fetchOne(
                    'SELECT COUNT(*) AS c FROM `' . $logTable . '` ' . $whereSql,
                    $params
                );
                $total = (int) ($count['c'] ?? 0);

                $sql = 'SELECT * FROM `' . $logTable . '` ' . $whereSql . '
                         ORDER BY `created_at` DESC, `id` DESC
                         LIMIT ' . $pageSize . ' OFFSET ' . $offset;
                $rows = Database::query($sql, $params);

                foreach ($rows as &$r) {
                    // 店铺余额类按访客当前币种展示（店铺资产语义 —— 和余额卡片、提现页同口径）
                    $r['amount_view'] = Currency::displayAmount((int) $r['amount']);
                    $r['before_view'] = Currency::displayAmount((int) $r['before_balance']);
                    $r['after_view']  = Currency::displayAmount((int) $r['after_balance']);
                    // 正负号：increase/sub_rebate 为加；decrease/refund/withdraw/withdraw_fee 为减
                    $r['direction'] = in_array($r['type'], ['increase', 'sub_rebate', 'adjust'], true) && (int) $r['after_balance'] >= (int) $r['before_balance']
                        ? '+' : '-';
                    if ($r['type'] === 'adjust') {
                        $r['direction'] = ((int) $r['after_balance']) >= ((int) $r['before_balance']) ? '+' : '-';
                    }
                }
                unset($r);

                Response::success('', [
                    'data' => $rows,
                    'total' => $total,
                    'csrf_token' => Csrf::token(),
                ]);
                break;
            }

            case 'summary': {
                // 概览：当月进账 / 当月退款 / 当月提现 / 当月子商返佣
                $monthStart = date('Y-m-01 00:00:00');
                $sums = [];
                $types = ['increase', 'refund', 'withdraw', 'withdraw_fee', 'sub_rebate'];
                foreach ($types as $t) {
                    $row = Database::fetchOne(
                        'SELECT COALESCE(SUM(`amount`), 0) AS s FROM `' . $logTable . '`
                          WHERE `merchant_id` = ? AND `type` = ? AND `created_at` >= ?',
                        [$merchantId, $t, $monthStart]
                    );
                    $sums[$t] = Currency::displayAmount((int) ($row['s'] ?? 0));
                }
                Response::success('', ['data' => $sums]);
                break;
            }

            default:
                Response::error('未知操作');
        }
    } catch (Throwable $e) {
        Response::error($e->getMessage() ?: '系统繁忙，请稍后再试');
    }
}

merchantRenderPage(__DIR__ . '/view/finance.php');
