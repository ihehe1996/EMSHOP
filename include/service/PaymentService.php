<?php

declare(strict_types=1);

/**
 * 支付服务。
 *
 * 钩子链：
 * 1. payment_methods_register — 各支付插件注册自己的支付方式
 * 2. payment_methods          — 对最终的支付方式列表做二次处理（排序、过滤、修改等）
 *
 * 支付策略（v1.3+）：
 *   全站统一由主站收款。商户站访问时也只展示主站启用的支付插件，钱进主站老板的账户；
 *   商户卖出商品的收益走分账逻辑（订单完成后写 em_merchant_balance_log，再由提现到 user.money）。
 *   商户后台不再提供独立收款配置入口。
 *
 *   实现：永远从 em_plugin 里 scope='main' AND category='支付插件' AND is_enabled=1 的行
 *   按需 include 主文件、临时把 __em_current_scope 切到 'main' 触发钩子，再恢复 scope。
 */
class PaymentService
{
    /**
     * 获取所有已启用的支付方式（已排序）。
     *
     * @return array<array{code:string, name:string, image:string}>
     */
    public static function getMethods(): array
    {
        $methods = self::collectMainPluginMethods();

        // 余额支付（内置，受后台开关控制，放在最后）
        $balanceEnabled = Config::get('shop_balance_enabled', '1');
        if ($balanceEnabled === '1') {
            // 登录用户：在显示名后拼当前余额，方便用户一眼看到够不够付
            $displayName = '余额支付';
            if (session_status() === PHP_SESSION_NONE) session_start();
            $fu = $_SESSION['em_front_user'] ?? null;
            if (is_array($fu) && isset($fu['money'])) {
                $bal = bcdiv((string) $fu['money'], '1000000', 2);
                $cur = Currency::getInstance()->getPrimary();
                $sym = $cur ? ($cur['symbol'] ?? '¥') : '¥';
                $displayName = '余额支付 ' . $sym . $bal;
            }
            $methods[] = [
                'code'         => 'balance',
                'name'         => '余额支付',
                'display_name' => $displayName,
                'image'        => '/content/static/img/balance.png',
                'channel'      => 'balance',
                'plugin'       => 'built-in',
                'plugin_name'  => '内置',
            ];
        }

        // payment_methods 过滤器仍按当前 scope 跑（让商户安装的"排序插件"等仍能起作用）
        $methods = applyFilter('payment_methods', $methods);

        return $methods;
    }

    /**
     * 触发 `payment_create` 过滤器，用主站 scope 跑插件钩子，返回支付创建元数据。
     *
     * 返回字段：
     * - pay_url：前端当前可直接跳转的支付地址（兼容既有逻辑）
     * - qrcode：二维码收银页地址（仅插件有返回时存在，如 mapi / 当面付场景）
     *
     * @param array<string, mixed> $order   订单行（OrderModel::getById 的结果，或充值"伪订单"）
     * @param array<string, mixed> $payment 支付方式行（来自 PaymentService::getMethods）
     * @return array{pay_url:string,qrcode:string}
     */
    public static function createPaymentPayload(array $order, array $payment): array
    {
        $ctx = self::runUnderMainScope(static function () use ($order, $payment) {
            return applyFilter('payment_create', [
                'order'       => $order,
                'payment'     => $payment,
                'pay_url'     => '',
                'qrcode'      => '',
            ]);
        });
        if (!is_array($ctx)) {
            $ctx = [];
        }
        return [
            'pay_url' => (string) ($ctx['pay_url'] ?? ''),
            // 兼容旧插件仍返回 qrcode_page 的情况
            'qrcode' => (string) (($ctx['qrcode'] ?? '') !== '' ? $ctx['qrcode'] : ($ctx['qrcode_page'] ?? '')),
        ];
    }

    /**
     * 兼容旧调用：仅返回 pay_url。
     *
     * @param array<string, mixed> $order
     * @param array<string, mixed> $payment
     */
    public static function createPayment(array $order, array $payment): string
    {
        $payload = self::createPaymentPayload($order, $payment);
        return $payload['pay_url'];
    }

    /**
     * 主站 scope 下分发支付异步通知（/notify 用）。
     *
     * 插件回调走的是访问发起方的同域（商户站发起 → 回调打商户子域），但插件内部的存储 / 凭证
     * 都在主站 scope，必须切 scope 才能让 sig 校验、订单更新等逻辑读到正确凭证。
     *
     * @param array<string, mixed> $data 合并 GET+POST 后的回调入参
     */
    public static function dispatchNotify(string $plugin, array $data): void
    {
        self::runUnderMainScope(static function () use ($plugin, $data) {
            doAction('payment_notify_' . $plugin, $data);
            return null;
        });
    }

