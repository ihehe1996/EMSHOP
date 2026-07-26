<?php

declare(strict_types=1);

/**
 * 订单模型。
 *
 * 负责订单创建、状态流转、查询等核心操作。
 * 状态变更统一通过 changeStatus() 方法执行，确保合法性校验和钩子触发。
 */
class OrderModel
{
    private static string $orderTable = '';
    private static string $orderGoodsTable = '';
    private static string $orderGoodsDeliveryItemTable = '';
    private static string $paymentTable = '';

    /**
     * 合法的状态流转映射。
     */
    private const STATUS_TRANSITIONS = [
        'pending'          => ['paid', 'expired', 'cancelled', 'failed'],
        'paid'             => ['delivering', 'delivered', 'refunding'],
        'delivering'       => ['delivered', 'delivery_failed'],
        'delivered'        => ['completed', 'refunding'],
        'delivery_failed'  => ['refunding', 'delivering'],
        'completed'        => ['refunding'],
        'refunding'        => ['refunded'],
        // 终态不可流转
        'expired'          => [],
        'cancelled'        => [],
        'refunded'         => [],
        'failed'           => ['refunding'],
    ];

    private static function tables(): void
    {
        if (self::$orderTable === '') {
            $prefix = Database::prefix();
            self::$orderTable = $prefix . 'order';
            self::$orderGoodsTable = $prefix . 'order_goods';
            self::$orderGoodsDeliveryItemTable = $prefix . 'order_goods_delivery_item';
            self::$paymentTable = $prefix . 'order_payment';
        }
    }

    /**
     * guest_address 六字段是否均已填写（trim 后非空）。
     *
     * @param mixed $g
     */
    private static function isGuestShippingAddressFilled($g): bool
    {
        if (!is_array($g)) {
            return false;
        }
        foreach (['recipient', 'mobile', 'province', 'city', 'district', 'detail'] as $k) {
            if (trim((string) ($g[$k] ?? '')) === '') {
                return false;
            }
        }
        return true;
    }

