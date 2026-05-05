<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
$isEdit = isset($editRow) && $editRow !== null;
$esc = function (string $s) use (&$esc): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
};

$typeOptions  = CouponModel::typeOptions();
$scopeOptions = CouponModel::scopeOptions();

$cur = [
    'code'              => $isEdit ? (string) $editRow['code'] : '',
    'name'              => $isEdit ? (string) $editRow['name'] : '',
    'title'             => $isEdit ? (string) $editRow['title'] : '',
    'description'       => $isEdit ? (string) $editRow['description'] : '',
    'type'              => $isEdit ? (string) $editRow['type'] : 'fixed_amount',
    'value'             => $isEdit ? $editRow['value'] : '0',
    'min_amount'        => $isEdit ? $editRow['min_amount'] : '0',
    'max_discount'      => $isEdit ? $editRow['max_discount'] : '0',
    'apply_scope'       => $isEdit ? (string) $editRow['apply_scope'] : 'all',
    'apply_ids'         => $isEdit ? ($editRow['apply_ids'] ?: []) : [],
    'start_at'          => $isEdit ? (string) ($editRow['start_at'] ?? '') : '',
    'end_at'            => $isEdit ? (string) ($editRow['end_at'] ?? '') : '',
    'total_usage_limit' => $isEdit ? (int) $editRow['total_usage_limit'] : -1,
    'is_enabled'        => $isEdit ? (int) $editRow['is_enabled'] : 1,
    'sort'              => $isEdit ? (int) $editRow['sort'] : 100,
];

include __DIR__ . '/header.php';
?>

