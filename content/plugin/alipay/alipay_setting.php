<?php
defined('EM_ROOT') || exit('Access Denied');

function plugin_setting_view(): void
{
    $storage = Storage::getInstance('alipay');
    $displayName = (string) ($storage->getValue('display_name') ?: '');
    $appId = (string) ($storage->getValue('app_id') ?: '');
    $alipayPublicKey = (string) ($storage->getValue('alipay_public_key') ?: '');
    $appPrivateKey = (string) ($storage->getValue('app_private_key') ?: '');

    $modeWebRaw = $storage->getValue('mode_web');
    $modeWapRaw = $storage->getValue('mode_wap');
    $modeFaceRaw = $storage->getValue('mode_face');

    // 三种支付类型默认都不勾选。
    $modeWebEnabled = ((string) $modeWebRaw === '1');
    $modeWapEnabled = ((string) $modeWapRaw === '1');
    $modeFaceEnabled = ((string) $modeFaceRaw === '1');

    $csrfToken = Csrf::token();
    ?>
    <div class="popup-inner">
        <form class="layui-form" id="alipayForm" lay-filter="alipayForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="popup-section">
                <div class="layui-form-item">
                    <label class="layui-form-label">外显名称</label>
                    <div class="layui-input-block">
                        <input type="text" class="layui-input" name="display_name" value="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>" placeholder="支付宝">
                    </div>
                    <div class="layui-form-mid layui-word-aux">留空则显示默认名称"支付宝"</div>
                </div>
            </div>

            <div class="popup-section">
                <div class="layui-form-item">
                    <label class="layui-form-label">支付类型</label>
                    <div class="layui-input-block">
                        <input type="checkbox" name="mode_web" value="1" title="电脑端支付" <?= $modeWebEnabled ? 'checked' : '' ?>>
                        <input type="checkbox" name="mode_wap" value="1" title="手机端支付" <?= $modeWapEnabled ? 'checked' : '' ?>>
                        <input type="checkbox" name="mode_face" value="1" title="当面付" <?= $modeFaceEnabled ? 'checked' : '' ?>>
                    </div>
                    <div class="layui-form-mid layui-word-aux">支持多选；系统会按访问场景自动选择合适通道。</div>
                </div>
            </div>

            <div class="popup-section">
                <div class="layui-form-item">
                    <label class="layui-form-label">APPID</label>
                    <div class="layui-input-block">
                        <input type="text" class="layui-input" name="app_id" value="<?= htmlspecialchars($appId, ENT_QUOTES, 'UTF-8') ?>" placeholder="请输入支付宝应用 APPID" autocomplete="off">
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">支付宝公钥</label>
                    <div class="layui-input-block">
                        <textarea class="layui-textarea" name="alipay_public_key" placeholder="粘贴支付宝公钥（支持带/不带 BEGIN PUBLIC KEY）" style="min-height:120px;"><?= htmlspecialchars($alipayPublicKey, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">应用私钥</label>
                    <div class="layui-input-block">
                        <textarea class="layui-textarea" name="app_private_key" placeholder="粘贴应用私钥（支持带/不带 BEGIN PRIVATE KEY）" style="min-height:140px;"><?= htmlspecialchars($appPrivateKey, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="popup-footer">
        <button type="button" class="popup-btn popup-btn--default" id="alipayCancelBtn">取消</button>
        <button type="button" class="popup-btn popup-btn--primary" id="alipaySaveBtn"><i class="layui-icon layui-icon-ok"></i> 保存配置</button>
    </div>
    <script>
    (function(){
        layui.use(['layer', 'form'], function(){
            var $ = layui.$; layui.form.render();
            $('#alipayCancelBtn').on('click', function(){ parent.layer.close(parent.layer.getFrameIndex(window.name)); });
            $('#alipaySaveBtn').on('click', function(){
                var $btn = $(this);
                $btn.prop('disabled', true).html('<i class="layui-icon layui-icon-loading"></i> 保存中...');
                $.ajax({ type:'POST', url: window.PLUGIN_SAVE_URL || '/admin/plugin.php', data: $('#alipayForm').serialize()+'&_action=save_config&name=alipay', dataType:'json',
                    success: function(res){
                        if(res.code===0||res.code===200){ if(res.data&&res.data.csrf_token) $('#alipayForm input[name=csrf_token]').val(res.data.csrf_token); parent.layer.msg('配置已保存'); parent.layer.close(parent.layer.getFrameIndex(window.name)); }
                        else{ layui.layer.msg(res.msg||'保存失败'); $btn.prop('disabled',false).html('<i class="layui-icon layui-icon-ok"></i> 保存配置'); }
                    },
                    error: function(){ layui.layer.msg('网络异常'); $btn.prop('disabled',false).html('<i class="layui-icon layui-icon-ok"></i> 保存配置'); }
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

    $appId = trim((string) Input::post('app_id', ''));
    $alipayPublicKey = trim((string) Input::post('alipay_public_key', ''));
    $appPrivateKey = trim((string) Input::post('app_private_key', ''));

    $modeWeb = Input::post('mode_web', '') === '1' ? '1' : '0';
    $modeWap = Input::post('mode_wap', '') === '1' ? '1' : '0';
    $modeFace = Input::post('mode_face', '') === '1' ? '1' : '0';

    $storage = Storage::getInstance('alipay');
    $storage->setValue('display_name', (string) Input::post('display_name', ''));
    $storage->setValue('app_id', $appId);
    $storage->setValue('alipay_public_key', $alipayPublicKey);
    $storage->setValue('app_private_key', $appPrivateKey);
    $storage->setValue('mode_web', $modeWeb);
    $storage->setValue('mode_wap', $modeWap);
    $storage->setValue('mode_face', $modeFace);

    Response::success('配置已保存', ['csrf_token' => Csrf::refresh()]);
}
