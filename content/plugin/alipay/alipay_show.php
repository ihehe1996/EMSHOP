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

if (!function_exists('alipay_get_config')) {
    require_once __DIR__ . '/alipay.php';
}

function alipay_show_h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function alipay_base64url_decode_local(string $raw): string
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

function alipay_show_json(array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function alipay_status_text(string $status, bool $isRecharge): string
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

function alipay_load_trade_context(string $orderNo): array
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
        if ((string) ($recharge['payment_plugin'] ?? '') !== 'alipay') {
            throw new RuntimeException('该订单不是支付宝支付');
        }
        if ($userId <= 0 || $userId !== (int) ($recharge['user_id'] ?? 0)) {
            throw new RuntimeException('无权访问该订单');
        }
        return [
            'is_recharge'  => true,
            'status'       => (string) ($recharge['status'] ?? ''),
            'amount_raw'   => (int) ($recharge['amount'] ?? 0),
            'redirect_url' => '/user/wallet.php',
            'title'        => '钱包充值 ' . $orderNo,
            'created_at'   => (string) ($recharge['created_at'] ?? ''),
            'order_items'  => [[
                'title'    => '钱包充值',
                'spec'     => '',
                'quantity' => 1,
                'cover'    => '',
            ]],
        ];
    }

    $order = OrderModel::getByOrderNo($orderNo);
    if (!$order) {
        throw new RuntimeException('订单不存在');
    }
    if ((string) ($order['payment_plugin'] ?? '') !== 'alipay') {
        throw new RuntimeException('该订单不是支付宝支付');
    }

    $isOwner = false;
    if ($userId > 0 && (int) ($order['user_id'] ?? 0) === $userId) {
        $isOwner = true;
    } elseif ((string) ($order['guest_token'] ?? '') !== '' && (string) ($order['guest_token'] ?? '') === $guestToken) {
        $isOwner = true;
    }
    if (!$isOwner) {
        throw new RuntimeException('无权访问该订单');
    }

    $orderItems = [];
    foreach (OrderModel::getOrderGoods((int) ($order['id'] ?? 0)) as $og) {
        $orderItems[] = [
            'title'    => (string) ($og['goods_title'] ?? ''),
            'spec'     => (string) ($og['spec_name'] ?? ''),
            'quantity' => (int) ($og['quantity'] ?? 0),
            'cover'    => (string) ($og['cover_image'] ?? ''),
        ];
    }

    return [
        'is_recharge'  => false,
        'status'       => (string) ($order['status'] ?? ''),
        'amount_raw'   => (int) ($order['pay_amount'] ?? 0),
        'redirect_url' => $userId > 0
            ? '/user/order_detail.php?order_no=' . rawurlencode($orderNo)
            : '/user/find_order.php',
        'title'        => '订单支付 ' . $orderNo,
        'created_at'   => (string) ($order['created_at'] ?? ''),
        'order_items'  => $orderItems,
    ];
}

$orderNo = trim((string) ($_GET['order_no'] ?? ''));
if ($orderNo === '') {
    http_response_code(400);
    echo 'missing order_no';
    exit;
}

try {
    $ctx = alipay_load_trade_context($orderNo);
} catch (Throwable $e) {
    http_response_code(403);
    echo alipay_show_h($e->getMessage());
    exit;
}

$isRecharge = (bool) ($ctx['is_recharge'] ?? false);
$status = (string) ($ctx['status'] ?? '');
$amountRaw = (int) ($ctx['amount_raw'] ?? 0);
$redirectUrl = (string) ($ctx['redirect_url'] ?? '/');
$title = (string) ($ctx['title'] ?? ('订单支付 ' . $orderNo));
$createdAt = (string) ($ctx['created_at'] ?? '');
$orderItems = is_array($ctx['order_items'] ?? null) ? $ctx['order_items'] : [];
$isPaid = $isRecharge
    ? $status === UserRechargeModel::STATUS_PAID
    : !in_array($status, ['pending', 'failed'], true);

