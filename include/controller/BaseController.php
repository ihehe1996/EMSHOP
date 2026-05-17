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
     * @return array{html:string, positions:string[]} 找不到也返回空 html + 空 positions
     */
    protected function getCurrentAnnouncement(): array
    {
        $merchantId = MerchantContext::currentId();
        if ($merchantId > 0) {
            $row = Database::fetchOne(
                'SELECT `announcement`, `announcement_positions` FROM `' . Database::prefix() . 'merchant`
                  WHERE `id` = ? LIMIT 1',
                [$merchantId]
            );
            $html = (string) ($row['announcement'] ?? '');
            $posStr = (string) ($row['announcement_positions'] ?? '');
        } else {
            $html = (string) (Config::get('shop_announcement') ?? '');
            $posStr = (string) (Config::get('shop_announcement_positions') ?? '');
        }
        $positions = array_values(array_filter(array_map('trim', explode(',', $posStr))));
        return ['html' => $html, 'positions' => $positions];
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
     * @param array  $where 筛选：category_id / is_recommended / keyword；可选 require_api_enabled、goods_ids、no_limit
     * @param int    $limit 条数（no_limit 为 true 时忽略）
     * @param string $orderBy 排序
     * @return array<array{id:int, name:string, price:float, original_price:float|null}>
     */
    protected function queryGoodsList(array $where = [], int $limit = 8, string $orderBy = 'g.sort ASC, g.id DESC'): array
    {
        $prefix = Database::prefix();
        $conditions = ['g.status = 1', 'g.is_on_sale = 1', 'g.deleted_at IS NULL'];
        // 分开收集：JOIN 里的 ? 要绑的参数放 $joinParams，WHERE 里的放 $whereParams；
        // 最终 SQL 里 JOIN 排在 WHERE 之前，参数也按 joinParams ++ whereParams 顺序拼接。
        $joinParams = [];
        $whereParams = [];
        $join = '';

        if (!empty($where['require_api_enabled'])) {
            $conditions[] = '(g.api_enabled IS NULL OR g.api_enabled = 1)';
        }

        if (!empty($where['category_ids']) && is_array($where['category_ids'])) {
            $placeholders = implode(',', array_fill(0, count($where['category_ids']), '?'));
            $conditions[] = "g.category_id IN ({$placeholders})";
            foreach ($where['category_ids'] as $cid) {
                $whereParams[] = (int) $cid;
            }
        } elseif (!empty($where['category_id'])) {
            $conditions[] = 'g.category_id = ?';
            $whereParams[] = (int) $where['category_id'];
        }
        // 主站 / 商户两套分类 id 可能撞号，必须按 source 过滤；不传默认 main（兼容老数据 ''/NULL）
        if (!empty($where['category_id']) || !empty($where['category_ids'])) {
            $catSource = (string) ($where['category_source'] ?? 'main');
            if ($catSource === 'merchant') {
                $conditions[] = "g.category_source = 'merchant'";
            } else {
                $conditions[] = "(g.category_source = 'main' OR g.category_source = '' OR g.category_source IS NULL)";
            }
        }
        if (!empty($where['goods_ids']) && is_array($where['goods_ids'])) {
            $idList = [];
            foreach ($where['goods_ids'] as $gid) {
                $gid = (int) $gid;
                if ($gid > 0) {
                    $idList[$gid] = true;
                }
            }
            $idList = array_keys($idList);
            if ($idList !== []) {
                $placeholders = implode(',', array_fill(0, count($idList), '?'));
                $conditions[] = "g.id IN ({$placeholders})";
                foreach ($idList as $gid) {
                    $whereParams[] = $gid;
                }
            }
        }
        if (!empty($where['is_recommended'])) {
            // 商户上下文下用 COALESCE（商户覆盖优先，否则跟随主站）
            $conditions[] = (class_exists('MerchantContext') && MerchantContext::currentId() > 0)
                ? 'COALESCE(mgr.is_recommended, g.is_recommended) = 1'
                : 'g.is_recommended = 1';
        }
        if (!empty($where['keyword'])) {
            $conditions[] = '(g.title LIKE ? OR g.intro LIKE ?)';
            $kw = '%' . $where['keyword'] . '%';
            $whereParams[] = $kw;
            $whereParams[] = $kw;
        }
        // 标签筛选（INNER JOIN，?要绑 tag_id）
        if (!empty($where['tag_id'])) {
            $join = " INNER JOIN {$prefix}goods_tag_relation gtr ON gtr.goods_id = g.id AND gtr.tag_id = ?";
            $joinParams[] = (int) $where['tag_id'];
        }

        // 商户上下文过滤：只看本店可见的商品（引用 + 自建）并附带 markup_rate 供价格重写
        [$selectExtra, $joinMerchant] = $this->applyMerchantScope($conditions, $whereParams, $joinParams);
        $join .= $joinMerchant;

        $params = array_merge($joinParams, $whereParams);
        $whereSql = implode(' AND ', $conditions);
        $limitTail = '';
        if (empty($where['no_limit'])) {
            $limit = max(1, $limit);
            $limitTail = " LIMIT {$limit}";
        }
        $sql = "SELECT g.id, g.title, g.category_id, g.cover_images, g.min_price, g.max_price,
                    g.total_stock, g.goods_type, g.plugin_data, g.configs, g.owner_id, g.jump_url" . $selectExtra . ",
                    {$this->goodsRefSpecColumnSql('market_price')} as default_market_price,
                    (SELECT COALESCE(SUM(sold_count), 0) FROM {$prefix}goods_spec
                     WHERE goods_id = g.id AND status = 1) as total_sold
                FROM {$prefix}goods g{$join}
                WHERE {$whereSql}
                ORDER BY {$orderBy}{$limitTail}";

        $rows = Database::query($sql, $params);
        $list = [];
        [$buyerId] = GoodsModel::resolveBuyerIdentity();
        foreach ($rows as $row) {
            $this->rewritePriceFactor($row);

            $minPrice = (float) GoodsModel::moneyFromDb($row['min_price']);
            if ($buyerId > 0) {
                $minPrice = $this->resolvePersonalizedMinPrice((int) $row['id'], $minPrice);
            }
            $marketPrice = $row['default_market_price']
                ? (float) GoodsModel::moneyFromDb($row['default_market_price'])
                : null;

            $covers = json_decode($row['cover_images'] ?? '[]', true) ?: [];
            $stock = (int) $row['total_stock'];
            // 发货类型解析：
            //   1) 优先 goods_delivery_type filter（virtual_card 按 plugin_data.auto_delivery 动态切换）
            //   2) filter 没响应 → 兜底读 goods_type_register 注册的 delivery_type 字段（physical 静态 manual 走这条）
            $deliveryType = applyFilter('goods_delivery_type', '', $row);
            if ($deliveryType === '' && !empty($row['goods_type']) && class_exists('GoodsTypeManager')) {
                $typeCfg = GoodsTypeManager::getTypeConfig((string) $row['goods_type']);
                if ($typeCfg && !empty($typeCfg['delivery_type'])) {
                    $deliveryType = (string) $typeCfg['delivery_type'];
                }
            }
            $list[] = [
                'id'             => (int) $row['id'],
                'category_id'    => (int) ($row['category_id'] ?? 0),
                'name'           => $row['title'],
                'image'          => $covers[0] ?? '',
                'price'          => $minPrice,
                'original_price' => ($marketPrice && $marketPrice > $minPrice) ? $marketPrice : null,
                // stock 是原始整数，供 JS/业务逻辑判断是否有货
                'stock'          => $stock,
                // stock_text 是展示文字（核心默认千分位；插件可通过 goods_stock_display 过滤器重写）
                'stock_text'     => self::formatStockText($stock),
                'sold'           => (int) $row['total_sold'],
                'goods_type'     => $row['goods_type'] ?? 'default',
                'delivery_type'  => $deliveryType,     // '' / 'auto' / 'manual'
                // 跳转链接：非空时点击卡片直接跳外链（类似广告），由 goods_card_href_attrs() 消费
                'jump_url'       => trim((string) ($row['jump_url'] ?? '')),
            ];
        }
        return $list;
    }

    /**
     * 列表/划线价用的参考规格列子查询：优先默认规格，否则取 sort、id 最小的在售规格。
     */
    protected function goodsRefSpecColumnSql(string $column): string
    {
        $prefix = Database::prefix();
        if (!preg_match('/^[a-z_]+$/', $column)) {
            $column = 'market_price';
        }
        return "(SELECT gs.{$column} FROM {$prefix}goods_spec gs
                 WHERE gs.goods_id = g.id AND gs.status = 1
                 ORDER BY gs.is_default DESC, gs.sort ASC, gs.id ASC
                 LIMIT 1)";
    }

    /**
     * 商品列表 ORDER BY 片段（白名单，禁止拼接用户原始字符串）。
     */
    protected function resolveGoodsListOrderBy(string $sort): string
    {
        switch ($sort) {
            case 'hot':
                return 'g.views_count DESC, g.id DESC';
            case 'sold':
                return 'total_sold DESC, g.id DESC';
            case 'price_asc':
                return 'g.min_price ASC, g.id DESC';
            case 'price_desc':
                return 'g.min_price DESC, g.id DESC';
            case 'default':
            default:
                return 'g.sort ASC, g.id DESC';
        }
    }

    /**
     * 分页查询前台商品列表。
     *
     * @param array  $where   筛选条件；可选 require_api_enabled=true 仅保留 api_enabled 为 1 或 NULL 的商品
     * @param int    $page    当前页码（从1开始）
     * @param int    $perPage 每页条数
     * @param string $orderBy 排序
     * @return array{list:array, total:int, page:int, per_page:int, total_pages:int}
     */
    protected function queryGoodsListPaginated(array $where = [], int $page = 1, int $perPage = 20, string $orderBy = 'g.sort ASC, g.id DESC'): array
    {
        $prefix = Database::prefix();
        $conditions = ['g.status = 1', 'g.is_on_sale = 1', 'g.deleted_at IS NULL'];
        // 分开收集 JOIN / WHERE 两类占位符参数；最终拼 params 前者在前后者在后
        $joinParams = [];
        $whereParams = [];
        $join = '';

        // 外部 API 商品列表：仅展示允许 API 下单的商品（与 ApiController::createOrder 一致）
        if (!empty($where['require_api_enabled'])) {
            $conditions[] = '(g.api_enabled IS NULL OR g.api_enabled = 1)';
        }

        if (!empty($where['category_ids']) && is_array($where['category_ids'])) {
            $placeholders = implode(',', array_fill(0, count($where['category_ids']), '?'));
            $conditions[] = "g.category_id IN ({$placeholders})";
            foreach ($where['category_ids'] as $cid) {
                $whereParams[] = (int) $cid;
            }
        } elseif (!empty($where['category_id'])) {
            $conditions[] = 'g.category_id = ?';
            $whereParams[] = (int) $where['category_id'];
        }
        // 主站 / 商户两套分类 id 可能撞号，必须按 source 过滤；不传默认 main（兼容老数据 ''/NULL）
        if (!empty($where['category_id']) || !empty($where['category_ids'])) {
            $catSource = (string) ($where['category_source'] ?? 'main');
            if ($catSource === 'merchant') {
                $conditions[] = "g.category_source = 'merchant'";
            } else {
                $conditions[] = "(g.category_source = 'main' OR g.category_source = '' OR g.category_source IS NULL)";
            }
        }
        if (!empty($where['is_recommended'])) {
            // 商户上下文下用 COALESCE（商户覆盖优先，否则跟随主站）
            $conditions[] = (class_exists('MerchantContext') && MerchantContext::currentId() > 0)
                ? 'COALESCE(mgr.is_recommended, g.is_recommended) = 1'
                : 'g.is_recommended = 1';
        }
        if (!empty($where['keyword'])) {
            $conditions[] = '(g.title LIKE ? OR g.intro LIKE ?)';
            $kw = '%' . $where['keyword'] . '%';
            $whereParams[] = $kw;
            $whereParams[] = $kw;
        }
        // 标签筛选（INNER JOIN）
        if (!empty($where['tag_id'])) {
            $join = " INNER JOIN {$prefix}goods_tag_relation gtr ON gtr.goods_id = g.id AND gtr.tag_id = ?";
            $joinParams[] = (int) $where['tag_id'];
        }

        // 商户上下文过滤
        [$selectExtra, $joinMerchant] = $this->applyMerchantScope($conditions, $whereParams, $joinParams);
        $join .= $joinMerchant;

        $params = array_merge($joinParams, $whereParams);
        $whereSql = implode(' AND ', $conditions);

        // 查询总数
        $countSql = "SELECT COUNT(*) as cnt FROM {$prefix}goods g{$join} WHERE {$whereSql}";
        $countRow = Database::fetchOne($countSql, $params);
        $total = (int) ($countRow['cnt'] ?? 0);

        $page = max(1, $page);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;

        // 查询当前页数据
        $sql = "SELECT g.id, g.title, g.category_id, g.cover_images, g.min_price, g.max_price,
                    g.total_stock, g.goods_type, g.plugin_data, g.configs, g.owner_id, g.jump_url" . $selectExtra . ",
                    {$this->goodsRefSpecColumnSql('market_price')} as default_market_price,
                    (SELECT COALESCE(SUM(sold_count), 0) FROM {$prefix}goods_spec
                     WHERE goods_id = g.id AND status = 1) as total_sold
                FROM {$prefix}goods g{$join}
                WHERE {$whereSql}
                ORDER BY {$orderBy}
                LIMIT {$perPage} OFFSET {$offset}";

        $rows = Database::query($sql, $params);
        $list = [];
        [$buyerId] = GoodsModel::resolveBuyerIdentity();
        foreach ($rows as $row) {
            $this->rewritePriceFactor($row);

            $minPrice = (float) GoodsModel::moneyFromDb($row['min_price']);
            if ($buyerId > 0) {
                $minPrice = $this->resolvePersonalizedMinPrice((int) $row['id'], $minPrice);
            }
            $marketPrice = $row['default_market_price']
                ? (float) GoodsModel::moneyFromDb($row['default_market_price'])
                : null;

            $covers = json_decode($row['cover_images'] ?? '[]', true) ?: [];
            $stock = (int) $row['total_stock'];
            // 发货类型解析（同 queryGoodsList）：filter 优先，没响应时兜底读 goods_type_register 注册的 delivery_type
            $deliveryType = applyFilter('goods_delivery_type', '', $row);
            if ($deliveryType === '' && !empty($row['goods_type']) && class_exists('GoodsTypeManager')) {
                $typeCfg = GoodsTypeManager::getTypeConfig((string) $row['goods_type']);
                if ($typeCfg && !empty($typeCfg['delivery_type'])) {
                    $deliveryType = (string) $typeCfg['delivery_type'];
                }
            }
            $list[] = [
                'id'             => (int) $row['id'],
                'category_id'    => (int) ($row['category_id'] ?? 0),
                'name'           => $row['title'],
                'image'          => $covers[0] ?? '',
                'price'          => $minPrice,
                'original_price' => ($marketPrice && $marketPrice > $minPrice) ? $marketPrice : null,
                // stock 是原始整数，供 JS/业务逻辑判断是否有货
                'stock'          => $stock,
                // stock_text 是展示文字（核心默认千分位；插件可通过 goods_stock_display 过滤器重写）
                'stock_text'     => self::formatStockText($stock),
                'sold'           => (int) $row['total_sold'],
                'goods_type'     => $row['goods_type'] ?? 'default',
                'delivery_type'  => $deliveryType,     // '' / 'auto' / 'manual'
                // 跳转链接：非空时点击卡片直接跳外链（类似广告），由 goods_card_href_attrs() 消费
                'jump_url'       => trim((string) ($row['jump_url'] ?? '')),
            ];
        }

        return [
            'list'        => $list,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * 当前若在商户上下文，给 $conditions / $params 追加作用域过滤：
     *   商户只看得到：自建商品（g.owner_id = 商户主 user_id）
     *   或引用商品（g.owner_id = 0 AND mgr.is_on_sale = 1）
     *
     * 返回 [SELECT 扩展列, JOIN 扩展字符串]；主站返回 ['', '']。
     *
     * @param array<int, string> $conditions
     * @param array<int, mixed> $params
     * @return array{0:string,1:string}
     */
    private function applyMerchantScope(array &$conditions, array &$whereParams, array &$joinParams): array
    {
        $merchantId = MerchantContext::currentId();
        if ($merchantId <= 0) {
            // 主站前台：只看主站自营商品（owner_id=0），避免商户自建商品漏到主站前台
            $conditions[] = 'g.owner_id = 0';
            return ['', ''];
        }
        $ownerUserId = MerchantContext::currentOwnerId();
        $prefix = Database::prefix();

        // JOIN 里的 ? 在最终 SQL 中排在 WHERE 之前，参数必须写入 $joinParams
        $join = " LEFT JOIN {$prefix}goods_merchant_ref mgr ON mgr.goods_id = g.id AND mgr.merchant_id = ?";
        $joinParams[] = $merchantId;

        // 店铺前台可见规则：
        //   - 自建：owner_id = ownerUserId（且 ownerUserId > 0，防止商户 user_id=0 的异常数据
        //     把 owner_id=0 的主站商品当"自建"漏出、绕过 mgr.is_on_sale 下架过滤）
        //   - 主站引用：owner_id = 0 且 ref 行不存在 或 ref.is_on_sale = 1
        if ($ownerUserId > 0) {
            $conditions[] = '(g.owner_id = ? OR (g.owner_id = 0 AND COALESCE(mgr.is_on_sale, 1) = 1))';
            $whereParams[] = $ownerUserId;
        } else {
            // 商户 user_id 异常：只放行主站引用，严格按 ref 过滤
            $conditions[] = '(g.owner_id = 0 AND COALESCE(mgr.is_on_sale, 1) = 1)';
        }

        $selectExtra = ', mgr.markup_rate AS mgr_markup_rate, mgr.is_on_sale AS mgr_is_on_sale';
        return [$selectExtra, $join];
    }

    /**
     * 对查询结果的 min_price / max_price 应用价格 factor —— 主站和商户站统一调用。
     *
     * factor 规则：
     *   - 主站：factor = buyer_discount（仅当前登录买家的会员折扣）
     *   - 商户站 主站引用商品：factor = (1+markup) × buyer_discount
     *   - 商户站 自建商品：factor = buyer_discount
     *
     * 商户拿货成本与本 factor 无关 —— 主站对商户始终按原价（goods_spec.price）收。
     *
     * @param array<string, mixed> $row 引用传入，原地修改
     */
    private function rewritePriceFactor(array &$row): void
    {
        $buyerDiscount = GoodsModel::resolveBuyerDiscountRate();
        $factor = $buyerDiscount;

        $merchantId = MerchantContext::currentId();
        if ($merchantId > 0 && (int) ($row['owner_id'] ?? 0) === 0) {
            // 商户站 + 引用主站商品：先 markup 再买家折扣
            $markup = isset($row['mgr_markup_rate']) && $row['mgr_markup_rate'] !== null
                ? (int) $row['mgr_markup_rate']
                : self::resolveMerchantDefaultMarkup($merchantId);
            $factor = (1 + $markup / 10000) * $buyerDiscount;
        }
        // 商户站的自建商品（owner = ownerUserId）和主站作用域，都只乘 buyer_discount

        if ($factor === 1.0) return;
        if (isset($row['min_price'])) {
            $row['min_price'] = (int) round(((int) $row['min_price']) * $factor);
        }
        if (isset($row['max_price'])) {
            $row['max_price'] = (int) round(((int) $row['max_price']) * $factor);
        }
        if (!empty($row['default_market_price'])) {
            $row['default_market_price'] = (int) round(((int) $row['default_market_price']) * $factor);
        }
    }

    /**
     * 读取商户默认加价率（万分位）。同一请求内缓存。
     * 没配 / 读不到 → 1000（10%，InstallService 的默认值）。
     */
    private static function resolveMerchantDefaultMarkup(int $merchantId): int
    {
        static $cache = [];
        if (isset($cache[$merchantId])) return $cache[$merchantId];
        $row = Database::fetchOne(
            'SELECT `default_markup_rate` FROM `' . Database::prefix() . 'merchant` WHERE `id` = ? LIMIT 1',
            [$merchantId]
        );
        return $cache[$merchantId] = (int) ($row['default_markup_rate'] ?? 1000);
    }

    /**
     * 登录用户场景下，按规格成交价重算商品卡片展示最低价。
     */
    private function resolvePersonalizedMinPrice(int $goodsId, float $fallback): float
    {
        static $cache = [];
        if (isset($cache[$goodsId])) {
            return $cache[$goodsId];
        }
        $specs = GoodsModel::getSpecsByGoodsId($goodsId);
        if ($specs === []) {
            return $cache[$goodsId] = $fallback;
        }

        $min = null;
        foreach ($specs as $spec) {
            $specPrice = (float) ($spec['price'] ?? 0);
            if ($min === null || $specPrice < $min) {
                $min = $specPrice;
            }
        }
        if ($min === null) {
            $min = $fallback;
        }
        return $cache[$goodsId] = $min;
    }

    /**
     * 查询前台文章列表（已发布、未删除，字段映射为模板格式）。
     *
     * @param array  $where 筛选：category_id / keyword
     * @param int    $limit 条数
     * @return array<array{id:int, title:string, excerpt:string, date:string, author:string, category:string, views:int}>
     */
    protected function queryArticleList(array $where = [], int $limit = 6): array
    {
        $prefix = Database::prefix();
        // 前台按当前 MerchantContext 过滤：主站只看主站文章，商户只看自己的文章
        $merchantId = MerchantContext::currentId();
        $conditions = ['a.status = 1', 'a.deleted_at IS NULL', 'a.merchant_id = ?'];
        $params = [$merchantId];

        if (!empty($where['category_id'])) {
            $conditions[] = 'a.category_id = ?';
            $params[] = (int) $where['category_id'];
        }
        if (!empty($where['keyword'])) {
            $conditions[] = '(a.title LIKE ? OR a.excerpt LIKE ?)';
            $kw = '%' . $where['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
        }

        $whereSql = implode(' AND ', $conditions);
        $sql = "SELECT a.id, a.title, a.excerpt, a.cover_image, a.views_count, a.created_at,
                    c.name as category_name,
                    COALESCE(u.nickname, u.username, '管理员') as author
                FROM {$prefix}blog a
                LEFT JOIN {$prefix}blog_category c ON a.category_id = c.id
                LEFT JOIN {$prefix}user u ON a.user_id = u.id
                WHERE {$whereSql}
                ORDER BY a.is_top DESC, a.sort ASC, a.id DESC
                LIMIT {$limit}";

        $rows = Database::query($sql, $params);
        $list = [];
        $blogIds = [];
        foreach ($rows as $row) {
            $blogIds[] = (int) $row['id'];
            $list[] = [
                'id'       => (int) $row['id'],
                'title'    => $row['title'],
                'excerpt'  => $row['excerpt'] ?: '',
                'image'    => $row['cover_image'] ?? '',
                'date'     => substr($row['created_at'], 0, 10),
                'author'   => $row['author'] ?: '管理员',
                'category' => $row['category_name'] ?: '未分类',
                'views'    => (int) $row['views_count'],
                'tags'     => [],
            ];
        }

        // 批量加载标签
        if ($blogIds) {
            $tagMap = BlogTagModel::getTagsByBlogIds($blogIds);
            foreach ($list as &$item) {
                $item['tags'] = $tagMap[$item['id']] ?? [];
            }
            unset($item);
        }

        return $list;
    }

    /**
     * 分页查询前台文章列表。
     *
     * @param array  $where   筛选条件：category_id / category_ids / keyword
     * @param int    $page    当前页码（从1开始）
     * @param int    $perPage 每页条数
     * @return array{list:array, total:int, page:int, per_page:int, total_pages:int}
     */
    protected function queryArticleListPaginated(array $where = [], int $page = 1, int $perPage = 10): array
    {
        $prefix = Database::prefix();
        // 前台按当前 MerchantContext 过滤
        $merchantId = MerchantContext::currentId();
        $conditions = ['a.status = 1', 'a.deleted_at IS NULL', 'a.merchant_id = ?'];
        $params = [$merchantId];

        if (!empty($where['category_ids']) && is_array($where['category_ids'])) {
            $placeholders = implode(',', array_fill(0, count($where['category_ids']), '?'));
            $conditions[] = "a.category_id IN ({$placeholders})";
            foreach ($where['category_ids'] as $cid) {
                $params[] = (int) $cid;
            }
        } elseif (!empty($where['category_id'])) {
            $conditions[] = 'a.category_id = ?';
            $params[] = (int) $where['category_id'];
        }
        if (!empty($where['keyword'])) {
            $conditions[] = '(a.title LIKE ? OR a.excerpt LIKE ?)';
            $kw = '%' . $where['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
        }

        // 标签筛选
        $joinTag = '';
        if (!empty($where['tag_id'])) {
            $joinTag = "INNER JOIN {$prefix}blog_tag_relation btr ON a.id = btr.blog_id AND btr.tag_id = ?";
            $params[] = (int) $where['tag_id'];
        }

        $whereSql = implode(' AND ', $conditions);

        // 查询总数
        $countSql = "SELECT COUNT(*) as cnt FROM {$prefix}blog a {$joinTag} WHERE {$whereSql}";
        $countRow = Database::fetchOne($countSql, $params);
        $total = (int) ($countRow['cnt'] ?? 0);

        $page = max(1, $page);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT a.id, a.title, a.excerpt, a.cover_image, a.views_count, a.created_at,
                    c.name as category_name,
                    COALESCE(u.nickname, u.username, '管理员') as author
                FROM {$prefix}blog a
                {$joinTag}
                LEFT JOIN {$prefix}blog_category c ON a.category_id = c.id
                LEFT JOIN {$prefix}user u ON a.user_id = u.id
                WHERE {$whereSql}
                ORDER BY a.is_top DESC, a.sort ASC, a.id DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $rows = Database::query($sql, $params);
        $list = [];
        $blogIds = [];
        foreach ($rows as $row) {
            $blogIds[] = (int) $row['id'];
            $list[] = [
                'id'       => (int) $row['id'],
                'title'    => $row['title'],
                'excerpt'  => $row['excerpt'] ?: '',
                'image'    => $row['cover_image'] ?? '',
                'date'     => substr($row['created_at'], 0, 10),
                'author'   => $row['author'] ?: '管理员',
                'category' => $row['category_name'] ?: '未分类',
                'views'    => (int) $row['views_count'],
                'tags'     => [],
            ];
        }

        // 批量加载标签
        if ($blogIds) {
            $tagMap = BlogTagModel::getTagsByBlogIds($blogIds);
            foreach ($list as &$item) {
                $item['tags'] = $tagMap[$item['id']] ?? [];
            }
            unset($item);
        }

        return [
            'list'        => $list,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * 获取商品侧边栏数据（分类列表 + 各分类商品数）。
     *
     * 商户上下文下额外做两件事：
     *   - 主站分类：用 em_merchant_category_map.alias_name 替换显示名（商户在后台设的别名）
     *   - 商户自建分类：从 em_merchant_category 拉一份合并到列表里
     * 每条 row 多带一个 `source` = 'main' / 'merchant'，模板生成链接时据此带上 category_source 参数。
     *
     * @return array{goods_categories:array, popular_tags:array}
     */
    protected function getGoodsSidebarData(): array
    {
        $prefix = Database::prefix();
        $merchantId = MerchantContext::currentId();

        // ---- 主站分类（商户上下文下应用别名 + 隐藏 + 商品计数限定 source=main） ----
        if ($merchantId > 0) {
            // 隐藏的分类整条不出现在 sidebar 里（mcm.is_hidden=1 → 跳过）
            $sql = "SELECT c.id, c.parent_id, c.name AS original_name, c.slug, c.icon, c.cover_image,
                           mcm.alias_name,
                           COUNT(g.id) as goods_count
                    FROM {$prefix}goods_category c
                    LEFT JOIN {$prefix}merchant_category_map mcm
                           ON mcm.master_category_id = c.id AND mcm.merchant_id = ?
                    LEFT JOIN {$prefix}goods g
                           ON g.category_id = c.id
                          AND (g.category_source = 'main' OR g.category_source = '' OR g.category_source IS NULL)
                          AND g.status = 1 AND g.is_on_sale = 1 AND g.deleted_at IS NULL
                    WHERE c.status = 1
                      AND (mcm.is_hidden IS NULL OR mcm.is_hidden = 0)
                    GROUP BY c.id
                    ORDER BY c.sort ASC, c.id ASC";
            $mainRows = Database::query($sql, [$merchantId]);
            foreach ($mainRows as &$r) {
                $r['source'] = 'main';
                // 商户后台设了别名 → 用别名；否则用主站原名
                $r['name'] = !empty($r['alias_name']) ? (string) $r['alias_name'] : (string) $r['original_name'];
            }
            unset($r);
        } else {
            $mainRows = Database::query(
                "SELECT c.id, c.parent_id, c.name, c.slug, c.icon, c.cover_image, COUNT(g.id) as goods_count
                 FROM {$prefix}goods_category c
                 LEFT JOIN {$prefix}goods g ON g.category_id = c.id
                     AND g.status = 1 AND g.is_on_sale = 1 AND g.deleted_at IS NULL
                 WHERE c.status = 1
                 GROUP BY c.id
                 ORDER BY c.sort ASC, c.id ASC"
            );
            foreach ($mainRows as &$r) {
                $r['source'] = 'main';
            }
            unset($r);
        }

        // ---- 商户自建分类（仅商户上下文）----
        $merchantRows = [];
        if ($merchantId > 0) {
            $merchantRows = Database::query(
                "SELECT c.id, c.parent_id, c.name, '' AS slug, c.icon, '' AS cover_image,
                        COUNT(g.id) as goods_count
                 FROM {$prefix}merchant_category c
                 LEFT JOIN {$prefix}goods g ON g.category_id = c.id
                     AND g.category_source = 'merchant'
                     AND g.status = 1 AND g.is_on_sale = 1 AND g.deleted_at IS NULL
                 WHERE c.merchant_id = ? AND c.status = 1
                 GROUP BY c.id
                 ORDER BY c.sort ASC, c.id ASC",
                [$merchantId]
            );
            foreach ($merchantRows as &$r) {
                $r['source'] = 'merchant';
            }
            unset($r);
        }

        // ---- 分别建二级树（main / merchant 各自一棵），最后拼接 ----
        // id 在两套分类里可能撞号，必须按 source 隔离 map 索引
        $tree = array_merge(
            $this->buildCategoryTree($mainRows),
            $this->buildCategoryTree($merchantRows)
        );

        return [
            'goods_categories' => $tree,
            'popular_tags'     => GoodsTagModel::getPopularTags(20),
        ];
    }

    /**
     * 给一组同 source 的分类行构建二级树。父子关系仅在同 source 内成立。
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildCategoryTree(array $rows): array
    {
        $map = [];
        $tree = [];
        foreach ($rows as $row) {
            $row['children'] = [];
            $map[(int) $row['id']] = $row;
        }
        foreach ($map as $id => &$item) {
            $pid = (int) $item['parent_id'];
            if ($pid > 0 && isset($map[$pid])) {
                $map[$pid]['children'][] = &$item;
            } else {
                $tree[] = &$item;
            }
        }
        unset($item);
        return $tree;
    }

    /**
     * 获取博客侧边栏数据（分类二级树 + 热门标签）。
     *
     * @return array{blog_categories:array, popular_blog_tags:array}
     */
    protected function getBlogSidebarData(): array
    {
        $prefix = Database::prefix();
        $merchantId = MerchantContext::currentId();
        // 分类 + 各分类文章数（仅统计已发布未删除的文章），全部按当前 merchant_id 过滤
        $rows = Database::query(
            "SELECT c.id, c.parent_id, c.name, c.icon, COUNT(a.id) as article_count
             FROM {$prefix}blog_category c
             LEFT JOIN {$prefix}blog a ON a.category_id = c.id
                 AND a.status = 1 AND a.deleted_at IS NULL
                 AND a.merchant_id = ?
             WHERE c.status = 1 AND c.merchant_id = ?
             GROUP BY c.id
             ORDER BY c.sort ASC, c.id ASC",
            [$merchantId, $merchantId]
        );

        // 构建二级树
        $map = [];
        $tree = [];
        foreach ($rows as $row) {
            $row['children'] = [];
            $map[(int) $row['id']] = $row;
        }
        foreach ($map as $id => &$item) {
            $pid = (int) $item['parent_id'];
            if ($pid > 0 && isset($map[$pid])) {
                $map[$pid]['children'][] = &$item;
            } else {
                $tree[] = &$item;
            }
        }
        unset($item);

        return [
            'blog_categories'   => $tree,
            'popular_blog_tags' => BlogTagModel::getPopularTags(20, $merchantId),
        ];
    }

    /**
     * 生成商品库存的展示文字（stock_text 字段来源）。
     *
     * 默认行为：千分位格式化（如 1234567 → "1,234,567"）。
     * 过滤器 goods_stock_display 可重写展示结果（例如 fuzzy_stock 插件把数字改成"充足"等）。
     * 核心不感知任何插件；插件返回非字符串时自动 fallback 为默认千分位结果。
     *
     * @param int $stock 原始库存数量（整数）
     * @return string 展示文本
     */
    public static function formatStockText(int $stock): string
    {
        $default = number_format($stock);
        $filtered = applyFilter('goods_stock_display', $stock);
        return is_string($filtered) ? $filtered : $default;
    }

    /**
     * 划线价：仅当规格市场价大于该规格售价时返回。
     *
     * @param array<string, mixed>|null $spec
     */
    protected static function resolveOriginalPrice(?array $spec): ?float
    {
        if (!$spec || empty($spec['market_price'])) {
            return null;
        }
        $market = (float) $spec['market_price'];
        $price = (float) $spec['price'];
        return $market > $price ? $market : null;
    }

}
