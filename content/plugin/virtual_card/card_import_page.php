<?php
/**
 * 卡密导入弹窗页面
 *
 * 独立弹窗，由 card_import_page action 加载。
 * 必须选择规格，支持去重勾选、导入顺序选择。
 *
 * 可用变量：$goods, $specs, $goodsId, $csrfToken
 */
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$esc = function (string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
};
?>

<div class="popup-inner">

    <div class="popup-section">
        <div class="form-group">
            <label class="form-group__label">选择规格 <span style="color:#ff4d4f;">*</span></label>
            <select id="importSpecId" class="form-group__input" style="height:36px;">
                <option value="">-- 请选择规格 --</option>
                <?php foreach ($specs as $spec): ?>
                <option value="<?php echo (int)$spec['id']; ?>"<?php echo count($specs) === 1 ? ' selected' : ''; ?>>
                    <?php echo $esc($spec['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-group__label">卡密内容 <span style="color:#ff4d4f;">*</span></label>
            <textarea id="importContent" class="form-group__textarea" style="min-height:160px;font-family:Consolas,'Courier New',monospace;font-size:13px;" placeholder="每行一个卡密，支持以下格式：&#10;&#10;卡号&#10;卡号:密码&#10;卡号|密码"></textarea>
            <div class="form-group__help" id="lineCount">已输入 0 条</div>
        </div>

        <div class="form-group">
            <label class="form-group__check">
                <input type="checkbox" id="importDedup">
                跳过已存在的卡密（去重）
            </label>
            <div class="form-group__help">勾选后，与库存中已有卡号相同的行将自动跳过不导入</div>
        </div>

        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:0;">
            <div class="form-group" style="flex:1;min-width:140px;margin-bottom:0;">
                <label class="form-group__label">导入顺序</label>
                <select id="importOrder" class="form-group__input" style="height:36px;">
                    <option value="asc">从上至下导入</option>
                    <option value="desc">从下至上导入</option>
                    <option value="shuffle">打乱顺序导入</option>
                </select>
            </div>
            <div class="form-group" style="flex:1;min-width:140px;margin-bottom:0;">
                <label class="form-group__label">备注（可选）</label>
                <input type="text" id="importRemark" class="form-group__input" placeholder="如：第3批采购">
            </div>
        </div>
    </div>

</div><!-- /popup-inner -->

<div class="popup-footer">
    <button type="button" class="popup-btn" id="cancelBtn"><i class="fa fa-times"></i> 取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="importBtn"><i class="fa fa-upload mr-5"></i>开始导入</button>
</div>

<script>
$(function() {
    var layer = layui.layer;
    var csrfToken = <?php echo json_encode($csrfToken); ?>;
    var goodsId = <?php echo $goodsId; ?>;

    // 实时统计行数
    $('#importContent').on('input', function() {
        var text = $.trim($(this).val());
        var count = text ? text.split('\n').filter(function(l) { return $.trim(l) !== ''; }).length : 0;
        $('#lineCount').text('已输入 ' + count + ' 条');
    });

    // 取消
    $('#cancelBtn').on('click', function() {
        var index = parent.layer.getFrameIndex(window.name);
        parent.layer.close(index);
    });

    // 导入
    $('#importBtn').on('click', function() {
        var specId = $('#importSpecId').val();
        if (!specId) {
            layer.msg('请选择规格');
            return;
        }
        var content = $.trim($('#importContent').val());
        if (!content) {
            layer.msg('请输入卡密内容');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-5"></i>导入中...');

        $.ajax({
            url: '/admin/index.php?_action=card_import',
            type: 'POST',
            dataType: 'json',
            data: {
                csrf_token: csrfToken,
                goods_id: goodsId,
                spec_id: specId,
                cards: content,
                order: $('#importOrder').val(),
                dedup: $('#importDedup').prop('checked') ? 1 : 0,
                remark: $('#importRemark').val()
            },
            success: function(res) {
                if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                if (res.code === 200) {
                    parent.layer.msg(res.msg || '导入成功');
                    var index = parent.layer.getFrameIndex(window.name);
                    parent.layer.close(index);
                } else {
                    layer.msg(res.msg || '导入失败');
                }
            },
            error: function() { layer.msg('网络异常'); },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="fa fa-upload mr-5"></i>开始导入');
            }
        });
    });
});
</script>
