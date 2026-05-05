<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
$isEdit = isset($article) && !empty($article);
$placeholderImg = EM_CONFIG['placeholder_img'] ?? '/content/static/img/placeholder.png';
$pageTitle = $isEdit ? '编辑文章' : '添加文章';
$extraHead = '<link rel="stylesheet" href="/content/static/lib/wangeditor/style.min.css">' . "\n";
$extraHead .= '<script src="/content/static/lib/wangeditor/index.min.js"></script>';
$esc = function (?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
};

include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <form class="layui-form" id="blogForm" lay-filter="blogForm">
        <input type="hidden" name="_action" value="save">
        <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
        <input type="hidden" name="id" value="<?= $isEdit ? $esc((string)$article['id']) : '' ?>">

        <!-- 选项卡 -->
        <div class="layui-tab layui-tab-brief blog-tab" lay-filter="blogTab">
            <ul class="layui-tab-title">
                <li class="layui-this"><i class="fa fa-cog"></i> 基础信息</li>
                <li><i class="fa fa-file-text-o"></i> 文章详情</li>
                <li><i class="fa fa-sliders"></i> 其他配置</li>
            </ul>
            <div class="layui-tab-content">

                <!-- ========== Tab 1: 基础信息 ========== -->
                <div class="layui-tab-item layui-show">
                    <div class="popup-section">
                        <div class="layui-form-item">
                            <label class="layui-form-label">文章标题</label>
                            <div class="layui-input-block">
                                <input type="text" name="title" lay-verify="required" placeholder="请输入文章标题" class="layui-input" value="<?= $isEdit ? $esc($article['title']) : '' ?>">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">文章分类</label>
                            <div class="layui-input-block">
                                <select name="category_id" lay-search>
                                    <option value="0">无分类</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= $isEdit && $article['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= str_repeat('—', $cat['parent_id'] ? 1 : 0) . $esc($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <!-- 标签 -->
                        <div class="layui-form-item">
                            <label class="layui-form-label">文章标签</label>
                            <div class="layui-input-block">
                                <div class="blog-tag-input-wrap" id="tagInputWrap">
                                    <div class="blog-tag-tokens" id="tagTokens">
                                        <?php
                                        $articleTags = [];
                                        if ($isEdit) {
                                            $articleTags = BlogTagModel::getTagsByBlogId((int) $article['id']);
                                            foreach ($articleTags as $t):
                                        ?>
                                        <span class="blog-tag-token" data-id="<?= (int) $t['id'] ?>">
                                            <?= $esc($t['name']) ?>
                                            <i class="fa fa-times blog-tag-remove"></i>
                                        </span>
                                        <?php endforeach; } ?>
                                        <input type="text" class="blog-tag-text-input" id="tagTextInput" placeholder="输入标签名，回车添加" autocomplete="off">
                                    </div>
                                    <div class="blog-tag-suggest" id="tagSuggest" style="display:none;"></div>
                                </div>
                                <input type="hidden" name="tags" id="tagHiddenInput" value="<?= $esc(implode(',', array_column($articleTags, 'name'))) ?>">
                                <div class="layui-form-mid layui-word-aux">输入标签名后按回车添加，支持多个标签</div>
                            </div>
                        </div>
                        <!-- 封面图 -->
                        <div class="layui-form-item">
                            <label class="layui-form-label">封面图</label>
                            <div class="layui-input-block">
                                <div class="admin-img-field" id="blogImgField" data-placeholder="<?= $esc($placeholderImg) ?>">
                                    <img src="<?= $isEdit && !empty($article['cover_image']) ? $esc($article['cover_image']) : $esc($placeholderImg) ?>"
                                         alt="" id="blogImgPreview">
                                    <input type="text" name="cover_image" class="layui-input admin-img-url" id="blogImgUrl"
                                           maxlength="500" placeholder="输入封面图URL或上传图片"
                                           value="<?= $isEdit ? $esc($article['cover_image'] ?? '') : '' ?>">
                                    <div class="admin-img-btns">
                                        <button type="button" class="layui-btn layui-btn-xs" id="blogImgUploadBtn" title="上传"><i class="fa fa-upload"></i></button>
                                        <button type="button" class="layui-btn layui-btn-xs layui-btn-normal" id="blogImgPickBtn" title="选择"><i class="fa fa-image"></i></button>
                                        <button type="button" class="layui-btn layui-btn-xs layui-btn-danger" id="blogImgClearBtn" title="清除"><i class="fa fa-times"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- 摘要 -->
                        <div class="layui-form-item">
                            <label class="layui-form-label">文章摘要</label>
                            <div class="layui-input-block">
                                <textarea name="excerpt" placeholder="文章摘要（可留空，将自动截取正文前200字）" class="layui-textarea" style="height:80px;"><?= $isEdit ? $esc($article['excerpt']) : '' ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========== Tab 2: 文章详情 ========== -->
                <div class="layui-tab-item">
                    <div class="popup-section">
                        <div class="layui-form-item">
                            <div class="layui-input-block" style="margin-left:0;">
                                <textarea name="content" id="editor-textarea" style="display:none;"><?= $isEdit ? $esc($article['content']) : '' ?></textarea>
                                <div id="editor-wrapper" style="border:1px solid #e6e6e6;border-radius:4px;overflow:hidden;">
                                    <div id="toolbar-container" style="border-bottom:1px solid #e6e6e6;"></div>
                                    <div id="editor-container" style="min-height:360px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========== Tab 3: 其他配置 ========== -->
                <div class="layui-tab-item">
                    <div class="popup-section">
                        <div class="layui-form-item">
                            <label class="layui-form-label">发布状态</label>
                            <div class="layui-input-block">
                                <input type="radio" name="status" value="1" title="发布" <?= (!$isEdit || $article['status'] == 1) ? 'checked' : '' ?>>
                                <input type="radio" name="status" value="0" title="草稿" <?= ($isEdit && $article['status'] == 0) ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">排序</label>
                            <div class="layui-input-block">
                                <input type="number" name="sort" placeholder="数值越小越靠前" class="layui-input" value="<?= $isEdit ? (int)$article['sort'] : 0 ?>">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">置顶</label>
                            <div class="layui-input-block">
                                <input type="checkbox" name="is_top" lay-skin="switch" lay-text="是|否" <?= $isEdit && $article['is_top'] == 1 ? 'checked' : '' ?>>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </form>
</div>

<div class="popup-footer">
    <button type="button" class="popup-btn" id="blogCancelBtn"><i class="fa fa-times"></i> 取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="blogSubmitBtn"><i class="fa fa-check mr-5"></i> 确认保存</button>
</div>

<style>
/* 选项卡样式 */
.blog-tab { margin: 0; }
.blog-tab > .layui-tab-title { padding: 0 10px; background: #fafafa; border-bottom: 1px solid #e6e6e6; }
.blog-tab > .layui-tab-title li { font-size: 13px; padding: 0 15px; }
.blog-tab > .layui-tab-title li .fa { margin-right: 3px; }
.blog-tab > .layui-tab-content > .layui-tab-item { padding: 0; }

/* 编辑器容器高度修复（解决只能点击第一行聚焦的问题） */
#editor-container { min-height: 200px; background: #fff; }
#editor-container [data-slate-editor] { min-height: 200px; }
#editor-container .w-e-text-container { min-height: 200px !important; }

/* 标签输入 */
.blog-tag-input-wrap { position: relative; }
.blog-tag-tokens {
    display: flex; flex-wrap: wrap; gap: 6px; align-items: center;
    padding: 4px 8px; min-height: 38px;
    border: 1px solid #e6e6e6; border-radius: 4px; background: #fff;
    cursor: text;
}
.blog-tag-tokens:focus-within { border-color: #4e6ef2; }
.blog-tag-token {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; font-size: 12px; color: #4e6ef2;
    background: #eef1fd; border-radius: 3px; white-space: nowrap;
}
.blog-tag-remove { cursor: pointer; font-size: 11px; opacity: .6; }
.blog-tag-remove:hover { opacity: 1; }
.blog-tag-text-input {
    flex: 1; min-width: 100px; border: none; outline: none;
    font-size: 13px; line-height: 28px; background: transparent;
}
.blog-tag-suggest {
    position: absolute; left: 0; right: 0; top: 100%; z-index: 10;
    background: #fff; border: 1px solid #e6e6e6; border-top: none;
    border-radius: 0 0 4px 4px; max-height: 180px; overflow-y: auto;
    box-shadow: 0 4px 12px rgba(0,0,0,.08);
}
.blog-tag-suggest-item {
    padding: 6px 12px; font-size: 13px; cursor: pointer;
}
.blog-tag-suggest-item:hover, .blog-tag-suggest-item.active { background: #f5f7fa; }
.blog-tag-suggest-item .tag-count { float: right; color: #c0c4cc; font-size: 12px; }
</style>

<script>
$(function() {
    'use strict';

    // 选项卡点击兜底
    $('.blog-tab').on('click', '.layui-tab-title>li', function() {
        var $li = $(this);
        var index = $li.index();
        var $tab = $li.closest('.layui-tab');
        $li.addClass('layui-this').siblings().removeClass('layui-this');
        $tab.children('.layui-tab-content').children('.layui-tab-item')
            .removeClass('layui-show').eq(index).addClass('layui-show');
    });

    layui.use(['layer', 'form', 'element'], function() {
        var layer = layui.layer;
        var form = layui.form;
        var element = layui.element;

        form.render();

        var csrfToken = <?= json_encode($csrfToken) ?>;
        var placeholderImg = <?= json_encode($placeholderImg) ?>;

        // ============================================================
        // 富文本编辑器初始化（WangEditor v5）
        // ============================================================
        (function() {
            var $editorTextarea = $('#editor-textarea');
            var initialContent = $editorTextarea.val() || '';

            var editorConfig = {
                placeholder: '请输入文章内容...',
                onChange: function(editor) {
                    $editorTextarea.val(editor.getHtml());
                },
                MENU_CONF: {
                    uploadImage: {
                        fieldName: 'file',
                        server: '/admin/upload.php',
                        data: {
                            csrf_token: csrfToken,
                            context: 'blog_image',
                        },
                        onSuccess: function(res) {
                            if (res && res.data && res.data.csrf_token) {
                                csrfToken = res.data.csrf_token;
                                $('input[name="csrf_token"]').val(csrfToken);
                            }
                        },
                    },
                },
            };

            try {
                var E = window.wangEditor;
                var editor = E.createEditor({
                    selector: '#editor-container',
                    html: initialContent || '<p><br></p>',
                    config: editorConfig,
                    mode: 'default',
                });

                E.createToolbar({
                    editor: editor,
                    selector: '#toolbar-container',
                    config: {},
                    mode: 'simple',
                });

                window._blogEditor = editor;

                // 点击编辑器空白区域聚焦
                $('#editor-container').on('click', function(e) {
                    if (e.target === this || $(e.target).hasClass('w-e-text-container') || $(e.target).hasClass('w-e-scroll')) {
                        editor.focus(true);
                    }
                });
            } catch (e) {
                console.error('富文本编辑器初始化失败:', e);
                $('#editor-wrapper').html('<div style="color:#f00;padding:10px;">富文本编辑器加载失败，请刷新页面重试</div>');
            }
        })();

        // 重新渲染选项卡
        element.render('tab');

        // ============================================================
        // 封面图
        // ============================================================

        // URL 输入后预览
        $('#blogImgUrl').on('change keyup', function() {
            var url = $.trim($(this).val());
            $('#blogImgPreview').attr('src', url || placeholderImg);
        });

        // 选择按钮：打开媒体库
        $('#blogImgPickBtn').on('click', function() {
            layer.open({
                type: 2,
                title: '选择图片',
                skin: 'admin-modal',
                maxmin: true,
                area: ['700px', '500px'],
                shadeClose: false,
                content: '/admin/media.php?_csrf=' + encodeURIComponent(csrfToken),
                btn: ['确定', '取消'],
                yes: function(index, layero) {
                    var win = layero.find('iframe')[0].contentWindow;
                    var url = win.selectMedia();
                    if (!url) {
                        layer.msg('请先选择一张图片');
                        return;
                    }
                    layer.close(index);
                    $('#blogImgUrl').val(url);
                    $('#blogImgPreview').attr('src', url);
                }
            });
        });

        // 上传按钮
        $('#blogImgUploadBtn').on('click', function() {
            var $fileInput = $('<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">');
            $fileInput.on('change', function() {
                var file = this.files[0];
                if (!file) return;
                var formData = new FormData();
                formData.append('file', file);
                formData.append('csrf_token', csrfToken);
                formData.append('context', 'blog_cover');
                $.ajax({
                    url: '/admin/upload.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(res) {
                        if (res.code === 200 && res.data && res.data.url) {
                            $('#blogImgUrl').val(res.data.url);
                            $('#blogImgPreview').attr('src', res.data.url);
                            if (res.data.csrf_token) {
                                csrfToken = res.data.csrf_token;
                                $('input[name="csrf_token"]').val(csrfToken);
                            }
                        } else {
                            layer.msg(res.msg || '上传失败');
                        }
                    },
                    error: function() { layer.msg('上传失败'); }
                });
            });
            $fileInput.click();
        });

        // 清除封面图
        $('#blogImgClearBtn').on('click', function() {
            $('#blogImgUrl').val('');
            $('#blogImgPreview').attr('src', placeholderImg);
        });

        // ============================================================
        // 标签输入
        // ============================================================
        (function() {
            var allTags = <?= json_encode(BlogTagModel::getAll(0), JSON_UNESCAPED_UNICODE) ?>;
            var $wrap = $('#tagInputWrap');
            var $tokens = $('#tagTokens');
            var $input = $('#tagTextInput');
            var $suggest = $('#tagSuggest');
            var $hidden = $('#tagHiddenInput');

            function syncHidden() {
                var names = [];
                $tokens.find('.blog-tag-token').each(function() {
                    names.push($(this).text().trim());
                });
                $hidden.val(names.join(','));
            }

            function hasTag(name) {
                name = name.toLowerCase();
                var found = false;
                $tokens.find('.blog-tag-token').each(function() {
                    if ($(this).text().trim().toLowerCase() === name) { found = true; return false; }
                });
                return found;
            }

            function addTag(name) {
                name = $.trim(name);
                if (!name || hasTag(name)) return;
                var $token = $('<span class="blog-tag-token">' + $('<span>').text(name).html() + ' <i class="fa fa-times blog-tag-remove"></i></span>');
                $input.before($token);
                $input.val('');
                $suggest.hide();
                syncHidden();
            }

            // 删除标签
            $tokens.on('click', '.blog-tag-remove', function() {
                $(this).closest('.blog-tag-token').remove();
                syncHidden();
            });

            // 点击容器聚焦输入框
            $wrap.on('click', function(e) {
                if (!$(e.target).closest('.blog-tag-suggest').length) $input.focus();
            });

            // 输入时显示建议
            $input.on('input', function() {
                var val = $.trim($(this).val()).toLowerCase();
                if (!val) { $suggest.hide(); return; }
                var html = '';
                var count = 0;
                for (var i = 0; i < allTags.length && count < 8; i++) {
                    if (allTags[i].name.toLowerCase().indexOf(val) !== -1 && !hasTag(allTags[i].name)) {
                        html += '<div class="blog-tag-suggest-item" data-name="' + $('<span>').text(allTags[i].name).html() + '">'
                            + $('<span>').text(allTags[i].name).html()
                            + '<span class="tag-count">' + (allTags[i].article_count || 0) + '篇</span></div>';
                        count++;
                    }
                }
                if (html) { $suggest.html(html).show(); } else { $suggest.hide(); }
            });

            // 回车添加
            $input.on('keydown', function(e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                    var val = $.trim($(this).val());
                    if (val) addTag(val);
                }
                // Backspace 删除最后一个
                if ((e.key === 'Backspace' || e.keyCode === 8) && !$(this).val()) {
                    $tokens.find('.blog-tag-token').last().remove();
                    syncHidden();
                }
            });

            // 点击建议项
            $suggest.on('click', '.blog-tag-suggest-item', function() {
                addTag($(this).data('name'));
            });

            // 失焦隐藏建议
            $input.on('blur', function() {
                setTimeout(function() { $suggest.hide(); }, 200);
            });
        })();

        // ============================================================
        // 保存
        // ============================================================
        $('#blogCancelBtn').on('click', function() {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        $('#blogSubmitBtn').on('click', function() {
            var $btn = $(this);
            $btn.find('i').attr('class', 'fa fa-refresh admin-spin mr-5');
            $btn.prop('disabled', true);

            var formData = $('#blogForm').serialize();

            $.ajax({
                url: '/admin/blog_edit.php?_action=save',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(res) {
                    if (res.code === 200) {
                        if (res.data && res.data.csrf_token) {
                            try { parent.updateCsrf(res.data.csrf_token); } catch(e) {}
                        }
                        try { parent.window._blogPopupSaved = true; } catch(e) {}
                        var index = parent.layer.getFrameIndex(window.name);
                        parent.layer.msg(res.msg || '保存成功');
                        parent.layer.close(index);
                    } else {
                        layer.msg(res.msg || '保存失败');
                    }
                },
                error: function() {
                    layer.msg('网络错误，请重试');
                },
                complete: function() {
                    $btn.find('i').attr('class', 'fa fa-check mr-5');
                    $btn.prop('disabled', false);
                }
            });
        });
    });
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
