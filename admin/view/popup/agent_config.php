<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
/** @var array|null $agent */
/** @var string|null $agentError */

$pageTitle = '获取正版授权码';

$esc = function (string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
};

include __DIR__ . '/header.php';
?>

<div class="popup-inner agent-wrap">

    <?php if ($agentError !== null): ?>
    <div class="agent-error">
        <div class="agent-error__icon"><i class="fa fa-exclamation-triangle"></i></div>
        <div class="agent-error__title">暂时无法获取授权信息</div>
        <div class="agent-error__msg"><?= $esc($agentError) ?></div>
        <div class="agent-error__hint">请点击后台右上角的线路菜单，切换到其他线路后重试。</div>
    </div>

    <?php elseif ($agent !== null): ?>

    <!-- ============ Hero（简约） ============ -->
    <div class="agent-hero">
        <div class="agent-hero__icon"><i class="fa fa-bolt"></i></div>
        <h1 class="agent-hero__title">解锁 EMSHOP 全部能力</h1>
        <p class="agent-hero__subtitle">激活正版授权 · 享专属模板、付费插件、优先技术支持</p>
    </div>

    

    <!-- ============ 购买渠道 ============ -->
    <?php $buyUrls = is_array($agent['buy_url'] ?? null) ? $agent['buy_url'] : []; ?>
    <div class="agent-section">
        <div class="agent-section__title">获取正版授权码</div>
        <?php if ($buyUrls !== []): ?>
        <div class="agent-plans">
            <?php foreach ($buyUrls as $i => $item): ?>
            <a href="<?= $esc((string) ($item['url'] ?? '#')) ?>" target="_blank" class="agent-plan">
                <div class="agent-plan__num"><i class="fa fa-shopping-cart"></i></div>
                <div class="agent-plan__body">
                    <div class="agent-plan__name"><?= $esc((string) ($item['name'] ?? '')) ?></div>
                    <div class="agent-plan__url"><?= $esc((string) ($item['url'] ?? '')) ?></div>
                </div>
                <i class="fa fa-arrow-right agent-plan__arrow"></i>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="agent-empty">
            <i class="fa fa-inbox"></i>
            <div>暂无购买渠道</div>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>

    <!-- ============ 联系方式 ============ -->
    <?php
    $contacts = [];
    if (!empty($agent['service_qq'])) {
        $contacts[] = ['icon' => 'fa-qq', 'label' => '客服 QQ', 'value' => (string) $agent['service_qq'], 'color' => '#1890ff'];
    }
    if (!empty($agent['qq_group'])) {
        $contacts[] = ['icon' => 'fa-users', 'label' => 'QQ 群', 'value' => (string) $agent['qq_group'], 'color' => '#722ed1'];
    }
    if ($contacts !== []):
    ?>
    <div class="agent-section">
        <div class="agent-section__title">客服联系方式</div>
        <div class="agent-contacts">
            <?php foreach ($contacts as $c): ?>
            <div class="agent-contact">
                <div class="agent-contact__icon" style="background: <?= $c['color'] ?>14; color: <?= $c['color'] ?>;">
                    <i class="fa <?= $esc($c['icon']) ?>"></i>
                </div>
                <div class="agent-contact__body">
                    <div class="agent-contact__label"><?= $esc($c['label']) ?></div>
                    <div class="agent-contact__value"><?= $esc($c['value']) ?></div>
                </div>
                <button type="button" class="agent-contact__copy" data-copy="<?= $esc($c['value']) ?>" title="复制">
                    <i class="fa fa-clone"></i>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
