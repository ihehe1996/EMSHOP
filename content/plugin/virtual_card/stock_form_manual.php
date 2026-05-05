<?php
/**
 * 虚拟卡密商品（人工发货模式）— 数量库存管理
 *
 * 与实物商品相同的库存管理方式：手动设置每个规格的库存数量。
 * 当商品未开启自动发货时使用此视图（管理员人工填写发货内容）。
 *
 * 可用变量：$goods, $specs, $csrfToken
 */
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$esc = function (string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
};

$totalStock = 0;
foreach ($specs as $spec) {
    $totalStock += max(0, (int)$spec['stock']);
}
?>

<div class="popup-inner">

<div class="popup-section">
    <div class="layui-form-item" style="margin-bottom:0;">
        <label class="layui-form-label">总库存</label>
        <div class="layui-input-block">
            <div class="layui-form-mid" style="padding-left:0;">
                <span id="totalStockNum" style="font-size:18px;font-weight:600;color:<?php echo $totalStock === 0 ? '#ff4d4f' : ($totalStock <= 10 ? '#fa8c16' : '#333'); ?>;"><?php echo $totalStock; ?></span>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($specs)): ?>
<form class="layui-form" id="stockForm" lay-filter="stockForm">
    <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">
    <input type="hidden" name="goods_id" value="<?php echo (int)$goods['id']; ?>">
    <input type="hidden" name="_action" value="save_stock">

    <div class="popup-section">
        <table class="layui-table" style="margin:0;">
            <colgroup>
                <col>
                <col width="160">
            </colgroup>
            <thead>
                <tr>
                    <th>规格名称</th>
                    <th>库存数量</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($specs as $spec): ?>
                    <tr>
                        <td>
                            <?php echo $esc($spec['name']); ?>
                            <?php if ((int)$spec['stock'] === 0): ?>
                                <span style="color:#ff4d4f;font-size:11px;margin-left:6px;">缺货</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <input type="number" name="spec_stock[<?php echo (int)$spec['id']; ?>]"
                                   class="layui-input stock-input"
                                   value="<?php echo max(0, (int)$spec['stock']); ?>"
                                   min="0" placeholder="0">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</form>
<?php else: ?>
<div style="text-align:center;padding:30px 0;color:#999;">
    <i class="fa fa-info-circle"></i> 暂无规格，请先在商品编辑中添加规格
</div>
<?php endif; ?>

</div><!-- /popup-inner -->

<?php if (!empty($specs)): ?>
<div class="popup-footer">
    <button type="button" class="popup-btn" id="stockCancelBtn"><i class="fa fa-times"></i> 取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="stockSaveBtn"><i class="fa fa-check mr-5"></i>保存库存</button>
</div>

<style>
.stock-input { height: 32px !important; text-align: center; }
.stock-input:focus { border-color: #1e9fff; }
</style>

<script>
$(function () {
    layui.use(['form', 'layer'], function () {
        var form = layui.form;
        var layer = layui.layer;
        form.render();

        $(document).on('input', '.stock-input', function () {
            var total = 0;
            $('.stock-input').each(function () {
                total += Math.max(0, parseInt($(this).val()) || 0);
            });
            var $num = $('#totalStockNum');
            $num.text(total);
            $num.css('color', total === 0 ? '#ff4d4f' : (total <= 10 ? '#fa8c16' : '#333'));
        });

        $('#stockCancelBtn').on('click', function () {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        $('#stockSaveBtn').on('click', function () {
            var $btn = $(this);
            $btn.find('i').attr('class', 'fa fa-refresh admin-spin mr-5');
            $btn.prop('disabled', true);
            $.ajax({
                // URL 由 popup header 注入到 iframe 自身 window（主站默认 /admin/goods_edit.php，商户覆盖 /user/merchant/goods.php）
                url: window.STOCK_SAVE_URL || '/admin/goods_edit.php?_action=save_stock',
                type: 'POST',
                dataType: 'json',
                data: $('#stockForm').serialize(),
                success: function (res) {
                    if (res.code === 200) {
                        try { parent.window._stockPopupSaved = true; } catch (e) {}
                        var index = parent.layer.getFrameIndex(window.name);
                        parent.layer.msg(res.msg || '保存成功');
                        parent.layer.close(index);
                    } else {
                        layer.msg(res.msg || '保存失败');
                    }
                },
                error: function () { layer.msg('网络异常'); },
                complete: function () {
                    $btn.find('i').attr('class', 'fa fa-check mr-5');
                    $btn.prop('disabled', false);
                }
            });
        });
    });
});
</script>
<?php endif; ?>
