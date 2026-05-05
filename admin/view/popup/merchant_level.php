<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
$isEdit = isset($editLevel) && $editLevel !== null;

$esc = function (string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
};

$val = function (string $k, $default = '') use ($isEdit, $editLevel, $esc) {
    return $isEdit && isset($editLevel[$k]) ? $esc((string) $editLevel[$k]) : $esc((string) $default);
};
$intVal = function (string $k, int $default = 0) use ($isEdit, $editLevel): int {
    return $isEdit && isset($editLevel[$k]) ? (int) $editLevel[$k] : $default;
};
$chk = function (string $k, int $default = 0) use ($isEdit, $editLevel): string {
    $v = $isEdit && isset($editLevel[$k]) ? (int) $editLevel[$k] : $default;
    return $v === 1 ? 'checked' : '';
};

include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <form class="layui-form" id="mlForm" lay-filter="mlForm">
        <input type="hidden" name="_action" value="<?= $isEdit ? 'update' : 'create' ?>">
        <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
        <input type="hidden" name="id" value="<?= $isEdit ? $esc((string) $editLevel['id']) : '' ?>">

        <!-- 选项卡（em-tabs，切换联动下方 layui-tab-content 面板） -->
        <div class="em-tabs" id="mlEditTabs" style="margin-bottom: 0;">
            <a class="em-tabs__item is-active"><i class="fa fa-info-circle"></i>基础信息</a>
            <a class="em-tabs__item"><i class="fa fa-percent"></i>费率配置</a>
            <a class="em-tabs__item"><i class="fa fa-globe"></i>域名权限</a>
            <a class="em-tabs__item"><i class="fa fa-key"></i>功能权限</a>
        </div>
        <div class="layui-tab-content ml-edit-content">

            <!-- ========== Tab 1: 基础信息（含启用状态） ========== -->
            <div class="layui-tab-item layui-show">
                <div class="popup-section">
                    <div class="layui-form-item">
                        <label class="layui-form-label">等级名称</label>
                        <div class="layui-input-block">
                            <input type="text" class="layui-input" id="mlName" name="name" maxlength="64"
                                   placeholder="如：普通商户 / 高级商户"
                                   value="<?= $val('name') ?>">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">自助开通价</label>
                        <div class="layui-input-block">
                            <div class="layui-input-wrap">
                                <input type="number" class="layui-input" name="price" step="0.01" min="0"
                                       placeholder="0 = 不允许自助开通"
                                       value="<?= $val('price_view', '0.00') ?>">
                                <div class="layui-input-suffix">元</div>
                            </div>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">排序</label>
                        <div class="layui-input-block">
                            <input type="number" class="layui-input" name="sort" step="1" min="0"
                                   value="<?= $intVal('sort', 100) ?>">
                        </div>
                        <div class="layui-form-mid layui-word-aux">越小越靠前</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">启用</label>
                        <div class="layui-input-block">
                            <input type="checkbox" name="is_enabled" lay-skin="switch" lay-text="启用|禁用" value="1" <?= $chk('is_enabled', 1) ?>>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========== Tab 2: 费率配置 ========== -->
            <div class="layui-tab-item">
                <div class="popup-section">
                    <div class="layui-form-item">
                        <label class="layui-form-label">自建手续费率</label>
                        <div class="layui-input-block">
                            <div class="layui-input-wrap">
                                <input type="number" class="layui-input" name="self_goods_fee_rate" step="0.01" min="0" max="100"
                                       placeholder="百分比，如 5 表示 5%"
                                       value="<?= $val('self_goods_fee_rate_view', '0') ?>">
                                <div class="layui-input-suffix">%</div>
                            </div>
                        </div>
                        <div class="layui-form-mid layui-word-aux">商户卖自建商品时主站抽成(按订单金额的比例)</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">提现手续费率</label>
                        <div class="layui-input-block">
                            <div class="layui-input-wrap">
                                <input type="number" class="layui-input" name="withdraw_fee_rate" step="0.01" min="0" max="100"
                                       placeholder="百分比"
                                       value="<?= $val('withdraw_fee_rate_view', '0') ?>">
                                <div class="layui-input-suffix">%</div>
                            </div>
                        </div>
                        <div class="layui-form-mid layui-word-aux">店铺余额 → 用户余额 的提现损耗</div>
                    </div>
                </div>
            </div>

            <!-- ========== Tab 3: 域名权限 ========== -->
            <div class="layui-tab-item">
                <div class="popup-section">
                    <div class="layui-form-item">
                        <label class="layui-form-label">二级域名</label>
                        <div class="layui-input-block">
                            <input type="checkbox" name="allow_subdomain" lay-skin="switch" lay-text="允许|禁用" value="1" <?= $chk('allow_subdomain') ?>>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">自定义顶级域名</label>
                        <div class="layui-input-block">
                            <input type="checkbox" name="allow_custom_domain" lay-skin="switch" lay-text="允许|禁用" value="1" <?= $chk('allow_custom_domain') ?>>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========== Tab 4: 功能权限 ========== -->
            <div class="layui-tab-item">
                <div class="popup-section">
                    <div class="layui-form-item">
                        <label class="layui-form-label">自建商品</label>
                        <div class="layui-input-block">
                            <input type="checkbox" name="allow_self_goods" lay-skin="switch" lay-text="允许|禁用" value="1" <?= $chk('allow_self_goods') ?>>
                        </div>
                        <div class="layui-form-mid layui-word-aux">允许商户自行上架商品</div>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

