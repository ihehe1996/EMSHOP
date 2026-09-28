<?php
/**
Plugin Name: 官方码支付
Version: 1.0.2
Plugin URL: https://m.ynile.cn/
Description: 无需资质 注册即用 收款实时到账 资金无中转
Author: EMSHOP
Author URL:
Category: 支付插件
*/

defined('EM_ROOT') || exit('Access Denied');

if (!defined('MPAY_DEFAULT_SUBMIT_PATH')) {
    define('MPAY_DEFAULT_SUBMIT_PATH', '/submit.php');
}
if (!defined('MPAY_DEFAULT_MAPI_PATH')) {
    define('MPAY_DEFAULT_MAPI_PATH', '/mapi.php');
}
if (!defined('MPAY_DEFAULT_SUBMIT_URL')) {
    define('MPAY_DEFAULT_SUBMIT_URL', 'https://m.ynile.cn/submit.php');
}
if (!defined('MPAY_DEFAULT_MAPI_URL')) {
    define('MPAY_DEFAULT_MAPI_URL', 'https://m.ynile.cn/mapi.php');
}

function mpay_channels(): array
{
    return [
        'mpay_alipay' => [
            'type'         => 'alipay',
            'default_name' => '支付宝',
            'image'        => '/content/plugin/mpay/alipay.png',
        ],
        'mpay_wxpay' => [
            'type'         => 'wxpay',
            'default_name' => '微信支付',
            'image'        => '/content/plugin/mpay/wxpay.png',
        ],
        'mpay_qqpay' => [
            'type'         => 'qqpay',
            'default_name' => 'QQ钱包',
            'image'        => '/content/plugin/mpay/qqpay.png',
        ],
    ];
}

function mpay_storage_value(Storage $storage, array $keys, string $default = ''): string
{
    foreach ($keys as $k) {
        $v = (string) ($storage->getValue($k) ?? '');
        $v = trim($v);
        if ($v !== '') {
            return $v;
        }
    }
    return $default;
}

function mpay_storage_enabled(Storage $storage, array $keys, bool $default = true): bool
{
    foreach ($keys as $k) {
        $raw = $storage->getValue($k);
        if ($raw === null || (string) $raw === '') {
            continue;
        }
        return (string) $raw === '1';
    }
    return $default;
}

function mpay_current_scheme(): string
{
    $forwarded = trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwarded !== '') {
        $parts = explode(',', $forwarded);
        $p = strtolower(trim((string) ($parts[0] ?? '')));
        if ($p === 'http' || $p === 'https') return $p;
    }

    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return 'https';
    }

    return 'http';
}

function mpay_site_url(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }
    return mpay_current_scheme() . '://' . $host;
}

function mpay_normalize_gateway_url(string $raw, string $defaultPath): string
{
    $url = trim($raw);
    if ($url === '') return '';

    if (strpos($url, '//') === 0) {
        $url = mpay_current_scheme() . ':' . $url;
    } elseif (!preg_match('#^https?://#i', $url)) {
        $url = mpay_current_scheme() . '://' . ltrim($url, '/');
    }

    $url = rtrim($url);

    if (preg_match('#/[^/?]+\.php(?:\?.*)?$#i', $url)) {
        return $url;
    }

    return rtrim($url, '/') . $defaultPath;
}

function mpay_client_ip(): string
{
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        $v = trim((string) ($_SERVER[$key] ?? ''));
        if ($v === '') continue;
        if ($key === 'HTTP_X_FORWARDED_FOR') {
            $parts = explode(',', $v);
            $v = trim((string) ($parts[0] ?? ''));
        }
        if ($v !== '') return $v;
    }
    return '127.0.0.1';
}

function mpay_detect_device(): string
{
    $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '') return 'pc';

    if (strpos($ua, 'micromessenger') !== false) return 'wechat';
    if (strpos($ua, 'qq/') !== false || strpos($ua, 'mqqbrowser') !== false) return 'qq';
    if (strpos($ua, 'alipayclient') !== false) return 'alipay';

    $isMobile =
        strpos($ua, 'mobile') !== false ||
        strpos($ua, 'android') !== false ||
        strpos($ua, 'iphone') !== false ||
        strpos($ua, 'ipad') !== false ||
        strpos($ua, 'ipod') !== false ||
        strpos($ua, 'harmonyos') !== false;

    return $isMobile ? 'mobile' : 'pc';
}

