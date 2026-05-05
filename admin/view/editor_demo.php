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
.toolbar-container { border: 1px solid #e8e8e8; border-bottom: none; border-radius: 4px 4px 0 0; background: #fff; }
.editor-container { border: 1px solid #e8e8e8; border-radius: 0 0 4px 4px; background: #fff; min-height: 400px; }
#editorTextArea { height: 100%; }
.editor-container .w-e-text-container { min-height: 400px; }
.editor-toolbar { padding: 8px 10px; border-bottom: 1px solid #f0f0f0; display: flex; flex-wrap: wrap; gap: 4px; align-items: center; }
.editor-toolbar-btn { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border: none; background: transparent; border-radius: 3px; cursor: pointer; font-size: 14px; color: #555; transition: all .15s; }
.editor-toolbar-btn:hover { background: #f0f0f0; color: #333; }
.editor-toolbar-btn.active { background: #1890ff; color: #fff; }
.editor-toolbar-sep { width: 1px; height: 20px; background: #e8e8e8; margin: 0 4px; }
.editor-footer { margin-top: 16px; display: flex; align-items: center; gap: 12px; }
.editor-footer .layui-btn { min-width: 100px; }
.editor-tip { font-size: 12px; color: #999; margin-left: auto; }
#editorPreview { margin-top: 24px; border: 1px solid #e8e8e8; border-radius: 6px; overflow: hidden; }
#editorPreview .preview-header { background: #f8f9fa; padding: 10px 16px; border-bottom: 1px solid #e8e8e8; font-size: 13px; font-weight: 600; color: #555; }
#editorPreview .preview-body { padding: 16px; min-height: 100px; max-height: 400px; overflow-y: auto; font-size: 14px; line-height: 1.8; color: #333; }
#editorPreview .preview-body img { max-width: 100%; height: auto; border-radius: 4px; margin: 8px 0; }
</style>

<div id="editor—wrapper">
  <div id="toolbar-container"><!-- 工具栏 --></div>
  <div id="editor-container" style="height: 400px;"><!-- 编辑器 --></div>
</div>

<link rel="stylesheet" href="/content/static/lib/wangeditor/style.min.css">
<script src="/content/static/lib/wangeditor/index.min.js"></script>
<script>
(function () {
    'use strict';

    var csrfToken = <?php echo json_encode($csrfToken); ?>;
    const { createEditor, createToolbar } = window.wangEditor

  const editorConfig = {
    placeholder: 'EMSHOP富文本演示',
    onChange(editor) {
      const html = editor.getHtml()
      console.log('editor content', html)
      // 也可以同步到 <textarea>
    },
  }

  const editor = createEditor({
    selector: '#editor-container',
    html: '<p><br></p>',
    config: editorConfig,
    mode: 'default', // or 'simple'
  })

  const toolbarConfig = {}

  const toolbar = createToolbar({
    editor,
    selector: '#toolbar-container',
    config: toolbarConfig,
    mode: 'simple', // or 'simple'
  })
})();
</script>
