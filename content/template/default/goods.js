/**
 * 商品详情页 JS（test 模板）。
 *
 * 依赖：jQuery、layui.layer、Viewer.js、guest_find.js（游客查单模式）
 *
 * 使用方式：
 *   GoodsDetail.init({
 *     specs: [...],                 // 规格数组，每项需含 stock（整数业务字段）与 stock_text（展示文字）
 *     currencySymbol: '¥',
 *     goodsUnit: '件',
 *   });
 *
 * 附加选项：DOM 中 input[name^="extra_"] 即为附加选项字段（与模板无关）。
 * 查单模式：DOM 中存在 #guestFindContactSection / #guestFindPasswordSection 即自动校验。
 * 库存判断统一使用 stock（整数）；stock_text 只用于展示。
 */
var GoodsDetail = (function () {
    var opts = {};
    var specs = [];
    var specMap = {};
    var currentSpec = null;
    var isMultiDim = false;
    // 地址弹层数据缓存：订单提交后端报错（如订单密码未填）时地址会丢，这里缓存本次填的值
    // 下次弹层重开以此为初始值回填，避免用户反复填地址
    var lastPickedAddressId = null;
    var lastGuestAddress = null;

    function hasStock(spec) {
        // stock 是整数业务字段；stock_text 仅用于展示，不参与判断
        return spec && (spec.stock | 0) > 0;
    }

    // 找默认规格：优先 is_default 且有库存；否则按价格升序找第一个有库存的
    function pickDefaultSpec() {
        for (var i = 0; i < specs.length; i++) {
            if (specs[i].is_default && hasStock(specs[i])) return specs[i];
        }
        var sorted = specs.slice().sort(function (a, b) { return a.price - b.price; });
        for (var i = 0; i < sorted.length; i++) {
            if (hasStock(sorted[i])) return sorted[i];
        }
        return null;
    }

    // 通过 value_ids 查找匹配的规格
    function findSpecByValueIds(selectedIds) {
        selectedIds = selectedIds.slice().sort();
        for (var i = 0; i < specs.length; i++) {
            var vids = (specs[i].value_ids || []).slice().sort();
            if (vids.length !== selectedIds.length) continue;
            var match = true;
            for (var j = 0; j < vids.length; j++) {
                if (vids[j] !== selectedIds[j]) { match = false; break; }
            }
            if (match) return specs[i];
        }
        return null;
    }

    // 获取当前所有选中维度的 value_ids
    function getSelectedValueIds() {
        var ids = [];
        $('.spec-group').each(function () {
            var $active = $(this).find('.spec-dim-btn.active');
            if ($active.length) ids.push(parseInt($active.data('value-id')));
        });
        return ids;
    }

    // 多维度规格：刷新每个 dim-btn 上方的 tags 容器
    //
    // 算法：每个按钮代表"该维度下选这个值"。把当前已选维度路径里"该按钮所属维度"
    // 这一项替换为按钮自身的 value_id，得到一个完整组合，去 specs[] 里找匹配的 spec，
    // 然后把 spec.tags 渲染到按钮上方。如果当前路径不全（用户取消了某个维度），
    // 没法精确确定 spec → 全部隐藏。
    function refreshDimBtnTags() {
        var $allBtns = $('.spec-dim-btn');
        if (!$allBtns.length) return;

        // 收集当前已选维度路径：dimId → valueId
        var currentPath = {};
        $('.spec-group').each(function () {
            var $a = $(this).find('.spec-dim-btn.active').first();
            if (!$a.length) return;
            var did = parseInt($a.data('dim-id'));
            var vid = parseInt($a.data('value-id'));
            if (did && vid) currentPath[did] = vid;
        });
        var totalDims = $('.spec-group').length;
        var pathDimCount = 0;
        for (var k in currentPath) if (currentPath.hasOwnProperty(k)) pathDimCount++;

        $allBtns.each(function () {
            var $btn = $(this);
            var $tags = $btn.parent('.spec-item').children('.spec-item-tags').first();
            if (!$tags.length) return;

            var did = parseInt($btn.data('dim-id'));
            var vid = parseInt($btn.data('value-id'));
            if (!did || !vid) { $tags.empty().hide(); return; }

            // 必须每个维度都有当前选中值（含按钮自身的维度），否则不显示
            // 先复制当前路径，把按钮所在维度替换为按钮的 value
            var hypo = {};
            for (var dk in currentPath) if (currentPath.hasOwnProperty(dk)) hypo[dk] = currentPath[dk];
            hypo[did] = vid;
            var hypoCount = 0;
            for (var hk in hypo) if (hypo.hasOwnProperty(hk)) hypoCount++;
            if (hypoCount < totalDims) { $tags.empty().hide(); return; }

            var hypoIds = [];
            for (var k2 in hypo) if (hypo.hasOwnProperty(k2)) hypoIds.push(hypo[k2]);
            var sp = findSpecByValueIds(hypoIds);
            var tagList = (sp && sp.tags && sp.tags.length) ? sp.tags : [];
            if (!tagList.length) { $tags.empty().hide(); return; }

            var html = '';
            for (var i = 0; i < tagList.length; i++) {
                var name = String(tagList[i] || '');
                if (!name) continue;
                html += '<span class="spec-tag">' + $('<span>').text(name).html() + '</span>';
            }
            if (html) {
                $tags.html(html).show();
            } else {
                $tags.empty().hide();
            }
        });
    }

    // 更新价格 / 库存 / 数量限制
    // 价格由末尾的 renderCurrentPrice() 统一渲染（要等 qtyInput 按新规格的 min_buy 重置后再算才对）
    function updateSpecDisplay(spec) {
        if (!spec) return;
        currentSpec = spec;
        var cs = opts.currencySymbol;
        var unit = opts.goodsUnit;

        // 多维度规格：所有按钮上方的 tags 重新计算（"假设选我会形成的 spec 的标签"）
        refreshDimBtnTags();

        // 数量选择器右侧只展示"限购" 或 "缺货"，库存数字放在上方 stats 区
        var stockHtml = '';
        if (!hasStock(spec)) {
            stockHtml = '<span style="color:#fa5252;">缺货</span>';
        } else if (spec.max_buy > 0) {
            stockHtml = '限购' + spec.max_buy + unit;
        }
        $('#stockInfo').html(stockHtml);

        $('#soldInfo').text(spec.sold_count || 0);
        $('#stockCount').text(spec.stock_text || '0');

        var minBuy = spec.min_buy || 1;
        if (minBuy > 1) {
            $('#minBuyHint').text('最低购买' + minBuy + unit).show();
        } else {
            $('#minBuyHint').hide();
        }

        var $qty = $('#qtyInput');
        $qty.val(minBuy).attr('min', minBuy);
        if (spec.max_buy > 0) {
            $qty.attr('max', spec.max_buy);
        } else {
            $qty.removeAttr('max');
        }
        // 规格切换时 min_buy 可能变，数量重置后也要重算一次价
        renderCurrentPrice();
    }

    // 商品级满减：从 opts.discountRules 里找"threshold ≤ 当前商品总额"里 discount 最大的一条
    // 满减规则假定已按门槛升序（PHP 解析时未排序则这里多一层保障），取最后一条匹配即为最优
    function pickDiscountAmount(goodsAmount) {
        var hit = pickDiscountRule(goodsAmount);
        return hit ? (parseFloat(hit.discount) || 0) : 0;
    }
    // 同样的匹配逻辑，但返回完整规则对象（threshold + discount），用于价格框右侧的"满 X 减 Y"提示文案
    function pickDiscountRule(goodsAmount) {
        var rules = opts.discountRules || [];
        var bestRule = null;
        var best = 0;
        for (var i = 0; i < rules.length; i++) {
            var t = parseFloat(rules[i].threshold) || 0;
            var d = parseFloat(rules[i].discount)  || 0;
            if (goodsAmount >= t && d > best) { best = d; bestRule = rules[i]; }
        }
        return bestRule;
    }

    // 按"单价 × 数量 - 满减"渲染 .detail-price-box
    // 规格切换 / 数量 +/-/手填 都走这里
    // 优惠券不融入这里显示（它在 #detailCouponApplied 独立展示"已优惠 ¥X"）—— 价格框只表达"商品小计"
    function renderCurrentPrice() {
        if (!currentSpec) return;
        // 货币展示：数值部分始终以"主货币元值"运算（spec.price / market_price 都是主货币），
        // 展示前 × window.EMSHOP_CURRENCY.rate 换成访客币种；符号用 EMSHOP_CURRENCY.symbol 或 opts.currencySymbol
        var CUR = (window.EMSHOP_CURRENCY || { symbol: opts.currencySymbol || '¥', rate: 1 });
        var cs = CUR.symbol;
        var qty = parseInt($('#qtyInput').val()) || 1;
        if (qty < 1) qty = 1;

        var subtotal = parseFloat(currentSpec.price) * qty;
        var discount = pickDiscountAmount(subtotal);
        var finalPrice = Math.max(0, subtotal - discount);
        $('#specPrice').text(cs + (finalPrice * CUR.rate).toFixed(2));

        // 满减命中：在价格右侧展示"原小计（删除线） · 满 X 元减 Y 元"
        if (discount > 0) {
            var hitRule = pickDiscountRule(subtotal);
            $('#specSubtotalStrike').text(cs + (subtotal * CUR.rate).toFixed(2));
            $('#specDiscountLabel').text(
                '满 ' + cs + (parseFloat(hitRule.threshold) * CUR.rate).toFixed(2)
                + ' 减 ' + cs + (parseFloat(hitRule.discount) * CUR.rate).toFixed(2)
            );
            $('#specDiscountHint').show();
        } else {
            $('#specDiscountHint').hide();
        }

        // 划线原价：按"市场价 × 数量"展示，不扣满减（满减是针对实价的让利，原价展示本身就是对比参照）
        if (currentSpec.market_price && currentSpec.market_price > currentSpec.price) {
            $('#specMarketPrice').text(cs + (parseFloat(currentSpec.market_price) * qty * CUR.rate).toFixed(2)).show();
        } else {
            $('#specMarketPrice').hide();
        }

        // 数量变化时，若用户已应用优惠券，重新校验 —— 商品总额变了，门槛可能失效 / 折扣金额可能变
        // 调 applyDetailCoupon(code) 即可复用服务端 check 逻辑；失败时 UI 自动提示并保持状态（用户可手动重选）
        if (typeof detailCouponState !== 'undefined' && detailCouponState && detailCouponState.applied && detailCouponState.code) {
            revalidateCouponIfNeeded();
        }
    }

    // 优惠券状态 / 重校验函数提升到模块顶层，renderCurrentPrice 能直接触发
    // bindEvents 里原本的局部 var/function 现在直接赋值给这两个顶层符号
    var detailCouponState = { applied: false, code: '' };
    var applyDetailCoupon = function () {}; // bindEvents 内部会覆盖成真实函数
    function revalidateCouponIfNeeded() {
        if (detailCouponState && detailCouponState.applied && detailCouponState.code) {
            applyDetailCoupon(detailCouponState.code);
        }
    }

    // 更新多维规格按钮的禁用态（根据剩余维度是否存在有货组合）
    function updateDimDisabledState() {
        if (!isMultiDim) return;
        var dimSelections = [];
        $('.spec-group').each(function () {
            var $active = $(this).find('.spec-dim-btn.active');
            dimSelections.push($active.length ? parseInt($active.data('value-id')) : null);
        });

        $('.spec-group').each(function (groupIdx) {
            $(this).find('.spec-dim-btn').each(function () {
                var $btn = $(this);
                var testVid = parseInt($btn.data('value-id'));
                var constraints = [];
                for (var gi = 0; gi < dimSelections.length; gi++) {
                    if (gi === groupIdx) {
                        constraints.push(testVid);
                    } else if (dimSelections[gi] !== null) {
                        constraints.push(dimSelections[gi]);
                    }
                }
                var hasCombo = false, hasAvail = false;
                for (var i = 0; i < specs.length; i++) {
                    var vids = specs[i].value_ids || [];
                    var containsAll = true;
                    for (var c = 0; c < constraints.length; c++) {
                        if (vids.indexOf(constraints[c]) === -1) { containsAll = false; break; }
                    }
                    if (containsAll) {
                        hasCombo = true;
                        if (hasStock(specs[i])) { hasAvail = true; break; }
                    }
                }
                if (!hasCombo) {
                    $btn.hide().removeClass('disabled');
                } else if (hasAvail) {
                    $btn.show().removeClass('disabled');
                } else {
                    $btn.show().addClass('disabled');
                }
            });
        });
    }

    // 取消选中：显示价格区间和总销量/库存
    function resetPriceRange() {
        currentSpec = null;
        var CUR = (window.EMSHOP_CURRENCY || { symbol: opts.currencySymbol || '¥', rate: 1 });
        var cs = CUR.symbol;
        var minP = Infinity, maxP = 0;
        for (var i = 0; i < specs.length; i++) {
            if (specs[i].price < minP) minP = specs[i].price;
            if (specs[i].price > maxP) maxP = specs[i].price;
        }
        // minP/maxP 是主货币元值，显示时乘 rate 换成访客币种
        if (minP === maxP) {
            $('#specPrice').text(cs + (parseFloat(minP) * CUR.rate).toFixed(2));
        } else {
            $('#specPrice').text(cs + (parseFloat(minP) * CUR.rate).toFixed(2) + ' ~ ' + cs + (parseFloat(maxP) * CUR.rate).toFixed(2));
        }
        $('#specMarketPrice').hide();
        $('#stockInfo').html('');
        $('#minBuyHint').hide();

        var totalSold = 0, firstInStockLabel = null;
        for (var i = 0; i < specs.length; i++) {
            totalSold += (specs[i].sold_count || 0);
            if (firstInStockLabel === null && hasStock(specs[i])) {
                firstInStockLabel = specs[i].stock_text;
            }
        }
        $('#soldInfo').text(totalSold);
        $('#stockCount').text(firstInStockLabel || '0');
    }

    // 收集附加选项输入（name 前缀 extra_）；不做必填校验，由后端统一校验
    function collectExtraFields() {
        var data = {};
        $('input[name^="extra_"]').each(function () {
            var $f = $(this);
            data[$f.attr('name')] = ($f.val() || '').trim();
        });
        return data;
    }

    // 拿当前选中的规格 id；无规格或未选时返回 0（由后端决定如何处理）
    function currentSpecId() {
        if (currentSpec) return currentSpec.id;
        if (specs.length === 1) return specs[0].id;
        return 0;
    }

    function bindEvents() {
        $(document).off('.goodsDetail');

        // 单维规格点击（支持取消）
        $(document).on('click.goodsDetail', '.spec-single-btn:not(.disabled)', function () {
            var $btn = $(this);
            if ($btn.hasClass('active')) {
                $btn.removeClass('active');
                resetPriceRange();
            } else {
                $btn.closest('.spec-group-options').find('.spec-btn').removeClass('active');
                $btn.addClass('active');
                updateSpecDisplay(specMap[parseInt($btn.data('spec-id'))]);
            }
        });

        // 多维规格点击
        $(document).on('click.goodsDetail', '.spec-dim-btn:not(.disabled)', function () {
            var $btn = $(this);
            if ($btn.hasClass('active')) {
                $btn.removeClass('active');
            } else {
                $btn.closest('.spec-group-options').find('.spec-btn').removeClass('active');
                $btn.addClass('active');
            }

            var selectedIds = getSelectedValueIds();
            var totalDims = $('.spec-group').length;
            if (selectedIds.length === totalDims) {
                var matched = findSpecByValueIds(selectedIds);
                if (matched) updateSpecDisplay(matched);
                // updateSpecDisplay 内部会调 refreshDimBtnTags
            } else if (selectedIds.length > 0) {
                // 部分选中：不会进 updateSpecDisplay，主动刷新所有按钮 tags
                // （此时 hypo 路径不全，所有按钮上方 tags 会被隐藏，符合预期）
                refreshDimBtnTags();
                // 部分选中：展示可选组合的销量与首个有货规格的库存文字
                resetPriceRange();
                var partialSold = 0, partialStockLabel = null;
                for (var i = 0; i < specs.length; i++) {
                    var vids = specs[i].value_ids || [];
                    var match = true;
                    for (var j = 0; j < selectedIds.length; j++) {
                        if (vids.indexOf(selectedIds[j]) === -1) { match = false; break; }
                    }
                    if (match) {
                        partialSold += (specs[i].sold_count || 0);
                        if (partialStockLabel === null && hasStock(specs[i])) {
                            partialStockLabel = specs[i].stock_text;
                        }
                    }
                }
                $('#soldInfo').text(partialSold);
                $('#stockCount').text(partialStockLabel || '0');
            } else {
                resetPriceRange();
                // 全部取消时也刷新（按钮上方 tags 会全部隐藏）
                refreshDimBtnTags();
            }

            updateDimDisabledState();
        });

        // 数量选择器：每次数量变化都重算小计（用户询问"改数量价格没变"的核心修复点）
        $(document).on('click.goodsDetail', '#qtyMinus', function () {
            var $input = $('#qtyInput');
            var min = parseInt($input.attr('min')) || 1;
            var val = parseInt($input.val()) || min;
            if (val > min) $input.val(val - 1);
            renderCurrentPrice();
        });
        $(document).on('click.goodsDetail', '#qtyPlus', function () {
            var $input = $('#qtyInput');
            var max = parseInt($input.attr('max')) || 0;
            var val = parseInt($input.val()) || 1;
            if (max > 0 && val >= max) return;
            $input.val(val + 1);
            renderCurrentPrice();
        });
        $(document).on('change.goodsDetail', '#qtyInput', function () {
            var $input = $(this);
            var min = parseInt($input.attr('min')) || 1;
            var max = parseInt($input.attr('max')) || 0;
            var val = parseInt($input.val()) || min;
            if (val < min) val = min;
            if (max > 0 && val > max) val = max;
            $input.val(val);
            renderCurrentPrice();
        });

        // 缩略图切换
        $(document).on('click.goodsDetail', '.detail-thumb', function () {
            $(this).addClass('active').siblings().removeClass('active');
            $('#mainImage').attr('src', $(this).find('img').attr('src'));
        });

        // 支付方式选中（禁用态跳过，后端也会二次拦截）
        $(document).on('click.goodsDetail', '.detail-payment-item:not(.is-disabled)', function () {
            $(this).addClass('active').siblings().removeClass('active');
        });

        // 选项卡切换
        $(document).on('click.goodsDetail', '.detail-meta-tab-btn', function () {
            var tab = $(this).data('tab');
            $(this).addClass('active').siblings().removeClass('active');
            $(this).closest('.detail-meta-tabs').find('.detail-meta-tab-pane').removeClass('active');
            $('#' + tab).addClass('active');
        });

        // ======== 优惠券（详情页）========
        // detailCouponState 已提升到模块顶层（供 renderCurrentPrice 做数量变化后的重校验）
        detailCouponState = { applied: false, code: '' };

        $(document).on('click.goodsDetail', '#detailCouponApplyBtn', function () {
            var $btn = $(this);
            if ($btn.text() === '更换') {
                $('#detailCouponInput').prop('readonly', false).val('').focus();
                $btn.text('使用').removeClass('detail-coupon-btn--ghost').addClass('detail-coupon-btn--primary');
                $('#detailCouponApplied').hide();
                detailCouponState = { applied: false, code: '' };
                return;
            }
            var code = ($('#detailCouponInput').val() || '').trim();
            if (!code) { layer.msg('请输入优惠券码'); return; }
            applyDetailCoupon(code);
        });

        $(document).on('click.goodsDetail', '#detailCouponChooseBtn', function () {
            $.get('?c=coupon&a=mine', function (res) {
                if (res.code !== 200) { layer.msg(res.msg || '获取失败'); return; }
                var list = (res.data && res.data.coupons) || [];
                if (!list.length) { layer.msg('您暂无可用优惠券'); return; }
                var html = '<div class="detail-coupon-picker">';
                // 券面额 / 门槛按访客币种展示（主货币值 × rate）
                var _cur = (window.EMSHOP_CURRENCY || { symbol: '¥', rate: 1 });
                list.forEach(function (c) {
                    var v = c.type === 'fixed_amount' ? (_cur.symbol + (parseFloat(c.value) * _cur.rate).toFixed(2))
                          : c.type === 'percent'     ? (c.value/10).toFixed(1)+'折'
                          : '免邮';
                    var minTxt = parseFloat(c.min_amount) > 0
                        ? '满 ' + _cur.symbol + (parseFloat(c.min_amount) * _cur.rate).toFixed(2) + ' 可用'
                        : '无门槛';
                    html += '<div class="detail-coupon-pick-item" data-code="'+c.code+'">';
                    html += '<div class="detail-coupon-pick-value">'+v+'</div>';
                    html += '<div class="detail-coupon-pick-main">';
                    html += '<div class="detail-coupon-pick-title">'+(c.title||c.name)+'</div>';
                    html += '<div class="detail-coupon-pick-meta">' + minTxt
                          + (c.end_at? ' · 至 '+String(c.end_at).substring(0,16):'')+'</div>';
                    html += '</div>';
                    html += '<button type="button" class="detail-coupon-pick-btn">使用</button>';
                    html += '</div>';
                });
                html += '</div>';
                var lay = layui.layer.open({
                    type: 1, title: '选择优惠券', skin: 'admin-modal',
                    area: [window.innerWidth >= 600 ? '520px':'92%', window.innerHeight >= 700 ? '520px':'80%'],
                    content: html,
                    success: function (layero) {
                        layero.on('click', '.detail-coupon-pick-btn', function () {
                            var code = $(this).closest('.detail-coupon-pick-item').data('code');
                            layui.layer.close(lay);
                            $('#detailCouponInput').val(code);
                            applyDetailCoupon(code);
                        });
                    }
                });
            }, 'json');
        });

        // applyDetailCoupon 赋值给模块顶层符号，renderCurrentPrice 里 revalidateCouponIfNeeded 能调到
        applyDetailCoupon = function (code) {
            if (!currentSpec && specs.length > 1) { layer.msg('请先选择规格再应用券'); return; }
            var sp = currentSpec || specs[0];
            if (!sp) { layer.msg('商品无规格'); return; }
            var qty = parseInt($('#qtyInput').val()) || 1;
            // 服务端 coupon check 用当前商品总额做门槛校验（单价 × 数量；满减由服务端/下单时独立处理）
            var goodsAmount = (sp.price * qty).toFixed(2);
            var gid = parseInt($('#buyNowBtn').data('goods-id'));
            // 是不是"revalidate"调用（数量变化后重校验，失败时自动回退而不是弹 toast 吓用户）
            var isRevalidate = detailCouponState.applied && detailCouponState.code === code;

            $.post('?c=coupon&a=check', {
                code: code,
                goods_amount: goodsAmount,
                goods_items: JSON.stringify([{ goods_id: gid }])
            }, function (res) {
                if (res.code !== 200) {
                    if (isRevalidate) {
                        // 数量变少导致门槛不够 → 自动取消已应用券，让用户重新选
                        detailCouponState = { applied: false, code: '' };
                        $('#detailCouponInput').prop('readonly', false).val('');
                        $('#detailCouponApplyBtn').text('使用')
                            .removeClass('detail-coupon-btn--ghost').addClass('detail-coupon-btn--primary');
                        $('#detailCouponApplied').hide();
                        layer.msg(res.msg || '当前金额不满足券使用门槛，已取消使用');
                    } else {
                        layer.msg(res.msg || '优惠券不可用');
                    }
                    return;
                }
                var d = parseFloat(res.data.discount) || 0;
                detailCouponState = { applied: true, code: code };
                $('#detailCouponInput').prop('readonly', true);
                $('#detailCouponApplyBtn').text('更换')
                    .removeClass('detail-coupon-btn--primary').addClass('detail-coupon-btn--ghost');
                // discount (d) 是主货币值（服务端按主货币返回），显示时按访客币种展示
                var _cur = (window.EMSHOP_CURRENCY || { symbol: '¥', rate: 1 });
                $('#detailCouponApplied')
                    .find('.detail-coupon-applied-name').text(res.data.coupon.title).end()
                    .find('.detail-coupon-applied-saved').text('已优惠 ' + _cur.symbol + (d * _cur.rate).toFixed(2)).end()
                    .show();
                if (!isRevalidate) layer.msg('优惠券已应用');
            }, 'json').fail(function () { layer.msg('网络异常'); });
        };

        // 立即购买 —— 前端只收集数据不校验，后端统一返回错误信息
        // needs_address 商品在发 POST 前先弹层收集地址（登录选地址簿、游客手填），其它商品保持原流程
        $(document).on('click.goodsDetail', '#buyNowBtn', function () {
            var $btn = $(this);
            var goodsId = parseInt($btn.data('goods-id'));
            var quantity = parseInt($('#qtyInput').val()) || 1;
            var $activePay = $('.detail-payment-item.active');
            var paymentCode = $activePay.length ? $activePay.data('code') : '';

            // GuestFind 组件存在时收集查单字段；不存在或 DOM 里无 section 时返回空
            var guestFindData = (typeof GuestFind !== 'undefined') ? GuestFind.collectData() : {};

            var basePostData = $.extend({
                goods_id: goodsId,
                spec_id: currentSpecId(),
                quantity: quantity,
                payment_code: paymentCode,
                coupon_code: detailCouponState.applied ? detailCouponState.code : ''
            }, collectExtraFields(), guestFindData);

            if (opts.needsAddress) {
                openAddressPickerThenSubmit(basePostData, $btn);
            } else {
                submitOrder(basePostData, $btn);
            }
        });

        // —— 提交订单：统一出口（不需地址的商品 & 需地址的商品最终都经由此） ——
        function submitOrder(postData, $btn) {
            var origHtml = $btn.html();
            $btn.addClass('is-loading').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> 提交中...');
            $.post('?c=order&a=create', postData, function (res) {
                $btn.removeClass('is-loading').prop('disabled', false).html(origHtml);
                if (res.code === 200) {
                    var orderDetailUrl = '/user/order_detail.php?order_no=' + encodeURIComponent(res.data.order_no || '');
                    var guestFindUrl = '/user/find_order.php';
                    if (res.data.paid) {
                        layer.msg('支付成功');
                        location.href = opts.isGuest ? guestFindUrl : orderDetailUrl;
                    } else if (res.data.pay_url) {
                        location.href = res.data.pay_url;
                    } else {
                        location.href = opts.isGuest ? guestFindUrl : orderDetailUrl;
                    }
                } else {
                    layer.msg(res.msg || '下单失败');
                }
            }, 'json').fail(function () {
                $btn.removeClass('is-loading').prop('disabled', false).html(origHtml);
                layer.msg('网络异常');
            });
        }

        // —— 弹层收集地址 —— 登录走 radio；游客走手填 + cityAreaSelect
        function openAddressPickerThenSubmit(basePostData, $btn) {
            var isGuest = !!opts.isGuest;
            var addresses = opts.userAddresses || [];
            var defaultId = opts.defaultAddressId || 0;
            var html, afterOpen;

            if (!isGuest) {
                // —— 登录用户
                if (!addresses.length) {
                    layui.layer.confirm('您还没有收货地址，是否现在前往添加？', { btn: ['去添加', '取消'] }, function (idx) {
                        layui.layer.close(idx);
                        location.href = '/user/address.php';
                    });
                    return;
                }
                // 默认选中优先级：本次之前已选过的 > 服务端下发的默认地址
                var preselectId = parseInt(lastPickedAddressId || defaultId) || 0;
                html = '<div class="detail-addr-layer">';
                html += '<div class="detail-addr-list">';
                addresses.forEach(function (a) {
                    var checked = (parseInt(a.id) === preselectId) ? ' checked' : '';
                    var isDef = parseInt(a.is_default) === 1;
                    html += '<label class="detail-addr-item' + (checked ? ' is-active' : '') + '">'
                          + '<input type="radio" name="addr_pick" value="' + a.id + '"' + checked + '>'
                          + '<div class="detail-addr-item-body">'
                          + '<div class="detail-addr-item-head">'
                          + '<strong>' + escapeHtml(a.recipient) + '</strong>'
                          + '<span class="detail-addr-item-mobile">' + escapeHtml(a.mobile) + '</span>'
                          + (isDef ? '<span class="detail-addr-item-default">默认</span>' : '')
                          + '</div>'
                          + '<div class="detail-addr-item-text">'
                          + escapeHtml((a.province || '') + ' ' + (a.city || '') + ' ' + (a.district || '') + ' ' + (a.detail || ''))
                          + '</div>'
                          + '</div></label>';
                });
                html += '</div>';
                html += '<a href="/user/address.php" class="detail-addr-manage"><i class="fa fa-cog"></i> 管理我的收货地址</a>';
                html += '</div>';
                afterOpen = function () {};
            } else {
                // —— 游客：手填（不入地址簿，只进订单快照）
                // 回填缓存：上次填过的地址（比如后端报错"订单密码未填"返回后再打开时，避免重新填）
                var preset = lastGuestAddress || { recipient: '', mobile: '', province: '', city: '', district: '', detail: '' };
                html = '<div class="detail-addr-layer">'
                     + '<div class="detail-addr-form">'
                     + '  <div class="detail-addr-row">'
                     + '    <div class="detail-addr-field"><label>收件人</label><input type="text" id="detailAddrRecipient" maxlength="50" placeholder="姓名" class="detail-addr-input" value="' + escapeHtml(preset.recipient) + '"></div>'
                     + '    <div class="detail-addr-field"><label>手机号</label><input type="text" id="detailAddrMobile" maxlength="11" placeholder="11 位手机号" class="detail-addr-input" value="' + escapeHtml(preset.mobile) + '"></div>'
                     + '  </div>'
                     + '  <div class="detail-addr-field"><label>所在地区</label>'
                     + '    <div class="detail-addr-cascade">'
                     + '      <select class="cityAreaSelect-select detail-addr-select" id="detailAddrProv"><option value="">请选择省/直辖市</option></select>'
                     + '      <select class="cityAreaSelect-select detail-addr-select" id="detailAddrCity"><option value="">请选择城市/区</option></select>'
                     + '      <select class="cityAreaSelect-select detail-addr-select" id="detailAddrArea"><option value="">请选择区/县</option></select>'
                     + '    </div>'
                     + '  </div>'
                     + '  <div class="detail-addr-field"><label>详细地址</label>'
                     + '    <textarea id="detailAddrDetail" maxlength="255" rows="2" placeholder="街道、门牌号、楼栋等" class="detail-addr-textarea">' + escapeHtml(preset.detail) + '</textarea>'
                     + '  </div>'
                     + '  <div class="detail-addr-tip"><i class="fa fa-info-circle"></i> 若您已有账号，可 <a href="?c=login">登录</a> 使用已保存地址。</div>'
                     + '</div>'
                     + '</div>';
                afterOpen = function () {
                    if (typeof ProvinceCityAreaSelect === 'undefined') return;
                    new ProvinceCityAreaSelect({ addrValElem: ['detailAddrProv', 'detailAddrCity', 'detailAddrArea'] });
                    // 省市区回填：给 select 设 value 后 dispatch change，让 cityAreaSelect 内部联动填下级
                    if (preset.province) {
                        var pe = document.getElementById('detailAddrProv');
                        var ce = document.getElementById('detailAddrCity');
                        var ae = document.getElementById('detailAddrArea');
                        pe.value = preset.province; pe.dispatchEvent(new Event('change'));
                        if (preset.city) { ce.value = preset.city; ce.dispatchEvent(new Event('change')); }
                        if (preset.district) { ae.value = preset.district; ae.dispatchEvent(new Event('change')); }
                    }
                };
            }

            var idx = layui.layer.open({
                type: 1,
                title: '确认收货地址',
                area: [window.innerWidth >= 640 ? '520px' : '95%', 'auto'],
                btn: ['确认下单', '取消'],
                content: html,
                success: function (layero) {
                    afterOpen();
                    // radio 点击切 is-active（登录态）
                    layero.on('change', 'input[name="addr_pick"]', function () {
                        layero.find('.detail-addr-item').removeClass('is-active');
                        $(this).closest('.detail-addr-item').addClass('is-active');
                    });
                },
                yes: function (layerIdx, layero) {
                    var postData = $.extend({}, basePostData);
                    if (!isGuest) {
                        var $picked = layero.find('input[name="addr_pick"]:checked');
                        if (!$picked.length) { layer.msg('请选择收货地址'); return; }
                        postData.address_id = parseInt($picked.val());
                        // 缓存选中项，后端报错再弹层时保持之前的选择
                        lastPickedAddressId = postData.address_id;
                    } else {
                        var g = {
                            recipient: (layero.find('#detailAddrRecipient').val() || '').trim(),
                            mobile:    (layero.find('#detailAddrMobile').val()    || '').trim(),
                            province:   layero.find('#detailAddrProv').val()       || '',
                            city:       layero.find('#detailAddrCity').val()       || '',
                            district:   layero.find('#detailAddrArea').val()       || '',
                            detail:    (layero.find('#detailAddrDetail').val()    || '').trim()
                        };
                        if (!g.recipient || !g.mobile || !g.province || !g.city || !g.district || !g.detail) {
                            layer.msg('请填写完整的收货地址'); return;
                        }
                        if (!/^1\d{10}$/.test(g.mobile)) { layer.msg('请输入正确的手机号'); return; }
                        postData['guest_address[recipient]'] = g.recipient;
                        postData['guest_address[mobile]']    = g.mobile;
                        postData['guest_address[province]']  = g.province;
                        postData['guest_address[city]']      = g.city;
                        postData['guest_address[district]']  = g.district;
                        postData['guest_address[detail]']    = g.detail;
                        // 缓存本次填的 6 字段，下次弹层以此回填，后端报错（如订单密码未填）也不丢
                        lastGuestAddress = g;
                    }
                    layui.layer.close(layerIdx);
                    submitOrder(postData, $btn);
                }
            });
        }

        // 简易 HTML 转义，避免地址簿里带特殊字符炸模板
        function escapeHtml(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }
    }

    // 初始化规格选中态 + Viewer.js
    function initSelection() {
        currentSpec = pickDefaultSpec();

        if (currentSpec && specs.length > 1) {
            if (isMultiDim) {
                var defaultVids = currentSpec.value_ids || [];
                for (var vi = 0; vi < defaultVids.length; vi++) {
                    $('.spec-dim-btn[data-value-id="' + defaultVids[vi] + '"]').addClass('active');
                }
                updateDimDisabledState();
            } else {
                $('.spec-single-btn').removeClass('active');
                $('.spec-single-btn[data-spec-id="' + currentSpec.id + '"]').addClass('active');
            }
            updateSpecDisplay(currentSpec);
        } else if (specs.length > 1) {
            // 全部无库存：不选中，显示价格区间
            $('.spec-single-btn').removeClass('active');
            resetPriceRange();
            updateDimDisabledState();
        }

        // 标记无库存的单维规格为禁用
        $('.spec-single-btn').each(function () {
            var sid = parseInt($(this).data('spec-id'));
            var s = specMap[sid];
            if (s && !hasStock(s)) {
                $(this).addClass('disabled').prop('disabled', true);
            }
        });
    }

    function initViewer() {
        var viewerEl = document.getElementById('viewerImages');
        if (!viewerEl || typeof Viewer === 'undefined') return;
        if (!viewerEl.querySelector('img')) return;
        var viewer = new Viewer(viewerEl, {
            navbar: true,
            title: false,
            toolbar: { zoomIn: 1, zoomOut: 1, oneToOne: 1, reset: 1, prev: 1, next: 1, rotateLeft: 1, rotateRight: 1 }
        });
        $(document).on('click.goodsDetail', '#mainImage', function () {
            var idx = $('.detail-thumb.active').data('index') || 0;
            viewer.view(idx);
        });
    }

    return {
        init: function (options) {
            opts = options || {};
            specs = opts.specs || [];
            specMap = {};
            for (var i = 0; i < specs.length; i++) specMap[specs[i].id] = specs[i];
            isMultiDim = $('.spec-dim-btn').length > 0;

            bindEvents();
            initSelection();
            initViewer();

            // 支付页返回时，浏览器会自动 restore qty input 的用户输入值（但不触发 change 事件）。
            // initSelection 里 $qty.val(minBuy) 先把数量写成最小值 → renderCurrentPrice 按最小值算出单价，
            // 然后浏览器 form restoration 才把 qty 改回 2（用户离开前的值），顺序由浏览器决定，
            // 导致 qty=2 / specPrice=单价 不一致。
            // pageshow 在 form restoration 之后才触发，这里再调一次 renderCurrentPrice 按当前 qty 重算。
            // 用全局旗标防止 PJAX 多次 init 累积重复监听。
            if (!window.__goodsDetailPageshowBound) {
                window.__goodsDetailPageshowBound = true;
                window.addEventListener('pageshow', function () {
                    if (currentSpec) renderCurrentPrice();
                });
            }
        }
    };
})();
