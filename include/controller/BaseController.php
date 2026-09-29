<?php

declare(strict_types=1);

/**
 * 前台控制器基类。
 *
 * 所有前台控制器继承此类，获取视图实例和调度器引用。
 */
abstract class BaseController
{
    /** @var View */
    protected View $view;

    /** @var Dispatcher */
    protected Dispatcher $dispatcher;

    /** @var string 当前控制器名（用于模板导航高亮等） */
    protected string $controllerName;

    /** @var FrontDataService|null 取数服务（无状态，请求内复用同一实例） */
    private ?FrontDataService $frontData = null;

    /**
     * @param View       $view           视图实例
     * @param Dispatcher $dispatcher    调度器引用
     * @param string     $controllerName 当前控制器名
     */
    public function __construct(View $view, Dispatcher $dispatcher, string $controllerName)
    {
        $this->view = $view;
        $this->dispatcher = $dispatcher;
        $this->controllerName = $controllerName;
        // $_controller 和 $_nav 已由 Dispatcher 在 $this->view->assign() 中统一注入，
        // 此处无需重复设置。
    }

    /**
     * 取数服务实例。
     *
     * 取数逻辑（商品/文章列表、侧栏、公告）已整体搬到 FrontDataService，本类只保留
     * 同名委托，让既有的 6 个控制器调用点一行都不用改。服务无状态，实例复用即可
     * （内部的 static 局部缓存因此仍在同一请求内共享）。
     */
    private function frontData(): FrontDataService
    {
        if ($this->frontData === null) {
            $this->frontData = new FrontDataService();
        }
        return $this->frontData;
    }

    /**
     * 获取 URL 路径参数。
     *
     * @param string|int $key
     * @param mixed $default
     * @return mixed
     */
    protected function getArg($key, $default = null)
    {
        return $this->dispatcher->getArg($key, $default);
    }

    /**
     * 获取所有路径参数。
     *
     * @return array<string, mixed>
     */
    protected function getPathArgs(): array
    {
        return $this->dispatcher->getPathArgs();
    }

    /**
     * 获取当前控制器名（URL 中的 c 参数）。
     */
    protected function getControllerName(): string
    {
        return $this->dispatcher->getController();
    }

    /**
     * 获取当前动作名。
     */
    protected function getActionName(): string
    {
        return $this->dispatcher->getAction();
    }

    /**
     * 获取站点 URL。
     */
    protected function getSiteUrl(): string
    {
        return $this->view->getData()['site_url'] ?? '';
    }

    /**
     * 获取商品列表页 URL。
     */
    protected function urlGoodsList(array $params = []): string
    {
        return $this->buildUrl('goods_list', $params);
    }

    /**
     * 获取商品详情页 URL。
     */
    protected function urlGoods(int $id): string
    {
        return '?c=goods&id=' . $id;
    }

    /**
     * 获取文章列表页 URL。
     */
    protected function urlBlogList(array $params = []): string
    {
        return $this->buildUrl('blog_list', $params);
    }

    /**
     * 获取文章详情页 URL。
     */
    protected function urlBlog(int $id): string
    {
        return '?c=blog&id=' . $id;
    }

    /**
     * 获取搜索页 URL。
     */
    protected function urlSearch(string $keyword = ''): string
    {
        if ($keyword !== '') {
            return '?c=search&q=' . urlencode($keyword);
        }
        return '?c=search';
    }

    /**
     * 取当前 scope 的店铺公告（富文本 + 显示位置数组）。
     *
     * 主站：从 em_config 读 shop_announcement / shop_announcement_positions
     * 商户：从 em_merchant 读 announcement / announcement_positions（按当前店铺独立维护）
     *
     * 实现已在 FrontDataService，这里只做委托，保持 BaseController 对子类的 API 不变。
     *
     * @return array{html:string, positions:string[]} 找不到也返回空 html + 空 positions
     */
    protected function getCurrentAnnouncement(): array
    {
        return $this->frontData()->getCurrentAnnouncement();
    }

