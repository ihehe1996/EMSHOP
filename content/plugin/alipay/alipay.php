<?php
/**
Plugin Name: 支付宝支付
Version: 1.0.0
Plugin URL:
Description: 官方支付宝在线支付插件，支持当面付、电脑端支付、手机端支付。
Author: EMSHOP
Author URL:
Category: 支付插件
*/

defined('EM_ROOT') || exit('Access Denied');

if (!defined('ALIPAY_PLUGIN_GATEWAY_URL')) {
    define('ALIPAY_PLUGIN_GATEWAY_URL', 'https://openapi.alipay.com/gateway.do');
}

function alipay_get_mode_value($raw, bool $defaultEnabled): string
{
    if ($raw === null || (string) $raw === '') {
        return $defaultEnabled ? '1' : '0';
    }
    return ((string) $raw === '1') ? '1' : '0';
}

function alipay_get_config(): array
{
    $storage = Storage::getInstance('alipay');
    return [
        'display_name'       => (string) ($storage->getValue('display_name') ?: ''),
        'app_id'             => trim((string) ($storage->getValue('app_id') ?: '')),
        'alipay_public_key'  => trim((string) ($storage->getValue('alipay_public_key') ?: '')),
        'app_private_key'    => trim((string) ($storage->getValue('app_private_key') ?: '')),
        'mode_web'           => alipay_get_mode_value($storage->getValue('mode_web'), false),
        'mode_wap'           => alipay_get_mode_value($storage->getValue('mode_wap'), false),
        'mode_face'          => alipay_get_mode_value($storage->getValue('mode_face'), false),
    ];
}

function alipay_has_basic_config(array $cfg): bool
{
    return $cfg['app_id'] !== ''
        && $cfg['alipay_public_key'] !== ''
        && $cfg['app_private_key'] !== '';
}

function alipay_is_mode_enabled(array $cfg, string $mode): bool
{
    $key = 'mode_' . $mode;
    return isset($cfg[$key]) && (string) $cfg[$key] === '1';
}

function alipay_is_mobile_scene(): bool
{
    $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '') {
        return false;
    }

    $keywords = ['mobile', 'android', 'iphone', 'ipad', 'ipod', 'mobi', 'harmonyos', 'micromessenger'];
    foreach ($keywords as $k) {
        if (strpos($ua, $k) !== false) {
            return true;
        }
    }
    return false;
}

function alipay_scene_candidates(bool $isMobile): array
{
    return $isMobile ? ['wap', 'face'] : ['web', 'face'];
}

function alipay_scene_has_available_mode(array $cfg, bool $isMobile): bool
{
    foreach (alipay_scene_candidates($isMobile) as $mode) {
        if (alipay_is_mode_enabled($cfg, $mode)) {
            return true;
        }
    }
    return false;
}

function alipay_site_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    return $scheme . '://' . $host;
}

function alipay_order_subject(string $orderNo): string
{
    if (strncmp($orderNo, 'R', 1) === 0) {
        return '钱包充值 ' . $orderNo;
    }
    return '订单 ' . $orderNo;
}

function alipay_clean_key_text(string $rawKey): string
{
    $key = str_replace(['\\r\\n', '\\n', '\\r'], "\n", $rawKey);
    $key = trim(str_replace(["\r\n", "\r"], "\n", $key));
    $key = trim($key, "\"' \t\n\r\0\x0B");
    return $key;
}

function alipay_normalize_key(string $rawKey, bool $private): string
{
    $key = alipay_clean_key_text($rawKey);
    if ($key === '') {
        return '';
    }

    if (strpos($key, 'BEGIN') !== false) {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $key)), static fn($line) => $line !== ''));
        return implode("\n", $lines);
    }

    $compact = preg_replace('/\s+/', '', $key) ?: '';
    if ($compact === '') {
        return '';
    }

    $header = $private ? '-----BEGIN PRIVATE KEY-----' : '-----BEGIN PUBLIC KEY-----';
    $footer = $private ? '-----END PRIVATE KEY-----' : '-----END PUBLIC KEY-----';

    return $header . "\n" . trim(chunk_split($compact, 64, "\n")) . "\n" . $footer;
}

