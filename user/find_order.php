<?php

declare(strict_types=1);

require_once __DIR__ . '/global_public.php';

/**
 * 游客查询订单（独立页面）。
 *
 * 不继承 user 中心模板：自带最简 HTML 框架，仅引入 layui / 站点基础 CSS 与 guest_find.js。
 *
 * 查询模式：
 *   1. token    —— 用浏览器 Cookie 里的 guest_token 列出最近 10 条订单
 *   2. contact  —— 订单编号 + 联系方式
 *   3. password —— 订单编号 + 订单密码（明文比对）
 *
 * 响应：所有 POST 都返回 JSON（列白名单脱敏，不带 order_password、admin_remark、内部 payment_*）。
 */

$siteName = Config::get('sitename', 'EMSHOP');
$siteLogoType = (string) (Config::get('site_logo_type') ?? 'text');
$siteLogo     = (string) (Config::get('site_logo') ?? '');
// 访客当前展示币种符号；下方 JS 里用它拼金额
$currencySymbol = Currency::visitorSymbol();

// 游客查单模式配置（开关/占位提示等）
$gfConfig = GuestFindModel::getConfig();
$prefix = Database::prefix();

/**
 * 订单列白名单：对外返回的字段，避免泄漏 order_password / admin_remark / payment 内部字段。
 */
$orderWhiteList = [
    'id', 'order_no', 'user_id', 'guest_token',
    'goods_amount', 'discount_amount', 'pay_amount',
    'payment_code', 'payment_name',
    'status', 'pay_time', 'delivery_time', 'complete_time',
    'remark', 'created_at', 'updated_at',
    'display_currency_code', 'display_rate',
    'shipping_address_snapshot',
];

/**
 * 对一行订单做字段白名单 + 金额显示字段拼接。
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
$sanitizeOrder = function (array $row) use ($orderWhiteList, $prefix): array {
    $out = [];
    foreach ($orderWhiteList as $k) {
        if (array_key_exists($k, $row)) $out[$k] = $row[$k];
    }
    // 金额按下单时冻结的展示货币 + 汇率渲染，历史不漂移
    $dispCode = (string) ($row['display_currency_code'] ?? '');
    $dispRate = (int) ($row['display_rate'] ?? 0);
    $out['pay_amount_display'] = Currency::displaySnapshot((int) ($row['pay_amount'] ?? 0), $dispCode, $dispRate, false);
    $out['goods_amount_display'] = Currency::displaySnapshot((int) ($row['goods_amount'] ?? 0), $dispCode, $dispRate, false);
    // 供视图层单独取符号拼接用
    $out['_disp_code'] = $dispCode;
    $out['_disp_rate'] = $dispRate;

    // 关联的订单商品（商品字段对外安全，保持原样即可）
    $out['order_goods'] = Database::query(
        "SELECT id, order_id, goods_id, spec_id, goods_title, spec_name, cover_image,
                price, quantity, goods_type, delivery_content, delivery_at, created_at
         FROM {$prefix}order_goods WHERE order_id = ?",
        [(int) ($row['id'] ?? 0)]
    );
    return $out;
};

/**
 * 判断给定凭据是否匹配订单的 contact_info。
 * contact_info 可能是纯字符串（游客联系方式）或 JSON（商品附加选项字典）。
 */
$contactMatch = function (array $row, string $contactQuery): bool {
    if ($contactQuery === '') return false;
    $stored = (string) ($row['contact_info'] ?? '');
    if ($stored === '') return false;
    $decoded = json_decode($stored, true);
    if (is_array($decoded)) {
        foreach ($decoded as $v) {
            if (stripos((string) $v, $contactQuery) !== false) return true;
        }
        return false;
    }
    return stripos($stored, $contactQuery) !== false;
};

