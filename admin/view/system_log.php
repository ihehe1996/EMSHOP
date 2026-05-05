<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
?>
<style>
/* 和控制台 / 模板管理一致：去掉 .admin-page 白底，统计卡 / 筛选 / 表格浮在灰底画布 */
.admin-page-log { padding: 8px 4px 40px; background: unset; }
.admin-page-log .admin-stats-row { margin-bottom: 14px; }

/* 日志详情弹窗：左侧 label / 右侧值，比 layui-table 干净 */
.log-detail { padding: 16px 20px; min-width: 420px; max-width: 640px; }
.log-detail__row {
    display: flex;
    align-items: flex-start;
    padding: 10px 0;
    font-size: 13px;
    border-bottom: 1px dashed #f0f2f5;
    line-height: 1.6;
}
.log-detail__row:last-child { border-bottom: none; padding-bottom: 2px; }
.log-detail__label {
    flex: 0 0 90px;
    color: #6b7280;
    padding-top: 1px;
}
.log-detail__value {
    flex: 1;
    min-width: 0;
    color: #1f2937;
    word-break: break-all;
}
.log-detail__value pre {
    margin: 0;
    padding: 10px 12px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    white-space: pre-wrap;
    word-break: break-all;
    font-size: 12px;
    line-height: 1.6;
    max-height: 300px;
    overflow: auto;
}
</style>

<div class="admin-page admin-page-log">
    <h1 class="admin-page__title">系统日志</h1>

    <!-- 统计卡片（使用全局 .admin-stat-card） -->
    <div class="admin-stats-row">
        <div class="admin-stat-card">
            <div class="admin-stat-card__icon admin-stat-card__icon--blue"><i class="fa fa-file-text-o"></i></div>
            <div class="admin-stat-card__body">
                <div class="admin-stat-card__num" id="statTotal"><?= (int) $stats['total']; ?></div>
                <div class="admin-stat-card__label">日志总数</div>
            </div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-card__icon admin-stat-card__icon--green"><i class="fa fa-sign-in"></i></div>
            <div class="admin-stat-card__body">
                <div class="admin-stat-card__num" id="statLogin"><?= (int) $stats['login']; ?></div>
                <div class="admin-stat-card__label">登录日志</div>
            </div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-card__icon admin-stat-card__icon--purple"><i class="fa fa-key"></i></div>
            <div class="admin-stat-card__body">
                <div class="admin-stat-card__num" id="statOp"><?= (int) $stats['admin_operation']; ?></div>
                <div class="admin-stat-card__label">操作日志</div>
            </div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-card__icon admin-stat-card__icon--orange"><i class="fa fa-bug"></i></div>
            <div class="admin-stat-card__body">
                <div class="admin-stat-card__num" id="statSys"><?= (int) $stats['system']; ?></div>
                <div class="admin-stat-card__label">系统日志</div>
            </div>
        </div>
    </div>

    <!-- 搜索条件（em-filter 风格） -->
    <div class="em-filter" id="logFilter">
        <div class="em-filter__head" id="logFilterHead">
            <span class="em-filter__title"><i class="fa fa-filter"></i>搜索条件</span>
            <span class="em-filter__toggle"><i class="fa fa-angle-down"></i><span class="em-filter__toggle-text">展开</span></span>
        </div>
        <div class="em-filter__body">
            <div class="em-filter__grid">
                <div class="em-filter__field">
                    <label>日志级别</label>
                    <select id="logLevelFilter">
                        <option value="">全部级别</option>
                        <option value="info">普通</option>
                        <option value="warning">警告</option>
                        <option value="error">错误</option>
                    </select>
                </div>
                <div class="em-filter__field">
                    <label>日志类型</label>
                    <select id="logTypeFilter">
                        <option value="">全部类型</option>
                        <option value="login">登录</option>
                        <option value="logout">登出</option>
                        <option value="admin_operation">后台操作</option>
                        <option value="system">系统</option>
                        <option value="api">API 调用</option>
                    </select>
                </div>
                <div class="em-filter__field">
                    <label>关键词</label>
                    <input type="text" id="logKeyword" placeholder="操作名称 / 消息内容 / 用户名" autocomplete="off">
                </div>
            </div>
            <div class="em-filter__actions">
                <button type="button" class="em-btn em-reset-btn" id="logResetBtn"><i class="fa fa-undo mr-5"></i>重置</button>
                <button type="button" class="em-btn em-save-btn" id="logSearchBtn"><i class="fa fa-search mr-5"></i>搜索</button>
            </div>
        </div>
    </div>

    <table id="logTable" lay-filter="logTable"></table>
