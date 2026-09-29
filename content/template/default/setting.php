<?php
defined('EM_ROOT') || exit('access denied!');

/**
 * 测试模板设置页。
 *
 * 配置项：
 *   - featured_count      首页「精选商品」显示多少个（默认 6）
 *   - goods_sort_enabled  商品列表排序功能：商品列表页是否显示排序栏（开关，默认开启）
 *
 * 读取用核心的 template_config()——它只负责把当前主题的配置整包取出来，
 * 不认识任何具体配置项；"有哪些配置项、默认值多少"由本文件与模板自己决定。
 */

function template_setting_view() {
    $themeName = (string) ($_GET['name'] ?? 'default');
    $cfg = template_config($themeName);
    // 与 index.php 里同一套取值规则（默认 6，1–30）
    $featuredCount = max(1, min(30, (int) ($cfg['featured_count'] ?? 6)));
    // 开关：没存过视为开启（默认开）
    $sortEnabled = (string) ($cfg['goods_sort_enabled'] ?? '1') !== '0';
?>

<div class="popup-inner">
<form class="layui-form" id="testTemplateForm" lay-filter="testTemplateForm" onsubmit="return false">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">

    <div class="popup-section">
        <div class="layui-form-item" style="margin-top:6px;">
            <label class="layui-form-label">精选商品显示数量</label>
            <div class="layui-input-block admin-form-width-sm">
                <input type="number" class="layui-input" name="featured_count" min="1" max="30"
                       value="<?php echo (int) $featuredCount; ?>">
            </div>
            <?php /* clear:both 强制提示落到输入框下一行：弹窗窄、提示较长，
                     否则 layui 的 float 布局会把提示挤到输入框那一行上 */
            ?>
            <div class="layui-form-mid layui-word-aux" style="clear:both;">首页显示多少个（1–30，默认 6）</div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">商品列表排序功能</label>
            <div class="layui-input-block">
                <input type="checkbox" name="goods_sort_enabled" lay-skin="switch" lay-text="开启|关闭"
                       value="1"<?php echo $sortEnabled ? ' checked' : ''; ?>>
            </div>
            <div class="layui-form-mid layui-word-aux" style="clear:both;">关闭后商品列表页不显示排序栏（默认 / 热度 / 销量 / 价格）</div>
        </div>
    </div>
</form>
</div>

<div class="popup-footer">
    <button type="button" class="popup-btn popup-btn--default" id="testTemplateCancelBtn">取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="testTemplateSubmitBtn"><i class="layui-icon layui-icon-ok"></i> 保存配置</button>
</div>

<script>
(function(){
    layui.use(['layer', 'form'], function(){
        var $ = layui.$;
        var layer = layui.layer;
        var form = layui.form;

        // 开关必须渲染一次才会变成 layui 的开关样式（否则是原生 checkbox）
        form.render();

        // 与弹窗 URL ?name= 一致，须为磁盘目录名（勿写死，否则 save_config 会报「磁盘上未找到该模板」）
        var templateDirName = <?php echo json_encode((string) ($_GET['name'] ?? 'default'), JSON_UNESCAPED_UNICODE); ?>;

        // 取消
        $('#testTemplateCancelBtn').on('click', function(){
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        // 保存
        function saveTemplateConfig(){
            var $btn = $('#testTemplateSubmitBtn');
            if ($btn.prop('disabled')) { return; }   // 防连点/重复提交
            $btn.prop('disabled', true).html('<i class="layui-icon layui-icon-loading"></i> 保存中...');

            var formData = $('#testTemplateForm').serialize();
            formData += '&_action=save_config&name=' + encodeURIComponent(templateDirName);

            // URL 由 popup header 注入到 iframe 自身 window（主站默认 /admin/template.php，商户覆盖为 /user/merchant/template.php）
            var __saveUrl = window.TEMPLATE_SAVE_URL || '/admin/template.php';
            $.ajax({
                type: 'POST',
                url: __saveUrl,
                data: formData,
                dataType: 'json',
                success: function(res){
                    if (res.code === 0 || res.code === 200) {
                        if (res.data && res.data.csrf_token) {
                            $('#testTemplateForm input[name=csrf_token]').val(res.data.csrf_token);
                        }
                        parent.layer.msg('配置已保存');
                        parent.layer.close(parent.layer.getFrameIndex(window.name));
                    } else {
                        layer.msg(res.msg || '保存失败');
                        $btn.prop('disabled', false).html('<i class="layui-icon layui-icon-ok"></i> 保存配置');
                    }
                },
                error: function(){
                    layer.msg('网络异常');
                    $btn.prop('disabled', false).html('<i class="layui-icon layui-icon-ok"></i> 保存配置');
                }
            });
        }

        $('#testTemplateSubmitBtn').on('click', function(){
            saveTemplateConfig();
        });

        // 回车提交：表单没有 action，浏览器默认提交会让 iframe 直接导航走（原来就是这个毛病），
        // 所以拦下 submit 事件改用同一套 AJAX 保存，并把默认行为挡掉。
        $('#testTemplateForm').on('submit', function(){
            saveTemplateConfig();
            return false;
        });
    });
})();
</script>

<?php }

/**
 * 保存模板配置。
 */
function template_setting() {
    $csrf = (string) Input::postStrVar('csrf_token');
    if (!Csrf::validate($csrf)) {
        Output::fail('请求已失效，请刷新页面后重试');
    }

    $storage = TemplateStorage::getInstance((string) Input::postStrVar('name') ?: 'default');

    // 精选商品显示数量：夹取到 1–30，非法值落回默认 6
    $count = (int) Input::postStrVar('featured_count');
    if ($count < 1 || $count > 30) {
        $count = 6;
    }
    $storage->setValue('featured_count', (string) $count);

    // 商品列表排序功能（开关）：未勾选时浏览器不会提交该字段，所以按"有没有值"判断
    $storage->setValue('goods_sort_enabled', !empty($_POST['goods_sort_enabled']) ? '1' : '0');

    Output::ok('配置已保存', ['csrf_token' => Csrf::refresh()]);
}
