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
            self::$paymentTable = $prefix . 'order_payment';
        }
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

                // 价格（BIGINT 格式）：price_raw 是已经应用了 factor（markup × 买家折扣）的成交单价
                $priceBigint = (int) $spec['price_raw'];
                // 主站原价（未应用任何 factor），下面算商户分账 cost 时要用
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
                    '_base_price'   => $basePriceBigint, // 内部字段：主站原价，cost_amount 计算用
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
                if ($buyerId > 0) {
                    // —— 登录用户：走地址簿
                    $addressId = (int) ($orderData['address_id'] ?? 0);
                    if ($addressId <= 0) {
                        throw new RuntimeException('请选择收货地址');
                    }
                    $addr = UserAddressModel::findById($addressId, $buyerId);
                    if ($addr === null) {
                        throw new RuntimeException('收货地址不存在或不属于当前账户');
                    }
                    $addressSnapshot = self::buildAddressSnapshot($addr);
                } else {
                    // —— 游客：下单页手填（整单一套地址，不入地址簿，只进订单快照）
                    $g = $orderData['guest_address'] ?? null;
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
                        // 引用商品：商户拿货成本 = 主站原价 × 数量（不打折，主站对商户始终全价）。
                        // 优惠券 / 买家折扣由主站承担，不影响 cost_amount。
                        $costAmount = (int) $row['_base_price'] * (int) $row['quantity'];
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
            throw new RuntimeException('订单不存在');
        }

        $currentStatus = $order['status'];
        $allowed = self::STATUS_TRANSITIONS[$currentStatus] ?? [];

        if (!in_array($newStatus, $allowed, true)) {
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
        $ok = Database::execute($sql, $params) > 0;

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
                } elseif ($newStatus === 'refunded') {
                    if ($merchantId > 0) {
                        MerchantLedgerService::refundOrder($orderId);
                    } else {
                        RebateService::revertOrder($orderId);
                    }
                }
            } catch (Throwable $e) {
                if (function_exists('log_message')) {
                    log_message('warn', '[ledger] settle/revert fail order=' . $orderId . ' msg=' . $e->getMessage());
                }
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
     * 将每个订单商品写入 em_delivery_queue，由 Swoole 队列消费者异步执行。
     * 不直接调用插件钩子，避免阻塞用户请求。
     */
    public static function triggerDelivery(int $orderId): void
    {
        self::tables();

        // 更新订单状态为发货中
        try {
            self::changeStatus($orderId, 'delivering');
        } catch (Throwable $e) {
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
        if (!empty($og['delivery_content'])) {
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

        $now = date('Y-m-d H:i:s');
        Database::execute(
            "UPDATE {$prefix}order_goods SET delivery_content = ?, delivery_at = ?, plugin_data = ? WHERE id = ?",
            [
                $deliveryContent,
                $now,
                $mergedPluginData ? json_encode($mergedPluginData, JSON_UNESCAPED_UNICODE) : null,
                $orderGoodsId,
            ]
        );

        // 如存在异步回调地址，推送发货结果给下游
        self::notifyDeliveryCallback($orderGoodsId);

        // 检查同订单其他行是否也都已发货，齐了就整单流转 delivered → completed
        $orderId = (int) $og['order_id'];
        $remaining = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM {$prefix}order_goods WHERE order_id = ? AND (delivery_content IS NULL OR delivery_content = '')",
            [$orderId]
        );
        if ((int) ($remaining['cnt'] ?? 0) === 0) {
            // 所有行都发完了 → delivered → completed（changeStatus 会拒绝非法流转，自动跳过）
            try { self::changeStatus($orderId, 'delivered'); } catch (Throwable $e) {}
            try { self::changeStatus($orderId, 'completed'); } catch (Throwable $e) {}
        }
    }

    /**
     * 检查订单所有商品是否已发货完成，如果是则更新订单状态。
     * 由 Swoole 队列消费者在每个任务完成后调用。
     *
     * 逻辑：
     * - 自动发货的商品（delivery_content 非空）才算已发货
     * - 人工发货的商品（delivery_content 为空但队列任务已 success）也算处理完毕
     * - 如果所有商品都有 delivery_content → delivered → completed
     * - 如果有人工发货的商品还没 delivery_content → 只保持 delivering，等管理员手动发货
     */
    public static function checkDeliveryComplete(int $orderId): void
    {
        self::tables();
        $prefix = Database::prefix();

        $orderGoods = self::getOrderGoods($orderId);
        $allAutoDelivered = true;
        $hasManual = false;

        foreach ($orderGoods as $og) {
            if (!empty($og['delivery_content'])) {
                continue; // 已发货
            }
            // 检查队列任务是否已完成（人工发货场景：队列 success 但 delivery_content 为空）
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
            // 忽略
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
            "SELECT og.`id` AS order_goods_id, og.`order_id`, og.`delivery_content`, og.`delivery_at`,
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
        $deliveryContent = (string) ($row['delivery_content'] ?? '');
        if ($callbackUrl === '' || $deliveryContent === '') {
            return;
        }

        $payload = [
            'order_no'        => (string) ($row['order_no'] ?? ''),
            'order_goods_id'  => (int) ($row['order_goods_id'] ?? 0),
            'delivery_content'=> $deliveryContent,
            'delivery_at'     => (string) ($row['delivery_at'] ?? ''),
        ];
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
        return Database::query("SELECT * FROM `" . self::$orderGoodsTable . "` WHERE order_id = ? ORDER BY id", [$orderId]);
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
