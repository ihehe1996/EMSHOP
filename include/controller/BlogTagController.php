<?php

declare(strict_types=1);

/**
 * 博客标签页控制器。
 *
 * 方法说明：
 * - _index()  标签列表页（所有标签）  → blog_tag_index.php（暂不使用）
 * - _detail() 标签详情页（该标签下的文章） → blog_tag.php
 */
class BlogTagController extends BaseController
{
    /**
     * 标签详情页：展示某个标签下的所有文章。
     *
     * URL: ?c=blog_tag&id=5
     */
    public function _detail(): void
    {
        $tagId = (int) $this->getArg('id', 0);
        $merchantId = MerchantContext::currentId();

        $tag = null;
        if ($tagId > 0) {
            $tag = BlogTagModel::getById($tagId);
            // 不属于当前 scope 的标签视为不存在
            if ($tag && (int) $tag['merchant_id'] !== $merchantId) {
                $tag = null;
            }
        }

        // 标签不存在时展示空状态
        if (!$tag) {
            $this->view->setTitle('标签不存在');
            $this->view->setData([
                'tag'          => null,
                'article_list' => [],
                'pagination'   => null,
                'all_tags'     => BlogTagModel::getPopularTags(30, $merchantId),
            ]);
            $this->view->render('blog_tag');
            return;
        }

        // 分页查询该标签下的文章
        $page = max(1, (int) $this->getArg('page', 1));
        $result = $this->queryArticleListPaginated(['tag_id' => $tagId], $page, 20);

        // 所有标签（侧边用）
        $allTags = BlogTagModel::getPopularTags(30, $merchantId);

        // 侧边栏数据
        $sidebarData = $this->getBlogSidebarData();
        $recentArticles = $this->queryArticleList([], 5);

        $this->view->setTitle('标签：' . $tag['name']);
        $this->view->setData(array_merge([
            'tag'             => $tag,
            'article_list'    => $result['list'],
            'pagination'      => $result,
            'all_tags'        => $allTags,
            'recent_articles' => $recentArticles,
        ], $sidebarData));
        $this->view->render('blog_tag');
    }
}
