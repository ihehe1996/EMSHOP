<?php

declare(strict_types=1);

/**
 * 商品标签页控制器。
 *
 * 方法说明：
 * - _detail() 标签详情页（该标签下的商品） → goods_tag.php
 */
class GoodsTagController extends BaseController
{
    /**
     * 标签详情页：展示某个标签下的所有商品。
     *
     * URL: ?c=goods_tag&id=3
     */
    public function _detail(): void
    {
        $tagId = (int) $this->getArg('id', 0);

        $tag = null;
        if ($tagId > 0) {
            $tag = GoodsTagModel::getById($tagId);
        }

        if (!$tag) {
            $this->view->setTitle('标签不存在');
            $this->view->setData([
                'tag'        => null,
                'goods_list' => [],
                'pagination' => null,
                'all_tags'   => GoodsTagModel::getPopularTags(30),
            ]);
            $this->view->render('goods_tag');
            return;
        }

        // 分页查询该标签下的商品
        $page = max(1, (int) $this->getArg('page', 1));
        $result = $this->queryGoodsListPaginated(['tag_id' => $tagId], $page, 20);
        $result['list'] = applyFilter('index_goods_list', $result['list'], 'tag');

        // 所有标签
        $allTags = GoodsTagModel::getPopularTags(30);

        $this->view->setTitle('标签：' . $tag['name']);
        $this->view->setData([
            'tag'        => $tag,
            'goods_list' => $result['list'],
            'pagination' => $result,
            'all_tags'   => $allTags,
        ]);
        $this->view->render('goods_tag');
    }
}