if (trim((string) ($_GET['action'] ?? '')) === 'status') {
    alipay_show_json([
        'code' => 200,
        'data' => [
            'paid' => $isPaid,
            'status' => $status,
            'status_text' => alipay_status_text($status, $isRecharge),
            'redirect_url' => $redirectUrl,
        ],
    ]);
}

$cfg = alipay_get_config();
$payError = '';
$qrUrl = '';

if (!$isPaid) {
    $qRaw = trim((string) ($_GET['q'] ?? ''));
    if ($qRaw !== '') {
        $decoded = trim(alipay_base64url_decode_local($qRaw));
        if (preg_match('#^https?://#i', $decoded)) {
            $qrUrl = $decoded;
        }
    }

    if ($qrUrl === '') {
        if (!alipay_has_basic_config($cfg)) {
            $payError = '支付宝配置不完整，请联系管理员。';
        } elseif ($amountRaw <= 0) {
            $payError = '订单金额异常，无法发起支付。';
        } elseif ($status !== 'pending' && !($isRecharge && $status === UserRechargeModel::STATUS_PENDING)) {
            $payError = '当前订单状态不可发起支付：' . $status;
        } else {
            try {
                $amount = number_format($amountRaw / 1000000, 2, '.', '');
                $subject = alipay_order_subject($orderNo);
                $qrUrl = alipay_create_face_pay_url($cfg, $orderNo, $amount, $subject);
            } catch (Throwable $e) {
                $payError = $e->getMessage();
            }
        }
    }
}

