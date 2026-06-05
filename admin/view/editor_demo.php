<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
?>
<style>
.editor-demo-wrap { padding: 20px 24px; max-width: 900px; }
.editor-demo-header { margin-bottom: 20px; }
.editor-demo-header h2 { font-size: 18px; font-weight: 600; color: #333; margin-bottom: 6px; }
.editor-demo-header p { color: #888; font-size: 13px; margin: 0; }
.editor-demo-info { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 14px 16px; margin-bottom: 16px; font-size: 13px; color: #555; line-height: 1.8; }
.editor-demo-info code { background: #e9ecef; padding: 1px 5px; border-radius: 3px; font-family: monospace; color: #c0392b; }
.editor-footer { margin-top: 16px; display: flex; align-items: center; gap: 12px; }
.editor-footer .layui-btn { min-width: 100px; }
.editor-tip { font-size: 12px; color: #999; margin-left: auto; }
#editorPreview { margin-top: 24px; border: 1px solid #e8e8e8; border-radius: 6px; overflow: hidden; }
#editorPreview .preview-header { background: #f8f9fa; padding: 10px 16px; border-bottom: 1px solid #e8e8e8; font-size: 13px; font-weight: 600; color: #555; }
#editorPreview .preview-body { padding: 16px; min-height: 100px; max-height: 400px; overflow-y: auto; font-size: 14px; line-height: 1.8; color: #333; }
#editorPreview .preview-body img { max-width: 100%; height: auto; border-radius: 4px; margin: 8px 0; }
</style>
 
<div class="editor-demo-wrap">
    <div class="editor-demo-header">
        <h2>TinyMCE 富文本演示</h2>
        <p>封装自 <code>/admin/static/js/em-tinymce.js</code>，与商品/博客/页面编辑同款配置。</p>
    </div>
    <div class="editor-demo-info">
        上传接口：<code>/admin/upload.php</code> · context：<code>default</code>
    </div>
    <textarea id="editor-demo-content" class="layui-textarea"><p>EMSHOP 富文本演示</p></textarea>
    <div class="editor-footer">
        <button type="button" class="layui-btn layui-btn-normal" id="editorDemoPreviewBtn">预览 HTML</button>
        <span class="editor-tip">打开浏览器控制台可查看同步日志</span>
    </div>
    <div id="editorPreview" style="display:none;">
        <div class="preview-header">HTML 预览</div>
        <div class="preview-body" id="editorPreviewBody"></div>
    </div>
</div>

<script>
(function () {
    'use strict';

    window.emTinymceInit({
        context: 'default',
        editors: [
            { selector: '#editor-demo-content', height: 400, placeholder: 'EMSHOP 富文本演示' }
        ]
    });

    $('#editorDemoPreviewBtn').on('click', function () {
        window.emTinymceSave();
        var html = $('#editor-demo-content').val() || '';
        console.log('editor content', html);
        $('#editorPreviewBody').html(html);
        $('#editorPreview').show();
    });
})();
</script>
