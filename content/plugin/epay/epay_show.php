<?php
declare(strict_types=1);

if (!defined('EM_ROOT')) {
    $root = dirname(__DIR__, 3);
    $init = $root . '/init.php';
    if (is_file($init)) {
        require_once $init;
    }
}

defined('EM_ROOT') || exit('access denied!');

if (!function_exists('epay_get_channel_config')) {
    require_once __DIR__ . '/epay.php';
}

function epay_show_h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function epay_show_json(array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function epay_show_base64url_decode(string $raw): string
{
    if ($raw === '') {
        return '';
    }
    $b64 = strtr($raw, '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad > 0) {
        $b64 .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode($b64, true);
    return $decoded === false ? '' : $decoded;
}

function epay_show_status_text(string $status, bool $isRecharge): string
{
    if ($isRecharge) {
        $map = [
            UserRechargeModel::STATUS_PENDING => '待支付',
            UserRechargeModel::STATUS_PAID => '已支付',
            UserRechargeModel::STATUS_CANCELLED => '已取消',
        ];
        return $map[$status] ?? ($status !== '' ? $status : '未知状态');
    }

    return OrderModel::statusName($status);
}

function epay_show_channel_label(string $paymentCode): string
{
    $map = [
        'epay_alipay' => '支付宝',
        'epay_wxpay' => '微信支付',
        'epay_qqpay' => 'QQ钱包',
    ];
    return $map[$paymentCode] ?? '易支付';
}

function epay_show_channel_logo(string $paymentCode): string
{
    $map = [
        'epay_alipay' => '/content/plugin/epay/alipay.png',
        'epay_wxpay' => '/content/plugin/epay/wxpay.png',
        'epay_qqpay' => '/content/plugin/epay/qqpay.png',
    ];
    return $map[$paymentCode] ?? '/content/plugin/epay/alipay.png';
}

function epay_show_trade_context(string $orderNo): array
{
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $frontUser = $_SESSION['em_front_user'] ?? null;
    $userId = !empty($frontUser['id']) ? (int) $frontUser['id'] : 0;
    $guestToken = GuestToken::get();

    $isRecharge = strncmp($orderNo, 'R', 1) === 0;
    if ($isRecharge) {
        $recharge = (new UserRechargeModel())->findByOrderNo($orderNo);
        if (!$recharge) {
            throw new RuntimeException('充值订单不存在');
        }
        if ((string) ($recharge['payment_plugin'] ?? '') !== 'epay') {
            throw new RuntimeException('该订单不是易支付支付');
        }
        if ($userId <= 0 || $userId !== (int) ($recharge['user_id'] ?? 0)) {
            throw new RuntimeException('无权访问该订单');
        }

        return [
            'is_recharge' => true,
            'status' => (string) ($recharge['status'] ?? ''),
            'amount_raw' => (int) ($recharge['amount'] ?? 0),
            'payment_code' => (string) ($recharge['payment_code'] ?? ''),
            'redirect_url' => '/user/wallet.php',
            'title' => '充值支付 ' . $orderNo,
            'created_at' => (string) ($recharge['created_at'] ?? ''),
            'order_items' => [],
        ];
    }

    $order = OrderModel::getByOrderNo($orderNo);
    if (!$order) {
        throw new RuntimeException('订单不存在');
    }
    if ((string) ($order['payment_plugin'] ?? '') !== 'epay') {
        throw new RuntimeException('该订单不是易支付支付');
    }

    $isOwner = false;
    if ($userId > 0 && $userId === (int) ($order['user_id'] ?? 0)) {
        $isOwner = true;
    } elseif ((string) ($order['guest_token'] ?? '') !== '' && (string) ($order['guest_token'] ?? '') === $guestToken) {
        $isOwner = true;
    }
    if (!$isOwner) {
        throw new RuntimeException('无权访问该订单');
    }

    $items = [];
    foreach (OrderModel::getOrderGoods((int) ($order['id'] ?? 0)) as $og) {
        $items[] = [
            'title' => (string) ($og['goods_title'] ?? ''),
            'spec' => (string) ($og['spec_name'] ?? ''),
            'quantity' => (int) ($og['quantity'] ?? 0),
            'cover' => (string) ($og['cover_image'] ?? ''),
        ];
    }

    return [
        'is_recharge' => false,
        'status' => (string) ($order['status'] ?? ''),
        'amount_raw' => (int) ($order['pay_amount'] ?? 0),
        'payment_code' => (string) ($order['payment_code'] ?? ''),
        'redirect_url' => $userId > 0
            ? '/user/order_detail.php?order_no=' . rawurlencode($orderNo)
            : '/user/find_order.php',
        'title' => '订单支付 ' . $orderNo,
        'created_at' => (string) ($order['created_at'] ?? ''),
        'order_items' => $items,
    ];
}

function epay_show_decode_payload(string $payloadRaw): array
{
    $payloadRaw = trim($payloadRaw);
    if ($payloadRaw === '') {
        return [];
    }

    $decoded = epay_show_base64url_decode($payloadRaw);
    if ($decoded === '') {
        return [];
    }

    $arr = json_decode($decoded, true);
    if (!is_array($arr)) {
        return [];
    }

    $submitTarget = trim((string) ($arr['submit_target'] ?? ''));
    if ($submitTarget !== '' && !preg_match('#^https?://#i', $submitTarget)) {
        $submitTarget = '';
    }

    $submitFields = [];
    $rawFields = $arr['submit_fields'] ?? [];
    if (is_array($rawFields)) {
        foreach ($rawFields as $k => $v) {
            $key = trim((string) $k);
            if ($key === '') {
                continue;
            }
            if (is_array($v) || is_object($v)) {
                continue;
            }
            $submitFields[$key] = (string) $v;
        }
    }

    return [
        'payurl' => trim((string) ($arr['payurl'] ?? '')),
        'qrcode' => trim((string) ($arr['qrcode'] ?? '')),
        'urlscheme' => trim((string) ($arr['urlscheme'] ?? '')),
        'trade_no' => trim((string) ($arr['trade_no'] ?? '')),
        'submit_target' => $submitTarget,
        'submit_fields' => $submitFields,
    ];
}

function epay_show_fetch_gateway_payload(string $orderNo, string $paymentCode, int $amountRaw): array
{
    $conf = epay_get_channel_config($paymentCode);
    if ($conf === null) {
        return ['payload' => [], 'error' => '支付通道未启用或配置不完整'];
    }
    if (trim((string) ($conf['mapi_url'] ?? '')) === '') {
        return ['payload' => [], 'error' => '当前通道未配置 mapi 接口地址，无法展示二维码支付页'];
    }

    try {
        $params = epay_build_trade_params($conf, [
            'order_no' => $orderNo,
            'pay_amount' => $amountRaw,
        ]);
    } catch (Throwable $e) {
        return ['payload' => [], 'error' => $e->getMessage()];
    }

    $res = epay_gateway_request_json((string) $conf['mapi_url'], $params);
    if (($res['ok'] ?? false) !== true) {
        return ['payload' => [], 'error' => (string) ($res['msg'] ?? '易支付接口请求失败')];
    }

    $ret = (array) ($res['data'] ?? []);
    if ((string) ($ret['code'] ?? '') !== '1') {
        return ['payload' => [], 'error' => (string) ($ret['msg'] ?? '易支付下单失败')];
    }

    $payload = [
        'payurl' => trim((string) ($ret['payurl'] ?? '')),
        'qrcode' => trim((string) ($ret['qrcode'] ?? '')),
        'urlscheme' => trim((string) ($ret['urlscheme'] ?? '')),
        'trade_no' => trim((string) ($ret['trade_no'] ?? '')),
    ];

    if ($payload['payurl'] === '' && $payload['qrcode'] === '' && $payload['urlscheme'] === '') {
        return ['payload' => [], 'error' => '易支付接口未返回可用支付地址'];
    }

    return ['payload' => $payload, 'error' => ''];
}

function epay_show_entry_link(array $payload): string
{
    $scheme = trim((string) ($payload['urlscheme'] ?? ''));
    if ($scheme !== '') {
        return $scheme;
    }

    $payUrl = trim((string) ($payload['payurl'] ?? ''));
    if ($payUrl !== '') {
        return $payUrl;
    }

    $qr = trim((string) ($payload['qrcode'] ?? ''));
    if ($qr !== '') {
        return $qr;
    }

    return '';
}

function epay_show_qr_text(array $payload): string
{
    $qr = trim((string) ($payload['qrcode'] ?? ''));
    if ($qr !== '') {
        return $qr;
    }

    $payUrl = trim((string) ($payload['payurl'] ?? ''));
    if ($payUrl !== '') {
        return $payUrl;
    }

    $scheme = trim((string) ($payload['urlscheme'] ?? ''));
    if ($scheme !== '') {
        return $scheme;
    }

    return '';
}

$orderNo = trim((string) ($_GET['order_no'] ?? ''));
if ($orderNo === '') {
    http_response_code(400);
    echo 'missing order_no';
    exit;
}

try {
    $ctx = epay_show_trade_context($orderNo);
} catch (Throwable $e) {
    http_response_code(403);
    echo epay_show_h($e->getMessage());
    exit;
}

$isRecharge = (bool) ($ctx['is_recharge'] ?? false);
$status = (string) ($ctx['status'] ?? '');
$amountRaw = (int) ($ctx['amount_raw'] ?? 0);
$paymentCode = (string) ($ctx['payment_code'] ?? '');
$redirectUrl = (string) ($ctx['redirect_url'] ?? '/');
$title = (string) ($ctx['title'] ?? ('订单支付 ' . $orderNo));
$createdAt = (string) ($ctx['created_at'] ?? '');
$orderItems = is_array($ctx['order_items'] ?? null) ? $ctx['order_items'] : [];

$isPaid = $isRecharge
    ? $status === UserRechargeModel::STATUS_PAID
    : !in_array($status, ['pending', 'failed'], true);

if (trim((string) ($_GET['action'] ?? '')) === 'status') {
    epay_show_json([
        'code' => 200,
        'data' => [
            'paid' => $isPaid,
            'status' => $status,
            'status_text' => epay_show_status_text($status, $isRecharge),
            'redirect_url' => $redirectUrl,
        ],
    ]);
}

$payError = '';
$payload = epay_show_decode_payload((string) ($_GET['p'] ?? ''));
if (!$isPaid && $payload === []) {
    $fallback = epay_show_fetch_gateway_payload($orderNo, $paymentCode, $amountRaw);
    $payload = is_array($fallback['payload'] ?? null) ? $fallback['payload'] : [];
    $payError = (string) ($fallback['error'] ?? '');
}

$submitTarget = trim((string) ($payload['submit_target'] ?? ''));
$submitFields = is_array($payload['submit_fields'] ?? null) ? $payload['submit_fields'] : [];
$isSubmitPostMode = $submitTarget !== '' && $submitFields !== [];

$qrText = epay_show_qr_text($payload);
$entryLink = epay_show_entry_link($payload);
if (!$isPaid && $payError === '' && !$isSubmitPostMode && $qrText === '' && $entryLink === '') {
    $payError = '支付参数缺失，请返回上一步重新发起支付。';
}

$money = number_format($amountRaw / 1000000, 2, '.', '');
$cur = Currency::getInstance()->getPrimary();
$sym = (string) ($cur['symbol'] ?? '¥');
$statusText = epay_show_status_text($status, $isRecharge);
$channelLabel = epay_show_channel_label($paymentCode);
$channelLogo = epay_show_channel_logo($paymentCode);

$firstItemCover = '';
foreach ($orderItems as $it) {
    $c = trim((string) ($it['cover'] ?? ''));
    if ($c !== '') {
        $firstItemCover = $c;
        break;
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= epay_show_h($title) ?></title>
    <style>
        :root{
            --ink:#152842;
            --muted:#5d7291;
            --line:#d6e0ef;
            --brand:#19a974;
            --brand-dark:#12885d;
            --ok-bg:#ecfff5;
            --ok-line:#9de8c4;
            --ok-ink:#145837;
            --err-bg:#fff4f4;
            --err-line:#ffd1d1;
            --err-ink:#9b1d1d;
        }
        *{box-sizing:border-box;}
        body{
            margin:0;
            font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;
            color:var(--ink);
            background:
                radial-gradient(circle at 0% 0%, rgba(25,169,116,.18), transparent 30%),
                radial-gradient(circle at 100% 10%, rgba(57,146,240,.14), transparent 30%),
                linear-gradient(180deg,#f8fbff 0%,#edf3fb 100%);
            min-height:100vh;
        }
        .shell{
            max-width:1120px;
            margin:0 auto;
            padding:26px 14px 40px;
        }
        .card{
            border:1px solid var(--line);
            border-radius:24px;
            overflow:hidden;
            background:#fff;
            box-shadow:0 20px 50px rgba(20,44,86,.08);
        }
        .hero{
            display:grid;
            grid-template-columns:320px 1fr;
            background:linear-gradient(110deg,#0ea86e 0%,#1fb47f 45%,#53c29a 100%);
            color:#fff;
            position:relative;
        }
        .hero::before{
            content:"";
            position:absolute;
            inset:0;
            background:
                radial-gradient(circle at 12% 20%, rgba(255,255,255,.14), transparent 32%),
                radial-gradient(circle at 88% 16%, rgba(255,255,255,.16), transparent 26%);
            pointer-events:none;
        }
        .hero-media{
            padding:24px;
            display:flex;
            align-items:center;
            justify-content:center;
            position:relative;
            z-index:1;
        }
        .hero-logo{
            width:230px;
            max-width:100%;
            height:120px;
            border-radius:16px;
            border:1px solid rgba(255,255,255,.65);
            background:rgba(255,255,255,.93);
            box-shadow:
                0 14px 32px rgba(6,37,27,.2),
                inset 0 1px 0 rgba(255,255,255,.95);
            display:flex;
            align-items:center;
            justify-content:center;
            padding:16px;
        }
        .hero-logo img{
            max-width:100%;
            max-height:100%;
            object-fit:contain;
            display:block;
        }
        .hero-main{
            padding:24px 28px 24px 6px;
            position:relative;
            z-index:1;
        }
        .hero-tag{
            display:inline-flex;
            align-items:center;
            padding:5px 12px;
            border-radius:999px;
            background:rgba(255,255,255,.2);
            font-size:12px;
            letter-spacing:.2px;
            margin-bottom:10px;
        }
        .hero-title{
            margin:0;
            font-size:38px;
            line-height:1.15;
            font-weight:800;
        }
        .hero-sub{
            margin:12px 0 0;
            font-size:15px;
            opacity:.96;
        }
        .hero-amount{
            margin-top:16px;
            font-size:40px;
            font-weight:900;
        }
        .hero-order{
            margin-top:10px;
            font-size:15px;
            opacity:.98;
            word-break:break-all;
        }
        .body{
            background:#f8fbff;
            padding:22px;
        }
        .alert{
            margin:0;
            padding:12px 14px;
            border-radius:12px;
            border:1px solid;
            font-size:14px;
            line-height:1.65;
        }
        .alert-ok{background:var(--ok-bg);border-color:var(--ok-line);color:var(--ok-ink);}
        .alert-err{background:var(--err-bg);border-color:var(--err-line);color:var(--err-ink);}
        .grid{
            display:grid;
            grid-template-columns:350px 1fr;
            gap:16px;
            align-items:start;
        }
        .qr-panel{
            border:1px dashed #c7d7ec;
            border-radius:14px;
            padding:14px;
            background:#fff;
            text-align:center;
            box-shadow:0 10px 22px rgba(23,73,153,.05);
        }
        .qr-box{
            width:280px;
            height:280px;
            max-width:100%;
            margin:0 auto;
            background:#fff;
            border:1px solid #e6edf8;
            border-radius:10px;
            display:flex;
            align-items:center;
            justify-content:center;
            overflow:hidden;
        }
        .qr-box canvas,.qr-box img{max-width:100%;max-height:100%;}
        .qr-tip{
            margin:10px 0 0;
            color:var(--muted);
            font-size:13px;
            line-height:1.75;
        }
        .guide{
            border:1px solid var(--line);
            border-radius:14px;
            background:#fff;
            padding:16px 16px 14px;
            box-shadow:0 10px 22px rgba(23,73,153,.05);
        }
        .guide h3{
            margin:0 0 10px;
            font-size:24px;
            color:#12325a;
            font-weight:800;
        }
        .steps{
            margin:0;
            padding-left:22px;
            color:#435c80;
            line-height:1.9;
            font-size:16px;
        }
        .meta{
            margin-top:12px;
            padding-top:10px;
            border-top:1px solid var(--line);
            display:flex;
            flex-wrap:wrap;
            align-items:center;
            gap:10px;
        }
        .meta-label{
            color:#5f7596;
            font-size:15px;
            font-weight:600;
        }
        .meta-value{
            font-size:28px;
            font-weight:800;
            color:#12335d;
        }
        .status-probe{
            margin-top:12px;
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:7px 12px;
            border-radius:999px;
            background:#eef6ff;
            color:#385a84;
            border:1px solid #d4e3f8;
            font-size:13px;
        }
        .status-spinner{
            width:14px;
            height:14px;
            border-radius:50%;
            border:2px solid #bdd0ea;
            border-top-color:#19a974;
            animation:spin .9s linear infinite;
        }
        .status-probe.idle .status-spinner{
            animation:none;
            border-color:#9eb4d2;
            border-top-color:#9eb4d2;
        }
        .btns{
            margin-top:14px;
            display:flex;
            flex-wrap:wrap;
            gap:10px;
        }
        .btn{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-height:46px;
            padding:0 16px;
            border-radius:11px;
            border:1px solid #cfd9e9;
            background:#fff;
            color:#1c3252;
            text-decoration:none;
            font-size:15px;
            font-weight:700;
        }
        .btn:hover{border-color:#9eb4d6;}
        .btn-primary{
            border-color:var(--brand-dark);
            background:linear-gradient(180deg,var(--brand),var(--brand-dark));
            color:#fff;
            box-shadow:0 10px 20px rgba(25,169,116,.28);
        }
        .btn-primary:hover{border-color:var(--brand-dark);}
        .order-info{
            margin-top:16px;
            border:1px solid var(--line);
            border-radius:14px;
            background:#fff;
            overflow:hidden;
            box-shadow:0 10px 22px rgba(23,73,153,.05);
        }
        .order-head{
            padding:14px 16px;
            border-bottom:1px solid var(--line);
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
        }
        .order-title{
            margin:0;
            font-size:18px;
            color:#12325a;
            font-weight:800;
        }
        .order-time{
            font-size:13px;
            color:#5f7391;
            white-space:nowrap;
        }
        .order-item{
            display:grid;
            grid-template-columns:70px 1fr auto;
            gap:12px;
            padding:14px 16px;
            border-top:1px solid #edf3fb;
            align-items:center;
        }
        .order-item:first-child{border-top:none;}
        .order-cover{
            width:70px;
            height:70px;
            border-radius:10px;
            border:1px solid #e3ebf8;
            object-fit:cover;
            background:#f6f9ff;
        }
        .order-name{
            margin:0;
            font-size:15px;
            font-weight:700;
            color:#153457;
            line-height:1.5;
            word-break:break-all;
        }
        .order-spec{
            margin-top:4px;
            color:#647d9f;
            font-size:12px;
            word-break:break-all;
        }
        .order-qty{
            font-size:13px;
            color:#315780;
            font-weight:700;
            background:#edf5ff;
            border-radius:999px;
            padding:4px 10px;
            white-space:nowrap;
        }
        .order-empty{
            padding:14px 16px;
            font-size:14px;
            color:#647b9b;
        }
        @keyframes spin{to{transform:rotate(360deg);}}
        @media (max-width:860px){
            .hero{grid-template-columns:1fr;}
            .hero-media{padding:20px 18px 8px;}
            .hero-main{padding:18px;}
            .hero-title{font-size:32px;}
            .hero-amount{font-size:34px;}
            .grid{grid-template-columns:1fr;}
            .guide h3{font-size:22px;}
            .meta-value{font-size:24px;}
        }
        @media (max-width:520px){
            .shell{padding:12px 8px 24px;}
            .card{border-radius:18px;}
            .body{padding:12px;}
            .hero-title{font-size:27px;}
            .hero-amount{font-size:30px;}
            .steps{font-size:15px;}
            .btns .btn{flex:1;}
            .order-item{
                grid-template-columns:60px 1fr;
                gap:10px;
            }
            .order-cover{width:60px;height:60px;}
            .order-qty{
                grid-column:2;
                justify-self:start;
                margin-top:6px;
            }
        }
    </style>
</head>
<body>
<div class="shell">
    <div class="card">
        <section class="hero">
            <div class="hero-media">
                <div class="hero-logo">
                    <img src="<?= epay_show_h($channelLogo) ?>" alt="<?= epay_show_h($channelLabel) ?>">
                </div>
            </div>
            <div class="hero-main">
                <span class="hero-tag">易支付收银台 · <?= epay_show_h($channelLabel) ?></span>
                <h1 class="hero-title"><?= epay_show_h($title) ?></h1>
                <p class="hero-sub">请在有效时间内完成支付，支付成功后页面会自动刷新并跳转结果页。</p>
                <div class="hero-amount"><?= epay_show_h($sym . $money) ?></div>
                <div class="hero-order">订单号：<?= epay_show_h($orderNo) ?></div>
            </div>
        </section>

        <section class="body">
            <?php if ($isPaid): ?>
                <p class="alert alert-ok">支付已完成，正在为你跳转到结果页面。</p>
                <div class="btns">
                    <a class="btn btn-primary" href="<?= epay_show_h(safe_url($redirectUrl)) ?>">查看结果</a>
                </div>
            <?php elseif ($payError !== ''): ?>
                <p class="alert alert-err">创建支付失败：<?= epay_show_h($payError) ?></p>
                <div class="btns">
                    <a class="btn" href="<?= epay_show_h(safe_url($redirectUrl)) ?>">返回</a>
                </div>
            <?php else: ?>
                <?php if ($isSubmitPostMode): ?>
                    <p class="alert alert-ok">正在安全跳转支付网关，请稍候...</p>
                    <div class="guide" style="margin-top:12px;">
                        <h3>支付跳转</h3>
                        <ol class="steps">
                            <li>系统将通过表单 POST 提交订单数据到易支付网关。</li>
                            <li>若未自动跳转，请点击下方“继续前往支付”。</li>
                            <li>支付完成后会自动返回并更新订单状态。</li>
                        </ol>
                    </div>
                    <form id="epaySubmitForm" method="post" action="<?= epay_show_h($submitTarget) ?>">
                        <?php foreach ($submitFields as $k => $v): ?>
                            <input type="hidden" name="<?= epay_show_h((string) $k) ?>" value="<?= epay_show_h((string) $v) ?>">
                        <?php endforeach; ?>
                    </form>
                    <div class="btns">
                        <button type="button" class="btn btn-primary" id="epayManualSubmitBtn">继续前往支付</button>
                    </div>
                <?php else: ?>
                    <div class="grid">
                        <div class="qr-panel">
                            <div class="qr-box" id="epayQrBox"></div>
                            <p class="qr-tip">请使用对应支付工具扫码支付。手机端可直接点击下方“立即支付”。</p>
                        </div>
                        <div class="guide">
                            <h3>支付步骤</h3>
                            <ol class="steps">
                                <li>打开对应支付 App 并扫描二维码。</li>
                                <li>核对订单金额和订单号后确认支付。</li>
                                <li>支付成功后页面将自动跳转到订单结果页。</li>
                            </ol>
                            <div class="meta">
                                <span class="meta-label">支付状态</span>
                                <span class="meta-value" id="payStatusText"><?= epay_show_h($statusText) ?></span>
                            </div>
                            <div class="status-probe" id="statusProbe">
                                <span class="status-spinner"></span>
                                <span id="statusProbeText">正在检测支付状态...</span>
                            </div>
                        </div>
                    </div>

                    <?php if ($entryLink !== ''): ?>
                    <div class="btns">
                        <a class="btn btn-primary" href="<?= epay_show_h(safe_url($entryLink)) ?>">立即支付</a>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!$isRecharge && ($orderItems !== [] || $createdAt !== '')): ?>
                <div class="order-info">
                    <div class="order-head">
                        <h3 class="order-title">订单信息</h3>
                        <?php if ($createdAt !== ''): ?>
                            <span class="order-time">下单时间：<?= epay_show_h($createdAt) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($orderItems !== []): ?>
                        <?php foreach ($orderItems as $it): ?>
                            <?php
                            $itemTitle = trim((string) ($it['title'] ?? ''));
                            $itemSpec = trim((string) ($it['spec'] ?? ''));
                            $itemQty = (int) ($it['quantity'] ?? 0);
                            $itemCover = trim((string) ($it['cover'] ?? ''));
                            if ($itemCover === '') {
                                $itemCover = $firstItemCover !== '' ? $firstItemCover : $channelLogo;
                            }
                            ?>
                            <div class="order-item">
                                <img class="order-cover" src="<?= epay_show_h($itemCover) ?>" alt="">
                                <div>
                                    <p class="order-name"><?= epay_show_h($itemTitle !== '' ? $itemTitle : '商品') ?></p>
                                    <?php if ($itemSpec !== ''): ?>
                                        <div class="order-spec">规格：<?= epay_show_h($itemSpec) ?></div>
                                    <?php endif; ?>
                                </div>
                                <span class="order-qty">x<?= $itemQty > 0 ? $itemQty : 1 ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="order-empty">暂无商品明细</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php if (!$isPaid && $payError === '' && $isSubmitPostMode): ?>
<script>
(function () {
    var form = document.getElementById('epaySubmitForm');
    var manualBtn = document.getElementById('epayManualSubmitBtn');
    if (!form) {
        return;
    }

    var submitted = false;
    function doSubmit() {
        if (submitted) {
            return;
        }
        submitted = true;
        form.submit();
    }

    if (manualBtn) {
        manualBtn.addEventListener('click', function () {
            doSubmit();
        });
    }

    setTimeout(doSubmit, 200);
})();
</script>
<?php elseif (!$isPaid && $payError === ''): ?>
<script src="/content/static/lib/qrcode.min.js"></script>
<script>
(function () {
    function statusToText(status) {
        var map = {
            pending: '待支付',
            paid: '已支付',
            cancelled: '已取消',
            failed: '支付失败',
            expired: '已过期',
            delivering: '发货中',
            delivered: '已发货',
            completed: '已完成',
            refunding: '退款中',
            refunded: '已退款'
        };
        return map[status] || status || '未知状态';
    }

    var qrText = <?= json_for_script($qrText) ?>;
    var qrBox = document.getElementById('epayQrBox');
    if (typeof QRCode !== 'undefined' && qrBox && qrText) {
        new QRCode(qrBox, {
            text: qrText,
            width: 280,
            height: 280,
            correctLevel: QRCode.CorrectLevel.M
        });
    } else if (qrBox) {
        qrBox.textContent = '二维码加载失败，请点击“立即支付”继续';
    }

    var statusText = document.getElementById('payStatusText');
    var statusProbe = document.getElementById('statusProbe');
    var statusProbeText = document.getElementById('statusProbeText');
    var polling = false;

    function setProbeChecking(msg) {
        if (!statusProbe) return;
        statusProbe.classList.remove('idle');
        if (statusProbeText) statusProbeText.textContent = msg || '正在检测支付状态...';
    }

    function setProbeIdle(msg) {
        if (!statusProbe) return;
        statusProbe.classList.add('idle');
        if (statusProbeText) statusProbeText.textContent = msg || '每 3 秒自动检测一次';
    }

    function poll() {
        if (polling) return;
        polling = true;
        setProbeChecking('正在检测支付状态...');
        var url = '/?plugin=epay&action=status&order_no=<?= rawurlencode($orderNo) ?>';
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || res.code !== 200 || !res.data) return;
                var stText = String(res.data.status_text || statusToText(String(res.data.status || '')));
                if (statusText) statusText.textContent = stText;
                if (res.data.paid) {
                    setProbeChecking('支付已完成，正在跳转...');
                    window.location.href = res.data.redirect_url || '<?= epay_show_h($redirectUrl) ?>';
                    return;
                }
                setProbeIdle('每 3 秒自动检测一次');
            })
            .catch(function () {
                setProbeIdle('检测异常，3 秒后重试');
            })
            .finally(function () {
                polling = false;
            });
    }

    poll();
    setInterval(poll, 3000);
})();
</script>
<?php endif; ?>
</body>
</html>
