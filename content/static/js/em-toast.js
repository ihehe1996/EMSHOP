/**
 * EmToast —— 公共浮动提示 / 加载遮罩
 *
 * 样式来自安装向导页（install/index.php），抽出来供全站复用。
 * 配套样式见 content/static/css/em-toast.css（必须排在 layui.css 之后引入）。
 *
 * 提示（layer.msg 深色通知卡）：
 *   EmToast.ok('保存成功');            // 绿色对勾
 *   EmToast.err('安装失败：目录不可写'); // 红色叉
 *   EmToast.warn('该操作不可撤销');      // 琥珀感叹号
 *   EmToast.info('正在同步…');          // 灰色信息（默认）
 *   EmToast.msg(text, 'ok');           // 通用形式，kind 可传上面四种
 *
 * 加载遮罩：
 *   var t = EmToast.loading();                        // 默认 shade 0.14、色 #0f1729、转圈样式
 *   var t = EmToast.loading(0.3);                     // 只调遮罩浓度
 *   var t = EmToast.loading({ shade: 0.3, color: '#000', type: 2 });
 *   EmToast.close(t);
 *
 * 注意：loading() 返回的是本组件自管的 token，不是 layui 的 layer index，
 *       close() 要传同一个 token；layui 自己的 layer index 不要传进来。
 *
 * 依赖 layui 的 layer 组件，需在 layui.js 之后引入。
 * layer 未就绪时调用会自动排队，就绪后按序补发，业务侧不必再包一层 layui.use。
 */
(function (window) {
    'use strict';

    var KINDS = { ok: 1, err: 1, warn: 1, info: 1 };
    var DEFAULT_SHADE = 0.14;
    var DEFAULT_SHADE_COLOR = '#0f1729';

    var layerRef = null;
    var queue = [];
    var booting = false;
    var loadings = {};
    var loadingSeq = 0;

    /**
     * 拿到 layer 后执行 fn；layer 还没加载完就先排队。
     * 页面上没引 layui 时给出一次告警，不静默吞掉调用。
     */
    function withLayer(fn) {
        if (layerRef) {
            fn(layerRef);
            return;
        }
        if (!window.layui) {
            if (window.console && window.console.warn) {
                window.console.warn('[EmToast] 未找到 layui，提示被忽略，请先引入 layui.js');
            }
            return;
        }
        queue.push(fn);
        if (booting) return;
        booting = true;
        window.layui.use(['layer'], function () {
            layerRef = window.layui.layer;
            var pending = queue.slice();
            queue.length = 0;
            booting = false;
            for (var i = 0; i < pending.length; i++) {
                pending[i](layerRef);
            }
        });
    }

    var EmToast = {
        /** 通用提示，kind：ok / err / warn / info（不传或非法值按 info 处理） */
        msg: function (text, kind) {
            var skin = 'em-toast em-toast--' + (KINDS[kind] ? kind : 'info');
            withLayer(function (layer) {
                layer.msg(text, { skin: skin });
            });
        },

        ok: function (text) { EmToast.msg(text, 'ok'); },
        err: function (text) { EmToast.msg(text, 'err'); },
        warn: function (text) { EmToast.msg(text, 'warn'); },
        info: function (text) { EmToast.msg(text, 'info'); },

        /**
         * 打开加载遮罩，返回可用于 close() 的 token。
         * opt 可省略、传数字（只当 shade）或传 { shade, color, type }。
         */
        loading: function (opt) {
            var o = (typeof opt === 'number') ? { shade: opt } : (opt || {});
            var shade = (o.shade != null) ? o.shade : DEFAULT_SHADE;
            var color = o.color || DEFAULT_SHADE_COLOR;
            var type = (o.type != null) ? o.type : 1;

            var token = ++loadingSeq;
            loadings[token] = {};

            withLayer(function (layer) {
                // 排队期间就被 close 掉了，别再开出来
                if (!loadings[token]) return;
                loadings[token].index = layer.load(type, { shade: [shade, color], skin: 'em-loading' });
            });

            return token;
        },

        /** 关闭 loading() 返回的 token；重复关闭、关闭不存在的 token 都安全 */
        close: function (token) {
            var rec = loadings[token];
            if (!rec) return;
            delete loadings[token];
            // 还在排队没开出来：上面那个回调会看到 loadings[token] 已删而跳过
            if (rec.index == null) return;
            if (layerRef) layerRef.close(rec.index);
        }
    };

    window.EmToast = EmToast;
})(window);
