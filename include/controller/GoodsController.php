<?php

declare(strict_types=1);

/**
 * 商品控制器。
 *
 * 方法说明：
 * - _index()  商城首页 → goods_index.php
 * - _list()   商品列表页 → goods_list.php
 * - _detail() 商品详情页 → goods.php
 */
class GoodsController extends BaseController
{
    /**
     * 商城首页。
     */
    public function _index(): void
    {
        $this->view->setTitle('');

        // 推荐商品（主区域）+ 最新商品 / 热门商品（侧边栏）
        $recommendedGoods = $this->queryGoodsList(['is_recommended' => true], 8);
        $recentGoods = $this->queryGoodsList([], 5, 'g.id DESC');
        $hotGoods = $this->queryGoodsList([], 5, 'total_sold DESC, g.id DESC');

        // 最新文章
        $recentArticles = $this->queryArticleList([], 3);

        // 侧边栏数据
        $sidebarData = $this->getGoodsSidebarData();

        $this->view->setData(array_merge([
            'recent_goods'      => $recentGoods,
            'hot_goods'         => $hotGoods,
            'recommended_goods' => $recommendedGoods,
            'recent_articles'   => $recentArticles,
            'announcement'      => $this->getCurrentAnnouncement(),
        ], $sidebarData));
        $this->view->render('goods_index');
    }

    /**
     * 商品列表页。
     */
    public function _list(): void
    {
        $categoryId = (int) $this->getArg('category_id', 0);
        $categorySource = (string) $this->getArg('category_source', 'main');
        if (!in_array($categorySource, ['main', 'merchant'], true)) $categorySource = 'main';
        $tagId = (int) $this->getArg('tag_id', 0);

        // 兼容 slug 路由：?c=goods_list&slug=xxx 或 pathinfo /goods_list/slug/xxx
        // 仅主站分类支持 slug；商户自建分类没有 slug 字段
        if ($categoryId <= 0) {
            $slug = trim((string) $this->getArg('slug', ''));
            if ($slug !== '' && preg_match('/^[a-zA-Z0-9_\-\p{Han}]+$/u', $slug)) {
                $row = Database::fetchOne(
                    'SELECT `id` FROM `' . Database::prefix() . 'goods_category` WHERE `slug` = ? AND `status` = 1 LIMIT 1',
                    [$slug]
                );
                if ($row) {
                    $categoryId = (int) $row['id'];
                    $categorySource = 'main';
                }
            }
        }

        // 获取分类列表（含商品计数，供分类 Tab 使用）
        $sidebarData = $this->getGoodsSidebarData();
        $categories = $sidebarData['goods_categories'] ?? [];

        // 根据分类筛选商品（选中父分类时包含所有子分类）
        $where = [];
        $title = '全部商品';

        // 标签筛选
        if ($tagId > 0) {
            $where['tag_id'] = $tagId;
            $tag = GoodsTagModel::getById($tagId);
            if ($tag) {
                $title = '标签：' . $tag['name'];
            }
        }
        if ($categoryId > 0) {
            $categoryIds = [$categoryId];
            // 在 sidebar 数据里找匹配的分类节点 —— 必须 id + source 都对（两套分类 id 可能撞号）
            foreach ($categories as $cat) {
                if ((int) $cat['id'] === $categoryId && (string) ($cat['source'] ?? 'main') === $categorySource) {
                    $title = $cat['name'];
                    foreach ($cat['children'] ?? [] as $child) {
                        $categoryIds[] = (int) $child['id'];
                    }
                    break;
                }
                foreach ($cat['children'] ?? [] as $child) {
                    if ((int) $child['id'] === $categoryId && (string) ($child['source'] ?? 'main') === $categorySource) {
                        $title = $child['name'];
                        break 2;
                    }
                }
            }
            $where['category_ids'] = $categoryIds;
            $where['category_source'] = $categorySource;
        }
        $page = max(1, (int) $this->getArg('page', 1));
        $sort = trim((string) $this->getArg('sort', 'default'));
        if (!in_array($sort, ['default', 'hot', 'sold', 'price_asc', 'price_desc'], true)) {
            $sort = 'default';
        }
        $orderBy = $this->resolveGoodsListOrderBy($sort);
        $result = $this->queryGoodsListPaginated($where, $page, 20, $orderBy);
        $result['list'] = applyFilter('index_goods_list', $result['list'], 'list');

        $this->view->setTitle($title);
        $this->view->setData([
            'goods_list'               => $result['list'],
            'goods_categories'         => $categories,
            'current_category'         => $categoryId,
            'current_category_source'  => $categorySource,
            'current_tag'              => $tagId,
            'goods_sort'               => $sort,
            'pagination'               => $result,
            'announcement'             => $this->getCurrentAnnouncement(),
        ]);
        $this->view->render('goods_list');
    }

