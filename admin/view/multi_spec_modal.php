<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$goodsId = (int)($_GET['goods_id'] ?? 0);
$csrfToken = Csrf::token();

// 获取现有维度
$dims = Database::query("SELECT * FROM " . Database::prefix() . "goods_spec_dim WHERE goods_id = ? ORDER BY sort ASC", [$goodsId]);

// 获取现有组合（带 spec 明细）
$combos = Database::query("
    SELECT c.*, s.price, s.cost_price, s.market_price, s.spec_no, s.tags, s.min_buy, s.max_buy, s.is_default, s.status AS spec_status
    FROM " . Database::prefix() . "goods_spec_combo c
    LEFT JOIN " . Database::prefix() . "goods_spec s ON c.spec_id = s.id
    WHERE c.goods_id = ?
    ORDER BY c.sort ASC
", [$goodsId]);

// 预取所有维度值，按 dim_id 分组
$allValues = Database::query("SELECT * FROM " . Database::prefix() . "goods_spec_value WHERE goods_id = ? ORDER BY sort ASC", [$goodsId]);
$valuesByDim = [];
foreach ($allValues as $v) {
    $valuesByDim[$v['dim_id']][] = $v;
}
?>
<style>
        .layui-card { margin-bottom: 12px; }
        .dim-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 12px;
        }
        .dim-header {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }
        .dim-header input { flex: 1; }
        .dim-header .sort-input { width: 70px !important; flex: none !important; }
        .dim-values { display: flex; flex-wrap: wrap; gap: 6px; }
        .val-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #f0f0f0;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 13px;
            border: 1px solid #e0e0e0;
            cursor: default;
        }
        .val-tag .val-del {
            cursor: pointer;
            color: #999;
            font-size: 12px;
            margin-left: 2px;
        }
        .val-tag .val-del:hover { color: #f56c6c; }
        .add-val-form { display: inline-flex; gap: 4px; align-items: center; margin-top: 8px; }
        .add-val-form input { width: 120px !important; height: 30px; }

        /* SKU 表格 */
        .sku-wrapper { overflow-x: auto; }
        .sku-table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 800px; }
        .sku-table th, .sku-table td {
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            text-align: center;
            white-space: nowrap;
        }
        .sku-table th { background: #f8fafc; font-weight: 600; }
        .sku-table input:not([type="radio"]):not([type="checkbox"]) {
            width: 80px;
            height: 28px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 0 6px;
            text-align: center;
        }
        .sku-table input.price { width: 70px; }
        .sku-table input.spec-no { width: 100px; text-align: left; }
        .sku-table input.tags { width: 100px; }
        .sku-table input.min-buy, .sku-table input.max-buy { width: 55px; }
        .sku-table .dim-cell {
            background: #f8fafc;
            font-weight: 500;
            color: #409eff;
            min-width: 80px;
        }
        .sku-table .default-col { width: 50px; }
        .sku-table .action-col { width: 40px; }
        .sku-table tbody tr:hover { background: #fafafa; }
        .combo-row-new { background: #fffbe6 !important; }
    </style>

<div class="popup-inner" style="padding: 15px; background: #f2f2f2;">

<!-- 维度管理卡片 -->
<div class="layui-card">
    <div class="layui-card-header" style="font-weight: 600;">
        <i class="fa fa-th-large"></i> 维度管理
    </div>
    <div class="layui-card-body">
        <div id="dimensionsContainer">
            <?php foreach ($dims as $dimIdx => $dim): ?>
            <div class="dim-card" data-dim-idx="<?= $dimIdx ?>">
                <div class="dim-header">
                    <input type="text" class="layui-input dim-name" value="<?= htmlspecialchars($dim['name']) ?>" placeholder="维度名称，如：颜色">
                    <input type="number" class="layui-input sort-input dim-sort" value="<?= $dim['sort'] ?>" placeholder="排序">
                    <button type="button" class="layui-btn layui-btn-sm layui-btn-normal add-dim-val-btn"><i class="fa fa-plus"></i> 添加值</button>
                    <button type="button" class="layui-btn layui-btn-sm layui-btn-danger remove-dim-btn"><i class="fa fa-trash"></i></button>
                </div>
                <div class="dim-values" data-has-values="1">
                    <?php
                    $dimValues = $valuesByDim[$dim['id']] ?? [];
                    foreach ($dimValues as $valIdx => $val):
                    ?>
                    <div class="val-tag" data-val-idx="<?= $valIdx ?>" data-val-id="<?= $val['id'] ?>">
                        <span class="val-name"><?= htmlspecialchars($val['name']) ?></span>
                        <i class="fa fa-times val-del" onclick="removeVal(this)"></i>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="layui-btn layui-btn-sm" id="addDimensionBtn"><i class="fa fa-plus"></i> 添加维度</button>
    </div>
</div>

<!-- SKU 组合管理卡片 -->
<div class="layui-card">
    <div class="layui-card-header" style="font-weight: 600;">
        <i class="fa fa-table"></i> SKU 组合管理
    </div>
    <div class="layui-card-body">
        <div style="margin-bottom: 10px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <button type="button" class="layui-btn layui-btn-normal layui-btn-sm" id="generateCombosBtn"><i class="fa fa-magic"></i> 生成全部组合</button>
            <button type="button" class="layui-btn layui-btn-sm" id="saveCombosBtn"><i class="fa fa-save"></i> 保存规格</button>
            <span id="comboCount" style="color:#999; font-size:12px; margin-left:10px;"></span>
        </div>

        <!-- 批量设置行 -->
        <div style="margin-bottom: 8px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; font-size: 12px; color: #666;">
            <span>批量设置：</span>
            <label>价格 <input type="number" step="0.01" id="batchPrice" class="layui-input" style="width:70px;height:26px;display:inline;"></label>
            <label>成本价 <input type="number" step="0.01" id="batchCost" class="layui-input" style="width:70px;height:26px;display:inline;"></label>
            <label>规格编号 <input type="text" id="batchSpecNo" class="layui-input" style="width:100px;height:26px;display:inline;"></label>
            <button type="button" class="layui-btn layui-btn-xs" id="applyBatchBtn"><i class="fa fa-check"></i> 应用到所有</button>
        </div>

        <div class="sku-wrapper">
            <table class="sku-table">
                <thead id="skuTableHead"></thead>
                <tbody id="skuTableBody"></tbody>
            </table>
        </div>
    </div>
</div>

</div><!-- /.popup-inner -->

<script>
// ================================================================
// 全局数据
// ================================================================
var goodsId = <?= (int)$goodsId ?>;
var csrfToken = '<?= $csrfToken ?>';

// 初始已有维度（PHP 渲染的维度数据，带真实数据库 ID）
var existingDims = <?php
    $phpDims = [];
    foreach ($dims as $dimIdx => $dim) {
        $phpDims[] = [
            'dimIdx' => $dimIdx,
            'dimId' => $dim['id'],
            'name' => $dim['name'],
            'sort' => $dim['sort'],
            'values' => $valuesByDim[$dim['id']] ?? [],
        ];
    }
    echo json_encode($phpDims);
?>;

// 初始已有组合
var existingCombos = <?php
    $phpCombos = [];
    foreach ($combos as $combo) {
        $tags = $combo['tags'] ? json_decode($combo['tags'], true) : [];
        $phpCombos[] = [
            'specId' => $combo['spec_id'],
            'comboText' => $combo['combo_text'],
            'valueIds' => json_decode($combo['value_ids'], true) ?: [],
            'price' => GoodsModel::moneyFromDb($combo['price']),
            'costPrice' => GoodsModel::moneyFromDb($combo['cost_price']),
            'marketPrice' => GoodsModel::moneyFromDb($combo['market_price']),
            'stock' => $combo['stock'],
            'specNo' => $combo['spec_no'],
            'tags' => is_array($tags) ? implode(',', $tags) : '',
            'minBuy' => $combo['min_buy'],
            'maxBuy' => $combo['max_buy'],
            'isDefault' => $combo['is_default'],
            'disabled' => ($combo['spec_status'] ?? 1) == 0 ? 1 : 0,
        ];
    }
    echo json_encode($phpCombos);
?>;

// 已渲染的行数（用于生成临时 combo 索引）
var renderedComboCount = 0;

// ================================================================
// 初始化：渲染已有组合到表格
// ================================================================
$(function() {
    // PJAX 防重复绑定：清掉本页历史 .admMultiSpec handler，避免事件成倍触发
    $(document).off('.admMultiSpec');

    layui.use('layer', function() { window.layer = layui.layer; });

    // 如果已有组合，加载到表格
    if (existingCombos.length > 0) {
        loadCombosIntoTable(existingCombos);
    }
});

// 从已有组合列表加载到表格
function loadCombosIntoTable(combos) {
    var $tbody = $('#skuTableBody');
    // 渲染表头（使用已有维度的名称）
    renderTableHead(existingDims);

    combos.forEach(function(combo, idx) {
        renderedComboCount = idx;
        var rowId = 'row-' + idx;
        var $tr = $('<tr></tr>').attr('data-row-id', rowId).attr('data-combo-idx', idx);

        // 维度值单元格（只读展示）
        combo.valueIds.forEach(function(vId) {
            var label = getValLabel(vId);
            $tr.append($('<td class="dim-cell"></td>').text(label));
        });

        // 价格列
        $tr.append($('<td></td>').append(
            $('<input type="number" step="0.01" class="combo-price" placeholder="价格">').val(combo.price || '')
        ));
        // 成本价
        $tr.append($('<td></td>').append(
            $('<input type="number" step="0.01" class="combo-cost-price" placeholder="成本">').val(combo.costPrice || '')
        ));
        // 市场价
        $tr.append($('<td></td>').append(
            $('<input type="number" step="0.01" class="combo-market-price" placeholder="划线价">').val(combo.marketPrice || '')
        ));
        // 规格编号
        $tr.append($('<td></td>').append(
            $('<input type="text" class="combo-spec-no" placeholder="编号">').val(combo.specNo || '')
        ));
        // 标签
        $tr.append($('<td></td>').append(
            $('<input type="text" class="combo-tags" placeholder="标签">').val(combo.tags || '')
        ));
        // 最小购买
        $tr.append($('<td></td>').append(
            $('<input type="number" class="combo-min-buy" placeholder="1">').val(combo.minBuy || '')
        ));
        // 最大购买
        $tr.append($('<td></td>').append(
            $('<input type="number" class="combo-max-buy" placeholder="0不限">').val(combo.maxBuy || '')
        ));
        // 默认选中
        $tr.append($('<td class="default-col"></td>').append(
            $('<input type="radio" name="combo-default">').attr('checked', combo.isDefault == 1)
        ));
        // 禁用
        $tr.append($('<td class="default-col"></td>').append(
            $('<input type="checkbox" class="combo-disabled">').prop('checked', combo.disabled == 1)
        ));
        // 删除
        $tr.append($('<td class="action-col"></td>').append(
            $('<button type="button" class="layui-btn layui-btn-danger layui-btn-xs"><i class="fa fa-trash"></i></button>')
                .click(function() { $(this).closest('tr').remove(); updateComboCount(); })
        ));

        $tbody.append($tr);
    });

    updateComboCount();
}

// ================================================================
// 渲染表头
// ================================================================
function renderTableHead(dims) {
    var $thead = $('#skuTableHead');
    $thead.empty();
    var $tr = $('<tr></tr>');
    dims.forEach(function(dim) {
        $tr.append($('<th></th>').text(dim.name));
    });
    $tr.append($('<th></th>').text('价格'));
    $tr.append($('<th></th>').text('成本价'));
    $tr.append($('<th></th>').text('划线价'));
    $tr.append($('<th></th>').text('规格编号'));
    $tr.append($('<th></th>').text('标签'));
    $tr.append($('<th></th>').text('最小购买'));
    $tr.append($('<th></th>').text('最大购买'));
    $tr.append($('<th></th>').text('默认'));
    $tr.append($('<th></th>').text('禁用'));
    $tr.append($('<th></th>').text('删除'));
    $thead.append($tr);
}

// ================================================================
// 辅助：获取维度值的显示标签
// ================================================================
function getValLabel(valueId) {
    // valueId 可能是字符串 "dimIdx|valIdx" 或 数字 ID
    for (var d = 0; d < existingDims.length; d++) {
        var dim = existingDims[d];
        for (var v = 0; v < dim.values.length; v++) {
            var val = dim.values[v];
            // 匹配 "dimIdx|valIdx" 或直接匹配数据库 ID
            if (valueId == (dim.dimIdx + '|' + v) || valueId == val.id || valueId == String(val.id)) {
                return val.name;
            }
        }
    }
    return valueId;
}

// ================================================================
// 添加维度
// ================================================================
$('#addDimensionBtn').click(function() {
    var idx = nextDimIdx();
    var html = '<div class="dim-card" data-dim-idx="' + idx + '">' +
        '<div class="dim-header">' +
        '<input type="text" class="layui-input dim-name" placeholder="维度名称，如：颜色">' +
        '<input type="number" class="layui-input sort-input dim-sort" value="0" placeholder="排序">' +
        '<button type="button" class="layui-btn layui-btn-sm layui-btn-normal add-dim-val-btn"><i class="fa fa-plus"></i> 添加值</button>' +
        '<button type="button" class="layui-btn layui-btn-sm layui-btn-danger remove-dim-btn"><i class="fa fa-trash"></i></button>' +
        '</div>' +
        '<div class="dim-values"></div>' +
        '</div>';
    $('#dimensionsContainer').append(html);
    clearTable(); // 清空表格，因为维度变化了
});

// 删除维度
$(document).on('click.admMultiSpec', '.remove-dim-btn', function() {
    $(this).closest('.dim-card').remove();
    clearTable();
});

// 添加维度值（按钮点击）
$(document).on('click.admMultiSpec', '.add-dim-val-btn', function() {
    var $card = $(this).closest('.dim-card');
    var $valuesDiv = $card.find('.dim-values');
    var $form = $card.find('.add-val-form');

    if ($form.length === 0) {
        var formHtml = '<div class="add-val-form" style="margin-top:8px;">' +
            '<input type="text" class="layui-input val-name-input" placeholder="输入值名称" style="width:120px;height:30px;">' +
            '<input type="number" class="layui-input val-sort-input" placeholder="排序" style="width:70px;height:30px;">' +
            '<button type="button" class="layui-btn layui-btn-xs add-val-confirm-btn"><i class="fa fa-check"></i> 确认</button>' +
            '</div>';
        $(this).closest('.dim-header').after(formHtml);
    } else {
        $form.find('.val-name-input').val('').focus();
    }
});

// 确认添加维度值
$(document).on('click.admMultiSpec', '.add-val-confirm-btn', function() {
    var $form = $(this).closest('.add-val-form');
    var $valuesDiv = $(this).closest('.dim-card').find('.dim-values');
    var valName = $form.find('.val-name-input').val().trim();
    var valSort = $form.find('.val-sort-input').val() || 0;
    if (!valName) { layer.msg('请输入值名称'); return; }
    addValTag($valuesDiv, valName, valSort, null);
    $form.find('.val-name-input').val('').focus();
});

// 回车添加维度值
$(document).on('keypress.admMultiSpec', '.val-name-input', function(e) {
    if (e.which == 13) {
        e.preventDefault();
        $(this).closest('.add-val-form').find('.add-val-confirm-btn').click();
    }
});

// 添加值标签
function addValTag($container, name, sort, existingId) {
    var $valuesDiv = $container;
    // 查找当前维度卡片中已有值的数量，用于生成 tempId
    var $card = $container.closest('.dim-card');
    var dimIdx = parseInt($card.data('dim-idx')) || nextDimIdx() - 1;
    var existingCount = $valuesDiv.find('.val-tag').length;
    var tempId = (existingId !== null) ? (dimIdx + '|' + existingId) : ('new-' + dimIdx + '-' + existingCount);

    // 检查是否已存在同名值
    var exists = false;
    $valuesDiv.find('.val-tag .val-name').each(function() {
        if ($(this).text() === name) exists = true;
    });
    if (exists) { layer.msg('该值已存在'); return; }

    var $tag = $('<span class="val-tag" data-val-idx="' + tempId + '"' + (existingId ? ' data-val-id="' + existingId + '"' : '') + '></span>');
    $tag.append($('<span class="val-name"></span>').text(name));
    $tag.append($('<i class="fa fa-times val-del" onclick="removeVal(this)"></i>'));
    $valuesDiv.append($tag);
    clearTable(); // 清空表格
}

// 删除值
function removeVal(el) {
    $(el).closest('.val-tag').remove();
    clearTable();
}

// ================================================================
// 清空表格
// ================================================================
function clearTable() {
    $('#skuTableHead').empty();
    $('#skuTableBody').empty();
    renderedComboCount = 0;
    updateComboCount();
}

// ================================================================
// 批量应用
// ================================================================
$('#applyBatchBtn').click(function() {
    var batchPrice = $('#batchPrice').val();
    var batchCost = $('#batchCost').val();
    var batchSpecNo = $('#batchSpecNo').val();

    $('#skuTableBody tr').each(function() {
        if (batchPrice !== '') $(this).find('.combo-price').val(batchPrice);
        if (batchCost !== '') $(this).find('.combo-cost-price').val(batchCost);
        if (batchSpecNo !== '') $(this).find('.combo-spec-no').val(batchSpecNo);
    });
    layer.msg('已应用到所有行');
});

// ================================================================
// 生成全部组合（笛卡尔积）
// ================================================================
$('#generateCombosBtn').click(function() {
    var dims = collectDimsFromDOM();
    if (dims.length === 0) {
        layer.msg('请至少添加一个维度及其值');
        return;
    }

    var allDimsHaveValues = dims.every(function(d) { return d.values.length > 0; });
    if (!allDimsHaveValues) {
        layer.msg('每个维度至少需要一个值');
        return;
    }

    // 渲染表头
    renderTableHead(dims.map(function(d) { return { name: d.name }; }));

    // 笛卡尔积生成
    var combos = cartesianProduct(dims);

    // 清空旧表体
    var $tbody = $('#skuTableBody').empty();
    renderedComboCount = 0;

    combos.forEach(function(combo) {
        addComboRow($tbody, combo.values, combo.text, combo.valueIds);
    });

    updateComboCount();
    layer.msg('生成了 ' + combos.length + ' 个组合');
});

// 从 DOM 收集维度数据
function collectDimsFromDOM() {
    var dims = [];
    $('.dim-card').each(function(dimIdx) {
        var name = $(this).find('.dim-name').val().trim();
        if (!name) return;
        var sort = $(this).find('.dim-sort').val() || 0;
        var values = [];
        $(this).find('.val-tag').each(function(valIdx) {
            var valName = $(this).find('.val-name').text().trim();
            if (!valName) return;
            var tempId = $(this).data('val-idx') || ('new-' + dimIdx + '-' + valIdx);
            var valId = $(this).data('val-id') || null;
            values.push({
                name: valName,
                tempId: tempId,
                valId: valId,
                idx: valIdx
            });
        });
        if (values.length > 0) {
            dims.push({ dimIdx: dimIdx, name: name, sort: sort, values: values });
        }
    });
    return dims;
}

// 笛卡尔积
function cartesianProduct(dims) {
    var result = [[]];
    for (var i = 0; i < dims.length; i++) {
        var newResult = [];
        for (var j = 0; j < result.length; j++) {
            for (var k = 0; k < dims[i].values.length; k++) {
                newResult.push(result[j].concat(dims[i].values[k]));
            }
        }
        result = newResult;
    }
    return result.map(function(combo) {
        var text = combo.map(function(v) { return v.name; }).join(' / ');
        // valueId 格式：优先用真实数据库 ID（valId），否则用临时 ID
        var valueIds = combo.map(function(v) {
            return v.valId || v.tempId;
        });
        return { text: text, valueIds: valueIds, values: combo };
    });
}

// 添加一行 SKU 行
function addComboRow($tbody, values, text, valueIds) {
    var idx = renderedComboCount++;
    var rowId = 'row-new-' + idx;
    var $tr = $('<tr></tr>').attr('data-row-id', rowId).attr('data-combo-idx', idx);

    // 维度值单元格
    values.forEach(function(v) {
        $tr.append($('<td class="dim-cell"></td>').text(v.name || v));
    });

    var $priceInput = $('<input type="number" step="0.01" class="combo-price" placeholder="价格">');
    var $costInput = $('<input type="number" step="0.01" class="combo-cost-price" placeholder="成本">');
    var $marketInput = $('<input type="number" step="0.01" class="combo-market-price" placeholder="划线价">');
    var $specNoInput = $('<input type="text" class="combo-spec-no" placeholder="编号">');
    var $tagsInput = $('<input type="text" class="combo-tags" placeholder="标签">');
    var $minBuyInput = $('<input type="number" class="combo-min-buy" placeholder="1">').val('1');
    var $maxBuyInput = $('<input type="number" class="combo-max-buy" placeholder="0不限">').val('0');

    $tr.append($('<td></td>').append($priceInput));
    $tr.append($('<td></td>').append($costInput));
    $tr.append($('<td></td>').append($marketInput));
    $tr.append($('<td></td>').append($specNoInput));
    $tr.append($('<td></td>').append($tagsInput));
    $tr.append($('<td></td>').append($minBuyInput));
    $tr.append($('<td></td>').append($maxBuyInput));
    $tr.append($('<td class="default-col"></td>').append(
        $('<input type="radio" name="combo-default">').attr('checked', idx === 0)
    ));
    $tr.append($('<td class="default-col"></td>').append(
        $('<input type="checkbox" class="combo-disabled">')
    ));
    $tr.append($('<td class="action-col"></td>').append(
        $('<button type="button" class="layui-btn layui-btn-danger layui-btn-xs"><i class="fa fa-trash"></i></button>')
            .click(function() { $(this).closest('tr').remove(); updateComboCount(); })
    ));

    // 隐藏 valueIds
    $tr.data('valueIds', valueIds);

    $tbody.append($tr);
    return $tr;
}

// 更新组合数量显示
function updateComboCount() {
    var count = $('#skuTableBody tr').length;
    $('#comboCount').text(count > 0 ? '共 ' + count + ' 个 SKU' : '');
}

// ================================================================
// 保存组合
// ================================================================
$('#saveCombosBtn').click(function() {
    var $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> 保存中...');

    // 收集维度
    var dims = collectDimsFromDOM();

    // 收集组合
    var combos = [];
    var defaultIdx = 0;
    $('#skuTableBody tr').each(function(idx) {
        var $row = $(this);
        if ($row.find('input[name="combo-default"]').is(':checked')) {
            defaultIdx = idx;
        }
        var valueIds = $row.data('valueIds') || [];

        combos.push({
            text: $row.find('.dim-cell').map(function() { return $(this).text(); }).get().join(' / '),
            valueIds: valueIds,
            price: $row.find('.combo-price').val() || '0',
            cost_price: $row.find('.combo-cost-price').val() || '',
            market_price: $row.find('.combo-market-price').val() || '',
            spec_no: $row.find('.combo-spec-no').val() || '',
            tags: $row.find('.combo-tags').val() || '',
            min_buy: $row.find('.combo-min-buy').val() || '',
            max_buy: $row.find('.combo-max-buy').val() || '',
            disabled: $row.find('.combo-disabled').is(':checked') ? 1 : 0,
        });
    });
    combos._default = defaultIdx;

    if (dims.length === 0) {
        layer.msg('请至少添加一个维度');
        $btn.prop('disabled', false).html('<i class="fa fa-save"></i> 保存规格');
        return;
    }

    $.ajax({
        url: '/admin/goods_edit.php?_action=save_multi_spec',
        type: 'POST',
        dataType: 'json',
        data: {
            csrf_token: csrfToken,
            goods_id: goodsId,
            dims: JSON.stringify(dims),
            combos: JSON.stringify(combos),
        },
        success: function(res) {
            if (res.code === 200) {
                csrfToken = res.data.csrf_token || csrfToken;
                layer.msg('保存成功');
                setTimeout(function() {
                    // 通知父窗口刷新规格数据
                    if (parent && parent.refreshSpecList) {
                        parent.refreshSpecList(goodsId);
                    }
                    parent.layer.closeAll();
                }, 800);
            } else {
                layer.msg(res.msg || '保存失败');
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> 保存规格');
            }
        },
        error: function() {
            layer.msg('网络异常，请重试');
            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> 保存规格');
        }
    });
});

// 生成下一个维度索引
function nextDimIdx() {
    var max = 0;
    $('.dim-card').each(function() {
        var idx = parseInt($(this).data('dim-idx')) || 0;
        if (idx >= max) max = idx + 1;
    });
    return max;
}
</script>