body.popup-body { background: #fff; }
.popup-inner.agent-wrap { padding: 0; }

/* ================================ Hero ================================ */
.agent-hero {
    padding: 36px 28px 28px;
    text-align: center;
    border-bottom: 1px solid #f3f4f6;
}
.agent-hero__icon {
    width: 56px; height: 56px;
    margin: 0 auto 16px;
    border-radius: 16px;
    background: linear-gradient(135deg, #eef2ff, #e0e7ff);
    display: flex; align-items: center; justify-content: center;
    font-size: 24px;
    color: #6366f1;
}
.agent-hero__title {
    font-size: 20px; font-weight: 600; color: #111827;
    margin: 0 0 6px;
    letter-spacing: 0.3px;
}
.agent-hero__subtitle {
    font-size: 13px; color: #6b7280;
    margin: 0; line-height: 1.6;
}

/* ================================ Section ================================ */
.agent-section {
    padding: 22px 28px 0;
}
.agent-section:last-child {
    padding-bottom: 24px;
}
.agent-section__title {
    letter-spacing: 0.8px; text-transform: uppercase;
    margin-bottom: 12px;
}

/* ================================ 联系方式 ================================ */
.agent-contacts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 10px;
}
.agent-contact {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 14px;
    background: #f8fafc;
    border: 1px solid #e4e7eb;
    border-radius: 10px;
    transition: all 0.15s ease;
}
.agent-contact:hover {
    background: #fff;
    border-color: #c7d2fe;
}
.agent-contact__icon {
    width: 36px; height: 36px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}
.agent-contact__body { flex: 1; min-width: 0; }
.agent-contact__label {
    font-size: 11px; color: #9ca3af;
    letter-spacing: 0.3px; margin-bottom: 2px;
}
.agent-contact__value {
    font-size: 14px; font-weight: 600; color: #111827;
    font-family: 'JetBrains Mono', Consolas, Monaco, monospace;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.agent-contact__copy {
    width: 30px; height: 30px;
    border: 1px solid #e4e7eb; border-radius: 7px;
    background: #fff;
    color: #9ca3af;
    cursor: pointer;
    font-size: 11px;
    transition: all 0.15s ease;
    flex-shrink: 0;
}
.agent-contact__copy:hover {
    background: #6366f1; color: #fff; border-color: #6366f1;
}
.agent-contact__copy.is-copied {
    background: #10b981; color: #fff; border-color: #10b981;
}

/* ================================ 购买渠道 ================================ */
.agent-plans {
    display: flex; flex-direction: column; gap: 8px;
}
.agent-plan {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 16px;
    background: #f8fafc;
    border: 1px solid #e4e7eb;
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.15s ease;
}
.agent-plan:hover {
    border-color: #a5b4fc;
    background: #faf8ff;
}

.agent-plan__num {
    width: 36px; height: 36px;
    border-radius: 9px;
    background: #eef2ff;
    color: #6366f1;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
    transition: all 0.15s ease;
}
.agent-plan:hover .agent-plan__num {
    transform: scale(1.05);
}

.agent-plan__body {
    flex: 1; min-width: 0;
}
.agent-plan__name {
    font-weight: 500; color: #111827;
    margin-bottom: 2px;
}
.agent-plan__url {
    font-size: 13px; color: #9ca3af;
    font-family: 'JetBrains Mono', Consolas, Monaco, monospace;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

.agent-plan__arrow {
    font-size: 12px; color: #cbd5e1;
    transition: all 0.15s ease;
    flex-shrink: 0;
}
.agent-plan:hover .agent-plan__arrow {
    color: #6366f1;
    transform: translateX(3px);
}

/* ================================ 空状态 ================================ */
.agent-empty {
    padding: 36px 20px; text-align: center;
    color: #9ca3af; font-size: 13px;
    background: #fafbfc; border-radius: 10px;
    border: 1px dashed #e4e7eb;
}
.agent-empty i {
    font-size: 28px; display: block; margin-bottom: 8px; color: #cbd5e1;
}

/* ================================ 错误 ================================ */
.agent-error {
    padding: 60px 40px;
    text-align: center;
}
.agent-error__icon {
    width: 64px; height: 64px;
    margin: 0 auto 18px;
    border-radius: 18px;
    background: #fff7ed;
    color: #f59e0b;
    display: flex; align-items: center; justify-content: center;
    font-size: 28px;
}
.agent-error__title {
    font-size: 16px; font-weight: 600; color: #111827;
    margin-bottom: 6px;
}
.agent-error__msg {
    color: #6b7280;
    line-height: 1.7; margin-bottom: 4px;
}
.agent-error__hint {
    font-size: 13px; color: #9ca3af;
}

/* ================================ 响应式 ================================ */
@media (max-width: 600px) {
    .agent-hero { padding: 28px 20px 22px; }
    .agent-hero__title { font-size: 18px; }
    .agent-section { padding: 20px 20px 0; }
    .agent-plan { padding: 12px 14px; gap: 10px; }
}
</style>

<script>
$(function () {
    $(document).on('click', '.agent-contact__copy', function () {
        var val = $(this).data('copy');
        if (!val) return;
        var $btn = $(this);
        var text = String(val);

        function showOK() {
            $btn.addClass('is-copied').find('i').removeClass('fa-clone').addClass('fa-check');
            setTimeout(function () {
                $btn.removeClass('is-copied').find('i').removeClass('fa-check').addClass('fa-clone');
            }, 1500);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(showOK).catch(function () {
                fallbackCopy(text);
                showOK();
            });
        } else {
            fallbackCopy(text);
            showOK();
        }
    });

    function fallbackCopy(text) {
        var $ta = $('<textarea>').val(text).css({position: 'fixed', top: 0, left: 0, opacity: 0}).appendTo('body').select();
        try { document.execCommand('copy'); } catch (e) {}
        $ta.remove();
    }
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
