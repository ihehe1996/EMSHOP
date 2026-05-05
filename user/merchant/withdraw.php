<?php

declare(strict_types=1);

require_once __DIR__ . '/global.php';

/**
 * 商户后台 - 提现申请 + 记录
 *
 * v1 流程（方案 §6.11）：
 *   1. 校验毛额 A 在 (0, 店铺余额]
 *   2. 计算手续费 F = A × withdraw_fee_rate / 10000
 *   3. 实到 N = A − F
 *   4. 走事务：
 *       em_user.shop_balance -= A
 *       em_user.money        += N
 *       em_merchant_balance_log(type=withdraw,     amount=A)
 *       em_merchant_balance_log(type=withdraw_fee, amount=F)  （F > 0 时）
 *       em_user_balance_log(type=increase, amount=N)
 *       em_merchant_withdraw(amount=A, fee_amount=F, net_amount=N, status=done)
 *
 * v1 简化：直通到账，不需审核。v3 引入 pending + 审核流程。
 */
merchantRequireLogin();

$merchantId = (int) $currentMerchant['id'];
$userId = (int) $currentMerchant['user_id'];
$feeRate = (int) ($merchantLevel['withdraw_fee_rate'] ?? 0); // 万分位

if (Request::isPost()) {
    try {
        if (!Csrf::validate((string) Input::post('csrf_token', ''))) {
            Response::error('请求已失效，请刷新页面后重试');
        }
        $action = (string) Input::post('_action', '');

        switch ($action) {
            case 'apply': {
                $amountYuan = (float) Input::post('amount', 0);
                if ($amountYuan <= 0) {
                    Response::error('请填写有效的提现金额');
                }
                $amount = (int) round($amountYuan * 1000000);
                if ($amount <= 0) {
                    Response::error('提现金额过小');
                }

                $fee = (int) floor($amount * $feeRate / 10000);
                $net = $amount - $fee;
                if ($net <= 0) {
                    Response::error('扣除手续费后实到账金额过小，请提升提现金额');
                }

                Database::begin();
                try {
                    $userTable = Database::prefix() . 'user';
                    $row = Database::fetchOne(
                        'SELECT `money`, `shop_balance` FROM `' . $userTable . '` WHERE `id` = ? FOR UPDATE',
                        [$userId]
                    );
                    if ($row === null) {
                        throw new RuntimeException('用户不存在');
                    }
                    $shopBefore = (int) $row['shop_balance'];
                    $moneyBefore = (int) $row['money'];

                    if ($shopBefore < $amount) {
                        throw new RuntimeException('店铺余额不足');
                    }

                    $shopAfter = $shopBefore - $amount;
                    $moneyAfter = $moneyBefore + $net;

                    Database::execute(
                        'UPDATE `' . $userTable . '` SET `shop_balance` = ?, `money` = ? WHERE `id` = ?',
                        [$shopAfter, $moneyAfter, $userId]
                    );

                    // 先登提现记录，拿到 withdraw_id
                    $now = date('Y-m-d H:i:s');
                    $withdrawId = Database::insert('merchant_withdraw', [
                        'merchant_id' => $merchantId,
                        'user_id' => $userId,
                        'amount' => $amount,
                        'fee_amount' => $fee,
                        'net_amount' => $net,
                        'before_balance' => $shopBefore,
                        'after_balance' => $shopAfter,
                        'target' => 'money',
                        'status' => 'done',
                        'audited_at' => $now,
                    ]);

                    // 店铺余额日志：提现本金
                    Database::insert('merchant_balance_log', [
                        'merchant_id' => $merchantId,
                        'user_id' => $userId,
                        'type' => 'withdraw',
                        'amount' => $amount,
                        'before_balance' => $shopBefore,
                        'after_balance' => $shopAfter,
                        'withdraw_id' => $withdrawId,
                        'remark' => '提现到消费余额',
                        'operator_id' => $userId,
                    ]);
                    // 手续费也走一条（若有）
                    if ($fee > 0) {
                        Database::insert('merchant_balance_log', [
                            'merchant_id' => $merchantId,
                            'user_id' => $userId,
                            'type' => 'withdraw_fee',
                            'amount' => $fee,
                            'before_balance' => $shopAfter,   // 手续费是"账面扣除"类，起点=本金扣后
                            'after_balance' => $shopAfter,    // 实际未再动 shop_balance
                            'withdraw_id' => $withdrawId,
                            'remark' => '提现手续费（' . rtrim(rtrim(number_format($feeRate / 100, 2, '.', ''), '0'), '.') . '%）',
                            'operator_id' => $userId,
                        ]);
                    }

                    // 消费余额日志
                    Database::insert('user_balance_log', [
                        'user_id' => $userId,
                        'type' => 'increase',
                        'amount' => $net,
                        'before_balance' => $moneyBefore,
                        'after_balance' => $moneyAfter,
                        'remark' => '店铺余额提现入账（扣除手续费）',
                        'operator_id' => $userId,
                        'operator_name' => $frontUser['nickname'] ?: $frontUser['username'],
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                    ]);

                    Database::commit();

                    $frontUser['money'] = $moneyAfter;
                    $frontUser['shop_balance'] = $shopAfter;
                    $_SESSION['em_front_user'] = $frontUser;

                    Response::success('提现成功', [
                        // 返回带访客币种符号的完整字符串，前端直接输出到提示条
                        'amount' => Currency::displayAmount($amount),
                        'fee' => Currency::displayAmount($fee),
                        'net' => Currency::displayAmount($net),
                        'csrf_token' => Csrf::refresh(),
                    ]);
                } catch (Throwable $e) {
                    Database::rollBack();
                    throw $e;
                }
                break;
            }

            case 'list': {
                $page = max(1, (int) Input::post('page', 1));
                $pageSize = max(1, min(100, (int) Input::post('limit', 20)));
                $offset = ($page - 1) * $pageSize;

                $t = Database::prefix() . 'merchant_withdraw';
                $count = Database::fetchOne(
                    'SELECT COUNT(*) AS c FROM `' . $t . '` WHERE `merchant_id` = ?',
                    [$merchantId]
                );
                $total = (int) ($count['c'] ?? 0);

                $rows = Database::query(
                    'SELECT * FROM `' . $t . '` WHERE `merchant_id` = ?
                      ORDER BY `created_at` DESC, `id` DESC
                      LIMIT ' . $pageSize . ' OFFSET ' . $offset,
                    [$merchantId]
                );
                foreach ($rows as &$r) {
                    // 按访客当前展示币种输出（金额列全部带符号）
                    $r['amount_view'] = Currency::displayAmount((int) $r['amount']);
                    $r['fee_view'] = Currency::displayAmount((int) $r['fee_amount']);
                    $r['net_view'] = Currency::displayAmount((int) $r['net_amount']);
                }
                unset($r);

                Response::success('', [
                    'data' => $rows,
                    'total' => $total,
                    'csrf_token' => Csrf::token(),
                ]);
                break;
            }

            default:
                Response::error('未知操作');
        }
    } catch (RuntimeException $e) {
        Response::error($e->getMessage());
    } catch (Throwable $e) {
        Response::error($e->getMessage() ?: '系统繁忙，请稍后再试');
    }
}

merchantRenderPage(__DIR__ . '/view/withdraw.php', [
    'feeRate' => $feeRate,
]);