    /**
     * 商品详情页。
     */
    public function _detail(): void
    {
        $id = (int) $this->getArg('id', 0);

        $goods = null;
        $specs = [];
        $specDims = [];
        $specsJson = '[]';

        if ($id > 0) {
            $row = GoodsModel::getById($id);
            // 作用域过滤：主站前台只看 owner_id=0；商户前台只看本店自建或主站引用
            if ($row && !MerchantContext::isGoodsVisibleToCurrentScope($row)) {
                $row = null;
            }
            // 跳转链接：非空时直接 302 跳出（"类似广告"语义，详情页本身不展示）。
            // 加 _preview=1 query 可绕过，方便后台预览编辑效果。
            if ($row
                && (int) $row['status'] === 1
                && $row['deleted_at'] === null
                && (int) $row['is_on_sale'] === 1
                && !empty($row['jump_url'])
                && (int) $this->getArg('_preview', 0) !== 1
            ) {
                Response::redirect((string) $row['jump_url']);
                return;
            }
            // 仅展示已上架、未删除的商品
            if ($row && (int) $row['status'] === 1 && $row['deleted_at'] === null && (int) $row['is_on_sale'] === 1) {
                // 获取所有规格（价格已自动转换）
                $rawSpecs = GoodsModel::getSpecsByGoodsId($id);
                $defaultSpec = null;
                foreach ($rawSpecs as $s) {
                    if ((int) $s['is_default'] === 1) {
                        $defaultSpec = $s;
                        break;
                    }
                }
                if (!$defaultSpec && !empty($rawSpecs)) {
                    $defaultSpec = $rawSpecs[0];
                }

                // 预加载 combo 数据（spec_id → value_ids 映射）
                $comboMap = [];
                $combos = Database::query(
                    "SELECT spec_id, value_ids FROM " . Database::prefix() . "goods_spec_combo WHERE goods_id = ?",
                    [$id]
                );
                foreach ($combos as $c) {
                    $comboMap[(int) $c['spec_id']] = json_decode($c['value_ids'], true) ?: [];
                }

                // 构建前端用的规格数据
                // stock 是原始整数（供 JS 业务判断），stock_text 是展示文字（默认千分位，可被插件替换）
                foreach ($rawSpecs as $s) {
                    $stockInt = (int) $s['stock'];
                    // tags：DB 存 JSON 数组（如 ["热卖","新品"]），后台保存时已 json_encode；
                    // 前端切换规格后在规格区上方展示这些徽章
                    $tagList = [];
                    if (!empty($s['tags'])) {
                        $decoded = is_string($s['tags']) ? json_decode($s['tags'], true) : $s['tags'];
                        if (is_array($decoded)) {
                            $tagList = array_values(array_filter(array_map('strval', $decoded), 'strlen'));
                        }
                    }
                    $specs[] = [
                        'id'           => (int) $s['id'],
                        'name'         => $s['name'],
                        'price'        => (float) $s['price'],
                        'market_price' => $s['market_price'] ? (float) $s['market_price'] : null,
                        'stock'        => $stockInt,
                        'stock_text'   => self::formatStockText($stockInt),
                        'sold_count'   => (int) ($s['sold_count'] ?? 0),
                        'min_buy'      => (int) ($s['min_buy'] ?? 1),
                        'max_buy'      => (int) ($s['max_buy'] ?? 0),
                        'is_default'   => (int) $s['is_default'],
                        'value_ids'    => $comboMap[(int) $s['id']] ?? [],
                        'tags'         => $tagList,
                    ];
                }

                // 获取多维规格数据（维度 + 维度值）
                // 注意：tags 不会被注入到 dim.value 上 —— 因为 tags 是"规格组合行"级别（如"红+S"），
                // 把它并集到维度值（如"红"）会让按钮挂上其它组合的标签，语义不准。
                // 多维度场景下 tags 由 JS 在用户选完规格组合后，按当前 spec.tags 渲染到 #specTags 容器。
                $specDims = $this->getSpecDims($id);

                // 规格 JSON（供 JS 切换价格/库存）
                $specsJson = json_encode($specs, JSON_UNESCAPED_UNICODE);

                // 获取分类名
                $categoryName = '';
                if ((int) $row['category_id'] > 0) {
                    $catModel = new GoodsCategoryModel();
                    $cat = $catModel->findById((int) $row['category_id']);
                    $categoryName = $cat ? $cat['name'] : '';
                }

                // 递增浏览量
                Database::execute(
                    "UPDATE " . Database::prefix() . "goods SET views_count = views_count + 1 WHERE id = ?",
                    [$id]
                );

                $covers = json_decode($row['cover_images'] ?? '[]', true) ?: [];
                // 解析商品配置（满减等）
                $configs = json_decode($row['configs'] ?? '{}', true) ?: [];
                $defaultDeliveryType = 'manual';
                if (!empty($row['goods_type']) && class_exists('GoodsTypeManager')) {
                    $typeCfg = GoodsTypeManager::getTypeConfig((string) $row['goods_type']);
                    if ($typeCfg && !empty($typeCfg['delivery_type'])) {
                        $defaultDeliveryType = (string) $typeCfg['delivery_type'];
                    }
                }
                $deliveryType = applyFilter('goods_delivery_type', $defaultDeliveryType, $row);

                // 多规格：首屏「库存」展示各规格库存之和（与 goods.total_stock 缓存一致）；单规格仍用该行库存
                $multiSpec = count($rawSpecs) > 1;
                $stock = $multiSpec
                    ? (int) ($row['total_stock'] ?? 0)
                    : ($defaultSpec ? (int) $defaultSpec['stock'] : (int) ($row['total_stock'] ?? 0));
                $goods = [
                    'id'             => (int) $row['id'],
                    'name'           => $row['title'],
                    'goods_type'     => (string) ($row['goods_type'] ?? ''),
                    'delivery_type'  => $deliveryType,
                    'image'          => $covers[0] ?? '',
                    'images'         => $covers,
                    'price'          => $defaultSpec ? (float) $defaultSpec['price'] : (float) $row['min_price'],
                    'original_price' => ($defaultSpec && $defaultSpec['market_price'])
                        ? (float) $defaultSpec['market_price']
                        : null,
                    // stock 是业务数字（整数），stock_text 是展示文字（默认千分位，可被插件替换）
                    'stock'          => $stock,
                    'stock_text'     => self::formatStockText($stock),
                    'min_buy'        => $defaultSpec ? (int) ($defaultSpec['min_buy'] ?? 1) : 1,
                    'max_buy'        => $defaultSpec ? (int) ($defaultSpec['max_buy'] ?? 0) : 0,
                    'category'       => $categoryName,
                    'sku'            => $row['code'],
                    'description'    => $row['intro'] ?: '',
                    'content'        => $row['content'] ?: '',
                    'tags'           => GoodsTagModel::getTagsByGoodsId($id),
                    'configs'        => $configs,
                    'unit'           => $row['unit'] ?: '件',
                    'total_sold'     => array_sum(array_column($specs, 'sold_count')),
                ];
            }
        }

        // 构建详情页表单字段（附加选项 + 查单模式），视图直接遍历渲染即可
        // 未登录用户才会拿到查单模式字段；登录用户订单天然与账户关联，无需游客查单
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $isGuest = empty($_SESSION['em_front_user']);

        // 获取支付方式，并在控制器里预先标记禁用态和默认选中项（视图直接按标记渲染）
        // 规则：
        //   - 余额支付 + 未登录 → disabled（不可选），避免前端错误提交
        //   - 默认选中：第一个 disabled=false 的方式
        $paymentMethods = PaymentService::getMethods();
        $defaultAssigned = false;
        foreach ($paymentMethods as &$pm) {
            $pm['disabled'] = ($pm['code'] === 'balance' && $isGuest);
            $pm['selected'] = false;
            if (!$defaultAssigned && !$pm['disabled']) {
                $pm['selected'] = true;
                $defaultAssigned = true;
            }
        }
        unset($pm);
        $formSections = $goods
            ? $this->buildDetailFormSections($goods['configs'] ?? [], $isGuest)
            : [];

        // 本商品是否要求收货地址（由商品类型插件在 goods_type_register 里声明 needs_address=true）
        // 模板 / JS 据此在"立即购买"时弹出地址选择或手填表单；不需要时保持现有流程不变
        $needsAddress = false;
        if ($goods && !empty($goods['goods_type']) && class_exists('GoodsTypeManager')) {
            $typeCfg = GoodsTypeManager::getTypeConfig((string) $goods['goods_type']);
            $needsAddress = !empty($typeCfg['needs_address']);
            $needsAddress = (bool) applyFilter('goods_needs_address', $needsAddress, $goods);
        }
        // 登录用户预拉地址簿，省一次 AJAX；游客走前端手填不预拉
        $userAddresses = [];
        $defaultAddressId = 0;
        $buyerId = (int) ($GLOBALS['frontUser']['id'] ?? $_SESSION['em_front_user']['id'] ?? 0);
        if ($needsAddress && $buyerId > 0) {
            $userAddresses = UserAddressModel::listByUserId($buyerId);
            foreach ($userAddresses as $addr) {
                if ((int) ($addr['is_default'] ?? 0) === 1) {
                    $defaultAddressId = (int) $addr['id'];
                    break;
                }
            }
            if ($defaultAddressId === 0 && !empty($userAddresses)) {
                $defaultAddressId = (int) $userAddresses[0]['id'];
            }
        }

        $this->view->setTitle($goods ? $goods['name'] : '商品详情');
        $this->view->setData([
            'goods'            => $goods,
            'specs'            => $specs,
            'spec_dims'        => $specDims,
            'specs_json'       => $specsJson,
            'payment_methods'  => $paymentMethods,
            'form_sections'    => $formSections,
            'needs_address'    => $needsAddress,
            'user_addresses'   => $userAddresses,
            'default_address_id' => $defaultAddressId,
        ]);
        $this->view->render('goods');
    }