</div>

<!-- 头部工具栏模板 -->
<script type="text/html" id="logToolbarTpl">
    <div class="layui-btn-container">
        <a class="em-btn em-reset-btn" id="logRefreshBtn"><i class="fa fa-refresh"></i>刷新</a>
        <a class="em-btn em-purple-btn" id="logCleanupBtn"><i class="fa fa-trash-o"></i>清理旧日志</a>
        <a class="em-btn em-red-btn em-disabled-btn" lay-event="batchDelete"><i class="fa fa-trash"></i>批量删除</a>
    </div>
</script>

<!-- 行内操作按钮模板 -->
<script type="text/html" id="logRowActionTpl">
    <div class="layui-clear-space">
        <a class="em-btn em-sm-btn em-save-btn" lay-event="view"><i class="fa fa-eye"></i>详情</a>
        <a class="em-btn em-sm-btn em-red-btn" lay-event="del"><i class="fa fa-trash"></i>删除</a>
    </div>
</script>

<!-- 日志级别：em-tag 胶囊 + 彩色圆点 -->
<script type="text/html" id="logLevelTpl">
    {{# if(d.level === 'error'){ }}
    <span class="em-tag em-tag--red"><span class="em-tag__dot"></span>错误</span>
    {{# } else if(d.level === 'warning'){ }}
    <span class="em-tag em-tag--amber"><span class="em-tag__dot"></span>警告</span>
    {{# } else { }}
    <span class="em-tag em-tag--blue"><span class="em-tag__dot"></span>普通</span>
    {{# } }}
</script>

<!-- 日志类型：不同颜色的 em-tag -->
<script type="text/html" id="logTypeTpl">
    {{# if(d.type === 'login'){ }}
    <span class="em-tag em-tag--on">登录</span>
    {{# } else if(d.type === 'logout'){ }}
    <span class="em-tag em-tag--muted">登出</span>
    {{# } else if(d.type === 'admin_operation'){ }}
    <span class="em-tag em-tag--purple">后台操作</span>
    {{# } else if(d.type === 'system'){ }}
    <span class="em-tag em-tag--amber">系统</span>
    {{# } else if(d.type === 'api'){ }}
    <span class="em-tag em-tag--blue">API</span>
    {{# } else { }}
    <span class="em-tag em-tag--muted">{{ d.type }}</span>
    {{# } }}
</script>

<!-- 用户模板 -->
<script type="text/html" id="logUserTpl">
    {{# if(d.user_id > 0){ }}
    <span style="color:#374151;">{{ d.username }}</span>
    <span style="color:#9ca3af;font-size:11px;font-family:Menlo,Consolas,monospace;">#{{ d.user_id }}</span>
    {{# } else { }}
    <span class="em-tag em-tag--muted">系统</span>
    {{# } }}
</script>

<!-- 时间模板：日期在上、时间浅色在下（和用户列表同风格） -->
<script type="text/html" id="logTimeTpl">
    {{# if(d.created_at){ }}
    {{# var dt = d.created_at.replace('T', ' ').substring(0, 19); var parts = dt.split(' '); }}
    <span style="display:inline-flex;flex-direction:column;align-items:center;line-height:1.3;">
        <span style="color:#374151;font-weight:500;font-size:12.5px;">{{parts[0]}}</span>
        <span style="color:#9ca3af;font-size:11.5px;font-family:Menlo,Consolas,monospace;">{{parts[1] || ''}}</span>
    </span>
    {{# } else { }}
    <span class="em-tag em-tag--muted">无</span>
    {{# } }}
</script>

<script>
$(function () {
    // PJAX 防重复绑定：清掉本页历史 .admSystemLog handler，避免事件成倍触发
    $(document).off('.admSystemLog');
    $(window).off('.admSystemLog');

    'use strict';

    var csrfToken = <?php echo json_encode($csrfToken); ?>;
    var tableIns;

    function updateCsrf(token) { if (token) csrfToken = token; }
    window.updateCsrf = updateCsrf;

    function escHtml(str) {
        if (str == null) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    layui.use(['layer', 'form', 'table'], function () {
        var layer = layui.layer;
        var form = layui.form;
        var table = layui.table;

        // em-filter 展开/收起（和其他列表页一致）
        var $filter = $('#logFilter');
        var filterOpenKey = 'system_log_filter_open';
        function setFilterOpen(open) {
            $filter.toggleClass('is-open', open);
            $('.em-filter__toggle-text', $filter).text(open ? '收起' : '展开');
            localStorage.setItem(filterOpenKey, open ? 'y' : 'n');
        }
        setFilterOpen(localStorage.getItem(filterOpenKey) === 'y');
        $('#logFilterHead').on('click', function () {
            setFilterOpen(!$filter.hasClass('is-open'));
        });

        // 渲染表格
        tableIns = table.render({
            elem: '#logTable',
            id: 'logTableId',
            url: '/admin/system_log.php',
            headers: {csrf: csrfToken},
            method: 'POST',
            where: {_action: 'list'},
            page: true,
            limit: 20,
            limits: [10, 20, 50, 100],
            toolbar: '#logToolbarTpl',
            defaultToolbar: [],
            lineStyle: 'height: 55px;',
            initSort: {field: 'id', type: 'desc'},
            cols: [[
                {type: 'checkbox'},
                {field: 'id', title: 'ID', width: 80, sort: true, align: 'center'},
                {field: 'level', title: '级别', width: 110, templet: '#logLevelTpl', align: 'center'},
                {field: 'type', title: '类型', width: 120, templet: '#logTypeTpl', align: 'center'},
                {field: 'action', title: '操作名称', minWidth: 160},
                {field: 'message', title: '消息内容', minWidth: 220, style: 'max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;'},
                {field: 'username', title: '操作用户', width: 140, templet: '#logUserTpl', align: 'center'},
                {field: 'ip', title: 'IP', width: 140, align: 'center'},
                {field: 'created_at', title: '时间', width: 150, templet: '#logTimeTpl', align: 'center', sort: true},
                {title: '操作', width: 210, toolbar: '#logRowActionTpl', align: 'center'}
            ]],
            parseData: function (res) {
                if (res.data && res.data.csrf_token) csrfToken = res.data.csrf_token;
                return {
                    'code': res.code === 200 ? 0 : res.code,
                    'msg': res.msg,
                    'data': res.data ? res.data.data : [],
                    'count': res.data ? res.data.total : 0
                };
            }
        });

        // 勾选联动：激活批量删除按钮
        table.on('checkbox(logTable)', function () {
            var checked = table.checkStatus('logTableId').data.length > 0;
            $('[lay-event="batchDelete"]').toggleClass('em-disabled-btn', !checked);
        });

        // 搜索
        function doSearch() {
            table.reload('logTableId', {
                page: {curr: 1},
                where: {
                    _action: 'list',
                    level: $('#logLevelFilter').val() || '',
                    type: $('#logTypeFilter').val() || '',
                    keyword: $('#logKeyword').val() || ''
                }
            });
        }
        $(document).on('click.admSystemLog', '#logSearchBtn', doSearch);

        // 重置
        $(document).on('click.admSystemLog', '#logResetBtn', function () {
            $('#logLevelFilter').val('');
            $('#logTypeFilter').val('');
            $('#logKeyword').val('');
            form.render('select');
            table.reload('logTableId', {
                page: {curr: 1},
                where: {_action: 'list', level: '', type: '', keyword: ''}
            });
        });

        // 回车搜索
        $('#logKeyword').on('keydown', function (e) {
            if (e.keyCode === 13) doSearch();
        });

        // 刷新
        $(document).on('click.admSystemLog', '#logRefreshBtn', function () {
            table.reload('logTableId');
        });

        // 清理旧日志
        $(document).on('click.admSystemLog', '#logCleanupBtn', function () {
            layer.prompt({
                title: '清理多少天之前的日志？',
                value: '30',
                formType: 0,
                skin: 'admin-modal'
            }, function (value, idx) {
                var days = parseInt(value, 10);
                if (isNaN(days) || days < 1) { layer.msg('请输入有效的天数'); return; }
                layer.close(idx);
                layer.confirm('确定要清理 <strong>' + days + '</strong> 天之前的所有日志吗？此操作不可恢复。', function (idx2) {
                    var loadIdx = layer.load(1);
                    $.ajax({
                        url: '/admin/system_log.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {csrf_token: csrfToken, _action: 'cleanup', days: days},
                        success: function (res) {
                            if (res.code === 200) {
                                csrfToken = res.data && res.data.csrf_token ? res.data.csrf_token : csrfToken;
                                layer.msg(res.msg || '清理成功');
                                table.reload('logTableId');
                                loadStats();
                            } else {
                                layer.msg(res.msg || '清理失败');
                            }
                        },
                        error: function () { layer.msg('网络异常'); },
                        complete: function () { layer.close(loadIdx); layer.close(idx2); }
                    });
                });
            });
        });

        // 加载统计数据
        function loadStats() {
            $.ajax({
                url: '/admin/system_log.php',
                type: 'POST',
                dataType: 'json',
                data: {_action: 'stats'},
                success: function (res) {
                    if (res.code === 200) {
                        csrfToken = res.data && res.data.csrf_token ? res.data.csrf_token : csrfToken;
                        $('#statTotal').text(res.data.total || 0);
                        var byType = res.data.by_type || {};
                        $('#statLogin').text(byType.login || 0);
                        $('#statOp').text(byType.admin_operation || 0);
                        $('#statSys').text(byType.system || 0);
                    }
                }
            });
        }

        // 行内事件（查看详情 / 删除）
        table.on('tool(logTable)', function (obj) {
            var data = obj.data;
            switch (obj.event) {
                case 'view':
                    viewDetail(data.id);
                    break;
                case 'del':
                    layer.confirm('确定要删除这条日志吗？', function (idx) {
                        $.ajax({
                            url: '/admin/system_log.php',
                            type: 'POST',
                            dataType: 'json',
                            data: {csrf_token: csrfToken, _action: 'delete', id: data.id},
                            success: function (res) {
                                if (res.code === 200) {
                                    csrfToken = res.data && res.data.csrf_token ? res.data.csrf_token : csrfToken;
                                    obj.del();
                                    layer.msg(res.msg || '删除成功');
                                    loadStats();
                                } else {
                                    layer.msg(res.msg || '删除失败');
                                }
                            },
                            error: function () { layer.msg('网络异常'); },
                            complete: function () { layer.close(idx); }
                        });
                    });
                    break;
            }
        });

        // JSON 简易语法高亮
        function syntaxHighlight(json) {
            if (typeof json !== 'string') json = JSON.stringify(json, null, 2);
            json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (m) {
                var cls = 'color:#2563eb;';
                if (/^"/.test(m)) {
                    if (/:$/.test(m)) {
                        cls = 'color:#7c3aed;font-weight:600;';
                        m = m.slice(0, -1) + '<span style="color:#d97706;">:</span>';
                    } else {
                        cls = 'color:#059669;';
                    }
                } else if (/true|false/.test(m)) { cls = 'color:#d97706;'; }
                else if (/null/.test(m))         { cls = 'color:#dc2626;'; }
                return '<span style="' + cls + '">' + m + '</span>';
            });
        }

        // 查看详情弹窗（全量动态拼接，规避原来的 html 拷贝 + 再替换）
        function viewDetail(id) {
            var loadIdx = layer.load(1);
            $.ajax({
                url: '/admin/system_log.php',
                type: 'POST',
                dataType: 'json',
                data: {csrf_token: csrfToken, _action: 'view', id: id},
                success: function (res) {
                    if (res.code !== 200) {
                        layer.msg(res.msg || '加载失败');
                        return;
                    }
                    csrfToken = res.data && res.data.csrf_token ? res.data.csrf_token : csrfToken;
                    var d = res.data.data;

                    // 级别 / 类型标签 html（和表格内模板保持一致）
                    var levelHtml = d.level === 'error'
                        ? '<span class="em-tag em-tag--red"><span class="em-tag__dot"></span>错误</span>'
                        : d.level === 'warning'
                            ? '<span class="em-tag em-tag--amber"><span class="em-tag__dot"></span>警告</span>'
                            : '<span class="em-tag em-tag--blue"><span class="em-tag__dot"></span>普通</span>';

                    var typeMap = {
                        'login': ['em-tag--on', '登录'],
                        'logout': ['em-tag--muted', '登出'],
                        'admin_operation': ['em-tag--purple', '后台操作'],
                        'system': ['em-tag--amber', '系统'],
                        'api': ['em-tag--blue', 'API']
                    };
                    var tm = typeMap[d.type] || ['em-tag--muted', d.type];
                    var typeHtml = '<span class="em-tag ' + tm[0] + '">' + escHtml(tm[1]) + '</span>';

                    // 详情数据：尝试 JSON pretty，否则纯文本
                    var detailParsed = d.detail_parsed;
                    if (!detailParsed && d.detail) {
                        try { detailParsed = JSON.parse(d.detail); } catch (e) { detailParsed = d.detail; }
                    }
                    var detailHtml;
                    if (detailParsed == null || detailParsed === '') {
                        detailHtml = '<span style="color:#9ca3af;">无</span>';
                    } else if (typeof detailParsed === 'string') {
                        detailHtml = '<pre>' + escHtml(detailParsed) + '</pre>';
                    } else {
                        detailHtml = '<pre>' + syntaxHighlight(JSON.stringify(detailParsed, null, 2)) + '</pre>';
                    }

                    var userText = d.user_id > 0 ? escHtml(d.username) + ' <span style="color:#9ca3af;">(#' + d.user_id + ')</span>' : '<span class="em-tag em-tag--muted">系统</span>';

                    var html = ''
                        + '<div class="log-detail">'
                        +   row('日志级别', levelHtml)
                        +   row('日志类型', typeHtml)
                        +   row('操作名称', escHtml(d.action || '-'))
                        +   row('消息内容', escHtml(d.message || '-'))
                        +   row('操作用户', userText)
                        +   row('IP 地址', '<code style="background:#f3f4f6;padding:2px 6px;border-radius:4px;font-size:12px;">' + escHtml(d.ip || '-') + '</code>')
                        +   row('User Agent', '<span style="font-size:11.5px;color:#6b7280;">' + escHtml(d.user_agent || '-') + '</span>')
                        +   row('发生时间', escHtml(d.created_at || '-'))
                        +   row('详情数据', detailHtml)
                        + '</div>';

                    layer.open({
                        type: 1,
                        title: '日志详情 — #' + d.id,
                        skin: 'admin-modal',
                        area: [window.innerWidth >= 700 ? '640px' : '95%', 'auto'],
                        maxHeight: window.innerHeight >= 700 ? 700 : Math.floor(window.innerHeight * 0.9),
                        shadeClose: true,
                        content: html
                    });
                },
                error: function () { layer.msg('网络异常'); },
                complete: function () { layer.close(loadIdx); }
            });
        }

        function row(label, valueHtml) {
            return '<div class="log-detail__row">'
                +    '<div class="log-detail__label">' + label + '</div>'
                +    '<div class="log-detail__value">' + valueHtml + '</div>'
                + '</div>';
        }

        // 头部工具栏（批量删除）
        table.on('toolbar(logTable)', function (obj) {
            if (obj.event === 'batchDelete') batchDelete();
        });

        function batchDelete() {
            if ($('[lay-event="batchDelete"]').hasClass('em-disabled-btn')) return;
            var checkStatus = table.checkStatus('logTableId');
            var data = checkStatus.data;
            if (data.length === 0) { layer.msg('请先选择要删除的日志'); return; }
            var ids = data.map(function (item) { return item.id; });
            layer.confirm('确定要删除选中的 <strong>' + ids.length + '</strong> 条日志吗？', function (idx) {
                var loadIdx = layer.load(1);
                $.ajax({
                    url: '/admin/system_log.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {csrf_token: csrfToken, _action: 'batchDelete', ids: ids.join(',')},
                    success: function (res) {
                        if (res.code === 200) {
                            csrfToken = res.data && res.data.csrf_token ? res.data.csrf_token : csrfToken;
                            layer.msg(res.msg || '删除成功');
                            table.reload('logTableId');
                            loadStats();
                        } else {
                            layer.msg(res.msg || '删除失败');
                        }
                    },
                    error: function () { layer.msg('网络异常'); },
                    complete: function () { layer.close(loadIdx); layer.close(idx); }
                });
            });
        }
    });
});
</script>
