<?php
declare(strict_types=1);

require __DIR__ . '/global.php';

// 后台登录校验
adminRequireLogin();

$action = $_GET['_action'] ?? $_POST['_action'] ?? '';

// 用户搜索（规格"用户专属价"弹窗内联搜索用）
// GET /admin/goods_edit.php?_action=search_users&q=keyword
// 按用户名 / 昵称 / 邮箱模糊匹配，仅 role=user 且 status=1，最多 10 条
if ($action === 'search_users') {
    $q = trim((string) Input::get('q', ''));
    if ($q === '' || mb_strlen($q) < 1) {
        Response::success('', ['list' => []]);
    }
    $like = '%' . $q . '%';
    try {
        $rows = Database::query(
            'SELECT `id`, `username`, `nickname`, `email`, `avatar`
               FROM `' . Database::prefix() . 'user`
              WHERE `role` = ? AND `status` = 1
                AND (`username` LIKE ? OR `nickname` LIKE ? OR `email` LIKE ? OR `id` = ?)
              LIMIT 10',
            ['user', $like, $like, $like, ctype_digit($q) ? (int) $q : -1]
        );
    } catch (Throwable $e) {
        $rows = [];
    }
    Response::success('', ['list' => $rows]);
}

// 获取商品类型插件表单（用于 AJAX 动态加载）
if ($action === 'get_plugin_form') {
    $goodsType = trim($_POST['goods_type'] ?? '');
    $goodsId = (int)($_POST['goods_id'] ?? 0);
    // 编辑面板的"按类型加载表单"钩子读 raw 价
    $goods = $goodsId ? GoodsModel::getById($goodsId, false) : null;

    ob_start();
    doAction("goods_type_{$goodsType}_create_form", $goods);
    $html = ob_get_clean();

    Response::success('', ['html' => $html, 'csrf_token' => Csrf::token()]);
}

// 物理删除单个规格
if ($action === 'remove_spec') {
    $specId = (int)($_POST['spec_id'] ?? 0);
    if (!$specId) {
        Response::error('规格ID不能为空');
    }
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!Csrf::validate($csrf)) {
        Response::error('请求已失效，请刷新页面后重试');
    }
    // 先查询该规格所属商品ID，用于后续更新缓存
    $specRow = Database::query("SELECT goods_id FROM " . Database::prefix() . "goods_spec WHERE id = ? LIMIT 1", [$specId]);
    $goodsId = !empty($specRow) ? (int)$specRow[0]['goods_id'] : 0;

    // 物理删除规格，同步清除 combo 映射记录和规格级专属价（等级/用户）
    $result = Database::execute("DELETE FROM " . Database::prefix() . "goods_spec WHERE id = ?", [$specId]);
    Database::execute("DELETE FROM " . Database::prefix() . "goods_spec_combo WHERE spec_id = ?", [$specId]);
    Database::execute("DELETE FROM " . Database::prefix() . "goods_price_level WHERE spec_id = ?", [$specId]);
    Database::execute("DELETE FROM " . Database::prefix() . "goods_price_user WHERE spec_id = ?", [$specId]);

    // 更新商品价格/库存缓存
    if ($goodsId > 0) {
        GoodsModel::updatePriceStockCache($goodsId);
    }

    if ($result !== false) {
        Response::success('删除成功', ['csrf_token' => Csrf::token()]);
    } else {
        Response::error('删除失败');
    }
}

