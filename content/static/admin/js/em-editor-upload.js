/**
 * WangEditor v5 图片上传配置
 * 本站接口为 { code:200, data:{ url, csrf_token } }，与编辑器默认 { errno:0, data:[{url}] } 不一致。
 *
 * 大小限制（改一处需三处对齐，取最小值生效）：
 *   1. 本文件 EM_EDITOR_IMAGE_MAX_BYTES（浏览器 / 编辑器，默认 20MB）
 *   2. include/service/UploadService.php 的 $maxSize（服务端）
 *   3. PHP：upload_max_filesize、post_max_size；Nginx：client_max_body_size
 */
(function ($) {
    'use strict';

    /** 富文本单图上传上限（字节），须 ≤ UploadService::$maxSize */
    window.EM_EDITOR_IMAGE_MAX_BYTES = 20 * 1024 * 1024;

    window.emEditorUploadImageConf = function (opts) {
        opts = opts || {};
        var server = opts.server || '/admin/upload.php';
        var baseData = opts.data || {};
        var maxFileSize = opts.maxFileSize != null ? opts.maxFileSize : window.EM_EDITOR_IMAGE_MAX_BYTES;
        var onCsrf = typeof opts.onCsrf === 'function' ? opts.onCsrf : function (token) {
            if (token) {
                $('input[name="csrf_token"]').val(token);
            }
        };

        function pickUrl(res) {
            if (!res) {
                return '';
            }
            if ((res.code === 200 || res.code === 0) && res.data && res.data.url) {
                return res.data.url;
            }
            if (res.errno === 0 && res.data) {
                var item = Array.isArray(res.data) ? res.data[0] : res.data;
                if (typeof item === 'string') {
                    return item;
                }
                if (item && item.url) {
                    return item.url;
                }
            }
            return '';
        }

        function syncCsrf(res) {
            if (res && res.data && res.data.csrf_token) {
                onCsrf(res.data.csrf_token);
            }
        }

        return {
            fieldName: 'file',
            server: server,
            maxFileSize: maxFileSize,
            data: baseData,
            customInsert: function (res, insertFn) {
                var url = pickUrl(res);
                if (!url) {
                    console.error('富文本图片上传失败', res);
                    return;
                }
                insertFn(url, '', '');
                syncCsrf(res);
            },
            onSuccess: function (file, res) {
                syncCsrf(res);
            },
        };
    };
})(jQuery);
