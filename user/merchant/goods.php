<?php

declare(strict_types=1);

require_once __DIR__ . '/global.php';
require_once EM_ROOT . '/include/model/MerchantGoodsRefModel.php';
require_once EM_ROOT . '/include/model/UserLevelModel.php';

/**
 * 商户后台 - 商品管理
 *
 * Tabs：
 *   ref  主站商品（所有主站商品默认可见；商户可覆盖加价率 / 上下架）
 *   self 自建商品（owner_id = 商户主 user_id）
 *
 * 主站商品 = 所有 owner_id=0 且 status=1 的商品，无需主站推送；
 *   em_goods_merchant_ref 作为"商户覆盖层"存在：商户修改加价率 / 下架时才 UPSERT 一行，
 *   没行时按默认（加价率 0、上架中）处理。
 *
 * 价格计算约定（与 a 系统文档/分站功能方案.md §6.2 一致）：
 *   拿货价 C = M × d_user（M=主站原价，d_user=商户主的用户等级折扣率；折扣存在 user_levels.discount，小数）
 *   店内售价 P = C × (1 + u)（u=加价率，markup_rate / 10000）
 */
merchantRequireLogin();

$merchantId = (int) $currentMerchant['id'];
$ownerUserId = (int) $currentMerchant['user_id'];

/**
 * 取指定用户的等级折扣率（小数）。
 *
 * user_levels.discount 语义：9.9 表示"9.9 折"（最终价 = 原价 × 9.9 / 10 = 0.99）；
 * UserLevelModel 存储时 × 1000000（float → int）。
 * 因此 d = (discount_raw / 1000000) / 10。
 *
 * 用户未设置等级（em_user.level_id = 0）或等级被禁用 → 返回 1.0（不打折）。
 */
function mcResolveDiscountRate(int $userId): float
{
    static $cache = [];
    if (isset($cache[$userId])) return $cache[$userId];

    $userTable = Database::prefix() . 'user';
    $levelTable = Database::prefix() . 'user_levels';
    $row = Database::fetchOne(
        'SELECT ul.`discount` AS d
           FROM `' . $userTable . '` u
      LEFT JOIN `' . $levelTable . '` ul ON ul.`id` = u.`level_id` AND ul.`enabled` = \'y\'
          WHERE u.`id` = ? LIMIT 1',
        [$userId]
    );
    $raw = (int) ($row['d'] ?? 0);
    if ($raw <= 0) {
        return $cache[$userId] = 1.0;
    }
    $rate = ($raw / 1000000) / 10;
    if ($rate <= 0 || $rate > 1) {
        $rate = 1.0;
    }
    return $cache[$userId] = $rate;
}

/**
 * 计算主站商品在本店的拿货价 / 售价（×1000000 整数返回）。
 */
function mcCalcRefPrices(int $basePrice, int $markupRate, float $discountRate): array
{
    $cost = (int) round($basePrice * $discountRate);
    $sell = (int) round($cost * (1 + $markupRate / 10000));
    return ['cost' => $cost, 'sell' => $sell];
}

/**
 * 商户对某主站商品的覆盖设置做 UPSERT；没行就新建，有行就更新。
 *
 * @param int|null $isRecommended 推荐覆盖；null = 跟随主站（不覆盖）、1/0 = 显式覆盖
 */
function mcUpsertGoodsRef(int $merchantId, int $goodsId, int $markupRate, int $isOnSale, ?int $isRecommended = null): void
{
    $refTable = Database::prefix() . 'goods_merchant_ref';
    $existing = Database::fetchOne(
        'SELECT `id` FROM `' . $refTable . '` WHERE `goods_id` = ? AND `merchant_id` = ? LIMIT 1',
        [$goodsId, $merchantId]
    );
    $payload = [
        'markup_rate'    => $markupRate,
        'is_on_sale'     => $isOnSale,
        'is_recommended' => $isRecommended,
    ];
    if ($existing !== null) {
        Database::update('goods_merchant_ref', $payload, (int) $existing['id']);
    } else {
        Database::insert('goods_merchant_ref', array_merge([
            'merchant_id' => $merchantId,
            'goods_id'    => $goodsId,
            'sort'        => 0,
            'pushed_at'   => date('Y-m-d H:i:s'),
        ], $payload));
    }
}

