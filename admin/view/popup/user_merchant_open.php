<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
/** @var array<string, mixed> $presetUser */
/** @var array<int, array<string, mixed>> $levels */
$csrfToken = Csrf::token();
$mainDomain = trim((string) (Config::get('main_domain') ?? ''));
$esc = $esc ?? function (string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
};
$displayName = (string) ($presetUser['nickname'] ?? '') ?: (string) ($presetUser['username'] ?? '');
$defaultShopName = $displayName !== '' ? $displayName . '的店铺' : '';
include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <form class="layui-form" id="userMchOpenForm" lay-filter="userMchOpenForm">
        <input type="hidden" name="_action" value="open_merchant">
        <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
        <input type="hidden" name="user_id" value="<?= (int) ($presetUser['id'] ?? 0) ?>">

        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">商户主</label>
                <div class="layui-input-block">
                    <div style="padding:10px 12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;font-size:13px;line-height:1.6;">
                        <strong style="color:#166534;"><?= $esc($displayName) ?></strong>
                        <span style="color:#6b7280;">（ID <?= (int) ($presetUser['id'] ?? 0) ?>）</span><br>
                        <span style="color:#6b7280;">账号：<?= $esc((string) ($presetUser['username'] ?? '')) ?></span>
                        <?php if (!empty($presetUser['email'])): ?>
                        <span style="color:#6b7280;"> · <?= $esc((string) $presetUser['email']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">商户等级</label>
                <div class="layui-input-block">
                    <select name="level_id" lay-verify="required">
                        <option value="">请选择等级</option>
                        <?php foreach ($levels as $lv): ?>
                        <option value="<?= (int) $lv['id'] ?>"><?= $esc((string) ($lv['name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">店铺名</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" name="name" id="userMchOpenName" maxlength="100"
                           value="<?= $esc($defaultShopName) ?>" placeholder="商户店铺对外展示名称">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">二级域名</label>
                <div class="layui-input-block">
                    <div style="display:flex;align-items:stretch;">
                        <input type="text" class="layui-input" name="subdomain" id="userMchOpenSubdomain" maxlength="64"
                               placeholder="如 shop1" style="flex:1;min-width:0;border-radius:4px 0 0 4px;">
                        <?php if ($mainDomain !== ''): ?>
                        <span style="flex-shrink:0;padding:0 10px;line-height:38px;background:#f3f4f6;border:1px solid #e5e7eb;border-left:0;border-radius:0 4px 4px 0;color:#6b7280;font-size:13px;">.<?= $esc($mainDomain) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="layui-form-mid layui-word-aux">
                        <?php if ($mainDomain !== ''): ?>
                        前缀仅小写字母、数字、连字符；主域名 <code><?= $esc($mainDomain) ?></code> 需配置泛解析
                        <?php else: ?>
                        主站根域名未配置，请先在 <a href="/admin/settings.php?action=substation" target="_top" style="color:#2563eb;">分站配置</a> 中填写主域名
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">自定义域名</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" name="custom_domain" id="userMchOpenCustomDomain" maxlength="200"
                           placeholder="如 www.myshop.com（选填）">
                    <div class="layui-form-mid layui-word-aux">选填；需 CNAME 到主站，且商户等级须允许自定义域名</div>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="popup-footer">
    <button type="button" class="popup-btn" id="userMchOpenCancelBtn"><i class="fa fa-times"></i> 取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="userMchOpenSubmitBtn"><i class="fa fa-check mr-5"></i>开通分站</button>
</div>

<script>
$(function () {
    layui.use(['layer', 'form'], function () {
        var layer = layui.layer;
        var form = layui.form;
        form.render();

        $('#userMchOpenCancelBtn').on('click', function () {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        $('#userMchOpenSubmitBtn').on('click', function () {
            if (!$('select[name="level_id"]').val()) { layer.msg('请选择商户等级'); return; }
            if (!$.trim($('#userMchOpenName').val())) { layer.msg('请填写店铺名'); return; }
            if (!$.trim($('#userMchOpenSubdomain').val())) { layer.msg('请填写二级域名前缀'); return; }
            var $btn = $(this);
            $btn.find('i').attr('class', 'fa fa-refresh admin-spin mr-5');
            $btn.prop('disabled', true);

            $.ajax({
                url: '/admin/user_list.php',
                type: 'POST',
                data: $('#userMchOpenForm').serialize(),
                dataType: 'json',
                success: function (res) {
                    if (res.code === 200) {
                        if (res.data && res.data.csrf_token) {
                            try { parent.updateCsrf(res.data.csrf_token); } catch (e) {}
                        }
                        try { parent.window._userMchOpenSaved = true; } catch (e) {}
                        var index = parent.layer.getFrameIndex(window.name);
                        parent.layer.msg(res.msg || '开通成功');
                        parent.layer.close(index);
                    } else {
                        layer.msg(res.msg || '开通失败');
                    }
                },
                error: function () { layer.msg('网络错误，请重试'); },
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
