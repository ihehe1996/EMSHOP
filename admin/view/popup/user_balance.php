<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
$esc = function (string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
};
include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <form class="layui-form" id="balanceForm" lay-filter="balanceForm">
        <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
        <input type="hidden" name="user_id" value="<?= (int) $balanceUser['id'] ?>">

        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">用户</label>
                <div class="layui-input-block">
                    <div class="layui-form-mid" style="padding-left:0;">
                        <strong><?= $esc($balanceUser['nickname'] ?: $balanceUser['username']) ?></strong>
                        <span style="color:#999; margin-left:8px;">ID: <?= (int) $balanceUser['id'] ?></span>
                    </div>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">当前余额</label>
                <div class="layui-input-block">
                    <div class="layui-form-mid" style="padding-left:0;">
                        <strong style="color:#fa5252; font-size:18px;"><?= $currentBalance ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">操作类型</label>
                <div class="layui-input-block">
                    <select name="type" lay-filter="balanceType">
                        <option value="">请选择</option>
                        <option value="increase">增加</option>
                        <option value="decrease">减少</option>
                    </select>
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">金额</label>
                <div class="layui-input-block">
                    <input type="number" name="amount" class="layui-input" placeholder="请输入操作金额" step="0.01" min="0.01" id="balanceAmount">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">备注</label>
                <div class="layui-input-block">
                    <input type="text" name="remark" class="layui-input" placeholder="留空默认为【客服操作】" id="balanceRemark">
                    <div style="margin-top:8px; display:flex; gap:6px; flex-wrap:wrap;">
                        <button type="button" class="layui-btn layui-btn-xs layui-btn-primary bal-quick-remark">客服赠送</button>
                        <button type="button" class="layui-btn layui-btn-xs layui-btn-primary bal-quick-remark">客服充值</button>
                        <button type="button" class="layui-btn layui-btn-xs layui-btn-primary bal-quick-remark">客服退款</button>
                        <button type="button" class="layui-btn layui-btn-xs layui-btn-primary bal-quick-remark">客服扣除</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="popup-footer">
    <button type="button" class="popup-btn" id="balanceCancelBtn"><i class="fa fa-times"></i> 取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="balanceSubmitBtn"><i class="fa fa-check mr-5"></i>确认操作</button>
</div>

<script>
$(function () {
    layui.use(['layer', 'form'], function () {
        var layer = layui.layer;
        var form = layui.form;
        form.render();

        // 快捷备注
        $('.bal-quick-remark').on('click', function () {
            $('#balanceRemark').val($(this).text());
        });

        // 取消
        $('#balanceCancelBtn').on('click', function () {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        // 提交
        $('#balanceSubmitBtn').on('click', function () {
            var $btn = $(this);
            var formData = $('#balanceForm').serialize();

            // 前端校验
            var type = $('select[name="type"]').val();
            var amount = $('#balanceAmount').val();
            if (!type) { layer.msg('请选择操作类型'); return; }
            if (!amount || parseFloat(amount) <= 0) { layer.msg('请输入有效金额'); return; }

            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-5"></i>处理中...');

            $.ajax({
                type: 'POST',
                url: '/admin/user_list.php',
                data: formData + '&_action=balance_adjust',
                dataType: 'json',
                success: function (res) {
                    if (res.code === 200) {
                        if (res.data && res.data.csrf_token) {
                            $('#balanceForm input[name=csrf_token]').val(res.data.csrf_token);
                        }
                        // 通知父窗口刷新列表
                        parent.window._userPopupSaved = true;
                        parent.layer.msg('操作成功');
                        var index = parent.layer.getFrameIndex(window.name);
                        parent.layer.close(index);
                    } else {
                        layer.msg(res.msg || '操作失败');
                        $btn.prop('disabled', false).html('<i class="fa fa-check mr-5"></i>确认操作');
                    }
                },
                error: function () {
                    layer.msg('网络异常');
                    $btn.prop('disabled', false).html('<i class="fa fa-check mr-5"></i>确认操作');
                }
            });
        });
    });
});
</script>