function mpay_sign(array $params, string $key): string
{
    unset($params['sign'], $params['sign_type']);

    $filtered = [];
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') continue;
        $filtered[(string) $k] = (string) $v;
    }
    ksort($filtered, SORT_STRING);

    $pairs = [];
    foreach ($filtered as $k => $v) {
        $pairs[] = $k . '=' . $v;
    }

    return md5(implode('&', $pairs) . $key);
}

function mpay_verify_notify_sign(array $data, string $key): bool
{
    $sign = (string) ($data['sign'] ?? '');
    if ($sign === '') return false;
    $expect = mpay_sign($data, $key);
    return hash_equals($expect, $sign);
}

function mpay_base64url_encode(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function mpay_get_channel_config(string $code): ?array
{
    $channels = mpay_channels();
    if (!isset($channels[$code])) {
        return null;
    }

    $ch = $channels[$code];
    $storage = Storage::getInstance('mpay');

    $enabled = mpay_storage_enabled($storage, [$code . '_enabled']);
    if (!$enabled) {
        return null;
    }

    // 网关地址强制硬编码，不允许从配置读取。
    $pid = mpay_storage_value($storage, [$code . '_merchant_id', 'merchant_id']);
    $secret = mpay_storage_value($storage, [$code . '_secret_key', 'secret_key']);
    $submitUrl = MPAY_DEFAULT_SUBMIT_URL;
    $mapiUrl = MPAY_DEFAULT_MAPI_URL;

    if ($pid === '' || $secret === '') {
        return null;
    }
    if ($submitUrl === '' && $mapiUrl === '') {
        return null;
    }

    $mode = strtolower(mpay_storage_value($storage, [$code . '_create_mode', 'create_mode'], 'submit'));
    if (!in_array($mode, ['submit', 'mapi'], true)) {
        $mode = 'submit';
    }

    $displayName = mpay_storage_value($storage, [$code . '_name'], $ch['default_name']);

    return [
        'code'         => $code,
        'type'         => $ch['type'],
        'display_name' => $displayName,
        'pid'          => $pid,
        'secret_key'   => $secret,
        'submit_url'   => $submitUrl,
        'mapi_url'     => $mapiUrl,
        'create_mode'  => $mode,
    ];
}

function mpay_build_subject(string $orderNo): string
{
    if (strncmp($orderNo, 'R', 1) === 0) {
        return '钱包充值 ' . $orderNo;
    }
    return '订单 ' . $orderNo;
}

function mpay_build_trade_params(array $conf, array $order): array
{
    $orderNo = (string) ($order['order_no'] ?? '');
    $amountRaw = (int) ($order['pay_amount'] ?? 0);
    $money = number_format($amountRaw / 1000000, 2, '.', '');

    $site = mpay_site_url();
    if ($site === '') {
        throw new RuntimeException('站点域名识别失败，无法生成回调地址');
    }

    $params = [
        'pid'          => $conf['pid'],
        'type'         => $conf['type'],
        'out_trade_no' => $orderNo,
        'notify_url'   => $site . '/notify',
        'return_url'   => $site . '/return',
        'name'         => mpay_build_subject($orderNo),
        'money'        => $money,
        'sitename'     => Config::get('sitename', 'EMSHOP'),
    ];

    // mapi 通道常用附加字段（submit.php 忽略多余字段，不影响）
    $params['clientip'] = mpay_client_ip();
    $params['device'] = mpay_detect_device();
    $params['param'] = $orderNo;

    $params['sign'] = mpay_sign($params, (string) $conf['secret_key']);
    $params['sign_type'] = 'MD5';

    return $params;
}

function mpay_build_show_url(string $orderNo, array $payload): string
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) $json = '{}';

    $site = mpay_site_url();
    if ($site === '') {
        return '/?plugin=mpay&order_no=' . rawurlencode($orderNo) . '&p=' . rawurlencode(mpay_base64url_encode($json));
    }

    return $site
        . '/?plugin=mpay&order_no=' . rawurlencode($orderNo)
        . '&p=' . rawurlencode(mpay_base64url_encode($json));
}