    /**
     * 构建商品详情页用的表单字段数据。
     *
     * 输出一个扁平 section 数组，供任意模板直接遍历渲染；
     * 把"附加选项映射、查单模式字段合并、登录态判断"等逻辑集中在控制器，
     * 模板只需关心样式，便于新模板复用。
     *
     * 顺序：附加选项 → 查单模式（仅未登录时）
     *
     * 每个 section 结构：
     *   [
     *     'id'     => string  section 容器 id（空字符串表示不需要外层容器）
     *     'group'  => 'extra' | 'guest_find_contact' | 'guest_find_password'
     *     'fields' => array<field>
     *   ]
     *
     * 每个 field 结构：
     *   [
     *     'name'        => string  input name
     *     'id'          => string  input id（可选）
     *     'label'       => string  标签文字
     *     'type'        => string  HTML input type
     *     'placeholder' => string
     *     'required'    => bool
     *     'maxlength'   => int
     *     'hidden'      => [ 'id' => ..., 'name' => ..., 'value' => ... ]?  伴随 hidden
     *   ]
     */
    private function buildDetailFormSections(array $configs, bool $isGuest): array
    {
        $sections = [];

        // —— 附加选项（商品 configs.extra_fields）
        $extraFields = [];
        foreach (($configs['extra_fields'] ?? []) as $ef) {
            $name = (string) ($ef['name'] ?? '');
            if ($name === '') continue;

            // format → HTML input type + maxlength 默认值
            $format = $ef['format'] ?? 'text';
            $type = 'text';
            $maxLen = 64;
            if ($format === 'email') { $type = 'email'; }
            elseif ($format === 'phone') { $type = 'tel'; $maxLen = 20; }
            elseif ($format === 'number') { $type = 'number'; $maxLen = 20; }

            $extraFields[] = [
                'name'        => 'extra_' . $name,
                'id'          => '',
                'label'       => (string) ($ef['title'] ?? $name),
                'type'        => $type,
                'placeholder' => (string) ($ef['placeholder'] ?? ''),
                'required'    => !empty($ef['required']),
                'maxlength'   => $maxLen,
            ];
        }
        if ($extraFields) {
            $sections[] = [
                'id'     => '',
                'group'  => 'extra',
                'fields' => $extraFields,
            ];
        }

        // —— 查单模式字段（仅未登录用户）
        if ($isGuest) {
            $gf = GuestFindModel::getConfig();

            if (!empty($gf['contact_enabled'])) {
                $sections[] = [
                    'id'     => 'guestFindContactSection',
                    'group'  => 'guest_find_contact',
                    'fields' => [
                        [
                            'name'        => 'guest_find_contact_query',
                            'id'          => 'guestFindContactQuery',
                            'label'       => (string) $gf['contact_type_label'],
                            'type'        => (string) $gf['contact_input_type'],
                            'placeholder' => (string) $gf['contact_checkout_placeholder'],
                            'required'    => true,
                            'maxlength'   => 32,
                            // 伴随 hidden：记录当前联系方式类型（供 JS 读取）
                            'hidden'      => [
                                'id'    => 'guestFindContactType',
                                'name'  => '',
                                'value' => (string) $gf['contact_type'],
                            ],
                        ],
                    ],
                ];
            }
            if (!empty($gf['password_enabled'])) {
                $sections[] = [
                    'id'     => 'guestFindPasswordSection',
                    'group'  => 'guest_find_password',
                    'fields' => [
                        [
                            'name'        => 'guest_find_password_query',
                            'id'          => 'guestFindPasswordQuery',
                            'label'       => '订单密码',
                            // 下单时"设置"订单密码，用明文 text 便于用户确认输入
                            'type'        => 'text',
                            'placeholder' => (string) $gf['password_checkout_placeholder'],
                            'required'    => true,
                            'maxlength'   => 32,
                        ],
                    ],
                ];
            }
        }

        return $sections;
    }