// ------------------------- AJAX 查询处理 -------------------------
if (Request::isPost()) {
    header('Content-Type: application/json; charset=utf-8');

    $mode = (string) Input::post('mode', '');
    $action = (string) Input::post('action', '');

    // —— 子动作：刷新算术验证码（用户点"换一题"时调用）
    // 不计入限流（仅签发新题，不查订单）
    if ($action === 'refresh_captcha') {
        echo json_encode([
            'code' => 200,
            'data' => ['expr' => Captcha::issue('find_order')],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $orderNo = trim((string) Input::post('order_no', ''));
    $guestTokenPost = trim((string) Input::post('guest_token', ''));
    $contactQuery = trim((string) Input::post('contact_query', ''));
    $orderPassword = trim((string) Input::post('order_password', ''));
    $captchaInput = trim((string) Input::post('captcha', ''));

    try {
        $result = [];

        // —— credentials / orderno 模式必须过两层防护：
        //   1) IP 限流（撞库的真正防线）：每分钟 ≤ 10 次
        //   2) 算术 captcha（人类友好门槛，过滤无脑脚本）
        // token 模式跳过：浏览器 cookie 里的 guest_token 等同于已认证身份，自身就是凭据
        if ($mode === 'credentials' || $mode === 'orderno') {
            $rlKey = 'find_order:' . RateLimit::clientIp();
            if (RateLimit::tooManyAttempts($rlKey, 10)) {
                $wait = RateLimit::availableIn($rlKey);
                // 限流命中也刷新一次 captcha，避免攻击者用同一个答案做 retry burst
                Captcha::issue('find_order');
                throw new RuntimeException("请求过于频繁，请 {$wait} 秒后再试");
            }
            // 先记 hit（即使 captcha 错也算一次，这样答错+重试也吃配额，逼着攻击者降速）
            RateLimit::hit($rlKey, 60);

            if (!Captcha::verify($captchaInput, 'find_order')) {
                // 答错时返回一道新题，前端要刷新显示
                echo json_encode([
                    'code' => 400,
                    'msg' => '验证码错误，请重试',
                    'data' => ['captcha_expr' => Captcha::issue('find_order')],
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        if ($mode === 'token') {
            // —— tab1：通过浏览器 guest_token 列近 10 单
            if ($guestTokenPost === '') {
                throw new RuntimeException('缺少游客身份令牌');
            }
            $rows = Database::query(
                "SELECT * FROM {$prefix}order WHERE guest_token = ? AND user_id = 0 ORDER BY id DESC LIMIT 10",
                [$guestTokenPost]
            );
            if (empty($rows)) {
                throw new RuntimeException('未找到订单');
            }
            foreach ($rows as $r) $result[] = $sanitizeOrder($r);

        } elseif ($mode === 'credentials') {
            // —— tab2：凭据查单（无订单号）
            // 联系方式 / 订单密码 至少填一项；都填时 AND 精确匹配
            if ($contactQuery === '' && $orderPassword === '') {
                throw new RuntimeException('请输入查单凭据');
            }
            if ($contactQuery !== '' && !$gfConfig['contact_enabled']) {
                throw new RuntimeException('联系方式查单未开启');
            }
            if ($orderPassword !== '' && !$gfConfig['password_enabled']) {
                throw new RuntimeException('订单密码查单未开启');
            }

            // 先用 order_password 精确过滤（如填了）；contact_query 交由 PHP 侧 LIKE 匹配
            // （contact_info 存储可能是 JSON，所以不在 SQL 层做模糊匹配）
            $where = ['user_id = 0'];
            $params = [];
            if ($orderPassword !== '') {
                $where[] = 'order_password = ?';
                $params[] = $orderPassword;
            }
            if ($contactQuery !== '') {
                $where[] = 'contact_info IS NOT NULL AND contact_info != \'\'';
            }
            $sql = "SELECT * FROM {$prefix}order WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT 50";
            $rows = Database::query($sql, $params);

            // 填了联系方式则按 contact_info 再过滤
            if ($contactQuery !== '') {
                $rows = array_values(array_filter($rows, function ($r) use ($contactMatch, $contactQuery) {
                    return $contactMatch($r, $contactQuery);
                }));
            }
            if (empty($rows)) {
                throw new RuntimeException('未找到匹配订单');
            }
            foreach ($rows as $r) $result[] = $sanitizeOrder($r);

        } elseif ($mode === 'orderno') {
            // —— tab3：仅订单编号查单（无需凭据）
            if ($orderNo === '') {
                throw new RuntimeException('请输入订单编号');
            }
            $row = Database::fetchOne(
                "SELECT * FROM {$prefix}order WHERE order_no = ? LIMIT 1",
                [$orderNo]
            );
            if (!$row) {
                throw new RuntimeException('未找到该订单');
            }
            $result[] = $sanitizeOrder($row);

        } else {
            throw new RuntimeException('无效的查询方式');
        }

        // 查询成功：把命中订单号加入 session"已授权可看详情"白名单。
        // 详情页 GET ?order_no=xxx 必须命中白名单才能访问，避免攻击者绕过 POST 路径上的
        // captcha + ratelimit，直接遍历订单号偷数据。
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $visible = $_SESSION['em_find_order_visible'] ?? [];
        foreach ($result as $r) {
            $no = (string) ($r['order_no'] ?? '');
            if ($no !== '') $visible[$no] = time();
        }
        // 防 session 无限膨胀：保留 7 天内访问过的，过期的清掉
        $cutoff = time() - 7 * 86400;
        foreach ($visible as $no => $t) {
            if ($t < $cutoff) unset($visible[$no]);
        }
        $_SESSION['em_find_order_visible'] = $visible;

        echo json_encode(['code' => 200, 'data' => $result], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        // 失败时给 captcha 模式补发一道新题（已 consume，不发新的会让用户误以为还能用旧值）
        $errPayload = ['code' => 400, 'msg' => $e->getMessage()];
        if ($mode === 'credentials' || $mode === 'orderno') {
            $errPayload['data'] = ['captcha_expr' => Captcha::issue('find_order')];
        }
        echo json_encode($errPayload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ------------------------- 页面渲染 -------------------------
// 注意：不使用 index_public.php（公共页头页尾），这里自带最简壳
$guestToken = GuestToken::get();
$esc = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

// 当前 tab：?tab=token / credentials / orderno；缺省 = token（浏览器订单）
// 走 URL 参数而不是单页 tab，是为了让"刷新页面"能停在用户当前所在的查询方式，
// 同时配合 PJAX 切 tab 不整页刷新
$validTabs = ['token', 'credentials', 'orderno'];
$currentTab = (string) Input::get('tab', 'token');
if (!in_array($currentTab, $validTabs, true)) $currentTab = 'token';

// credentials/orderno tab 没在配置开启时降级到 token
$contactOn = !empty($gfConfig['contact_enabled']);
$passwordOn = !empty($gfConfig['password_enabled']);
if (($currentTab === 'credentials' || $currentTab === 'orderno') && !$contactOn && !$passwordOn) {
    $currentTab = 'token';
}

// 初始 captcha：只有进入 credentials/orderno 时才签发，token tab 不要 captcha
// 注意：每次页面渲染（含 PJAX 切 tab）都会重新签发，自然替换旧题
$captchaExpr = '';
if ($currentTab === 'credentials' || $currentTab === 'orderno') {
    $captchaExpr = Captcha::issue('find_order');
}

// GET ?order_no=xxx → 渲染单订单详情（游客场景，无侧边栏）
// 安全约束：必须先经过 POST 查询（受 captcha + ratelimit 保护）拿到这个订单号，
// 才会被写入 session 白名单，详情页才允许显示。
// 直接拼链接访问 / 复制别人的订单号 → 白名单不命中 → 走拒绝分支，
// 拒绝时不区分"订单不存在"和"订单存在但你无权看"，避免被用作存在性探测。
$detailOrder = null;
$detailGoods = [];
$detailDenied = false;
$detailOrderNo = trim((string) Input::get('order_no', ''));
if ($detailOrderNo !== '') {
    if (session_status() === PHP_SESSION_NONE) @session_start();
    $visible = $_SESSION['em_find_order_visible'] ?? [];
    if (!isset($visible[$detailOrderNo])) {
        $detailDenied = true;
    } else {
        $row = Database::fetchOne(
            "SELECT * FROM {$prefix}order WHERE order_no = ? LIMIT 1",
            [$detailOrderNo]
        );
        if ($row) {
            $detailOrder = $sanitizeOrder($row);
            $detailGoods = $detailOrder['order_goods'] ?? [];
        } else {
            // 白名单写入后订单又被删除的边角情况：也走拒绝分支，对外表现一致
            $detailDenied = true;
        }
    }
}

// 订单状态中文 + 配色
$statusMap = [
    'pending'    => ['label' => '待付款', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
    'paid'       => ['label' => '已付款', 'color' => '#2563eb', 'bg' => '#dbeafe'],
    'delivering' => ['label' => '发货中', 'color' => '#6366f1', 'bg' => '#e0e7ff'],
    'delivered'  => ['label' => '已发货', 'color' => '#8b5cf6', 'bg' => '#ede9fe'],
    'completed'  => ['label' => '已完成', 'color' => '#059669', 'bg' => '#d1fae5'],
    'refunding'  => ['label' => '退款中', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
    'refunded'   => ['label' => '已退款', 'color' => '#6b7280', 'bg' => '#f3f4f6'],
    'cancelled'  => ['label' => '已取消', 'color' => '#9ca3af', 'bg' => '#f3f4f6'],
    'expired'    => ['label' => '已过期', 'color' => '#9ca3af', 'bg' => '#f3f4f6'],
    'failed'     => ['label' => '失败',   'color' => '#e11d48', 'bg' => '#ffe4e6'],
];
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>查询订单 - <?= $esc($siteName) ?></title>
    <link rel="stylesheet" href="/content/static/lib/font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="/content/static/lib/layui-v2.13.5/layui/css/layui.css">
    <!-- 独立页专属样式：自给自足，不依赖 user.css / 模板 style.css -->
    <link rel="stylesheet" href="/content/static/css/find_order.css">
    <script src="/content/static/lib/jquery.min.3.5.1.js"></script>
    <script src="/content/static/lib/jquery.pjax.js"></script>
    <script src="/content/static/lib/layui-v2.13.5/layui/layui.js"></script>
    <script src="<?= $esc(theme_asset_url('guest_find.js', active_theme_name('test'))) ?>"></script>
</head>
<body>

<!-- 顶部栏（参考个人中心 uc-header v2 样式） -->
<header class="fo-header">
    <div class="fo-header-inner">
        <a href="/" class="fo-header-logo">
            <?php if ($siteLogoType === 'image' && $siteLogo !== ''): ?>
            <img src="<?= $esc($siteLogo) ?>" alt="<?= $esc($siteName) ?>" class="fo-header-logo__img">
            <?php else: ?>
            <span class="fo-header-logo__text"><?= $esc($siteName) ?></span>
            <?php endif; ?>
        </a>
        <span class="fo-header-title"><i class="fa fa-search"></i> 订单查询</span>
        <div class="fo-header-right">
            <a href="/" class="fo-header-btn"><i class="fa fa-home"></i> 返回首页</a>
        </div>
    </div>
</header>

<main id="foContent" class="fo-wrap">
<?php if ($detailOrder !== null):
    $st = $statusMap[$detailOrder['status']] ?? ['label' => $detailOrder['status'], 'color' => '#6b7280', 'bg' => '#f3f4f6'];
?>
    <!-- 订单详情（GET ?order_no=xxx） -->
    <div class="fo-card fo-detail">
        <div class="fo-detail__header">
            <div class="fo-detail__title">
                <i class="fa fa-file-text-o"></i> 订单详情
            </div>
            <a href="/user/find_order.php" data-pjax class="fo-detail__back"><i class="fa fa-angle-left"></i> 返回查单</a>
        </div>

        <div class="fo-detail__meta">
            <div class="fo-detail__row">
                <span class="fo-detail__label">订单编号</span>
                <span class="fo-detail__value"><?= $esc($detailOrder['order_no']) ?></span>
            </div>
            <div class="fo-detail__row">
                <span class="fo-detail__label">订单状态</span>
                <span class="fo-status-pill" style="color:<?= $st['color'] ?>;background:<?= $st['bg'] ?>;"><?= $esc($st['label']) ?></span>
            </div>
            <div class="fo-detail__row">
                <span class="fo-detail__label">下单时间</span>
                <span class="fo-detail__value"><?= $esc((string) ($detailOrder['created_at'] ?? '')) ?></span>
            </div>
            <?php if (!empty($detailOrder['pay_time'])): ?>
            <div class="fo-detail__row">
                <span class="fo-detail__label">支付时间</span>
                <span class="fo-detail__value"><?= $esc((string) $detailOrder['pay_time']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($detailOrder['payment_name'])): ?>
            <div class="fo-detail__row">
                <span class="fo-detail__label">支付方式</span>
                <span class="fo-detail__value"><?= $esc((string) $detailOrder['payment_name']) ?></span>
            </div>
            <?php endif; ?>
            <div class="fo-detail__row">
                <span class="fo-detail__label">订单金额</span>
                <span class="fo-detail__value fo-detail__amount"><?= Currency::displaySnapshot((int) ($detailOrder['pay_amount'] ?? 0), (string) ($detailOrder['_disp_code'] ?? ''), (int) ($detailOrder['_disp_rate'] ?? 0)) ?></span>
            </div>
        </div>

        <?php
        // 收货地址快照（仅需要地址的订单有值）
        $detailShipAddr = null;
        if (!empty($detailOrder['shipping_address_snapshot'])) {
            $detailShipAddr = json_decode((string) $detailOrder['shipping_address_snapshot'], true);
        }
        ?>
        <?php if (is_array($detailShipAddr) && !empty($detailShipAddr['recipient'])): ?>
        <div class="fo-detail__section">
            <div class="fo-detail__section-title"><i class="fa fa-map-marker"></i> 收货地址</div>
            <div style="line-height:1.7;">
                <div>
                    <strong><?= $esc((string) $detailShipAddr['recipient']) ?></strong>
                    <span style="color:#666;margin-left:10px;"><?= $esc((string) ($detailShipAddr['mobile'] ?? '')) ?></span>
                </div>
                <div style="color:#555;">
                    <?= $esc(trim(($detailShipAddr['province'] ?? '') . ' ' . ($detailShipAddr['city'] ?? '') . ' ' . ($detailShipAddr['district'] ?? ''))) ?>
                    <?= $esc((string) ($detailShipAddr['detail'] ?? '')) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($detailGoods)): ?>
        <?php
        // 商品行价格也走订单快照（同订单内币种一致）
        $dispCode = (string) ($detailOrder['_disp_code'] ?? '');
        $dispRate = (int) ($detailOrder['_disp_rate'] ?? 0);
        ?>
        <div class="fo-detail__section">
            <div class="fo-detail__section-title">商品明细</div>
            <?php foreach ($detailGoods as $g): ?>
            <div class="fo-detail__goods">
                <?php if (!empty($g['cover_image'])): ?>
                <img class="fo-detail__goods-cover" src="<?= $esc((string) $g['cover_image']) ?>" alt="">
                <?php else: ?>
                <div class="fo-detail__goods-cover fo-detail__goods-cover--empty"><i class="fa fa-image"></i></div>
                <?php endif; ?>
                <div class="fo-detail__goods-body">
                    <div class="fo-detail__goods-title"><?= $esc((string) $g['goods_title']) ?></div>
                    <?php if (!empty($g['spec_name'])): ?>
                    <div class="fo-detail__goods-spec">规格：<?= $esc((string) $g['spec_name']) ?></div>
                    <?php endif; ?>
                    <?php
                    // 发货内容交给插件渲染（卡密类走分卡片 + 单条复制 + 导出）
                    $pluginDeliveryHtml = (string) applyFilter('frontend_order_goods_delivery_html', '', $g);
                    ?>
                    <?php if ($pluginDeliveryHtml !== ''): ?>
                        <?= $pluginDeliveryHtml ?>
                    <?php elseif (!empty($g['delivery_content'])): ?>
                    <div class="fo-detail__delivery">
                        <div class="fo-detail__delivery-label"><i class="fa fa-key"></i> 发货内容</div>
                        <pre class="fo-detail__delivery-content"><?= $esc((string) $g['delivery_content']) ?></pre>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="fo-detail__goods-price">
                    <?= Currency::displaySnapshot((int) $g['price'], $dispCode, $dispRate) ?>
                    × <?= (int) $g['quantity'] ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($detailOrder['remark'])): ?>
        <div class="fo-detail__section">
            <div class="fo-detail__section-title">订单备注</div>
            <div class="fo-detail__remark"><?= $esc((string) $detailOrder['remark']) ?></div>
        </div>
        <?php endif; ?>
    </div>
<?php elseif ($detailDenied): ?>
    <!-- 拒绝直链访问：订单存在性故意不暴露，统一文案引导走查询入口 -->
    <div class="fo-card fo-detail">
        <div class="fo-detail__header">
            <div class="fo-detail__title"><i class="fa fa-lock"></i> 无法直接访问订单详情</div>
            <a href="/user/find_order.php" data-pjax class="fo-detail__back"><i class="fa fa-angle-left"></i> 去查询</a>
        </div>
        <div style="padding:24px 28px;color:#475569;line-height:1.8;">
            <p style="margin-bottom:8px;">为保护买家隐私，订单详情不能通过链接直接打开。</p>
            <p style="color:#94a3b8;font-size:13px;">请回到查单页，使用浏览器订单 / 联系方式 / 订单密码 / 订单编号 任一种方式查询，从结果中点击「查看详情」即可正常查看。</p>
        </div>
    </div>
<?php elseif ($detailOrderNo !== ''): ?>
    <!-- 兜底（白名单命中但订单已被硬删等极端情况）—— 文案与 detailDenied 保持一致 -->
    <div class="fo-card fo-detail">
        <div class="fo-detail__header">
            <div class="fo-detail__title"><i class="fa fa-lock"></i> 无法直接访问订单详情</div>
            <a href="/user/find_order.php" data-pjax class="fo-detail__back"><i class="fa fa-angle-left"></i> 去查询</a>
        </div>
        <div style="padding:24px 28px;color:#475569;line-height:1.8;">
            <p style="margin-bottom:8px;">订单不存在或已被删除。</p>
            <p style="color:#94a3b8;font-size:13px;">请回到查单页重新查询。</p>
        </div>
    </div>
<?php else: ?>
    <!-- 查单列表（默认）：hero 标题 + 三 tab 查询面板 -->
    <div class="fo-card">
        <div class="fo-hero">
            <div class="fo-hero__title">订单查询</div>
            <div class="fo-hero__sub">输入订单号或联系方式查看订单状态</div>
        </div>
        <?php include __DIR__ . '/view/find_order.php'; ?>
    </div>
<?php endif; ?>
</main>

<!-- PJAX Loading -->
<div class="fo-loading" id="foLoading"><div class="fo-loading-spinner"></div></div>

<script>
(function(){
    // 详情链接 + 返回查单链接 走 PJAX，仅替换 #foContent
    $(document).pjax('a[data-pjax]', '#foContent', {
        fragment: '#foContent', timeout: 8000, scrollTo: 0
    });
    var $loading = $('#foLoading');
    $(document).on('pjax:send', function(){ $loading.addClass('is-active'); });
    $(document).on('pjax:complete pjax:error', function(){ $loading.removeClass('is-active'); });
})();
</script>

</body>
</html>
