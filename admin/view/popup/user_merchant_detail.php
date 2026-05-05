<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

/** @var array<string, mixed> $merchantDetail 商户主记录 (em_merchant.*) */
/** @var array<string, mixed>|null $merchantLevel 商户所在等级 (em_merchant_level.*) */
/** @var array<string, mixed>|null $merchantOwner 商户主用户 (em_user 子集) */
/** @var string $storefrontUrl 店铺前台 URL（空串 = 尚未配置可访问域名） */

$esc = function (?string $s): string {
    return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES, 'UTF-8');
};
$fmtMoney = function ($raw): string {
    return number_format(((int) $raw) / 1000000, 2, '.', '');
};
$fmtTime = function (?string $t): string {
    if (!$t) return '—';
    return str_replace('T', ' ', substr($t, 0, 19));
};
$openedViaMap = [
    'admin' => '管理员开通',
    'self'  => '用户自助开通',
];
// 主站域名；拼完整二级域名链接要用
$mainDomain = trim((string) (Config::get('main_domain') ?? ''));

include __DIR__ . '/header.php';
?>

<style>
/* 详情行：label 固定宽，value 自适应；每行下划线分隔 */
.mch-info-row {
    display: flex;
    align-items: flex-start;
    padding: 9px 0;
    font-size: 13px;
    border-bottom: 1px dashed #f0f2f5;
    line-height: 1.6;
}
.mch-info-row:last-child { border-bottom: none; padding-bottom: 2px; }
.mch-info-row__label {
    flex: 0 0 96px;
    color: #6b7280;
    padding-top: 1px;
}
.mch-info-row__value {
    flex: 1;
    min-width: 0;
    color: #1f2937;
    word-break: break-all;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
}
.mch-info-row__value--muted {
    color: #9ca3af;
    font-style: italic;
}
.mch-info-row__value code {
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    padding: 1px 6px;
    font-family: Menlo, Consolas, monospace;
    font-size: 12.5px;
    color: #374151;
}
.mch-info-row__value a {
    color: #2563eb;
    text-decoration: none;
}
.mch-info-row__value a:hover { text-decoration: underline; }
.mch-info-row__value .mch-logo {
    max-width: 72px;
    max-height: 72px;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
    background: #fff;
    object-fit: contain;
    padding: 2px;
}
.mch-info-row__value pre.mch-desc {
    margin: 0;
    padding: 0;
    font-family: inherit;
    font-size: 13px;
    white-space: pre-wrap;
    color: #374151;
}
</style>

