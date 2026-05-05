<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
$isEdit = isset($editLang) && $editLang !== null;

$esc = function (string $str) use (&$esc): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
};
include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <form class="layui-form" id="langTransForm" lay-filter="langTransForm">
        <input type="hidden" name="_action" value="<?php echo $isEdit ? 'update' : 'batchCreate'; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">
        <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?php echo (int) $editLang['id']; ?>">
        <input type="hidden" name="lang_id" value="<?php echo (int) $editLang['lang_id']; ?>">
        <?php endif; ?>

        <div class="popup-section">
            <?php if ($isEdit): ?>
            <div class="layui-form-item">
                <label class="layui-form-label">所属语言</label>
                <div class="layui-input-block">
                    <span class="layui-form-readonly"><?php echo $editLangName ? $esc($editLangName) : '未知语言'; ?></span>
                </div>
            </div>
            <?php endif; ?>

            <div class="layui-form-item">
                <label class="layui-form-label"><?php echo $isEdit ? '翻译语句' : '翻译语句'; ?></label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" id="langTransTranslate" name="translate" maxlength="200"
                        placeholder="输入要翻译的语句(页面上默认展示的文字)"
                        value="<?php echo $isEdit ? $esc($editLang['translate']) : ''; ?>"
                        <?php echo $isEdit ? 'readonly' : ''; ?>>
                </div>
                <div class="layui-form-mid layui-word-aux"><?php echo $isEdit ? '编辑模式下不可修改' : '填写要翻译的原始语句,将同步填充到所有语言'; ?></div>
            </div>

            <?php if (!$isEdit): ?>
            <div class="popup-lang-translations">
                <div class="layui-form-item">
                    <label class="layui-form-label">各语言翻译</label>
                </div>
                <?php foreach ($languages as $lang): ?>
                <div class="layui-form-item">
                    <label class="layui-form-label popup-lang-label">
                        <?php echo $esc($lang['name']); ?> (<?php echo $esc($lang['code']); ?>)
                    </label>
                    <div class="layui-input-block">
                        <input type="text" class="layui-input popup-lang-content" name="translations[<?php echo (int) $lang['id']; ?>]"
                            maxlength="2000" placeholder="输入 <?php echo $esc($lang['name']); ?> 翻译">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="layui-form-item">
                <label class="layui-form-label">翻译内容</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" id="langTransContent" name="content" maxlength="2000" placeholder="输入翻译后的内容" value="<?php echo $esc($editLang['content']); ?>">
                </div>
            </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="popup-footer">
    <button type="button" class="popup-btn" id="langTransCancelBtn"><i class="fa fa-times"></i> 取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="langTransSubmitBtn"><i class="fa fa-check mr-5"></i>确认保存</button>
</div>

<script>
$(function () {
    layui.use(['layer', 'form'], function () {
        var layer = layui.layer;
        var form = layui.form;

        // 取消按钮
        $('#langTransCancelBtn').on('click', function () {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        // 确认保存
        $('#langTransSubmitBtn').on('click', function () {
            var isEdit = <?php echo $isEdit ? 'true' : 'false'; ?>;
            var translate = $('#langTransTranslate').val();

            if (!translate) {
                layer.msg('请填写翻译语句');
                return;
            }

            var $btn = $(this);
            $btn.find('i').attr('class', 'fa fa-refresh admin-spin mr-5');
            $btn.prop('disabled', true);

            $.ajax({
                url: '/admin/lang.php',
                type: 'POST',
                data: $('#langTransForm').serialize(),
                dataType: 'json',
                success: function (res) {
                    if (res.code === 200) {
                        var msg = res.msg || '保存成功';
                        if (res.data && res.data.csrf_token) {
                            try { parent.updateCsrf(res.data.csrf_token); } catch(e) {}
                        }
                        try { parent.window._langTransPopupSaved = true; } catch(e) {}
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
<style>
.layui-form-readonly {
    display: block;
    padding: 9px 15px;
    background: #f5f5f5;
    border: 1px solid #e6e6e6;
    border-radius: 4px;
    color: #666;
    font-size: 14px;
}
.popup-lang-label {
    font-size: 13px;
    width: 130px !important;
}
.popup-lang-content {
    font-size: 13px;
}
</style>
<?php
include __DIR__ . '/footer.php';