    /**
     * 生成订单编号。
     * 格式：EMS + 年月日时分秒 + 6位随机字符
     */
    public static function generateOrderNo(): string
    {
        $chars = '0123456789';
        $rand = '';
        for ($i = 0; $i < 6; $i++) {
            $rand .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return date('YmdHis') . $rand;
    }

    /**
     * 创建订单。
     *
     * @param array $orderData 订单主数据
     * @param array $goodsItems 订单商品列表，每项包含 goods_id, spec_id, quantity 等
     * @return array{order_id: int, order_no: string} 创建成功返回订单信息
     * @throws RuntimeException
     */
    public static function create(array $orderData, array $goodsItems): array
    {
        self::tables();

        if (empty($goodsItems)) {
            throw new RuntimeException('订单商品不能为空');
        }

        Database::begin();
        try {
            $orderNo = self::generateOrderNo();
            $now = date('Y-m-d H:i:s');

            // 计算商品总金额并校验库存
            $goodsAmount = 0;
            $orderGoodsRows = [];

            foreach ($goodsItems as $item) {
                $goodsId = (int) $item['goods_id'];
                $specId = (int) ($item['spec_id'] ?? 0);
                $quantity = (int) ($item['quantity'] ?? 1);

                // 查询商品信息
                $goods = GoodsModel::getById($goodsId);
                if (!$goods || (int) $goods['status'] !== 1 || (int) $goods['is_on_sale'] !== 1 || $goods['deleted_at'] !== null) {
                    throw new RuntimeException('商品不存在或已下架');
                }

                // 查询规格信息
                $spec = null;
                if ($specId > 0) {
                    $specs = GoodsModel::getSpecsByGoodsId($goodsId);
                    foreach ($specs as $s) {
                        if ((int) $s['id'] === $specId) {
                            $spec = $s;
                            break;
                        }
                    }
                } else {
                    // 无指定规格，取第一个
                    $specs = GoodsModel::getSpecsByGoodsId($goodsId);
                    $spec = $specs[0] ?? null;
                    if ($spec) {
                        $specId = (int) $spec['id'];
                    }
                }

                if (!$spec) {
                    throw new RuntimeException('商品规格不存在：' . $goods['title']);
                }

                // 起购 / 限购（goods_spec.min_buy、max_buy；与详情页 goods.js 一致）
                $minBuyRaw = $spec['min_buy'] ?? null;
                $minBuy = ($minBuyRaw === null || $minBuyRaw === '') ? 1 : max(1, (int) $minBuyRaw);
                $maxBuyRaw = $spec['max_buy'] ?? null;
                $maxBuy = ($maxBuyRaw === null || $maxBuyRaw === '' || (int) $maxBuyRaw <= 0) ? 0 : (int) $maxBuyRaw;
                if ($quantity < $minBuy) {
                    throw new RuntimeException('购买数量不能少于 ' . $minBuy);
                }
                if ($maxBuy > 0 && $quantity > $maxBuy) {
                    throw new RuntimeException('购买数量不能超过 ' . $maxBuy);
                }

                // 库存不足抛专用异常，携带商品名 + 剩余数量，调用方按场景选择消息格式
                if ((int) $spec['stock'] >= 0 && (int) $spec['stock'] < $quantity) {
                    throw new StockShortageException((string) $goods['title'], (int) $spec['stock']);
                }

                // 商品类型插件必须已启用，否则下单后续环节（order_submit 校验、
                // order_paid 发货、needs_address 判断等）的钩子都接不上，订单会卡成半残。
                // 这里 fail-fast 拦住，比让用户付钱后永远收不到货好得多。
                // 为兼容历史无类型数据：goods_type 为空串时放过，保持旧行为。
                $goodsType = (string) ($goods['goods_type'] ?? '');
                if ($goodsType !== '' && class_exists('GoodsTypeManager')) {
                    if (GoodsTypeManager::getTypeConfig($goodsType) === null) {
                        throw new RuntimeException('商品「' . $goods['title'] . '」所属类型插件未启用，暂不支持下单，请联系管理员');
                    }
                }

                // 插件下单前校验（如虚拟商品检查卡密库存等）
                $submitError = applyFilter("goods_type_{$goods['goods_type']}_order_submit", '', [
                    'goods'    => $goods,
                    'spec'     => $spec,
                    'quantity' => $quantity,
                ]);
                if (is_string($submitError) && $submitError !== '') {
                    throw new RuntimeException($submitError);
                }

                // 价格（BIGINT 格式）：price_raw 为对客成交单价（分站引用货 = 主站售价×加价）
                $priceBigint = (int) $spec['price_raw'];
                $basePriceBigint = (int) ($spec['_base_price_raw'] ?? $spec['price_raw']);
                $itemTotal = $priceBigint * $quantity;
                $goodsAmount += $itemTotal;

                // 封面图
                $covers = json_decode($goods['cover_images'] ?? '[]', true) ?: [];

                $orderGoodsRows[] = [
                    'goods_id'      => $goodsId,
                    'spec_id'       => $specId,
                    'goods_title'   => $goods['title'],
                    'spec_name'     => $spec['name'],
                    'cover_image'   => $covers[0] ?? '',
                    'price'         => $priceBigint,
                    'quantity'      => $quantity,
                    'goods_type'    => $goods['goods_type'] ?? '',
                    // 商户分账所需的原始字段（下面统一生成快照）
                    'goods_owner_id'=> (int) ($goods['owner_id'] ?? 0),
                    'markup_rate'   => (int) ($spec['_shop_markup_rate'] ?? 0),
                    '_base_price'   => $basePriceBigint,
                    '_owner_cost'   => (int) ($spec['_owner_cost_raw'] ?? 0),
                    // 本商品原始配置，下面算满减时用（configs.discount_rules 是商品级的阶梯折扣）
                    '_goods_configs' => (string) ($goods['configs'] ?? ''),
                    '_item_total'    => $itemTotal,
                ];
            }

            // —— 商品级满减：按每条 order_goods 的 itemTotal 匹配该商品 configs.discount_rules 的最大档
            //   - threshold/discount 在 DB 里已经是 ×1000000 的 BIGINT raw，单位和 itemTotal 一致
            //   - 多条 order_goods 的满减独立累加；不跨商品合并门槛
            //   - 前端 goods.js pickDiscountAmount 同款规则，保持两端一致
            $reduceAmount = 0;
            foreach ($orderGoodsRows as $r) {
                $configs = json_decode((string) ($r['_goods_configs'] ?? ''), true);
                $rules = is_array($configs) ? ($configs['discount_rules'] ?? []) : [];
                if (!is_array($rules) || !$rules) continue;
                $itemTotal = (int) $r['_item_total'];
                $itemReduce = 0;
                foreach ($rules as $rule) {
                    $t = (int) ($rule['threshold'] ?? 0);
                    $d = (int) ($rule['discount'] ?? 0);
                    if ($itemTotal >= $t && $d > $itemReduce) $itemReduce = $d;
                }
                $reduceAmount += $itemReduce;
            }
            // 内部字段不入表，循环结束立即清掉，避免 Database::insert 把未知字段传进 SQL
            foreach ($orderGoodsRows as &$_r) {
                unset($_r['_goods_configs'], $_r['_item_total']);
            }
            unset($_r);

            // —— 优惠券（可选）：已由调用方 CouponService::check 校验过；这里只在事务内扣减次数
            // orderData 约定携带：coupon（check 返回的 coupon 数据）+ coupon_discount（BIGINT 折扣）
            $couponDiscount = 0;
            $couponCode = null;
            if (!empty($orderData['coupon']) && is_array($orderData['coupon'])) {
                $couponCode = (string) $orderData['coupon']['code'];
                $couponDiscount = (int) ($orderData['coupon_discount'] ?? 0);
            }
            // 总折扣 = 商品级满减 + 优惠券折扣；上限为商品总额，避免出现负应付
            $discountAmount = $reduceAmount + $couponDiscount;
            if ($discountAmount > $goodsAmount) $discountAmount = $goodsAmount;

            $payAmount = $goodsAmount - $discountAmount;
            if ($payAmount < 0) $payAmount = 0;

            // 商户上下文：下单时所在的商户（由调用方从 MerchantContext 取入）
            // owner_id 一致性：商户订单的 owner_id 必须等于商户主 user_id（而非商户 id）
            $merchantId = (int) ($orderData['merchant_id'] ?? 0);
            $ownerId = (int) ($orderData['owner_id'] ?? 0);

            // 展示货币快照：下单瞬间锁定访客选择的展示货币 + 当时汇率，
            // 后续订单详情都按这个快照渲染，不受汇率变动影响（访客币=主货币时返回 ['', 0]）
            [$displayCurrencyCode, $displayRate] = Currency::visitorSnapshot();

            // 收货地址快照：任一商品类型在 goods_type_register 里声明 needs_address=true 时，本单要求填地址
            //   - 登录用户：从 $orderData['address_id'] 查 UserAddressModel 快照为 JSON
            //   - 游客：从 $orderData['guest_address'] 读手填 6 字段（下单页弹出的表单），同款 JSON 快照
            //   - 所有商品都不需要地址时 → null（保持现有虚拟卡密订单不变）
            $needsAddress = false;
            $goodsCfgCache = [];
            foreach ($orderGoodsRows as $r) {
                $typeCfg = class_exists('GoodsTypeManager') ? GoodsTypeManager::getTypeConfig((string) ($r['goods_type'] ?? '')) : null;
                $need = !empty($typeCfg['needs_address']);
                $cfgArr = json_decode((string) ($r['_goods_configs'] ?? '{}'), true) ?: [];
                if ($cfgArr === []) {
                    $gid = (int) ($r['goods_id'] ?? 0);
                    if ($gid > 0) {
                        if (!array_key_exists($gid, $goodsCfgCache)) {
                            $cfgRow = Database::fetchOne(
                                "SELECT `configs` FROM `" . Database::prefix() . "goods` WHERE `id` = ? LIMIT 1",
                                [$gid]
                            );
                            $goodsCfgCache[$gid] = is_array($cfgRow)
                                ? (json_decode((string) ($cfgRow['configs'] ?? '{}'), true) ?: [])
                                : [];
                        }
                        if (is_array($goodsCfgCache[$gid]) && $goodsCfgCache[$gid] !== []) {
                            $cfgArr = $goodsCfgCache[$gid];
                        }
                    }
                }
                $needCtx = [
                    'goods_type' => (string) ($r['goods_type'] ?? ''),
                    'configs'    => $cfgArr,
                ];
                $need = (bool) applyFilter('goods_needs_address', $need, $needCtx);
                if ($need) {
                    $needsAddress = true;
                    break;
                }
            }
            $addressSnapshot = null;
            if ($needsAddress) {
                $buyerId = (int) ($orderData['user_id'] ?? 0);
                $addressId = (int) ($orderData['address_id'] ?? 0);
                $g = $orderData['guest_address'] ?? null;

                if ($buyerId > 0 && $addressId > 0) {
                    // —— 登录用户且显式选了地址簿：优先走地址簿
                    $addr = UserAddressModel::findById($addressId, $buyerId);
                    if ($addr === null) {
                        throw new RuntimeException('收货地址不存在或不属于当前账户');
                    }
                    $addressSnapshot = self::buildAddressSnapshot($addr);
                } elseif (self::isGuestShippingAddressFilled($g)) {
                    // —— 手填整单地址：游客单，或 API 单（user_id 为付款人但终端收货走 guest_address）
                    if (!preg_match('/^1\d{10}$/', (string) $g['mobile'])) {
                        throw new RuntimeException('收货手机号格式错误');
                    }
                    if (mb_strlen((string) $g['detail']) > 255) {
                        throw new RuntimeException('详细地址过长');
                    }
                    $addressSnapshot = self::buildAddressSnapshot($g);
                } elseif ($buyerId > 0) {
                    throw new RuntimeException('请选择收货地址');
                } else {
                    // —— 游客未填齐
                    if (!is_array($g)) {
                        throw new RuntimeException('请填写收货地址');
                    }
                    foreach (['recipient', 'mobile', 'province', 'city', 'district', 'detail'] as $k) {
                        if (empty(trim((string) ($g[$k] ?? '')))) {
                            throw new RuntimeException('请填写完整的收货地址');
                        }
                    }
                    if (!preg_match('/^1\d{10}$/', (string) $g['mobile'])) {
                        throw new RuntimeException('收货手机号格式错误');
                    }
                    if (mb_strlen((string) $g['detail']) > 255) {
                        throw new RuntimeException('详细地址过长');
                    }
                    $addressSnapshot = self::buildAddressSnapshot($g);
                }
            }

            // 插入订单主表（使用 Database::insert 获取自增ID）
            $orderId = (int) Database::insert('order', [
                'order_no'            => $orderNo,
                'user_id'             => (int) ($orderData['user_id'] ?? 0),
                'guest_token'         => $orderData['guest_token'] ?? null,
                'owner_id'            => $ownerId,
                'merchant_id'         => $merchantId,
                'goods_amount'        => $goodsAmount,
                'discount_amount'     => $discountAmount,
                'pay_amount'          => $payAmount,
                'payment_code'        => $orderData['payment_code'] ?? '',
                'payment_name'        => $orderData['payment_name'] ?? '',
                'payment_plugin'      => $orderData['payment_plugin'] ?? '',
                'payment_plugin_name' => $orderData['payment_plugin_name'] ?? '',
                'payment_channel'     => $orderData['payment_channel'] ?? '',
                'status'              => 'pending',
                'coupon_code'         => $couponCode,
                // 返佣归因快照：登录用户取 user.inviter_l1/l2；游客走 Cookie 再翻一层 user 表
                'inviter_l1'          => $orderData['inviter_l1'] ?? null,
                'inviter_l2'          => $orderData['inviter_l2'] ?? null,
                'contact_info'        => isset($orderData['contact_info']) ? (is_array($orderData['contact_info']) ? json_encode($orderData['contact_info'], JSON_UNESCAPED_UNICODE) : (string) $orderData['contact_info']) : null,
                'guest_contact'       => isset($orderData['guest_contact']) && (string) $orderData['guest_contact'] !== ''
                    ? (string) $orderData['guest_contact']
                    : null,
                'order_password'      => $orderData['order_password'] ?? null,
                'ip'                  => $orderData['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
                'source'              => $orderData['source'] ?? 'web',
                'display_currency_code' => $displayCurrencyCode,
                'display_rate'        => $displayRate,
                'shipping_address_snapshot' => $addressSnapshot,
                'delivery_callback_url' => !empty($orderData['delivery_callback_url']) ? (string) $orderData['delivery_callback_url'] : null,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);

            // 事务内扣减券使用次数 + 标记用户券为 used
            if (!empty($orderData['coupon']) && is_array($orderData['coupon'])) {
                $couponService = new CouponService();
                $couponService->apply(
                    $orderData['coupon'],
                    (int) ($orderData['user_id'] ?? 0),
                    $orderId
                );
            }

            // 插入订单商品（含商户分账快照字段：goods_owner_id / cost_amount / fee_amount）
            foreach ($orderGoodsRows as $row) {
                // 快照计算：主站订单不分账；商户订单按商品归属计算
                $costAmount = 0;
                $feeAmount = 0;
                if ($merchantId > 0) {
                    $goodsOwnerId = (int) $row['goods_owner_id'];
                    if ($goodsOwnerId === 0) {
                        // 引用商品：拿货成本 = 站长拿货单价 × 数量（与对客售价无关）
                        $ownerUnitCost = (int) ($row['_owner_cost'] ?? 0);
                        if ($ownerUnitCost <= 0) {
                            $ownerUnitCost = (int) round(
                                (int) $row['_base_price'] * MerchantLedgerService::resolveOwnerDiscountRate($ownerId)
                            );
                        }
                        $costAmount = $ownerUnitCost * max(1, (int) $row['quantity']);
                    } elseif ($goodsOwnerId === $ownerId) {
                        // 自建商品：fee = price × qty × self_goods_fee_rate / 10000
                        // 注意：price 是已乘买家折扣后的成交价，主站收的手续费按"实付"算
                        $feeAmount = MerchantLedgerService::computeSelfFee(
                            $merchantId,
                            (int) $row['price'],
                            (int) $row['quantity']
                        );
                    }
                    // 其它情况（owner 不匹配且不是主站货）理论上不应出现 —— 留作 0
                }

                $sql = "INSERT INTO `" . self::$orderGoodsTable . "`
                        (order_id, goods_id, spec_id, goods_title, spec_name, cover_image, price, quantity, goods_type,
                         goods_owner_id, cost_amount, fee_amount, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                Database::execute($sql, [
                    $orderId,
                    $row['goods_id'],
                    $row['spec_id'],
                    $row['goods_title'],
                    $row['spec_name'],
                    $row['cover_image'],
                    $row['price'],
                    $row['quantity'],
                    $row['goods_type'],
                    (int) $row['goods_owner_id'],
                    $costAmount,
                    $feeAmount,
                    $now,
                ]);
            }

            Database::commit();

            return ['order_id' => $orderId, 'order_no' => $orderNo, 'pay_amount' => $payAmount];

        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * 状态流转。
     *
     * @throws RuntimeException
     */
    public static function changeStatus(int $orderId, string $newStatus): bool
    {
        self::tables();

        $order = self::getById($orderId);
        if (!$order) {
            self::writeSystemLog(
                'warning',
                '订单状态流转失败',
                '订单不存在，无法执行状态流转',
                [
                    'order_id' => $orderId,
                    'target_status' => $newStatus,
                    'reason' => 'order_not_found',
                ]
            );
            throw new RuntimeException('订单不存在');
        }

        $currentStatus = $order['status'];
        $allowed = self::STATUS_TRANSITIONS[$currentStatus] ?? [];

        if (!in_array($newStatus, $allowed, true)) {
            self::writeSystemLog(
                'warning',
                '订单状态流转拒绝',
                '当前状态不允许流转到目标状态',
                [
                    'order_id' => $orderId,
                    'current_status' => $currentStatus,
                    'target_status' => $newStatus,
                    'allowed_statuses' => $allowed,
                ]
            );
            throw new RuntimeException("状态不允许从 {$currentStatus} 变更为 {$newStatus}");
        }

        $updates = ['status' => $newStatus];

        // 根据新状态设置时间字段
        switch ($newStatus) {
            case 'paid':
                $updates['pay_time'] = date('Y-m-d H:i:s');
                break;
            case 'delivered':
                $updates['delivery_time'] = date('Y-m-d H:i:s');
                break;
            case 'completed':
                $updates['complete_time'] = date('Y-m-d H:i:s');
                break;
        }

        $sets = [];
        $params = [];
        foreach ($updates as $k => $v) {
            $sets[] = "`{$k}` = ?";
            $params[] = $v;
        }
        $params[] = $orderId;

        $sql = "UPDATE `" . self::$orderTable . "` SET " . implode(', ', $sets) . " WHERE id = ?";
        $affected = Database::execute($sql, $params);
        $ok = $affected > 0;

        // 状态钩子：订单完成 → 触发结算；退款完成 → 倒扣
        // 商户订单走 MerchantLedgerService（并跳过主站推广返佣，见 RebateService::settleOrder）
        // 主站订单走 RebateService
        // 失败不影响主状态流转，仅吞掉异常
        if ($ok) {
            $merchantId = (int) ($order['merchant_id'] ?? 0);

            try {
                if ($newStatus === 'completed') {
                    if ($merchantId > 0) {
                        MerchantLedgerService::settleOrder($orderId);
                    } else {
                        RebateService::settleOrder($orderId);
                    }
                    UserExperienceService::onOrderCompleted($orderId);
                } elseif ($newStatus === 'refunded') {
                    if ($merchantId > 0) {
                        MerchantLedgerService::refundOrder($orderId);
                    } else {
                        RebateService::revertOrder($orderId);
                    }
                }
            } catch (Throwable $e) {
                self::writeSystemLog(
                    'warning',
                    '订单后置处理失败',
                    '状态变更成功，但结算/经验/回滚等后置处理失败',
                    [
                        'order_id' => $orderId,
                        'status' => $newStatus,
                        'merchant_id' => $merchantId,
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }
        return $ok;
    }

    /**
     * 余额支付处理。
     * 扣款 → 更新订单状态 → 记录支付流水 → 触发发货钩子。
     *
     * @throws RuntimeException
     */
    public static function payWithBalance(int $orderId, int $userId): bool
    {
        self::tables();

        $order = self::getById($orderId);
        if (!$order) {
            throw new RuntimeException('订单不存在');
        }
        if ($order['status'] !== 'pending') {
            throw new RuntimeException('订单状态异常');
        }

        $payAmount = (int) $order['pay_amount'];

        Database::begin();
        try {
            // 扣款
            $balanceLog = new UserBalanceLogModel();
            $ok = $balanceLog->decrease($userId, $payAmount, '购买商品：' . $order['order_no']);
            if (!$ok) {
                throw new RuntimeException('余额不足');
            }

            // 更新订单状态为已支付
            self::changeStatus($orderId, 'paid');

            // 记录支付流水
            $sql = "INSERT INTO `" . self::$paymentTable . "`
                    (order_id, payment_code, payment_plugin, trade_no, amount, status, paid_at, created_at)
                    VALUES (?, 'balance', 'built-in', ?, ?, 'success', NOW(), NOW())";
            Database::execute($sql, [
                $orderId,
                'BAL' . $order['order_no'],
                $payAmount,
            ]);

            Database::commit();

            // 触发发货流程（异步，不在事务内）
            self::triggerDelivery($orderId);

            return true;

        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * 0 元订单支付处理。
     * 直接更新订单状态为已支付并记录一条内置支付流水，然后触发发货队列。
     *
     * @throws RuntimeException
     */
    public static function payFreeOrder(int $orderId): bool
    {
        self::tables();

        $order = self::getById($orderId);
        if (!$order) {
            throw new RuntimeException('订单不存在');
        }
        if ($order['status'] !== 'pending') {
            throw new RuntimeException('订单状态异常');
        }

        $payAmount = (int) $order['pay_amount'];
        if ($payAmount > 0) {
            throw new RuntimeException('订单金额不为0，不能走免支付流程');
        }

        Database::begin();
        try {
            self::changeStatus($orderId, 'paid');

            $sql = "INSERT INTO `" . self::$paymentTable . "`
                    (order_id, payment_code, payment_plugin, trade_no, amount, status, paid_at, created_at)
                    VALUES (?, 'free', 'built-in', ?, 0, 'success', NOW(), NOW())";
            Database::execute($sql, [
                $orderId,
                'FREE' . $order['order_no'],
            ]);

            Database::commit();

            self::triggerDelivery($orderId);

            return true;
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * 触发发货流程。
     * 将每个订单商品写入 em_delivery_queue，由后台任务服务队列消费者异步执行。
     * 不直接调用插件钩子，避免阻塞用户请求。
     */
    public static function triggerDelivery(int $orderId): void
    {
        self::tables();

        // 更新订单状态为发货中
        try {
            self::changeStatus($orderId, 'delivering');
        } catch (Throwable $e) {
            self::writeSystemLog(
                'warning',
                '订单进入发货中失败',
                '触发发货时状态流转失败，订单保持原状态',
                [
                    'order_id' => $orderId,
                    'from_status' => 'paid',
                    'to_status' => 'delivering',
                    'error' => $e->getMessage(),
                ]
            );
            return;
        }

        $orderGoods = self::getOrderGoods($orderId);
        $queueTable = Database::prefix() . 'delivery_queue';

        foreach ($orderGoods as $og) {
            $goodsType = $og['goods_type'];
            if ($goodsType === '') {
                continue;
            }

            // 生成回调验证令牌
            $callbackToken = bin2hex(random_bytes(16));

            // 写入队列任务
            Database::insert('delivery_queue', [
                'order_id'       => $orderId,
                'order_goods_id' => (int) $og['id'],
                'task_type'      => 'delivery',
                'goods_type'     => $goodsType,
                'payload'        => json_encode([
                    'plugin_data' => $og['plugin_data'] ?? '',
                ], JSON_UNESCAPED_UNICODE),
                'status'         => 'pending',
                'callback_token' => $callbackToken,
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * 管理员手动发货：对单条 order_goods 写入发货内容 + 可选的插件附加数据，
     * 然后检查同订单其他行是否也齐了，齐了就整单流转到 delivered→completed。
     *
     * 此方法只做"落库 + 状态流转"的编排；每种商品类型具体要填什么字段
     * 由插件自己在 goods_type_{type}_manual_delivery_submit 钩子里准备好
     * $deliveryContent 和 $pluginData 再传进来。
     *
     * @param int         $orderGoodsId    订单商品行 ID
     * @param string      $deliveryContent 展示给买家看的发货内容（卡密文本 / 快递描述）
     * @param array|null  $pluginData      合并到 order_goods.plugin_data 的额外字段（如 express_no）
     * @throws RuntimeException
     */
    public static function manualShipOrderGoods(int $orderGoodsId, string $deliveryContent, ?array $pluginData = null): void
    {
        self::tables();
        $prefix = Database::prefix();

        $og = Database::fetchOne(
            "SELECT id, order_id, delivery_content, plugin_data FROM {$prefix}order_goods WHERE id = ?",
            [$orderGoodsId]
        );
        if (!$og) {
            throw new RuntimeException('订单商品不存在');
        }
        if (self::hasDeliveryContent($orderGoodsId, (string) ($og['delivery_content'] ?? ''))) {
            throw new RuntimeException('该商品已发货，不能重复发货');
        }
        if ($deliveryContent === '') {
            throw new RuntimeException('发货内容不能为空');
        }

        // 合并 plugin_data：保留原有字段，插件传的字段覆盖同名键
        $mergedPluginData = [];
        if (!empty($og['plugin_data'])) {
            $decoded = json_decode((string) $og['plugin_data'], true);
            if (is_array($decoded)) $mergedPluginData = $decoded;
        }
        if (is_array($pluginData)) {
            $mergedPluginData = array_merge($mergedPluginData, $pluginData);
        }

        $stored = self::persistDeliveryContent($orderGoodsId, $deliveryContent);
        if (!$stored) {
            throw new RuntimeException('发货明细写入失败，请先完成系统升级迁移');
        }
        $now = date('Y-m-d H:i:s');
        Database::execute(
            "UPDATE {$prefix}order_goods SET delivery_at = ?, plugin_data = ? WHERE id = ?",
            [
                $now,
                $mergedPluginData ? json_encode($mergedPluginData, JSON_UNESCAPED_UNICODE) : null,
                $orderGoodsId,
            ]
        );

        // 如存在异步回调地址，推送发货结果给下游
        self::notifyDeliveryCallback($orderGoodsId);

        // 检查同订单其他行是否也都已发货，齐了就整单流转 delivered → completed
        $orderId = (int) $og['order_id'];
        $remaining = false;
        foreach (self::getOrderGoods($orderId) as $row) {
            if (!self::hasDeliveryContent((int) $row['id'], (string) ($row['delivery_content'] ?? ''))) {
                $remaining = true;
                break;
            }
        }
        if (!$remaining) {
            // 所有行都发完了 → delivered → completed（changeStatus 会拒绝非法流转，自动跳过）
            try { self::changeStatus($orderId, 'delivered'); } catch (Throwable $e) {}
            try { self::changeStatus($orderId, 'completed'); } catch (Throwable $e) {}
        }
    }

    /**
     * 检查订单所有商品是否已发货完成，如果是则更新订单状态。
     * 由后台任务服务队列消费者在每个任务完成后调用。
     *
     * 逻辑：
     * - 自动发货写入了发货内容（明细表）才算已发货
     * - 人工发货的商品（内容为空但队列任务已 success）也算处理完毕
     * - 如果所有商品都有发货内容 → delivered → completed
     * - 如果有人工发货商品还没内容 → 只保持 delivering，等管理员手动发货
     */
    public static function checkDeliveryComplete(int $orderId): void
    {
        self::tables();
        $prefix = Database::prefix();
        $orderGoods = self::getOrderGoods($orderId);
        $allAutoDelivered = true;
        $hasManual = false;

        foreach ($orderGoods as $og) {
            if (self::hasDeliveryContent((int) ($og['id'] ?? 0), (string) ($og['delivery_content'] ?? ''))) {
                continue; // 已发货
            }
            // 检查队列任务是否已完成（人工发货场景：队列 success 但发货内容为空）
            $task = Database::fetchOne(
                "SELECT status FROM {$prefix}delivery_queue WHERE order_goods_id = ? ORDER BY id DESC LIMIT 1",
                [(int) $og['id']]
            );
            if ($task && $task['status'] === 'success') {
                $hasManual = true; // 人工发货，队列已处理但没有自动写入内容
            } else {
                $allAutoDelivered = false; // 还有未处理完的任务
            }
        }

        if (!$allAutoDelivered) {
            return; // 还有任务没完成
        }

        if ($hasManual) {
            // 有人工发货的商品，订单保持 delivering 等管理员手动发货
            return;
        }

        // 全部自动发货完成
        try {
            self::changeStatus($orderId, 'delivered');
            self::changeStatus($orderId, 'completed');
        } catch (Throwable $e) {
            self::writeSystemLog(
                'warning',
                '订单完结状态流转失败',
                '发货完成后尝试流转 delivered/completed 失败，订单保持当前状态',
                [
                    'order_id' => $orderId,
                    'target_statuses' => ['delivered', 'completed'],
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * 若订单配置了 delivery_callback_url，则把订单商品发货结果异步回调给下游。
     * 失败仅记录日志，不影响主流程状态流转。
     */
    public static function notifyDeliveryCallback(int $orderGoodsId): void
    {
        self::tables();
        $prefix = Database::prefix();
        $row = Database::fetchOne(
            "SELECT og.`id` AS order_goods_id, og.`order_id`, og.`goods_id`, og.`spec_id`, og.`delivery_content`, og.`delivery_at`,
                    o.`order_no`, o.`delivery_callback_url`
             FROM `{$prefix}order_goods` og
             INNER JOIN `{$prefix}order` o ON o.`id` = og.`order_id`
             WHERE og.`id` = ? LIMIT 1",
            [$orderGoodsId]
        );
        if (!$row) {
            return;
        }
        $callbackUrl = trim((string) ($row['delivery_callback_url'] ?? ''));
        $deliveryContent = self::getDeliveryContent($orderGoodsId, (string) ($row['delivery_content'] ?? ''));
        if ($callbackUrl === '' || $deliveryContent === '') {
            return;
        }

        $payload = [
            'order_no'         => (string) ($row['order_no'] ?? ''),
            'order_goods_id'   => (int) ($row['order_goods_id'] ?? 0),
            'delivery_content' => $deliveryContent,
            'delivery_at'      => (string) ($row['delivery_at'] ?? ''),
        ];
        $specId = (int) ($row['spec_id'] ?? 0);
        if ($specId > 0) {
            $specRow = Database::fetchOne(
                "SELECT `stock` FROM `{$prefix}goods_spec` WHERE `id` = ? LIMIT 1",
                [$specId]
            );
            if ($specRow !== null && array_key_exists('stock', $specRow)) {
                $payload['spec_remaining_stock'] = (int) $specRow['stock'];
            }
        }
        $goodsId = (int) ($row['goods_id'] ?? 0);
        if ($goodsId > 0) {
            $range = GoodsModel::getPriceAndStockRange($goodsId);
            $payload['goods_total_stock'] = (int) ($range['total_stock'] ?? 0);
        }
        $httpCode = 0;
        $responseBody = '';
        $ok = self::postJson($callbackUrl, $payload, $httpCode, $responseBody);
        if ($ok) {
            self::writeSystemLog(
                'info',
                '订单发货回调推送成功',
                '已向下游推送发货结果',
                [
                    'order_no' => (string) ($row['order_no'] ?? ''),
                    'order_goods_id' => (int) ($row['order_goods_id'] ?? 0),
                    'callback_url' => $callbackUrl,
                    'http_code' => $httpCode,
                    'response' => mb_substr((string) $responseBody, 0, 500),
                ]
            );
        } else {
            self::writeSystemLog(
                'warning',
                '订单发货回调推送失败',
                '向下游推送发货结果失败',
                [
                    'order_no' => (string) ($row['order_no'] ?? ''),
                    'order_goods_id' => (int) ($row['order_goods_id'] ?? 0),
                    'callback_url' => $callbackUrl,
                    'http_code' => $httpCode,
                    'response' => mb_substr((string) $responseBody, 0, 500),
                ]
            );
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function postJson(string $url, array $payload, int &$httpCode = 0, string &$responseBody = ''): bool
    {
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            $responseBody = 'invalid_url';
            return false;
        }
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body)) {
            $responseBody = 'json_encode_failed';
            return false;
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                $responseBody = 'curl_init_failed';
                return false;
            }
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 2,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=UTF-8'],
            ]);
            $out = curl_exec($ch);
            $errno = curl_errno($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($errno !== 0 && function_exists('log_message')) {
                log_message('error', '[order_callback] push failed errno=' . $errno . ' url=' . $url);
                $responseBody = 'curl_errno:' . $errno;
                return false;
            }
            $responseBody = is_string($out) ? $out : '';
            return $httpCode >= 200 && $httpCode < 300;
        }
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => 8.0,
            ],
        ]);
        $result = @file_get_contents($url, false, $ctx);
        $responseBody = is_string($result) ? $result : '';
        $headerLine = isset($http_response_header[0]) ? (string) $http_response_header[0] : '';
        if (preg_match('#\s(\d{3})\s#', $headerLine, $m)) {
            $httpCode = (int) $m[1];
        }
        if ($httpCode === 0) {
            return $result !== false;
        }
        return $httpCode >= 200 && $httpCode < 300;
    }

    /**
     * @param array<string, mixed> $detail
     */
    private static function writeSystemLog(string $level, string $action, string $message, array $detail = []): void
    {
        try {
            if (!defined('EM_ROOT')) {
                return;
            }
            require_once EM_ROOT . '/include/model/SystemLogModel.php';
            if (!class_exists('SystemLogModel')) {
                return;
            }
            $m = new SystemLogModel();
            if ($level === 'error') {
                $m->error('system', $action, $message, $detail);
            } elseif ($level === 'warning' || $level === 'warn') {
                $m->warning('system', $action, $message, $detail);
            } else {
                $m->info('system', $action, $message, $detail);
            }
        } catch (Throwable $e) {
            // 系统日志写入失败不影响业务主流程
        }
    }

    /**
     * 按 ID 查询订单。
     */
    public static function getById(int $id): ?array
    {
        self::tables();
        return Database::fetchOne("SELECT * FROM `" . self::$orderTable . "` WHERE id = ?", [$id]);
    }

    /**
     * 按订单编号查询。
     */
    public static function getByOrderNo(string $orderNo): ?array
    {
        self::tables();
        return Database::fetchOne("SELECT * FROM `" . self::$orderTable . "` WHERE order_no = ?", [$orderNo]);
    }

    /**
     * 获取订单商品列表。
     */
    public static function getOrderGoods(int $orderId): array
    {
        self::tables();
        $rows = Database::query("SELECT * FROM `" . self::$orderGoodsTable . "` WHERE order_id = ? ORDER BY id", [$orderId]);
        if ($rows === []) {
            return [];
        }
        self::hydrateDeliveryContent($rows);
        return $rows;
    }

    /**
     * 订单是否已购买（非待付/取消/过期/失败），用于控制「购买后才可见」的内容如使用教程。
     */
    public static function isPurchasedStatus(string $status): bool
    {
        return !in_array($status, ['pending', 'expired', 'cancelled', 'failed'], true);
    }

    /**
     * 为订单商品行附加商品使用教程（guide 富文本）。
     *
     * @param array<int, array<string, mixed>> $orderGoods
     * @return array<int, array<string, mixed>>
     */
    public static function attachGoodsGuides(array $orderGoods): array
    {
        if ($orderGoods === []) {
            return $orderGoods;
        }

        $goodsIds = [];
        foreach ($orderGoods as $og) {
            $gid = (int) ($og['goods_id'] ?? 0);
            if ($gid > 0) {
                $goodsIds[$gid] = true;
            }
        }
        if ($goodsIds === []) {
            return $orderGoods;
        }

        $ids = array_keys($goodsIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::query(
            'SELECT `id`, `guide` FROM `' . Database::prefix() . 'goods`
              WHERE `id` IN (' . $placeholders . ') AND `deleted_at` IS NULL',
            $ids
        );

        $guideMap = [];
        foreach ($rows as $row) {
            $guide = trim((string) ($row['guide'] ?? ''));
            if ($guide !== '') {
                $guideMap[(int) $row['id']] = $guide;
            }
        }

        foreach ($orderGoods as &$og) {
            $gid = (int) ($og['goods_id'] ?? 0);
            $og['goods_guide'] = $guideMap[$gid] ?? '';
        }
        unset($og);

        return $orderGoods;
    }

    /**
     * 是否已存在发货内容（兼容旧字段 + 新明细表）。
     */
    public static function hasDeliveryContent(int $orderGoodsId, string $legacyContent = ''): bool
    {
        self::tables();
        if (trim($legacyContent) !== '') {
            return true;
        }
        try {
            $row = Database::fetchOne(
                "SELECT 1 FROM `" . self::$orderGoodsDeliveryItemTable . "` WHERE order_goods_id = ? LIMIT 1",
                [$orderGoodsId]
            );
            return (bool) $row;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * 读取某条订单商品的完整发货内容（优先新明细表）。
     */
    public static function getDeliveryContent(int $orderGoodsId, string $fallback = ''): string
    {
        self::tables();
        try {
            $rows = Database::query(
                "SELECT `content` FROM `" . self::$orderGoodsDeliveryItemTable . "`
                 WHERE `order_goods_id` = ? ORDER BY `sort` ASC, `id` ASC",
                [$orderGoodsId]
            );
            if ($rows !== []) {
                $lines = [];
                foreach ($rows as $r) {
                    $line = trim((string) ($r['content'] ?? ''));
                    if ($line !== '') $lines[] = $line;
                }
                if ($lines !== []) {
                    return implode("\n", $lines);
                }
            }
        } catch (Throwable $e) {
            // 表尚未迁移时回退旧字段
        }
        return (string) $fallback;
    }

    /**
     * 持久化发货内容到明细表。
     */
    public static function persistDeliveryContent(int $orderGoodsId, string $deliveryContent): bool
    {
        self::tables();
        $lines = self::splitDeliveryLines($deliveryContent);
        if ($lines === []) {
            return false;
        }
        try {
            Database::execute(
                "DELETE FROM `" . self::$orderGoodsDeliveryItemTable . "` WHERE `order_goods_id` = ?",
                [$orderGoodsId]
            );
            foreach ($lines as $idx => $line) {
                Database::insert('order_goods_delivery_item', [
                    'order_goods_id' => $orderGoodsId,
                    'content'        => $line,
                    'sort'           => $idx + 1,
                ]);
            }
        } catch (Throwable $e) {
            // 迁移尚未执行时由调用方兜底报错
            return false;
        }
        return true;
    }

    /**
     * 批量给 order_goods 行补全完整发货内容。
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private static function hydrateDeliveryContent(array &$rows): void
    {
        $ids = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id > 0) $ids[] = $id;
        }
        if ($ids === []) return;

        $map = [];
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $items = Database::query(
                "SELECT `order_goods_id`, `content`
                   FROM `" . self::$orderGoodsDeliveryItemTable . "`
                  WHERE `order_goods_id` IN ({$placeholders})
                  ORDER BY `order_goods_id` ASC, `sort` ASC, `id` ASC",
                $ids
            );
            foreach ($items as $it) {
                $id = (int) ($it['order_goods_id'] ?? 0);
                $line = trim((string) ($it['content'] ?? ''));
                if ($id <= 0 || $line === '') continue;
                $map[$id][] = $line;
            }
        } catch (Throwable $e) {
            return;
        }

        foreach ($rows as &$r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id > 0 && !empty($map[$id])) {
                $r['delivery_content'] = implode("\n", $map[$id]);
            }
        }
        unset($r);
    }

    /**
     * @return string[]
     */
    private static function splitDeliveryLines(string $deliveryContent): array
    {
        $lines = preg_split("/\r\n|\r|\n/", $deliveryContent);
        if (!is_array($lines)) return [];
        $lines = array_values(array_filter(array_map(static fn($v) => trim((string) $v), $lines), static fn($v) => $v !== ''));
        return $lines;
    }

    /**
     * 获取状态显示名称。
     */
    /**
     * 从地址数据（地址簿行或游客手填数组）统一编码为订单快照 JSON 字符串。
     * 只取 6 个标准字段；插件额外字段（身份证等）应走 extra_fields / order_goods.plugin_data。
     *
     * @param array<string, mixed> $data
     */
    private static function buildAddressSnapshot(array $data): string
    {
        return (string) json_encode([
            'recipient' => (string) ($data['recipient'] ?? ''),
            'mobile'    => (string) ($data['mobile']    ?? ''),
            'province'  => (string) ($data['province']  ?? ''),
            'city'      => (string) ($data['city']      ?? ''),
            'district'  => (string) ($data['district']  ?? ''),
            'detail'    => (string) ($data['detail']    ?? ''),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 将附加选项打包为 contact_info（含下单时 title 快照，展示不依赖商品后续改配置）。
     *
     * @param array<int, array<string, mixed>> $definitions 商品 configs.extra_fields
     * @param array<string, string> $valuesByName 字段 name => 用户填写值
     * @return array{extra: list<array{name: string, title: string, value: string}>}
     */
    public static function packExtraContactInfo(array $definitions, array $valuesByName): array
    {
        $items = [];
        $packedNames = [];

        foreach ($definitions as $ef) {
            if (!is_array($ef)) {
                continue;
            }
            $name = (string) ($ef['name'] ?? '');
            if ($name === '' || !array_key_exists($name, $valuesByName)) {
                continue;
            }
            $val = trim((string) $valuesByName[$name]);
            $items[] = [
                'name'  => $name,
                'title' => (string) ($ef['title'] ?? $name),
                'value' => $val,
            ];
            $packedNames[$name] = true;
        }

        foreach ($valuesByName as $name => $val) {
            $name = (string) $name;
            if ($name === '' || isset($packedNames[$name])) {
                continue;
            }
            $items[] = [
                'name'  => $name,
                'title' => $name,
                'value' => trim((string) $val),
            ];
        }

        return ['extra' => $items];
    }

    /**
     * 从订单关联商品配置解析附加字段 name => title（旧订单展示回退用）。
     *
     * @return array<string, string>
     */
    public static function resolveExtraFieldTitleMap(int $orderId): array
    {
        if ($orderId <= 0) {
            return [];
        }
        $prefix = Database::prefix();
        $rows = Database::query(
            "SELECT DISTINCT g.`configs` FROM `{$prefix}order_goods` og
             INNER JOIN `{$prefix}goods` g ON g.`id` = og.`goods_id`
             WHERE og.`order_id` = ?",
            [$orderId]
        );
        $map = [];
        foreach ($rows as $row) {
            $cfg = json_decode((string) ($row['configs'] ?? '{}'), true);
            if (!is_array($cfg)) {
                continue;
            }
            foreach (($cfg['extra_fields'] ?? []) as $ef) {
                if (!is_array($ef)) {
                    continue;
                }
                $name = (string) ($ef['name'] ?? '');
                if ($name !== '') {
                    $map[$name] = (string) ($ef['title'] ?? $name);
                }
            }
        }
        return $map;
    }

    /**
     * 解析订单买家填写信息：游客联系方式、附加选项、订单密码。
     *
     * contact_info 新格式：{"extra":[{"name":"qq","title":"QQ号","value":"111"},...]}
     * 旧格式：{"qq":"111"}（展示时尝试按订单商品配置补中文 title）
     *
     * @param array<string, mixed> $order
     * @return array{guest_contact: string, extra_pairs: array<string, string>, order_password: string}
     */
    public static function parseBuyerContactFields(array $order): array
    {
        $guestContact = trim((string) ($order['guest_contact'] ?? ''));
        $contactRaw = (string) ($order['contact_info'] ?? '');
        $extraPairs = [];
        $orderId = (int) ($order['id'] ?? 0);

        if ($contactRaw !== '' && str_starts_with($contactRaw, '{')) {
            $decoded = json_decode($contactRaw, true);
            if (is_array($decoded)) {
                if ($guestContact === '' && isset($decoded['guest_find_contact'])) {
                    $guestContact = trim((string) $decoded['guest_find_contact']);
                }
                $extraPairs = self::extraPairsFromContactDecoded($decoded, $orderId);
            }
        } elseif ($guestContact === '' && $contactRaw !== '') {
            $guestContact = trim($contactRaw);
        }

        return [
            'guest_contact'  => $guestContact,
            'extra_pairs'    => $extraPairs,
            'order_password' => trim((string) ($order['order_password'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $decoded
     * @return array<string, string> 展示用 label => value
     */
    private static function extraPairsFromContactDecoded(array $decoded, int $orderId): array
    {
        if (isset($decoded['extra']) && is_array($decoded['extra'])) {
            $pairs = [];
            foreach ($decoded['extra'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $title = trim((string) ($item['title'] ?? $item['name'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $value = isset($item['value']) && is_scalar($item['value'])
                    ? (string) $item['value']
                    : '';
                $pairs[self::uniqueExtraDisplayLabel($pairs, $title)] = $value;
            }
            return $pairs;
        }

        $skipKeys = ['guest_find_contact', 'api_attach', 'extra'];
        $titleMap = $orderId > 0 ? self::resolveExtraFieldTitleMap($orderId) : [];
        $pairs = [];
        foreach ($decoded as $k => $v) {
            $name = (string) $k;
            if ($name === '' || in_array($name, $skipKeys, true)) {
                continue;
            }
            $label = $titleMap[$name] ?? $name;
            $pairs[self::uniqueExtraDisplayLabel($pairs, $label)] = is_scalar($v)
                ? (string) $v
                : (string) json_encode($v, JSON_UNESCAPED_UNICODE);
        }
        return $pairs;
    }

    /**
     * @param array<string, string> $existing
     */
    private static function uniqueExtraDisplayLabel(array $existing, string $label): string
    {
        if (!array_key_exists($label, $existing)) {
            return $label;
        }
        $n = 2;
        while (array_key_exists($label . ' (' . $n . ')', $existing)) {
            $n++;
        }
        return $label . ' (' . $n . ')';
    }

    public static function statusName(string $status): string
    {
        $map = [
            'pending'          => '待付款',
            'paid'             => '已付款',
            'delivering'       => '发货中',
            'delivered'        => '已发货',
            'completed'        => '已完成',
            'expired'          => '已过期',
            'cancelled'        => '已取消',
            'delivery_failed'  => '发货失败',
            'refunding'        => '退款中',
            'refunded'         => '已退款',
            'failed'           => '失败',
        ];
        return $map[$status] ?? $status;
    }
}
