<?php
/** @var array<string, mixed> $frontUser */
/** @var string $siteName */
/** @var string $siteLogoType */
/** @var string $siteLogo */
/** @var bool $selfOpenEnabled */
/** @var array<int, array<string, mixed>> $levels */
/** @var string $csrfToken */
/** @var string $displayMoney */
/** @var string $currencySymbol */

$esc = function (string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>开通分站 - <?= $esc($siteName) ?></title>
    <link rel="stylesheet" href="/content/static/lib/font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="/content/static/lib/layui-v2.13.5/layui/css/layui.css">
    <link rel="stylesheet" href="/user/merchant/static/css/merchant.css?v=<?= @filemtime(EM_ROOT . '/user/merchant/static/css/merchant.css') ?: time() ?>">
    <script src="/content/static/lib/jquery.min.3.5.1.js"></script>
    <script src="/content/static/lib/layui-v2.13.5/layui/layui.js"></script>
</head>
<body class="mc-apply-page">

<!-- 页头：白底 + sticky + 玻璃模糊；logo 跟随 site_logo_type 二选一 -->
<header class="mc-apply-header">
    <div class="mc-apply-header__inner">
        <a href="/" class="mc-apply-header__logo">
            <?php if ($siteLogoType === 'image' && $siteLogo !== ''): ?>
            <img src="<?= $esc($siteLogo) ?>" alt="<?= $esc($siteName) ?>" class="mc-apply-header__logo-img">
            <?php else: ?>
            <span class="mc-apply-header__logo-text"><?= $esc($siteName) ?></span>
            <?php endif; ?>
        </a>
        <span class="mc-apply-header__chip"><i class="fa fa-sitemap"></i> 开通分站</span>
        <div class="mc-apply-header__right">
            <a href="/user/home.php" class="mc-apply-header__btn"><i class="fa fa-user"></i> 个人中心</a>
            <a href="/" class="mc-apply-header__btn"><i class="fa fa-home"></i> 返回首页</a>
        </div>
    </div>
</header>

<main class="mc-apply-main">
    <div class="mc-apply-card">
        <!-- hero：左侧紫色 accent + 简洁标题（替代原浮夸大标题） -->
        <div class="mc-apply-hero">
            <div class="mc-apply-hero__title">开通分站</div>
            <div class="mc-apply-hero__sub">一次开通，长期使用。可使用主站商品转售、自建商品、独立分站页面等能力</div>
        </div>

        <div class="mc-apply-body">
        <?php if (!$selfOpenEnabled): ?>
            <div class="mc-apply-alert mc-apply-alert--warn">
                <i class="fa fa-exclamation-triangle"></i>
                <div>
                    <div class="mc-apply-alert__title">暂未开放自助开通</div>
                    <div class="mc-apply-alert__desc">请联系管理员协助为您开通分站</div>
                </div>
            </div>

        <?php elseif ($levels === []): ?>
            <div class="mc-apply-alert mc-apply-alert--empty">
                <i class="fa fa-inbox"></i>
                <div>
                    <div class="mc-apply-alert__title">暂无可自助开通的等级</div>
                    <div class="mc-apply-alert__desc">请联系管理员了解开通方式</div>
                </div>
            </div>

        <?php else: ?>
            <form id="mcApplyForm" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">

                <!-- 分站等级：每个等级一张卡片，点击高亮 + radio 同步 -->
                <div class="mc-apply-section">
                    <div class="mc-apply-section__label">选择分站等级</div>
                    <div class="mc-apply-levels">
                    <?php foreach ($levels as $i => $lv):
                        // 等级开通价按访客当前展示币种换算（不含符号，模板里 $currencySymbol . $priceYuan）
                        $priceYuan = Currency::displayAmount((int) $lv['price'], null, false);
                        $feeRate = rtrim(rtrim(number_format(((int) $lv['self_goods_fee_rate']) / 100, 2, '.', ''), '0'), '.');
                        $wdFee = rtrim(rtrim(number_format(((int) $lv['withdraw_fee_rate']) / 100, 2, '.', ''), '0'), '.');
                        $allowSelfGoods = (int) ($lv['allow_self_goods'] ?? 0) === 1;
                        $allowSubdomain = (int) ($lv['allow_subdomain'] ?? 0) === 1;
                        $allowCustomDomain = (int) ($lv['allow_custom_domain'] ?? 0) === 1;
                    ?>
                    <label class="mc-apply-level <?= $i === 0 ? 'is-selected' : '' ?>" data-lvl-id="<?= (int) $lv['id'] ?>">
                        <input type="radio" name="level_id" value="<?= (int) $lv['id'] ?>" <?= $i === 0 ? 'checked' : '' ?>>
                        <div class="mc-apply-level__check"><i class="fa fa-check"></i></div>
                        <div class="mc-apply-level__main">
                            <div class="mc-apply-level__head">
                                <span class="mc-apply-level__name"><?= $esc($lv['name']) ?></span>
                                <span class="mc-apply-level__price">
                                    <span class="mc-apply-level__currency"><?= $esc($currencySymbol) ?></span><?= $esc($priceYuan) ?>
                                </span>
                            </div>
                            <div class="mc-apply-level__feats">
                                <?php if ($allowSelfGoods): ?>
                                <span class="mc-apply-level__feat"><i class="fa fa-cubes"></i> 自建商品</span>
                                <?php endif; ?>
                                <?php if ($allowSubdomain): ?>
                                <span class="mc-apply-level__feat"><i class="fa fa-link"></i> 二级域名</span>
                                <?php endif; ?>
                                <?php if ($allowCustomDomain): ?>
                                <span class="mc-apply-level__feat"><i class="fa fa-globe"></i> 自定义域名</span>
                                <?php endif; ?>
                                <span class="mc-apply-level__feat mc-apply-level__feat--muted">自建手续费 <?= $feeRate ?>%</span>
                                <span class="mc-apply-level__feat mc-apply-level__feat--muted">提现手续费 <?= $wdFee ?>%</span>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                    </div>
                </div>

                <!-- 分站名称 -->
                <div class="mc-apply-section">
                    <div class="mc-apply-section__label">分站名称</div>
                    <input type="text" class="mc-apply-input" name="name" id="mcApplyName"
                           maxlength="100" placeholder="例如：XXX 分站 / XXX 旗舰店">
                    <div class="mc-apply-section__hint">店铺对外展示名称，开通后可在商户后台修改</div>
                </div>

                <!-- 余额信息 -->
                <div class="mc-apply-balance">
                    <div class="mc-apply-balance__icon"><i class="fa fa-credit-card"></i></div>
                    <div class="mc-apply-balance__main">
                        <div class="mc-apply-balance__label">账户余额</div>
                        <div class="mc-apply-balance__amount"><?= $esc($currencySymbol) ?><?= $displayMoney ?></div>
                    </div>
                    <a href="/user/wallet.php" class="mc-apply-balance__action">
                        <i class="fa fa-plus"></i> 充值
                    </a>
                </div>
                <div class="mc-apply-section__hint" style="text-align:center;margin-top:8px;">
                    开通费用将从此余额扣除，请确保余额充足
                </div>

                <button type="button" class="mc-apply-submit" id="mcApplySubmit">
                    <i class="fa fa-check-circle"></i> 立即开通
                </button>
            </form>
        <?php endif; ?>
        </div>
    </div>

    <!-- 底部说明 -->
    <div class="mc-apply-foot">
        <i class="fa fa-shield"></i> 所有等级均支持后续升级 · 余额扣款立即生效 · 不满意可联系客服处理
    </div>
</main>

<script>
$(function () {
    // PJAX 防重复绑定：清掉本页历史 .mcApplyPage handler，避免事件成倍触发
    $(document).off('.mcApplyPage');
    $(window).off('.mcApplyPage');

    layui.use(['layer'], function () {
        var layer = layui.layer;

        // 等级卡片点击高亮（同步 radio 选中态）
        $(document).on('click.mcApplyPage', '.mc-apply-level', function () {
            $('.mc-apply-level').removeClass('is-selected');
            $(this).addClass('is-selected');
            $(this).find('input[type="radio"]').prop('checked', true);
        });

        $(document).on('click.mcApplyPage', '#mcApplySubmit', function () {
            var name = $.trim($('#mcApplyName').val());
            var levelId = $('input[name="level_id"]:checked').val();

            if (!levelId) { layer.msg('请选择分站等级'); return; }
            if (!name)    { layer.msg('请填写分站名称'); return; }

            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-refresh fa-spin"></i> 开通中...');

            $.ajax({
                url: '/user/merchant/apply.php',
                type: 'POST',
                dataType: 'json',
                data: $('#mcApplyForm').serialize(),
                success: function (res) {
                    if (res.code === 200) {
                        layer.msg(res.msg || '开通成功', { time: 800 });
                        setTimeout(function () {
                            location.href = (res.data && res.data.redirect) || '/user/merchant/home.php';
                        }, 800);
                    } else {
                        layer.msg(res.msg || '开通失败');
                        $btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> 立即开通');
                    }
                },
                error: function () {
                    layer.msg('网络错误，请重试');
                    $btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> 立即开通');
                }
            });
        });
    });
});
</script>

</body>
</html>
