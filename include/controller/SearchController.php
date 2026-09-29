<?php

declare(strict_types=1);

/**
 * 搜索控制器。
 *
 * 方法说明：
 * - display() 搜索页 / 搜索结果页 → search.php
 *
 * 与商品、文章侧同一套约定（见 GoodsController / BlogController）：动作方法只做
 * 「接收请求 + 调用页面」，**不取数**：关键词 / 搜索类型 / 查询结果都由模板自己取
 * （search.php 开头调 module.php 的 template_search_data()）。
 *
 * 搜索页没有「这一页能不能打开」的判断（任何关键词都只是空结果），所以控制器里
 * 一行取数都不需要，是所有控制器里最薄的一个。
 *
 * 动作名不叫 `_index`/`_list`，入口登记在 Dispatcher::DEFAULT_ACTIONS（'search' → display），
 * pretty 路由（/search-xxx、/search/xxx）也各自写死这个动作名。
 */
class SearchController extends BaseController
{
    /**
     * 搜索页 / 搜索结果页。
     *
     * 请求参数的解析与页面标题在模板侧（module.php，标题必须早于 body 渲染），
     * 数据由 search.php 自己取。
     */
    public function display(): void
    {
        $this->view->render('search');
    }
}