// 保存商品
if ($action === 'save') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!Csrf::validate($csrf)) {
        Response::error('请求已失效，请刷新页面后重试');
    }
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $goods_type = trim($_POST['goods_type'] ?? '');
    $intro = trim($_POST['intro'] ?? '');
    $content = $_POST['content'] ?? '';
    $cover_images = $_POST['cover_images'] ?? '[]';
    $unit = trim((string) ($_POST['unit'] ?? ''));
    // 空单位默认"个"（按需求约定）
    if ($unit === '') $unit = '个';
    $sort = (int)($_POST['sort'] ?? 0);
    $is_top_home = isset($_POST['is_top_home']) ? 1 : 0;
    $is_top_category = isset($_POST['is_top_category']) ? 1 : 0;
    $is_recommended = isset($_POST['is_recommended']) ? 1 : 0;
    $api_enabled = isset($_POST['api_enabled']) ? 1 : 0;
    $jump_url = trim($_POST['jump_url'] ?? '');

    // 附加选项（自定义表单字段）
    $extraFields = [];
    $rawExtraFields = $_POST['extra_fields'] ?? [];
    if (is_array($rawExtraFields)) {
        foreach ($rawExtraFields as $idx => $field) {
            if (!is_numeric($idx)) continue;
            $fTitle = trim($field['title'] ?? '');
            $fName = trim($field['name'] ?? '');
            // 两个都空则跳过该行
            if ($fTitle === '' && $fName === '') continue;
            // 只填了一个则提示
            if ($fTitle === '') {
                Response::error('附加选项第' . ($idx + 1) . '行：字段名称不能为空');
            }
            if ($fName === '') {
                Response::error('附加选项第' . ($idx + 1) . '行：字段标识不能为空');
            }
            $extraFields[] = [
                'title' => $fTitle,
                'name' => $fName,
                'placeholder' => trim($field['placeholder'] ?? ''),
                'format' => $field['format'] ?? 'text',
                'required' => !empty($field['required']) ? 1 : 0,
            ];
        }
    }

    // 营销配置（满减规则，金额转为 BIGINT 存储）
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

    // 构建 configs JSON（存储附加选项、营销配置、返佣配置）
    $configs = [];
    if (!empty($extraFields)) {
        $configs['extra_fields'] = $extraFields;
    }
    if (!empty($discountRules)) {
        $configs['discount_rules'] = $discountRules;
    }
    // 返佣配置：前端按"百分比"输入（5 = 5%），落库转成万分位（500）
    // 空 = 未设置（不记该字段），0 = 明确"不返佣"（记 0），区分两种含义
    $rebate = [];
    $rebateL1Raw = trim((string) Input::post('rebate_l1', ''));
    $rebateL2Raw = trim((string) Input::post('rebate_l2', ''));
    $rebateL1Pct = 0.0; $rebateL2Pct = 0.0;
    if ($rebateL1Raw !== '') {
        $rebateL1Pct = max(0.0, min(100.0, (float) $rebateL1Raw));
        $rebate['l1'] = (int) round($rebateL1Pct * 100);
    }
    if ($rebateL2Raw !== '') {
        $rebateL2Pct = max(0.0, min(100.0, (float) $rebateL2Raw));
        $rebate['l2'] = (int) round($rebateL2Pct * 100);
    }
    // 总返佣上限校验：l1 + l2 不得超过订单金额的 30%
    if (($rebateL1Pct + $rebateL2Pct) > 30.0) {
        Response::error('返佣配置错误：一级 + 二级总返佣不得超过订单金额的 30%（当前 '
            . rtrim(rtrim(number_format($rebateL1Pct + $rebateL2Pct, 2), '0'), '.')
            . '%）');
    }
    // 计算方式：amount / profit / 空串（= 跟随系统）
    $rebateMode = (string) Input::post('rebate_mode', '');
    if (!in_array($rebateMode, ['amount', 'profit', ''], true)) {
        $rebateMode = '';
    }
    if ($rebateMode !== '') {
        $rebate['mode'] = $rebateMode;
    }
    // 只要任一字段被显式设置就落库；全空 = 未配置，保持不写入 configs.rebate
    if ($rebate !== []) {
        $configs['rebate'] = $rebate;
    }

    // SEO 配置：title / keywords / description 存入 configs.seo
    $seo = [];
    foreach (['title' => 'seo_title', 'keywords' => 'seo_keywords', 'description' => 'seo_description'] as $k => $inputName) {
        $v = trim((string) Input::post($inputName, ''));
        if ($v !== '') $seo[$k] = $v;
    }
    if ($seo !== []) {
        $configs['seo'] = $seo;
    }

    // 验证必填（按步骤顺序）
    // ① 商品类型
    if (empty($goods_type)) {
        Response::error('请选择商品类型');
    }
    // ② 插件类型配置必填项校验（通过 filter 钩子，插件返回错误信息字符串则中断）
    $pluginData = $_POST['plugin_data'] ?? [];
    $pluginValidateError = applyFilter("goods_type_{$goods_type}_validate", '', $pluginData);
    if (!empty($pluginValidateError)) {
        Response::error($pluginValidateError);
    }
    // ③ 商品分类
    if (empty($category_id)) {
        Response::error('请选择商品分类');
    }
    // ④ 商品标题
    if (empty($title)) {
        Response::error('商品标题不能为空');
    }

    $data = [
        'title' => $title,
        'code' => $code,
        'category_id' => $category_id,
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

    // 仅创建时设置 owner_id 和 created_by，更新时不覆盖
    if (!$id) {
        $data['owner_id'] = 0; // 主站
        $data['created_by'] = $_SESSION['em_admin_auth']['id'] ?? 0;
    }

    try {
        if ($id) {
            // 更新
            $result = GoodsModel::update($id, $data);
            $goodsId = $id;
        } else {
            // 创建
            $goodsId = GoodsModel::create($data);
            $result = $goodsId ? true : false;
        }

        if (!$result) {
            Response::error('保存失败，请检查是否填写了所有必填项');
        }

        // 处理规格（统一逻辑：通过"/"分隔自动识别多维规格）
        $specs = $_POST['specs'] ?? [];
        $prefix = Database::prefix();
        // 维度名称：用户在表头输入如"颜色/款式"，按"/"拆分
        $specDimNameRaw = trim($_POST['spec_dim_name'] ?? '');
        $specDimNames = $specDimNameRaw !== '' ? array_map('trim', explode('/', $specDimNameRaw)) : [];

        // 收集有效规格行（名称为空但有价格的行自动填充"默认规格"）
        $specRows = [];
        foreach ($specs as $index => $spec) {
            if (!is_numeric($index)) continue;
            $name = trim($spec['name'] ?? '');
            if ($name === '') {
                // 有价格则自动填充名称，完全空行则跳过
                if (!empty($spec['price']) && (float)$spec['price'] > 0) {
                    $spec['name'] = '默认规格';
                } else {
                    continue;
                }
            }
            $specRows[$index] = $spec;
        }

        // 校验维度一致性：解析每行的"/"数量
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

        // ============================================================
        // 规格就地更新策略：按名称匹配，UPDATE 已有 / INSERT 新增 / DELETE 被移除
        // 保持 spec.id 不变，避免触发关联表（卡密、等级价格等）的大量更新
        // ============================================================

        // 1. 查询旧规格，建立 name => row 映射
        $oldSpecMap = []; // name => {id, stock, sold_count}
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

        // 2. 收集本次提交的规格名称集合
        $newSpecNames = [];
        foreach ($specRows as $spec) {
            $newSpecNames[] = trim($spec['name']);
        }

        // 3. 删除被移除的规格（旧名称不在新列表中）
        foreach ($oldSpecMap as $oldName => $oldInfo) {
            if (!in_array($oldName, $newSpecNames, true)) {
                $oldId = $oldInfo['id'];
                // 清理该规格关联的组合映射
                Database::execute("DELETE FROM {$prefix}goods_spec_combo WHERE spec_id = ?", [$oldId]);
                // 清理等级价格和用户专属价格
                Database::execute("DELETE FROM {$prefix}goods_price_level WHERE spec_id = ?", [$oldId]);
                Database::execute("DELETE FROM {$prefix}goods_price_user WHERE spec_id = ?", [$oldId]);
                // 卡密不删除，仅清除 spec_id 关联（卡密内容仍有保留价值）
                try {
                    Database::execute(
                        "UPDATE {$prefix}goods_virtual_card SET spec_id = NULL WHERE spec_id = ?",
                        [$oldId]
                    );
                } catch (Throwable $e) {} // 插件表可能不存在
                // 删除规格本身
                Database::execute("DELETE FROM {$prefix}goods_spec WHERE id = ?", [$oldId]);
            }
        }

        // 4. UPDATE 已有规格 / INSERT 新增规格
        $specIdMap = []; // index => specId
        foreach ($specRows as $index => $spec) {
            $tags = null;
            if (!empty($spec['tags'])) {
                $tagList = array_map('trim', explode(',', $spec['tags']));
                $tagList = array_filter($tagList);
                if (!empty($tagList)) {
                    $tags = json_encode($tagList);
                }
            }

            $specName = trim($spec['name']);

            // 规格级 configs JSON：现在只存 images。
            // level_prices / user_prices 从 JSON 中剥离出来，稍后同步到独立表 em_goods_price_level / em_goods_price_user
            $specConfigsJson = null;
            $pendingLevelPrices = [];   // 供稍后写入 em_goods_price_level
            $pendingUserPrices  = [];   // 供稍后写入 em_goods_price_user
            if (isset($spec['configs']) && is_string($spec['configs']) && $spec['configs'] !== '') {
                $decoded = json_decode($spec['configs'], true);
                if (is_array($decoded)) {
                    // 价格类字段前端按元录入（DECIMAL 直接存元，无 ×1000000 转换）
                    if (!empty($decoded['level_prices']) && is_array($decoded['level_prices'])) {
                        foreach ($decoded['level_prices'] as $lvlId => $priceYuan) {
                            if (is_numeric($priceYuan) && $priceYuan >= 0 && (int) $lvlId > 0) {
                                $pendingLevelPrices[(int) $lvlId] = (float) $priceYuan;
                            }
                        }
                    }
                    if (!empty($decoded['user_prices']) && is_array($decoded['user_prices'])) {
                        foreach ($decoded['user_prices'] as $uid => $priceYuan) {
                            if (is_numeric($priceYuan) && $priceYuan >= 0 && (int) $uid > 0) {
                                $pendingUserPrices[(int) $uid] = (float) $priceYuan;
                            }
                        }
                    }
                    // 只保留 images 存到 configs
                    $keepConfigs = [];
                    if (!empty($decoded['images']) && is_array($decoded['images'])) {
                        $keepConfigs['images'] = array_values(array_filter($decoded['images'], 'is_string'));
                    }
                    $specConfigsJson = $keepConfigs !== [] ? json_encode($keepConfigs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
                }
            }

            $updateData = [
                'spec_no' => $spec['spec_no'] ?? '',
                'price' => GoodsModel::moneyToDb($spec['price'] ?? 0),
                'cost_price' => !empty($spec['cost_price']) ? GoodsModel::moneyToDb($spec['cost_price']) : null,
                'market_price' => !empty($spec['market_price']) ? GoodsModel::moneyToDb($spec['market_price']) : null,
                'tags' => $tags,
                'configs' => $specConfigsJson,
                // 空串 → NULL（不限制）；有值才取整；min_buy 最小 1、max_buy 最小 0
                'min_buy' => isset($spec['min_buy']) && $spec['min_buy'] !== '' ? max(1, (int)$spec['min_buy']) : null,
                'max_buy' => isset($spec['max_buy']) && $spec['max_buy'] !== '' ? max(0, (int)$spec['max_buy']) : null,
                'sort' => (int)($spec['sort'] ?? 0),
                'is_default' => isset($specs['is_default']) && $specs['is_default'] == $index ? 1 : 0,
            ];

            if (isset($oldSpecMap[$specName])) {
                // 已有规格：就地 UPDATE，保留 id / stock / sold_count
                $existingId = $oldSpecMap[$specName]['id'];
                Database::update('goods_spec', $updateData, $existingId);
                $specIdMap[$index] = $existingId;
                $specIdForPrices = $existingId;
            } else {
                // 新增规格：INSERT
                $insertData = array_merge($updateData, [
                    'goods_id' => $goodsId,
                    'name' => $specName,
                    'stock' => 0,
                ]);
                $specIdMap[$index] = Database::insert('goods_spec', $insertData);
                $specIdForPrices = $specIdMap[$index];
            }

            // 同步用户等级专属价：先按 spec_id 全删，再按当前配置全插
            // 价格按项目统一约定 ×1000000 存 BIGINT（和 em_goods_spec.price 保持一致）
            Database::execute("DELETE FROM {$prefix}goods_price_level WHERE spec_id = ?", [$specIdForPrices]);
            foreach ($pendingLevelPrices as $lvlId => $priceYuan) {
                Database::insert('goods_price_level', [
                    'spec_id'  => $specIdForPrices,
                    'level_id' => $lvlId,
                    'price'    => GoodsModel::moneyToDb($priceYuan),
                ]);
            }

            // 同步用户专属价
            Database::execute("DELETE FROM {$prefix}goods_price_user WHERE spec_id = ?", [$specIdForPrices]);
            foreach ($pendingUserPrices as $uid => $priceYuan) {
                Database::insert('goods_price_user', [
                    'spec_id' => $specIdForPrices,
                    'user_id' => $uid,
                    'price'   => GoodsModel::moneyToDb($priceYuan),
                ]);
            }
        }

        // 5. 重建维度/维度值/组合映射（这些是轻量级展示数据，安全重建）
        Database::execute("DELETE FROM {$prefix}goods_spec_combo WHERE goods_id = ?", [$goodsId]);
        Database::execute("DELETE FROM {$prefix}goods_spec_value WHERE goods_id = ?", [$goodsId]);
        Database::execute("DELETE FROM {$prefix}goods_spec_dim WHERE goods_id = ?", [$goodsId]);

        // 单维规格
        if ($dimCount == 1 && !empty($specRows)) {
            Database::insert('goods_spec_dim', [
                'goods_id' => $goodsId,
                'name' => $specDimNames[0] ?? '规格',
                'sort' => 0,
            ]);
        }

        // 多维规格：自动生成维度/维度值/组合映射表
        if ($dimCount > 1 && !empty($specRows)) {
            $dimValues = [];
            $specDimParts = [];
            foreach ($specRows as $index => $spec) {
                $parts = array_map('trim', explode('/', trim($spec['name'])));
                $specDimParts[$index] = $parts;
                foreach ($parts as $dimIdx => $val) {
                    if (!isset($dimValues[$dimIdx])) {
                        $dimValues[$dimIdx] = [];
                    }
                    if (!in_array($val, $dimValues[$dimIdx])) {
                        $dimValues[$dimIdx][] = $val;
                    }
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
                foreach ($parts as $dimIdx => $val) {
                    $valueIds[] = $valueIdMap[$dimIdx . '|' . $val];
                }
                $comboHash = md5(implode('|', $valueIds));
                Database::insert('goods_spec_combo', [
                    'goods_id' => $goodsId,
                    'spec_id' => $specIdMap[$index],
                    'combo_hash' => $comboHash,
                    'combo_text' => trim($spec['name']),
                    'value_ids' => json_encode($valueIds),
                ]);
            }
        }

        // 6. 无规格行时确保有默认规格
        if (empty($specRows)) {
            if (isset($oldSpecMap['默认规格'])) {
                // 默认规格已存在，保持不动（stock/sold_count 不变）
            } else {
                Database::insert('goods_spec', [
                    'goods_id' => $goodsId,
                    'name' => '默认规格',
                    'price' => 0,
                    'stock' => 0,
                    'is_default' => 1,
                    'status' => 1,
                ]);
            }
        }

        // 更新价格缓存
        GoodsModel::updatePriceStockCache($goodsId);

        // 保存商品标签关联
        $goodsTagsStr = trim($_POST['goods_tags'] ?? '');
        $goodsTagNames = array_filter(array_map('trim', explode(',', $goodsTagsStr)));
        $goodsTagIds = [];
        foreach ($goodsTagNames as $tagName) {
            if ($tagName !== '') {
                $goodsTagIds[] = GoodsTagModel::findOrCreate($tagName);
            }
        }
        GoodsTagModel::syncGoodsTags($goodsId, $goodsTagIds);
        GoodsTagModel::refreshAllCounts();

        // 触发商品类型保存钩子
        $postData = $_POST;
        doAction("goods_type_{$goods_type}_save", $goodsId, $postData);

    } catch (Throwable $e) {
        $errorMsg = $e->getMessage();
        // 字段不能为 NULL：提取具体字段名，如 "Column 'title' cannot be null"
        if (preg_match("/Column '(\w+)' cannot be null/i", $errorMsg, $m)) {
            Response::error('保存失败：字段「' . $m[1] . '」不能为空');
        }
        // 字段无默认值：如 "Field 'category_id' doesn't have a default value"
        if (preg_match("/Field '(\w+)' doesn't have a default/i", $errorMsg, $m)) {
            Response::error('保存失败：字段「' . $m[1] . '」未填写');
        }
        if (stripos($errorMsg, 'Duplicate entry') !== false) {
            Response::error('保存失败：商品编码重复，请更换编码');
        }
        Response::error('保存失败：' . $errorMsg);
    }

    // 保存成功后刷新 token（重要操作后更换 token）
    $newToken = Csrf::refresh();

    Response::success('保存成功', ['id' => $goodsId, 'csrf_token' => $newToken]);
}

// 插件专属 action（card_list/card_import/card_delete/card_export/card_manager）
// 已迁移至各自的商品类型插件目录，通过 admin_plugin_action 钩子分发
// 路由入口：/admin/index.php?_action=card_list 等

// 库存管理弹窗（按商品类型加载不同的库存管理界面）
if ($action === 'stock_manager') {
    $goodsId = (int)($_GET['goods_id'] ?? 0);
    if (!$goodsId) {
        exit('商品ID不能为空');
    }
    // 库存管理弹窗：用 raw 价，避免库存面板里展示的价被买家折扣污染
    $goods = GoodsModel::getById($goodsId, false);
    if (!$goods) {
        exit('商品不存在');
    }
    $specs = GoodsModel::getSpecsByGoodsId($goodsId, false);
    $pageTitle = '库存管理';
    include __DIR__ . '/view/popup/header.php';
    include __DIR__ . '/view/popup/stock_manager.php';
    include __DIR__ . '/view/popup/footer.php';
    exit;
}

// 库存保存（AJAX）
if ($action === 'save_stock') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!Csrf::validate($csrf)) {
        Response::error('请求已失效，请刷新页面后重试');
    }
    $goodsId = (int)($_POST['goods_id'] ?? 0);
    if (!$goodsId) {
        Response::error('商品ID不能为空');
    }
    // 库存保存只是 ACL 校验，价格不参与，但保持和库存弹窗同口径用 raw
    $goods = GoodsModel::getById($goodsId, false);
    if (!$goods) {
        Response::error('商品不存在');
    }

    // 保存规格库存（必须为非负整数）
    $specStocks = $_POST['spec_stock'] ?? [];
    if (is_array($specStocks)) {
        foreach ($specStocks as $specId => $stock) {
            $stockVal = max(0, (int)$stock);
            Database::update('goods_spec', ['stock' => $stockVal], (int)$specId);
        }
    }

    // 触发类型专属库存保存钩子（如卡密类型需要额外处理）
    doAction("goods_type_{$goods['goods_type']}_stock_save", $goods, $_POST);

    // 同步更新商品价格/库存缓存字段
    GoodsModel::updatePriceStockCache($goodsId);

    Response::success('库存已保存', ['csrf_token' => Csrf::token()]);
}

