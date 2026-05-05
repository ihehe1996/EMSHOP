<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$csrfToken = Csrf::token();
$isEdit = isset($goods) && !empty($goods);
$placeholderImg = EM_CONFIG['placeholder_img'] ?? '/content/static/img/placeholder.png';
$coverImages = $isEdit && !empty($goods['cover_images']) ? json_decode($goods['cover_images'], true) : [];
$pageTitle = $isEdit ? '编辑商品' : '添加商品';
$extraHead = '<link rel="stylesheet" href="/content/static/lib/wangeditor/style.min.css">' . "\n";
$extraHead .= '<script src="/content/static/lib/wangeditor/index.min.js"></script>';
$esc = function (?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
};

// 解析 configs JSON（附加选项、营销配置）
$goodsConfigs = [];
if ($isEdit && !empty($goods['configs'])) {
    $goodsConfigs = json_decode($goods['configs'], true) ?: [];
}
$extraFields = $goodsConfigs['extra_fields'] ?? [];
$discountRules = $goodsConfigs['discount_rules'] ?? [];
$rebateConfig = $goodsConfigs['rebate'] ?? ['l1' => 0, 'l2' => 0];
// 营销配置中的金额字段还原为前端展示值（DB 存的是 ×1000000 后的整数）
foreach ($discountRules as &$_dr) {
    $_dr['threshold'] = GoodsModel::moneyFromDb($_dr['threshold'] ?? 0);
    $_dr['discount'] = GoodsModel::moneyFromDb($_dr['discount'] ?? 0);
}
unset($_dr);

include EM_ROOT . '/admin/view/popup/header.php';
?>

