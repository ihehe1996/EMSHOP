<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * 后台 - 佣金流水 & 提现记录（只读）。
 *
 * 路由：
 *   ?tab=log      (默认) 佣金流水
 *   ?tab=withdraw        提现记录
 * POST _action = list 返回表格数据
 */
adminRequireLogin();
$user = $adminUser;
$siteName = Config::get('sitename', 'EMSHOP');

$tab = (string) Input::get('tab', 'log');
if (!in_array($tab, ['log', 'withdraw'], true)) $tab = 'log';

if (Request::isPost()) {
    try {
        $action = (string) Input::post('_action', '');
        if ($action === 'list') {
            $type = (string) Input::post('tab', 'log');
            $page = max(1, (int) Input::post('page', 1));
            $perPage = max(1, min(100, (int) Input::post('limit', 20)));

            if ($type === 'withdraw') {
                $filter = [];
                $uid = (int) Input::post('user_id', 0);
                if ($uid > 0) $filter['user_id'] = $uid;

                $result = (new CommissionWithdrawModel())->paginate($filter, $page, $perPage);
            } else {
                // 佣金流水：按 user_id / status / level 过滤
                $status = (string) Input::post('status', '');
                $level  = (int) Input::post('level', 0);
                $uid    = (int) Input::post('user_id', 0);
                $where = []; $params = [];
                if ($uid > 0)    { $where[] = 'user_id = ?'; $params[] = $uid; }
                if ($status)     { $where[] = 'status = ?'; $params[] = $status; }
                if ($level > 0)  { $where[] = 'level = ?'; $params[] = $level; }
                $whereSql = $where ? implode(' AND ', $where) : '1=1';

                $prefix = Database::prefix();
                $countRow = Database::fetchOne("SELECT COUNT(*) AS cnt FROM {$prefix}commission_log WHERE {$whereSql}", $params);
                $total = (int) ($countRow['cnt'] ?? 0);
                $totalPages = max(1, (int) ceil($total / $perPage));
                if ($page > $totalPages) $page = $totalPages;
                $offset = ($page - 1) * $perPage;

                $rows = Database::query(
                    "SELECT * FROM {$prefix}commission_log WHERE {$whereSql} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}",
                    $params
                );
                foreach ($rows as &$r) {
                    $r['amount_display'] = bcdiv((string) $r['amount'], '1000000', 2);
                    $r['basis_amount_display'] = bcdiv((string) ($r['basis_amount'] ?? 0), '1000000', 2);
                }
                unset($r);
                $result = ['list' => $rows, 'total' => $total];
            }

            Response::success('', [
                'data' => $result['list'],
                'total' => $result['total'],
                'csrf_token' => Csrf::token(),
            ]);
        }
        Response::error('未知操作');
    } catch (Throwable $e) {
        Response::error('系统繁忙');
    }
}

$csrfToken = Csrf::token();

if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/commission.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/commission.php';
    require __DIR__ . '/index.php';
}