// 获取商品规格列表（JSON，供 AJAX 刷新）
if ($action === 'get_specs_json') {
    $goodsId = (int)($_GET['id'] ?? 0);
    if (!$goodsId) {
        Response::error('商品ID不能为空');
    }
    // 编辑表单"刷新规格"AJAX 端点：必须返回 raw 价
    $specs = GoodsModel::getSpecsByGoodsId($goodsId, false);
    Response::success('', ['specs' => $specs]);
}

// 默认：显示编辑页面
$id = (int)($_GET['id'] ?? 0);
$goods = null;
$specs = [];
$specDimNames = [];
if ($id) {
    // 编辑表单初始加载：用 raw 价，把"成交价配置"原值显示给运营，
    // 不能被当前登录管理员账号的买家折扣污染（曾经报过 9.9 元的 bug）
    $goods = GoodsModel::getById($id, false);
    $specs = GoodsModel::getSpecsByGoodsId($id, false);
    // 查询维度名称用于回显到规格类型输入框
    $specDimNames = Database::query(
        "SELECT name FROM " . Database::prefix() . "goods_spec_dim WHERE goods_id = ? ORDER BY sort ASC",
        [$id]
    );
}

$categories = Database::query("SELECT * FROM " . Database::prefix() . "goods_category WHERE status = 1 ORDER BY parent_id ASC, sort ASC");
$goodsTypes = GoodsTypeManager::getTypes();