function alipay_private_key_candidates(string $rawKey): array
{
    $key = alipay_clean_key_text($rawKey);
    if ($key === '') {
        return [];
    }

    // 已带 PEM 头时优先按原样尝试。
    if (strpos($key, 'BEGIN') !== false) {
        return [alipay_normalize_key($key, true)];
    }

    $compact = preg_replace('/\s+/', '', $key) ?: '';
    if ($compact === '') {
        return [];
    }

    return [
        "-----BEGIN PRIVATE KEY-----\n" . trim(chunk_split($compact, 64, "\n")) . "\n-----END PRIVATE KEY-----",
        "-----BEGIN RSA PRIVATE KEY-----\n" . trim(chunk_split($compact, 64, "\n")) . "\n-----END RSA PRIVATE KEY-----",
    ];
}

function alipay_public_key_candidates(string $rawKey): array
{
    $key = alipay_clean_key_text($rawKey);
    if ($key === '') {
        return [];
    }

    if (strpos($key, 'BEGIN') !== false) {
        return [alipay_normalize_key($key, false)];
    }

    $compact = preg_replace('/\s+/', '', $key) ?: '';
    if ($compact === '') {
        return [];
    }

    return [
        "-----BEGIN PUBLIC KEY-----\n" . trim(chunk_split($compact, 64, "\n")) . "\n-----END PUBLIC KEY-----",
        "-----BEGIN RSA PUBLIC KEY-----\n" . trim(chunk_split($compact, 64, "\n")) . "\n-----END RSA PUBLIC KEY-----",
    ];
}

function alipay_load_private_key(string $rawKey)
{
    foreach (alipay_private_key_candidates($rawKey) as $candidate) {
        $res = openssl_pkey_get_private($candidate);
        if ($res !== false) {
            return $res;
        }
    }
    return false;
}

function alipay_load_public_key(string $rawKey)
{
    foreach (alipay_public_key_candidates($rawKey) as $candidate) {
        $res = openssl_pkey_get_public($candidate);
        if ($res !== false) {
            return $res;
        }
    }
    return false;
}


function alipay_build_sign_content(array $params, bool $excludeSignType): string
{
    unset($params['sign']);
    unset($params['plugin']);
    if ($excludeSignType) {
        unset($params['sign_type']);
    }

    $filtered = [];
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') {
            continue;
        }
        $filtered[(string) $k] = (string) $v;
    }

    ksort($filtered, SORT_STRING);

    $pairs = [];
    foreach ($filtered as $k => $v) {
        $pairs[] = $k . '=' . $v;
    }

    return implode('&', $pairs);
}

function alipay_sign(array $params, string $privateKeyRaw): string
{
    $content = alipay_build_sign_content($params, false);
    if ($content === '') {
        return '';
    }

    $res = alipay_load_private_key($privateKeyRaw);
    if ($res === false) {
        return '';
    }

    $signature = '';
    $ok = openssl_sign($content, $signature, $res, OPENSSL_ALGO_SHA256);
    if (!$ok) {
        return '';
    }

    return base64_encode($signature);
}

function alipay_verify(array $params, string $sign, string $publicKeyRaw): bool
{
    $content = alipay_build_sign_content($params, true);
    if ($content === '' || $sign === '') {
        return false;
    }

    $res = alipay_load_public_key($publicKeyRaw);
    if ($res === false) {
        return false;
    }

    $decodedSign = base64_decode($sign, true);
    if ($decodedSign === false) {
        return false;
    }

    return openssl_verify($content, $decodedSign, $res, OPENSSL_ALGO_SHA256) === 1;
}