function mpay_gateway_request_json(string $url, array $params): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'msg' => '服务器未启用 cURL，无法调用码支付接口'];
    }

    $payload = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json,text/plain,*/*',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        ],
        CURLOPT_USERAGENT      => 'EMSHOP-MPayPlugin/2.0',
        CURLOPT_ENCODING       => '',
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $raw === '') {
        return ['ok' => false, 'msg' => '码支付接口请求失败：' . ($err ?: 'empty response')];
    }
    if ($http !== 200) {
        return ['ok' => false, 'msg' => '码支付接口状态异常：HTTP ' . $http];
    }

    $text = trim((string) $raw);
    if (strncmp($text, "\xEF\xBB\xBF", 3) === 0) {
        $text = substr($text, 3);
    }

    $json = json_decode($text, true);
    if (!is_array($json)) {
        $l = strpos($text, '{');
        $r = strrpos($text, '}');
        if ($l !== false && $r !== false && $r > $l) {
            $json = json_decode(substr($text, $l, $r - $l + 1), true);
        }
    }
    if (!is_array($json)) {
        return ['ok' => false, 'msg' => '码支付接口返回非 JSON'];
    }

    return ['ok' => true, 'data' => $json];
}

function mpay_log(string $msg): void
{
    if (function_exists('log_message')) {
        log_message('warn', '[mpay] ' . $msg);
    }
}

addFilter('payment_methods_register', function (array $methods): array {
    foreach (mpay_channels() as $code => $ch) {
        $conf = mpay_get_channel_config($code);
        if ($conf === null) {
            continue;
        }

        $methods[] = [
            'code'         => $code,
            'name'         => $ch['default_name'],
            'display_name' => $conf['display_name'],
            'image'        => $ch['image'],
            'channel'      => $ch['type'],
            'plugin'       => 'mpay',
            'plugin_name'  => '码支付',
        ];
    }

    return $methods;
});

addFilter('payment_create', function (array $ctx): array {
    if ((string) ($ctx['payment']['plugin'] ?? '') !== 'mpay') {
        return $ctx;
    }

    $order = $ctx['order'] ?? [];
    $orderNo = (string) ($order['order_no'] ?? '');
    if ($orderNo === '') {
        return $ctx;
    }

    $conf = mpay_get_channel_config((string) ($ctx['payment']['code'] ?? ''));
    if ($conf === null) {
        throw new RuntimeException('码支付渠道未启用或配置不完整');
    }

    $params = mpay_build_trade_params($conf, $order);


    $useMapi = $conf['create_mode'] === 'mapi' && $conf['mapi_url'] !== '';
    if ($useMapi) {
        $res = mpay_gateway_request_json((string) $conf['mapi_url'], $params);
        
        if (($res['ok'] ?? false) !== true) {
            throw new RuntimeException((string) ($res['msg'] ?? '码支付接口请求失败'));
        }

        $ret = (array) ($res['data'] ?? []);
        $codeVal = (string) ($ret['code'] ?? '');
        if ($codeVal !== '1') {
            $msg = (string) ($ret['msg'] ?? '下单失败');
            throw new RuntimeException('码支付下单失败：' . $msg);
        }

        $payUrl = trim((string) ($ret['payurl'] ?? ''));
        $qr = trim((string) ($ret['qrcode'] ?? ''));
        $scheme = trim((string) ($ret['urlscheme'] ?? ''));
        $tradeNo = trim((string) ($ret['trade_no'] ?? ''));

        // mapi 模式统一先进入本站插件收银页，避免直接跳第三方站点。
        if ($payUrl !== '' || $qr !== '' || $scheme !== '') {
            $ctx['pay_url'] = mpay_build_show_url($orderNo, [
                'payurl'    => $payUrl,
                'qrcode'    => $qr,
                'urlscheme' => $scheme,
                'trade_no'  => $tradeNo,
            ]);
            $ctx['qrcode'] = $ctx['pay_url'];
            return $ctx;
        }

        mpay_log('mapi success but no pay url: ' . json_encode($ret, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        throw new RuntimeException('码支付下单失败：接口未返回可用支付地址');
    }

    if ($conf['submit_url'] === '') {
        throw new RuntimeException('码支付未配置页面跳转地址 submit_url');
    }

    // submit 模式也先进入本站插件页，再由前台页面用 HTML form POST 提交到码支付网关。
    $ctx['pay_url'] = mpay_build_show_url($orderNo, [
        'submit_target' => (string) $conf['submit_url'],
        'submit_fields' => $params,
    ]);
    return $ctx;
});

addAction('payment_notify_mpay', function (array $data): void {
    $orderNo = trim((string) ($data['out_trade_no'] ?? ''));
    if ($orderNo === '') {
        echo 'fail';
        exit;
    }

    $isRecharge = strncmp($orderNo, 'R', 1) === 0;
    $tradeAmount = 0;
    $paymentCode = '';

    if ($isRecharge) {
        $recharge = (new UserRechargeModel())->findByOrderNo($orderNo);
        if (!$recharge) {
            echo 'fail';
            exit;
        }

        if ((string) ($recharge['payment_plugin'] ?? '') !== 'mpay') {
            echo 'fail';
            exit;
        }

        // 幂等短路：已支付 = 网关重复投递，按成功处理
        if ((string) ($recharge['status'] ?? '') === UserRechargeModel::STATUS_PAID) {
            echo 'success';
            exit;
        }
        // 非 pending 且未支付（如 cancelled）：客户已经付了钱，但充值单不在可入账状态。
        // 不能静默当成功 —— 那会让网关停止重投、款项被吞掉且余额永不入账。
        // 回 fail 让网关继续重投并留下告警，由人工对账。
        if ((string) ($recharge['status'] ?? '') !== UserRechargeModel::STATUS_PENDING) {
            mpay_log('充值单 #' . (int) $recharge['id'] . ' 当前状态 ' . ($recharge['status'] ?? '')
                . '，但收到支付成功通知，需人工对账');
            echo 'fail';
            exit;
        }

        $paymentCode = (string) ($recharge['payment_code'] ?? '');
        $tradeAmount = (int) ($recharge['amount'] ?? 0);
    } else {
        $order = OrderModel::getByOrderNo($orderNo);
        if (!$order) {
            echo 'fail';
            exit;
        }

        if ((string) ($order['payment_plugin'] ?? '') !== 'mpay') {
            echo 'fail';
            exit;
        }

        // 幂等短路：已支付 = 网关重复投递，直接按成功回应。
        if ((string) ($order['status'] ?? '') === 'paid') {
            echo 'success';
            exit;
        }
        // 非 pending 且未支付（expired / cancelled / failed / refunded 等）：客户已经付了钱，
        // 但订单不在可入账状态。**不能**静默当成功 —— 那会让网关停止重投，款项被吞掉。
        // 回 fail 让网关继续重投并留下告警，由人工对账。
        if ((string) ($order['status'] ?? '') !== 'pending') {
            mpay_log('订单 ' . ($order['order_no'] ?? '') . ' 当前状态 ' . ($order['status'] ?? '')
                . '，但收到支付成功通知，需人工对账');
            echo 'fail';
            exit;
        }

        $paymentCode = (string) ($order['payment_code'] ?? '');
        $tradeAmount = (int) ($order['pay_amount'] ?? 0);
    }

    $conf = mpay_get_channel_config($paymentCode);
    if ($conf === null) {
        echo 'fail';
        exit;
    }

    if ((string) ($data['pid'] ?? '') !== (string) $conf['pid']) {
        echo 'fail';
        exit;
    }

    if (!mpay_verify_notify_sign($data, (string) $conf['secret_key'])) {
        echo 'fail';
        exit;
    }

    if ((string) ($data['trade_status'] ?? '') !== 'TRADE_SUCCESS') {
        echo 'fail';
        exit;
    }

    $notifyMoney = number_format((float) ($data['money'] ?? 0), 2, '.', '');
    $expectMoney = number_format($tradeAmount / 1000000, 2, '.', '');
    if ($notifyMoney !== $expectMoney) {
        echo 'fail';
        exit;
    }

    $tradeNo = (string) ($data['trade_no'] ?? '');

    if ($isRecharge) {
        $ok = (new UserRechargeModel())->markPaid((int) $recharge['id'], $tradeNo);
        echo $ok ? 'success' : 'fail';
        exit;
    }

    try {
        Database::begin();

        OrderModel::changeStatus((int) $order['id'], 'paid');

        Database::execute(
            'INSERT INTO `' . Database::prefix() . 'order_payment`
             (order_id, payment_code, payment_plugin, trade_no, amount, status, paid_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                (int) $order['id'],
                (string) ($order['payment_code'] ?? ''),
                'mpay',
                $tradeNo,
                (int) $order['pay_amount'],
                'success',
            ]
        );

        Database::commit();
    } catch (Throwable $e) {
        Database::rollBack();
        mpay_log('notify db error: ' . $e->getMessage());
        echo 'fail';
        exit;
    }

    try {
        OrderModel::triggerDelivery((int) $order['id']);
    } catch (Throwable $e) {
        mpay_log('trigger delivery failed: ' . $e->getMessage());
    }

    echo 'success';
    exit;
});

addAction('payment_return_mpay', function (array $data): void {
    $orderNo = (string) ($data['out_trade_no'] ?? '');
    if ($orderNo !== '' && strncmp($orderNo, 'R', 1) === 0) {
        Response::redirect('/user/wallet.php');
    }

    Response::redirect(PaymentCallbackController::buildReturnRedirectUrl($orderNo));
});
