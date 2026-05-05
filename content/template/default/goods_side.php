<?php
defined('EM_ROOT') || exit('access denied!');
/**
 * 商城侧边栏（page / goods_index 首页使用）
 *
 * 依赖控制器提供的变量：
 * - $recent_goods      最新商品列表（前5条）
 * - $hot_goods         热门商品列表（按销量排序，前5条）
 * - $goods_categories  商品分类列表（含 goods_count）
 */
?>
<aside class="blog-sidebar">

    <!-- 个人信息 -->
    <div class="sidebar-widget">
        <div class="sidebar-title">个人信息</div>
        <?php if (!empty($front_user)): ?>
        <div class="sidebar-user">
            <div class="sidebar-user-avatar">
                <?php if (!empty($front_user['avatar'])): ?>
                <img src="<?= htmlspecialchars($front_user['avatar']) ?>" alt="">
                <?php else: ?>
                <span class="sidebar-user-avatar--default"><i class="fa fa-user"></i></span>
                <?php endif; ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= htmlspecialchars($front_user['nickname'] ?? $front_user['username'] ?? '用户') ?></div>
                <div class="sidebar-user-meta"><?= htmlspecialchars($front_user['email'] ?? '') ?></div>
            </div>
        </div>
        <div class="sidebar-user-links">
            <a href="/user/"><i class="fa fa-user"></i> 个人中心</a>
            <a href="/user/order.php"><i class="fa fa-file-text-o"></i> 我的订单</a>
        </div>
        <?php else: ?>
        <div class="sidebar-user sidebar-user--guest">
            <div class="sidebar-user-avatar sidebar-user-avatar--guest">
                <i class="fa fa-user"></i>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name">欢迎光临</div>
                <div class="sidebar-user-meta">登录后享受更多服务</div>
            </div>
        </div>
        <div class="sidebar-user-links">
            <a href="?c=login" data-pjax class="sidebar-user-btn sidebar-user-btn--primary"><i class="fa fa-sign-in"></i> 登录</a>
            <a href="?c=register" data-pjax class="sidebar-user-btn"><i class="fa fa-user-plus"></i> 注册</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- 最新商品 -->
    <?php if (!empty($recent_goods)): ?>
    <div class="sidebar-widget">
        <div class="sidebar-title">最新商品</div>
        <div class="sidebar-goods-list">
            <?php foreach (array_slice($recent_goods, 0, 5) as $g): ?>
            <a <?= goods_card_href_attrs($g) ?> class="sidebar-goods-item">
                <div class="sidebar-goods-img">
                    <?php if (trim((string) ($g['image'] ?? '')) !== ''): ?>
                    <img src="<?= htmlspecialchars($g['image']) ?>" alt="<?= htmlspecialchars($g['name']) ?>">
                    <?php else: ?>
                    <div class="goods-no-image" aria-hidden="true"></div>
                    <?php endif; ?>
                </div>
                <div class="sidebar-goods-info">
                    <div class="sidebar-goods-name"><?= htmlspecialchars($g['name']) ?></div>
                    <div class="sidebar-goods-price">
                        <span class="price"><?= Currency::displayMain((float) $g['price']) ?></span>
                        <?php if (!empty($g['original_price'])): ?>
                        <span class="price-original"><?= Currency::displayMain((float) $g['original_price']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 热门商品 -->
    <?php if (!empty($hot_goods)): ?>
    <div class="sidebar-widget">
        <div class="sidebar-title">热门商品</div>
        <div class="sidebar-goods-list">
            <?php foreach (array_slice($hot_goods, 0, 5) as $g): ?>
            <a <?= goods_card_href_attrs($g) ?> class="sidebar-goods-item">
                <div class="sidebar-goods-img">
                    <?php if (trim((string) ($g['image'] ?? '')) !== ''): ?>
                    <img src="<?= htmlspecialchars($g['image']) ?>" alt="<?= htmlspecialchars($g['name']) ?>">
                    <?php else: ?>
                    <div class="goods-no-image" aria-hidden="true"></div>
                    <?php endif; ?>
                </div>
                <div class="sidebar-goods-info">
                    <div class="sidebar-goods-name"><?= htmlspecialchars($g['name']) ?></div>
                    <div class="sidebar-goods-price">
                        <span class="price"><?= Currency::displayMain((float) $g['price']) ?></span>
                        <?php if (!empty($g['original_price'])): ?>
                        <span class="price-original"><?= Currency::displayMain((float) $g['original_price']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 商品分类 -->
    <?php if (!empty($goods_categories)): ?>
    <div class="sidebar-widget">
        <div class="sidebar-title">商品分类</div>
        <div class="sidebar-cat-list">
            <?php foreach ($goods_categories as $cat): ?>
            <div class="sidebar-cat-group">
                <div class="sidebar-cat-parent-row">
                    <a href="<?= url_goods_category($cat) ?>" class="sidebar-cat-parent">
                        <?php if (!empty($cat['icon'])): ?>
                        <img class="sidebar-cat-icon" src="<?= htmlspecialchars($cat['icon']) ?>" alt="">
                        <?php endif; ?>
                        <span class="sidebar-cat-name"><?= htmlspecialchars($cat['name']) ?></span>
                        <?php if (empty($cat['children'])): ?>
                        <span class="sidebar-cat-count"><?= (int) $cat['goods_count'] ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if (!empty($cat['children'])): ?>
                    <span class="sidebar-cat-arrow"><i class="fa fa-chevron-down"></i></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($cat['children'])): ?>
                <div class="sidebar-cat-children" style="display:none;">
                    <?php foreach ($cat['children'] as $child): ?>
                    <a href="<?= url_goods_category($child) ?>" class="sidebar-cat-child">
                        <span class="sidebar-cat-name"><?= htmlspecialchars($child['name']) ?></span>
                        <span class="sidebar-cat-count"><?= (int) $child['goods_count'] ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="sidebar-widget">
        <div class="sidebar-title">商品分类</div>
        <div class="sidebar-list">
            <span style="color:#adb5bd; font-size:13px;">暂无分类</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- 标签 -->
    <?php if (!empty($popular_tags)): ?>
    <div class="sidebar-widget">
        <div class="sidebar-title">标签</div>
        <div class="sidebar-tag-cloud">
            <?php foreach ($popular_tags as $tag): ?>
            <a href="<?= url_goods_tag((int) $tag['id']) ?>" class="sidebar-tag" data-pjax>
                <?= htmlspecialchars($tag['name']) ?>
                <span class="tag-count"><?= (int) $tag['goods_count'] ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</aside>
