<?php
defined('EM_ROOT') || exit('access denied!');
/**
 * 博客侧边栏（blog_index / blog 详情共用）
 *
 * 依赖控制器提供的变量：
 * - $front_user        当前登录用户（或 null）
 * - $recent_articles   最新文章列表（前5条）
 * - $blog_categories   文章分类列表（二级树，含 article_count）
 * - $popular_articles  热门文章列表（含 id / title）
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

    <!-- 最新文章 -->
    <?php if (!empty($recent_articles)): ?>
    <div class="sidebar-widget">
        <div class="sidebar-title">最新文章</div>
        <div class="sidebar-article-list">
            <?php foreach (array_slice($recent_articles, 0, 5) as $a): ?>
            <a href="<?= url_blog((int) $a['id']) ?>" class="sidebar-article-item" data-pjax>
                <?php if (!empty($a['image'])): ?>
                <div class="sidebar-article-img"><img src="<?= htmlspecialchars($a['image']) ?>" alt="<?= htmlspecialchars($a['title']) ?>"></div>
                <?php else: ?>
                <div class="sidebar-article-img sidebar-article-img--empty"><i class="fa fa-file-text-o"></i></div>
                <?php endif; ?>
                <div class="sidebar-article-info">
                    <div class="sidebar-article-title"><?= htmlspecialchars($a['title']) ?></div>
                    <div class="sidebar-article-date"><?= htmlspecialchars($a['date']) ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 热门文章 -->
    <?php if (!empty($popular_articles)): ?>
    <div class="sidebar-widget">
        <div class="sidebar-title">热门文章</div>
        <div class="sidebar-posts">
            <?php foreach ($popular_articles as $i => $a): ?>
            <a href="<?= url_blog((int) $a['id']) ?>" class="sidebar-post" data-pjax>
                <span class="sidebar-rank<?= $i < 3 ? ' top' : '' ?>"><?= $i + 1 ?></span>
                <span class="sidebar-post-title"><?= htmlspecialchars($a['title']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 文章分类（二级树 + 手风琴折叠） -->
    <?php if (!empty($blog_categories)): ?>
    <div class="sidebar-widget">
        <div class="sidebar-title">文章分类</div>
        <div class="sidebar-cat-list">
            <?php foreach ($blog_categories as $cat): ?>
            <div class="sidebar-cat-group">
                <div class="sidebar-cat-parent-row">
                    <a href="<?= url_blog_list(['category_id' => $cat['id']]) ?>" class="sidebar-cat-parent" data-pjax>
                        <?php if (!empty($cat['icon'])): ?>
                        <img class="sidebar-cat-icon" src="<?= htmlspecialchars($cat['icon']) ?>" alt="">
                        <?php endif; ?>
                        <span class="sidebar-cat-name"><?= htmlspecialchars($cat['name']) ?></span>
                        <?php if (empty($cat['children'])): ?>
                        <span class="sidebar-cat-count"><?= (int) $cat['article_count'] ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if (!empty($cat['children'])): ?>
                    <span class="sidebar-cat-arrow"><i class="fa fa-chevron-down"></i></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($cat['children'])): ?>
                <div class="sidebar-cat-children" style="display:none;">
                    <?php foreach ($cat['children'] as $child): ?>
                    <a href="<?= url_blog_list(['category_id' => $child['id']]) ?>" class="sidebar-cat-child" data-pjax>
                        <span class="sidebar-cat-name"><?= htmlspecialchars($child['name']) ?></span>
                        <span class="sidebar-cat-count"><?= (int) $child['article_count'] ?></span>
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
        <div class="sidebar-title">文章分类</div>
        <div class="sidebar-list">
            <span style="color:#adb5bd; font-size:13px;">暂无分类</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- 标签云 -->
    <?php if (!empty($popular_tags)): ?>
    <div class="sidebar-widget">
        <div class="sidebar-title">标签</div>
        <div class="sidebar-tag-cloud">
            <?php foreach ($popular_tags as $tag): ?>
            <a href="<?= url_blog_tag((int) $tag['id']) ?>" class="sidebar-tag" data-pjax>
                <?= htmlspecialchars($tag['name']) ?>
                <span class="tag-count"><?= (int) $tag['article_count'] ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</aside>
