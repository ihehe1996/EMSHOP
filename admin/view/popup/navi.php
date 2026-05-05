<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
$isEdit = isset($editItem) && $editItem !== null;
$isSystem = $isEdit && (int) $editItem['is_system'] === 1;

$esc = function (string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
};

$currentType = $isEdit ? ($editItem['type'] ?? 'custom') : 'custom';

include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <form class="layui-form" id="naviForm" lay-filter="naviForm">
        <input type="hidden" name="_action" value="<?= $isEdit ? 'update' : 'create' ?>">
        <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
        <input type="hidden" name="id" value="<?= $isEdit ? (int) $editItem['id'] : '' ?>">

        <!-- 选项卡（em-tabs，同商品分类 / 商户等级编辑弹窗） -->
        <div class="em-tabs" id="naviEditTabs" style="margin-bottom: 0;">
            <a class="em-tabs__item is-active"><i class="fa fa-info-circle"></i>基本信息</a>
            <a class="em-tabs__item"><i class="fa fa-link"></i>链接设置</a>
        </div>
        <div class="layui-tab-content navi-edit-content">

            <!-- ========== Tab 1: 基本信息 ========== -->
            <div class="layui-tab-item layui-show">
                <div class="popup-section">
                    <?php if (!$isSystem): ?>
                    <div class="layui-form-item">
                        <label class="layui-form-label">导航类型</label>
                        <div class="layui-input-block">
                            <?php
                            $types = [
                                'custom'    => '自定义导航',
                                'page'      => '自定义页面',
                                'goods_cat' => '商品分类导航',
                                'blog_cat'  => '博客分类导航',
                            ];
                            ?>
                            <select name="type" lay-filter="naviTypeSelect">
                                <?php foreach ($types as $val => $label): ?>
                                <option value="<?= $val ?>" <?= $currentType === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="layui-form-item">
                        <label class="layui-form-label">上级菜单</label>
                        <div class="layui-input-block">
                            <select name="parent_id">
                                <option value="0">顶级菜单</option>
                                <?php foreach ($topLevelItems as $nav): ?>
                                    <?php if ($isEdit && (int) $editItem['id'] === (int) $nav['id']) continue; ?>
                                    <option value="<?= (int) $nav['id'] ?>" <?= $isEdit && (int) $editItem['parent_id'] === (int) $nav['id'] ? 'selected' : '' ?>>
                                        <?= $esc($nav['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="layui-form-item">
                        <label class="layui-form-label">导航名称</label>
                        <div class="layui-input-block">
                            <input type="text" class="layui-input" name="name" maxlength="100" placeholder="请输入导航名称"
                                   value="<?= $isEdit ? $esc($editItem['name']) : '' ?>">
                        </div>
                    </div>

                    <!-- 商品分类选择（仅 goods_cat 类型显示） -->
                    <div class="layui-form-item" id="goodsCatRow" style="<?= $currentType === 'goods_cat' ? '' : 'display:none' ?>">
                        <label class="layui-form-label">商品分类</label>
                        <div class="layui-input-block">
                            <select name="type_ref_id_goods">
                                <option value="">请选择</option>
                                <?php foreach ($goodsCategories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>"
                                    <?= $isEdit && $currentType === 'goods_cat' && (int) ($editItem['type_ref_id'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>>
                                    <?= (int) $cat['parent_id'] > 0 ? '└ ' : '' ?><?= $esc($cat['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- 博客分类选择（仅 blog_cat 类型显示） -->
                    <div class="layui-form-item" id="blogCatRow" style="<?= $currentType === 'blog_cat' ? '' : 'display:none' ?>">
                        <label class="layui-form-label">博客分类</label>
                        <div class="layui-input-block">
                            <select name="type_ref_id_blog">
                                <option value="">请选择</option>
                                <?php foreach ($blogCategories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>"
                                    <?= $isEdit && $currentType === 'blog_cat' && (int) ($editItem['type_ref_id'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>>
                                    <?= (int) $cat['parent_id'] > 0 ? '└ ' : '' ?><?= $esc($cat['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- 自定义页面选择（仅 page 类型显示） -->
                    <div class="layui-form-item" id="pageRow" style="<?= $currentType === 'page' ? '' : 'display:none' ?>">
                        <label class="layui-form-label">选择页面</label>
                        <div class="layui-input-block">
                            <select name="type_ref_id_page">
                                <option value="">请选择（仅显示"已发布"页面）</option>
                                <?php foreach (($publishedPages ?? []) as $pg): ?>
                                <option value="<?= (int) $pg['id'] ?>"
                                    <?= $isEdit && $currentType === 'page' && (int) ($editItem['type_ref_id'] ?? 0) === (int) $pg['id'] ? 'selected' : '' ?>>
                                    <?= $esc($pg['title']) ?> （/p/<?= $esc($pg['slug']) ?>）
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="layui-form-mid layui-word-aux">没有可选页面？先去 <a href="/admin/page.php" target="_blank" style="color:#4f46e5;">页面管理</a> 创建并发布</div>
                    </div>

                    <div class="layui-form-item">
                        <label class="layui-form-label">排序</label>
                        <div class="layui-input-block admin-form-width-sm">
                            <input type="number" class="layui-input" name="sort" min="0" placeholder="100"
                                   value="<?= $isEdit ? (int) $editItem['sort'] : 100 ?>">
                        </div>
                        <div class="layui-form-mid layui-word-aux">数值越小越靠前</div>
                    </div>
                </div>
            </div>

            <!-- ========== Tab 2: 链接设置 ========== -->
            <div class="layui-tab-item">
                <div class="popup-section">
                    <?php if (!$isSystem): ?>
                    <!-- 链接地址（自定义类型时显示） -->
                    <div class="layui-form-item" id="linkRow" style="<?= $currentType === 'custom' ? '' : 'display:none' ?>">
                        <label class="layui-form-label">链接地址</label>
                        <div class="layui-input-block">
                            <input type="text" class="layui-input" name="link" maxlength="500" placeholder="如：/about、https://example.com"
                                   value="<?= $isEdit ? $esc($editItem['link']) : '' ?>">
                        </div>
                        <div class="layui-form-mid layui-word-aux">仅当导航类型为"自定义导航"时生效；选择分类 / 页面类型时会自动生成链接</div>
                    </div>
                    <?php endif; ?>

                    <div class="layui-form-item">
                        <label class="layui-form-label">打开方式</label>
                        <div class="layui-input-block">
                            <select name="target">
                                <option value="_self" <?= $isEdit && ($editItem['target'] ?? '') === '_self' ? 'selected' : '' ?>>当前窗口</option>
                                <option value="_blank" <?= $isEdit && ($editItem['target'] ?? '') === '_blank' ? 'selected' : '' ?>>新窗口</option>
                            </select>
                        </div>
                    </div>

                    <div class="layui-form-item">
                        <label class="layui-form-label">显示状态</label>
                        <div class="layui-input-block">
                            <input type="checkbox" name="status" lay-skin="switch" lay-text="启用|禁用" value="1"
                                <?= $isEdit ? ($editItem['status'] == 1 ? 'checked' : '') : 'checked' ?>>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

<div class="popup-footer">
    <button type="button" class="em-btn em-reset-btn" id="naviCancelBtn"><i class="fa fa-times"></i>取消</button>
    <button type="button" class="em-btn em-save-btn" id="naviSubmitBtn"><i class="fa fa-check"></i>确认保存</button>
</div>

<style>
.navi-edit-content > .layui-tab-item { padding: 0; }
</style>

<script>
$(function () {
    // em-tabs 点击切换：同步 .is-active 到 tab 项，同步 .layui-show 到对应面板
    $('#naviEditTabs').on('click', '.em-tabs__item', function () {
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

        // 导航类型切换：显示/隐藏对应的分类/页面选择和链接输入
        form.on('select(naviTypeSelect)', function (data) {
            var type = data.value;
            $('#goodsCatRow').toggle(type === 'goods_cat');
            $('#blogCatRow').toggle(type === 'blog_cat');
            $('#pageRow').toggle(type === 'page');
            $('#linkRow').toggle(type === 'custom');
        });

        // 取消
        $('#naviCancelBtn').on('click', function () {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        // 保存
        $('#naviSubmitBtn').on('click', function () {
            var $btn = $(this);
            $btn.find('i').attr('class', 'fa fa-refresh admin-spin');
            $btn.prop('disabled', true).addClass('is-loading');

            // 将分类选择的值写入 type_ref_id
            var formData = $('#naviForm').serializeArray();
            var type = '';
            formData.forEach(function(item) { if (item.name === 'type') type = item.value; });

            if (type === 'goods_cat') {
                formData.push({name: 'type_ref_id', value: $('select[name="type_ref_id_goods"]').val() || ''});
            } else if (type === 'blog_cat') {
                formData.push({name: 'type_ref_id', value: $('select[name="type_ref_id_blog"]').val() || ''});
            } else if (type === 'page') {
                formData.push({name: 'type_ref_id', value: $('select[name="type_ref_id_page"]').val() || ''});
            }

            $.ajax({
                url: '/admin/navi.php',
                type: 'POST',
                data: $.param(formData),
                dataType: 'json',
                success: function (res) {
                    if (res.code === 200) {
                        if (res.data && res.data.csrf_token) {
                            try { parent.updateCsrf(res.data.csrf_token); } catch(e) {}
                        }
                        try { parent.window._naviPopupSaved = true; } catch(e) {}
                        var index = parent.layer.getFrameIndex(window.name);
                        parent.layer.msg(res.msg || '保存成功');
                        parent.layer.close(index);
                    } else {
                        if (res.data && res.data.csrf_token) {
                            $('input[name="csrf_token"]').val(res.data.csrf_token);
                        }
                        layer.msg(res.msg || '操作失败');
                    }
                },
                error: function () { layer.msg('网络错误，请重试'); },
                complete: function () {
                    $btn.find('i').attr('class', 'fa fa-check');
                    $btn.prop('disabled', false).removeClass('is-loading');
                }
            });
        });
    });
});
</script>
<?php include __DIR__ . '/footer.php'; ?>