    /**
     * 获取商品的多维规格维度及其维度值。
     *
     * @return array<array{id:int, name:string, values:array}>
     */
    private function getSpecDims(int $goodsId): array
    {
        $prefix = Database::prefix();

        $dims = Database::query(
            "SELECT id, name FROM {$prefix}goods_spec_dim WHERE goods_id = ? ORDER BY sort ASC, id ASC",
            [$goodsId]
        );

        if (empty($dims)) {
            return [];
        }

        $values = Database::query(
            "SELECT id, dim_id, name FROM {$prefix}goods_spec_value WHERE goods_id = ? ORDER BY sort ASC, id ASC",
            [$goodsId]
        );

        // 按维度分组
        $valuesByDim = [];
        foreach ($values as $v) {
            $valuesByDim[(int) $v['dim_id']][] = [
                'id'   => (int) $v['id'],
                'name' => $v['name'],
            ];
        }

        $result = [];
        foreach ($dims as $d) {
            $dimId = (int) $d['id'];
            // 仅返回有维度值的维度
            if (!empty($valuesByDim[$dimId])) {
                $result[] = [
                    'id'     => $dimId,
                    'name'   => $d['name'],
                    'values' => $valuesByDim[$dimId],
                ];
            }
        }
        return $result;
    }
}
