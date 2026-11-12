<?php

declare(strict_types=1);

namespace App\User\Controller;

/**
 * 用户中心页面（/user/rebate）。
 */
final class RebateController extends UserBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        if ($this->merchantContext->currentId() > 0) {
            header('Location: /user/home');
            exit;
        }

        $siteName = $this->config->get('sitename', 'EMSHOP');
        $csrfToken = $this->csrf->token();
        $displayMoney = '0.00';
        if (!empty($frontUser['money'])) {
            $displayMoney = bcdiv((string) $frontUser['money'], '1000000', 2);
        }
        $userId = (int) $frontUser['id'];

        // 懒执行：把到期 frozen 转为 available
        (new \App\Model\CommissionLogModel())->promoteMatured($userId);

        // 最新用户数据
        $row = $this->db->find('user', $userId);
        $frozen = (int) ($row['commission_frozen'] ?? 0);
        $available = (int) ($row['commission_available'] ?? 0);
        $inviteCode = trim((string) ($row['invite_code'] ?? ''));
        if ($inviteCode === '') {
            $inviteCode = \InviteToken::generateUniqueCode();
            $this->db->update('user', ['invite_code' => $inviteCode], $userId);
        }

        // 统计
        $prefix = $this->db->prefix();
        $directCount = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM {$prefix}user WHERE inviter_l1 = ?", [$userId]
        );
        $teamCount = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM {$prefix}user WHERE inviter_l1 = ? OR inviter_l2 = ?",
            [$userId, $userId]
        );
        $totalEarned = $this->db->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM {$prefix}commission_log
             WHERE user_id = ? AND status IN ('frozen', 'available', 'withdrawn')",
            [$userId]
        );

        // 推广链接
        $buildInviteLink = function (string $code): string {
            if ($code === '') return '';
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            return $scheme . '://' . $host . '/?' . \InviteToken::QUERY_PARAM . '=' . rawurlencode($code);
        };
        $inviteLink = $buildInviteLink($inviteCode);
        // 佣金按访客当前展示币种渲染（带符号完整字符串）；数据库里都是主货币 BIGINT ×1000000
        $commissionFrozenDisplay    = $this->currency->displayAmount((int) $frozen);
        $commissionAvailableDisplay = $this->currency->displayAmount((int) $available);
        $totalEarnedDisplay         = $this->currency->displayAmount((int) ($totalEarned['total'] ?? 0));
        $rebateEnabled              = app(\App\Service\RebateService::class)->isEnabled();
        $freezeDays                 = app(\App\Service\RebateService::class)->freezeDays();
        $directCountNum             = (int) ($directCount['cnt'] ?? 0);
        $teamCountNum               = (int) ($teamCount['cnt'] ?? 0);

        if ($this->request->isPjax()) {
            echo '<div id="userContent" class="uc-content">';
            $this->render('rebate', get_defined_vars());
            echo '</div>';
        } else {
            \App\User\UserShell::render(dirname(__DIR__) . '/View/rebate.php', $frontUser, get_defined_vars());
        }
    }
}
