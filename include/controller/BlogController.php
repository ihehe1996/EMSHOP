<?php

declare(strict_types=1);

/**
 * 文章控制器。
 *
 * 方法说明：
 * - _index()  博客首页 → blog_index.php
 * - _list()   文章列表页 → blog_list.php
 * - _detail() 文章详情页 → blog.php
 */
class BlogController extends BaseController
{
    /**
     * 博客首页（分页，无分类筛选，分类由侧边栏跳转到 blog_list）。
     */
    public function _index(): void
    {
        $page = max(1, (int) $this->getArg('page', 1));
        $result = $this->queryArticleListPaginated([], $page, 20);

        // 侧边栏数据
        $sidebarData = $this->getBlogSidebarData();
        $recentArticles = $this->queryArticleList([], 5);

        $this->view->setTitle('');
        $this->view->setData(array_merge([
            'article_list'    => $result['list'],
            'pagination'      => $result,
            'recent_articles' => $recentArticles,
        ], $sidebarData));
        $this->view->render('blog_index');
    }

    /**
     * 文章列表页（分类筛选 + 分页）。
     */
    public function _list(): void
    {
        $categoryId = (int) $this->getArg('category_id', 0);
        $tagId = (int) $this->getArg('tag_id', 0);

        // 侧边栏数据（含二级分类树）
        $sidebarData = $this->getBlogSidebarData();
        $categories = $sidebarData['blog_categories'] ?? [];

        // 根据分类筛选（选中父分类时包含所有子分类）
        $where = [];
        $title = '全部文章';

        // 标签筛选 —— 标签 ID 必须属于当前 scope
        if ($tagId > 0) {
            $tag = BlogTagModel::getById($tagId);
            if ($tag && (int) $tag['merchant_id'] === MerchantContext::currentId()) {
                $where['tag_id'] = $tagId;
                $title = '标签：' . $tag['name'];
            }
        }

        if ($categoryId > 0) {
            $categoryIds = [$categoryId];
            foreach ($categories as $cat) {
                if ((int) $cat['id'] === $categoryId) {
                    $title = $cat['name'];
                    foreach ($cat['children'] ?? [] as $child) {
                        $categoryIds[] = (int) $child['id'];
                    }
                    break;
                }
                foreach ($cat['children'] ?? [] as $child) {
                    if ((int) $child['id'] === $categoryId) {
                        $title = $child['name'];
                        break 2;
                    }
                }
            }
            $where['category_ids'] = $categoryIds;
        }

        $page = max(1, (int) $this->getArg('page', 1));
        $result = $this->queryArticleListPaginated($where, $page, 20);

        $this->view->setTitle($title);
        $this->view->setData([
            'article_list'     => $result['list'],
            'blog_categories'  => $categories,
            'current_category' => $categoryId,
            'current_tag'      => $tagId,
            'pagination'       => $result,
        ]);
        $this->view->render('blog_list');
    }

    /**
     * 文章详情页。
     */
    public function _detail(): void
    {
        $id = (int) $this->getArg('id', 0);

        $article = null;
        $prevId = null;
        $prevTitle = null;
        $nextId = null;
        $nextTitle = null;

        if ($id > 0) {
            // 详情读取限定到当前 scope（主站只看主站文章，商户只看自己文章）
            $merchantId = MerchantContext::currentId();
            $row = BlogModel::getByIdForScope($id, $merchantId);
            if ($row && (int) $row['status'] === 1) {
                // 递增浏览量
                BlogModel::incrementViews($id);

                $article = [
                    'id'       => (int) $row['id'],
                    'title'    => $row['title'],
                    'content'  => $row['content'] ?: '',
                    'date'     => substr($row['created_at'], 0, 10),
                    'author'   => $row['author'] ?: '管理员',
                    'category' => $row['category_name'] ?: '未分类',
                    'views'    => (int) $row['views_count'] + 1,
                    'tags'     => BlogTagModel::getTagsByBlogId($id),
                ];

                // 上下篇（限定 scope 内）
                $nav = BlogModel::getPrevNextId($id, $merchantId);
                $prevId = $nav['prev_id'];
                $prevTitle = $nav['prev_title'];
                $nextId = $nav['next_id'];
                $nextTitle = $nav['next_title'];
            }
        }

        // 评论数量
        $commentCount = $article ? BlogCommentModel::getCountByBlog($id) : 0;

        // 侧边栏数据
        $sidebarData = $this->getBlogSidebarData();
        $recentArticles = $this->queryArticleList([], 5);

        $this->view->setTitle($article ? $article['title'] : '文章详情');
        $this->view->setData(array_merge([
            'article'         => $article,
            'prev_id'         => $prevId,
            'prev_title'      => $prevTitle,
            'next_id'         => $nextId,
            'next_title'      => $nextTitle,
            'recent_articles' => $recentArticles,
            'comment_count'   => $commentCount,
        ], $sidebarData));
        $this->view->render('blog');
    }
}