if (Request::isPost()) {
    try {
        // _action 允许从 POST body 或 URL query 读，兼容"URL 带 ?_action=xxx, body 只放业务字段"的调用形式
        $action = (string) (Input::post('_action', '') !== '' ? Input::post('_action', '') : Input::get('_action', ''));
        // 只读类 action 免 CSRF：列表查询 + 规格拉取 + 插件表单渲染
        $readonlyActions = ['list_ref', 'list_self', 'get_specs_json', 'get_plugin_form'];
        if (!in_array($action, $readonlyActions, true)) {
            if (!Csrf::validate((string) Input::post('csrf_token', ''))) {
                Response::error('请求已失效，请刷新页面后重试');
            }
        }

        switch ($action) {
            // ==================== 主站商品 ====================
            case 'list_ref': {
                $keyword = trim((string) Input::post('keyword', ''));
                $isOnSale = Input::post('is_on_sale', '');
                $page = max(1, (int) Input::post('page', 1));
                $pageSize = max(1, min(100, (int) Input::post('limit', 20)));
                $offset = ($page - 1) * $pageSize;

                // 商户默认加价率（万分位）—— ref 行没设 markup 时取这个
                $defaultMarkup = (int) ($currentMerchant['default_markup_rate'] ?? 1000);

                $refTable = Database::prefix() . 'goods_merchant_ref';
                $goodsTable = Database::prefix() . 'goods';

                // 默认：所有主站商品都显示；em_goods_merchant_ref 只是商户覆盖层（可能没行）
                $conds = ['g.deleted_at IS NULL', 'g.status = 1', 'g.owner_id = 0'];
                $params = [$merchantId]; // 给 LEFT JOIN 用

                if ($keyword !== '') {
                    $conds[] = '(g.title LIKE ? OR g.code LIKE ?)';
                    $params[] = '%' . $keyword . '%';
                    $params[] = '%' . $keyword . '%';
                }
                if ($isOnSale !== '') {
                    // 默认视为上架（ref 没行就等于 1）
                    $conds[] = 'COALESCE(r.is_on_sale, 1) = ?';
                    $params[] = (int) $isOnSale;
                }
                $whereSql = 'WHERE ' . implode(' AND ', $conds);
                $joinSql = 'LEFT JOIN `' . $refTable . '` r ON r.goods_id = g.id AND r.merchant_id = ?';

                $countRow = Database::fetchOne(
                    'SELECT COUNT(*) AS c FROM `' . $goodsTable . '` g ' . $joinSql . ' ' . $whereSql,
                    $params
                );
                $total = (int) ($countRow['c'] ?? 0);

                // g.min_price 是主站"原价"（×1000000）；实际拿货价以 d_user 计算
                // 加价率默认取商户 default_markup_rate，推荐默认跟随主站 g.is_recommended
                $sql = 'SELECT g.id AS goods_id,
                               r.id AS ref_id,
                               COALESCE(r.markup_rate, ?) AS markup_rate,
                               COALESCE(r.is_on_sale, 1) AS is_on_sale,
                               r.is_recommended AS ref_is_recommended,
                               g.is_recommended AS goods_is_recommended,
                               COALESCE(r.is_recommended, g.is_recommended) AS is_recommended,
                               g.title, g.code, g.cover_images, g.goods_type, g.min_price, g.max_price
                          FROM `' . $goodsTable . '` g ' . $joinSql . ' ' . $whereSql . '
                         ORDER BY g.id DESC
                         LIMIT ' . $pageSize . ' OFFSET ' . $offset;
                // 把 defaultMarkup 插在参数最前（COALESCE 的 ? 位于 SELECT 子句，MySQL 按顺序绑定）
                array_unshift($params, $defaultMarkup);
                $rows = Database::query($sql, $params);

                $discountRate = mcResolveDiscountRate($ownerUserId);

                foreach ($rows as &$row) {
                    $basePrice = (int) $row['min_price'];
                    $maxBasePrice = (int) ($row['max_price'] ?? 0);
                    $calc = mcCalcRefPrices($basePrice, (int) $row['markup_rate'], $discountRate);
                    // 所有金额按访客当前展示币种输出完整字符串（含币种符号），前端直接拼接
                    $row['base_price_view'] = Currency::displayAmount($basePrice);
                    $row['cost_view'] = Currency::displayAmount($calc['cost']);
                    $row['sell_view'] = Currency::displayAmount($calc['sell']);
                    if ($maxBasePrice > $basePrice) {
                        $maxCalc = mcCalcRefPrices($maxBasePrice, (int) $row['markup_rate'], $discountRate);
                        $row['max_base_price_view'] = Currency::displayAmount($maxBasePrice);
                        $row['max_cost_view'] = Currency::displayAmount($maxCalc['cost']);
                        $row['max_sell_view'] = Currency::displayAmount($maxCalc['sell']);
                    }
                    $row['markup_rate_view'] = rtrim(rtrim(number_format(((int) $row['markup_rate']) / 100, 2, '.', ''), '0'), '.');
                    $row['cover_image'] = '';
                    if (!empty($row['cover_images'])) {
                        $imgs = json_decode((string) $row['cover_images'], true) ?: [];
                        $row['cover_image'] = $imgs[0] ?? '';
                    }
                    unset($row['cover_images']);
                }
                unset($row);

                Response::success('', [
                    'data' => $rows,
                    'total' => $total,
                    'discount_rate' => $discountRate,
                    'csrf_token' => Csrf::token(),
                ]);
                break;
            }

            case 'update_ref': {
                // 按 goods_id 做 UPSERT：没有覆盖行就新建，有就改
                $goodsId = (int) Input::post('goods_id', 0);
                if ($goodsId <= 0) Response::error('参数错误');

                $goodsRow = Database::fetchOne(
                    'SELECT id FROM `' . Database::prefix() . 'goods`
                     WHERE id = ? AND owner_id = 0 AND status = 1 AND deleted_at IS NULL LIMIT 1',
                    [$goodsId]
                );
                if ($goodsRow === null) Response::error('商品不存在或已下架');

                // 加价率以百分比输入（如 20 表示 20%），存万分位
                $markupPct = (float) Input::post('markup_rate', 0);
                if ($markupPct < 0 || $markupPct > 1000) {
                    Response::error('加价率需在 0 ~ 1000%%');
                }
                $markupRate = (int) round($markupPct * 100);
                $isOnSale = (int) Input::post('is_on_sale', 0) === 1 ? 1 : 0;

                // 推荐覆盖：'inherit' = NULL，'1' = 强制推荐，'0' = 强制不推荐
                $recInput = (string) Input::post('is_recommended', 'inherit');
                $isRecommended = null;
                if ($recInput === '1')      $isRecommended = 1;
                elseif ($recInput === '0')  $isRecommended = 0;

                mcUpsertGoodsRef($merchantId, $goodsId, $markupRate, $isOnSale, $isRecommended);

                Response::success('已保存', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            case 'toggle_ref_sale': {
                // 按 goods_id 切换上下架：没覆盖行就新建一行，状态翻转
                $goodsId = (int) Input::post('goods_id', 0);
                if ($goodsId <= 0) Response::error('参数错误');

                $defaultMarkup = (int) ($currentMerchant['default_markup_rate'] ?? 1000);

                $refTable = Database::prefix() . 'goods_merchant_ref';
                $existing = Database::fetchOne(
                    'SELECT `id`, `is_on_sale`, `markup_rate`, `is_recommended` FROM `' . $refTable . '`
                     WHERE `goods_id` = ? AND `merchant_id` = ? LIMIT 1',
                    [$goodsId, $merchantId]
                );
                // 默认 is_on_sale=1（无覆盖 = 上架中），翻转就是 0
                $currentOnSale = $existing ? (int) $existing['is_on_sale'] : 1;
                $newVal = $currentOnSale === 1 ? 0 : 1;
                // 仅切换上下架：markup 和 is_recommended 保留原值（没覆盖行则用商户默认值 / NULL）
                $markupRate = $existing ? (int) $existing['markup_rate'] : $defaultMarkup;
                $isRec = ($existing && $existing['is_recommended'] !== null) ? (int) $existing['is_recommended'] : null;

                mcUpsertGoodsRef($merchantId, $goodsId, $markupRate, $newVal, $isRec);

                Response::success('状态已更新', ['is_on_sale' => $newVal, 'csrf_token' => Csrf::refresh()]);
                break;
            }

            case 'reset_ref': {
                // 恢复为默认（删除覆盖行），下次 list 时会走 COALESCE 的默认值
                $goodsId = (int) Input::post('goods_id', 0);
                if ($goodsId <= 0) Response::error('参数错误');
                Database::execute(
                    'DELETE FROM `' . Database::prefix() . 'goods_merchant_ref`
                     WHERE `goods_id` = ? AND `merchant_id` = ?',
                    [$goodsId, $merchantId]
                );
                Response::success('已恢复默认', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            // ==================== 自建商品 ====================
            case 'list_self': {
                $keyword = trim((string) Input::post('keyword', ''));
                $isOnSale = Input::post('is_on_sale', '');
                $page = max(1, (int) Input::post('page', 1));
                $pageSize = max(1, min(100, (int) Input::post('limit', 20)));

                $where = ['owner_id' => $ownerUserId, 'status' => 1];
                if ($keyword !== '') $where['keyword'] = $keyword;
                if ($isOnSale !== '') $where['is_on_sale'] = (int) $isOnSale;

                $result = GoodsModel::getList($where, $page, $pageSize, 'g.sort ASC, g.id DESC');
                foreach ($result['list'] as &$row) {
                    $row['cover_image'] = '';
                    if (!empty($row['cover_images'])) {
                        $imgs = json_decode((string) $row['cover_images'], true) ?: [];
                        $row['cover_image'] = $imgs[0] ?? '';
                    }
                    unset($row['cover_images']);
                    // min_price / max_price 已被 moneyFromDb 转成主货币"X.XX"字符串；
                    // 再用 displayMain 换算到访客当前展示币种并拼符号
                    $row['min_price_view'] = Currency::displayMain((float) $row['min_price']);
                    $row['max_price_view'] = Currency::displayMain((float) $row['max_price']);
                    // 类型中文标签：走 goods_type_label filter（physical / virtual_card 都注册了）
                    // 没匹配 filter 则回落到原始 goods_type 字符串，避免空显示
                    $label = (string) applyFilter('goods_type_label', '', $row);
                    $row['type_label'] = $label !== '' ? $label : (string) ($row['goods_type'] ?? '');
                }
                unset($row);

                Response::success('', [
                    'data' => $result['list'],
                    'total' => $result['total'],
                    'csrf_token' => Csrf::token(),
                ]);
                break;
            }

            case 'toggle_self_sale': {
                $id = (int) Input::post('id', 0);
                $goods = GoodsModel::getById($id);
                if ($goods === null || (int) $goods['owner_id'] !== $ownerUserId) {
                    Response::error('商品不存在或无权限');
                }
                $newVal = (int) $goods['is_on_sale'] === 1 ? 0 : 1;
                GoodsModel::update($id, ['is_on_sale' => $newVal]);
                Response::success('状态已更新', ['is_on_sale' => $newVal, 'csrf_token' => Csrf::refresh()]);
                break;
            }

            case 'delete_self': {
                $id = (int) Input::post('id', 0);
                $goods = GoodsModel::getById($id);
                if ($goods === null || (int) $goods['owner_id'] !== $ownerUserId) {
                    Response::error('商品不存在或无权限');
                }
                GoodsModel::delete($id);
                // 自动同步标签计数（软删除后商品被排除）
                GoodsTagModel::refreshAllCounts();
                Response::success('删除成功', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            // 切换推荐态（本店内推荐；商户自建商品用）
            case 'toggle_self_recommend': {
                $id = (int) Input::post('id', 0);
                $goods = GoodsModel::getById($id);
                if ($goods === null || (int) $goods['owner_id'] !== $ownerUserId) {
                    Response::error('商品不存在或无权限');
                }
                $newVal = (int) $goods['is_recommended'] === 1 ? 0 : 1;
                GoodsModel::update($id, ['is_recommended' => $newVal]);
                Response::success('状态已更新', ['is_recommended' => $newVal, 'csrf_token' => Csrf::refresh()]);
                break;
            }

            // 克隆自建商品（复制主表行 + 规格；新建行 owner_id 保持为本商户主）
            case 'clone_self': {
                $id = (int) Input::post('id', 0);
                $goods = GoodsModel::getById($id);
                if ($goods === null || (int) $goods['owner_id'] !== $ownerUserId) {
                    Response::error('商品不存在或无权限');
                }
                // 直接复用 GoodsModel::clone（主站已有）；clone 出的新行需要校验 owner 继承
                if (!method_exists('GoodsModel', 'clone')) {
                    Response::error('克隆方法不可用');
                }
                $newId = (int) GoodsModel::{'clone'}($id);
                if ($newId <= 0) Response::error('克隆失败');
                // 兜底：强制把新商品的 owner_id 锁定到本商户主用户（即便 GoodsModel::clone 可能默认带旧 owner 也无碍）
                Database::update('goods', ['owner_id' => $ownerUserId, 'created_by' => $ownerUserId, 'source_type' => 'self'], $newId);
                Response::success('克隆成功', ['id' => $newId, 'csrf_token' => Csrf::refresh()]);
                break;
            }

            // 批量操作：只允许对本店自建商品操作；逐条校验 owner_id
            case 'batch_self': {
                $batchAction = (string) Input::post('batch_action', '');
                $idsRaw = Input::post('ids', []);
                if (is_string($idsRaw)) $idsRaw = json_decode($idsRaw, true) ?: [];
                if (!is_array($idsRaw) || $idsRaw === []) Response::error('请选择商品');
                $ids = array_values(array_unique(array_map('intval', $idsRaw)));

                $allowed = ['on_sale', 'off_sale', 'delete', 'recommend', 'unrecommend'];
                if (!in_array($batchAction, $allowed, true)) Response::error('未知批量动作');

                $failed = 0;
                foreach ($ids as $gid) {
                    if ($gid <= 0) { $failed++; continue; }
                    $g = GoodsModel::getById($gid);
                    if ($g === null || (int) $g['owner_id'] !== $ownerUserId) {
                        $failed++; continue;
                    }
                    try {
                        switch ($batchAction) {
                            case 'on_sale':      GoodsModel::update($gid, ['is_on_sale' => 1]); break;
                            case 'off_sale':     GoodsModel::update($gid, ['is_on_sale' => 0]); break;
                            case 'delete':       GoodsModel::delete($gid); break;
                            case 'recommend':    GoodsModel::update($gid, ['is_recommended' => 1]); break;
                            case 'unrecommend':  GoodsModel::update($gid, ['is_recommended' => 0]); break;
                        }
                    } catch (Throwable $e) { $failed++; }
                }

                // 涉及删除批量时同步刷新标签计数
                if ($batchAction === 'delete') {
                    GoodsTagModel::refreshAllCounts();
                }

                if ($failed === 0) {
                    Response::success('批量操作成功', ['csrf_token' => Csrf::refresh()]);
                }
                Response::error('批量操作部分失败（' . $failed . '/' . count($ids) . '）', ['csrf_token' => Csrf::refresh()]);
                break;
            }

            // ==================== 自建商品 - 全量保存 ====================
            // 与主站 admin/goods_edit.php 的 save action 逻辑对齐（规格/多维/configs/标签/类型钩子），
            // 差异点：
            //   - 身份：owner_id = 商户主 user_id；created_by = 商户主 user_id；source_type = 'self'
            //   - 分类：支持 category_source（main/merchant）两种来源
            //   - 编辑时校验 owner_id 归属，防止跨商户越权
            case 'save_self': {
                if ((int) ($merchantLevel['allow_self_goods'] ?? 0) !== 1) {
                    Response::error('当前商户等级不允许上架自建商品');
                }

                $id = (int) Input::post('id', 0);
                $title = trim((string) Input::post('title', ''));
                $code = trim((string) Input::post('code', ''));
                $category_id = (int) Input::post('category_id', 0);
                $category_source = (string) Input::post('category_source', 'main');
                $goods_type = trim((string) Input::post('goods_type', ''));
                $intro = trim((string) Input::post('intro', ''));
                $content = (string) Input::post('content', '');
                $cover_images = (string) Input::post('cover_images', '[]');
                $unit = trim((string) Input::post('unit', '件'));
                $sort = (int) Input::post('sort', 0);
                $is_top_home = isset($_POST['is_top_home']) ? 1 : 0;        // 商户是否允许置顶主站首页？按你之前全保留策略保留字段，但主站前台是否展示由主站策略决定
                $is_top_category = isset($_POST['is_top_category']) ? 1 : 0;
                $is_recommended = isset($_POST['is_recommended']) ? 1 : 0;
                $api_enabled = isset($_POST['api_enabled']) ? 1 : 0;
                $jump_url = trim((string) Input::post('jump_url', ''));

                // 编辑模式：校验 owner_id 归属
                $existingGoods = null;
                if ($id > 0) {
                    $existingGoods = GoodsModel::getById($id);
                    if ($existingGoods === null || (int) $existingGoods['owner_id'] !== $ownerUserId) {
                        Response::error('商品不存在或无权限');
                    }
                }

                // 附加选项（extra_fields）
                $extraFields = [];
                $rawExtraFields = $_POST['extra_fields'] ?? [];
                if (is_array($rawExtraFields)) {
                    foreach ($rawExtraFields as $idx => $field) {
                        if (!is_numeric($idx)) continue;
                        $fTitle = trim($field['title'] ?? '');
                        $fName = trim($field['name'] ?? '');
                        if ($fTitle === '' && $fName === '') continue;
                        if ($fTitle === '') Response::error('附加选项第' . ($idx + 1) . '行：字段名称不能为空');
                        if ($fName === '')  Response::error('附加选项第' . ($idx + 1) . '行：字段标识不能为空');
                        $extraFields[] = [
                            'title' => $fTitle,
                            'name' => $fName,
                            'placeholder' => trim($field['placeholder'] ?? ''),
                            'format' => $field['format'] ?? 'text',
                            'required' => !empty($field['required']) ? 1 : 0,
                        ];
                    }
                }

                // 满减规则
                $discountRules = [];
                $rawDiscountRules = $_POST['discount_rules'] ?? [];
                if (is_array($rawDiscountRules)) {
                    foreach ($rawDiscountRules as $idx => $rule) {
                        if (!is_numeric($idx)) continue;
                        $threshold = (float)($rule['threshold'] ?? 0);
                        $discount = (float)($rule['discount'] ?? 0);
                        if ($threshold > 0 && $discount > 0) {
                            $discountRules[] = [
                                'threshold' => GoodsModel::moneyToDb($threshold),
                                'discount' => GoodsModel::moneyToDb($discount),
                            ];
                        }
                    }
                }

                $configs = [];
                if (!empty($extraFields))   $configs['extra_fields'] = $extraFields;
                if (!empty($discountRules)) $configs['discount_rules'] = $discountRules;
                $rebate = [
                    'l1' => max(0, (int) Input::post('rebate_l1', 0)),
                    'l2' => max(0, (int) Input::post('rebate_l2', 0)),
                ];
                if (array_sum($rebate) > 0) $configs['rebate'] = $rebate;

                // 必填校验
                if (empty($goods_type))  Response::error('请选择商品类型');
                // 插件类型自定义校验
                $pluginData = $_POST['plugin_data'] ?? [];
                $pluginValidateError = applyFilter("goods_type_{$goods_type}_validate", '', $pluginData);
                if (!empty($pluginValidateError)) Response::error($pluginValidateError);

                if (empty($category_id)) Response::error('请选择商品分类');
                if (empty($title))       Response::error('商品标题不能为空');

                // 分类归属校验（main → em_goods_category，merchant → em_merchant_category 且 merchant_id 匹配本店）
                if (!in_array($category_source, ['main', 'merchant'], true)) Response::error('分类来源非法');
                if ($category_source === 'main') {
                    $catRow = Database::fetchOne(
                        'SELECT `id` FROM `' . Database::prefix() . 'goods_category` WHERE `id` = ? AND `status` = 1 LIMIT 1',
                        [$category_id]
                    );
                    if ($catRow === null) Response::error('主站分类无效');
                } else {
                    $catRow = Database::fetchOne(
                        'SELECT `id` FROM `' . Database::prefix() . 'merchant_category` WHERE `id` = ? AND `merchant_id` = ? LIMIT 1',
                        [$category_id, $merchantId]
                    );
                    if ($catRow === null) Response::error('本店分类无效');
                }

                $data = [
                    'title' => $title,
                    'code' => $code,
                    'category_id' => $category_id,
                    'category_source' => $category_source,
                    'goods_type' => $goods_type,
                    'unit' => $unit,
                    'intro' => $intro,
                    'content' => $content,
                    'cover_images' => $cover_images,
                    'configs' => !empty($configs) ? json_encode($configs, JSON_UNESCAPED_UNICODE) : null,
                    'sort' => $sort,
                    'is_top_home' => $is_top_home,
                    'is_top_category' => $is_top_category,
                    'is_recommended' => $is_recommended,
                    'api_enabled' => $api_enabled,
                    'jump_url' => $jump_url,
                ];

                // 新建时补身份字段
                if (!$id) {
                    $data['owner_id'] = $ownerUserId;
                    $data['created_by'] = $ownerUserId;
                    $data['source_type'] = 'self';
                }

                try {
                    if ($id) {
                        $result = GoodsModel::update($id, $data);
                        $goodsId = $id;
                    } else {
                        $goodsId = GoodsModel::create($data);
                        $result = $goodsId ? true : false;
                    }
                    if (!$result) Response::error('保存失败，请检查是否填写了所有必填项');

                    // 处理规格（逻辑与主站 admin/goods_edit.php 一致）
                    $specsInput = $_POST['specs'] ?? [];
                    $prefix = Database::prefix();
                    $specDimNameRaw = trim((string) Input::post('spec_dim_name', ''));
                    $specDimNames = $specDimNameRaw !== '' ? array_map('trim', explode('/', $specDimNameRaw)) : [];

                    $specRows = [];
                    foreach ($specsInput as $index => $spec) {
                        if (!is_numeric($index)) continue;
                        $name = trim($spec['name'] ?? '');
                        if ($name === '') {
                            if (!empty($spec['price']) && (float)$spec['price'] > 0) {
                                $spec['name'] = '默认规格';
                            } else {
                                continue;
                            }
                        }
                        $specRows[$index] = $spec;
                    }

                    if (!empty($specRows)) {
                        $dimCounts = [];
                        foreach ($specRows as $index => $spec) {
                            $parts = array_map('trim', explode('/', trim($spec['name'])));
                            $parts = array_filter($parts, function ($p) { return $p !== ''; });
                            $dimCounts[$index] = count($parts);
                        }
                        $uniqueCounts = array_unique(array_values($dimCounts));
                        if (count($uniqueCounts) > 1) {
                            $min = min($uniqueCounts);
                            $max = max($uniqueCounts);
                            Response::error("规格维度数量不一致：部分规格有{$min}个维度，部分有{$max}个维度。请确保所有规格使用相同数量的\"/\"分隔");
                        }
                        $dimCount = reset($uniqueCounts);
                    } else {
                        $dimCount = 1;
                    }

                    // 就地更新：按名称匹配（和主站保持一致）
                    $oldSpecMap = [];
                    $oldSpecs = Database::query(
                        "SELECT id, name, stock, sold_count FROM {$prefix}goods_spec WHERE goods_id = ?",
                        [$goodsId]
                    );
                    foreach ($oldSpecs as $os) {
                        $oldSpecMap[$os['name']] = [
                            'id'         => (int)$os['id'],
                            'stock'      => (int)$os['stock'],
                            'sold_count' => (int)$os['sold_count'],
                        ];
                    }

                    $newSpecNames = [];
                    foreach ($specRows as $spec) $newSpecNames[] = trim($spec['name']);

                    // DELETE 被移除的
                    foreach ($oldSpecMap as $oldName => $oldInfo) {
                        if (!in_array($oldName, $newSpecNames, true)) {
                            $oldSpecId = $oldInfo['id'];
                            Database::execute("DELETE FROM {$prefix}goods_spec_combo WHERE spec_id = ?", [$oldSpecId]);
                            Database::execute("DELETE FROM {$prefix}goods_price_level WHERE spec_id = ?", [$oldSpecId]);
                            Database::execute("DELETE FROM {$prefix}goods_price_user WHERE spec_id = ?", [$oldSpecId]);
                            try {
                                Database::execute(
                                    "UPDATE {$prefix}goods_virtual_card SET spec_id = NULL WHERE spec_id = ?",
                                    [$oldSpecId]
                                );
                            } catch (Throwable $e) {}
                            Database::execute("DELETE FROM {$prefix}goods_spec WHERE id = ?", [$oldSpecId]);
                        }
                    }

                    // UPDATE/INSERT
                    $specIdMap = [];
                    foreach ($specRows as $index => $spec) {
                        $tags = null;
                        if (!empty($spec['tags'])) {
                            $tagList = array_map('trim', explode(',', $spec['tags']));
                            $tagList = array_filter($tagList);
                            if (!empty($tagList)) $tags = json_encode($tagList);
                        }
                        $specName = trim($spec['name']);
                        $updateData = [
                            'spec_no' => $spec['spec_no'] ?? '',
                            'price' => GoodsModel::moneyToDb($spec['price'] ?? 0),
                            'cost_price' => !empty($spec['cost_price']) ? GoodsModel::moneyToDb($spec['cost_price']) : null,
                            'market_price' => !empty($spec['market_price']) ? GoodsModel::moneyToDb($spec['market_price']) : null,
                            'tags' => $tags,
                            'min_buy' => isset($spec['min_buy']) && $spec['min_buy'] !== '' ? max(1, (int)$spec['min_buy']) : 1,
                            'max_buy' => isset($spec['max_buy']) && $spec['max_buy'] !== '' ? max(0, (int)$spec['max_buy']) : 0,
                            'sort' => (int)($spec['sort'] ?? 0),
                            'is_default' => isset($specsInput['is_default']) && $specsInput['is_default'] == $index ? 1 : 0,
                        ];

                        if (isset($oldSpecMap[$specName])) {
                            $existingSpecId = $oldSpecMap[$specName]['id'];
                            Database::update('goods_spec', $updateData, $existingSpecId);
                            $specIdMap[$index] = $existingSpecId;
                        } else {
                            $insertData = array_merge($updateData, [
                                'goods_id' => $goodsId,
                                'name' => $specName,
                                'stock' => 0,
                            ]);
                            $specIdMap[$index] = Database::insert('goods_spec', $insertData);
                        }
                    }

                    // 维度/维度值/组合重建
                    Database::execute("DELETE FROM {$prefix}goods_spec_combo WHERE goods_id = ?", [$goodsId]);
                    Database::execute("DELETE FROM {$prefix}goods_spec_value WHERE goods_id = ?", [$goodsId]);
                    Database::execute("DELETE FROM {$prefix}goods_spec_dim WHERE goods_id = ?", [$goodsId]);

                    if ($dimCount == 1 && !empty($specRows)) {
                        Database::insert('goods_spec_dim', [
                            'goods_id' => $goodsId,
                            'name' => $specDimNames[0] ?? '规格',
                            'sort' => 0,
                        ]);
                    }
                    if ($dimCount > 1 && !empty($specRows)) {
                        $dimValues = [];
                        $specDimParts = [];
                        foreach ($specRows as $index => $spec) {
                            $parts = array_map('trim', explode('/', trim($spec['name'])));
                            $specDimParts[$index] = $parts;
                            foreach ($parts as $dimIdx => $val) {
                                if (!isset($dimValues[$dimIdx])) $dimValues[$dimIdx] = [];
                                if (!in_array($val, $dimValues[$dimIdx])) $dimValues[$dimIdx][] = $val;
                            }
                        }
                        $valueIdMap = [];
                        for ($i = 0; $i < $dimCount; $i++) {
                            $dimId = Database::insert('goods_spec_dim', [
                                'goods_id' => $goodsId,
                                'name' => $specDimNames[$i] ?? ('规格' . ($i + 1)),
                                'sort' => $i,
                            ]);
                            foreach ($dimValues[$i] as $valSort => $valName) {
                                $valId = Database::insert('goods_spec_value', [
                                    'dim_id' => $dimId,
                                    'goods_id' => $goodsId,
                                    'name' => $valName,
                                    'sort' => $valSort,
                                ]);
                                $valueIdMap[$i . '|' . $valName] = $valId;
                            }
                        }
                        foreach ($specRows as $index => $spec) {
                            $parts = $specDimParts[$index];
                            $valueIds = [];
                            foreach ($parts as $dimIdx => $val) $valueIds[] = $valueIdMap[$dimIdx . '|' . $val];
                            Database::insert('goods_spec_combo', [
                                'goods_id' => $goodsId,
                                'spec_id' => $specIdMap[$index],
                                'combo_hash' => md5(implode('|', $valueIds)),
                                'combo_text' => trim($spec['name']),
                                'value_ids' => json_encode($valueIds),
                            ]);
                        }
                    }

                    // 兜底默认规格
                    if (empty($specRows) && !isset($oldSpecMap['默认规格'])) {
                        Database::insert('goods_spec', [
                            'goods_id' => $goodsId,
                            'name' => '默认规格',
                            'price' => 0,
                            'stock' => 0,
                            'is_default' => 1,
                            'status' => 1,
                        ]);
                    }

                    GoodsModel::updatePriceStockCache($goodsId);

                    // 商品标签（共用主站 em_goods_tag 标签池；商户标签命中相同名时自动合并）
                    $goodsTagsStr = trim((string) Input::post('goods_tags', ''));
                    $goodsTagNames = array_filter(array_map('trim', explode(',', $goodsTagsStr)));
                    $goodsTagIds = [];
                    foreach ($goodsTagNames as $tagName) {
                        if ($tagName !== '') $goodsTagIds[] = GoodsTagModel::findOrCreate($tagName);
                    }
                    GoodsTagModel::syncGoodsTags($goodsId, $goodsTagIds);
                    GoodsTagModel::refreshAllCounts();

                    // 触发商品类型保存钩子
                    $postData = $_POST;
                    doAction("goods_type_{$goods_type}_save", $goodsId, $postData);

                } catch (Throwable $e) {
                    $errorMsg = $e->getMessage();
                    if (preg_match("/Column '(\w+)' cannot be null/i", $errorMsg, $m)) {
                        Response::error('保存失败：字段「' . $m[1] . '」不能为空');
                    }
                    if (preg_match("/Field '(\w+)' doesn't have a default/i", $errorMsg, $m)) {
                        Response::error('保存失败：字段「' . $m[1] . '」未填写');
                    }
                    if (stripos($errorMsg, 'Duplicate entry') !== false) {
                        Response::error('保存失败：商品编码重复，请更换编码');
                    }
                    Response::error('保存失败：' . $errorMsg);
                }

                Response::success('保存成功', ['id' => $goodsId, 'csrf_token' => Csrf::refresh()]);
                break;
            }

            // 规格 JSON（编辑时 AJAX 刷新规格表用）—— 用 raw 价
            case 'get_specs_json': {
                $gid = (int) Input::post('id', (int) Input::get('id', 0));
                if ($gid <= 0) Response::error('商品ID不能为空');
                $g = GoodsModel::getById($gid, false);
                if ($g === null || (int) $g['owner_id'] !== $ownerUserId) Response::error('商品不存在或无权限');
                Response::success('', ['specs' => GoodsModel::getSpecsByGoodsId($gid, false)]);
                break;
            }

            // 删除单个规格（物理删）
            case 'remove_spec': {
                $specId = (int) Input::post('spec_id', 0);
                if ($specId <= 0) Response::error('规格ID不能为空');
                // 先校验规格所属商品归属本商户
                $specRow = Database::fetchOne(
                    'SELECT s.`goods_id`, g.`owner_id` FROM `' . Database::prefix() . 'goods_spec` s
                       LEFT JOIN `' . Database::prefix() . 'goods` g ON g.`id` = s.`goods_id`
                      WHERE s.`id` = ? LIMIT 1',
                    [$specId]
                );
                if ($specRow === null || (int) $specRow['owner_id'] !== $ownerUserId) {
                    Response::error('规格不存在或无权限');
                }
                $gid = (int) $specRow['goods_id'];
                Database::execute("DELETE FROM " . Database::prefix() . "goods_spec WHERE id = ?", [$specId]);
                Database::execute("DELETE FROM " . Database::prefix() . "goods_spec_combo WHERE spec_id = ?", [$specId]);
                if ($gid > 0) GoodsModel::updatePriceStockCache($gid);
                Response::success('删除成功', ['csrf_token' => Csrf::token()]);
                break;
            }

            // 保存规格库存（stock_manager 弹窗使用）
            case 'save_stock': {
                $gid = (int) Input::post('goods_id', 0);
                if ($gid <= 0) Response::error('商品ID不能为空');
                $g = GoodsModel::getById($gid);
                if ($g === null || (int) $g['owner_id'] !== $ownerUserId) Response::error('商品不存在或无权限');

                $specStocks = $_POST['spec_stock'] ?? [];
                if (is_array($specStocks)) {
                    foreach ($specStocks as $specId => $stock) {
                        Database::update('goods_spec', ['stock' => max(0, (int) $stock)], (int) $specId);
                    }
                }
                doAction("goods_type_{$g['goods_type']}_stock_save", $g, $_POST);
                GoodsModel::updatePriceStockCache($gid);
                Response::success('库存已保存', ['csrf_token' => Csrf::token()]);
                break;
            }

            // 商品类型插件表单 HTML（切换类型时 AJAX 加载）
            case 'get_plugin_form': {
                $gt = trim((string) Input::post('goods_type', ''));
                $gid = (int) Input::post('goods_id', 0);
                $g = null;
                if ($gid > 0) {
                    $g = GoodsModel::getById($gid);
                    // 如果传了 goods_id，校验归属（新建时通常为 0，跳过校验）
                    if ($g !== null && (int) $g['owner_id'] !== $ownerUserId) {
                        Response::error('商品不存在或无权限');
                    }
                }
                ob_start();
                doAction("goods_type_{$gt}_create_form", $g);
                $html = ob_get_clean();
                Response::success('', ['html' => $html, 'csrf_token' => Csrf::token()]);
                break;
            }

            default:
                Response::error('未知操作');
        }
    } catch (RuntimeException $e) {
        Response::error($e->getMessage());
    } catch (Throwable $e) {
        Response::error($e->getMessage() ?: '系统繁忙，请稍后再试');
    }
}

// ============================================================
// 弹窗：编辑主站商品（加价率 / 上下架）
// 主键已改成 goods_id：ref 行可能不存在，用 LEFT JOIN + COALESCE 取默认值
// ============================================================
if ((string) Input::get('_popup', '') === 'ref_edit') {
    $goodsId = (int) Input::get('goods_id', 0);
    $refTable = Database::prefix() . 'goods_merchant_ref';
    $goodsTable = Database::prefix() . 'goods';
    $defaultMarkup = (int) ($currentMerchant['default_markup_rate'] ?? 1000);
    $row = Database::fetchOne(
        'SELECT g.id AS goods_id, g.title, g.min_price, g.max_price,
                g.is_recommended AS goods_is_recommended,
                COALESCE(r.markup_rate, ?) AS markup_rate,
                COALESCE(r.is_on_sale, 1) AS is_on_sale,
                r.is_recommended AS ref_is_recommended,
                r.id AS ref_id
           FROM `' . $goodsTable . '` g
      LEFT JOIN `' . $refTable . '` r ON r.goods_id = g.id AND r.merchant_id = ?
          WHERE g.id = ? AND g.owner_id = 0 AND g.status = 1 AND g.deleted_at IS NULL
          LIMIT 1',
        [$defaultMarkup, $merchantId, $goodsId]
    );
    if ($row === null) {
        echo '<div style="padding:30px;text-align:center;color:#999">商品不存在或已下架</div>';
        return;
    }

    $discountRate = mcResolveDiscountRate($ownerUserId);
    $csrfToken = Csrf::token();
    $pageTitle = '编辑：' . ($row['title'] ?? '');
    include __DIR__ . '/view/popup/ref_edit.php';
    return;
}

// ============================================================
// 弹窗：库存管理（按商品类型插件的 stock_form 钩子渲染）
// ============================================================
if ((string) Input::get('_popup', '') === 'stock_manager') {
    $gid = (int) Input::get('id', 0);
    if ($gid <= 0) exit('商品ID不能为空');
    // 库存管理弹窗：用 raw 价，避免库存面板里展示的价被当前登录商户的买家折扣污染
    $goods = GoodsModel::getById($gid, false);
    if ($goods === null || (int) $goods['owner_id'] !== $ownerUserId) {
        exit('商品不存在或无权限');
    }
    $specs = GoodsModel::getSpecsByGoodsId($gid, false);

    $csrfToken = Csrf::token();
    $pageTitle = '库存管理';
    // 让 stock_form 插件里的 AJAX 把 save_stock 打回商户控制器，而不是 /admin/goods_edit.php
    $popupStockSaveUrl = '/user/merchant/goods.php?_action=save_stock';
    include EM_ROOT . '/admin/view/popup/header.php';
    include EM_ROOT . '/admin/view/popup/stock_manager.php';
    include EM_ROOT . '/admin/view/popup/footer.php';
    return;
}

// ============================================================
// 弹窗：新建 / 编辑 自建商品（全量版，视图复用 admin/view/popup/goods_edit.php 的完整结构）
// ============================================================
if ((string) Input::get('_popup', '') === 'self_edit') {
    if ((int) ($merchantLevel['allow_self_goods'] ?? 0) !== 1) {
        exit('当前商户等级不允许上架自建商品');
    }

    $editId = (int) Input::get('id', 0);
    // 变量命名与主站视图对齐（$goods / $specs / $specDimNames / $categories / $goodsTypes / $merchantCats）
    $goods = null;
    $specs = [];
    $specDimNames = [];
    if ($editId > 0) {
        // 编辑表单：用 raw 价，把"成交价配置"原值显示给商户，
        // 不能被当前登录商户账号的买家折扣污染（曾经报过 10 元变 9.9 元的 bug）
        $g = GoodsModel::getById($editId, false);
        if ($g === null || (int) $g['owner_id'] !== $ownerUserId) {
            exit('商品不存在或无权限');
        }
        $goods = $g;
        $specs = GoodsModel::getSpecsByGoodsId($editId, false);
        $specDimNames = Database::query(
            'SELECT `name` FROM `' . Database::prefix() . 'goods_spec_dim` WHERE `goods_id` = ? ORDER BY `sort` ASC',
            [$editId]
        );
    }

    // 分类数据：本店自定义 + 主站
    $merchantCats = Database::query(
        'SELECT `id`, `name`, `parent_id` FROM `' . Database::prefix() . 'merchant_category`
          WHERE `merchant_id` = ? ORDER BY `sort` ASC, `id` ASC',
        [$merchantId]
    );
    $categories = Database::query(
        'SELECT * FROM `' . Database::prefix() . 'goods_category`
          WHERE `status` = 1 ORDER BY `parent_id` ASC, `sort` ASC'
    );
    $goodsTypes = GoodsTypeManager::getTypes();
    $isPopup = true;
    $isEdit = ($goods !== null && !empty($goods));

    $csrfToken = Csrf::token();
    $pageTitle = $isEdit ? '编辑商品' : '新建自建商品';
    include __DIR__ . '/view/popup/goods_edit_self.php';
    return;
}

// ============================================================
// 正常视图
// ============================================================
$tab = (string) Input::get('tab', 'ref');
if (!in_array($tab, ['ref', 'self'], true)) {
    $tab = 'ref';
}
$allowSelf = (int) ($merchantLevel['allow_self_goods'] ?? 0) === 1;

merchantRenderPage(__DIR__ . '/view/goods.php', [
    'currentTab' => $tab,
    'allowSelf' => $allowSelf,
]);