$money = number_format($amountRaw / 1000000, 2, '.', '');
$cur = Currency::getInstance()->getPrimary();
$sym = (string) ($cur['symbol'] ?? '¥');
$statusText = alipay_status_text($status, $isRecharge);
$cardImage = '/content/plugin/alipay/alipay_card.png';
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
    <title><?= alipay_show_h($title) ?></title>
    <style>
        :root{
            --ink:#12233d;
            --muted:#5f7391;
            --line:#dbe6f6;
            --brand:#1677ff;
            --brand-dark:#0f63d8;
            --ok-bg:#edfff4;
            --ok-line:#9ee4b8;
            --ok-ink:#0f5132;
            --err-bg:#fff4f4;
            --err-line:#ffd2d2;
            --err-ink:#9b1c1c;
        }
        *{box-sizing:border-box;}
        body{
            margin:0;
            color:var(--ink);
            font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;
            background:
                radial-gradient(circle at 8% 14%, rgba(51,132,255,.18), transparent 34%),
                radial-gradient(circle at 92% 2%, rgba(28,108,255,.15), transparent 28%),
                linear-gradient(180deg,#f9fbff 0%,#eef3ff 100%);
            min-height:100vh;
        }
        .pay-shell{
            max-width:1120px;
            margin:0 auto;
            padding:28px 16px 42px;
        }
        .pay-card{
            background:#fff;
            border:1px solid var(--line);
            border-radius:26px;
            overflow:hidden;
            box-shadow:0 22px 56px rgba(15,36,74,.09);
        }
        .hero{
            display:grid;
            grid-template-columns:340px 1fr;
            background:linear-gradient(110deg,#0f66e8 0%,#2b86ff 58%,#6fafff 100%);
            color:#fff;
            position:relative;
        }
        .hero::after{
            content:"";
            position:absolute;
            right:-90px;
            top:-110px;
            width:280px;
            height:280px;
            border-radius:50%;
            background:rgba(255,255,255,.13);
        }
        .hero-media{
            position:relative;
            z-index:1;
            padding:26px 24px;
            display:flex;
            align-items:center;
            justify-content:center;
            background:linear-gradient(145deg,rgba(255,255,255,.16),rgba(255,255,255,0));
        }
        .hero-media-panel{
            width:100%;
            max-width:290px;
            min-height:176px;
            border-radius:18px;
            padding:12px;
            background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(248,252,255,.92));
            border:1px solid rgba(255,255,255,.74);
            box-shadow:
                0 16px 32px rgba(3,20,61,.2),
                inset 0 1px 0 rgba(255,255,255,.95);
            display:flex;
            align-items:center;
            justify-content:center;
        }
        .hero-media img{
            width:100%;
            max-width:270px;
            border-radius:16px;
            box-shadow:0 14px 28px rgba(2,14,43,.15);
            display:block;
        }
        .hero-main{
            position:relative;
            z-index:1;
            padding:28px 28px 26px;
        }
        .hero-tag{
            display:inline-flex;
            align-items:center;
            padding:5px 12px;
            border-radius:999px;
            font-size:12px;
            letter-spacing:.2px;
            background:rgba(255,255,255,.22);
            margin-bottom:10px;
        }
        .hero-title{
            margin:0;
            font-size:42px;
            font-weight:800;
            letter-spacing:.3px;
            line-height:1.1;
        }
        .hero-sub{
            margin:12px 0 0;
            font-size:16px;
            opacity:.95;
        }
        .hero-amount{
            margin-top:18px;
            font-size:42px;
            font-weight:900;
            letter-spacing:.5px;
        }
        .hero-order{
            margin-top:10px;
            font-size:15px;
            opacity:.96;
            word-break:break-all;
        }
        .body{
            background:#f9fbff;
            padding:24px;
        }
        .alert{
            margin:0;
            padding:12px 14px;
            border-radius:12px;
            border:1px solid;
            font-size:14px;
            line-height:1.6;
        }
        .alert-ok{background:var(--ok-bg);border-color:var(--ok-line);color:var(--ok-ink);}
        .alert-err{background:var(--err-bg);border-color:var(--err-line);color:var(--err-ink);}
        .pay-grid{
            display:grid;
            grid-template-columns:360px 1fr;
            gap:18px;
            align-items:start;
        }
        .qr-panel{
            border:1px dashed #c8d9f2;
            background:#fff;
            border-radius:16px;
            padding:16px;
            text-align:center;
            box-shadow:0 10px 24px rgba(23,73,153,.06);
        }
        .qr-box{
            width:280px;
            height:280px;
            max-width:100%;
            margin:0 auto;
            border-radius:10px;
            border:1px solid #edf2fb;
            background:#fff;
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
            border-radius:16px;
            padding:18px 18px 16px;
            background:#fff;
            box-shadow:0 10px 24px rgba(23,73,153,.05);
        }
        .order-info{
            margin-top:16px;
            border:1px solid var(--line);
            border-radius:16px;
            background:#fff;
            box-shadow:0 10px 24px rgba(23,73,153,.05);
            overflow:hidden;
        }
        .order-info-head{
            padding:14px 16px;
            border-bottom:1px solid var(--line);
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
        }
        .order-info-title{
            margin:0;
            font-size:18px;
            font-weight:800;
            color:#16325e;
        }
        .order-info-time{
            font-size:13px;
            color:#5f7391;
            white-space:nowrap;
        }
        .order-item{
            display:grid;
            grid-template-columns:72px 1fr auto;
            gap:12px;
            padding:14px 16px;
            border-top:1px solid #edf2fa;
            align-items:center;
        }
        .order-item:first-child{border-top:none;}
        .order-cover{
            width:72px;
            height:72px;
            border-radius:10px;
            border:1px solid #e4ebf7;
            object-fit:cover;
            background:#f7f9fc;
        }
        .order-main{
            min-width:0;
        }
        .order-name{
            font-size:15px;
            font-weight:700;
            color:#16325e;
            line-height:1.5;
            margin:0;
            word-break:break-all;
        }
        .order-spec{
            margin-top:4px;
            font-size:12px;
            color:#647b9b;
            word-break:break-all;
        }
        .order-qty{
            font-size:13px;
            color:#355785;
            font-weight:700;
            white-space:nowrap;
            padding:4px 10px;
            background:#edf4ff;
            border-radius:999px;
        }
        .order-empty{
            padding:14px 16px;
            font-size:14px;
            color:#647b9b;
        }
        .guide h3{
            margin:0 0 10px;
            font-size:26px;
            font-weight:800;
            color:#12315f;
        }
        .steps{
            margin:0;
            padding-left:22px;
            color:#445b7e;
            font-size:17px;
            line-height:1.9;
        }
        .meta{
            margin-top:14px;
            padding-top:12px;
            border-top:1px solid var(--line);
            font-size:27px;
            font-weight:700;
            color:#12315f;
            display:flex;
            flex-wrap:wrap;
            align-items:center;
            gap:10px 12px;
        }
        .meta-label{
            font-size:16px;
            font-weight:600;
            color:#586f91;
        }
        .meta-value{font-size:29px;}
        .status-probe{
            margin-top:12px;
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:7px 12px;
            border-radius:999px;
            background:#eff5ff;
            color:#355785;
            font-size:13px;
            border:1px solid #d4e3fb;
        }
        .status-spinner{
            width:14px;
            height:14px;
            border:2px solid #b8cdf0;
            border-top-color:#1677ff;
            border-radius:50%;
            animation:spin .9s linear infinite;
        }
        .status-probe.idle .status-spinner{
            animation:none;
            border-color:#9eb4d9;
            border-top-color:#9eb4d9;
        }
        .btns{
            display:flex;
            flex-wrap:wrap;
            gap:10px;
            margin-top:16px;
        }
        .btn{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-height:46px;
            padding:0 18px;
            border-radius:11px;
            border:1px solid #ced7e8;
            background:#fff;
            color:#1c2f4a;
            text-decoration:none;
            font-size:15px;
            font-weight:700;
        }
        .btn:hover{border-color:#9eb4da;}
        .btn-primary{
            border-color:var(--brand-dark);
            background:linear-gradient(180deg,var(--brand),var(--brand-dark));
            color:#fff;
            box-shadow:0 10px 20px rgba(22,119,255,.28);
        }
        .btn-primary:hover{border-color:var(--brand-dark);}
        @keyframes spin{to{transform:rotate(360deg);}}
        @media (max-width:860px){
            .hero{grid-template-columns:1fr;}
            .hero-media{padding:20px 18px 8px;}
            .hero-media-panel{max-width:250px;min-height:152px;}
            .hero-media img{max-width:220px;}
            .hero-main{padding:18px 18px 22px;}
            .hero-title{font-size:34px;}
            .hero-sub{font-size:15px;}
            .hero-amount{font-size:36px;}
            .pay-grid{grid-template-columns:1fr;}
            .guide h3{font-size:22px;}
            .steps{font-size:16px;}
            .meta-value{font-size:25px;}
        }
        @media (max-width:520px){
            .pay-shell{padding:14px 10px 26px;}
            .pay-card{border-radius:18px;}
            .body{padding:14px;}
            .hero-title{font-size:28px;}
            .hero-amount{font-size:30px;}
            .steps{font-size:15px;line-height:1.8;}
            .meta{font-size:22px;}
            .meta-value{font-size:22px;}
            .btns .btn{flex:1;}
            .order-item{
                grid-template-columns:60px 1fr;
                gap:10px;
            }
            .order-cover{
                width:60px;
                height:60px;
            }
            .order-qty{
                grid-column:2;
                justify-self:start;
                margin-top:6px;
            }
        }
    </style>
</head>
<body>
<div class="pay-shell">
    <div class="pay-card">
        <section class="hero">
            <div class="hero-media">
                <div class="hero-media-panel">
                    <img src="<?= alipay_show_h($cardImage) ?>" alt="支付宝收银卡片">
                </div>
            </div>
            <div class="hero-main">
                <span class="hero-tag">支付宝当面付</span>
                <h1 class="hero-title"><?= alipay_show_h($title) ?></h1>
                <p class="hero-sub">请在有效时间内完成支付，支付成功后页面将自动刷新状态。</p>
                <div class="hero-amount"><?= alipay_show_h($sym . $money) ?></div>
                <div class="hero-order">订单号：<?= alipay_show_h($orderNo) ?></div>
            </div>
        </section>

        <section class="body">
            <?php if ($isPaid): ?>
                <p class="alert alert-ok">支付已完成，正在为你跳转到结果页面。</p>
                <div class="btns">
                    <a class="btn btn-primary" href="<?= alipay_show_h($redirectUrl) ?>">查看结果</a>
                </div>
            <?php elseif ($payError !== ''): ?>
                <p class="alert alert-err">创建支付失败：<?= alipay_show_h($payError) ?></p>
                <div class="btns">
                    <a class="btn" href="<?= alipay_show_h($redirectUrl) ?>">返回</a>
                </div>
            <?php else: ?>
                <div class="pay-grid">
                    <div class="qr-panel">
                        <div class="qr-box" id="alipayQrBox"></div>
                        <p class="qr-tip">使用支付宝扫一扫进行支付，移动端可直接点击下方“打开支付宝支付”。</p>
                    </div>
                    <div class="guide">
                        <h3>支付步骤</h3>
                        <ol class="steps">
                            <li>打开支付宝，使用“扫一扫”扫描二维码。</li>
                            <li>核对订单金额与订单号后确认支付。</li>
                            <li>支付成功后，页面会自动跳转到订单结果页。</li>
                        </ol>
                        <div class="meta">
                            <span class="meta-label">支付状态</span>
                            <span class="meta-value" id="payStatusText"><?= alipay_show_h($statusText) ?></span>
                        </div>
                        <div class="status-probe" id="statusProbe">
                            <span class="status-spinner"></span>
                            <span id="statusProbeText">正在检测支付状态...</span>
                        </div>
                    </div>
                </div>

                <div class="btns">
                    <a class="btn btn-primary" href="<?= alipay_show_h($qrUrl) ?>">打开支付宝支付</a>
                </div>
            <?php endif; ?>

            <?php if (!$isRecharge && ($orderItems !== [] || $createdAt !== '')): ?>
                <div class="order-info">
                    <div class="order-info-head">
                        <h3 class="order-info-title">订单信息</h3>
                        <?php if ($createdAt !== ''): ?>
                            <span class="order-info-time">下单时间：<?= alipay_show_h($createdAt) ?></span>
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
                                $itemCover = $firstItemCover !== '' ? $firstItemCover : $cardImage;
                            }
                            ?>
                            <div class="order-item">
                                <img class="order-cover" src="<?= alipay_show_h($itemCover) ?>" alt="">
                                <div class="order-main">
                                    <p class="order-name"><?= alipay_show_h($itemTitle !== '' ? $itemTitle : '商品') ?></p>
                                    <?php if ($itemSpec !== ''): ?>
                                        <div class="order-spec">规格：<?= alipay_show_h($itemSpec) ?></div>
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

<?php if (!$isPaid && $payError === ''): ?>
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

    var qrText = <?= json_encode($qrUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var qrBox = document.getElementById('alipayQrBox');
    if (typeof QRCode !== 'undefined' && qrBox && qrText) {
        new QRCode(qrBox, {
            text: qrText,
            width: 280,
            height: 280,
            correctLevel: QRCode.CorrectLevel.M
        });
    } else if (qrBox) {
        qrBox.textContent = '二维码加载失败，请点击下方按钮打开支付宝支付';
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
        var url = '/?plugin=alipay&action=status&order_no=<?= rawurlencode($orderNo) ?>';
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || res.code !== 200 || !res.data) return;
                var stText = String(res.data.status_text || statusToText(String(res.data.status || '')));
                if (statusText) statusText.textContent = stText;
                if (res.data.paid) {
                    setProbeChecking('支付已完成，正在跳转...');
                    window.location.href = res.data.redirect_url || '<?= alipay_show_h($redirectUrl) ?>';
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
