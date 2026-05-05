<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
$levels = $levels ?? [];
$editRow = $editRow ?? [];
$esc = $esc ?? function (string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
};

$val = function (string $k, $default = '') use ($editRow, $esc) {
    return isset($editRow[$k]) && $editRow[$k] !== null ? $esc((string) $editRow[$k]) : $esc((string) $default);
};
$intVal = function (string $k, int $default = 0) use ($editRow): int {
    return isset($editRow[$k]) ? (int) $editRow[$k] : $default;
};
$chk = function (string $k, int $default = 0) use ($editRow): string {
    $v = isset($editRow[$k]) ? (int) $editRow[$k] : $default;
    return $v === 1 ? 'checked' : '';
};

include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <form class="layui-form" id="mchEditForm" lay-filter="mchEditForm">
        <input type="hidden" name="_action" value="update">
        <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
        <input type="hidden" name="id" value="<?= $esc((string) $editRow['id']) ?>">

        <!-- 选项卡（em-tabs，和商品分类编辑弹窗同款） -->
        <div class="em-tabs" id="mchEditTabs" style="margin-bottom: 0;">
            <a class="em-tabs__item is-active"><i class="fa fa-info-circle"></i>基础信息</a>
            <a class="em-tabs__item"><i class="fa fa-image"></i>店铺展示</a>
            <a class="em-tabs__item"><i class="fa fa-globe"></i>域名设置</a>
        </div>
        <div class="layui-tab-content mch-edit-content">

            <!-- ========== Tab 1: 基础信息 ========== -->
            <div class="layui-tab-item layui-show">
                <div class="popup-section">
                    <div class="layui-form-item">
                        <label class="layui-form-label">slug</label>
                        <div class="layui-input-block">
                            <input type="text" class="layui-input" readonly value="<?= $val('slug') ?>">
                        </div>
                        <div class="layui-form-mid layui-word-aux">开通后不可修改</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">商户主</label>
                        <div class="layui-input-block">
                            <input type="text" class="layui-input" readonly
                                   value="<?= $val('user_nickname') ?: $val('user_username') ?> (ID: <?= $intVal('user_id') ?>)">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">店铺名</label>
                        <div class="layui-input-block">
                            <input type="text" class="layui-input" name="name" maxlength="100" value="<?= $val('name') ?>">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">等级</label>
                        <div class="layui-input-block">
                            <select name="level_id">
                                <?php foreach ($levels as $lv): ?>
                                <option value="<?= (int) $lv['id'] ?>" <?= ((int) $lv['id']) === $intVal('level_id') ? 'selected' : '' ?>>
                                    <?= $esc($lv['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">上级商户</label>
                        <div class="layui-input-block">
                            <input type="text" class="layui-input" readonly
                                   value="<?= $intVal('parent_id') > 0 ? $val('parent_name') . ' (slug: ' . $val('parent_slug') . ')' : '—' ?>">
                        </div>
                        <div class="layui-form-mid layui-word-aux">上级关系开通时确定，如需变更请重新开通</div>
                    </div>
                </div>
            </div>

            <!-- ========== Tab 2: 店铺展示 ========== -->
            <div class="layui-tab-item">
                <div class="popup-section">
                    <div class="layui-form-item">
                        <label class="layui-form-label">Logo</label>
                        <div class="layui-input-block">
                            <input type="text" class="layui-input" name="logo" maxlength="500" placeholder="Logo 图片 URL" value="<?= $val('logo') ?>">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">Slogan</label>
                        <div class="layui-input-block">
                            <input type="text" class="layui-input" name="slogan" maxlength="255" value="<?= $val('slogan') ?>">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">详细介绍</label>
                        <div class="layui-input-block">
                            <textarea class="layui-textarea" name="description" rows="3"><?= htmlspecialchars((string) ($editRow['description'] ?? ''), ENT_QUOTES) ?></textarea>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">备案号</label>
                        <div class="layui-input-block">
                            <input type="text" class="layui-input" name="icp" maxlength="100" value="<?= $val('icp') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========== Tab 3: 域名设置 ========== -->
            <div class="layui-tab-item">
                <div class="popup-section">
                    <div class="layui-form-item">
                        <label class="layui-form-label">二级域名</label>
                        <div class="layui-input-block">
                            <input type="text" class="layui-input" name="subdomain" maxlength="64" placeholder="如 shop1" value="<?= $val('subdomain') ?>">
                        </div>
                        <div class="layui-form-mid layui-word-aux">商户等级需允许二级域名方生效</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">自定义域名</label>
                        <div class="layui-input-block">
                            <input type="text" class="layui-input" name="custom_domain" maxlength="200" placeholder="如 www.myshop.com" value="<?= $val('custom_domain') ?>">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">域名已验证</label>
                        <div class="layui-input-block">
                            <input type="checkbox" name="domain_verified" lay-skin="switch" lay-text="已验证|未验证" value="1" <?= $chk('domain_verified') ?>>
                        </div>
                        <div class="layui-form-mid layui-word-aux">自定义域名必须此处勾选方能生效</div>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

<div class="popup-footer">
    <button type="button" class="popup-btn" id="mchEditCancelBtn"><i class="fa fa-times"></i> 取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="mchEditSubmitBtn"><i class="fa fa-check mr-5"></i>保存</button>
</div>

<style>
/* 选项卡内的 layui-tab-item 去掉默认 padding（和 goods_category.php 做法一致） */
.mch-edit-content > .layui-tab-item { padding: 0; }
</style>

<script>
$(function () {
    // em-tabs 点击切换：同步 .is-active 到 tab 项，同步 .layui-show 到对应面板
    $('#mchEditTabs').on('click', '.em-tabs__item', function () {
        var $item = $(this);
        if ($item.hasClass('is-active')) return;
        var index = $item.index();
        $item.addClass('is-active').siblings().removeClass('is-active');
        $item.closest('.em-tabs').next('.layui-tab-content')
            .children('.layui-tab-item')
            .removeClass('layui-show').eq(index).addClass('layui-show');
    });

    layui.use(['layer', 'form'], function () {
        var layer = layui.layer;
        var form = layui.form;
        form.render();

        $('#mchEditCancelBtn').on('click', function () {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        $('#mchEditSubmitBtn').on('click', function () {
            var $btn = $(this);
            $btn.find('i').attr('class', 'fa fa-refresh admin-spin mr-5');
            $btn.prop('disabled', true);

            // 序列化 —— domain_verified 未勾选时补 0
            var data = $('#mchEditForm').serializeArray();
            var has = {};
            $.each(data, function (_, it) { has[it.name] = true; });
            if (!has.domain_verified) data.push({name: 'domain_verified', value: '0'});

            $.ajax({
                url: '/admin/merchant.php',
                type: 'POST',
                data: $.param(data),
                dataType: 'json',
                success: function (res) {
                    if (res.code === 200) {
                        if (res.data && res.data.csrf_token) {
                            try { parent.updateCsrf(res.data.csrf_token); } catch(e) {}
                        }
                        try { parent.window._mchPopupSaved = true; } catch(e) {}
                        var index = parent.layer.getFrameIndex(window.name);
                        parent.layer.msg(res.msg || '保存成功');
                        parent.layer.close(index);
                    } else {
                        layer.msg(res.msg || '保存失败');
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
