/**
 * TinyMCE 8 后台富文本封装
 * 依赖：jQuery、tinymce.min.js、em-editor-upload.js（EM_EDITOR_IMAGE_MAX_BYTES）
 *
 * 用法示例：
 *   window.emTinymceInit({
 *       editors: [
 *           { selector: '#editor-1', height: 300, placeholder: '请输入简介...' },
 *           { selector: '#editor-2', height: 450, placeholder: '请输入详情...' }
 *       ],
 *       context: 'goods_image',
 *       onCsrf: function (token) { csrfToken = token; }
 *   });
 *   window.emTinymceBindTabResize({ tabs: '#goodsEditTabs', tabIndex: 2 });
 *   // 提交前
 *   window.emTinymceSave();
 */
(function ($) {
    'use strict';
 
    var DEFAULT_PLUGINS = [
        'accordion',       // 折叠/手风琴内容块
        'advlist',         // 高级列表（更多样式/编号）
        'anchor',          // 锚点
        'autolink',        // 自动识别链接
        // autoresize 与固定 height 冲突，启用后 height 会失效
        'autosave',        // 自动保存草稿（localStorage）
        'charmap',         // 特殊字符
        'code',            // 源码模式（查看/编辑 HTML）
        'codesample',      // 代码示例（高亮）
        'directionality',  // 文本方向（LTR/RTL）
        'emoticons',       // 表情
        'fullscreen',      // 全屏
        'help',            // 帮助
        'image',           // 图片
        'importcss',       // 导入页面 CSS（用于格式下拉等）
        'insertdatetime',  // 插入日期时间
        'link',            // 超链接
        'lists',           // 列表
        'media',           // 音视频/媒体
        'nonbreaking',     // 不间断空格等特殊空白
        'pagebreak',       // 分页符
        'preview',         // 预览
        'quickbars',       // 选中文本浮动工具条
        'save',            // 保存（触发保存命令，通常需你自定义）
        'searchreplace',   // 查找替换
        'table',           // 表格
        'visualblocks',    // 可视化块级元素（显示段落块边界）
        'visualchars',     // 可视化不可见字符（如 &nbsp;）
        'wordcount'        // 字数统计
    ];

    var DEFAULT_TOOLBAR = [
        'undo redo', // 撤销/重做
        'blocks fontsize', // 段落/标题（blocks） + 字号
        'bold italic underline strikethrough', // 加粗/斜体/下划线/删除线
        'forecolor backcolor', // 字体颜色/背景色
        'alignleft aligncenter alignright alignjustify', // 对齐
        'outdent indent', // 减少缩进/增加缩进
        'bullist numlist', // 无序/有序列表
        'removeformat', // 清除格式
        'link anchor', // 链接/锚点
        'image media', // 图片/媒体
        'table', // 表格
        'charmap emoticons', // 特殊字符/表情
        'insertdatetime', // 日期时间
        'codesample', // 代码示例
        'searchreplace', // 查找替换
        'visualblocks visualchars', // 显示块边界/不可见字符
        'preview fullscreen', // 预览/全屏
        'help', // 帮助
        'code' // 源码
    ];

    function getCsrfToken() {
        return $('input[name="csrf_token"]').val() || '';
    }

    function syncCsrf(token, onCsrf) {
        if (!token) {
            return;
        }
        $('input[name="csrf_token"]').val(token);
        if (typeof onCsrf === 'function') {
            onCsrf(token);
        }
    } 

    function toSelector(selectorOrId) {
        var sel = String(selectorOrId || '');
        if (!sel) {
            return '';
        }
        return sel.charAt(0) === '#' ? sel : ('#' + sel);
    }

    function removeEditor(selectorOrId) {
        if (!window.tinymce || !selectorOrId) {
            return;
        }
        var selector = toSelector(selectorOrId);
        try {
            tinymce.remove(selector);
        } catch (e) {
            var id = selector.replace(/^#/, '');
            var ed = tinymce.get(id);
            if (ed) {
                ed.remove();
            }
        }
    }

    /**
     * 销毁容器内（及已脱离 DOM 的孤儿）编辑器，PJAX 切换前调用
     */
    function destroyEditorsIn(container) {
        if (!window.tinymce) {
            return;
        }

        var $root = container ? $(container) : null;
        var editors = (tinymce.editors || []).slice();

        editors.forEach(function (ed) {
            if (!ed) {
                return;
            }
            var el = typeof ed.getElement === 'function' ? ed.getElement() : ed.targetElm;
            var orphaned = !el || !$.contains(document, el);
            var inRoot = $root && $root.length && el && $root[0].contains(el);

            if (!$root || !$root.length || inRoot || orphaned) {
                try {
                    ed.remove();
                } catch (e) {}
            }
        });

        if ($root && $root.length) {
            $root.find('textarea[id]').each(function () {
                removeEditor(this.id);
            });
        }
    }

    function createUploadHandler(opts) {
        var uploadUrl = opts.uploadUrl || window.TEMPLATE_UPLOAD_URL || '/admin/upload.php';
        var context = opts.context || 'default';
        var onCsrf = opts.onCsrf;
        var maxSize = opts.maxFileSize != null
            ? opts.maxFileSize
            : (window.EM_EDITOR_IMAGE_MAX_BYTES || (20 * 1024 * 1024));

        return function (blobInfo) {
            if (blobInfo.blob().size > maxSize) {
                return Promise.reject('图片大小不能超过 ' + Math.round(maxSize / 1024 / 1024) + 'MB');
            }

            var fd = new FormData();
            fd.append('file', blobInfo.blob(), blobInfo.filename());
            fd.append('csrf_token', getCsrfToken());
            fd.append('context', context);

            return fetch(uploadUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    if (json.code === 200 && json.data && json.data.url) {
                        syncCsrf(json.data.csrf_token, onCsrf);
                        return json.data.url;
                    }
                    return Promise.reject(json.msg || '上传失败');
                })
                .catch(function (err) {
                    return Promise.reject(typeof err === 'string' ? err : '网络异常，上传失败');
                });
        };
    }

    /**
     * 返回 TinyMCE 公共配置，可通过 opts 覆盖/扩展
     */
    window.emTinymceBaseConfig = function (opts) {
        opts = opts || {};

        var base = {
            license_key: 'gpl',
            language: 'zh_CN',
            plugins: (opts.plugins || DEFAULT_PLUGINS).join(' '),
            toolbar: opts.toolbar || DEFAULT_TOOLBAR.join(' | '),
            toolbar_mode: opts.toolbar_mode || 'wrap',
            quickbars_insert_toolbar: opts.quickbars_insert_toolbar != null ? opts.quickbars_insert_toolbar : false,
            quickbars_selection_toolbar: opts.quickbars_selection_toolbar || 'bold italic | quicklink h2 h3 blockquote',
            quickbars_image_toolbar: opts.quickbars_image_toolbar || 'alignleft aligncenter alignright',
            menubar: opts.menubar != null ? opts.menubar : false,
            images_upload_handler: createUploadHandler(opts)
        };

        if (opts.extra) {
            Object.assign(base, opts.extra);
        }
 
        return base;
    };

    /**
     * 初始化一个或多个编辑器
     * @param {Object} opts
     * @param {Array}  opts.editors  每项：{ selector, height, placeholder, ...tinymceOptions }
     * @param {string} opts.context   上传 context，如 goods_image / blog_image
     * @param {string} opts.uploadUrl 上传地址，默认 TEMPLATE_UPLOAD_URL 或 /admin/upload.php
     * @param {Function} opts.onCsrf  上传后刷新 token 的回调
     */ 
    window.emTinymceInit = function (opts) {
        opts = opts || {};
        var editors = opts.editors || [];
        var base = window.emTinymceBaseConfig(opts);
        var pjaxGen = window._emTinymcePjaxGen || 0;

        if (!window.tinymce) {
            return Promise.reject(new Error('TinyMCE 未加载'));
        }

        editors.forEach(function (editorOpts) {
            if (editorOpts.selector) {
                removeEditor(editorOpts.selector);
            }
        });

        var tasks = editors.map(function (editorOpts) {
            var config = Object.assign({}, base, editorOpts);
            return tinymce.init(config);
        });

        return Promise.all(tasks).then(function (result) {
            if (pjaxGen !== (window._emTinymcePjaxGen || 0)) {
                editors.forEach(function (editorOpts) {
                    if (editorOpts.selector) {
                        removeEditor(editorOpts.selector);
                    }
                });
                return Promise.reject(new Error('TinyMCE init cancelled: PJAX navigated away'));
            }
            return result;
        });
    };

    /** PJAX 切换前销毁 #adminContent 内所有编辑器 */
    window.emTinymceDestroyIn = destroyEditorsIn;

    /** 重算所有已初始化编辑器的尺寸（隐藏 Tab 切换后调用） */
    window.emTinymceResize = function () {
        (tinymce.editors || []).forEach(function (ed) {
            if (ed && ed.theme && ed.theme.resize) {
                ed.theme.resize();
            }
        });
    };

    /** 提交表单前同步编辑器内容到 textarea */
    window.emTinymceSave = function () {
        if (window.tinymce) {
            tinymce.triggerSave();
        }
    };

    /** 销毁指定编辑器（PJAX 重复加载页面时用） */
    window.emTinymceRemove = removeEditor; 

    /**
     * 绑定 Tab 切换后自动 resize
     * @param {Object} opts
     * @param {string} opts.tabs          Tab 容器选择器，如 '#goodsEditTabs'
     * @param {string} opts.itemSelector  Tab 项选择器，默认 '.em-tabs__item'
     * @param {number} opts.tabIndex      需要 resize 的 Tab 下标（0 起）
     * @param {number} opts.delay         延迟毫秒，默认 50
     */
    window.emTinymceBindTabResize = function (opts) {
        opts = opts || {};
        var tabs = opts.tabs;
        var tabIndex = opts.tabIndex;
        var itemSelector = opts.itemSelector || '.em-tabs__item';
        var delay = opts.delay != null ? opts.delay : 50;

        if (!tabs || tabIndex == null) {
            return;
        }

        $(tabs).on('click', itemSelector, function () {
            if ($(this).index() === tabIndex) {
                setTimeout(window.emTinymceResize, delay);
            }
        });
    };
})(jQuery);
