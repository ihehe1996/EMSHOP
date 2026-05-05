<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
$isEdit = isset($editLevel) && $editLevel !== null;

$placeholderImg = EM_CONFIG['placeholder_img'];
$esc = function (string $str) use (&$esc): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
};
include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <form class="layui-form" id="levelForm" lay-filter="levelForm">
        <input type="hidden" name="_action" value="<?php echo $isEdit ? 'update' : 'create'; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">
        <input type="hidden" name="id" value="<?php echo $isEdit ? $esc($editLevel['id']) : ''; ?>">

        <!-- 基本信息 -->
        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">等级名称</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" id="levelName" name="name" maxlength="50" placeholder="如：铜牌会员、银牌会员" value="<?php echo $isEdit ? $esc($editLevel['name']) : ''; ?>">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">等级</label>
                <div class="layui-input-block">
                    <input type="number" class="layui-input" name="level" step="1" min="0" placeholder="填写数字"
                           value="<?php echo $isEdit ? (int) $editLevel['level'] : ''; ?>">
                </div>
            </div>
        </div>

        <!-- 会员权益 -->
        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">享受折扣</label>
                <div class="layui-input-block">
                    <div class="layui-input-wrap">
                        <input type="number" class="layui-input" id="levelDiscount" name="discount" step="0.1" min="1" max="10" placeholder="1~10"
                               value="<?php echo $isEdit ? $esc((string) $editLevel['discount']) : '9.9'; ?>">
                        <div class="layui-input-suffix">折</div>
                    </div>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">自助开通价格</label>
                <div class="layui-input-block">
                    <div class="layui-input-wrap">
                        <input type="number" class="layui-input" name="self_open_price" step="0.1" min="0" placeholder="金额（单位：元）"
                               value="<?php echo $isEdit ? number_format((float) $editLevel['self_open_price'], 2, '.', '') : '0.00'; ?>">
                        <div class="layui-input-suffix">元</div>
                    </div>
                </div>
                <div class="layui-form-mid layui-word-aux">0 = 不允许自助开通</div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">解锁经验值</label>
                <div class="layui-input-block">
                    <input type="number" class="layui-input" name="unlock_exp" step="1" min="0" placeholder="达到此经验值自动解锁"
                           value="<?php echo $isEdit ? (int) $editLevel['unlock_exp'] : 0; ?>">
                </div>
                <div class="layui-form-mid layui-word-aux">0 = 不启用自动解锁</div>
            </div>
        </div>


        <!-- 启用状态 -->
        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">启用状态</label>
                <div class="layui-input-block">
                    <input type="checkbox" name="enabled" lay-skin="switch" lay-text="启用|禁用" value="y"
                        <?php echo $isEdit ? ($editLevel['enabled'] === 'y' ? 'checked' : '') : 'checked'; ?>>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">备注</label>
                <div class="layui-input-block">
                    <textarea class="layui-textarea" name="remark" rows="2" maxlength="500" placeholder="可选填写备注信息"><?php echo $isEdit ? ($editLevel['remark'] ?? '') : ''; ?></textarea>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="popup-footer">
    <button type="button" class="popup-btn" id="levelCancelBtn"><i class="fa fa-times"></i> 取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="levelSubmitBtn"><i class="fa fa-check mr-5"></i>确认保存</button>
</div>

<script>
$(function () {
    layui.use(['layer', 'form'], function () {
        var layer = layui.layer;
        var form = layui.form;

        form.render('checkbox');
        form.render('switch');

        // ============================================================
        // 取消按钮
        // ============================================================
        $('#levelCancelBtn').on('click', function () {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        // ============================================================
        // 确认保存
        // ============================================================
        $('#levelSubmitBtn').on('click', function () {
            var name = $('#levelName').val();
            

            var $btn = $(this);
            $btn.find('i').attr('class', 'fa fa-refresh admin-spin mr-5');
            $btn.prop('disabled', true);

            $.ajax({
                url: '/admin/user_level.php',
                type: 'POST',
                data: $('#levelForm').serialize(),
                dataType: 'json',
                success: function (res) {
                    if (res.code === 200) {
                        var msg = res.msg || '保存成功';
                        if (res.data && res.data.csrf_token) {
                            try { parent.updateCsrf(res.data.csrf_token); } catch(e) {}
                        }
                        // 标记保存成功，父窗口关闭时刷新表格
                        try { parent.window._levelPopupSaved = true; } catch(e) {}
                        var index = parent.layer.getFrameIndex(window.name);
                        parent.layer.msg(msg);
                        parent.layer.close(index);
                    } else {
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