    /**
     * 主站 scope 下分发支付同步跳回（/return 用）。同 dispatchNotify。
     *
     * @param array<string, mixed> $data 合并 GET+POST 后的回调入参
     */
    public static function dispatchReturn(string $plugin, array $data): void
    {
        self::runUnderMainScope(static function () use ($plugin, $data) {
            doAction('payment_return_' . $plugin, $data);
            return null;
        });
    }

    /**
     * 在回调 URL 未携带 plugin 参数时，根据订单号反查对应支付插件。
     *
     * 适配场景：
     * - 部分支付渠道会忽略 notify_url / return_url 的 query 参数
     * - 回调仍会带 out_trade_no/order_no，可据此反查订单支付插件
     *
     * @param array<string, mixed> $data 回调参数（GET+POST）
     */
    public static function detectPluginFromCallback(array $data): string
    {
        $orderNo = self::extractCallbackOrderNo($data);
        if ($orderNo === '') {
            return '';
        }

        // 充值订单号约定以 R 开头，走 user_recharge
        if (strncmp($orderNo, 'R', 1) === 0) {
            $recharge = (new UserRechargeModel())->findByOrderNo($orderNo);
            $plugin = trim((string) ($recharge['payment_plugin'] ?? ''));
            return preg_match('/^[a-zA-Z0-9_\-]+$/', $plugin) ? $plugin : '';
        }

        // 普通商品订单走 order 表
        $order = OrderModel::getByOrderNo($orderNo);
        $plugin = trim((string) ($order['payment_plugin'] ?? ''));
        return preg_match('/^[a-zA-Z0-9_\-]+$/', $plugin) ? $plugin : '';
    }

    /**
     * 从回调参数里提取订单号。
     *
     * @param array<string, mixed> $data
     */
    private static function extractCallbackOrderNo(array $data): string
    {
        foreach (['out_trade_no', 'order_no', 'merchant_order_no'] as $key) {
            $val = trim((string) ($data[$key] ?? ''));
            if ($val !== '') {
                return $val;
            }
        }
        return '';
    }

    /**
     * 公共的 scope 切换包装：把 $GLOBALS['__em_current_scope'] 临时设为 'main' 跑闭包，
     * finally 恢复原值（即便闭包抛异常也能恢复）。
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    private static function runUnderMainScope(callable $fn)
    {
        $savedScope = $GLOBALS['__em_current_scope'] ?? null;
        $GLOBALS['__em_current_scope'] = 'main';
        try {
            return $fn();
        } finally {
            if ($savedScope === null) {
                unset($GLOBALS['__em_current_scope']);
            } else {
                $GLOBALS['__em_current_scope'] = $savedScope;
            }
        }
    }

    /**
     * 收集主站启用的支付插件注册的支付方式（不含余额支付）。
     *
     * 实现思路：
     *   1. 从 em_plugin 读 scope='main' 且 category=支付插件 且 is_enabled=1 的插件名单
     *   2. include_once 每个插件主文件（幂等，重复加载是 no-op）
     *   3. 临时把 $GLOBALS['__em_current_scope'] 切到 'main'，让插件钩子里的
     *      Storage::getInstance() 读到主站存储；触发 payment_methods_register 过滤器
     *   4. 用 em_plugin 主站启用名单做白名单过滤（钩子注册是全局的；同一个 epay.php 若曾被
     *      其它 scope 加载过，回调依然会 fire，要靠 enabledNames 卡掉）
     *   5. 恢复 scope
     *
     * @return array<int, array<string, mixed>>
     */
    private static function collectMainPluginMethods(): array
    {
        $pm = new PluginModel();
        $rows = $pm->getEnabledByCategory('支付插件', 'main');
        if ($rows === []) {
            return [];
        }

        // 1. 加载主文件（幂等）
        foreach ($rows as $r) {
            $file = EM_ROOT . '/content/plugin/' . $r['name'] . '/' . $r['main_file'];
            if (is_file($file)) {
                include_once $file;
            }
        }

        // 2. 切 scope，跑钩子，再恢复
        $enabledNames = array_column($rows, 'name');
        $savedScope = $GLOBALS['__em_current_scope'] ?? null;
        $GLOBALS['__em_current_scope'] = 'main';
        try {
            $methods = applyFilter('payment_methods_register', []);
        } finally {
            if ($savedScope === null) {
                unset($GLOBALS['__em_current_scope']);
            } else {
                $GLOBALS['__em_current_scope'] = $savedScope;
            }
        }

        // 3. 白名单过滤：只保留主站启用的支付插件注册的方式
        $methods = array_values(array_filter($methods, static function (array $m) use ($enabledNames) {
            return in_array($m['plugin'] ?? '', $enabledNames, true);
        }));

        return $methods;
    }
}
