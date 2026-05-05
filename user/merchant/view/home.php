<?php
/** @var array<string, mixed> $frontUser */
/** @var array<string, mixed> $currentMerchant */
/** @var array<string, mixed>|null $merchantLevel */
/** @var array<string, mixed> $uc */
/** @var int $orderTotal */
/** @var int $orderThisMonth */
/** @var int $orderPending */
/** @var string $salesThisMonth */
/** @var int $referencedGoods */
/** @var int $selfGoods */

$lv = $merchantLevel ?? [];
$showSelfGoods = (int) ($lv['allow_self_goods'] ?? 0) === 1;
$domainAlerts = $domainAlerts ?? [];
?>
<div class="mc-page">
    <div class="mc-page-header">
        <h2 class="mc-page-title">店铺概览</h2>
        <p class="mc-page-desc">欢迎回来，<?= htmlspecialchars($currentMerchant['name']) ?></p>
    </div>

    <?php foreach ($domainAlerts as $alert):
        $bg = $alert['type'] === 'warning' ? '#fff7ed' : '#eff6ff';
        $border = $alert['type'] === 'warning' ? '#fed7aa' : '#bfdbfe';
        $color = $alert['type'] === 'warning' ? '#c2410c' : '#1d4ed8';
        $icon = $alert['type'] === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
    ?>
    <div style="margin-bottom:14px;padding:12px 16px;background:<?= $bg ?>;border:1px solid <?= $border ?>;border-radius:6px;color:<?= $color ?>;font-size:13px;line-height:1.7;">
        <i class="fa <?= $icon ?>"></i> <?= htmlspecialchars($alert['msg']) ?>
        <a href="/user/merchant/settings.php" data-pjax="#merchantContent" style="color:<?= $color ?>;text-decoration:underline;margin-left:4px;">前往设置 →</a>
    </div>
    <?php endforeach; ?>

    <!-- 数据卡 -->
    <div class="mc-stat-grid">
        <div class="mc-stat-card">
            <div class="mc-stat-icon" style="background:#eef2ff;color:#4e6ef2;"><i class="fa fa-rmb"></i></div>
            <div class="mc-stat-body">
                <div class="mc-stat-label">店铺余额</div>
                <div class="mc-stat-value"><?= htmlspecialchars((string) $uc['shopBalance']) ?></div>
            </div>
        </div>
        <div class="mc-stat-card">
            <div class="mc-stat-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fa fa-line-chart"></i></div>
            <div class="mc-stat-body">
                <div class="mc-stat-label">本月销售额</div>
                <div class="mc-stat-value"><?= htmlspecialchars($uc['currencySymbol']) ?><?= htmlspecialchars($salesThisMonth) ?></div>
            </div>
        </div>
        <div class="mc-stat-card">
            <div class="mc-stat-icon" style="background:#fff7e6;color:#fa8c16;"><i class="fa fa-file-text-o"></i></div>
            <div class="mc-stat-body">
                <div class="mc-stat-label">本月订单</div>
                <div class="mc-stat-value"><?= $orderThisMonth ?></div>
            </div>
        </div>
        <div class="mc-stat-card">
            <div class="mc-stat-icon" style="background:#f0f5ff;color:#1890ff;"><i class="fa fa-shopping-bag"></i></div>
            <div class="mc-stat-body">
                <div class="mc-stat-label">累计订单</div>
                <div class="mc-stat-value"><?= $orderTotal ?></div>
            </div>
        </div>
        <div class="mc-stat-card">
            <div class="mc-stat-icon" style="background:#fef2f2;color:#dc2626;"><i class="fa fa-bell-o"></i></div>
            <div class="mc-stat-body">
                <div class="mc-stat-label">待处理订单</div>
                <div class="mc-stat-value"><?= $orderPending ?></div>
            </div>
        </div>
        <div class="mc-stat-card">
            <div class="mc-stat-icon" style="background:#ecfeff;color:#0891b2;"><i class="fa fa-cubes"></i></div>
            <div class="mc-stat-body">
                <div class="mc-stat-label">商品（主站 / 自建）</div>
                <div class="mc-stat-value"><?= $referencedGoods ?> / <?= $selfGoods ?></div>
            </div>
        </div>
    </div>

    <!-- 店铺信息 -->
    <div class="mc-section">
        <div class="mc-section-title">
            店铺信息
            <a href="/user/merchant/settings.php" data-pjax="#merchantContent" class="mc-section-action">编辑 →</a>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:16px;font-size:13px;color:#374151;">
            <div>
                <div style="color:#9ca3af;margin-bottom:4px;">店铺名称</div>
                <div><?= htmlspecialchars($currentMerchant['name']) ?></div>
            </div>
            <?php if (!empty($currentMerchant['subdomain'])): ?>
            <div>
                <div style="color:#9ca3af;margin-bottom:4px;">二级域名</div>
                <div style="font-family:Consolas,Monaco,monospace;"><?= htmlspecialchars($currentMerchant['subdomain']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($currentMerchant['custom_domain'])): ?>
            <div>
                <div style="color:#9ca3af;margin-bottom:4px;">自定义域名</div>
                <div style="font-family:Consolas,Monaco,monospace;">
                    <?= htmlspecialchars($currentMerchant['custom_domain']) ?>
                    <?php if ((int) $currentMerchant['domain_verified'] === 1): ?>
                    <span class="layui-badge layui-bg-green" style="margin-left:6px;">已验证</span>
                    <?php else: ?>
                    <span class="layui-badge layui-bg-gray" style="margin-left:6px;">未验证</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($merchantLevel)): ?>
            <div>
                <div style="color:#9ca3af;margin-bottom:4px;">商户等级</div>
                <div><?= htmlspecialchars($merchantLevel['name']) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 权限总览 -->
    <?php if (!empty($merchantLevel)): ?>
    <div class="mc-section">
        <div class="mc-section-title">当前等级权限</div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;font-size:12px;">
            <?php
            $badgeStyle = function (bool $on): string {
                return $on
                    ? 'background:#dcfce7;color:#166534;'
                    : 'background:#f3f4f6;color:#9ca3af;';
            };
            $badges = [
                ['二级域名',       (int) $merchantLevel['allow_subdomain'] === 1],
                ['自定义顶级域名', (int) $merchantLevel['allow_custom_domain'] === 1],
                ['自建商品',       (int) $merchantLevel['allow_self_goods'] === 1],
            ];
            foreach ($badges as $b):
            ?>
            <span style="padding:4px 10px;border-radius:12px;<?= $badgeStyle($b[1]) ?>">
                <?= $b[1] ? '✓' : '×' ?> <?= htmlspecialchars($b[0]) ?>
            </span>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:12px;color:#6b7280;font-size:12px;line-height:1.7;">
            自建手续费 <strong><?= rtrim(rtrim(number_format(((int) $merchantLevel['self_goods_fee_rate']) / 100, 2, '.', ''), '0'), '.') ?>%</strong>
            · 提现手续费 <strong><?= rtrim(rtrim(number_format(((int) $merchantLevel['withdraw_fee_rate']) / 100, 2, '.', ''), '0'), '.') ?>%</strong>
        </div>
    </div>
    <?php endif; ?>

    <!-- 快捷入口 -->
    <div class="mc-section">
        <div class="mc-section-title">快捷操作</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(140px, 1fr));gap:10px;">
            <a href="/user/merchant/goods.php" data-pjax="#merchantContent" class="uc-quick-item">
                <i class="fa fa-shopping-bag"></i><span>商品管理</span>
            </a>
            <a href="/user/merchant/order.php" data-pjax="#merchantContent" class="uc-quick-item">
                <i class="fa fa-file-text-o"></i><span>订单管理</span>
            </a>
            <a href="/user/merchant/finance.php" data-pjax="#merchantContent" class="uc-quick-item">
                <i class="fa fa-list-alt"></i><span>余额明细</span>
            </a>
            <a href="/user/merchant/withdraw.php" data-pjax="#merchantContent" class="uc-quick-item">
                <i class="fa fa-credit-card"></i><span>申请提现</span>
            </a>
        </div>
    </div>
</div>
