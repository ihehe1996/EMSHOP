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

// 导出用：规格 id + 名称（库存数量由前端从当前输入框实时读取，保证「所见即所得」）
$exportSpecs = [];
foreach ($specs as $spec) {
    $exportSpecs[] = ['id' => (int)$spec['id'], 'name' => (string)$spec['name']];
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
    <button type="button" class="popup-btn" id="stockExportBtn"><i class="fa fa-download mr-5"></i>导出</button>
    <button type="button" class="popup-btn" id="stockCancelBtn"><i class="fa fa-times"></i> 取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="stockSaveBtn"><i class="fa fa-check mr-5"></i>保存库存</button>
</div>

<style>
.stock-input { height: 32px !important; text-align: center; }
.stock-input:focus { border-color: #1e9fff; }
</style>

<script>
var STOCK_GOODS_TITLE = <?php echo json_encode($goods['title'] ?? '', JSON_UNESCAPED_UNICODE); ?>;
var STOCK_GOODS_ID    = <?php echo (int)($goods['id'] ?? 0); ?>;
var STOCK_SPECS       = <?php echo json_encode($exportSpecs, JSON_UNESCAPED_UNICODE); ?>;

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

        // ============================================================
        //  导出库存清单（TXT）
        //  纯前端生成：读取当前输入框的库存值，拼成对齐的文本后下载。
        //  CJK 字符按 2 宽度对齐，保证记事本 / 等宽字体下表格整齐。
        // ============================================================
        function stockDispWidth(s) {
            var w = 0;
            for (var i = 0; i < s.length; i++) {
                var c = s.charCodeAt(i);
                if (c >= 0x1100 && (
                    c <= 0x115F || c === 0x2329 || c === 0x232A ||
                    (c >= 0x2E80 && c <= 0xA4CF && c !== 0x303F) ||
                    (c >= 0xAC00 && c <= 0xD7A3) ||
                    (c >= 0xF900 && c <= 0xFAFF) ||
                    (c >= 0xFE30 && c <= 0xFE4F) ||
                    (c >= 0xFF00 && c <= 0xFF60) ||
                    (c >= 0xFFE0 && c <= 0xFFE6) ||
                    (c >= 0x20000 && c <= 0x2FFFD) ||
                    (c >= 0x30000 && c <= 0x3FFFD)
                )) {
                    w += 2;
                } else {
                    w += 1;
                }
            }
            return w;
        }
        function stockPadRight(s, width) {
            var pad = width - stockDispWidth(s);
            return s + (pad > 0 ? new Array(pad + 1).join(' ') : ' ');
        }
        function stockPadLeft(s, width) {
            var pad = width - stockDispWidth(s);
            return (pad > 0 ? new Array(pad + 1).join(' ') : '') + s;
        }
        function stockPad2(n) { return (n < 10 ? '0' : '') + n; }

        function stockBuildExportText() {
            var rows = [];
            var total = 0;
            for (var i = 0; i < STOCK_SPECS.length; i++) {
                var spec = STOCK_SPECS[i];
                var val = parseInt($('input[name="spec_stock[' + spec.id + ']"]').val(), 10);
                if (isNaN(val) || val < 0) val = 0;
                total += val;
                rows.push({ name: spec.name || '', stock: val });
            }

            var now = new Date();
            var ts = now.getFullYear() + '-' + stockPad2(now.getMonth() + 1) + '-' + stockPad2(now.getDate())
                   + ' ' + stockPad2(now.getHours()) + ':' + stockPad2(now.getMinutes()) + ':' + stockPad2(now.getSeconds());

            var line = [];
            line.push('虚拟卡密商品 · 库存清单');
            line.push('========================================');
            line.push('商品名称：' + (STOCK_GOODS_TITLE || ''));
            line.push('商品 ID ：' + STOCK_GOODS_ID);
            line.push('导出时间：' + ts);
            line.push('');
            line.push('----------------------------------------');
            line.push(stockPadRight('规格名称', 22) + stockPadLeft('库存数量', 10));
            line.push('----------------------------------------');
            for (var j = 0; j < rows.length; j++) {
                line.push(stockPadRight(rows[j].name, 22) + stockPadLeft(String(rows[j].stock), 10));
            }
            line.push('----------------------------------------');
            line.push(stockPadRight('合计', 22) + stockPadLeft(String(total), 10));
            return line.join('\r\n');
        }

        function stockBuildFilename() {
            var t = String(STOCK_GOODS_TITLE || '库存').trim();
            t = t.replace(/[\\\/:*?"<>|]/g, '_').replace(/\s+/g, ' ').trim();
            if (t.length > 50) t = t.substring(0, 50);
            if (!t) t = '库存';
            var now = new Date();
            var stamp = now.getFullYear() + stockPad2(now.getMonth() + 1) + stockPad2(now.getDate())
                      + '_' + stockPad2(now.getHours()) + stockPad2(now.getMinutes()) + stockPad2(now.getSeconds());
            return t + '_库存清单_' + stamp + '.txt';
        }

        $('#stockExportBtn').on('click', function () {
            var text = stockBuildExportText();
            var filename = stockBuildFilename();
            // 带 BOM，Windows 记事本可正确识别 UTF-8，中文不乱码
            var blob = new Blob(['\uFEFF' + text], { type: 'text/plain;charset=utf-8' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(function () { URL.revokeObjectURL(url); }, 1500);
        });
    });
});
</script>
<?php endif; ?>