// 弹窗模式判断
$isPopup = Input::get('_popup', '') === '1';
if ($isPopup) {
    $isEdit = isset($goods) && !empty($goods);
    $pageTitle = $isEdit ? '编辑商品' : '添加商品';
    $esc = function (?string $str): string {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    };
    // 所有启用的用户等级，供规格"用户等级专属价"弹窗渲染
    $userLevels = [];
    if (class_exists('UserLevelModel')) {
        try {
            $userLevels = (new UserLevelModel())->getAll();
        } catch (Throwable $e) {
            $userLevels = [];
        }
    }

    // 规格级专属价：按 spec_id 预取
    //   $specLevelPricesBySpec = [spec_id => [level_id => price_yuan]]
    //   $specUserPricesBySpec  = [spec_id => [user_id => {price, label}]]
    $specLevelPricesBySpec = [];
    $specUserPricesBySpec  = [];
    if ($isEdit && !empty($specs)) {
        $specIds = array_map(static fn($s) => (int) $s['id'], $specs);
        $specIds = array_values(array_filter($specIds, static fn($v) => $v > 0));
        if ($specIds !== []) {
            $placeholders = implode(',', array_fill(0, count($specIds), '?'));
            // 等级价（DB 存 ×1M BIGINT → 给前端转回元）
            try {
                $rows = Database::query(
                    "SELECT `spec_id`, `level_id`, `price` FROM " . Database::prefix() . "goods_price_level WHERE `spec_id` IN ({$placeholders})",
                    $specIds
                );
                foreach ($rows as $r) {
                    $specLevelPricesBySpec[(int) $r['spec_id']][(int) $r['level_id']] = GoodsModel::moneyFromDb((int) $r['price']);
                }
            } catch (Throwable $e) { /* 表可能还没建 */ }
            // 用户价 + JOIN em_user 取展示用的昵称 / 用户名
            try {
                $rows = Database::query(
                    "SELECT p.`spec_id`, p.`user_id`, p.`price`, u.`username`, u.`nickname`
                       FROM " . Database::prefix() . "goods_price_user p
                       LEFT JOIN " . Database::prefix() . "user u ON u.id = p.user_id
                      WHERE p.`spec_id` IN ({$placeholders})",
                    $specIds
                );
                foreach ($rows as $r) {
                    $uid = (int) $r['user_id'];
                    $nick = (string) ($r['nickname'] ?? '');
                    $uname = (string) ($r['username'] ?? '');
                    $label = $nick !== '' ? $nick : ($uname !== '' ? $uname : ('#' . $uid));
                    $specUserPricesBySpec[(int) $r['spec_id']][$uid] = [
                        'price' => GoodsModel::moneyFromDb((int) $r['price']),
                        'label' => $label . ' (#' . $uid . ')',
                    ];
                }
            } catch (Throwable $e) { /* 表可能还没建 */ }
        }
    }
    include __DIR__ . '/view/popup/goods_edit.php';
    return;
}
