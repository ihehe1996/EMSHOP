<?php
/**
 * 用户中心 - 概览首页（重设计版）
 */
$displayMoney = $displayMoney ?? '0.00';
$currencySymbol = $currencySymbol ?? '¥';

$prefix = Database::prefix();
$userId = (int) ($frontUser['id'] ?? 0);

// ---------- 统计数据（真实查询，失败兜底 0） ----------
$stats = [
    'order_total'    => 0,
    'order_pending'  => 0,
    'order_delivering' => 0,
    'order_completed'=> 0,
    'month_spent'    => 0,  // 本月消费（×1000000）
    'today_spent'    => 0,  // 今日消费
];
try {
    $row = Database::fetchOne(
        "SELECT COUNT(*) AS total,
                SUM(status='pending') AS pending,
                SUM(status IN ('paid','delivering','delivered')) AS delivering,
                SUM(status='completed') AS completed
           FROM `{$prefix}order` WHERE user_id = ?",
        [$userId]
    );
    if ($row) {
        $stats['order_total']      = (int) $row['total'];
        $stats['order_pending']    = (int) $row['pending'];
        $stats['order_delivering'] = (int) $row['delivering'];
        $stats['order_completed']  = (int) $row['completed'];
    }

    $monthRow = Database::fetchOne(
        "SELECT COALESCE(SUM(pay_amount), 0) AS s
           FROM `{$prefix}order`
          WHERE user_id = ? AND status IN ('paid','delivering','delivered','completed')
            AND DATE_FORMAT(pay_time, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')",
        [$userId]
    );
    $stats['month_spent'] = (int) ($monthRow['s'] ?? 0);

    $todayRow = Database::fetchOne(
        "SELECT COALESCE(SUM(pay_amount), 0) AS s
           FROM `{$prefix}order`
          WHERE user_id = ? AND status IN ('paid','delivering','delivered','completed')
            AND DATE(pay_time) = CURDATE()",
        [$userId]
    );
    $stats['today_spent'] = (int) ($todayRow['s'] ?? 0);
} catch (Throwable $e) {
    // 静默兜底
}

// ---------- 最近订单（最多 5 条） ----------
$recentOrders = [];
try {
    $recentOrders = Database::query(
        "SELECT id, order_no, pay_amount, status, created_at, display_currency_code, display_rate
           FROM `{$prefix}order`
          WHERE user_id = ?
          ORDER BY id DESC LIMIT 5",
        [$userId]
    );
} catch (Throwable $e) {}

$statusLabels = [
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

// 时间问候
$hour = (int) date('G');
if ($hour < 5)       $greet = '夜深了';
elseif ($hour < 11)  $greet = '早上好';
elseif ($hour < 13)  $greet = '中午好';
elseif ($hour < 18)  $greet = '下午好';
else                 $greet = '晚上好';

$nickname = htmlspecialchars($frontUser['nickname'] ?? $frontUser['username'] ?? '用户');
// 金额统一走 Currency::displayAmount()：自动读访客 cookie → 前台默认货币（em_currency.is_frontend_default）→ 主货币
$fmtMoney = static fn(int $raw): string => Currency::displayAmount($raw);
?>
<div class="uc-page uc-home">

    <!-- ========= Hero 欢迎卡 ========= -->
    <section class="uc-hero">
        <div class="uc-hero__bg"></div>
        <div class="uc-hero__body">
            <div class="uc-hero__left">
                <div class="uc-hero__greet"><?= $greet ?>，<?= $nickname ?> <span class="uc-hero__wave">👋</span></div>
                <div class="uc-hero__desc">欢迎回到个人中心 · 祝你今天购物愉快</div>
                <div class="uc-hero__stats">
                    <div class="uc-hero__stat">
                        <div class="uc-hero__stat-label">账户余额</div>
                        <div class="uc-hero__stat-value"><?= Currency::displayAmount((int) ($frontUser['money'] ?? 0)) ?></div>
                    </div>
                    <div class="uc-hero__stat">
                        <div class="uc-hero__stat-label">今日消费</div>
                        <div class="uc-hero__stat-value"><?= $fmtMoney((int) $stats['today_spent']) ?></div>
                    </div>
                    <div class="uc-hero__stat">
                        <div class="uc-hero__stat-label">本月消费</div>
                        <div class="uc-hero__stat-value"><?= $fmtMoney((int) $stats['month_spent']) ?></div>
                    </div>
                </div>
            </div>
            <div class="uc-hero__right">
                <a href="/" class="uc-hero__btn uc-hero__btn--primary"><i class="fa fa-shopping-bag"></i> 去逛逛</a>
                <a href="/user/wallet.php" data-pjax class="uc-hero__btn"><i class="fa fa-credit-card"></i> 充值</a>
            </div>
        </div>
    </section>

    <!-- ========= 订单状态 4 卡 ========= -->
    <section class="uc-metrics">
        <a href="/user/order.php" data-pjax class="uc-metric">
            <div class="uc-metric__icon uc-metric__icon--indigo"><i class="fa fa-shopping-bag"></i></div>
            <div class="uc-metric__body">
                <div class="uc-metric__label">全部订单</div>
                <div class="uc-metric__value"><?= (int) $stats['order_total'] ?></div>
            </div>
        </a>
        <a href="/user/order.php?status=pending" data-pjax class="uc-metric">
            <div class="uc-metric__icon uc-metric__icon--amber"><i class="fa fa-hourglass-half"></i></div>
            <div class="uc-metric__body">
                <div class="uc-metric__label">待付款</div>
                <div class="uc-metric__value"><?= (int) $stats['order_pending'] ?></div>
            </div>
        </a>
        <a href="/user/order.php?status=delivering" data-pjax class="uc-metric">
            <div class="uc-metric__icon uc-metric__icon--blue"><i class="fa fa-truck"></i></div>
            <div class="uc-metric__body">
                <div class="uc-metric__label">待收货</div>
                <div class="uc-metric__value"><?= (int) $stats['order_delivering'] ?></div>
            </div>
        </a>
        <a href="/user/order.php?status=completed" data-pjax class="uc-metric">
            <div class="uc-metric__icon uc-metric__icon--emerald"><i class="fa fa-check-circle"></i></div>
            <div class="uc-metric__body">
                <div class="uc-metric__label">已完成</div>
                <div class="uc-metric__value"><?= (int) $stats['order_completed'] ?></div>
            </div>
        </a>
    </section>

    <!-- ========= 快捷操作 + 最近订单 并列 ========= -->
    <section class="uc-grid-2">
        <!-- 快捷操作 -->
        <div class="uc-card">
            <div class="uc-card__header">
                <div class="uc-card__title"><i class="fa fa-th"></i> 常用功能</div>
            </div>
            <div class="uc-quick">
                <a href="/user/profile.php" data-pjax class="uc-quick__item">
                    <span class="uc-quick__icon" style="background:#eef2ff;color:#6366f1;"><i class="fa fa-user"></i></span>
                    <span class="uc-quick__text">个人资料</span>
                </a>
                <a href="/user/order.php" data-pjax class="uc-quick__item">
                    <span class="uc-quick__icon" style="background:#fef3c7;color:#f59e0b;"><i class="fa fa-list-alt"></i></span>
                    <span class="uc-quick__text">我的订单</span>
                </a>
                <a href="/user/wallet.php" data-pjax class="uc-quick__item">
                    <span class="uc-quick__icon" style="background:#dbeafe;color:#2563eb;"><i class="fa fa-credit-card"></i></span>
                    <span class="uc-quick__text">我的钱包</span>
                </a>
                <a href="/user/balance_log.php" data-pjax class="uc-quick__item">
                    <span class="uc-quick__icon" style="background:#d1fae5;color:#059669;"><i class="fa fa-exchange"></i></span>
                    <span class="uc-quick__text">余额明细</span>
                </a>
                <a href="/user/coupon.php" data-pjax class="uc-quick__item">
                    <span class="uc-quick__icon" style="background:#fee2e2;color:#e11d48;"><i class="fa fa-ticket"></i></span>
                    <span class="uc-quick__text">优惠券</span>
                </a>
                <?php // 推广 / 返佣只在主站启用；商户子域名下隐藏入口 ?>
                <?php if (MerchantContext::currentId() === 0): ?>
                <a href="/user/rebate.php" data-pjax class="uc-quick__item">
                    <span class="uc-quick__icon" style="background:#ede9fe;color:#8b5cf6;"><i class="fa fa-share-alt"></i></span>
                    <span class="uc-quick__text">我的推广</span>
                </a>
                <?php endif; ?>
                <a href="/user/api.php" data-pjax class="uc-quick__item">
                    <span class="uc-quick__icon" style="background:#e0f2fe;color:#0ea5e9;"><i class="fa fa-plug"></i></span>
                    <span class="uc-quick__text">API 对接</span>
                </a>
                <a href="/user/find_order.php" class="uc-quick__item">
                    <span class="uc-quick__icon" style="background:#f3f4f6;color:#6b7280;"><i class="fa fa-search"></i></span>
                    <span class="uc-quick__text">订单查询</span>
                </a>
            </div>
        </div>

        <!-- 最近订单 -->
        <div class="uc-card">
            <div class="uc-card__header">
                <div class="uc-card__title"><i class="fa fa-history"></i> 最近订单</div>
                <a href="/user/order.php" data-pjax class="uc-card__more">查看全部 <i class="fa fa-angle-right"></i></a>
            </div>
            <?php if (empty($recentOrders)): ?>
                <div class="uc-empty">
                    <i class="fa fa-inbox"></i>
                    <p>暂无订单记录</p>
                    <a href="/" class="uc-empty__btn">去逛逛 <i class="fa fa-arrow-right"></i></a>
                </div>
            <?php else: ?>
                <div class="uc-order-list">
                    <?php foreach ($recentOrders as $o):
                        $st = $statusLabels[$o['status']] ?? ['label' => $o['status'], 'color' => '#6b7280', 'bg' => '#f3f4f6'];
                    ?>
                    <a href="/user/order_detail.php?order_no=<?= urlencode($o['order_no']) ?>" class="uc-order-list__item">
                        <div class="uc-order-list__main">
                            <div class="uc-order-list__no">#<?= htmlspecialchars($o['order_no']) ?></div>
                            <div class="uc-order-list__time"><?= htmlspecialchars($o['created_at']) ?></div>
                        </div>
                        <div class="uc-order-list__amount"><?= Currency::displaySnapshot((int) $o['pay_amount'], (string) ($o['display_currency_code'] ?? ''), (int) ($o['display_rate'] ?? 0)) ?></div>
                        <span class="uc-order-list__status" style="color:<?= $st['color'] ?>;background:<?= $st['bg'] ?>;"><?= $st['label'] ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
