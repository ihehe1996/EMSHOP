<?php

declare(strict_types=1);

/**
 * 商品控制器。
 *
 * 方法说明：
 * - display()        商品列表页，同时也是商城首页入口 → goods_list.php
 * - displayContent() 商品详情页 → goods_content.php
 *
 * 动作方法只做「接收请求 + 调用页面」，**不取数**：要什么数据由模板自己在开头取
 * （goods_list.php / goods_content.php）。取数与渲染都归视图层，控制器保持薄。
 *
 * 为什么只有一个 display()：商城首页入口（`/post/`、`?c=goods_index`）和商品列表页
 * （`?c=goods_list`、`/goods_list`、分类/标签/排序/翻页）**是同一个页面**——同一个模板，
 * 所以只保留一个动作方法，两个入口都指到它。
 *
 * 两个动作名都不叫 `_index`/`_list`/`_detail`，要同步的地方比常规控制器多：
 *   - Dispatcher::DEFAULT_ACTIONS 的 'goods_list' 与 'goods_index' 两条（→ display）
 *   - Dispatcher::DETAIL_ACTIONS 的 'goods' 一条（→ displayContent，路由带 id 时用）
 *   - Dispatcher 里 7 处 pretty 路由把列表动作名写死为 'display'（/goods_list、分类/标签/翻页等）
 *   - Dispatcher 的 $staticMap['post'] 与 `/post/` 的 pathinfo 分支
 * 改方法名时漏改任一处，对应入口就会 404。
 */
class GoodsController extends BaseController
{
    /**
     * 商品列表页（同时是商城首页入口）。
     *
     * 请求参数的解析与页面标题在模板侧（module.php，因为标题必须早于 body 渲染），
     * 数据由 goods_list.php 自己取。
     */
    public function display(): void
    {
        $this->view->render('goods_list');
    }

    /**
     * 商品详情页。
     */
    public function displayContent(): void
    {
        $id = (int) $this->getArg("id", 0);

        // —— 「这一页能不能打开」的判断：重定向 / 404 必须在任何输出之前完成，所以留在控制器 ——
        // 主站前台只看 owner_id=0；商户前台只看本店自建或主站引用
        $row = $id > 0 ? GoodsModel::getById($id) : null;
        if ($row && !MerchantContext::isGoodsVisibleToCurrentScope($row)) {
            $row = null;
        }

        // 跳转链接：非空时直接 302 跳出（"类似广告"语义，详情页本身不展示）。
        // 加 _preview=1 query 可绕过，方便后台预览编辑效果。
        if ($row
            && (int) $row["status"] === 1
            && $row["deleted_at"] === null
            && (int) $row["is_on_sale"] === 1
            && !empty($row["jump_url"])
            && (int) $this->getArg("_preview", 0) !== 1
        ) {
            Response::redirect((string) $row["jump_url"]);
            return;
        }

        // 仅展示已上架、未删除的商品；开启「售罄禁止访问」且总库存为 0 时不可访问（-1 表示无限）
        $unavailableReason = "";
        $canView = $row
            && (int) $row["status"] === 1
            && $row["deleted_at"] === null
            && (int) $row["is_on_sale"] === 1;
        if ($canView
            && (string) Config::get("shop_block_sold_out_access", "1") !== "0"
            && (int) ($row["total_stock"] ?? 0) === 0
        ) {
            $canView = false;
            $unavailableReason = "sold_out";
        }

        // 页面标题必须在 body 渲染前确定（View 先渲染 header）
        $this->view->setTitle($canView ? (string) $row["title"] : "商品详情");

        // 商品行交给模板取数时复用（守卫已经查过一次，不重复查库）
        $this->view->setData([
            "goods_row"          => $canView ? $row : null,
            "unavailable_reason" => $unavailableReason,
        ]);
        $this->view->render("goods_content");
    }
}