<div class="popup-footer">
    <button type="button" class="popup-btn" id="mlCancelBtn"><i class="fa fa-times"></i> 取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="mlSubmitBtn"><i class="fa fa-check mr-5"></i>确认保存</button>
</div>

<style>
/* 选项卡内的 layui-tab-item 去掉默认 padding */
.ml-edit-content > .layui-tab-item { padding: 0; }
</style>

<script>
$(function () {
    // em-tabs 点击切换：同步 .is-active 到 tab 项，同步 .layui-show 到对应面板
    $('#mlEditTabs').on('click', '.em-tabs__item', function () {
        var $item = $(this);
        if ($item.hasClass('is-active')) return;
        var index = $item.index();
        $item.addClass('is-active').siblings().removeClass('is-active');
        $item.closest('.em-tabs').next('.layui-tab-content')
            .children('.layui-tab-item')
            .removeClass('layui-show').eq(index).addClass('layui-show');
    });

    layui.use(['layer', 'form'], function () {
        var layer = layui.layer;
        var form = layui.form;
        form.render();

        $('#mlCancelBtn').on('click', function () {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        $('#mlSubmitBtn').on('click', function () {
            var name = $.trim($('#mlName').val());
            if (!name) {
                layer.msg('请填写等级名称');
                return;
            }

            var $btn = $(this);
            $btn.find('i').attr('class', 'fa fa-refresh admin-spin mr-5');
            $btn.prop('disabled', true);

            // 处理未勾选的 checkbox —— 默认 form.serialize() 不会提交未勾选项
            var data = $('#mlForm').serializeArray();
            var switches = ['allow_subdomain','allow_custom_domain',
                            'allow_self_goods','is_enabled'];
            var present = {};
            $.each(data, function (_, it) { present[it.name] = true; });
            $.each(switches, function (_, key) {
                if (!present[key]) data.push({name: key, value: '0'});
            });

            $.ajax({
                url: '/admin/merchant_level.php',
                type: 'POST',
                data: $.param(data),
                dataType: 'json',
                success: function (res) {
                    if (res.code === 200) {
                        if (res.data && res.data.csrf_token) {
                            try { parent.updateCsrf(res.data.csrf_token); } catch(e) {}
                        }
                        try { parent.window._mlPopupSaved = true; } catch(e) {}
                        var index = parent.layer.getFrameIndex(window.name);
                        parent.layer.msg(res.msg || '保存成功');
                        parent.layer.close(index);
                    } else {
                        layer.msg(res.msg || '操作失败');
                    }
                },
                error: function () { layer.msg('网络错误，请重试'); },
                complete: function () {
                    $btn.find('i').attr('class', 'fa fa-check mr-5');
                    $btn.prop('disabled', false);
                }
            });
        });
    });
});
</script>
<?php
include __DIR__ . '/footer.php';
