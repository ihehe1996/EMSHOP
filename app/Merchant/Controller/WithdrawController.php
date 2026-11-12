<?php

declare(strict_types=1);

namespace App\Merchant\Controller;

/**
 * 商户后台页面（/user/merchant/withdraw）。
 */
final class WithdrawController extends MerchantBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        $currentMerchant = $this->merchant();
        $merchantLevel = $this->level();
        $merchantId = (int) $currentMerchant['id'];
        $userId = (int) $currentMerchant['user_id'];
        $feeRate = (int) ($merchantLevel['withdraw_fee_rate'] ?? 0); // 万分位

        if ($this->request->isPost()) {
            try {
                if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
                    $this->response->error('请求已失效，请刷新页面后重试');
                }
                $action = (string) $this->input->post('_action', '');

                switch ($action) {
                    case 'apply': {
                        // \Money::parse 做严格的十进制白名单校验后换算成 micro 整数。
                        // 原先的 (float) + round 走浮点，虽然不会抛错，但金额入参不该经过
                        // 浮点运算；且它与前台提现/充值的口径统一后更易维护。
                        try {
                            $amount = \Money::parse($this->input->post('amount', ''));
                        } catch (InvalidArgumentException $e) {
                            $this->response->error('请填写有效的提现金额');
                        }

                        $fee = (int) floor($amount * $feeRate / 10000);
                        $net = $amount - $fee;
                        if ($net <= 0) {
                            $this->response->error('扣除手续费后实到账金额过小，请提升提现金额');
                        }

                        $this->db->begin();
                        try {
                            $userTable = $this->db->prefix() . 'user';
                            $row = $this->db->fetchOne(
                                'SELECT `money`, `shop_balance` FROM `' . $userTable . '` WHERE `id` = ? FOR UPDATE',
                                [$userId]
                            );
                            if ($row === null) {
                                throw new \RuntimeException('用户不存在');
                            }
                            $shopBefore = (int) $row['shop_balance'];
                            $moneyBefore = (int) $row['money'];

                            if ($shopBefore < $amount) {
                                throw new \RuntimeException('店铺余额不足');
                            }

                            $shopAfter = $shopBefore - $amount;
                            $moneyAfter = $moneyBefore + $net;

                            $this->db->execute(
                                'UPDATE `' . $userTable . '` SET `shop_balance` = ?, `money` = ? WHERE `id` = ?',
                                [$shopAfter, $moneyAfter, $userId]
                            );

                            // 先登提现记录，拿到 withdraw_id
                            $now = date('Y-m-d H:i:s');
                            $withdrawId = $this->db->insert('merchant_withdraw', [
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
                            $this->db->insert('merchant_balance_log', [
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
                                $this->db->insert('merchant_balance_log', [
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
                            $this->db->insert('user_balance_log', [
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

                            $this->db->commit();

                            $frontUser['money'] = $moneyAfter;
                            $frontUser['shop_balance'] = $shopAfter;
                            $_SESSION['em_front_user'] = $frontUser;

                            $this->response->success('提现成功', [
                                // 返回带访客币种符号的完整字符串，前端直接输出到提示条
                                'amount' => $this->currency->displayAmount($amount),
                                'fee' => $this->currency->displayAmount($fee),
                                'net' => $this->currency->displayAmount($net),
                                'csrf_token' => $this->csrf->refresh(),
                            ]);
                        } catch (\Throwable $e) {
                            $this->db->rollBack();
                            throw $e;
                        }
                        break;
                    }

                    case 'list': {
                        $page = max(1, (int) $this->input->post('page', 1));
                        $pageSize = max(1, min(100, (int) $this->input->post('limit', 20)));
                        $offset = ($page - 1) * $pageSize;

                        $t = $this->db->prefix() . 'merchant_withdraw';
                        $count = $this->db->fetchOne(
                            'SELECT COUNT(*) AS c FROM `' . $t . '` WHERE `merchant_id` = ?',
                            [$merchantId]
                        );
                        $total = (int) ($count['c'] ?? 0);

                        $rows = $this->db->query(
                            'SELECT * FROM `' . $t . '` WHERE `merchant_id` = ?
                              ORDER BY `created_at` DESC, `id` DESC
                              LIMIT ' . $pageSize . ' OFFSET ' . $offset,
                            [$merchantId]
                        );
                        foreach ($rows as &$r) {
                            // 按访客当前展示币种输出（金额列全部带符号）
                            $r['amount_view'] = $this->currency->displayAmount((int) $r['amount']);
                            $r['fee_view'] = $this->currency->displayAmount((int) $r['fee_amount']);
                            $r['net_view'] = $this->currency->displayAmount((int) $r['net_amount']);
                        }
                        unset($r);

                        $this->response->success('', [
                            'data' => $rows,
                            'total' => $total,
                            'csrf_token' => $this->csrf->token(),
                        ]);
                        break;
                    }

                    default:
                        $this->response->error('未知操作');
                }
            } catch (RuntimeException $e) {
                $this->response->error($e->getMessage());
            } catch (\Throwable $e) {
                $this->response->error($e->getMessage() ?: '系统繁忙，请稍后再试');
            }
        }

        \App\Merchant\MerchantShell::render(dirname(__DIR__) . '/View/withdraw.php', $frontUser, $currentMerchant, $merchantLevel, [
            'feeRate' => $feeRate,
        ]);
    }
}
