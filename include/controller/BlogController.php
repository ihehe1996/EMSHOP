<?php

declare(strict_types=1);

/**
 * 文章控制器。
 *
 * 方法说明：
 * - display()        文章列表页 → blog_list.php
 * - displayContent() 文章详情页 → blog_content.php
 *
 * 与商品侧同一套约定（见 GoodsController）：动作方法只做「接收请求 + 调用页面」，
 * **不取数**：要什么数据由模板自己在开头取（blog_list.php / blog_content.php）。
 * 取数与渲染都归视图层，控制器保持薄。
 *
 * 两个动作名都不叫 `_index`/`_list`/`_detail`，所以改动/查找入口时要同步
 * Dispatcher 的两张表：DEFAULT_ACTIONS（'blog_list' → display）与
 * DETAIL_ACTIONS（'blog' → displayContent），以及几处 pretty 路由。
 *
 * 原来的「博客首页」（blog_index）已去掉：博客只保留列表页与详情页。
 */
class BlogController extends BaseController
{
    /**
     * 文章列表页（分类筛选 + 分页）。
     *
     * 请求参数的解析与页面标题在模板侧（module.php，因为标题必须早于 body 渲染），
     * 数据由 blog_list.php 自己取。
     */
    public function display(): void
    {
        $this->view->render('blog_list');
    }

    /**
     * 文章详情页。
     */
    public function displayContent(): void
    {
        $id = (int) $this->getArg('id', 0);

        // —— 「这一页能不能打开」+ 页面标题：两者都必须在任何输出之前完成，所以留在控制器 ——
        // 详情读取限定到当前 scope（主站只看主站文章，商户只看自己文章）
        $row = $id > 0 ? BlogModel::getByIdForScope($id, MerchantContext::currentId()) : null;
        if ($row && (int) $row['status'] !== 1) {
            $row = null;
        }

        // 页面标题必须在 body 渲染前确定（View 先渲染 header）
        $this->view->setTitle($row ? (string) $row['title'] : '文章详情');

        // 文章行交给模板取数时复用（守卫已经查过一次，不重复查库）
        $this->view->setData(['article_row' => $row]);
        $this->view->render('blog_content');
    }
}