<div class="popup-inner">
    <form class="layui-form" id="couponForm" lay-filter="couponForm">
        <input type="hidden" name="_action" value="<?= $isEdit ? 'update' : 'create' ?>">
        <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
        <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $editRow['id'] ?>">
        <?php endif; ?>

        <!-- 基本信息 -->
        <div class="popup-section">
            <?php if ($isEdit): ?>
            <!-- 编辑态：券码只读展示 -->
            <div class="layui-form-item">
                <label class="layui-form-label">券码</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" value="<?= $esc($cur['code']) ?>" readonly style="background:#f9f9f9;color:#888;">
                    <input type="hidden" name="code" value="<?= $esc($cur['code']) ?>">
                </div>
            </div>
            <?php else: ?>
            <!-- 新增态：前缀 + 生成数量 -->
            <div class="layui-form-item">
                <label class="layui-form-label">券码前缀</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" name="code_prefix" maxlength="16" value="EM" placeholder="如 EM / NEW" autocomplete="off">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">生成数量</label>
                <div class="layui-input-block">
                    <input type="number" class="layui-input" name="generate_count" min="1" max="1000" placeholder="留空则生成 1 张" autocomplete="off">
                </div>
                <div class="layui-form-mid layui-word-aux">保存后自动在前缀后拼接随机后缀生成多张券，所有券规则相同</div>
            </div>
            <?php endif; ?>

            <div class="layui-form-item">
                <label class="layui-form-label">名称</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" name="name" value="<?= $esc($cur['name']) ?>"
                           placeholder="后台识别名称" maxlength="100">
                </div>
            </div>

            <div class="layui-form-item">
                <label class="layui-form-label">标题</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" name="title" value="<?= $esc($cur['title']) ?>"
                           placeholder="用户看到的标题（如：新人专享券）" maxlength="100">
                </div>
            </div>

            <div class="layui-form-item">
                <label class="layui-form-label">使用说明</label>
                <div class="layui-input-block">
                    <textarea name="description" class="layui-textarea" rows="3" maxlength="500"
                              placeholder="展示给用户的使用说明"><?= $esc($cur['description']) ?></textarea>
                </div>
            </div>
        </div>

        <!-- 使用规则 -->
        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">类型</label>
                <div class="layui-input-block">
                    <select name="type" id="couponType" lay-filter="couponTypeFilter">
                        <?php foreach ($typeOptions as $k => $v): ?>
                        <option value="<?= $esc($k) ?>" <?= $cur['type'] === $k ? 'selected' : '' ?>><?= $esc($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="layui-form-item" id="fieldValue">
                <label class="layui-form-label">券值</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" name="value" id="couponValue" value="<?= $esc((string) $cur['value']) ?>" autocomplete="off">
                </div>
                <div class="layui-form-mid layui-word-aux" id="couponValueHint">满减券填写金额；折扣券填整数百分比（如 85 代表 8.5 折）；免邮券填抵扣金额</div>
            </div>

            <div class="layui-form-item" id="fieldMaxDiscount">
                <label class="layui-form-label">折扣封顶</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" name="max_discount" value="<?= $esc((string) $cur['max_discount']) ?>" placeholder="0 不限" autocomplete="off">
                </div>
                <div class="layui-form-mid layui-word-aux">仅折扣券生效，单笔最多减免金额</div>
            </div>

            <div class="layui-form-item">
                <label class="layui-form-label">使用门槛</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" name="min_amount" value="<?= $esc((string) $cur['min_amount']) ?>" placeholder="0 无门槛" autocomplete="off">
                </div>
                <div class="layui-form-mid layui-word-aux">订单满 X 元可用</div>
            </div>

            <div class="layui-form-item">
                <label class="layui-form-label">总使用次数</label>
                <div class="layui-input-block">
                    <input type="number" class="layui-input" name="total_usage_limit" value="<?= (int) $cur['total_usage_limit'] ?>" autocomplete="off">
                </div>
                <div class="layui-form-mid layui-word-aux">所有人累计可使用次数，-1 表示无限</div>
            </div>
        </div>

        <!-- 适用范围 -->
        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">适用于</label>
                <div class="layui-input-block">
                    <select name="apply_scope" id="couponScope" lay-filter="couponScopeFilter">
                        <?php foreach ($scopeOptions as $k => $v): ?>
                        <option value="<?= $esc($k) ?>" <?= $cur['apply_scope'] === $k ? 'selected' : '' ?>><?= $esc($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="layui-form-item" id="fieldApplyIds" style="display:none;">
                <label class="layui-form-label">目标 ID</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" id="applyIdsInput"
                           value="<?= $esc(implode(',', (array) $cur['apply_ids'])) ?>"
                           placeholder="逗号分隔多个 ID，如 1,2,3（商品类型填类型名如 virtual_card,physical）" autocomplete="off">
                </div>
                <div class="layui-form-mid layui-word-aux">当前版本先手填 ID；后续版本再做下拉选择</div>
            </div>
        </div>

        <!-- 有效期 -->
        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">开始时间</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" name="start_at" id="couponStartAt" value="<?= $esc($cur['start_at']) ?>" placeholder="不填则立即生效" autocomplete="off">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">结束时间</label>
                <div class="layui-input-block">
                    <input type="text" class="layui-input" name="end_at" id="couponEndAt" value="<?= $esc($cur['end_at']) ?>" placeholder="不填则永不过期" autocomplete="off">
                </div>
                <div class="layui-form-mid layui-word-aux">两项都留空表示永久有效</div>
            </div>
        </div>

        <!-- 其他 -->
        <div class="popup-section">
            <div class="layui-form-item">
                <label class="layui-form-label">排序</label>
                <div class="layui-input-block">
                    <input type="number" class="layui-input" name="sort" value="<?= (int) $cur['sort'] ?>" autocomplete="off">
                </div>
                <div class="layui-form-mid layui-word-aux">数值越小越靠前</div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">启用</label>
                <div class="layui-input-block">
                    <input type="checkbox" name="is_enabled" value="1" lay-skin="switch" lay-text="启用|禁用"
                           <?= $cur['is_enabled'] ? 'checked' : '' ?>>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- 底部按钮区（参考 user_level 弹窗结构：放在 form 外部、.popup-footer 容器内） -->
<div class="popup-footer">
    <button type="button" class="popup-btn" id="couponCancelBtn"><i class="fa fa-times"></i> 取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="couponSubmitBtn"><i class="fa fa-check mr-5"></i>确认保存</button>
</div>

<script>
$(function () {
    layui.use(['form', 'layer', 'laydate'], function () {
        var form = layui.form, layer = layui.layer, laydate = layui.laydate;
        form.render();

        laydate.render({ elem: '#couponStartAt', type: 'datetime' });
        laydate.render({ elem: '#couponEndAt',   type: 'datetime' });

        // 类型切换：控制"券值"字段提示 + 折扣封顶显隐
        function refreshTypeFields() {
            var t = $('#couponType').val();
            var $hint = $('#couponValueHint');
            var $max = $('#fieldMaxDiscount');
            if (t === 'fixed_amount') {
                $hint.text('填写金额（元），订单金额减去该金额');
                $max.hide();
            } else if (t === 'percent') {
                $hint.text('填写整数百分比：85 代表 8.5 折（即减 15%）');
                $max.show();
            } else if (t === 'free_shipping') {
                $hint.text('免邮券：本期可填写抵扣金额作为折扣（后续接入运费后自动处理）');
                $max.hide();
            }
        }
        form.on('select(couponTypeFilter)', refreshTypeFields);
        refreshTypeFields();

        // 适用范围切换：all 时隐藏 apply_ids
        function refreshScopeFields() {
            var s = $('#couponScope').val();
            $('#fieldApplyIds').toggle(s !== 'all');
        }
        form.on('select(couponScopeFilter)', refreshScopeFields);
        refreshScopeFields();

        // 取消
        $('#couponCancelBtn').on('click', function () {
            parent.layer.close(parent.layer.getFrameIndex(window.name));
        });

        // 保存
        $('#couponSubmitBtn').on('click', function () {
            var $btn = $(this);
            $btn.find('i').attr('class', 'fa fa-refresh admin-spin mr-5');
            $btn.prop('disabled', true);

            var data = $('#couponForm').serializeArray().reduce(function (acc, f) { acc[f.name] = f.value; return acc; }, {});
            if (!('is_enabled' in data)) data.is_enabled = '0';

            // apply_ids：逗号切割 → 数组（按 scope 决定字符串或数字）
            var idsStr = ($('#applyIdsInput').val() || '').trim();
            var ids = idsStr === '' ? [] : idsStr.split(/\s*,\s*/).filter(Boolean);
            var scope = data.apply_scope;
            ids.forEach(function (v, i) {
                data['apply_ids[' + i + ']'] = scope === 'goods_type' ? v : (parseInt(v, 10) || 0);
            });

            $.ajax({
                url: '/admin/coupon.php', type: 'POST', dataType: 'json', data: data,
                success: function (res) {
                    if (res.code === 200) {
                        try { parent.window._couponPopupSaved = true; } catch (e) {}
                        parent.layer.msg(res.msg || '保存成功');
                        parent.layer.close(parent.layer.getFrameIndex(window.name));
                    } else {
                        layer.msg(res.msg || '保存失败');
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

<?php include __DIR__ . '/footer.php'; ?>
