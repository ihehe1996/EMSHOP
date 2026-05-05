<?php
defined('EM_ROOT') || exit('access denied!');

/**
 * 测试模板设置页。
 *
 * 使用 layui 选项卡分为"基础配置"和"轮播图配置"两个页签。
 * 轮播图以表格形式展示，添加/编辑通过弹窗完成。
 */
function template_setting_view() {
    $storage = TemplateStorage::getInstance((string) ($_GET['name'] ?? 'default'));

    // 商城轮播图
    $slidesMall = $storage->getValue('hero_slides_mall');
    if (is_string($slidesMall)) { $slidesMall = json_decode($slidesMall, true); }
    if (!is_array($slidesMall)) {
        // 兼容旧数据：从 hero_slides 迁移
        $slidesOld = $storage->getValue('hero_slides');
        if (is_string($slidesOld)) { $slidesOld = json_decode($slidesOld, true); }
        $slidesMall = is_array($slidesOld) ? $slidesOld : [];
    }
    // 博客轮播图
    $slidesBlog = $storage->getValue('hero_slides_blog');
    if (is_string($slidesBlog)) { $slidesBlog = json_decode($slidesBlog, true); }
    if (!is_array($slidesBlog)) { $slidesBlog = []; }

    $themeAccent = (string) $storage->getValue('theme_accent');
    if ($themeAccent === '') $themeAccent = '#4e6ef2';
?>

<div class="popup-inner">
<form class="layui-form" id="testTemplateForm" lay-filter="testTemplateForm">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">

    <div class="layui-tab layui-tab-brief setting-tab" lay-filter="settingTab">
        <ul class="layui-tab-title">
            <li class="layui-this"><i class="fa fa-paint-brush"></i> 基础配置</li>
            <li><i class="fa fa-picture-o"></i> 轮播图配置</li>
        </ul>
        <div class="layui-tab-content">

            <!-- ========== Tab 1: 基础配置 ========== -->
            <div class="layui-tab-item layui-show">
                <div class="popup-section">
                    <div class="layui-form-item" style="margin-top:6px;">
                        <label class="layui-form-label">主题色</label>
                        <div class="layui-input-inline" style="width:200px;">
                            <input type="text" class="layui-input" name="theme_accent" value="<?php echo htmlspecialchars($themeAccent, ENT_QUOTES, 'UTF-8'); ?>" placeholder="#4e6ef2">
                        </div>
                        <div class="layui-form-mid layui-word-aux">全局主题色，影响按钮、链接等元素。</div>
                    </div>
                </div>
            </div>

            <!-- ========== Tab 2: 轮播图配置 ========== -->
            <div class="layui-tab-item">
                <div class="popup-section">
                    <!-- 商城/博客 子选项卡 -->
                    <div class="slide-scene-tabs">
                        <span class="slide-scene-tab active" data-scene="mall"><i class="fa fa-shopping-bag"></i> 商城</span>
                        <span class="slide-scene-tab" data-scene="blog"><i class="fa fa-pencil-square-o"></i> 博客</span>
                    </div>
                    <!-- 商城轮播表格 -->
                    <div class="slide-scene-panel" data-scene="mall">
                        <div style="margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;">
                            <span style="font-size:13px;color:#999;">拖拽行可调整顺序，配置保存后生效。</span>
                            <button type="button" class="layui-btn layui-btn-sm addSlideBtn" data-scene="mall"><i class="fa fa-plus"></i> 添加幻灯片</button>
                        </div>
                        <table class="layui-table" lay-size="sm">
                            <colgroup><col width="50"><col width="80"><col><col><col width="120"></colgroup>
                            <thead><tr><th style="text-align:center;">序号</th><th style="text-align:center;">预览</th><th>标题</th><th>副标题</th><th style="text-align:center;">操作</th></tr></thead>
                            <tbody id="slideTableBody_mall"></tbody>
                        </table>
                    </div>
                    <!-- 博客轮播表格 -->
                    <div class="slide-scene-panel" data-scene="blog" style="display:none;">
                        <div style="margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;">
                            <span style="font-size:13px;color:#999;">拖拽行可调整顺序，配置保存后生效。</span>
                            <button type="button" class="layui-btn layui-btn-sm addSlideBtn" data-scene="blog"><i class="fa fa-plus"></i> 添加幻灯片</button>
                        </div>
                        <table class="layui-table" lay-size="sm">
                            <colgroup><col width="50"><col width="80"><col><col><col width="120"></colgroup>
                            <thead><tr><th style="text-align:center;">序号</th><th style="text-align:center;">预览</th><th>标题</th><th>副标题</th><th style="text-align:center;">操作</th></tr></thead>
                            <tbody id="slideTableBody_blog"></tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</form>
</div>

<div class="popup-footer">
    <button type="button" class="popup-btn popup-btn--default" id="testTemplateCancelBtn">取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="testTemplateSubmitBtn"><i class="layui-icon layui-icon-ok"></i> 保存配置</button>
</div>


<style>
/* 选项卡样式（与商品编辑页一致） */
.setting-tab { margin: 0; }
.setting-tab > .layui-tab-title { padding: 0 10px; background: #fafafa; border-bottom: 1px solid #e6e6e6; }
.setting-tab > .layui-tab-title li { font-size: 13px; padding: 0 15px; }
.setting-tab > .layui-tab-title li .fa { margin-right: 3px; }
.setting-tab > .layui-tab-content > .layui-tab-item { padding: 0; }

/* 轮播图表格 */
#slideTable { margin-bottom: 0; }
#slideTable td { vertical-align: middle; }
.slide-thumb {
    width: 60px; height: 36px; object-fit: cover; border-radius: 4px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: block; margin: 0 auto;
}
.slide-thumb-empty {
    width: 60px; height: 36px; border-radius: 4px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex; align-items: center; justify-content: center;
    color: rgba(255,255,255,0.7); font-size: 11px; margin: 0 auto;
}
.slide-no-text { color: #ccc; font-size: 12px; }
/* 商城/博客 子选项卡 */
.slide-scene-tabs {
    display: flex; gap: 0; margin-bottom: 14px;
    border-bottom: 1px solid #e6e6e6;
}
.slide-scene-tab {
    padding: 8px 18px; font-size: 13px; color: #666;
    cursor: pointer; border-bottom: 2px solid transparent;
    transition: color .2s, border-color .2s;
}
.slide-scene-tab:hover { color: #333; }
.slide-scene-tab.active { color: #4e6ef2; border-bottom-color: #4e6ef2; font-weight: 500; }
.slide-scene-tab .fa { margin-right: 3px; }
</style>

<script>
(function(){
    layui.use(['layer', 'form', 'element'], function(){
        var $ = layui.$;
        var layer = layui.layer;
        var form = layui.form;
        var element = layui.element;
        var csrfToken = '<?php echo Csrf::token(); ?>';

        // 幻灯片数据（商城 / 博客 分开）
        var slidesData = {
            mall: <?php echo json_encode($slidesMall, JSON_UNESCAPED_UNICODE); ?>,
            blog: <?php echo json_encode($slidesBlog, JSON_UNESCAPED_UNICODE); ?>
        };
        var currentScene = 'mall';

        element.render('tab');
        form.render();

        // 选项卡点击兜底（与商品编辑页一致）
        $('.setting-tab').on('click', '.layui-tab-title>li', function(){
            var $li = $(this);
            var index = $li.index();
            var $tab = $li.closest('.layui-tab');
            $li.addClass('layui-this').siblings().removeClass('layui-this');
            $tab.children('.layui-tab-content').children('.layui-tab-item')
                .removeClass('layui-show').eq(index).addClass('layui-show');
        });

        // 商城/博客 子选项卡切换
        $('.slide-scene-tab').on('click', function(){
            var scene = $(this).data('scene');
            currentScene = scene;
            $(this).addClass('active').siblings().removeClass('active');
            $('.slide-scene-panel').hide();
            $('.slide-scene-panel[data-scene="' + scene + '"]').show();
        });

        /**
         * 渲染幻灯片表格
         * @param {string} scene  mall 或 blog
         */
        function renderSlideTable(scene) {
            var slides = slidesData[scene];
            var $tbody = $('#slideTableBody_' + scene);
            $tbody.empty();
            if (!slides.length) {
                $tbody.append('<tr><td colspan="5" style="text-align:center;color:#999;padding:24px 0;">暂无幻灯片，请点击"添加幻灯片"按钮</td></tr>');
                return;
            }
            for (var i = 0; i < slides.length; i++) {
                var s = slides[i];
                var thumbHtml = s.image
                    ? '<img class="slide-thumb" src="' + escHtml(s.image) + '" alt="">'
                    : '<div class="slide-thumb-empty">渐变</div>';
                var titleHtml = s.title ? escHtml(s.title) : '<span class="slide-no-text">未设置</span>';
                var subtitleHtml = s.subtitle ? escHtml(s.subtitle) : '<span class="slide-no-text">未设置</span>';

                var html = '<tr data-index="' + i + '">'
                    + '<td style="text-align:center;cursor:move;"><i class="fa fa-bars" style="color:#ccc;margin-right:4px;"></i>' + (i + 1) + '</td>'
                    + '<td style="text-align:center;">' + thumbHtml + '</td>'
                    + '<td>' + titleHtml + '</td>'
                    + '<td>' + subtitleHtml + '</td>'
                    + '<td style="text-align:center; width: 180px;">'
                    +   '<div class="layui-clear-space">'
                    +     '<a class="layui-btn layui-btn-normal layui-btn-xs slide-edit-btn" data-scene="' + scene + '" data-idx="' + i + '"><i class="fa fa-pencil"></i> 编辑</a>'
                    +     '<a class="layui-btn layui-btn-danger layui-btn-xs slide-del-btn" data-scene="' + scene + '" data-idx="' + i + '"><i class="fa fa-trash"></i> 删除</a>'
                    +   '</div>'
                    + '</td>'
                    + '</tr>';
                $tbody.append(html);
            }

            // 拖拽排序
            if (typeof Sortable !== 'undefined') {
                Sortable.create($tbody[0], {
                    animation: 150,
                    handle: 'td:first-child',
                    onEnd: function(evt) {
                        var item = slidesData[scene].splice(evt.oldIndex, 1)[0];
                        slidesData[scene].splice(evt.newIndex, 0, item);
                        renderSlideTable(scene);
                    }
                });
            }
        }

        function escHtml(str) {
            if (str == null) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        /**
         * 构建幻灯片表单 HTML
         */
        function buildSlideFormHtml(data) {
            return '<div style="padding:16px;">'
                + '<div class="popup-section">'
                + '<div class="layui-form-item">'
                +   '<label class="layui-form-label">背景图</label>'
                +   '<div class="layui-input-block"><div style="display:flex;gap:8px;">'
                +     '<input type="text" class="layui-input" id="slideFormImage" value="' + escHtml(data.image || '') + '" placeholder="图片URL（留空使用渐变背景）">'
                +     '<button type="button" class="layui-btn layui-btn-sm" id="slideFormPickBtn"><i class="fa fa-image"></i></button>'
                +   '</div></div>'
                + '</div>'
                + '<div class="layui-form-item">'
                +   '<label class="layui-form-label">标题</label>'
                +   '<div class="layui-input-block"><input type="text" class="layui-input" id="slideFormTitle" value="' + escHtml(data.title || '') + '" placeholder="幻灯片标题"></div>'
                + '</div>'
                + '<div class="layui-form-item">'
                +   '<label class="layui-form-label">副标题</label>'
                +   '<div class="layui-input-block"><input type="text" class="layui-input" id="slideFormSubtitle" value="' + escHtml(data.subtitle || '') + '" placeholder="幻灯片副标题"></div>'
                + '</div>'
                + '<div class="layui-form-item">'
                +   '<label class="layui-form-label">链接</label>'
                +   '<div class="layui-input-block"><input type="text" class="layui-input" id="slideFormLink" value="' + escHtml(data.link || '') + '" placeholder="点击跳转地址"></div>'
                + '</div>'
                + '<div class="layui-form-item">'
                +   '<label class="layui-form-label">按钮文字</label>'
                +   '<div class="layui-input-block"><input type="text" class="layui-input" id="slideFormBtnText" value="' + escHtml(data.btn_text || '') + '" placeholder="按钮文字（留空不显示按钮）"></div>'
                + '</div>'
                + '</div></div>';
        }

        /**
         * 打开幻灯片编辑弹窗
         * @param {string}      scene     mall 或 blog
         * @param {number|null} editIndex null=新增，数字=编辑对应索引
         */
        function openSlideForm(scene, editIndex) {
            var slides = slidesData[scene];
            var isEdit = (editIndex !== null && editIndex !== undefined);
            var data = isEdit ? slides[editIndex] : {};

            layer.open({
                type: 1,
                title: isEdit ? '编辑幻灯片' : '添加幻灯片',
                skin: 'admin-modal',
                area: ['500px'],
                shadeClose: false,
                content: buildSlideFormHtml(data),
                btn: ['确定', '取消'],
                yes: function(index, layero) {
                    var newData = {
                        image:    $.trim(layero.find('#slideFormImage').val()),
                        title:    $.trim(layero.find('#slideFormTitle').val()),
                        subtitle: $.trim(layero.find('#slideFormSubtitle').val()),
                        link:     $.trim(layero.find('#slideFormLink').val()),
                        btn_text: $.trim(layero.find('#slideFormBtnText').val())
                    };

                    if (!newData.title && !newData.image) {
                        layer.msg('请至少填写标题或背景图');
                        return;
                    }

                    if (isEdit) {
                        slidesData[scene][editIndex] = newData;
                    } else {
                        slidesData[scene].push(newData);
                    }
                    renderSlideTable(scene);
                    layer.close(index);
                },
                success: function(layero) {
                    layero.find('#slideFormPickBtn').on('click', function(){
                        var $input = layero.find('#slideFormImage');
                        layer.open({
                            type: 2,
                            title: '选择图片',
                            skin: 'admin-modal',
                            maxmin: true,
                            area: ['700px', '500px'],
                            content: '/admin/media.php?_csrf=' + encodeURIComponent(csrfToken),
                            btn: ['确定', '取消'],
                            yes: function(idx2, layero2) {
                                var win = layero2.find('iframe')[0].contentWindow;
                                var url = win.selectMedia();
                                if (!url) { layer.msg('请先选择一张图片'); return; }
                                layer.close(idx2);
                                $input.val(url);
                            }
                        });
                    });
                }
            });
        }

        // 添加幻灯片
        $(document).on('click', '.addSlideBtn', function(){
            openSlideForm($(this).data('scene'), null);
        });

        // 编辑幻灯片
        $(document).on('click', '.slide-edit-btn', function(){
            var scene = $(this).data('scene');
            var idx = parseInt($(this).data('idx'), 10);
            openSlideForm(scene, idx);
        });

        // 删除幻灯片
        $(document).on('click', '.slide-del-btn', function(){
            var scene = $(this).data('scene');
            var idx = parseInt($(this).data('idx'), 10);
            layer.confirm('确定要删除这条幻灯片吗？', { icon: 3, title: '删除确认', skin: 'admin-modal' }, function(confirmIdx) {
                slidesData[scene].splice(idx, 1);
                renderSlideTable(scene);
                layer.close(confirmIdx);
            });
        });

        // 初始渲染两个表格
        renderSlideTable('mall');
        renderSlideTable('blog');

        // 取消
        $('#testTemplateCancelBtn').on('click', function(){
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        // 保存
        $('#testTemplateSubmitBtn').on('click', function(){
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="layui-icon layui-icon-loading"></i> 保存中...');

            // 将幻灯片数据序列化为表单字段
            var formData = $('#testTemplateForm').serialize();
            // 追加商城/博客轮播图 JSON
            formData += '&slides_mall_json=' + encodeURIComponent(JSON.stringify(slidesData.mall));
            formData += '&slides_blog_json=' + encodeURIComponent(JSON.stringify(slidesData.blog));
            formData += '&_action=save_config&name=test';

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
                            csrfToken = res.data.csrf_token;
                        }
                        parent.layer.msg('配置已保存', {icon: 1});
                        parent.layer.close(parent.layer.getFrameIndex(window.name));
                    } else {
                        layer.msg(res.msg || '保存失败', {icon: 2});
                        $btn.prop('disabled', false).html('<i class="layui-icon layui-icon-ok"></i> 保存配置');
                    }
                },
                error: function(){
                    layer.msg('网络异常', {icon: 2});
                    $btn.prop('disabled', false).html('<i class="layui-icon layui-icon-ok"></i> 保存配置');
                }
            });
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

    // 保存轮播图（商城 + 博客，分别存储）
    foreach (['mall', 'blog'] as $scene) {
        $rawJson = $_POST["slides_{$scene}_json"] ?? '[]';
        $rawSlides = json_decode($rawJson, true);
        $slides = [];
        if (is_array($rawSlides)) {
            foreach ($rawSlides as $s) {
                $title = trim($s['title'] ?? '');
                $image = trim($s['image'] ?? '');
                if ($title === '' && $image === '') continue;
                $slides[] = [
                    'image'    => $image,
                    'title'    => $title,
                    'subtitle' => trim($s['subtitle'] ?? ''),
                    'link'     => trim($s['link'] ?? ''),
                    'btn_text' => trim($s['btn_text'] ?? ''),
                ];
            }
        }
        $storage->setValue("hero_slides_{$scene}", json_encode($slides, JSON_UNESCAPED_UNICODE));
    }

    // 其他设置
    $storage->setValue('theme_accent', (string) Input::postStrVar('theme_accent'));

    Output::ok('配置已保存', ['csrf_token' => Csrf::refresh()]);
}
