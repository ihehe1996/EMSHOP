<?php

declare(strict_types=1);

/**
 * 用户经验值与累计消费。
 *
 * - 注册：按 user_credit_initial 赠送初始经验
 * - 订单完成：累计 pay_amount 到 total_consumption，按 user_exp_per_yuan 赠送经验，并按 unlock_exp 自动升级等级
 */
final class UserExperienceService
{
    private const MONEY_SCALE = 1000000;

    private static function settleTable(): string
    {
        return Database::prefix() . 'user_experience_order';
    }

    /**
     * 注册成功后赠送初始经验（配置 user_credit_initial）。
     */
    public static function applyRegisterBonus(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $initial = max(0, (int) Config::get('user_credit_initial', '0'));
        if ($initial <= 0) {
            return;
        }

        self::addExperience($userId, $initial, 0);
    }

    /**
     * 订单完成：累计消费 + 赠送经验 + 尝试按经验自动解锁等级（幂等）。
     */
    public static function onOrderCompleted(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $orderTable = Database::prefix() . 'order';
        $settleTable = self::settleTable();
        $order = Database::fetchOne(
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

        $exists = Database::fetchOne(
            'SELECT `order_id` FROM `' . $settleTable . '` WHERE `order_id` = ? LIMIT 1',
            [$orderId]
        );
        if ($exists !== null) {
            return;
        }

        $payAmount = max(0, (int) ($order['pay_amount'] ?? 0));
        $expPerYuan = max(0, (int) Config::get('user_exp_per_yuan', '0'));
        $yuan = intdiv($payAmount, self::MONEY_SCALE);
        $expGain = $yuan * $expPerYuan;

        if ($payAmount <= 0 && $expGain <= 0) {
            return;
        }

        Database::begin();
        try {
            $userTable = Database::prefix() . 'user';
            $userRow = Database::fetchOne(
                'SELECT `id` FROM `' . $userTable . '` WHERE `id` = ? AND `role` = \'user\' LIMIT 1 FOR UPDATE',
                [$userId]
            );
            if ($userRow === null) {
                Database::rollBack();
                return;
            }

            Database::execute(
                'INSERT INTO `' . $settleTable . '` (`order_id`, `user_id`, `pay_amount`, `exp_gained`) VALUES (?, ?, ?, ?)',
                [$orderId, $userId, $payAmount, $expGain]
            );

            if ($payAmount > 0) {
                Database::execute(
                    'UPDATE `' . $userTable . '`
                        SET `total_consumption` = `total_consumption` + ?
                      WHERE `id` = ? LIMIT 1',
                    [$payAmount, $userId]
                );
            }

            if ($expGain > 0) {
                Database::execute(
                    'UPDATE `' . $userTable . '`
                        SET `experience` = `experience` + ?
                      WHERE `id` = ? LIMIT 1',
                    [$expGain, $userId]
                );
            }

            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            // 并发重复结算：主键冲突视为已处理
            if (self::isDuplicateKey($e)) {
                return;
            }
            throw $e;
        }

        self::tryAutoUpgradeLevel($userId);
    }

    /**
     * 增加经验并尝试自动升级（供注册等场景）。
     */
    private static function addExperience(int $userId, int $delta, int $consumptionDelta): void
    {
        if ($delta <= 0 && $consumptionDelta <= 0) {
            return;
        }

        $userTable = Database::prefix() . 'user';
        Database::begin();
        try {
            $userRow = Database::fetchOne(
                'SELECT `id` FROM `' . $userTable . '` WHERE `id` = ? AND `role` = \'user\' LIMIT 1 FOR UPDATE',
                [$userId]
            );
            if ($userRow === null) {
                Database::rollBack();
                return;
            }

            if ($consumptionDelta > 0) {
                Database::execute(
                    'UPDATE `' . $userTable . '`
                        SET `total_consumption` = `total_consumption` + ?
                      WHERE `id` = ? LIMIT 1',
                    [$consumptionDelta, $userId]
                );
            }
            if ($delta > 0) {
                Database::execute(
                    'UPDATE `' . $userTable . '`
                        SET `experience` = `experience` + ?
                      WHERE `id` = ? LIMIT 1',
                    [$delta, $userId]
                );
            }

            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        if ($delta > 0) {
            self::tryAutoUpgradeLevel($userId);
        }
    }

    /**
     * 按经验值自动解锁更高用户等级（仅升级、不降级）。
     */
    public static function tryAutoUpgradeLevel(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $userTable = Database::prefix() . 'user';
        $levelTable = Database::prefix() . 'user_levels';

        $user = Database::fetchOne(
            'SELECT `level_id`, `experience` FROM `' . $userTable . '` WHERE `id` = ? AND `role` = \'user\' LIMIT 1',
            [$userId]
        );
        if ($user === null) {
            return;
        }

        $experience = (int) ($user['experience'] ?? 0);
        $currentLevelNum = 0;
        $levelId = (int) ($user['level_id'] ?? 0);
        if ($levelId > 0) {
            $cur = Database::fetchOne(
                'SELECT `level` FROM `' . $levelTable . '` WHERE `id` = ? AND `enabled` = \'y\' LIMIT 1',
                [$levelId]
            );
            if ($cur !== null) {
                $currentLevelNum = (int) ($cur['level'] ?? 0);
            }
        }

        $best = Database::fetchOne(
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

        Database::execute(
            'UPDATE `' . $userTable . '` SET `level_id` = ? WHERE `id` = ? AND `role` = \'user\' LIMIT 1',
            [$newLevelId, $userId]
        );
    }

    private static function isDuplicateKey(Throwable $e): bool
    {
        $msg = $e->getMessage();
        if (str_contains($msg, '1062') || str_contains($msg, 'Duplicate entry')) {
            return true;
        }
        $prev = $e->getPrevious();
        return $prev instanceof Throwable && self::isDuplicateKey($prev);
    }
}
