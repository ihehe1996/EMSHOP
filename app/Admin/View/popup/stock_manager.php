<?php
/**
 * 库存管理弹窗 — 纯分发器
 *
 * 不包含任何具体的库存管理表单。
 * 各商品类型插件通过 goods_type_{type}_stock_form 钩子提供自己的库存管理界面。
 * 插件需自行输出完整的 HTML 结构（popup-inner + popup-footer）。
 */
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$goodsType = $goods['goods_type'] ?? '';

// 触发类型专属库存管理钩子
ob_start();
doAction("goods_type_{$goodsType}_stock_form", $goods, $specs);
$pluginStockHtml = ob_get_clean();

if (!empty(trim($pluginStockHtml))) {
    echo $pluginStockHtml;
} else {
    // 无插件接管时的兜底提示
    ?>
    <div class="popup-inner">
        <div style="text-align:center;padding:60px 20px;color:#999;">
            <i class="fa fa-exclamation-circle" style="font-size:36px;margin-bottom:12px;display:block;"></i>
            当前商品类型插件未提供库存管理界面
        </div>
    </div>
    <?php
}
