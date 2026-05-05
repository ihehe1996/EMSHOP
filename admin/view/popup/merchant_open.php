<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
$levels = $levels ?? [];
$esc = $esc ?? function (string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
};
include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <form class="layui-form" id="mchOpenForm" lay-filter="mchOpenForm">
        <input type="hidden" name="_action" value="open">
        <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
        <input type="hidden" name="user_id" id="mchOpenUserId" value="">
        <input type="hidden" name="parent_id" id="mchOpenParentId" value="0">

        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">商户主</label>
                <div class="layui-input-block">
                    <div style="position:relative;">
                        <input type="text" class="layui-input" id="mchOpenUserKw" maxlength="60"
                               placeholder="输入账号 / 昵称 / 邮箱搜索用户" autocomplete="off">
                        <div id="mchOpenUserResults" class="mch-open-results"></div>
                    </div>
                    <div id="mchOpenUserChosen" style="margin-top:6px;color:#16baaa;display:none;"></div>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">商户等级</label>
                <div class="layui-input-block">
                    <select name="level_id" lay-verify="required">
                        <option value="">请选择等级</option>
                        <?php foreach ($levels as $lv): ?>
                        <option value="<?= (int) $lv['id'] ?>"><?= $esc($lv['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">店铺名</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" name="name" id="mchOpenName" maxlength="100"
                           placeholder="商户店铺对外展示名称">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">上级商户</label>
                <div class="layui-input-block">
                    <div style="position:relative;">
                        <input type="text" class="layui-input" id="mchOpenParentKw" maxlength="60"
                               placeholder="可选：输入店铺名搜索上级商户" autocomplete="off">
                        <div id="mchOpenParentResults" class="mch-open-results"></div>
                    </div>
                    <div id="mchOpenParentChosen" style="margin-top:6px;color:#1890ff;display:none;"></div>
                </div>
                <div class="layui-form-mid layui-word-aux">留空=独立商户；仅一层返佣关系</div>
            </div>
        </div>
    </form>
</div>

<div class="popup-footer">
    <button type="button" class="popup-btn" id="mchOpenCancelBtn"><i class="fa fa-times"></i> 取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="mchOpenSubmitBtn"><i class="fa fa-check mr-5"></i>开通</button>
</div>

<style>
.mch-open-results{position:absolute;top:100%;left:0;right:0;z-index:9;background:#fff;border:1px solid #e5e5e5;border-radius:4px;max-height:220px;overflow:auto;display:none;box-shadow:0 4px 12px rgba(0,0,0,.08);}
.mch-open-results.active{display:block;}
.mch-open-item{padding:8px 12px;cursor:pointer;font-size:13px;line-height:1.4;}
.mch-open-item:hover{background:#f0f8ff;}
.mch-open-item .mch-open-item__sub{color:#999;font-size:12px;}
.mch-open-item.is-disabled{opacity:.5;cursor:not-allowed;}
</style>

<script>
$(function () {
    layui.use(['layer', 'form'], function () {
        var layer = layui.layer;
        var form = layui.form;
        form.render();

        var userSearchTimer = null;
        $('#mchOpenUserKw').on('input', function () {
            var kw = $.trim($(this).val());
            $('#mchOpenUserChosen').hide().text('');
            $('#mchOpenUserId').val('');
            clearTimeout(userSearchTimer);
            if (!kw) { $('#mchOpenUserResults').removeClass('active').html(''); return; }
            userSearchTimer = setTimeout(function () { searchUser(kw); }, 280);
        });

        function searchUser(kw) {
            $.ajax({
                url: '/admin/merchant.php',
                type: 'POST',
                dataType: 'json',
                data: {_action: 'search_user', keyword: kw},
                success: function (res) {
                    if (res.code !== 200) return;
                    var items = (res.data && res.data.data) || [];
                    var $list = $('#mchOpenUserResults');
                    if (!items.length) { $list.removeClass('active').html(''); return; }
                    var html = '';
                    items.forEach(function (u) {
                        var disabled = u.merchant_id > 0;
                        html += '<div class="mch-open-item' + (disabled ? ' is-disabled' : '') + '" '
                             + 'data-id="' + u.id + '" '
                             + 'data-name="' + (u.nickname || u.username) + '" '
                             + 'data-disabled="' + (disabled ? '1' : '0') + '">'
                             + '<div>' + (u.nickname || u.username) + (disabled ? ' <span class="layui-badge layui-bg-gray">已开店</span>' : '') + '</div>'
                             + '<div class="mch-open-item__sub">' + u.username + ' · ' + u.email + '</div>'
                             + '</div>';
                    });
                    $list.html(html).addClass('active');
                }
            });
        }

        $(document).on('click', '#mchOpenUserResults .mch-open-item', function () {
            if ($(this).data('disabled') == 1) return;
            var id = $(this).data('id');
            var name = $(this).data('name');
            $('#mchOpenUserId').val(id);
            $('#mchOpenUserKw').val(name);
            $('#mchOpenUserResults').removeClass('active').html('');
            $('#mchOpenUserChosen').text('✓ 已选：' + name + ' (ID ' + id + ')').show();
            if (!$('#mchOpenName').val()) $('#mchOpenName').val(name + '的店铺');
        });

        // 上级商户搜索（按 name）
        var parentTimer = null;
        $('#mchOpenParentKw').on('input', function () {
            var kw = $.trim($(this).val());
            $('#mchOpenParentChosen').hide().text('');
            $('#mchOpenParentId').val('0');
            clearTimeout(parentTimer);
            if (!kw) { $('#mchOpenParentResults').removeClass('active').html(''); return; }
            parentTimer = setTimeout(function () { searchParent(kw); }, 280);
        });

        function searchParent(kw) {
            $.ajax({
                url: '/admin/merchant.php',
                type: 'POST',
                dataType: 'json',
                data: {_action: 'list', keyword: kw, page: 1, limit: 10, csrf_token: $('input[name="csrf_token"]').val()},
                success: function (res) {
                    if (res.code !== 200) return;
                    var items = (res.data && res.data.data) || [];
                    var $list = $('#mchOpenParentResults');
                    if (!items.length) { $list.removeClass('active').html(''); return; }
                    var html = '';
                    items.forEach(function (m) {
                        html += '<div class="mch-open-item" data-id="' + m.id + '" data-name="' + m.name + '">'
                             + '<div>' + m.name + '</div>'
                             + '<div class="mch-open-item__sub">ID: ' + m.id + '</div>'
                             + '</div>';
                    });
                    $list.html(html).addClass('active');
                }
            });
        }

        $(document).on('click', '#mchOpenParentResults .mch-open-item', function () {
            var id = $(this).data('id');
            var name = $(this).data('name');
            $('#mchOpenParentId').val(id);
            $('#mchOpenParentKw').val(name);
            $('#mchOpenParentResults').removeClass('active').html('');
            $('#mchOpenParentChosen').text('✓ 上级：' + name).show();
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#mchOpenUserKw, #mchOpenUserResults').length) {
                $('#mchOpenUserResults').removeClass('active');
            }
            if (!$(e.target).closest('#mchOpenParentKw, #mchOpenParentResults').length) {
                $('#mchOpenParentResults').removeClass('active');
            }
        });

        $('#mchOpenCancelBtn').on('click', function () {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        $('#mchOpenSubmitBtn').on('click', function () {
            if (!$('#mchOpenUserId').val()) { layer.msg('请选择商户主'); return; }
            if (!$('select[name="level_id"]').val()) { layer.msg('请选择等级'); return; }
            if (!$.trim($('#mchOpenName').val())) { layer.msg('请填写店铺名'); return; }

            var $btn = $(this);
            $btn.find('i').attr('class', 'fa fa-refresh admin-spin mr-5');
            $btn.prop('disabled', true);

            $.ajax({
                url: '/admin/merchant.php',
                type: 'POST',
                data: $('#mchOpenForm').serialize(),
                dataType: 'json',
                success: function (res) {
                    if (res.code === 200) {
                        if (res.data && res.data.csrf_token) {
                            try { parent.updateCsrf(res.data.csrf_token); } catch(e) {}
                        }
                        try { parent.window._mchPopupSaved = true; } catch(e) {}
                        var index = parent.layer.getFrameIndex(window.name);
                        parent.layer.msg(res.msg || '开通成功');
                        parent.layer.close(index);
                    } else {
                        layer.msg(res.msg || '开通失败');
                    }
                },
                error: function () { layer.msg('网络错误，请重试'); },
                complete: function () {
                    $btn.find('i').attr('class', 'fa fa-check mr-5');
                    $btn.prop('disabled', false);
                }
            });
        });
    });
});
</script>
<?php
include __DIR__ . '/footer.php';
