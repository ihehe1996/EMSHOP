<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
/** @var array<string, mixed> $row  拥有 r.* + g.title/min_price/max_price */
/** @var string $csrfToken */
/** @var string $pageTitle */

$esc = function (string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
};

$basePrice = (int) $row['min_price'];
$maxBasePrice = (int) ($row['max_price'] ?? 0);
// v1.3+：商户拿货统一按主站原价（无折扣），cost = base
$cost = $basePrice;
$maxCost = $maxBasePrice;
$markupPct = rtrim(rtrim(number_format(((int) $row['markup_rate']) / 100, 2, '.', ''), '0'), '.');

// 弹窗是 iframe，拿不到父窗口的 window.EMSHOP_CURRENCY —— 这里重新解析访客币种 + 汇率
$curSymbol = Currency::visitorSymbol();
$visitorCurCode = Currency::visitorCode();
$visitorCurRow = $visitorCurCode !== '' ? Currency::getInstance()->getByCode($visitorCurCode) : null;
$curRate = 1.0;
if ($visitorCurRow !== null) {
    $rateRaw = (int) ($visitorCurRow['rate'] ?? 0);
    if ($rateRaw > 0) $curRate = 1000000 / $rateRaw;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $esc($pageTitle) ?></title>
    <link rel="stylesheet" href="/content/static/lib/layui-v2.13.5/layui/css/layui.css">
    <link rel="stylesheet" href="/content/static/lib/font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="/admin/static/css/popup.css">
    <script src="/content/static/lib/jquery.min.3.5.1.js"></script>
    <script src="/content/static/lib/layui-v2.13.5/layui/layui.js"></script>
</head>
<body class="popup-body">
<div class="popup-wrap" id="popupWrap">
<div class="popup-content" id="popupContent">

<div class="popup-inner">
    <form class="layui-form" id="refEditForm">
        <input type="hidden" name="_action" value="update_ref">
        <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
        <input type="hidden" name="goods_id" value="<?= (int) $row['goods_id'] ?>">

        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">商品</label>
                <div class="layui-input-block">
                    <div style="line-height:34px;"><strong><?= $esc($row['title']) ?></strong></div>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">主站原价</label>
                <div class="layui-input-block">
                    <div style="line-height:34px;color:#6b7280;">
                        <?= $esc(Currency::displayAmount($basePrice)) ?>
                        <?php if ($maxBasePrice > $basePrice): ?>
                        ~ <?= $esc(Currency::displayAmount($maxBasePrice)) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">拿货价（只读）</label>
                <div class="layui-input-block">
                    <div style="line-height:34px;color:#8b5cf6;">
                        <?= $esc(Currency::displayAmount($cost)) ?>
                        <?php if ($maxCost > $cost): ?>
                        ~ <?= $esc(Currency::displayAmount($maxCost)) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="layui-form-mid layui-word-aux">
                    商户拿货统一按主站原价收，与会员等级无关
                </div>
            </div>
        </div>

        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">加价率</label>
                <div class="layui-input-block">
                    <div class="layui-input-wrap">
                        <input type="number" class="layui-input" name="markup_rate" id="refMarkup"
                               step="0.01" min="0" max="1000"
                               value="<?= $esc($markupPct) ?>">
                        <div class="layui-input-suffix">%</div>
                    </div>
                </div>
                <div class="layui-form-mid layui-word-aux">
                    0 = 不加价；店内售价 = 拿货价 × (1 + 加价率 / 100)
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">本店售价预览</label>
                <div class="layui-input-block">
                    <div style="line-height:34px;color:#f59e0b;font-weight:600;font-size:15px;" id="refSellPreview">
                        计算中...
                    </div>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">上架状态</label>
                <div class="layui-input-block">
                    <input type="checkbox" name="is_on_sale" value="1" lay-skin="switch" lay-text="上架|下架" <?= (int) $row['is_on_sale'] === 1 ? 'checked' : '' ?>>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">推荐</label>
                <div class="layui-input-block">
                    <?php
                    $refRec = $row['ref_is_recommended']; // null / '0' / '1'
                    $goodsRec = (int) $row['goods_is_recommended'] === 1;
                    $currentRec = $refRec === null ? 'inherit' : ($refRec ? '1' : '0');
                    ?>
                    <input type="radio" name="is_recommended" value="inherit" title="跟随主站（<?= $goodsRec ? '推荐' : '不推荐' ?>）" <?= $currentRec === 'inherit' ? 'checked' : '' ?>>
                    <input type="radio" name="is_recommended" value="1" title="推荐" <?= $currentRec === '1' ? 'checked' : '' ?>>
                    <input type="radio" name="is_recommended" value="0" title="不推荐" <?= $currentRec === '0' ? 'checked' : '' ?>>
                </div>
                <div class="layui-form-mid layui-word-aux">
                    默认跟随主站；选择"推荐"或"不推荐"则强制覆盖。
                </div>
            </div>
        </div>
    </form>
</div>

<div class="popup-footer">
    <button type="button" class="popup-btn" id="refCancelBtn"><i class="fa fa-times"></i> 取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="refSubmitBtn"><i class="fa fa-check mr-5"></i>保存</button>
</div>

</div>
</div>

<script>
$(function () {
    layui.use(['layer', 'form'], function () {
        var layer = layui.layer;
        var form = layui.form;
        form.render();

        var cost = <?= $cost ?>;
        var maxCost = <?= $maxCost ?>;
        // 按访客当前展示币种符号 / 汇率展示（iframe 不共享父窗口 EMSHOP_CURRENCY，这里直接注入）
        var CUR = {symbol: <?= json_encode($curSymbol) ?>, rate: <?= (float) $curRate ?>};

        function updateSellPreview() {
            var pct = parseFloat($('#refMarkup').val()) || 0;
            if (pct < 0) pct = 0;
            var sell = cost * (1 + pct / 100);
            var html = CUR.symbol + ((sell / 1000000) * CUR.rate).toFixed(2);
            if (maxCost > cost) {
                var maxSell = maxCost * (1 + pct / 100);
                html += ' ~ ' + CUR.symbol + ((maxSell / 1000000) * CUR.rate).toFixed(2);
            }
            $('#refSellPreview').text(html);
        }
        $('#refMarkup').on('input', updateSellPreview);
        updateSellPreview();

        $('#refCancelBtn').on('click', function () {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        $('#refSubmitBtn').on('click', function () {
            var $btn = $(this);
            $btn.find('i').attr('class', 'fa fa-refresh admin-spin mr-5');
            $btn.prop('disabled', true);

            // 未勾选 switch 不提交，后端默认 0
            var data = $('#refEditForm').serializeArray();
            var has = false;
            $.each(data, function (_, it) { if (it.name === 'is_on_sale') has = true; });
            if (!has) data.push({name: 'is_on_sale', value: '0'});

            $.ajax({
                url: '/user/merchant/goods.php',
                type: 'POST',
                dataType: 'json',
                data: $.param(data),
                success: function (res) {
                    if (res.code === 200) {
                        try { parent.updateCsrf(res.data && res.data.csrf_token); } catch(e) {}
                        try { parent.window._mcRefPopupSaved = true; } catch(e) {}
                        var index = parent.layer.getFrameIndex(window.name);
                        parent.layer.msg(res.msg || '保存成功');
                        parent.layer.close(index);
                    } else {
                        layer.msg(res.msg || '保存失败');
                    }
                },
                error: function () { layer.msg('网络错误'); },
                complete: function () {
                    $btn.find('i').attr('class', 'fa fa-check mr-5');
                    $btn.prop('disabled', false);
                }
            });
        });
    });
});
</script>

</body>
</html>