    /**
     * 构建带参数的 URL。
     */
    protected function buildUrl(string $c, array $params = []): string
    {
        $query = 'c=' . urlencode($c);
        foreach ($params as $k => $v) {
            $query .= '&' . urlencode($k) . '=' . urlencode((string) $v);
        }
        return '?' . $query;
    }

    // ============================================================
    // 前台公共查询方法
    // ============================================================

    /**
     * 查询前台商品列表（已上架、未删除，字段映射为模板格式）。
     *
     * 实现已在 FrontDataService，这里只做委托。
     *
     * @param array  $where 筛选：category_id / is_recommended / keyword；可选 require_api_enabled、goods_ids、no_limit
     * @param int    $limit 条数（no_limit 为 true 时忽略）
     * @param string $orderBy 排序
     * @return array<array{id:int, name:string, price:float, original_price:float|null}>
     */
    protected function queryGoodsList(array $where = [], int $limit = 8, string $orderBy = 'g.sort ASC, g.id DESC'): array
    {
        return $this->frontData()->queryGoodsList($where, $limit, $orderBy);
    }

    /**
     * 列表/划线价用的参考规格列子查询。实现已在 FrontDataService。
     */
    protected function goodsRefSpecColumnSql(string $column): string
    {
        return $this->frontData()->goodsRefSpecColumnSql($column);
    }

    /**
     * 商品列表排序白名单解析。实现已在 FrontDataService。
     */
    protected function resolveGoodsListOrderBy(string $sort): string
    {
        return $this->frontData()->resolveGoodsListOrderBy($sort);
    }

    /**
     * 查询前台商品列表（分页版）。实现已在 FrontDataService。
     *
     * @return array{list:array, total:int, page:int, per_page:int, total_pages:int}
     */
    protected function queryGoodsListPaginated(array $where = [], int $page = 1, int $perPage = 20, string $orderBy = 'g.sort ASC, g.id DESC'): array
    {
        return $this->frontData()->queryGoodsListPaginated($where, $page, $perPage, $orderBy);
    }

    /**
     * 查询前台文章列表（已发布、未删除，字段映射为模板格式）。
     *
     * 实现已在 FrontDataService，这里只做委托。
     *
     * @return array<array{id:int, title:string, excerpt:string, date:string, author:string, category:string, views:int}>
     */
    protected function queryArticleList(array $where = [], int $limit = 6): array
    {
        return $this->frontData()->queryArticleList($where, $limit);
    }

    /**
     * 查询前台文章列表（分页版）。实现已在 FrontDataService。
     *
     * @return array{list:array, total:int, page:int, per_page:int, total_pages:int}
     */
    protected function queryArticleListPaginated(array $where = [], int $page = 1, int $perPage = 10): array
    {
        return $this->frontData()->queryArticleListPaginated($where, $page, $perPage);
    }

    /**
     * 商品侧栏数据（分类树 + 热门标签）。实现已在 FrontDataService。
     *
     * @return array{goods_categories: array, popular_tags: array}
     */
    protected function getGoodsSidebarData(): array
    {
        return $this->frontData()->getGoodsSidebarData();
    }

    /**
     * 博客侧栏数据（分类 + 热门标签）。实现已在 FrontDataService。
     *
     * @return array{blog_categories: array, popular_blog_tags: array}
     */
    protected function getBlogSidebarData(): array
    {
        return $this->frontData()->getBlogSidebarData();
    }

    /**
     * 库存展示文本。实现已在 FrontDataService；保持 public static 以兼容插件调用。
     */
    public static function formatStockText(int $stock): string
    {
        return FrontDataService::formatStockText($stock);
    }

    /**
     * 划线价：仅当规格市场价大于该规格售价时返回。
     *
     * 实现已在 FrontDataService（主题侧的 module.php 也要用），这里只做委托。
     *
     * @param array<string, mixed>|null $spec
     */
    protected static function resolveOriginalPrice(?array $spec): ?float
    {
        return FrontDataService::resolveOriginalPrice($spec);
    }

}
