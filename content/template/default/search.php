<?php
defined('EM_ROOT') || exit('access denied!');

/**
 * 搜索页 / 搜索结果页。
 *
 * 本模板只有渲染：取数在 module.php 的 template_search_data()。关键词与搜索类型由
 * module.php 解析后 assign 成 $search_q 传下来 —— 页面标题必须早于 body 渲染，
 * 所以参数解析不能在模板里做。
 */
$_d = template_search_data(is_array($search_q ?? null) ? $search_q : []);

// 与改造前同名的变量，下面正文一个字都不用改
$keyword         = $_d['keyword'];
$search_type     = $_d['search_type'];
$results         = $_d['results'];
$article_results = $_d['article_results'];
$result_count    = $_d['result_count'];

// 商品卡片上的展示开关
$_shopDispStock = (string) Config::get('shop_display_stock', '1') !== '0';
$_shopDispSales = (string) Config::get('shop_display_sales', '1') !== '0';
$_blockSoldOut = shop_block_sold_out_access();

// 结果类型切换（关键词为空时不显示这一排）
$searchTabs = ['all' => '全部', 'goods' => '商品', 'article' => '文章'];
?>
<!-- 搜索结果（SearchController::display） -->
<div class="page-body">

    <!-- 面包屑 -->
    <div class="breadcrumb">
        <a href="<?= url_home() ?>" data-pjax>首页</a>
        <span class="sep">/</span>
        搜索
    </div>

    <!-- 搜索区：输入框 + 结果摘要 + 类型切换 -->
    <div class="search-hero">
        <form class="search-box" method="get" data-pjax>
            <input type="hidden" name="c" value="search">
            <input type="hidden" name="type" value="<?= htmlspecialchars($search_type) ?>">
            <div class="search-box__field">
                <i class="fa fa-search search-box__ico" aria-hidden="true"></i>
                <input type="text" name="q" class="search-input" placeholder="输入关键词搜索..."
                       maxlength="64" value="<?= htmlspecialchars($keyword) ?>">
            </div>
            <button type="submit" class="btn btn-primary search-submit">搜索</button>
        </form>

        <?php if ($keyword !== ''): ?>
        <div class="search-result-head">
            <div class="search-result-count">
                找到 <strong><?= (int) $result_count ?></strong> 个与「<em><?= htmlspecialchars($keyword) ?></em>」相关的结果
            </div>
            <div class="search-type-tabs" role="tablist">
                <?php foreach ($searchTabs as $_tabKey => $_tabLabel): ?>
                <a href="<?= url_append(url_search($keyword), ['type' => $_tabKey]) ?>" data-pjax role="tab"
                   aria-selected="<?= $search_type === $_tabKey ? 'true' : 'false' ?>"
                   class="search-type-tab<?= $search_type === $_tabKey ? ' active' : '' ?>"><?= $_tabLabel ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- 商品结果（type=all 或 type=goods） -->
    <?php if ($search_type !== 'article' && !empty($results)): ?>
    <section class="search-sec">
        <div class="search-sec__head">
            <div class="search-sec__title">
                <i class="fa fa-cube" aria-hidden="true"></i> 商品
                <span class="search-sec__count"><?= count($results) ?></span>
            </div>
        </div>
        <div class="goods-grid">
            <?php foreach ($results as $item): ?>
            <?php $isSoldOut = ((int) ($item['stock'] ?? 0)) === 0; ?>
            <a <?= goods_card_href_attrs($item) ?> class="card goods-card<?= $isSoldOut ? ' stock-empty-box' : '' ?><?= ($isSoldOut && $_blockSoldOut) ? ' is-blocked' : '' ?>">
                <div class="card-img">
                    <?php if (trim((string) ($item['image'] ?? '')) !== ''): ?>
                    <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                    <?php else: ?>
                    <div class="goods-no-image" aria-hidden="true"></div>
                    <?php endif; ?>
                    <?php if ($isSoldOut): ?>
                    <span class="goods-soldout-stamp" aria-hidden="true"><span class="goods-soldout-stamp__text">已售罄</span></span>
                    <?php endif; ?>
                    <?php if (($item['delivery_type'] ?? '') === 'auto'): ?>
                    <span class="goods-badge goods-badge--auto">自动发货</span>
                    <?php elseif (($item['delivery_type'] ?? '') === 'manual'): ?>
                    <span class="goods-badge goods-badge--manual">人工发货</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="card-title"><?= htmlspecialchars($item['name']) ?></div>
                    <?php if ($_shopDispStock || $_shopDispSales): ?>
                    <div class="card-stats">
                        <?php if ($_shopDispStock): ?>
                        <span>库存 <?= htmlspecialchars((string) ($item['stock_text'] ?? '0')) ?></span>
                        <?php endif; ?>
                        <?php if ($_shopDispSales): ?>
                        <span>销量 <?= (int) ($item['sold'] ?? 0) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="card-bottom">
                        <span class="price"><?= Currency::displayMain((float) $item['price']) ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- 文章结果（type=all 或 type=article）：卡片与文章列表页同一套 -->
    <?php if ($search_type !== 'goods' && !empty($article_results)): ?>
    <section class="search-sec">
        <div class="search-sec__head">
            <div class="search-sec__title">
                <i class="fa fa-file-text-o" aria-hidden="true"></i> 文章
                <span class="search-sec__count"><?= count($article_results) ?></span>
            </div>
        </div>
        <div class="article-list">
            <?php foreach ($article_results as $a): ?>
            <a href="<?= url_blog((int) $a['id']) ?>" class="card blog-article-card" data-pjax>
                <div class="blog-article-img">
                    <?php if (!empty($a['image'])): ?>
                    <img src="<?= htmlspecialchars($a['image']) ?>" alt="<?= htmlspecialchars($a['title']) ?>">
                    <?php endif; ?>
                </div>
                <div class="blog-article-body">
                    <div class="blog-article-title"><?= htmlspecialchars($a['title']) ?></div>
                    <div class="blog-article-excerpt"><?= htmlspecialchars($a['excerpt']) ?></div>
                    <?php if (!empty($a['tags'])): ?>
                    <div class="blog-article-tags">
                        <?php foreach ($a['tags'] as $tag): ?>
                        <span class="article-tag-label"><?= htmlspecialchars($tag['name']) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div class="blog-article-meta">
                        <span><i class="fa fa-calendar-o"></i> <?= htmlspecialchars($a['date']) ?></span>
                        <span><i class="fa fa-folder-o"></i> <?= htmlspecialchars($a['category'] ?? '未分类') ?></span>
                        <span><i class="fa fa-eye"></i> <?= (int) ($a['views'] ?? 0) ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- 空态：还没输关键词 / 什么都没搜到 -->
    <?php if ($keyword === ''): ?>
    <div class="card empty-state">
        <div class="empty-icon">&#128269;</div>
        <h3>输入关键词开始搜索</h3>
        <p>商品名称、文章标题都能搜</p>
    </div>
    <?php elseif ((int) $result_count === 0): ?>
    <div class="card empty-state">
        <div class="empty-icon">&#128269;</div>
        <h3>没有找到与「<?= htmlspecialchars($keyword) ?>」相关的内容</h3>
        <p>
            换个关键词试试，或者直接逛
            <a href="<?= url_goods_list() ?>" data-pjax>全部商品</a> /
            <a href="<?= url_blog_list() ?>" data-pjax>全部文章</a>
        </p>
    </div>
    <?php endif; ?>

</div>
