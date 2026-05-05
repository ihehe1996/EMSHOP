<?php
defined('EM_ROOT') || exit('access denied!');

function plugin_setting_view() {
    $plugin_storage = Storage::getInstance('tips');
    $demo = $plugin_storage->getValue('demo');
?>

<div class="popup-inner">
<form class="layui-form" id="tipsForm" lay-filter="tipsForm">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">

    <div class="popup-section">

        <blockquote class="layui-elem-quote">
            这是世界上第一个EMSHOP插件。此处配置作为演示，也可用于插件开发参考。
        </blockquote>

        <div class="layui-form-item" style="margin-top: 16px;">
            <label class="layui-form-label">演示配置</label>
            <div class="layui-input-block">
                <input type="text" class="layui-input" name="demo" value="<?php echo htmlspecialchars((string) $demo, ENT_QUOTES, 'UTF-8'); ?>" placeholder="输入任意文本">
            </div>
            <div class="layui-form-mid layui-word-aux">输入的内容将作为插件的演示配置保存</div>
        </div>

    </div>

</form>
</div>

<div class="popup-footer">
    <button type="button" class="popup-btn popup-btn--default" id="tipsCancelBtn">取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="tipsSubmitBtn"><i class="layui-icon layui-icon-ok"></i> 保存配置</button>
</div>

<script>
(function(){
    layui.use(['layer', 'form'], function(){
        var $ = layui.$;
        var form = layui.form;
        form.render();

        $('#tipsCancelBtn').on('click', function(){
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        $('#tipsSubmitBtn').on('click', function(){
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="layui-icon layui-icon-loading layui-icon"></i> 保存中...');

            $.ajax({
                type: 'POST',
                url: window.PLUGIN_SAVE_URL || '/admin/plugin.php',
                data: $('#tipsForm').serialize() + '&_action=save_config&name=tips',
                dataType: 'json',
                success: function(res){
                    if (res.code === 0 || res.code === 200) {
                        var index = parent.layer.getFrameIndex(window.name);
                        if (res.data && res.data.csrf_token) {
                            $('#tipsForm input[name=csrf_token]').val(res.data.csrf_token);
                        }
                        parent.layer.msg('配置已保存');
                        parent.layer.close(index);
                    } else {
                        layui.layer.msg(res.msg || '保存失败');
                        $btn.prop('disabled', false).html('<i class="layui-icon layui-icon-ok"></i> 保存配置');
                    }
                },
                error: function(){
                    layui.layer.msg('网络异常');
                    $btn.prop('disabled', false).html('<i class="layui-icon layui-icon-ok"></i> 保存配置');
                }
            });
        });
    });
})();
</script>

<?php }

function plugin_setting() {
    $csrf = (string) Input::postStrVar('csrf_token');
    if (!Csrf::validate($csrf)) {
        Output::fail('请求已失效，请刷新页面后重试');
    }
    $demo = Input::postStrVar('demo');
    $plugin_storage = Storage::getInstance('tips');
    $plugin_storage->setValue('demo', $demo);
    Output::ok('配置已保存', ['csrf_token' => Csrf::refresh()]);
}
