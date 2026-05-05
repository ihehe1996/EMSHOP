<?php
/**
 * 用户中心 - 收货地址列表
 *
 * 由 user/address.php 控制器预填 $addressList。
 * 省市区三级联动使用第三方库 cityAreaSelect（数据与逻辑都在库里）。
 */
$csrfToken = $csrfToken ?? Csrf::token();
$addressList = $addressList ?? [];
$maxPerUser = UserAddressModel::MAX_PER_USER;
$esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>

<!-- 第三方省市区联动库（数据内置，无需单独加载 json） -->
<link rel="stylesheet" href="/content/static/lib/cityAreaSelect/dist/css/cityAreaSelect.css">
<script src="/content/static/lib/cityAreaSelect/dist/js/cityAreaSelect.min.js"></script>

<style>
/* 页面作用域样式 —— uc-address-* */
.uc-address-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
.uc-address-limit { font-size: 12px; color: #9ca3af; }
.uc-address-limit b { color: #4f46e5; font-weight: 600; }

.uc-address-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 14px; margin-top: 18px; }
.uc-address-card {
    position: relative;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
    padding: 18px 20px 14px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    transition: box-shadow 0.15s ease, border-color 0.15s ease;
}
.uc-address-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06); border-color: #d1d5db; }
.uc-address-card.is-default { border-color: #a5b4fc; background: #f5f3ff; }
.uc-address-card__badge {
    position: absolute; top: 12px; right: 14px;
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 10px;
    background: #4f46e5; color: #fff; font-size: 11px; font-weight: 500;
}
.uc-address-card__head { display: flex; align-items: baseline; gap: 10px; margin-bottom: 8px; padding-right: 60px; }
.uc-address-card__name { font-size: 15px; font-weight: 600; color: #111827; }
.uc-address-card__mobile { color: #6b7280; font-size: 13px; }
.uc-address-card__region { color: #4b5563; font-size: 13px; margin-bottom: 4px; }
.uc-address-card__detail { color: #111827; font-size: 13px; line-height: 1.6; word-break: break-all; min-height: 40px; }
.uc-address-card__actions { display: flex; gap: 12px; margin-top: 10px; padding-top: 10px; border-top: 1px dashed #e5e7eb; font-size: 12.5px; }
.uc-address-card__act { color: #6b7280; cursor: pointer; transition: color 0.15s; background: transparent; border: 0; padding: 0; }
.uc-address-card__act:hover { color: #4f46e5; }
.uc-address-card__act--danger:hover { color: #ef4444; }

.uc-address-empty {
    text-align: center; padding: 60px 20px; color: #9ca3af;
    background: #fff; border: 1px dashed #d1d5db; border-radius: 12px;
}
.uc-address-empty i { font-size: 48px; color: #d1d5db; display: block; margin-bottom: 12px; }
.uc-address-empty-hint { font-size: 13px; }

.uc-address-add-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; background: #4f46e5; color: #fff;
    border: 0; border-radius: 6px; font-size: 13px; cursor: pointer;
    transition: background 0.15s;
}
.uc-address-add-btn:hover { background: #4338ca; }
.uc-address-add-btn:disabled { background: #c7d2fe; cursor: not-allowed; }

/* 编辑表单（在 layer type:1 里用） */
.uc-addr-form { padding: 20px 24px; background: #fff; }
.uc-addr-form__row { display: flex; gap: 10px; margin-bottom: 14px; }
.uc-addr-form__row > .uc-addr-form__field { flex: 1; min-width: 0; }
.uc-addr-form__field { margin-bottom: 14px; }
.uc-addr-form__label {
    display: block; font-size: 13px; color: #374151; margin-bottom: 6px; font-weight: 500;
}
.uc-addr-form__label .req { color: #ef4444; margin-right: 2px; }
.uc-addr-form__input, .uc-addr-form__textarea {
    width: 100%; box-sizing: border-box;
    padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px;
    font-size: 13px; color: #111827; background: #fff;
    outline: none; transition: border-color 0.15s, box-shadow 0.15s;
}
.uc-addr-form__input:focus, .uc-addr-form__textarea:focus {
    border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
}
.uc-addr-form__textarea { resize: vertical; min-height: 72px; line-height: 1.6; }
.uc-addr-form__check { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-size: 13px; color: #374151; }
.uc-addr-form__check input { width: 14px; height: 14px; cursor: pointer; }
.uc-addr-form__footer {
    display: flex; justify-content: flex-end; gap: 10px;
    padding: 14px 24px; background: #f9fafb; border-top: 1px solid #e5e7eb;
}
.uc-addr-form__btn {
    padding: 8px 20px; border-radius: 6px; font-size: 13px; cursor: pointer;
    border: 0; transition: all 0.15s;
}
.uc-addr-form__btn--cancel { background: #fff; color: #6b7280; border: 1px solid #d1d5db; }
.uc-addr-form__btn--cancel:hover { background: #f3f4f6; }
.uc-addr-form__btn--primary { background: #4f46e5; color: #fff; }
.uc-addr-form__btn--primary:hover { background: #4338ca; }
.uc-addr-form__btn--primary:disabled { background: #c7d2fe; cursor: not-allowed; }

/* 覆盖 cityAreaSelect 默认布局到我们的风格（库默认用 bootstrap col-md-4） */
.uc-addr-cascade .cityAreaSelect-group { display: flex; gap: 8px; }
.uc-addr-cascade .cityAreaSelect-item { flex: 1; min-width: 0; }
.uc-addr-cascade .cityAreaSelect-select {
    width: 100%; box-sizing: border-box;
    padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px;
    font-size: 13px; color: #111827; background: #fff;
    outline: none; transition: border-color 0.15s, box-shadow 0.15s;
}
.uc-addr-cascade .cityAreaSelect-select:focus {
    border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
}
</style>

<div class="uc-page">
    <div class="uc-page-header">
        <h2 class="uc-page-title">收货地址</h2>
        <p class="uc-page-desc">管理你的收货地址，下单实物商品时可直接选用</p>
    </div>

    <div class="uc-form-card">
        <div class="uc-address-head">
            <span class="uc-address-limit">已保存 <b><?= count($addressList) ?></b> / <?= $maxPerUser ?> 个地址</span>
            <button type="button" class="uc-address-add-btn" id="ucAddrAddBtn" <?= count($addressList) >= $maxPerUser ? 'disabled' : '' ?>>
                <i class="fa fa-plus"></i>新增地址
            </button>
        </div>

        <?php if (empty($addressList)): ?>
            <div class="uc-address-empty">
                <i class="fa fa-map-marker"></i>
                <div>暂无收货地址</div>
                <div class="uc-address-empty-hint">点击右上角"新增地址"开始添加</div>
            </div>
        <?php else: ?>
            <div class="uc-address-list">
                <?php foreach ($addressList as $addr): ?>
                <div class="uc-address-card<?= ((int) $addr['is_default'] === 1) ? ' is-default' : '' ?>"
                     data-id="<?= (int) $addr['id'] ?>"
                     data-raw='<?= $esc(json_encode($addr, JSON_UNESCAPED_UNICODE)) ?>'>
                    <?php if ((int) $addr['is_default'] === 1): ?>
                        <span class="uc-address-card__badge"><i class="fa fa-check"></i>默认</span>
                    <?php endif; ?>
                    <div class="uc-address-card__head">
                        <span class="uc-address-card__name"><?= $esc($addr['recipient']) ?></span>
                        <span class="uc-address-card__mobile"><?= $esc($addr['mobile']) ?></span>
                    </div>
                    <div class="uc-address-card__region">
                        <?= $esc($addr['province']) ?>
                        <?php if ($addr['city'] && $addr['city'] !== $addr['province']): ?> · <?= $esc($addr['city']) ?><?php endif; ?>
                        <?php if ($addr['district']): ?> · <?= $esc($addr['district']) ?><?php endif; ?>
                    </div>
                    <div class="uc-address-card__detail"><?= $esc($addr['detail']) ?></div>
                    <div class="uc-address-card__actions">
                        <?php if ((int) $addr['is_default'] !== 1): ?>
                        <button type="button" class="uc-address-card__act" data-act="set_default">
                            <i class="fa fa-thumb-tack"></i> 设为默认
                        </button>
                        <?php endif; ?>
                        <button type="button" class="uc-address-card__act" data-act="edit">
                            <i class="fa fa-pencil"></i> 编辑
                        </button>
                        <button type="button" class="uc-address-card__act uc-address-card__act--danger" data-act="delete">
                            <i class="fa fa-trash"></i> 删除
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(function () {
    // PJAX 防重复绑定：清掉本页历史 .ucAddrPage handler，避免事件成倍触发
    $(document).off('.ucAddrPage');
    $(window).off('.ucAddrPage');

    'use strict';
    var csrfToken = <?= json_encode($csrfToken) ?>;

    // 每次打开弹窗用唯一 id 后缀，避免 layer 快速 open/close 时 DOM 残留导致 pcaSelect 绑到旧元素
    var pcaSeq = 0;

    // 打开 新增/编辑 弹窗；addr 为 null 则新增，否则为编辑数据
    function openEditor(addr) {
        var isEdit = !!addr;
        var data = addr || {};
        var seq = ++pcaSeq;
        var idProv = 'ucAddrProv_' + seq;
        var idCity = 'ucAddrCity_' + seq;
        var idArea = 'ucAddrArea_' + seq;

        var html = ''
            + '<div class="uc-addr-form">'
            + '  <div class="uc-addr-form__row">'
            + '    <div class="uc-addr-form__field">'
            + '      <label class="uc-addr-form__label"><span class="req">*</span>收件人</label>'
            + '      <input type="text" class="uc-addr-form__input" id="fRecipient_' + seq + '" maxlength="50" placeholder="姓名">'
            + '    </div>'
            + '    <div class="uc-addr-form__field">'
            + '      <label class="uc-addr-form__label"><span class="req">*</span>手机号码</label>'
            + '      <input type="text" class="uc-addr-form__input" id="fMobile_' + seq + '" maxlength="11" placeholder="11 位手机号">'
            + '    </div>'
            + '  </div>'
            + '  <div class="uc-addr-form__field">'
            + '    <label class="uc-addr-form__label"><span class="req">*</span>所在地区</label>'
            + '    <div class="uc-addr-cascade">'
            + '      <div class="cityAreaSelect-group">'
            + '        <div class="cityAreaSelect-item">'
            + '          <select class="cityAreaSelect-select" id="' + idProv + '"><option value="">请选择省/直辖市</option></select>'
            + '        </div>'
            + '        <div class="cityAreaSelect-item">'
            + '          <select class="cityAreaSelect-select" id="' + idCity + '"><option value="">请选择城市/区</option></select>'
            + '        </div>'
            + '        <div class="cityAreaSelect-item">'
            + '          <select class="cityAreaSelect-select" id="' + idArea + '"><option value="">请选择区/县</option></select>'
            + '        </div>'
            + '      </div>'
            + '    </div>'
            + '  </div>'
            + '  <div class="uc-addr-form__field">'
            + '    <label class="uc-addr-form__label"><span class="req">*</span>详细地址</label>'
            + '    <textarea class="uc-addr-form__textarea" id="fDetail_' + seq + '" maxlength="255" placeholder="街道、门牌号、楼栋等"></textarea>'
            + '  </div>'
            + '  <div class="uc-addr-form__field">'
            + '    <label class="uc-addr-form__check">'
            + '      <input type="checkbox" id="fIsDefault_' + seq + '"><span>设为默认地址</span>'
            + '    </label>'
            + '  </div>'
            + '</div>'
            + '<div class="uc-addr-form__footer">'
            + '  <button type="button" class="uc-addr-form__btn uc-addr-form__btn--cancel" id="fCancel_' + seq + '">取消</button>'
            + '  <button type="button" class="uc-addr-form__btn uc-addr-form__btn--primary" id="fSave_' + seq + '">保存</button>'
            + '</div>';

        var idx = layer.open({
            type: 1,
            title: isEdit ? '编辑地址' : '新增地址',
            area: [window.innerWidth >= 640 ? '520px' : '95%', 'auto'],
            content: html,
            shadeClose: false
        });

        // 回填基本字段
        $('#fRecipient_' + seq).val(data.recipient || '');
        $('#fMobile_' + seq).val(data.mobile || '');
        $('#fDetail_' + seq).val(data.detail || '');
        $('#fIsDefault_' + seq).prop('checked', Number(data.is_default) === 1);

        // —— 初始化第三方省市区联动控件
        //    编辑场景：onInit 里直接给 select 赋值并 dispatch change，让库自己触发级联填充
        //    插件内 change 用原生 addEventListener 监听，dispatchEvent(new Event('change')) 能正常触发
        var preset = {
            province: data.province || '',
            city: data.city || '',
            district: data.district || ''
        };

        // eslint-disable-next-line no-new
        new ProvinceCityAreaSelect({
            addrValElem: [idProv, idCity, idArea],
            onInit: function () {
                if (!preset.province) return;
                var $prov = document.getElementById(idProv);
                var $city = document.getElementById(idCity);
                var $area = document.getElementById(idArea);
                $prov.value = preset.province;
                $prov.dispatchEvent(new Event('change'));
                // 库是同步填 city 选项的，立即 set 市值
                if (preset.city) {
                    $city.value = preset.city;
                    $city.dispatchEvent(new Event('change'));
                }
                if (preset.district) {
                    $area.value = preset.district;
                    $area.dispatchEvent(new Event('change'));
                }
            }
        });

        // 取消
        $('#fCancel_' + seq).on('click', function () { layer.close(idx); });

        // 保存
        $('#fSave_' + seq).on('click', function () {
            var $btn = $(this).prop('disabled', true);
            var payload = {
                csrf_token: csrfToken,
                action: 'save',
                id: isEdit ? data.id : 0,
                recipient: $('#fRecipient_' + seq).val().trim(),
                mobile: $('#fMobile_' + seq).val().trim(),
                // 控件的 value 是地区"名称"文本（库内置的 option 文本），直接交后端存快照
                province: $('#' + idProv).val() || '',
                city: $('#' + idCity).val() || '',
                district: $('#' + idArea).val() || '',
                detail: $('#fDetail_' + seq).val().trim(),
                is_default: $('#fIsDefault_' + seq).is(':checked') ? 1 : ''
            };
            $.post('/user/address.php', payload, function (res) {
                if (res.code === 200) {
                    if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                    layer.msg(res.msg || '已保存');
                    layer.close(idx);
                    if ($.pjax) $.pjax.reload('#userContent');
                    else location.reload();
                } else {
                    layer.msg(res.msg || '保存失败');
                    $btn.prop('disabled', false);
                }
            }, 'json').fail(function () {
                layer.msg('网络异常');
                $btn.prop('disabled', false);
            });
        });
    }

    // 新增按钮
    $('#ucAddrAddBtn').on('click', function () { openEditor(null); });

    // 行内操作（编辑 / 设默认 / 删除）事件委托
    $(document).on('click.ucAddrPage', '.uc-address-card__act', function () {
        var $card = $(this).closest('.uc-address-card');
        var raw = $card.attr('data-raw');
        var addr = raw ? JSON.parse(raw) : null;
        var act = $(this).data('act');
        if (!addr) return;

        if (act === 'edit') {
            openEditor(addr);
        } else if (act === 'set_default') {
            $.post('/user/address.php',
                { csrf_token: csrfToken, action: 'set_default', id: addr.id },
                function (res) {
                    if (res.code === 200) {
                        if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                        layer.msg(res.msg || '已设为默认');
                        if ($.pjax) $.pjax.reload('#userContent');
                        else location.reload();
                    } else layer.msg(res.msg || '操作失败');
                }, 'json');
        } else if (act === 'delete') {
            layer.confirm('确认删除这条地址吗？', { btn: ['确认删除', '取消'] }, function (confirmIdx) {
                $.post('/user/address.php',
                    { csrf_token: csrfToken, action: 'delete', id: addr.id },
                    function (res) {
                        if (res.code === 200) {
                            if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                            layer.msg(res.msg || '已删除');
                            layer.close(confirmIdx);
                            if ($.pjax) $.pjax.reload('#userContent');
                            else location.reload();
                        } else layer.msg(res.msg || '删除失败');
                    }, 'json');
            });
        }
    });
});
</script>
