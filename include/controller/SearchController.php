<?php

declare(strict_types=1);

/**
 * 搜索控制器。
 *
 * 方法说明：
 * - _index() 搜索页 / 搜索结果页
 */
class SearchController extends BaseController
{
    /**
     * 搜索页入口。
     * ?c=search            → 显示搜索框
     * ?c=search&q=关键词   → 显示搜索结果
     */
    public function _index(): void
    {
        $keyword = trim((string) $this->getArg('q', ''));
        $type = trim((string) $this->getArg('type', 'all'));
        if (!in_array($type, ['all', 'goods', 'article'])) {
            $type = 'all';
        }

        $this->view->setTitle($keyword !== '' ? '搜索：' . $keyword : '商品搜索');

        $goodsResults = [];
        $articleResults = [];

        if ($keyword !== '') {
            // 根据搜索类型查询对应数据
            if ($type !== 'article') {
                $goodsResults = $this->queryGoodsList(['keyword' => $keyword], 12);
                $goodsResults = applyFilter('index_goods_list', $goodsResults, 'search');
            }
            if ($type !== 'goods') {
                $articleResults = $this->queryArticleList(['keyword' => $keyword], 6);
            }
        }

        $this->view->setData([
            'keyword'         => $keyword,
            'results'         => $goodsResults,
            'article_results' => $articleResults,
            'result_count'    => count($goodsResults) + count($articleResults),
        ]);
        $this->view->render('search');
    }

    /**
     * 搜索结果页（兼容 a=_list 的调用方式）。
     */
    public function _list(): void
    {
        $this->_index();
    }
}
