<?php

declare(strict_types=1);

/**
 * 前台"我的推广"控制器。
 *
 * 路由：
 *   _index()   推广主页（数据面板 / 推广链接 / 佣金明细 / 提现入口）
 *   withdraw() AJAX 提现佣金到余额
 *   logs()     AJAX 分页查询佣金流水
 */
class RebateController extends BaseController
{
    private function requireLogin(): array
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $user = $_SESSION['em_front_user'] ?? null;
        if (empty($user['id'])) {
            if (Request::isPost()) Response::error('请先登录');
            // GET 请求未登录 → 跳登录页
            Response::redirect('/?c=login');
        }
        return $user;
    }

    public function _index(): void
    {
        // 主页面移到个人中心内（user/rebate.php），这里直接重定向
        Response::redirect('/user/rebate.php');
    }

    /**
     * AJAX：提现佣金到余额。
     */
    public function withdraw(): void
    {
        if (!Request::isPost()) Response::error('无效请求');

        $user = $this->requireLogin();
        $userId = (int) $user['id'];

        $amount = trim((string) Input::post('amount', ''));
        if ($amount === '' || !is_numeric($amount) || (float) $amount <= 0) {
            Response::error('请输入正确的提现金额');
        }
        $amountRaw = (int) bcmul($amount, '1000000', 0);

        try {
            $withdrawId = RebateService::withdraw($userId, $amountRaw);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage());
        }

        Response::success('提现成功', ['withdraw_id' => $withdrawId]);
    }

    /**
     * AJAX：分页拉取佣金流水（前台我的推广页面下方列表）。
     */
    public function logs(): void
    {
        $user = $this->requireLogin();
        $userId = (int) $user['id'];

        $page = max(1, (int) Input::get('page', 1));
        $perPage = max(1, min(50, (int) Input::get('limit', 20)));
        $status = (string) Input::get('status', '');

        $filter = [];
        if ($status !== '') $filter['status'] = $status;

        $result = (new CommissionLogModel())->paginateByUser($userId, $filter, $page, $perPage);
        Response::success('', [
            'data'  => $result['list'],
            'total' => $result['total'],
        ]);
    }

    /**
     * AJAX：分页拉取团队成员列表。
     * level 过滤：'' 全部，'1' 仅直推，'2' 仅二级。
     */
    public function team(): void
    {
        $user = $this->requireLogin();
        $userId = (int) $user['id'];

        $page = max(1, (int) Input::get('page', 1));
        $perPage = max(1, min(50, (int) Input::get('limit', 20)));
        $level = (string) Input::get('level', '');
        $offset = ($page - 1) * $perPage;

        $prefix = Database::prefix();

        // 用 CASE 把关系标成 L1/L2，一条 SQL 搞定；level 过滤时把 WHERE 收窄
        if ($level === '1') {
            $where = 'inviter_l1 = ?';
            $params = [$userId];
            $levelExpr = "1";
        } elseif ($level === '2') {
            $where = 'inviter_l2 = ? AND (inviter_l1 IS NULL OR inviter_l1 <> ?)';
            $params = [$userId, $userId];
            $levelExpr = "2";
        } else {
            $where = '(inviter_l1 = ? OR inviter_l2 = ?)';
            $params = [$userId, $userId];
            $levelExpr = "CASE WHEN inviter_l1 = {$userId} THEN 1 ELSE 2 END";
        }

        $countRow = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM {$prefix}user WHERE {$where}",
            $params
        );
        $total = (int) ($countRow['cnt'] ?? 0);

        $rows = Database::query(
            "SELECT id, username, nickname, avatar, created_at, {$levelExpr} AS team_level
             FROM {$prefix}user
             WHERE {$where}
             ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        // 脱敏：用户名保留首尾各 1 位，中间用 * 替代
        foreach ($rows as &$r) {
            $name = (string) ($r['nickname'] ?: $r['username']);
            $r['display_name'] = self::maskName($name);
            $r['team_level']   = (int) $r['team_level'];
            unset($r['username']); // 不把原始用户名回给前端
        }
        unset($r);

        Response::success('', ['data' => $rows, 'total' => $total]);
    }

    /**
     * 用户名脱敏：保留首尾各 1 位，中间固定 ***。
     */
    private static function maskName(string $name): string
    {
        $len = mb_strlen($name, 'UTF-8');
        if ($len <= 1) return $name;
        if ($len === 2) return mb_substr($name, 0, 1, 'UTF-8') . '*';
        return mb_substr($name, 0, 1, 'UTF-8') . '***' . mb_substr($name, $len - 1, 1, 'UTF-8');
    }

}
