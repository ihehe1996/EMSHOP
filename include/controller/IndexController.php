<?php

declare(strict_types=1);

/**
 * 首页控制器。
 *
 * 方法说明：
 * - _index() 首页（商城模式显示推荐商品 + 最新文章）
 */
class IndexController extends BaseController
{
    /**
     * 首页。
     */
    public function _index(): void
    {

        $this->view->setTitle('');

        // 推荐商品（主区域）+ 最新商品 / 热门商品（侧边栏）
        // 推荐商品不限数量，有多少显示多少
        $recommendedGoods = $this->queryGoodsList(['is_recommended' => true], 999);
        $recentGoods = $this->queryGoodsList([], 5, 'g.id DESC');
        $hotGoods = $this->queryGoodsList([], 5, 'total_sold DESC, g.id DESC');

        // echo '<pre>'; print_r($recommendedGoods); echo die;

        // 最新文章
        $recentArticles = $this->queryArticleList([], 8);


        // 侧边栏数据
        $sidebarData = $this->getGoodsSidebarData();

        // 插件可通过 addFilter('index_goods_list', callback) 修改首页商品数据
        $recommendedGoods = applyFilter('index_goods_list', $recommendedGoods, 'recommended');
        $recentGoods = applyFilter('index_goods_list', $recentGoods, 'recent');
        $hotGoods = applyFilter('index_goods_list', $hotGoods, 'hot');

        $this->view->setData(array_merge([
            'recent_goods'    => $recentGoods,
            'hot_goods'       => $hotGoods,
            'recommended_goods' => $recommendedGoods,
            'recent_articles' => $recentArticles,
            // 店铺公告：模板按 announcement.positions 决定是否渲染
            'announcement'    => $this->getCurrentAnnouncement(),
        ], $sidebarData));
        // 商城首页统一用 goods_index.php 模板（page.php 留给 PageController 做 CMS 页面）
        $this->view->render('goods_index');
    }
}