<div class="popup-inner">
    <form class="layui-form" id="goodsForm" lay-filter="goodsForm">
        <input type="hidden" name="_action" value="save_self">
        <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">
        <input type="hidden" name="id" value="<?php echo $isEdit ? $esc($goods['id']) : ''; ?>">
        <input type="hidden" name="cover_images" id="coverImagesInput" value='<?php echo $esc($isEdit ? $goods['cover_images'] : '[]'); ?>'>
        <!-- 分类来源：main=主站分类表 / merchant=本店自定义分类；由 category_id select 里 option 的 data-source 同步 -->
        <input type="hidden" name="category_source" id="categorySourceInput" value="<?php echo $isEdit ? $esc($goods['category_source'] ?? 'main') : 'main'; ?>">

        <!-- 选项卡 -->
        <div class="layui-tab layui-tab-brief goods-tab" lay-filter="goodsTab">
            <ul class="layui-tab-title">
                <li class="layui-this"><i class="fa fa-cog"></i> 基础设置</li>
                <li><i class="fa fa-image"></i> 图片/规格</li>
                <li><i class="fa fa-file-text-o"></i> 详细内容</li>
                <li><i class="fa fa-list-alt"></i> 附加选项</li>
                <li><i class="fa fa-tags"></i> 营销配置</li>
                <li><i class="fa fa-sliders"></i> 其他设置</li>
            </ul>
            <div class="layui-tab-content">

                <!-- ========== Tab 1: 基础设置 ========== -->
                <div class="layui-tab-item layui-show">
                    <div class="popup-section">
                    <div class="layui-form-item">
                        <label class="layui-form-label">商品类型</label>
                        <div class="layui-input-block">
                            <div class="goods-type-group" style="display:flex;align-items:center;border:1px solid #e6e6e6;border-radius:4px;">
                                <div class="goods-type-group__select" style="flex:1;min-width:0;">
                                    <select name="goods_type" id="goodsTypeSelect" lay-filter="goodsType">
                                        <?php if (!$isEdit): ?>
                                            <option value="">请选择商品类型</option>
                                        <?php endif; ?>
                                        <?php foreach ($goodsTypes as $type => $config): ?>
                                            <option value="<?php echo $esc($type); ?>" <?php echo $isEdit && $goods['goods_type'] == $type ? 'selected' : ''; ?>><?php echo $esc($config['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="button" class="goods-type-group__btn" id="editPluginConfigBtn" style="flex-shrink:0;padding:0 14px;height:38px;border:none;border-left:1px solid #d2d2d2;background:#1e9fff;color:#fff;font-size:13px;cursor:pointer;white-space:nowrap;border-radius:0 3px 3px 0;">
                                    <i class="fa fa-cog"></i> 类型配置
                                </button>
                            </div>
                        </div>
                        <div class="layui-form-mid"> 商品类型已全面插件化，如当前没有您所需的类型，可前往插件管理处启用更多商品类型插件</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">商品分类</label>
                        <div class="layui-input-block">
                            <select name="category_id" id="mcGoodsCategorySelect" lay-verify="required" lay-search lay-filter="mcGoodsCategorySelect">
                                <option value="">请选择商品分类</option>
                                <?php
                                    $curCatId     = $isEdit ? (int) ($goods['category_id'] ?? 0) : 0;
                                    $curCatSource = $isEdit ? (string) ($goods['category_source'] ?? 'main') : 'main';
                                ?>
                                <?php if (!empty($merchantCats)): ?>
                                <optgroup label="本店分类" data-source="merchant">
                                    <?php foreach ($merchantCats as $mcat): ?>
                                        <option value="<?php echo (int) $mcat['id']; ?>" data-source="merchant"
                                            <?php echo ($curCatSource === 'merchant' && (int) $mcat['id'] === $curCatId) ? 'selected' : ''; ?>>
                                            <?php echo str_repeat('—', $mcat['parent_id'] ? 1 : 0) . $esc($mcat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endif; ?>
                                <?php if (!empty($categories)): ?>
                                <optgroup label="主站分类" data-source="main">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo (int) $cat['id']; ?>" data-source="main"
                                            <?php echo ($curCatSource === 'main' && (int) $cat['id'] === $curCatId) ? 'selected' : ''; ?>>
                                            <?php echo str_repeat('—', $cat['parent_id'] ? 1 : 0) . $esc($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">商品标题</label>
                        <div class="layui-input-block">
                            <input type="text" name="title" lay-verify="required" placeholder="请输入商品标题" class="layui-input" value="<?php echo $isEdit ? $esc($goods['title']) : ''; ?>">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">商品编码</label>
                        <div class="layui-input-block">
                            <input type="text" name="code" placeholder="留空自动生成" class="layui-input" value="<?php echo $isEdit ? $esc($goods['code']) : ''; ?>">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">商品单位</label>
                        <div class="layui-input-block">
                            <select name="unit" lay-filter="goodsUnit">
                                <?php
                                $unitOptions = ['件', '个', '张', '份', '套', '台', '本', '瓶', '箱', '公斤', '克', '米', '次'];
                                $currentUnit = $isEdit ? ($goods['unit'] ?? '件') : '件';
                                foreach ($unitOptions as $u):
                                ?>
                                    <option value="<?php echo $esc($u); ?>" <?php echo $currentUnit === $u ? 'selected' : ''; ?>><?php echo $esc($u); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <!-- 商品标签 -->
                    <div class="layui-form-item">
                        <label class="layui-form-label">商品标签</label>
                        <div class="layui-input-block">
                            <div class="goods-tag-input-wrap" id="goodsTagInputWrap">
                                <div class="goods-tag-tokens" id="goodsTagTokens">
                                    <?php
                                    $goodsTags = [];
                                    if ($isEdit) {
                                        $goodsTags = GoodsTagModel::getTagsByGoodsId((int) $goods['id']);
                                        foreach ($goodsTags as $t):
                                    ?>
                                    <span class="goods-tag-token" data-id="<?php echo (int) $t['id']; ?>">
                                        <?php echo $esc($t['name']); ?>
                                        <i class="fa fa-times goods-tag-remove"></i>
                                    </span>
                                    <?php endforeach; } ?>
                                    <input type="text" class="goods-tag-text-input" id="goodsTagTextInput" placeholder="输入标签名，回车添加" autocomplete="off">
                                </div>
                                <div class="goods-tag-suggest" id="goodsTagSuggest" style="display:none;"></div>
                            </div>
                            <input type="hidden" name="goods_tags" id="goodsTagHiddenInput" value="<?php echo $esc(implode(',', array_column($goodsTags, 'name'))); ?>">
                            <div class="layui-form-mid layui-word-aux">输入标签名后按回车添加，支持多个标签</div>
                        </div>
                    </div>
                    </div>
                </div>

                <!-- ========== Tab 2: 图片/规格 ========== -->
                <div class="layui-tab-item">
                    <!-- 商品图片 -->
                    <div class="popup-section">
                        <div class="layui-form-item">
                            <div class="layui-input-block" style="margin-left:0;">
                                <div class="admin-img-field" id="goodsImgField" data-placeholder="<?php echo $esc($placeholderImg); ?>">
                                    <img src="<?php echo $isEdit && !empty($coverImages) ? $esc($coverImages[0]) : $esc($placeholderImg); ?>"
                                         alt="" id="goodsImgPreview">
                                    <input type="text" class="layui-input admin-img-url" id="goodsImgUrl" maxlength="500"
                                           placeholder="输入图片URL，点击添加按钮或按回车" value="">
                                    <div class="admin-img-btns">
                                        <button type="button" class="layui-btn layui-btn-xs layui-btn-normal" id="goodsImgAddBtn" title="添加"><i class="fa fa-plus"></i></button>
                                        <button type="button" class="layui-btn layui-btn-xs" id="goodsImgUploadBtn" title="上传"><i class="fa fa-upload"></i></button>
                                        <button type="button" class="layui-btn layui-btn-xs layui-btn-normal" id="goodsImgPickBtn" title="选择"><i class="fa fa-image"></i></button>
                                        <button type="button" class="layui-btn layui-btn-xs layui-btn-danger" id="goodsImgClearBtn" title="清除"><i class="fa fa-times"></i></button>
                                    </div>
                                </div>
                                <div class="layui-form-mid" style="color:#909399;">支持多图上传，拖拽排序调整顺序，第一张为封面图</div>
                                <div class="image-preview-list<?php echo !empty($coverImages) ? ' has-images' : ''; ?>" id="imagePreviewList">
                                    <?php foreach ($coverImages as $img): ?>
                                        <div class="image-preview-item" data-url="<?php echo $esc($img); ?>">
                                            <img src="<?php echo $esc($img); ?>" class="img-clickable">
                                            <span class="remove-btn" onclick="removeImage(this)">×</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- 规格设置 -->
                    <div class="popup-section">
                        <div class="layui-form-item">
                            <div class="layui-input-block" style="margin-left:0;">
                                <div class="spec-table-wrap">
                                <table class="layui-table spec-table" id="specTable">
                                    <colgroup>
                                        <col width="30">
                                        <col width="300">
                                        <col width="120">
                                        <col width="120">
                                        <col width="150">
                                        <col width="90">
                                        <col width="90">
                                        <col width="70">
                                        <col width="70">
                                        <col width="60">
                                        <col width="60">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th class="spec-th">
                                                <?php
                                                // 编辑时回显维度名称（如"颜色/款式"）
                                                $dimNameStr = '';
                                                if (!empty($specDimNames)) {
                                                    $dimNameStr = implode('/', array_column($specDimNames, 'name'));
                                                }
                                                ?>
                                                <input type="text" style="font-weight: normal;" class="layui-input" id="specNameInput" name="spec_dim_name" placeholder="规格类型，多维用/分隔，如：颜色/款式" value="<?php echo $esc($dimNameStr); ?>">
                                            </th>
                                            <th>价格</th>
                                            <th>成本价</th>
                                            <th>市场价</th>
                                            <th>规格编号</th>
                                            <th>标签</th>
                                            <th>最小购买</th>
                                            <th>最大购买</th>
                                            <th>默认选中</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody id="specList">
                                        <?php if ($isEdit && !empty($specs)): ?>
                                            <?php foreach ($specs as $index => $spec): ?>
                                                <tr class="spec-row" data-spec-id="<?php echo $spec['id']; ?>">
                                                    <td class="drag-handle"><input type="hidden" name="specs[<?php echo $index; ?>][id]" value="<?php echo $spec['id']; ?>"><i class="fa fa-bars"></i></td>
                                                    <td class="spec-td"><input type="text" name="specs[<?php echo $index; ?>][name]" class="layui-input" value="<?php echo $esc($spec['name']); ?>" placeholder="规格值"></td>
                                                    <td><input type="number" step="0.01" name="specs[<?php echo $index; ?>][price]" class="layui-input" value="<?php echo $spec['price']; ?>"></td>
                                                    <td><input type="number" step="0.01" name="specs[<?php echo $index; ?>][cost_price]" class="layui-input" value="<?php echo $spec['cost_price'] ?? ''; ?>" placeholder=""></td>
                                                    <td><input type="number" step="0.01" name="specs[<?php echo $index; ?>][market_price]" class="layui-input" value="<?php echo $spec['market_price'] ?? ''; ?>" placeholder=""></td>
                                                    <td><input type="text" name="specs[<?php echo $index; ?>][spec_no]" class="layui-input" value="<?php echo $esc($spec['spec_no']); ?>"></td>
                                                    <td><input type="text" name="specs[<?php echo $index; ?>][tags]" class="layui-input" value="<?php $tagsRaw=$spec["tags"]??"";$tagsDecoded=is_string($tagsRaw)?json_decode($tagsRaw,true):$tagsRaw;echo $esc(is_array($tagsDecoded)?implode(",",$tagsDecoded):$tagsRaw);?>" placeholder=""></td>
                                                    <td><input type="number" name="specs[<?php echo $index; ?>][min_buy]" class="layui-input" value="<?php echo $spec["min_buy"] ?? ""; ?>"></td>
                                                    <td><input type="number" name="specs[<?php echo $index; ?>][max_buy]" class="layui-input" value="<?php echo $spec["max_buy"] ?? ""; ?>" placeholder=""></td>
                                                    <td><input type="radio" name="specs[is_default]" lay-skin="primary" value="<?php echo $index; ?>" <?php echo $spec['is_default'] ? 'checked' : ''; ?>></td>
                                                    <td><button type="button" class="layui-btn layui-btn-danger layui-btn-xs" onclick="removeSpec(this)"><i class="fa fa-trash"></i></button></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                </div>
                                <button type="button" class="layui-btn layui-btn-sm" id="addSpecBtn"><i class="fa fa-plus"></i> 添加规格</button>
                                <button type="button" class="layui-btn layui-btn-sm layui-btn-normal" id="specHelpBtn"><i class="fa fa-info"></i> 查看设置教程</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========== Tab 3: 详细内容 ========== -->
                <div class="layui-tab-item">
                    <div class="popup-section">
                    <div class="layui-form-item">
                        <label class="layui-form-label">商品简介</label>
                        <div class="layui-input-block">
                            <textarea name="intro" placeholder="简短描述" class="layui-textarea"><?php echo $isEdit ? $esc($goods['intro']) : ''; ?></textarea>
                        </div>
                    </div>
                    <div class="layui-form-item" style="margin-bottom:0;">
                        <label class="layui-form-label">商品详情</label>
                        <div class="layui-input-block">
                            <div id="editor-wrapper">
                                <div id="toolbar-container"></div>
                                <div id="editor-container"></div>
                            </div>
                            <textarea name="content" id="editor-textarea" style="display:none;"><?php echo $isEdit ? $esc($goods['content']) : ''; ?></textarea>
                        </div>
                    </div>
                    </div>
                </div>

                <!-- ========== Tab 4: 附加选项 ========== -->
                <div class="layui-tab-item">
                    <div class="popup-section">
                    <blockquote class="layui-elem-quote" style="margin-bottom:15px;">
                        配置下单时需要用户填写的自定义表单字段，如 QQ、性别、手机号等。字段名称和字段标识为必填项。
                    </blockquote>
                    <table class="layui-table extra-fields-table" id="extraFieldsTable">
                        <colgroup>
                            <col width="30">
                            <col width="160">
                            <col width="140">
                            <col width="160">
                            <col width="130">
                            <col width="80">
                            <col width="60">
                        </colgroup>
                        <thead>
                            <tr>
                                <th></th>
                                <th>字段名称</th>
                                <th>字段标识</th>
                                <th>占位提示</th>
                                <th>格式验证</th>
                                <th>必填</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="extraFieldsList">
                            <?php if (!empty($extraFields)): ?>
                                <?php foreach ($extraFields as $efIdx => $ef): ?>
                                    <tr class="extra-field-row">
                                        <td class="drag-handle"><i class="fa fa-bars"></i></td>
                                        <td><input type="text" name="extra_fields[<?php echo $efIdx; ?>][title]" class="layui-input" value="<?php echo $esc($ef['title']); ?>" placeholder="如：QQ号"></td>
                                        <td><input type="text" name="extra_fields[<?php echo $efIdx; ?>][name]" class="layui-input" value="<?php echo $esc($ef['name']); ?>" placeholder="如：qq"></td>
                                        <td><input type="text" name="extra_fields[<?php echo $efIdx; ?>][placeholder]" class="layui-input" value="<?php echo $esc($ef['placeholder'] ?? ''); ?>" placeholder="如：请输入QQ号"></td>
                                        <td>
                                            <select name="extra_fields[<?php echo $efIdx; ?>][format]">
                                                <option value="text" <?php echo ($ef['format'] ?? 'text') === 'text' ? 'selected' : ''; ?>>文本</option>
                                                <option value="number" <?php echo ($ef['format'] ?? '') === 'number' ? 'selected' : ''; ?>>纯数字</option>
                                                <option value="phone" <?php echo ($ef['format'] ?? '') === 'phone' ? 'selected' : ''; ?>>手机号</option>
                                                <option value="email" <?php echo ($ef['format'] ?? '') === 'email' ? 'selected' : ''; ?>>邮箱</option>
                                            </select>
                                        </td>
                                        <td style="text-align:center;"><input type="checkbox" name="extra_fields[<?php echo $efIdx; ?>][required]" value="1" lay-skin="switch" lay-text="是|否" <?php echo !empty($ef['required']) ? 'checked' : ''; ?>></td>
                                        <td style="text-align:center;"><button type="button" class="layui-btn layui-btn-danger layui-btn-xs" onclick="$(this).closest('tr').remove()"><i class="fa fa-trash"></i></button></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <button type="button" class="layui-btn layui-btn-sm" id="addExtraFieldBtn"><i class="fa fa-plus"></i> 添加字段</button>
                    </div>
                </div>

                <!-- ========== Tab 5: 营销配置 ========== -->
                <div class="layui-tab-item">
                    <div class="popup-section">
                        <blockquote class="layui-elem-quote" style="margin-bottom:15px;">
                            设置满减规则：当订单金额满足阈值时，自动减去对应金额。可设置多个阶梯。
                        </blockquote>
                        <div class="spec-table-wrap">
                        <table class="layui-table discount-table" id="discountTable">
                            <colgroup>
                                <col width="30">
                                <col width="200">
                                <col width="200">
                                <col width="60">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>满（元）</th>
                                    <th>减（元）</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody id="discountList">
                                <?php if (!empty($discountRules)): ?>
                                    <?php foreach ($discountRules as $drIdx => $dr): ?>
                                        <tr class="discount-row">
                                            <td class="drag-handle"><i class="fa fa-bars"></i></td>
                                            <td><input type="number" step="0.01" name="discount_rules[<?php echo $drIdx; ?>][threshold]" class="layui-input" value="<?php echo $dr['threshold']; ?>" placeholder="满额"></td>
                                            <td><input type="number" step="0.01" name="discount_rules[<?php echo $drIdx; ?>][discount]" class="layui-input" value="<?php echo $dr['discount']; ?>" placeholder="减额"></td>
                                            <td style="text-align:center;"><button type="button" class="layui-btn layui-btn-danger layui-btn-xs" onclick="$(this).closest('tr').remove()"><i class="fa fa-trash"></i></button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        </div>
                        <button type="button" class="layui-btn layui-btn-sm" id="addDiscountBtn"><i class="fa fa-plus"></i> 添加阶梯</button>
                    </div>
                    <div class="popup-section">
                        <div class="layui-form-item">
                            <label class="layui-form-label">推荐商品</label>
                            <div class="layui-input-block">
                                <?php // 新建默认开启；编辑时按数据库值回显 ?>
                                <input type="checkbox" name="is_recommended" lay-skin="switch" lay-text="是|否" value="1" <?php echo ($isEdit ? !empty($goods['is_recommended']) : true) ? 'checked' : ''; ?>>
                            </div>
                        </div>
                    </div>

                    <div class="popup-section">
                        <blockquote class="layui-elem-quote" style="margin-bottom:15px;">
                            本商品的 3 级返佣比例（整数"万分位"，如 500 = 5%）。<br>
                            任意一级填 0 则使用分类级 → 全局级回退。全部不填 → 此商品不返佣。<br>
                            <strong>注意：</strong>按"订单利润（售价 − 成本价）"计算；商品未设置成本价时不计佣金。
                        </blockquote>
                        <div class="layui-form-item">
                            <label class="layui-form-label">一级</label>
                            <div class="layui-input-inline" style="width:180px;">
                                <div class="layui-input-wrap">
                                    <input type="number" name="rebate_l1" class="layui-input" min="0" max="10000"
                                           value="<?php echo (int) ($rebateConfig['l1'] ?? 0); ?>">
                                    <div class="layui-input-suffix">万分</div>
                                </div>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">二级</label>
                            <div class="layui-input-inline" style="width:180px;">
                                <div class="layui-input-wrap">
                                    <input type="number" name="rebate_l2" class="layui-input" min="0" max="10000"
                                           value="<?php echo (int) ($rebateConfig['l2'] ?? 0); ?>">
                                    <div class="layui-input-suffix">万分</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========== Tab 6: 其他设置 ========== -->
                <div class="layui-tab-item">
                    <div class="popup-section">
                    <div class="layui-form-item">
                        <label class="layui-form-label">排序值</label>
                        <div class="layui-input-block">
                            <input type="number" name="sort" class="layui-input" value="<?php echo $isEdit ? $goods['sort'] : 0; ?>">
                        </div>
                        <div class="layui-form-mid">数字越小越靠前</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">首页置顶</label>
                        <div class="layui-input-block">
                            <input type="checkbox" name="is_top_home" lay-skin="switch" lay-text="是|否" value="1" <?php echo $isEdit && $goods['is_top_home'] ? 'checked' : ''; ?>>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">分类置顶</label>
                        <div class="layui-input-block">
                            <input type="checkbox" name="is_top_category" lay-skin="switch" lay-text="是|否" value="1" <?php echo $isEdit && $goods['is_top_category'] ? 'checked' : ''; ?>>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">API对接</label>
                        <div class="layui-input-block">
                            <input type="checkbox" name="api_enabled" lay-skin="switch" lay-text="开启|关闭" value="1" <?php echo (!$isEdit || $goods['api_enabled']) ? 'checked' : ''; ?>>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">跳转链接</label>
                        <div class="layui-input-block">
                            <input type="text" name="jump_url" class="layui-input" value="<?php echo $isEdit ? $esc($goods['jump_url']) : ''; ?>">
                        </div>
                        <div class="layui-form-mid">非空时点击商品跳转至外部链接</div>
                    </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- 类型配置（隐藏区域，存放插件表单字段供主表单提交） -->
        <div id="pluginFormContainer" style="display:none;">
            <?php if ($isEdit && isset($goods['goods_type']) && !empty($goods['goods_type'])): ?>
                <?php doAction("goods_type_{$goods['goods_type']}_create_form", $goods); ?>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="popup-footer">
    <button type="button" class="popup-btn" id="goodsCancelBtn"><i class="fa fa-times"></i> 取消</button>
    <button type="button" class="popup-btn popup-btn--primary" id="goodsSubmitBtn"><i class="fa fa-check mr-5"></i> 确认保存</button>
</div>

<style>
/* 选项卡样式微调 */
.goods-tab { margin: 0; }
.goods-tab > .layui-tab-title { padding: 0 10px; background: #fafafa; border-bottom: 1px solid #e6e6e6; }
.goods-tab > .layui-tab-title li { font-size: 13px; padding: 0 15px; }
.goods-tab > .layui-tab-title li .fa { margin-right: 3px; }
.goods-tab > .layui-tab-content > .layui-tab-item { padding: 0; }


/* 商品类型选择 + 类型配置按钮组合 */
.goods-type-group {
    display: flex;
    align-items: center;
    border: 1px solid #e6e6e6;
    border-radius: 4px;
}
.goods-type-group__select {
    flex: 1;
    min-width: 0;
}
.goods-type-group .layui-form-select .layui-input {
    border: none !important;
    box-shadow: none !important;
}
.goods-type-group__btn {
    flex-shrink: 0;
    padding: 0 14px;
    height: 38px;
    border: none;
    border-left: 1px solid #d2d2d2;
    background: #1e9fff;
    color: #fff;
    font-size: 13px;
    cursor: pointer;
    white-space: nowrap;
    transition: background .2s;
}
.goods-type-group__btn:hover {
    background: #0c8ce6;
}
.goods-type-group__btn .fa {
    margin-right: 3px;
    color: #fff;
}

/* 富文本编辑器边框 */
#editor-wrapper {
    border: 1px solid #e6e6e6;
    border-radius: 4px;
    background: #fff;
    overflow: hidden;
}
#toolbar-container { border-bottom: 1px solid #e6e6e6; }
#editor-container { min-height: 200px; background: #fff; }
#editor-container [data-slate-editor] { min-height: 200px; }
#editor-container .w-e-text-container { min-height: 200px !important; }

/* 图片预览列表间距 & 无图时隐藏 */
.image-preview-list { margin-top: 8px; display: none; }
div.image-preview-list.has-images { display: block; }

/* 图片可点击放大 */
.img-clickable { cursor: pointer; transition: opacity 0.2s; }
.img-clickable:hover { opacity: 0.8; }

/* 规格/附加选项/满减表格通用样式 */
.spec-table, .extra-fields-table, .discount-table { margin-bottom: 10px; }
.spec-table th, .spec-table td,
.extra-fields-table th, .extra-fields-table td,
.discount-table th, .discount-table td {
    text-align: center; padding: 5px 8px !important; min-width: 70px;
}
.spec-td, .spec-th{
    min-width: 123px!important;
    text-align: center!important;
}
/* 拖拽列（第一列）覆盖 min-width */
.spec-table th:first-child, .spec-table td:first-child,
.extra-fields-table th:first-child, .extra-fields-table td:first-child,
.discount-table th:first-child, .discount-table td:first-child {
    min-width: 30px !important; width: 30px; padding: 0 !important;
}
/* 第二列左对齐（原第一列内容列） */
.spec-table th:nth-child(2), .spec-table td:nth-child(2),
.extra-fields-table th:nth-child(2), .extra-fields-table td:nth-child(2) {
    text-align: left;
}
.spec-table td:last-child, .extra-fields-table td:last-child, .discount-table td:last-child {
    text-align: center !important;
}
.spec-table .layui-input:not([type="radio"]):not([type="checkbox"]),
.extra-fields-table .layui-input,
.discount-table .layui-input {
    height: 32px; line-height: 32px; padding: 0 10px; text-align: center;
    border-radius: 4px; border: 1px solid #dcdfe6;
    transition: border-color .2s, box-shadow .2s;
}
.spec-table .layui-input:focus,
.extra-fields-table .layui-input:focus,
.discount-table .layui-input:focus {
    border-color: #1e9fff;
    box-shadow: 0 0 0 2px rgba(30, 159, 255, 0.12);
}
.extra-fields-table .layui-input { text-align: left; }
/* 表格内 select 下拉框样式 */
.extra-fields-table .layui-form-select .layui-input {
    height: 32px; line-height: 32px; border-radius: 4px;
}
.spec-table .layui-form-switch { height: 20px; line-height: 20px; margin-top: 4px; }
.spec-table .layui-form-radio{ padding-right: 0; margin-right: 0; }
.spec-table .layui-form-radio i{ padding-right: 0; margin-right: 0; }
/* 表格横向滚动 */
.spec-table-wrap { overflow-x: auto; margin-bottom: 10px; }
.spec-table-wrap::-webkit-scrollbar { height: 6px; }
.spec-table-wrap::-webkit-scrollbar-thumb { background: #d0d0d0; border-radius: 3px; }
.spec-table-wrap::-webkit-scrollbar-track { background: #f0f0f0; }

/* 拖拽手柄 */
.drag-handle {
    cursor: grab; text-align: center !important; color: #ccc;
    width: 30px; min-width: 30px !important; padding: 0 !important;
    user-select: none;
}
.drag-handle:active { cursor: grabbing; }
.drag-handle:hover { color: #999; }
.drag-handle .fa { font-size: 14px; line-height: 30px; }

/* Sortable 拖拽样式 */
.sortable-ghost { opacity: 0.4; background: #e6f7ff; }

/* 商品标签输入 */
.goods-tag-input-wrap { position: relative; }
.goods-tag-tokens {
    display: flex; flex-wrap: wrap; gap: 6px; align-items: center;
    padding: 4px 8px; min-height: 38px;
    border: 1px solid #e6e6e6; border-radius: 4px; background: #fff;
    cursor: text;
}
.goods-tag-token {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; font-size: 12px; line-height: 20px;
    background: #eef3ff; color: #5b8ff9; border-radius: 3px;
    white-space: nowrap;
}
.goods-tag-remove { cursor: pointer; font-size: 11px; color: #aaa; }
.goods-tag-remove:hover { color: #f00; }
.goods-tag-text-input {
    border: none; outline: none; flex: 1; min-width: 120px;
    font-size: 13px; line-height: 28px; background: transparent;
}
.goods-tag-suggest {
    position: absolute; left: 0; right: 0; top: 100%; z-index: 9999;
    background: #fff; border: 1px solid #e6e6e6; border-top: none;
    border-radius: 0 0 4px 4px; max-height: 200px; overflow-y: auto;
    box-shadow: 0 4px 12px rgba(0,0,0,.08);
}
.goods-tag-suggest-item {
    padding: 6px 12px; font-size: 13px; cursor: pointer;
    display: flex; justify-content: space-between; align-items: center;
}
.goods-tag-suggest-item:hover { background: #f0f5ff; }
.goods-tag-suggest-item .tag-count { font-size: 11px; color: #adb5bd; }
</style>

<script>
$(function() {
    // 选项卡点击兜底：独立于 layui.use 注册，确保即使模块加载异常也能切换
    $('.goods-tab').on('click', '.layui-tab-title>li', function() {
        var $li = $(this);
        var index = $li.index();
        var $tab = $li.closest('.layui-tab');
        $li.addClass('layui-this').siblings().removeClass('layui-this');
        $tab.children('.layui-tab-content').children('.layui-tab-item')
            .removeClass('layui-show').eq(index).addClass('layui-show');
    });

    layui.use(['layer', 'form', 'upload', 'element'], function() {
        var layer = layui.layer;
        var form = layui.form;
        var upload = layui.upload;
        var element = layui.element;

        form.render();

        // ============================================================
        // 变量初始化
        // ============================================================
        var csrfToken = <?php echo json_encode($csrfToken); ?>;
        var goodsId = <?php echo $isEdit ? $goods['id'] : 0; ?>;
        var specIndex = <?php echo ($isEdit && !empty($specs)) ? count($specs) : 0; ?>;
        var extraFieldIndex = <?php echo !empty($extraFields) ? count($extraFields) : 0; ?>;
        var discountIndex = <?php echo !empty($discountRules) ? count($discountRules) : 0; ?>;
        var $previewList = $('#imagePreviewList');
        var $coverInput = $('#coverImagesInput');
        var placeholderImg = <?php echo json_encode($placeholderImg); ?>;

        // ============================================================
        // 富文本编辑器初始化（WangEditor v5）
        // ============================================================
        (function() {
            var $editorTextarea = $('#editor-textarea');
            var initialContent = $editorTextarea.val() || '';

            var editorConfig = {
                placeholder: '请输入商品详情...',
                onChange: function(editor) {
                    $editorTextarea.val(editor.getHtml());
                },
                MENU_CONF: {
                    uploadImage: {
                        fieldName: 'file',
                        server: '/user/merchant/upload.php',
                        data: {
                            csrf_token: csrfToken,
                            context: 'goods_image',
                        },
                        onSuccess: function(res) {
                            if (res && res.data && res.data.csrf_token) {
                                csrfToken = res.data.csrf_token;
                                $('input[name="csrf_token"]').val(csrfToken);
                            }
                        },
                    },
                },
            };

            try {
                var E = window.wangEditor;
                var editor = E.createEditor({
                    selector: '#editor-container',
                    html: initialContent || '<p><br></p>',
                    config: editorConfig,
                    mode: 'default',
                });

                E.createToolbar({
                    editor: editor,
                    selector: '#toolbar-container',
                    config: {},
                    mode: 'simple',
                });

                window._goodsEditor = editor;

                $('#editor-container').on('click', function(e) {
                    if (e.target === this || $(e.target).hasClass('w-e-text-container') || $(e.target).hasClass('w-e-scroll')) {
                        editor.focus(true);
                    }
                });
            } catch (e) {
                console.error('富文本编辑器初始化失败:', e);
                $('#editor-wrapper').html('<div style="color:#f00;padding:10px;">富文本编辑器加载失败，请刷新页面重试</div>');
            }
        })();

        // ============================================================
        // 图片相关
        // ============================================================
        function updateCoverInput() {
            var urls = [];
            $previewList.find('.image-preview-item').each(function() {
                urls.push($(this).data('url'));
            });
            $coverInput.val(JSON.stringify(urls));
            if ($previewList.find('.image-preview-item').length === 0) {
                $previewList.removeClass('has-images');
            } else {
                $previewList.addClass('has-images');
            }
        }

        function addImageToList(url) {
            if (!url) return;
            var $item = $('<div class="image-preview-item" data-url="' + url + '"><img src="' + url + '" class="img-clickable"><span class="remove-btn" onclick="removeImage(this)">×</span></div>');
            $previewList.append($item);
            updateCoverInput();
            bindImageZoom();
            $('#goodsImgPreview').attr('src', url);
            $('#goodsImgUrl').val(url);
        }

        window.removeImage = function(btn) {
            $(btn).closest('.image-preview-item').remove();
            updateCoverInput();
            if ($previewList.find('.image-preview-item').length === 0) {
                $('#goodsImgPreview').attr('src', placeholderImg);
                $('#goodsImgUrl').val('');
            }
        };

        function bindImageZoom() {
            $previewList.find('.img-clickable').off('click').on('click', function() {
                var src = $(this).attr('src');
                layer.photos({
                    photos: { data: [{ src: src }] },
                    shade: 0.6,
                    anim: 5
                });
            });
        }
        bindImageZoom();

        $('#goodsImgUrl').on('input', function() {
            var url = $(this).val();
            $('#goodsImgPreview').attr('src', url || placeholderImg);
        });

        $('#goodsImgClearBtn').on('click', function() {
            $('#goodsImgUrl').val('');
            $('#goodsImgPreview').attr('src', placeholderImg);
        });

        $('#goodsImgAddBtn').on('click', function() {
            var url = $.trim($('#goodsImgUrl').val());
            if (!url) {
                layer.msg('请先输入图片URL');
                return;
            }
            addImageToList(url);
            $('#goodsImgUrl').val('');
            $('#goodsImgPreview').attr('src', placeholderImg);
        });

        $('#goodsImgUrl').on('keydown', function(e) {
            if (e.keyCode === 13) {
                e.preventDefault();
                $('#goodsImgAddBtn').trigger('click');
            }
        });

        // 选择按钮：打开媒体库
        $('#goodsImgPickBtn').on('click', function() {
            var pickLayerIndex = layer.open({
                type: 2,
                title: '选择图片',
                skin: 'admin-modal',
                maxmin: true,
                area: ['700px', '500px'],
                shadeClose: false,
                content: '/user/merchant/media.php?_csrf=' + encodeURIComponent(csrfToken),
                btn: ['确定', '取消'],
                yes: function(index, layero) {
                    var win = layero.find('iframe')[0].contentWindow;
                    var url = win.selectMedia();
                    if (!url) {
                        layer.msg('请先选择一张图片');
                        return;
                    }
                    layer.close(index);
                    addImageToList(url);
                }
            });
        });

        // 上传按钮
        var $fileInput = $('<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple style="display:none;">');
        $('body').append($fileInput);

        $fileInput.on('change', function() {
            var files = this.files;
            if (!files || !files.length) return;

            var uploaded = 0;
            var total = files.length;

            function uploadNext() {
                if (uploaded >= total) {
                    $fileInput.val('');
                    return;
                }
                var file = files[uploaded++];
                if (!file.type.match(/image\/(jpeg|png|gif|webp)/i)) {
                    layer.msg('仅支持 JPG、PNG、GIF、WebP 格式');
                    uploadNext();
                    return;
                }
                if (file.size > 10 * 1024 * 1024) {
                    layer.msg('图片大小不能超过 10MB');
                    uploadNext();
                    return;
                }

                var formData = new FormData();
                formData.append('file', file);
                formData.append('csrf_token', csrfToken);
                formData.append('context', 'goods_image');

                $.ajax({
                    url: '/user/merchant/upload.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(res) {
                        if (res.code === 200) {
                            addImageToList(res.data.url);
                            if (res.data.csrf_token) {
                                csrfToken = res.data.csrf_token;
                                $('input[name="csrf_token"]').val(csrfToken);
                            }
                            layer.msg('上传成功');
                        } else {
                            layer.msg(res.msg || '上传失败');
                        }
                    },
                    error: function() {
                        layer.msg('网络异常，上传失败');
                    },
                    complete: function() {
                        uploadNext();
                    }
                });
            }

            uploadNext();
        });

        $('#goodsImgUploadBtn').on('click', function() {
            $fileInput.trigger('click');
        });

        // 拖拽排序（Sortable.js）
        if (typeof Sortable !== 'undefined') {
            // 图片排序
            new Sortable($previewList[0], {
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: function() { updateCoverInput(); }
            });
            // 规格表格排序
            new Sortable(document.getElementById('specList'), {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'sortable-ghost'
            });
            // 附加选项表格排序
            new Sortable(document.getElementById('extraFieldsList'), {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'sortable-ghost'
            });
            // 满减配置表格排序
            new Sortable(document.getElementById('discountList'), {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'sortable-ghost'
            });
        } else {
            console.warn('Sortable.js 未加载，拖拽排序不可用');
        }

        // ============================================================
        // 规格相关
        // ============================================================
        $('#addSpecBtn').click(function() {
            var idx = specIndex++;
            var html = '<tr class="spec-row">' +
                '<td class="drag-handle"><input type="hidden" name="specs[' + idx + '][id]" value=""><i class="fa fa-bars"></i></td>' +
                '<td class="spec-td"><input type="text" name="specs[' + idx + '][name]" class="layui-input" placeholder="规格值"></td>' +
                '<td><input type="number" step="0.01" name="specs[' + idx + '][price]" class="layui-input" value="" placeholder=""></td>' +
                '<td><input type="number" step="0.01" name="specs[' + idx + '][cost_price]" class="layui-input" value="" placeholder=""></td>' +
                '<td><input type="number" step="0.01" name="specs[' + idx + '][market_price]" class="layui-input" value="" placeholder=""></td>' +
                '<td><input type="text" name="specs[' + idx + '][spec_no]" class="layui-input" placeholder=""></td>' +
                '<td><input type="text" name="specs[' + idx + '][tags]" class="layui-input" placeholder=""></td>' +
                '<td><input type="number" name="specs[' + idx + '][min_buy]" class="layui-input" value=""></td>' +
                '<td><input type="number" name="specs[' + idx + '][max_buy]" class="layui-input" value="" placeholder=""></td>' +
                '<td><input type="radio" name="specs[is_default]" lay-skin="primary" value="' + idx + '"></td>' +
                '<td><button type="button" class="layui-btn layui-btn-danger layui-btn-xs" onclick="removeSpec(this)"><i class="fa fa-trash"></i></button></td>' +
                '</tr>';
            $('#specList').append(html);
            form.render();
        });

        window.removeSpec = function(btn) {
            var $row = $(btn).closest('.spec-row');
            var specId = $row.data('spec-id');

            layer.confirm('确定要删除该规格吗？', function(index) {
                layer.close(index);
                if (specId) {
                    $.ajax({
                        url: '/user/merchant/goods.php?_action=remove_spec',
                        type: 'POST',
                        dataType: 'json',
                        data: { spec_id: specId, csrf_token: csrfToken },
                        success: function(res) {
                            if (res.code === 200) {
                                csrfToken = res.data.csrf_token;
                                $row.remove();
                                layer.msg('删除成功');
                            } else {
                                layer.msg(res.msg || '删除失败');
                            }
                        },
                        error: function() { layer.msg('网络异常'); }
                    });
                } else {
                    $row.remove();
                }
            });
        };

        // 刷新规格列表
        window.refreshSpecList = function(goodsId) {
            $.ajax({
                url: '/user/merchant/goods.php',
                type: 'GET',
                dataType: 'json',
                data: { id: goodsId, _action: 'get_specs_json' },
                success: function(res) {
                    if (res.code === 200 && res.data) {
                        renderSpecsToTable(res.data.specs);
                    }
                },
                error: function() {
                    console.error('刷新规格列表失败');
                }
            });
        };

        window.renderSpecsToTable = function(specs) {
            var $tbody = $('#specList');
            $tbody.empty();
            var esc = function(str) {
                return String(str || '').replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            };
            specs.forEach(function(spec, idx) {
                var tags = Array.isArray(spec.tags) ? spec.tags.join(',') : (spec.tags || '');
                var minBuy = spec.min_buy || '';
                var maxBuy = spec.max_buy || '';
                var html = '<tr class="spec-row" data-spec-id="' + esc(spec.id) + '">' +
                    '<td class="drag-handle"><input type="hidden" name="specs[' + idx + '][id]" value="' + esc(spec.id) + '"><i class="fa fa-bars"></i></td>' +
                    '<td><input type="text" name="specs[' + idx + '][name]" class="layui-input" value="' + esc(spec.name) + '" placeholder="如：默认"></td>' +
                    '<td><input type="number" step="0.01" name="specs[' + idx + '][price]" class="layui-input" value="' + esc(spec.price) + '"></td>' +
                    '<td><input type="number" step="0.01" name="specs[' + idx + '][cost_price]" class="layui-input" value="' + esc(spec.cost_price) + '" placeholder="成本"></td>' +
                    '<td><input type="number" step="0.01" name="specs[' + idx + '][market_price]" class="layui-input" value="' + esc(spec.market_price) + '" placeholder="划线价"></td>' +
                    '<td><input type="text" name="specs[' + idx + '][spec_no]" class="layui-input" value="' + esc(spec.spec_no) + '"></td>' +
                    '<td><input type="text" name="specs[' + idx + '][tags]" class="layui-input" value="' + esc(tags) + '" placeholder="标签"></td>' +
                    '<td><input type="number" name="specs[' + idx + '][min_buy]" class="layui-input" value="' + esc(minBuy) + '"></td>' +
                    '<td><input type="number" name="specs[' + idx + '][max_buy]" class="layui-input" value="' + esc(maxBuy) + '" placeholder="0不限"></td>' +
                    '<td><input type="radio" name="specs[is_default]" lay-skin="primary" value="' + idx + '"' + (spec.is_default == 1 ? ' checked' : '') + '></td>' +
                    '<td><button type="button" class="layui-btn layui-btn-danger layui-btn-xs" onclick="removeSpec(this)"><i class="fa fa-trash"></i></button></td>' +
                    '</tr>';
                $tbody.append(html);
            });
            if (typeof form !== 'undefined') {
                form.render('radio');
            }
        };

        // 规格设置教程弹窗
        $('#specHelpBtn').on('click', function() {
            layer.open({
                type: 1,
                title: '<i class="fa fa-info-circle"></i> 规格设置教程',
                skin: 'admin-modal',
                area: ['520px', 'auto'],
                content: '<div style="padding:20px;line-height:2;font-size:14px;">' +
                    '<p><b>① 单规格：</b>仅需填写一行，规格类型与规格值留空即可</p>' +
                    '<p><b>② 单维规格：</b>例如 【规格类型：时长】 【规格值：周卡、月卡、年卡】有几个就添加几个规格</p>' +
                    '<p><b>③ 多维规格：</b>例如 【规格类型：颜色/款式】 【规格值：黑色/长款、白色/长款、白色/短款】用斜线（/）分隔</p>' +
                    '<p style="margin-top:10px;color:#999;font-size:12px;">本教程由群主手写，如看不懂，那就再看一遍。</p>' +
                    '</div>',
                btn: ['<i class="fa fa-check"></i> 知道了'],
                btnAlign: 'c'
            });
        });

        // ============================================================
        // 附加选项（自定义表单字段）
        // ============================================================
        $('#addExtraFieldBtn').on('click', function() {
            var idx = extraFieldIndex++;
            var html = '<tr class="extra-field-row">' +
                '<td class="drag-handle"><i class="fa fa-bars"></i></td>' +
                '<td><input type="text" name="extra_fields[' + idx + '][title]" class="layui-input" placeholder="如：QQ号"></td>' +
                '<td><input type="text" name="extra_fields[' + idx + '][name]" class="layui-input" placeholder="如：qq"></td>' +
                '<td><input type="text" name="extra_fields[' + idx + '][placeholder]" class="layui-input" placeholder="如：请输入QQ号"></td>' +
                '<td><select name="extra_fields[' + idx + '][format]">' +
                    '<option value="text">文本</option>' +
                    '<option value="number">纯数字</option>' +
                    '<option value="phone">手机号</option>' +
                    '<option value="email">邮箱</option>' +
                '</select></td>' +
                '<td style="text-align:center;"><input type="checkbox" name="extra_fields[' + idx + '][required]" value="1" lay-skin="switch" lay-text="是|否"></td>' +
                '<td style="text-align:center;"><button type="button" class="layui-btn layui-btn-danger layui-btn-xs" onclick="$(this).closest(\'tr\').remove()"><i class="fa fa-trash"></i></button></td>' +
                '</tr>';
            $('#extraFieldsList').append(html);
            form.render();
        });

        // ============================================================
        // 满减配置
        // ============================================================
        $('#addDiscountBtn').on('click', function() {
            var idx = discountIndex++;
            var html = '<tr class="discount-row">' +
                '<td class="drag-handle"><i class="fa fa-bars"></i></td>' +
                '<td><input type="number" step="0.01" name="discount_rules[' + idx + '][threshold]" class="layui-input" placeholder="满额"></td>' +
                '<td><input type="number" step="0.01" name="discount_rules[' + idx + '][discount]" class="layui-input" placeholder="减额"></td>' +
                '<td style="text-align:center;"><button type="button" class="layui-btn layui-btn-danger layui-btn-xs" onclick="$(this).closest(\'tr\').remove()"><i class="fa fa-trash"></i></button></td>' +
                '</tr>';
            $('#discountList').append(html);
        });

        // ============================================================
        // 商品类型切换：弹出类型配置弹窗
        // ============================================================
        var openPluginConfigPopup = function(goodsType) {
            if (!goodsType) {
                layer.msg('请先选择商品类型');
                return;
            }
            var loadingIdx = layer.load(2);
            $.ajax({
                url: '/user/merchant/goods.php?_action=get_plugin_form',
                type: 'POST',
                dataType: 'json',
                data: {
                    csrf_token: csrfToken,
                    goods_type: goodsType,
                    goods_id: goodsId
                },
                success: function(res) {
                    layer.close(loadingIdx);
                    if (res.code !== 200) {
                        layer.msg(res.msg || '加载失败');
                        return;
                    }

                    var formHtml = res.data.html || '';
                    if (!formHtml || !$.trim(formHtml)) {
                        layer.msg('该类型暂无配置项');
                        return;
                    }

                    // 构建弹窗内容
                    var popupHtml = '<div style="padding:15px;">' +
                        '<form class="layui-form" id="pluginConfigForm" lay-filter="pluginConfigForm">' +
                        formHtml +
                        '</form>' +
                        '</div>';

                    layer.open({
                        type: 1,
                        title: '<i class="fa fa-cog"></i> 类型配置',
                        skin: 'admin-modal',
                        area: ['600px', '450px'],
                        content: popupHtml,
                        btn: ['<i class="fa fa-check"></i> 确认', '<i class="fa fa-times"></i> 取消'],
                        btnAlign: 'r',
                        yes: function(index) {
                            // 确认：将弹窗中的表单字段复制到主表单的隐藏容器中
                            var $container = $('#pluginFormContainer');
                            $container.empty().append($('#pluginConfigForm').html());
                            // 同步表单字段值
                            $('#pluginConfigForm').find('input, select, textarea').each(function() {
                                var name = $(this).attr('name');
                                if (!name) return;
                                var $target = $container.find('[name="' + name + '"]');
                                if ($(this).is(':checkbox') || $(this).is(':radio')) {
                                    $target.prop('checked', $(this).prop('checked'));
                                } else {
                                    $target.val($(this).val());
                                }
                            });
                            layer.close(index);
                        },
                        success: function() {
                            // 从隐藏容器回填之前保存的字段值到弹窗表单
                            var $container = $('#pluginFormContainer');
                            if ($container.children().length) {
                                $container.find('input, select, textarea').each(function() {
                                    var name = $(this).attr('name');
                                    if (!name) return;
                                    var $target = $('#pluginConfigForm').find('[name="' + name + '"]');
                                    if (!$target.length) return;
                                    if ($(this).is(':checkbox') || $(this).is(':radio')) {
                                        $target.prop('checked', $(this).prop('checked'));
                                    } else {
                                        $target.val($(this).val());
                                    }
                                });
                            }
                            form.render(null, 'pluginConfigForm');
                        }
                    });
                },
                error: function() {
                    layer.close(loadingIdx);
                    layer.msg('网络异常');
                }
            });
        };

        // 类型配置按钮点击
        $('#editPluginConfigBtn').on('click', function() {
            var currentType = $('#goodsTypeSelect').val();
            openPluginConfigPopup(currentType);
        });

        // 分类下拉切换时，把 option 的 data-source（main/merchant）同步到隐藏字段 category_source
        form.on('select(mcGoodsCategorySelect)', function(data) {
            var $opt = $('#mcGoodsCategorySelect').find('option[value="' + data.value + '"]');
            var src = $opt.data('source');
            if (src === 'merchant' || src === 'main') {
                $('#categorySourceInput').val(src);
            } else {
                $('#categorySourceInput').val('main'); // 兜底：未知来源按主站
            }
        });

        // 切换商品类型时自动弹出配置
        form.on('select(goodsType)', function(data) {
            var goodsType = data.value;
            if (!goodsType) {
                $('#pluginFormContainer').empty();
                return;
            }
            openPluginConfigPopup(goodsType);
        });

        // ============================================================
        // 默认行：表格为空时自动添加一行
        // ============================================================
        if ($('#specList tr').length === 0) {
            $('#addSpecBtn').trigger('click');
        }
        if ($('#extraFieldsList tr').length === 0) {
            $('#addExtraFieldBtn').trigger('click');
        }
        if ($('#discountList tr').length === 0) {
            $('#addDiscountBtn').trigger('click');
        }

        // 重新渲染选项卡，确保所有tab可切换
        element.render('tab');

        // ============================================================
        // 保存
        // ============================================================
        $('#goodsCancelBtn').on('click', function() {
            var index = parent.layer.getFrameIndex(window.name);
            parent.layer.close(index);
        });

        $('#goodsSubmitBtn').on('click', function() {
            var $btn = $(this);
            $btn.find('i').attr('class', 'fa fa-refresh admin-spin mr-5');
            $btn.prop('disabled', true);

            var formData = $('#goodsForm').serialize();

            $.ajax({
                url: '/user/merchant/goods.php?_action=save_self',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(res) {
                    if (res.code === 200) {
                        if (res.data && res.data.csrf_token) {
                            try { parent.updateCsrf(res.data.csrf_token); } catch(e) {}
                        }
                        try { parent.window._goodsPopupSaved = true; } catch(e) {}
                        // 商户后台父页 (user/merchant/view/goods.php) 用 _mcSelfSaved 标志在 layer end 回调里 reload 表格
                        try { parent.window._mcSelfSaved = true; } catch(e) {}
                        var index = parent.layer.getFrameIndex(window.name);
                        parent.layer.msg(res.msg || '保存成功');
                        parent.layer.close(index);
                    } else {
                        layer.msg(res.msg || '保存失败');
                    }
                },
                error: function() {
                    layer.msg('网络错误，请重试');
                },
                complete: function() {
                    $btn.find('i').attr('class', 'fa fa-check mr-5');
                    $btn.prop('disabled', false);
                }
            });
        });
    });
});

// 商品标签输入
(function(){
    var allTags = <?= json_encode(GoodsTagModel::getAll(), JSON_UNESCAPED_UNICODE) ?>;
    var $wrap = $('#goodsTagInputWrap');
    var $tokens = $('#goodsTagTokens');
    var $input = $('#goodsTagTextInput');
    var $hidden = $('#goodsTagHiddenInput');
    var $suggest = $('#goodsTagSuggest');

    function syncHidden() {
        var names = [];
        $tokens.find('.goods-tag-token').each(function(){ names.push($(this).text().trim()); });
        $hidden.val(names.join(','));
    }
    function hasTag(name) {
        var found = false;
        $tokens.find('.goods-tag-token').each(function(){
            if ($(this).text().trim().toLowerCase() === name.toLowerCase()) { found = true; return false; }
        });
        return found;
    }
    function addTag(name) {
        name = $.trim(name);
        if (!name || hasTag(name)) return;
        var $tag = $('<span class="goods-tag-token">' + $('<span>').text(name).html()
            + ' <i class="fa fa-times goods-tag-remove"></i></span>');
        $input.before($tag);
        syncHidden();
    }
    // 删除标签
    $tokens.on('click', '.goods-tag-remove', function(){ $(this).parent().remove(); syncHidden(); });
    // 点击区域聚焦
    $tokens.on('click', function(){ $input.focus(); });
    // 回车添加
    $input.on('keydown', function(e){
        if (e.keyCode === 13) { e.preventDefault(); addTag($input.val()); $input.val(''); $suggest.hide(); }
        if (e.keyCode === 8 && $input.val() === '') {
            $tokens.find('.goods-tag-token').last().remove(); syncHidden();
        }
    });
    // 自动补全
    $input.on('input', function(){
        var val = $.trim($input.val()).toLowerCase();
        if (!val) { $suggest.hide(); return; }
        var html = '', count = 0;
        for (var i = 0; i < allTags.length && count < 8; i++) {
            if (allTags[i].name.toLowerCase().indexOf(val) !== -1 && !hasTag(allTags[i].name)) {
                html += '<div class="goods-tag-suggest-item" data-name="' + $('<span>').text(allTags[i].name).html() + '">'
                    + $('<span>').text(allTags[i].name).html()
                    + '<span class="tag-count">' + (allTags[i].goods_count || 0) + '件</span></div>';
                count++;
            }
        }
        if (html) { $suggest.html(html).show(); } else { $suggest.hide(); }
    });
    $suggest.on('click', '.goods-tag-suggest-item', function(){ addTag($(this).data('name')); $input.val(''); $suggest.hide(); });
    $(document).on('click', function(e){ if (!$(e.target).closest('#goodsTagInputWrap').length) $suggest.hide(); });
})();
</script>

<?php include EM_ROOT . '/admin/view/popup/footer.php'; ?>
