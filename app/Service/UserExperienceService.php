<?php

declare(strict_types=1);

namespace App\Service;





/**
 * 用户经验值与累计消费。
 *
 * - 注册：按 user_credit_initial 赠送初始经验
 * - 订单完成：累计 pay_amount 到 total_consumption，按 user_exp_per_yuan 赠送经验，并按 unlock_exp 自动升级等级
 */
final class UserExperienceService extends BaseService
{
    private const MONEY_SCALE = 1000000;

    private function settleTable(): string
    {
        return $this->db->prefix() . 'user_experience_order';
    }

    /**
     * 注册成功后赠送初始经验（配置 user_credit_initial）。
     */
    public function applyRegisterBonus(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $initial = max(0, (int) $this->config->get('user_credit_initial', '0'));
        if ($initial <= 0) {
            return;
        }

        $this->addExperience($userId, $initial, 0);
    }

    /**
     * 订单完成：累计消费 + 赠送经验 + 尝试按经验自动解锁等级（幂等）。
     */
    public function onOrderCompleted(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $orderTable = $this->db->prefix() . 'order';
        $settleTable = $this->settleTable();
        $order = $this->db->fetchOne(
            'SELECT `id`, `user_id`, `pay_amount`, `status`
               FROM `' . $orderTable . '`
              WHERE `id` = ? LIMIT 1',
            [$orderId]
        );
        if ($order === null) {
            return;
        }
        if (($order['status'] ?? '') !== 'completed') {
            return;
        }

        $userId = (int) ($order['user_id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $exists = $this->db->fetchOne(
            'SELECT `order_id` FROM `' . $settleTable . '` WHERE `order_id` = ? LIMIT 1',
            [$orderId]
        );
        if ($exists !== null) {
            return;
        }

        $payAmount = max(0, (int) ($order['pay_amount'] ?? 0));
        $expPerYuan = max(0, (int) $this->config->get('user_exp_per_yuan', '0'));
        $yuan = intdiv($payAmount, self::MONEY_SCALE);
        $expGain = $yuan * $expPerYuan;

        if ($payAmount <= 0 && $expGain <= 0) {
            return;
        }

        $this->db->begin();
        try {
            $userTable = $this->db->prefix() . 'user';
            $userRow = $this->db->fetchOne(
                // 不按 role 过滤：管理账号在前台也能下单，经验/消费额同样要结算。
                // 过滤掉会出现「订单完成了但没给经验」——而且是 rollBack + return，
                // 不抛异常也不写日志，完全无痕。这里的 FOR UPDATE 也依赖它真能锁到行。
                'SELECT `id` FROM `' . $userTable . '` WHERE `id` = ? LIMIT 1 FOR UPDATE',
                [$userId]
            );
            if ($userRow === null) {
                $this->db->rollBack();
                return;
            }

            $this->db->execute(
                'INSERT INTO `' . $settleTable . '` (`order_id`, `user_id`, `pay_amount`, `exp_gained`) VALUES (?, ?, ?, ?)',
                [$orderId, $userId, $payAmount, $expGain]
            );

            if ($payAmount > 0) {
                $this->db->execute(
                    'UPDATE `' . $userTable . '`
                        SET `total_consumption` = `total_consumption` + ?
                      WHERE `id` = ? LIMIT 1',
                    [$payAmount, $userId]
                );
            }

            if ($expGain > 0) {
                $this->db->execute(
                    'UPDATE `' . $userTable . '`
                        SET `experience` = `experience` + ?
                      WHERE `id` = ? LIMIT 1',
                    [$expGain, $userId]
                );
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            // 并发重复结算：主键冲突视为已处理
            if ($this->isDuplicateKey($e)) {
                return;
            }
            throw $e;
        }

        $this->tryAutoUpgradeLevel($userId);
    }

    /**
     * 增加经验并尝试自动升级（供注册等场景）。
     */
    private function addExperience(int $userId, int $delta, int $consumptionDelta): void
    {
        if ($delta <= 0 && $consumptionDelta <= 0) {
            return;
        }

        $userTable = $this->db->prefix() . 'user';
        $this->db->begin();
        try {
            $userRow = $this->db->fetchOne(
                // 不按 role 过滤：管理账号在前台也能下单，经验/消费额同样要结算。
                // 过滤掉会出现「订单完成了但没给经验」——而且是 rollBack + return，
                // 不抛异常也不写日志，完全无痕。这里的 FOR UPDATE 也依赖它真能锁到行。
                'SELECT `id` FROM `' . $userTable . '` WHERE `id` = ? LIMIT 1 FOR UPDATE',
                [$userId]
            );
            if ($userRow === null) {
                $this->db->rollBack();
                return;
            }

            if ($consumptionDelta > 0) {
                $this->db->execute(
                    'UPDATE `' . $userTable . '`
                        SET `total_consumption` = `total_consumption` + ?
                      WHERE `id` = ? LIMIT 1',
                    [$consumptionDelta, $userId]
                );
            }
            if ($delta > 0) {
                $this->db->execute(
                    'UPDATE `' . $userTable . '`
                        SET `experience` = `experience` + ?
                      WHERE `id` = ? LIMIT 1',
                    [$delta, $userId]
                );
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        if ($delta > 0) {
            $this->tryAutoUpgradeLevel($userId);
        }
    }

    /**
     * 按经验值自动解锁更高用户等级（仅升级、不降级）。
     */
    public function tryAutoUpgradeLevel(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $userTable = $this->db->prefix() . 'user';
        $levelTable = $this->db->prefix() . 'user_levels';

        $user = $this->db->fetchOne(
            'SELECT `level_id`, `experience` FROM `' . $userTable . '` WHERE `id` = ? LIMIT 1',
            [$userId]
        );
        if ($user === null) {
            return;
        }

        $experience = (int) ($user['experience'] ?? 0);
        $currentLevelNum = 0;
        $levelId = (int) ($user['level_id'] ?? 0);
        if ($levelId > 0) {
            $cur = $this->db->fetchOne(
                'SELECT `level` FROM `' . $levelTable . '` WHERE `id` = ? AND `enabled` = \'y\' LIMIT 1',
                [$levelId]
            );
            if ($cur !== null) {
                $currentLevelNum = (int) ($cur['level'] ?? 0);
            }
        }

        $best = $this->db->fetchOne(
            'SELECT `id`, `level`
               FROM `' . $levelTable . '`
              WHERE `enabled` = \'y\'
                AND `unlock_exp` > 0
                AND `unlock_exp` <= ?
                AND `level` > ?
              ORDER BY `level` DESC
              LIMIT 1',
            [$experience, $currentLevelNum]
        );
        if ($best === null) {
            return;
        }

        $newLevelId = (int) ($best['id'] ?? 0);
        if ($newLevelId <= 0 || $newLevelId === $levelId) {
            return;
        }

        $this->db->execute(
            'UPDATE `' . $userTable . '` SET `level_id` = ? WHERE `id` = ? LIMIT 1',
            [$newLevelId, $userId]
        );
    }

    private function isDuplicateKey(\Throwable $e): bool
    {
        $msg = $e->getMessage();
        if (strpos($msg, '1062') !== false || strpos($msg, 'Duplicate entry') !== false) {
            return true;
        }
        $prev = $e->getPrevious();
        return $prev instanceof \Throwable && $this->isDuplicateKey($prev);
    }
}
