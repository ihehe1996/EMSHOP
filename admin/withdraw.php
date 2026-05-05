<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * 后台 - 钱包提现审核。
 *
 * POST _action:
 *   list     分页查询
 *   approve  pending → approved（审核通过，等待打款）
 *   reject   pending/approved → rejected（驳回，退回余额）
 *   paid     approved → paid（标记已打款）
 */
adminRequireLogin();
$user = $adminUser;
$siteName = Config::get('sitename', 'EMSHOP');

if (Request::isPost()) {
    try {
        $action = (string) Input::post('_action', '');
        $model = new UserWithdrawModel();

        if ($action === 'list') {
            $page    = max(1, (int) Input::post('page', 1));
            $perPage = max(1, min(100, (int) Input::post('limit', 20)));
            $filter  = [
                'status'  => (string) Input::post('status', ''),
                'keyword' => trim((string) Input::post('keyword', '')),
            ];
            $result = $model->paginate($filter, $page, $perPage);
            foreach ($result['list'] as &$r) {
                // 金额千分位展示（大额提现更易读）
                $r['amount_display'] = number_format((float) bcdiv((string) $r['amount'], '1000000', 2), 2, '.', ',');
            }
            unset($r);
            Response::success('', [
                'data'       => $result['list'],
                'total'      => $result['total'],
                'csrf_token' => Csrf::token(),
            ]);
        }

        if (!Csrf::validate((string) Input::post('csrf_token', ''))) {
            Response::error('令牌失效，请刷新后重试');
        }
        $id     = (int) Input::post('id', 0);
        $remark = trim((string) Input::post('remark', ''));
        $aid    = (int) ($adminUser['id'] ?? 0);

        if ($action === 'approve') {
            if (!$model->approve($id, $aid, $remark)) Response::error('操作失败（状态可能已变）');
            Response::success('已审核通过');
        }
        if ($action === 'reject') {
            $model->reject($id, $aid, $remark);
            Response::success('已驳回，金额已退回用户');
        }
        if ($action === 'paid') {
            if (!$model->markPaid($id, $aid, $remark)) Response::error('操作失败（状态可能已变）');
            Response::success('已标记打款');
        }

        Response::error('未知操作');
    } catch (RuntimeException $e) {
        Response::error($e->getMessage());
    } catch (Throwable $e) {
        Response::error('系统繁忙：' . $e->getMessage());
    }
}

$csrfToken = Csrf::token();

if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/withdraw.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/withdraw.php';
    require __DIR__ . '/index.php';
}
