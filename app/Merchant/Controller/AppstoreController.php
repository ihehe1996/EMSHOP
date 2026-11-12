<?php

declare(strict_types=1);

namespace App\Merchant\Controller;

/**
 * 商户后台 - appstore（/user/merchant/appstore）。
 */
final class AppstoreController extends MerchantBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        $currentMerchant = $this->merchant();
        $merchantLevel = $this->level();
        $csrfToken   = $this->csrf->token();
        $merchantId  = (int) ($currentMerchant['id'] ?? 0);
        $merchantUid = (int) ($currentMerchant['user_id'] ?? 0);

        if ((string) $this->input->get('_action', '') === 'list') {
            try {
                $prefix = $this->db->prefix();
                $page = max(1, (int) $this->input->get('page', 1));
                $pageSize = max(1, min(100, (int) $this->input->get('limit', 12)));
                $offset = ($page - 1) * $pageSize;

                $type = (string) $this->input->get('type', '');
                $keyword = trim((string) $this->input->get('keyword', ''));
                $listMode = (string) $this->input->get('list_mode', '');
                if (!in_array($listMode, ['', 'purchased'], true)) $listMode = '';

                $merchantCategories = \PluginModel::MERCHANT_PLUGIN_CATEGORIES;
                $categoryId = (int) $this->input->get('category_id', 0);
                $categoryName = $categoryId > 0 ? (string) ($merchantCategories[$categoryId] ?? '') : '';

                $params = [$merchantId];
                if ($listMode === 'purchased') {
                    $sqlBase = " FROM `{$prefix}app_purchase` p
                                 LEFT JOIN `{$prefix}app_market` m
                                   ON m.app_code = p.app_code AND m.type = p.type
                                WHERE p.merchant_id = ?";
                    if (in_array($type, ['plugin', 'template'], true)) {
                        $sqlBase .= ' AND p.type = ?';
                        $params[] = $type;
                    }
                    if ($categoryName !== '') {
                        $sqlBase .= ' AND m.category = ?';
                        $params[] = $categoryName;
                    }
                    if ($keyword !== '') {
                        $sqlBase .= ' AND (p.app_code LIKE ? OR m.title LIKE ? OR m.description LIKE ?)';
                        $kw = '%' . $keyword . '%';
                        $params[] = $kw;
                        $params[] = $kw;
                        $params[] = $kw;
                    }

                    $countRow = $this->db->fetchOne('SELECT COUNT(*) AS c ' . $sqlBase, $params);
                    $total = (int) ($countRow['c'] ?? 0);

                    $rows = $this->db->query(
                        'SELECT p.id AS purchase_id, p.merchant_id, p.user_id, p.app_code, p.type,
                                p.market_id, p.paid_amount, p.purchased_at,
                                m.id, m.title, m.version, m.category, m.cover, m.description,
                                m.retail_price, m.total_quota, m.consumed_quota, m.is_listed
                         ' . $sqlBase . '
                         ORDER BY p.id DESC
                         LIMIT ' . $pageSize . ' OFFSET ' . $offset,
                        $params
                    );
                    foreach ($rows as &$row) {
                        $row['name_en'] = (string) ($row['app_code'] ?? '');
                        $row['name_cn'] = (string) (($row['title'] ?? '') !== '' ? $row['title'] : $row['app_code']);
                        $row['content'] = (string) ($row['description'] ?? '');
                        $row['my_price'] = (int) ($row['paid_amount'] ?? 0);
                        $row['is_purchased'] = 1;
                        $row['is_installed'] = 1;
                        $row['remaining'] = max(0, (int) ($row['total_quota'] ?? 0) - (int) ($row['consumed_quota'] ?? 0));
                    }
                    unset($row);
                } else {
                    $sqlBase = " FROM `{$prefix}app_market` m
                                 LEFT JOIN `{$prefix}app_purchase` p
                                   ON p.merchant_id = ? AND p.app_code = m.app_code AND p.type = m.type
                                WHERE m.is_listed = 1";
                    // 商户端应用商店不展示主站统管插件（支付/商品类型/共享店铺）
                    $sysCats = array_values(\PluginModel::SYSTEM_PLUGINS);
                    if ($sysCats !== []) {
                        $sqlBase .= ' AND NOT (m.type = \'plugin\' AND m.category IN (' . implode(',', array_fill(0, count($sysCats), '?')) . '))';
                        foreach ($sysCats as $c) $params[] = $c;
                    }
                    if (in_array($type, ['plugin', 'template'], true)) {
                        $sqlBase .= ' AND m.type = ?';
                        $params[] = $type;
                    }
                    if ($categoryName !== '') {
                        $sqlBase .= ' AND m.category = ?';
                        $params[] = $categoryName;
                    }
                    if ($keyword !== '') {
                        $sqlBase .= ' AND (m.title LIKE ? OR m.app_code LIKE ? OR m.description LIKE ?)';
                        $kw = '%' . $keyword . '%';
                        $params[] = $kw;
                        $params[] = $kw;
                        $params[] = $kw;
                    }

                    $countRow = $this->db->fetchOne('SELECT COUNT(*) AS c ' . $sqlBase, $params);
                    $total = (int) ($countRow['c'] ?? 0);

                    $rows = $this->db->query(
                        'SELECT m.*, p.id AS purchase_id, p.purchased_at, p.paid_amount,
                                IF(p.id IS NULL, 0, 1) AS is_purchased
                         ' . $sqlBase . '
                         ORDER BY m.updated_at DESC
                         LIMIT ' . $pageSize . ' OFFSET ' . $offset,
                        $params
                    );
                    foreach ($rows as &$row) {
                        $row['name_en'] = (string) ($row['app_code'] ?? '');
                        $row['name_cn'] = (string) (($row['title'] ?? '') !== '' ? $row['title'] : $row['app_code']);
                        $row['content'] = (string) ($row['description'] ?? '');
                        $row['my_price'] = (int) ($row['retail_price'] ?? 0);
                        $row['is_installed'] = (int) ($row['is_purchased'] ?? 0);
                        $row['remaining'] = max(0, (int) ($row['total_quota'] ?? 0) - (int) ($row['consumed_quota'] ?? 0));
                    }
                    unset($row);
                }

                $this->response->success('', [
                    'list'    => $rows,
                    'count'   => $total,
                    'page'    => $page,
                    'pageNum' => $pageSize,
                ]);
            } catch (\Throwable $e) {
                $this->response->error($e->getMessage());
            }
        }

        if ($this->request->isPost() && (string) $this->input->post('_action', '') === 'buy') {
            try {
                if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
                    $this->response->error('请求已失效，请刷新页面后重试');
                }

                $marketId = (int) $this->input->post('market_id', 0);
                if ($marketId <= 0) $this->response->error('参数错误');

                $marketModel = new \AppMarketModel();
                $purchaseModel = new \AppPurchaseModel();

                $this->db->begin();
                try {
                    $market = $marketModel->lockById($marketId);
                    if ($market === null) {
                        throw new \RuntimeException('应用不存在或已下架');
                    }
                    if ((int) ($market['is_listed'] ?? 0) !== 1) {
                        throw new \RuntimeException('该应用已下架');
                    }

                    $type = (string) ($market['type'] ?? '');
                    $appCode = (string) ($market['app_code'] ?? '');
                    $title = (string) (($market['title'] ?? '') !== '' ? $market['title'] : $appCode);
                    if ($appCode === '' || !in_array($type, ['plugin', 'template'], true)) {
                        throw new \RuntimeException('应用数据异常');
                    }
                    $remaining = max(0, (int) ($market['total_quota'] ?? 0) - (int) ($market['consumed_quota'] ?? 0));
                    if ($remaining <= 0) {
                        throw new \RuntimeException('库存不足，请联系主站补货');
                    }

                    // 主站统管插件由主站继承，不允许在商户端购买
                    if ($type === 'plugin' && in_array((string) ($market['category'] ?? ''), array_values(\PluginModel::SYSTEM_PLUGINS), true)) {
                        throw new \RuntimeException('该应用由主站统一管理，无需购买');
                    }

                    if ($purchaseModel->isPurchased($merchantId, $appCode, $type)) {
                        throw new \RuntimeException('你已购买该应用');
                    }

                    // 文件存在校验：主站采购上架后物理文件应该已准备好
                    if ($type === 'plugin') {
                        if (!(new \PluginModel())->existsOnDisk($appCode)) {
                            throw new \RuntimeException('主站文件未就绪，请联系主站管理员检查插件文件');
                        }
                    } else {
                        if (!(new \TemplateModel())->existsOnDisk($appCode)) {
                            throw new \RuntimeException('主站文件未就绪，请联系主站管理员检查模板文件');
                        }
                    }

                    $price = max(0, (int) ($market['retail_price'] ?? 0));
                    $before = 0;
                    $after = 0;
                    $balanceLogId = 0;

                    // 锁站长余额并扣款
                    $userTable = $this->db->prefix() . 'user';
                    $userRow = $this->db->fetchOne(
                        'SELECT `id`, `money` FROM `' . $userTable . '` WHERE `id` = ? FOR UPDATE',
                        [$merchantUid]
                    );
                    if ($userRow === null) {
                        throw new \RuntimeException('账户异常，请重新登录后重试');
                    }
                    $before = (int) ($userRow['money'] ?? 0);
                    $after = $before - $price;
                    if ($after < 0) {
                        throw new \RuntimeException('余额不足，请先充值');
                    }

                    if ($price > 0) {
                        $this->db->execute(
                            'UPDATE `' . $userTable . '` SET `money` = ? WHERE `id` = ?',
                            [$after, $merchantUid]
                        );

                        $this->db->insert('user_balance_log', [
                            'user_id'        => $merchantUid,
                            'type'           => 'decrease',
                            'amount'         => $price,
                            'before_balance' => $before,
                            'after_balance'  => $after,
                            'remark'         => '购买应用：' . $title . '（' . $appCode . '）',
                            'operator_id'    => $merchantUid,
                            'operator_name'  => (string) ($frontUser['nickname'] ?: $frontUser['username']),
                            'ip'             => $_SERVER['REMOTE_ADDR'] ?? '',
                        ]);
                        $balanceLogId = (int) ($this->db->fetchOne('SELECT LAST_INSERT_ID() AS id', [])['id'] ?? 0);
                    } else {
                        $after = $before;
                    }

                    // 扣配额
                    $marketModel->incrementConsumed($marketId);

                    // 写已购记录
                    $purchaseModel->create([
                        'merchant_id'    => $merchantId,
                        'user_id'        => $merchantUid,
                        'app_code'       => $appCode,
                        'type'           => $type,
                        'market_id'      => $marketId,
                        'paid_amount'    => $price,
                        'balance_log_id' => $balanceLogId,
                    ]);

                    // 写应用订单
                    $appOrderNo = 'AO' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                    (new \AppOrderModel())->create([
                        'order_no'       => $appOrderNo,
                        'merchant_id'    => $merchantId,
                        'user_id'        => $merchantUid,
                        'market_id'      => $marketId,
                        'app_code'       => $appCode,
                        'type'           => $type,
                        'app_title'      => $title,
                        'amount'         => $price,
                        'pay_method'     => 'balance',
                        'status'         => 'paid',
                        'balance_log_id' => $balanceLogId,
                        'before_balance' => $before,
                        'after_balance'  => $after,
                        'note'           => '商户端应用商店购买',
                    ]);

                    $this->db->commit();

                    // 同步会话余额
                    $frontUser['money'] = $after;
                    $_SESSION['em_front_user'] = $frontUser;

                    $this->response->success('购买成功', [
                        'order_no'      => $appOrderNo,
                        'paid_amount'   => $price,
                        'after_balance' => $after,
                        'after_balance_text' => $this->currency->displayAmount($after),
                        'csrf_token'    => $this->csrf->refresh(),
                    ]);
                } catch (\Throwable $e) {
                    $this->db->rollBack();
                    throw $e;
                }
            } catch (RuntimeException $e) {
                $this->response->error($e->getMessage());
            } catch (\Throwable $e) {
                $this->response->error($e->getMessage() ?: '购买失败，请稍后再试');
            }
        }

        \App\Merchant\MerchantShell::render(dirname(__DIR__) . '/View/appstore.php', $frontUser, $currentMerchant, $merchantLevel, [
            'csrfToken' => $csrfToken,
            'moneyRaw'  => (int) ($frontUser['money'] ?? 0),
            'moneyText' => $this->currency->displayAmount((int) ($frontUser['money'] ?? 0)),
        ]);
    }
}
