<?php
defined('EM_ROOT') || exit('Access Denied');

function virtualCardSettingNormalizeIssueOrder($value): string
{
    $order = strtolower(trim((string) $value));
    $map = [
        'old' => 'old',
        'asc' => 'old',
        'new' => 'new',
        'desc' => 'new',
        'random' => 'random',
        'rand' => 'random',
        'shuffle' => 'random',
    ];
    return $map[$order] ?? 'old';
}

function plugin_setting_view(): void
{
    $storage = Storage::getInstance('virtual_card');
    $issueOrder = virtualCardSettingNormalizeIssueOrder($storage->getValue('card_issue_order'));
    $csrfToken = Csrf::token();
    ?>
    <div class="popup-inner">
        <form class="layui-form" id="virtualCardSettingForm" lay-filter="virtualCardSettingForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="popup-section">
                <div class="layui-form-item">
                    <label class="layui-form-label">默认出卡顺序</label>
                    <div class="layui-input-block">
                        <select name="card_issue_order">
                            <option value="old" <?= $issueOrder === 'old' ? 'selected' : '' ?>>旧卡优先</option>
                            <option value="new" <?= $issueOrder === 'new' ? 'selected' : '' ?>>新卡优先</option>
                            <option value="random" <?= $issueOrder === 'random' ? 'selected' : '' ?>>随机出卡</option>
                        </select>
                    </div>
                    <div class="layui-form-mid layui-word-aux">
                        作为全局默认值使用；若商品类型配置中指定了出卡顺序，则以商品类型配置为准
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="popup-footer">
        <button type="button" class="popup-btn popup-btn--default" id="virtualCardSettingCancelBtn">取消</button>
        <button type="button" class="popup-btn popup-btn--primary" id="virtualCardSettingSaveBtn"><i class="layui-icon layui-icon-ok"></i> 保存配置</button>
    </div>
    <script>
    (function(){
        layui.use(['layer', 'form'], function(){
            var $ = layui.$;
            layui.form.render();

            $('#virtualCardSettingCancelBtn').on('click', function(){
                parent.layer.close(parent.layer.getFrameIndex(window.name));
            });

            $('#virtualCardSettingSaveBtn').on('click', function(){
                var $btn = $(this);
                $btn.prop('disabled', true).html('<i class="layui-icon layui-icon-loading"></i> 保存中...');
                $.ajax({
                    type: 'POST',
                    url: window.PLUGIN_SAVE_URL || '/admin/plugin.php',
                    data: $('#virtualCardSettingForm').serialize() + '&_action=save_config&name=virtual_card',
                    dataType: 'json',
                    success: function(res){
                        if (res.code === 0 || res.code === 200) {
                            if (res.data && res.data.csrf_token) {
                                $('#virtualCardSettingForm input[name=csrf_token]').val(res.data.csrf_token);
                            }
                            parent.layer.msg('配置已保存');
                            parent.layer.close(parent.layer.getFrameIndex(window.name));
                            return;
                        }
                        layer.msg(res.msg || '保存失败');
                        $btn.prop('disabled', false).html('<i class="layui-icon layui-icon-ok"></i> 保存配置');
                    },
                    error: function(){
                        layer.msg('网络异常');
                        $btn.prop('disabled', false).html('<i class="layui-icon layui-icon-ok"></i> 保存配置');
                    }
                });
            });
        });
    })();
    </script>
    <?php
}

function plugin_setting(): void
{
    $csrf = (string) Input::post('csrf_token', '');
    if (!Csrf::validate($csrf)) {
        Response::error('请求已失效，请刷新页面后重试');
    }

    $issueOrder = virtualCardSettingNormalizeIssueOrder(Input::post('card_issue_order', 'old'));
    $storage = Storage::getInstance('virtual_card');
    $storage->setValue('card_issue_order', $issueOrder);

    Response::success('配置已保存', ['csrf_token' => Csrf::refresh()]);
}
