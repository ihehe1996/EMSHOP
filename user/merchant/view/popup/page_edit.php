<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

/** @var array<string, mixed>|null $pageRow */

$csrfToken = Csrf::token();
$isEdit = isset($pageRow) && !empty($pageRow);
$pageTitle = $isEdit ? '编辑页面' : '新建页面';
$extraHead = '<link rel="stylesheet" href="/content/static/lib/wangeditor/style.min.css">' . "\n";
$extraHead .= '<script src="/content/static/lib/wangeditor/index.min.js"></script>';
$esc = function (?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
};

include EM_ROOT . '/admin/view/popup/header.php';
?>

<div class="popup-inner">
    <form class="layui-form" id="mcPageForm" lay-filter="mcPageForm">
        <input type="hidden" name="_action" value="save">
        <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
        <input type="hidden" name="id" value="<?= $isEdit ? $esc((string) $pageRow['id']) : '' ?>">

        <div class="em-tabs" id="mcPageEditTabs" style="margin-bottom: 0;">
            <a class="em-tabs__item is-active"><i class="fa fa-cog"></i>基础信息</a>
            <a class="em-tabs__item"><i class="fa fa-file-text-o"></i>页面内容</a>
            <a class="em-tabs__item"><i class="fa fa-search"></i>SEO 配置</a>
            <a class="em-tabs__item"><i class="fa fa-sliders"></i>其他设置</a>
        </div>
        <div class="layui-tab-content page-edit-content">

            <div class="layui-tab-item layui-show">
                <div class="popup-section">
                    <div class="layui-form-item">
                        <label class="layui-form-label">页面标题</label>
                        <div class="layui-input-block">
                            <input type="text" name="title" maxlength="200" placeholder="如：关于我们"
                                   class="layui-input" value="<?= $isEdit ? $esc($pageRow['title']) : '' ?>">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">URL 别名</label>
                        <div class="layui-input-block">
                            <input type="text" name="slug" maxlength="100" placeholder="留空则根据标题自动生成"
                                   class="layui-input" value="<?= $isEdit ? $esc($pageRow['slug']) : '' ?>"
                                   pattern="[a-zA-Z0-9_\-]+">
                        </div>
                        <div class="layui-form-mid layui-word-aux">
                            访问地址：<code style="background:#f3f4f6;padding:1px 5px;border-radius:3px;">/p/<span id="slugPreview"><?= $isEdit ? $esc($pageRow['slug']) : 'your-slug' ?></span></code>
                            · 只允许字母 / 数字 / 中划线 / 下划线 · 仅在本店唯一（主站可同名）
                        </div>
                    </div>
                </div>
            </div>

            <div class="layui-tab-item">
                <div class="popup-section">
                    <div class="layui-form-item">
                        <div class="layui-input-block" style="margin-left:0;">
                            <textarea name="content" id="editor-textarea" style="display:none;"><?= $isEdit ? $esc($pageRow['content'] ?? '') : '' ?></textarea>
                            <div id="editor-wrapper" style="border:1px solid #e6e6e6;border-radius:4px;overflow:hidden;">
                                <div id="toolbar-container" style="border-bottom:1px solid #e6e6e6;"></div>
                                <div id="editor-container" style="min-height:360px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="layui-tab-item">
                <div class="popup-section">
                    <div class="layui-form-item">
                        <label class="layui-form-label">标题 title</label>
                        <div class="layui-input-block">
                            <input type="text" name="seo_title" maxlength="200" placeholder="留空则使用页面标题"
                                   class="layui-input" value="<?= $isEdit ? $esc($pageRow['seo_title'] ?? '') : '' ?>">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">关键词 keywords</label>
                        <div class="layui-input-block">
                            <input type="text" name="seo_keywords" maxlength="500" placeholder="多个关键词用英文逗号分隔"
                                   class="layui-input" value="<?= $isEdit ? $esc($pageRow['seo_keywords'] ?? '') : '' ?>">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">描述 description</label>
                        <div class="layui-input-block">
                            <textarea name="seo_description" maxlength="500" rows="3"
                                      placeholder="页面描述，搜索引擎抓取时显示"
                                      class="layui-textarea"><?= $isEdit ? $esc($pageRow['seo_description'] ?? '') : '' ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="layui-tab-item">
                <div class="popup-section">
                    <div class="layui-form-item">
                        <label class="layui-form-label">发布状态</label>
                        <div class="layui-input-block">
                            <input type="radio" name="status" value="1" title="发布" <?= (!$isEdit || (int) $pageRow['status'] === 1) ? 'checked' : '' ?>>
                            <input type="radio" name="status" value="0" title="草稿" <?= ($isEdit && (int) $pageRow['status'] === 0) ? 'checked' : '' ?>>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">自定义模板</label>
                        <div class="layui-input-block">
                            <input type="text" name="template_name" maxlength="50" placeholder="留空用通用 page.php；例：填 about 将用 page-about.php"
                                   class="layui-input" value="<?= $isEdit ? $esc($pageRow['template_name'] ?? '') : '' ?>"
                                   pattern="[a-z0-9_\-]*">
                        </div>
                        <div class="layui-form-mid layui-word-aux">
                            前台渲染优先级：<code>page-{slug}.php</code> → <code>page-{这里的值}.php</code> → <code>page.php</code>（主题下） → 内置兜底
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">排序</label>
                        <div class="layui-input-block">
                            <input type="number" name="sort" class="layui-input" placeholder="数值越小越靠前"
                                   value="<?= $isEdit ? (int) $pageRow['sort'] : 100 ?>">
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

