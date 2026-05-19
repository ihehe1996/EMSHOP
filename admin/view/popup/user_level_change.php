<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
/** @var array<string, mixed> $levelUser */
/** @var array<int, array<string, mixed>> $userLevels */
$csrfToken = Csrf::token();
$esc = $esc ?? function (string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
};
$displayName = (string) ($levelUser['nickname'] ?? '') ?: (string) ($levelUser['username'] ?? '');
$currentLevelId = (int) ($levelUser['level_id'] ?? 0);
include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <form class="layui-form" id="userLevelForm" lay-filter="userLevelForm">
        <input type="hidden" name="_action" value="set_level">
        <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
        <input type="hidden" name="user_id" value="<?= (int) ($levelUser['id'] ?? 0) ?>">

        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">用户</label>
                <div class="layui-input-block">
                    <div class="layui-form-mid" style="padding-left:0;">
                        <strong><?= $esc($displayName) ?></strong>
                        <span style="color:#999; margin-left:8px;">ID: <?= (int) ($levelUser['id'] ?? 0) ?></span>
                    </div>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">用户等级</label>
                <div class="layui-input-block">
                    <select name="level_id" lay-search>
                        <option value="0" <?= $currentLevelId === 0 ? 'selected' : '' ?>>无等级（不打折）</option>
                        <?php foreach ($userLevels as $lv):
                            $lvId = (int) $lv['id'];
                            $lvName = (string) ($lv['name'] ?? '');
                            $disc = (float) ($lv['discount'] ?? 0);
                            $discTxt = $disc > 0 ? ' · ' . rtrim(rtrim(number_format($disc, 2), '0'), '.') . ' 折' : '';
                        ?>
                        <option value="<?= $lvId ?>" <?= $lvId === $currentLevelId ? 'selected' : '' ?>>
                            <?= $esc($lvName . $discTxt) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="layui-form-mid layui-word-aux">买家会员折扣，购买商品时按该等级折扣计算成交价</div>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="popup-footer">
    <button type="button" class="popup-btn" id="userLevelCancelBtn"><i class="fa fa-times"></i> 取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="userLevelSubmitBtn"><i class="fa fa-check mr-5"></i>保存</button>
</div>

<script>
$(function () {
    layui.use(['layer', 'form'], function () {
        var layer = layui.layer;
        var form = layui.form;
        form.render();

        $('#userLevelCancelBtn').on('click', function () {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        $('#userLevelSubmitBtn').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-5"></i>保存中...');

            $.ajax({
                type: 'POST',
                url: '/admin/user_list.php',
                data: $('#userLevelForm').serialize(),
                dataType: 'json',
                success: function (res) {
                    if (res.code === 200) {
                        if (res.data && res.data.csrf_token) {
                            $('#userLevelForm input[name=csrf_token]').val(res.data.csrf_token);
                        }
                        parent.window._userPopupSaved = true;
                        parent.layer.msg(res.msg || '保存成功');
                        var index = parent.layer.getFrameIndex(window.name);
                        parent.layer.close(index);
                    } else {
                        layer.msg(res.msg || '保存失败');
                        $btn.prop('disabled', false).html('<i class="fa fa-check mr-5"></i>保存');
                    }
                },
                error: function () {
                    layer.msg('网络异常');
                    $btn.prop('disabled', false).html('<i class="fa fa-check mr-5"></i>保存');
                }
            });
        });
    });
});
</script>
<?php
include __DIR__ . '/footer.php';
