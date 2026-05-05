<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
// $csrfToken, $mode, $currency, $isPrimary, $primaryCode, $primaryName, $currencyDisplay
// 由 currency_popup.php 控制器提供
// $esc 由 currency_popup.php 控制器定义
$isEdit = $mode === 'edit';


?>

<div class="popup-inner">
    <?php if ($isPrimary): ?>
    <div class="form-tips">
        <strong><?php echo $esc($currency['code']); ?></strong> 为系统主货币，不可删除，仅可修改名称、符号和汇率。
    </div>
    <?php endif; ?>

    <form class="layui-form" id="currencyForm" lay-filter="currencyForm">
        <input type="hidden" name="_action" value="<?php echo $isEdit ? 'edit' : 'add'; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">
        <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?php echo (int) $currency['id']; ?>">
        <?php endif; ?>

        <!-- 基本信息 -->
        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">货币代码</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" id="currencyCode" name="code"
                           value="<?php echo $esc($currency['code']); ?>"
                           maxlength="3" placeholder="如 USD" style="text-transform:uppercase;"
                           <?php echo $isEdit ? 'readonly' : ''; ?>>
                </div>
                <div class="layui-form-mid layui-word-aux">3位大写字母，添加后不可修改代码</div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">货币名称</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" id="currencyName" name="name"
                           value="<?php echo $esc($currency['name']); ?>"
                           maxlength="30" placeholder="如 美元">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">货币符号</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" id="currencySymbol" name="symbol"
                           value="<?php echo $esc($currency['symbol']); ?>"
                           maxlength="10" placeholder="如 $">
                </div>
            </div>
        </div>

        <!-- 汇率设置 -->
        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">兑<?php echo $esc($primaryCode); ?>汇率</label>
                <div class="layui-input-block">
                    <input type="number" class="layui-input" id="currencyRate" name="rate"
                           value="<?= empty($currency['rate']) ? '' : $esc((float) $currency['rate'] / 1000000); ?>"
                           step="0.01" min="0" placeholder="1 单位该货币 = ? <?php echo $esc($primaryCode); ?>">
                </div>
                <div class="layui-form-mid layui-word-aux">1 <?php echo $esc($currency['code'] ?: '该货币'); ?> 等于多少<?php echo $esc($primaryCode); ?>（<?php echo $esc($primaryName); ?>），例如 USD=7.2000</div>
            </div>
        </div>

        <!-- 货币配置 -->
        <div class="popup-section">
            <?php if (!$isPrimary): ?>
            <div class="layui-form-item">
                <label class="layui-form-label">启用状态</label>
                <div class="layui-input-block">
                    <input type="checkbox" name="enabled" lay-skin="switch" lay-text="启用|禁用" value="1"
                        <?php echo ($isEdit ? ((int) ($currency['enabled'] ?? 1) === 1) : true) ? 'checked' : ''; ?>>
                </div>
                <div class="layui-form-mid layui-word-aux">禁用的货币不会在前台展示</div>
            </div>
            <?php else: ?>
            <div class="layui-form-item">
                <label class="layui-form-label">启用状态</label>
                <div class="layui-input-block">
                    <input type="checkbox" lay-skin="switch" lay-text="启用|禁用" value="1" checked disabled>
                </div>
                <div class="layui-form-mid layui-word-aux">主货币始终启用，无法禁用</div>
            </div>
            <?php endif; ?>
            <!-- "前台默认展示"已迁移到货币列表页的独立列，直接在列表一键切换 —— 这里不再展示勾选 -->
        </div>
    </form>
</div>

<div class="popup-footer">
    <button type="button" class="popup-btn" id="cancelBtn"><i class="fa fa-times"></i> 取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="submitBtn"><i class="fa fa-check mr-5"></i>确认保存</button>
</div>

<script>
$(function () {
    layui.use(['layer', 'form'], function () {
        var layer = layui.layer;
        var form = layui.form;

        form.render('checkbox');
        form.render('switch');

        // 取消
        $('#cancelBtn').on('click', function () {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        // 确认保存
        $('#submitBtn').on('click', function () {
            var $btn = $(this);
            $btn.find('i').attr('class', 'fa fa-refresh admin-spin mr-5');
            $btn.prop('disabled', true);

            $.ajax({
                url: '/admin/currency_popup.php',
                type: 'POST',
                data: $('#currencyForm').serialize(),
                dataType: 'json',
                success: function (res) {
                    if (res.code === 200) {
                        try { parent.updateCsrf(res.data.csrf_token); } catch(e) {}
                        try { parent.window._currencyPopupSaved = true; } catch(e) {}
                        var index = parent.layer.getFrameIndex(window.name);
                        parent.layer.msg(res.msg || '保存成功');
                        parent.layer.close(index);
                    } else {
                        try { parent.updateCsrf(res.data.csrf_token); } catch(e) {}
                        if (res.data && res.data.csrf_token) {
                            $('input[name="csrf_token"]').val(res.data.csrf_token);
                        }
                        layer.msg(res.msg || '操作失败');
                    }
                },
                error: function () {
                    layer.msg('网络错误，请重试');
                },
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