<div class="popup-footer">
    <button type="button" class="em-btn em-reset-btn" id="mcPageCancelBtn"><i class="fa fa-times"></i>取消</button>
    <button type="button" class="em-btn em-save-btn" id="mcPageSubmitBtn"><i class="fa fa-check"></i>确认保存</button>
</div>

<style>
.page-edit-content > .layui-tab-item { padding: 0; }
#editor-container { min-height: 360px; background: #fff; }
#editor-container [data-slate-editor] { min-height: 360px; }
#editor-container .w-e-text-container { min-height: 360px !important; }
</style>

<script>
$(function () {
    'use strict';

    $('#mcPageEditTabs').on('click', '.em-tabs__item', function () {
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

        var csrfToken = <?= json_encode($csrfToken) ?>;

        $('input[name="slug"]').on('input', function () {
            var v = $.trim($(this).val());
            $('#slugPreview').text(v || 'your-slug');
        });

        // 富文本编辑器
        (function () {
            var $textarea = $('#editor-textarea');
            var initialContent = $textarea.val() || '';

            try {
                var E = window.wangEditor;
                var editor = E.createEditor({
                    selector: '#editor-container',
                    html: initialContent || '<p><br></p>',
                    config: {
                        placeholder: '输入页面内容…',
                        onChange: function (ed) { $textarea.val(ed.getHtml()); },
                        MENU_CONF: {
                            uploadImage: {
                                fieldName: 'file',
                                server: '/user/merchant/upload.php',
                                data: { csrf_token: csrfToken, context: 'page_image' },
                                onSuccess: function (file, res) {
                                    if (res && res.data && res.data.csrf_token) {
                                        csrfToken = res.data.csrf_token;
                                        $('input[name="csrf_token"]').val(csrfToken);
                                    }
                                }
                            }
                        }
                    },
                    mode: 'default'
                });
                E.createToolbar({
                    editor: editor,
                    selector: '#toolbar-container',
                    config: {},
                    mode: 'simple'
                });
                window._mcPageEditor = editor;

                $('#editor-container').on('click', function (e) {
                    if (e.target === this || $(e.target).hasClass('w-e-text-container') || $(e.target).hasClass('w-e-scroll')) {
                        editor.focus(true);
                    }
                });
            } catch (e) {
                console.error('富文本编辑器初始化失败:', e);
                $('#editor-wrapper').html('<div style="color:#f00;padding:10px;">富文本编辑器加载失败，请刷新页面重试</div>');
            }
        })();

        $('#mcPageCancelBtn').on('click', function () {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        $('#mcPageSubmitBtn').on('click', function () {
            var $btn = $(this);
            $btn.find('i').attr('class', 'fa fa-refresh admin-spin');
            $btn.prop('disabled', true).addClass('is-loading');

            $.ajax({
                url: '/user/merchant/page_edit.php',
                type: 'POST',
                data: $('#mcPageForm').serialize(),
                dataType: 'json',
                success: function (res) {
                    if (res.code === 200) {
                        try { parent.updateCsrf(res.data.csrf_token); } catch (e) {}
                        try { parent.window._mcPagePopupSaved = true; } catch (e) {}
                        var index = parent.layer.getFrameIndex(window.name);
                        parent.layer.msg(res.msg || '保存成功');
                        parent.layer.close(index);
                    } else {
                        if (res.data && res.data.csrf_token) {
                            $('input[name="csrf_token"]').val(res.data.csrf_token);
                        }
                        layer.msg(res.msg || '保存失败');
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
<?php include EM_ROOT . '/admin/view/popup/footer.php'; ?>