<div class="popup-inner">
    <!-- 基础信息 -->
    <div class="popup-section">
        <div class="mch-info-row">
            <div class="mch-info-row__label">店铺名</div>
            <div class="mch-info-row__value"><?= $esc($merchantDetail['name'] ?? '') ?></div>
        </div>
        <div class="mch-info-row">
            <div class="mch-info-row__label">商户等级</div>
            <div class="mch-info-row__value">
                <span class="em-tag em-tag--purple"><?= $esc($merchantLevel['name'] ?? '未知等级') ?></span>
            </div>
        </div>
        <div class="mch-info-row">
            <div class="mch-info-row__label">启用状态</div>
            <div class="mch-info-row__value">
                <?php if ((int) ($merchantDetail['status'] ?? 0) === 1): ?>
                <span class="em-tag em-tag--on">已启用</span>
                <?php else: ?>
                <span class="em-tag em-tag--red">已禁用</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="mch-info-row">
            <div class="mch-info-row__label">开通方式</div>
            <div class="mch-info-row__value">
                <span class="em-tag em-tag--blue"><?= $esc($openedViaMap[$merchantDetail['opened_via'] ?? ''] ?? '—') ?></span>
            </div>
        </div>
        <div class="mch-info-row">
            <div class="mch-info-row__label">开通时间</div>
            <div class="mch-info-row__value"><?= $esc($fmtTime($merchantDetail['opened_at'] ?? null)) ?></div>
        </div>
    </div>

    <!-- 商户主 -->
    <?php if ($merchantOwner !== null): ?>
    <div class="popup-section">
        <div class="mch-info-row">
            <div class="mch-info-row__label">账号</div>
            <div class="mch-info-row__value">
                <?= $esc(($merchantOwner['nickname'] ?: $merchantOwner['username'])) ?>
                <span style="color:#9ca3af;">(ID: <?= (int) $merchantOwner['id'] ?>)</span>
            </div>
        </div>
        <div class="mch-info-row">
            <div class="mch-info-row__label">邮箱</div>
            <div class="mch-info-row__value">
                <?php if (!empty($merchantOwner['email'])): ?>
                <?= $esc($merchantOwner['email']) ?>
                <?php else: ?>
                <span class="mch-info-row__value--muted">未填写</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="mch-info-row">
            <div class="mch-info-row__label">手机</div>
            <div class="mch-info-row__value">
                <?php if (!empty($merchantOwner['mobile'])): ?>
                <?= $esc($merchantOwner['mobile']) ?>
                <?php else: ?>
                <span class="mch-info-row__value--muted">未填写</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="mch-info-row">
            <div class="mch-info-row__label">店铺余额</div>
            <div class="mch-info-row__value" style="color:#fa5252;font-weight:600;">
                ¥ <?= $esc($fmtMoney($merchantOwner['shop_balance'] ?? 0)) ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 店铺展示 -->
    <div class="popup-section">
        <div class="mch-info-row">
            <div class="mch-info-row__label">Logo</div>
            <div class="mch-info-row__value">
                <?php if (!empty($merchantDetail['logo'])): ?>
                <img src="<?= $esc($merchantDetail['logo']) ?>" alt="logo" class="mch-logo">
                <?php else: ?>
                <span class="mch-info-row__value--muted">未设置</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="mch-info-row">
            <div class="mch-info-row__label">Slogan</div>
            <div class="mch-info-row__value">
                <?php if (!empty($merchantDetail['slogan'])): ?>
                <?= $esc($merchantDetail['slogan']) ?>
                <?php else: ?>
                <span class="mch-info-row__value--muted">未设置</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="mch-info-row">
            <div class="mch-info-row__label">简介</div>
            <div class="mch-info-row__value">
                <?php if (!empty($merchantDetail['description'])): ?>
                <pre class="mch-desc"><?= $esc($merchantDetail['description']) ?></pre>
                <?php else: ?>
                <span class="mch-info-row__value--muted">未填写</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="mch-info-row">
            <div class="mch-info-row__label">备案号</div>
            <div class="mch-info-row__value">
                <?php if (!empty($merchantDetail['icp'])): ?>
                <?= $esc($merchantDetail['icp']) ?>
                <?php else: ?>
                <span class="mch-info-row__value--muted">未填写</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 域名 -->
    <div class="popup-section">
        <div class="mch-info-row">
            <div class="mch-info-row__label">二级域名</div>
            <div class="mch-info-row__value">
                <?php if (!empty($merchantDetail['subdomain'])): ?>
                    <?php if ($mainDomain !== ''): ?>
                    <?php $subFull = $merchantDetail['subdomain'] . '.' . $mainDomain; ?>
                    <code><?= $esc($subFull) ?></code>
                    <a href="http://<?= $esc($subFull) ?>/" target="_blank" title="在新窗口打开"><i class="fa fa-external-link"></i></a>
                    <?php else: ?>
                    <code><?= $esc($merchantDetail['subdomain']) ?></code>
                    <span class="em-tag em-tag--amber">未配置主站域名</span>
                    <?php endif; ?>
                <?php else: ?>
                <span class="mch-info-row__value--muted">未设置</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="mch-info-row">
            <div class="mch-info-row__label">自定义域名</div>
            <div class="mch-info-row__value">
                <?php if (!empty($merchantDetail['custom_domain'])): ?>
                <code><?= $esc($merchantDetail['custom_domain']) ?></code>
                <a href="http://<?= $esc($merchantDetail['custom_domain']) ?>/" target="_blank" title="在新窗口打开"><i class="fa fa-external-link"></i></a>
                <?php if ((int) ($merchantDetail['domain_verified'] ?? 0) === 1): ?>
                <span class="em-tag em-tag--on">已验证</span>
                <?php else: ?>
                <span class="em-tag em-tag--amber">待验证</span>
                <?php endif; ?>
                <?php else: ?>
                <span class="mch-info-row__value--muted">未设置</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- 底部：默认 popup-footer 就是右对齐（flex-end），去掉 --single 居中修饰 -->
<div class="popup-footer">
    <button type="button" class="popup-btn popup-btn--default" id="closeBtn"><i class="fa fa-times"></i> 关闭</button>
    <?php if ($storefrontUrl !== ''): ?>
    <button type="button" class="popup-btn popup-btn--primary" id="visitShopBtn"
            data-url="<?= $esc($storefrontUrl) ?>"><i class="fa fa-external-link mr-5"></i>访问店铺</button>
    <?php else: ?>
    <button type="button" class="popup-btn popup-btn--primary" disabled
            title="尚未配置可访问域名"><i class="fa fa-external-link mr-5"></i>访问店铺</button>
    <?php endif; ?>
</div>

<script>
$(function () {
    $('#closeBtn').on('click', function () {
        var index = parent.layer.getFrameIndex(window.name);
        parent.layer.close(index);
    });
    $('#visitShopBtn').on('click', function () {
        var url = $(this).data('url');
        if (url) window.open(url, '_blank');
    });
});
</script>
<?php
include __DIR__ . '/footer.php';