function alipay_build_request_params(array $cfg, string $method, array $bizContent, bool $withReturnUrl = true): array
{
    if ((string) ($cfg['app_id'] ?? '') === '') {
        throw new RuntimeException('APPID 未配置');
    }

    $site = alipay_site_url();

    $params = [
        'app_id'      => $cfg['app_id'],
        'method'      => $method,
        'format'      => 'JSON',
        'charset'     => 'utf-8',
        'sign_type'   => 'RSA2',
        'timestamp'   => date('Y-m-d H:i:s'),
        'version'     => '1.0',
        // 官方支付宝在部分场景会忽略 query 参数，回调 URL 使用纯路径更稳妥。
        // 插件识别由 /notify 按 out_trade_no 反查 payment_plugin 完成。
        'notify_url'  => $site . '/notify',
        'biz_content' => json_encode($bizContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];

    if ($withReturnUrl) {
        $params['return_url'] = $site . '/return';
    }

    $sign = alipay_sign($params, $cfg['app_private_key']);
    if ($sign === '') {
        throw new RuntimeException('支付宝请求签名失败，请检查密钥配置');
    }

    $params['sign'] = $sign;
    return $params;
}

function alipay_build_gateway_url(array $params): string
{
    return ALIPAY_PLUGIN_GATEWAY_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function alipay_base64url_encode(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function alipay_post_gateway(array $params): array
{
    $payload = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'msg' => '服务器未启用 cURL，无法调用支付宝接口'];
    }

    $request = static function (bool $post) use ($payload): array {
        $url = ALIPAY_PLUGIN_GATEWAY_URL;
        if (!$post) {
            $url .= '?' . $payload;
        }

        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json,text/plain,*/*',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            ],
            CURLOPT_USERAGENT => 'EMSHOP-AlipayPlugin/1.0',
            CURLOPT_ENCODING => '',
        ];
        if ($post) {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $payload;
        } else {
            $opts[CURLOPT_HTTPGET] = true;
        }

        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['response' => $response, 'error' => $error, 'http_code' => $httpCode];
    };

    $decodeJson = static function (string $raw): ?array {
        $text = trim($raw);
        if ($text === '') return null;

        // 去掉 UTF-8 BOM，避免 json_decode 失败
        if (strncmp($text, "\xEF\xBB\xBF", 3) === 0) {
            $text = substr($text, 3);
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) return $decoded;

        // 兼容返回体里夹杂了前后文本（极少数网关/代理会注入）
        $l = strpos($text, '{');
        $r = strrpos($text, '}');
        if ($l !== false && $r !== false && $r > $l) {
            $maybe = substr($text, $l, $r - $l + 1);
            $decoded2 = json_decode($maybe, true);
            if (is_array($decoded2)) return $decoded2;
        }
        return null;
    };

    $snippet = static function (string $raw): string {
        $s = trim(preg_replace('/\s+/', ' ', $raw) ?? '');
        if ($s === '') return '';
        return function_exists('mb_substr')
            ? mb_substr($s, 0, 180, 'UTF-8')
            : substr($s, 0, 180);
    };

    // 先按官方 SDK 默认的 POST 请求；非 JSON 时再降级尝试 GET（兼容部分代理环境）
    $first = $request(true);
    $response = $first['response'];
    $httpCode = (int) ($first['http_code'] ?? 0);
    $error = (string) ($first['error'] ?? '');
    if ($response === false || $response === '') {
        return ['ok' => false, 'msg' => '支付宝接口请求失败：' . ($error ?: 'empty response')];
    }
    if ($httpCode !== 200) {
        return ['ok' => false, 'msg' => '支付宝接口状态异常：HTTP ' . $httpCode];
    }

    $decoded = $decodeJson((string) $response);
    if (!is_array($decoded)) {
        $second = $request(false);
        $response2 = $second['response'];
        $httpCode2 = (int) ($second['http_code'] ?? 0);
        if ($response2 !== false && $response2 !== '' && $httpCode2 === 200) {
            $decoded2 = $decodeJson((string) $response2);
            if (is_array($decoded2)) {
                $decoded = $decoded2;
            }
        }
    }
    if (!is_array($decoded)) {
        $raw = (string) $response;
        alipay_log('gateway non-json response, http=' . $httpCode . ', body=' . $snippet($raw));
        return ['ok' => false, 'msg' => '支付宝接口返回非 JSON（请检查服务器网络/网关访问）'];
    }

    $responseKey = str_replace('.', '_', (string) ($params['method'] ?? '')) . '_response';
    $bizResp = $decoded[$responseKey] ?? null;
    if (!is_array($bizResp)) {
        return ['ok' => false, 'msg' => '支付宝接口响应结构异常'];
    }

    $code = (string) ($bizResp['code'] ?? '');
    if ($code !== '10000') {
        $msg = (string) ($bizResp['sub_msg'] ?? $bizResp['msg'] ?? '支付宝下单失败');
        return ['ok' => false, 'msg' => $msg];
    }

    return ['ok' => true, 'data' => $bizResp];
}

function alipay_create_face_pay_url(array $cfg, string $orderNo, string $amount, string $subject): string
{
    $params = alipay_build_request_params($cfg, 'alipay.trade.precreate', [
        'out_trade_no'   => $orderNo,
        'total_amount'   => $amount,
        'subject'        => $subject,
        'timeout_express'=> '30m',
    ], false);

    $result = alipay_post_gateway($params);
    if (($result['ok'] ?? false) !== true) {
        throw new RuntimeException((string) ($result['msg'] ?? '支付宝当面付下单失败'));
    }

    $qrCode = trim((string) (($result['data']['qr_code'] ?? '')));
    if ($qrCode === '') {
        throw new RuntimeException('支付宝当面付下单失败：未返回二维码链接');
    }

    return $qrCode;
}

function alipay_log(string $msg): void
{
    if (function_exists('log_message')) {
        log_message('warn', '[alipay] ' . $msg);
    }
}

// 注册支付方式：按当前场景过滤，当前场景没有可用通道就不注册。
addFilter('payment_methods_register', function (array $methods): array {
    $cfg = alipay_get_config();
    $isMobile = alipay_is_mobile_scene();
    if (!alipay_scene_has_available_mode($cfg, $isMobile)) {
        return $methods;
    }

    $displayName = $cfg['display_name'] !== '' ? $cfg['display_name'] : '支付宝';

    $methods[] = [
        'code'         => 'alipay',
        'name'         => '支付宝',
        'display_name' => $displayName,
        'image'        => '/content/plugin/alipay/alipay.png',
        'channel'      => 'alipay',
        'plugin'       => 'alipay',
        'plugin_name'  => '支付宝支付',
    ];

    return $methods;
});

// 支付创建：按场景自动选择通道。
addFilter('payment_create', function (array $ctx): array {
    if ((string) ($ctx['payment']['plugin'] ?? '') !== 'alipay') {
        return $ctx;
    }

    $cfg = alipay_get_config();
    $order = $ctx['order'] ?? [];
    $orderNo = (string) ($order['order_no'] ?? '');
    if ($orderNo === '') {
        return $ctx;
    }

    $amountRaw = (int) ($order['pay_amount'] ?? 0);
    if ($amountRaw <= 0) {
        return $ctx;
    }

    $amount = number_format($amountRaw / 1000000, 2, '.', '');
    $subject = alipay_order_subject($orderNo);

    $candidates = alipay_scene_candidates(alipay_is_mobile_scene());

    $attempted = false;
    $errors = [];

    foreach ($candidates as $mode) {
        if (!alipay_is_mode_enabled($cfg, $mode)) {
            continue;
        }
        $attempted = true;

        if ($mode === 'web') {
            try {
                $params = alipay_build_request_params($cfg, 'alipay.trade.page.pay', [
                    'out_trade_no'    => $orderNo,
                    'product_code'    => 'FAST_INSTANT_TRADE_PAY',
                    'total_amount'    => $amount,
                    'subject'         => $subject,
                    'timeout_express' => '30m',
                ]);
                $ctx['pay_url'] = alipay_build_gateway_url($params);
                return $ctx;
            } catch (Throwable $e) {
                $errors[] = '电脑端支付：' . $e->getMessage();
            }
            continue;
        }

        if ($mode === 'wap') {
            try {
                $params = alipay_build_request_params($cfg, 'alipay.trade.wap.pay', [
                    'out_trade_no'    => $orderNo,
                    'total_amount'    => $amount,
                    'subject'         => $subject,
                    'product_code'    => 'QUICK_WAP_WAY',
                    'timeout_express' => '30m',
                ]);
                $ctx['pay_url'] = alipay_build_gateway_url($params);
                return $ctx;
            } catch (Throwable $e) {
                $errors[] = '手机端支付：' . $e->getMessage();
            }
            continue;
        }

        if ($mode === 'face') {
            try {
                $url = alipay_create_face_pay_url($cfg, $orderNo, $amount, $subject);
                $ctx['pay_url'] = alipay_site_url()
                    . '/?plugin=alipay&order_no=' . rawurlencode($orderNo)
                    . '&q=' . rawurlencode(alipay_base64url_encode($url));
                $ctx['qrcode'] = $ctx['pay_url'];
                return $ctx;
            } catch (Throwable $e) {
                $errors[] = '当面付：' . $e->getMessage();
            }
        }
    }

    if (!$attempted) {
        throw new RuntimeException('当前场景未启用可用的支付宝支付方式');
    }

    if ($errors !== []) {
        throw new RuntimeException('支付宝创建支付失败：' . implode('；', $errors));
    }

    throw new RuntimeException('支付宝创建支付失败，请检查配置后重试');
});

// 异步回调：/notify（核心按 out_trade_no 自动识别 alipay）
addAction('payment_notify_alipay', function (array $data): void {
    $cfg = alipay_get_config();
    if (!alipay_has_basic_config($cfg)) {
        echo 'fail';
        exit;
    }

    $sign = (string) ($data['sign'] ?? '');
    if (!alipay_verify($data, $sign, $cfg['alipay_public_key'])) {
        echo 'fail';
        exit;
    }

    $appId = (string) ($data['app_id'] ?? '');
    if ($appId !== '' && $appId !== $cfg['app_id']) {
        echo 'fail';
        exit;
    }

    $orderNo = (string) ($data['out_trade_no'] ?? '');
    if ($orderNo === '') {
        echo 'fail';
        exit;
    }

    $tradeStatus = (string) ($data['trade_status'] ?? '');
    if (!in_array($tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED'], true)) {
        echo 'fail';
        exit;
    }

    $tradeNo = (string) ($data['trade_no'] ?? '');
    $totalAmount = isset($data['total_amount']) ? number_format((float) $data['total_amount'], 2, '.', '') : '';

    $isRecharge = strncmp($orderNo, 'R', 1) === 0;

    if ($isRecharge) {
        $recharge = (new UserRechargeModel())->findByOrderNo($orderNo);
        if (!$recharge) {
            echo 'fail';
            exit;
        }
        if ((string) ($recharge['payment_plugin'] ?? '') !== 'alipay') {
            echo 'fail';
            exit;
        }

        if ((string) $recharge['status'] !== UserRechargeModel::STATUS_PENDING) {
            echo 'success';
            exit;
        }

        $expected = number_format(((int) $recharge['amount']) / 1000000, 2, '.', '');
        if ($totalAmount !== '' && $totalAmount !== $expected) {
            echo 'fail';
            exit;
        }

        $ok = (new UserRechargeModel())->markPaid((int) $recharge['id'], $tradeNo);
        echo $ok ? 'success' : 'fail';
        exit;
    }

    $order = OrderModel::getByOrderNo($orderNo);
    if (!$order) {
        echo 'fail';
        exit;
    }
    if ((string) ($order['payment_plugin'] ?? '') !== 'alipay') {
        echo 'fail';
        exit;
    }

    if ((string) $order['status'] !== 'pending') {
        echo 'success';
        exit;
    }

    $expected = number_format(((int) $order['pay_amount']) / 1000000, 2, '.', '');
    if ($totalAmount !== '' && $totalAmount !== $expected) {
        echo 'fail';
        exit;
    }

    try {
        Database::begin();

        OrderModel::changeStatus((int) $order['id'], 'paid');

        $sql = 'INSERT INTO `' . Database::prefix() . 'order_payment`
                (order_id, payment_code, payment_plugin, trade_no, amount, status, paid_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())';
        Database::execute($sql, [
            (int) $order['id'],
            (string) ($order['payment_code'] ?? 'alipay'),
            'alipay',
            $tradeNo,
            (int) $order['pay_amount'],
            'success',
        ]);

        Database::commit();
    } catch (Throwable $e) {
        Database::rollBack();
        alipay_log('notify db error: ' . $e->getMessage());
        echo 'fail';
        exit;
    }

    try {
        OrderModel::triggerDelivery((int) $order['id']);
    } catch (Throwable $e) {
        alipay_log('trigger delivery failed: ' . $e->getMessage());
    }

    echo 'success';
    exit;
});

// 同步回跳：/return（核心按 out_trade_no 自动识别 alipay）
addAction('payment_return_alipay', function (array $data): void {
    $orderNo = (string) ($data['out_trade_no'] ?? '');
    if ($orderNo !== '' && strncmp($orderNo, 'R', 1) === 0) {
        Response::redirect('/user/wallet.php');
    }

    Response::redirect(PaymentCallbackController::buildReturnRedirectUrl($orderNo));
});
