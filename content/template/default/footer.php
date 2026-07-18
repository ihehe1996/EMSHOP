<?php
/**
 * 测试模板 - 通用底部
 */
?>
</div><!-- #main -->

<!-- 底部：极简版权 + ICP 备案 + 第三方统计代码注入 -->
<footer class="site-footer">
<div class="wrapper">
    <div class="site-footer__copy">
        <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($site_name ?? 'EMSHOP') ?></span>
        <span>Powered by <?= htmlspecialchars($site_name ?? 'EMSHOP') ?></span>
    </div>
    <?php if (!empty($site_icp)): ?>
    <div class="site-footer__icp">
        <?php
        // 中国大陆备案号惯例：跳工信部公示页（不强制，让用户能自助核验）
        $_icpHref = 'https://beian.miit.gov.cn/';
        ?>
        <a href="<?= htmlspecialchars($_icpHref) ?>" target="_blank" rel="nofollow noopener">
            <?= htmlspecialchars($site_icp) ?>
        </a>
    </div>
    <?php endif; ?>
</div>
</footer>

<!-- 搜索弹窗：放在 body 末尾，避免与顶栏内侧滑菜单（transform/stacking）抢层叠或撑出横向滚动 -->
<div class="search-modal" id="searchModal">
    <div class="search-modal-mask"></div>
    <div class="search-modal-stage">
        <div class="search-modal-body">
            <div class="search-modal-tabs">
                <button type="button" class="search-modal-tab active" data-type="all">全部</button>
                <button type="button" class="search-modal-tab" data-type="goods">商品</button>
                <button type="button" class="search-modal-tab" data-type="article">文章</button>
            </div>
            <form id="searchModalForm" class="search-modal-bar">
                <input type="text" id="searchModalInput" placeholder="输入关键词搜索..." autocomplete="off">
                <button type="submit">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </button>
            </form>
            <div class="search-modal-hint">按 ESC 关闭</div>
        </div>
    </div>
</div>

<!-- 移动端侧滑菜单：移出 header；关闭时 display:none，打开时由 JS 加 .mobile-nav--shown 再滑入 -->
<div class="mobile-nav-backdrop" id="mobileNavBackdrop" aria-hidden="true"></div>
<div class="mobile-nav" id="mobileNav">
    <div class="mobile-nav-inner">
        <?php
        if (empty($nav_items) || !is_array($nav_items)) {
            $nav_items = [];
        }
        $mobileIcons = ['首页' => 'fa-home', '商城' => 'fa-shopping-bag', '博客' => 'fa-pencil'];
        foreach ($nav_items as $item):
            $isActive = ($item['text'] === '首页' && $nav_id === 'home')
                     || ($item['text'] === '商城' && $nav_id === 'goods')
                     || ($item['text'] === '博客' && $nav_id === 'blog');
            $active = $isActive ? ' active' : '';
            $icon = $mobileIcons[$item['text']] ?? 'fa-link';
            $hasChildren = !empty($item['children']);
        ?>
        <?php if ($hasChildren): ?>
        <div class="mobile-nav-group">
            <div class="mobile-nav-item mobile-nav-toggle<?= $active ?>">
                <i class="fa <?= $icon ?>"></i>
                <span><?= htmlspecialchars($item['text']) ?></span>
                <i class="fa fa-chevron-down mobile-nav-arrow"></i>
            </div>
            <div class="mobile-nav-sub">
                <?php foreach ($item['children'] as $child): ?>
                <?php
                    $_cp = '/';
                    $_cip = parse_url((string) ($child['url'] ?? ''), PHP_URL_PATH);
                    if (is_string($_cip) && $_cip !== '') {
                        $_cp = $_cip;
                    }
                    $_cp = '/' . trim($_cp, '/');
                    $_childNavPathAttr = ($_cp !== '/') ? ' data-nav-path="' . htmlspecialchars($_cp, ENT_QUOTES, 'UTF-8') . '"' : '';
                ?>
                <a href="<?= htmlspecialchars($child['url']) ?>" data-pjax class="mobile-nav-sub-item"<?= $_childNavPathAttr ?>><?= htmlspecialchars($child['text']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <?php
            $_ip = '/';
            $_p = parse_url((string) ($item['url'] ?? ''), PHP_URL_PATH);
            if (is_string($_p) && $_p !== '') {
                $_ip = $_p;
            }
            $_ip = '/' . trim($_ip, '/');
            $_itemNavPathAttr = ($_ip !== '/') ? ' data-nav-path="' . htmlspecialchars($_ip, ENT_QUOTES, 'UTF-8') . '"' : '';
        ?>
        <a href="<?= htmlspecialchars($item['url']) ?>" data-pjax class="mobile-nav-item<?= $active ?>"<?= $_itemNavPathAttr ?>>
            <i class="fa <?= $icon ?>"></i><span><?= htmlspecialchars($item['text']) ?></span>
        </a>
        <?php endif; ?>
        <?php endforeach; ?>
        <div class="mobile-nav-divider"></div>
        <?php if (!empty($front_user)): ?>
        <a href="/user/" class="mobile-nav-item">
            <i class="fa fa-user"></i><span>个人中心</span>
        </a>
        <a href="/user/order.php" class="mobile-nav-item">
            <i class="fa fa-file-text-o"></i><span>我的订单</span>
        </a>
        <a href="?c=login&a=logout" class="mobile-nav-item mobile-nav-logout">
            <i class="fa fa-sign-out"></i><span>退出登录</span>
        </a>
        <?php else: ?>
        <a href="?c=login" data-pjax class="mobile-nav-item">
            <i class="fa fa-sign-in"></i><span>登录</span>
        </a>
        <?php if (!empty($user_register_enabled)): ?>
        <a href="?c=register" data-pjax class="mobile-nav-item">
            <i class="fa fa-user-plus"></i><span>注册</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
// 第三方统计代码（百度统计 / Google Analytics / 自定义脚本）
// 直接 raw 输出（管理员粘贴的 <script> 片段不做转义）；商户站不注入主站统计，避免混淆数据归属
if (!empty($site_statistical_code) && MerchantContext::isMaster()) {
    echo $site_statistical_code;
}
?>

<script>
/**
 * PJAX 后同步移动端侧栏导航高亮（整页刷新由 PHP 输出 active，PJAX 只换 #main 故需 JS 同步）。
 */
function syncMobileNavActive(currentFull, navId) {
    var $mob = $('#mobileNav');
    if (!$mob.length) return;

    $mob.find('.mobile-nav-item, .mobile-nav-toggle, .mobile-nav-sub-item').removeClass('active');

    var currentNavPath = ($('#main').attr('data-nav-current-path') || '').trim();
    if (!currentNavPath && currentFull) {
        try {
            var nx = document.createElement('a');
            nx.href = currentFull;
            currentNavPath = '/' + String(nx.pathname || '/').replace(/^\/+|\/+$/g, '');
        } catch (e0) {
            currentNavPath = '';
        }
    }
    if (currentNavPath && currentNavPath !== '/') {
        var mPathHits = [];
        $mob.find('a[data-nav-path]').each(function () {
            if (($(this).attr('data-nav-path') || '').trim() === currentNavPath) {
                mPathHits.push(this);
            }
        });
        if (mPathHits.length === 1) {
            $(mPathHits[0]).addClass('active');
            return;
        }
    }

    var subHits = [];
    $mob.find('a.mobile-nav-sub-item[href]').each(function () {
        try {
            var b = document.createElement('a');
            b.href = $(this).attr('href') || '';
            if (b.href.split('#')[0] === currentFull) {
                subHits.push(this);
            }
        } catch (e) {}
    });
    if (subHits.length === 1) {
        $(subHits[0]).addClass('active');
        return;
    }

    var labelMap = { home: '首页', goods: '商城', blog: '博客' };
    var want = labelMap[navId] || '';
    if (!want) return;

    $mob.find('.mobile-nav-toggle, a.mobile-nav-item[href]').each(function () {
        var $el = $(this);
        if ($el.hasClass('mobile-nav-sub-item')) return;
        var label = $el.find('> span').first().text().trim();
        if (label === want) {
            $el.addClass('active');
        }
    });
}

/**
 * 根据当前 URL + #main[data-nav-id] 更新主导航 active（与 module.php 初次渲染一致）。
 */
function updateNavActive(url) {
    var effective = (url && String(url).trim()) ? String(url).trim() : window.location.href;
    var currentFull = '';
    try {
        var ca = document.createElement('a');
        ca.href = effective;
        currentFull = ca.href.split('#')[0];
    } catch (e) {
        currentFull = String(effective).split('#')[0];
    }

    var navId = ($('#main').attr('data-nav-id') || '').trim();

    var currentNavPath = ($('#main').attr('data-nav-current-path') || '').trim();
    if (!currentNavPath) {
        try {
            var ap0 = document.createElement('a');
            ap0.href = effective;
            currentNavPath = '/' + String(ap0.pathname || '/').replace(/^\/+|\/+$/g, '');
        } catch (e0) {
            currentNavPath = '/';
        }
    }

    var $nav = $('.main-nav');
    if ($nav.length) {
        $nav.find('a').removeClass('active');

        if (navId === 'home' || navId === 'goods' || navId === 'blog') {
            var want = navId === 'home' ? '首页' : (navId === 'goods' ? '商城' : '博客');
            $nav.find('> a[href], > .nav-dropdown > a[href]').each(function () {
                if ($(this).text().trim() === want) {
                    $(this).addClass('active');
                }
            });
            syncMobileNavActive(currentFull, navId);
            return;
        }

        if (currentNavPath && currentNavPath !== '/') {
            var navPathHits = [];
            $nav.find('a[data-nav-path]').each(function () {
                var dp = ($(this).attr('data-nav-path') || '').trim();
                if (dp && dp === currentNavPath) {
                    navPathHits.push(this);
                }
            });
            if (navPathHits.length === 1) {
                $(navPathHits[0]).addClass('active');
                syncMobileNavActive(currentFull, navId);
                return;
            }
        }

        var exactHits = [];
        $nav.find('a[href]').each(function () {
            var href = $(this).attr('href') || '';
            try {
                var b = document.createElement('a');
                b.href = href;
                if (b.href.split('#')[0] === currentFull) {
                    exactHits.push(this);
                }
            } catch (e2) {}
        });
        if (exactHits.length === 1) {
            $(exactHits[0]).addClass('active');
            syncMobileNavActive(currentFull, navId);
            return;
        }

        var currentPath = '/';
        try {
            var ap = document.createElement('a');
            ap.href = effective;
            currentPath = ap.pathname || '/';
        } catch (e3) {
            currentPath = String(effective).split('?')[0].split('#')[0];
        }
        currentPath = '/' + currentPath.replace(/^\/+|\/+$/g, '');

        var pathHits = [];
        $nav.find('a[href]').each(function () {
            var href = $(this).attr('href') || '';
            var itemPath = '/';
            try {
                var bp = document.createElement('a');
                bp.href = href;
                itemPath = bp.pathname || '/';
            } catch (e4) {}
            itemPath = '/' + itemPath.replace(/^\/+|\/+$/g, '');
            if (itemPath !== '/' && itemPath === currentPath) {
                pathHits.push(this);
            }
        });
        if (pathHits.length === 1) {
            $(pathHits[0]).addClass('active');
        }
    }

    syncMobileNavActive(currentFull, navId);
}

// ============================================================
// PJAX 初始化
// ============================================================
(function () {
    var $bar = $('#pjaxBar');

    // 禁用浏览器默认的 scroll restoration
    if (window.history.scrollRestoration !== undefined) {
        window.history.scrollRestoration = 'manual';
    }

    // 对所有带 data-pjax 的链接启用 PJAX
    $(document).pjax('[data-pjax]', '#main', {
        fragment: '#main',
        timeout: 10000,
        scrollTo: false
    });

    // 对 #main 容器内的所有内部链接启用 PJAX
    $(document).on('click', '#main a[href]', function (e) {
        var href = this.href;
        if (!href || (href.indexOf('//') > -1 && href.indexOf(location.host) === -1)) return;
        if (href.indexOf('#') === 0) return;
        if (this.hasAttribute('download')) return;
        if (/^(mailto|tel|javascript):/i.test(href)) return;
        if (this.hasAttribute('data-pjax')) return;

        $.pjax.click(e, {
            url: href,
            container: '#main',
            fragment: '#main',
            timeout: 10000,
            scrollTo: false
        });
    });

    // 对带 data-pjax 的表单启用 PJAX 提交
    $(document).on('submit', 'form[data-pjax]', function (e) {
        $.pjax.submit(e, { container: '#main', fragment: '#main', timeout: 10000 });
    });

    var pjaxBarHideTimer = null;
    var pjaxBarOnTransEnd = null;

    function clearPjaxBarHideSchedule() {
        if (pjaxBarHideTimer) {
            clearTimeout(pjaxBarHideTimer);
            pjaxBarHideTimer = null;
        }
        var el = $bar[0];
        if (el && pjaxBarOnTransEnd) {
            el.removeEventListener('transitionend', pjaxBarOnTransEnd);
            pjaxBarOnTransEnd = null;
        }
    }

    /** 进度条瞬时归零（避免去掉 .done/.running 后 width 从 100% 过渡回 0 的「回退」感） */
    function resetPjaxBarInstant() {
        var el = $bar[0];
        if (!el) return;
        el.style.transition = 'none';
        $bar.removeClass('running done');
        void el.offsetWidth;
        el.style.transition = '';
    }

    /** 等拉到 100%（.done 的 width 过渡结束）后再隐藏 */
    function schedulePjaxBarHideAfterFull() {
        clearPjaxBarHideSchedule();
        var el = $bar[0];
        if (!el) return;
        if (!$bar.hasClass('done')) {
            resetPjaxBarInstant();
            return;
        }
        var settled = false;
        function settle() {
            if (settled) return;
            settled = true;
            clearPjaxBarHideSchedule();
            resetPjaxBarInstant();
        }
        pjaxBarOnTransEnd = function (e) {
            if (e.target !== el) return;
            if (e.propertyName !== 'width') return;
            settle();
        };
        el.addEventListener('transitionend', pjaxBarOnTransEnd);
        pjaxBarHideTimer = setTimeout(settle, 480);
    }

    // 进度条：开始
    $(document).on('pjax:send', function () {
        clearPjaxBarHideSchedule();
        $bar.removeClass('done').addClass('running');
    });

    // 进度条：完成
    $(document).on('pjax:complete', function () {
        $bar.removeClass('running').addClass('done');
    });

    // 页面标题 & 导航更新
    $(document).on('pjax:success', function (event, data, status, xhr, options) {
        $bar.removeClass('running').addClass('done');
        var rawTitle = xhr.getResponseHeader('X-PJAX-Title');
        if (rawTitle) {
            try { rawTitle = decodeURIComponent(rawTitle); } catch (e) {}
            if (rawTitle) document.title = rawTitle;
        }
        // PJAX 不会替换 #main 自身的属性，需要从响应头同步 data-nav-id
        var navIdHeader = xhr.getResponseHeader('X-PJAX-Nav-Id');
        if (navIdHeader === null || navIdHeader === '') {
            navIdHeader = xhr.getResponseHeader('x-pjax-nav-id');
        }
        if (navIdHeader !== null && navIdHeader !== undefined) {
            try { navIdHeader = decodeURIComponent(navIdHeader); } catch (e) {}
            $('#main').attr('data-nav-id', navIdHeader || '');
        }
        var navPathHeader = xhr.getResponseHeader('X-PJAX-Current-Path');
        if (navPathHeader === null || navPathHeader === '') {
            navPathHeader = xhr.getResponseHeader('x-pjax-current-path');
        }
        if (navPathHeader !== null && navPathHeader !== undefined) {
            try { navPathHeader = decodeURIComponent(navPathHeader); } catch (e) {}
            $('#main').attr('data-nav-current-path', navPathHeader || '');
        }
        updateNavActive(window.location.href || options.url);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // 错误/超时回退
    $(document).on('pjax:error', function (xhr, textStatus, error, options) {
        clearPjaxBarHideSchedule();
        resetPjaxBarInstant();
        if (textStatus === 'timeout') {
            var msg = document.createElement('div');
            msg.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.4);display:flex;align-items:center;justify-content:center;z-index:99999;';
            msg.innerHTML = '<div style="background:#fff;padding:24px 40px;border-radius:8px;text-align:center;"><div style="font-size:15px;margin-bottom:8px;">请求超时</div><div style="color:#999;font-size:13px;">正在跳转...</div></div>';
            document.body.appendChild(msg);
            setTimeout(function () {
                document.body.removeChild(msg);
                window.location.href = options.url;
            }, 1200);
        }
        return false;
    });

    // 清理进度条：先让 .done 过渡到 100%，再瞬时隐藏（无回退动画）
    $(document).on('pjax:end', function () {
        schedulePjaxBarHideAfterFull();
    });

    // 退出登录（直接跳转，刷新整页以更新页头状态）
    $(document).on('click', 'a[href*="a=logout"]', function (e) {
        e.preventDefault();
        location.href = this.href;
    });

    // 侧边栏分类手风琴折叠（全局委托，一次绑定，商品/博客侧边栏共用）
    $(document).on('click', '.sidebar-cat-arrow', function(){
        var $arrow = $(this);
        var $group = $arrow.closest('.sidebar-cat-group');
        var $children = $group.find('.sidebar-cat-children');
        if (!$children.length) return;

        if ($arrow.hasClass('is-open')) {
            $arrow.removeClass('is-open');
            $children.stop(true).slideUp(250);
        } else {
            // 同一侧边栏内只展开一个
            $group.siblings('.sidebar-cat-group').each(function(){
                var $other = $(this);
                var $otherArrow = $other.find('.sidebar-cat-arrow.is-open');
                if ($otherArrow.length) {
                    $otherArrow.removeClass('is-open');
                    $other.find('.sidebar-cat-children').stop(true).slideUp(250);
                }
            });
            $arrow.addClass('is-open');
            $children.stop(true).slideDown(250);
        }
    });
})();

// ============================================================
// 搜索弹窗
// ============================================================
(function () {
    var $modal = $('#searchModal');
    var $input = $('#searchModalInput');
    var searchType = 'all';

    // 打开弹窗
    $('#searchToggle').on('click', function (e) {
        e.preventDefault();
        // 若侧滑菜单已打开，先关掉（避免两层 fixed + body 锁滚动相互干扰）
        if ($('#menuToggle').hasClass('active')) {
            $('#menuToggle').trigger('click');
        }
        $modal.addClass('active');
        $('body').addClass('search-modal-open');
        setTimeout(function () { $input.focus(); }, 100);
    });

    // 关闭弹窗
    function closeModal() {
        $modal.removeClass('active');
        $('body').removeClass('search-modal-open');
    }
    // .search-modal-stage 盖在 mask 之上，点「暗色区域」实际点在 stage 上，mask 的 click 收不到；委托到 #searchModal，点在 .search-modal-body 外则关闭
    $modal.on('click', function (e) {
        if (!$(e.target).closest('.search-modal-body').length) {
            closeModal();
        }
    });
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $modal.hasClass('active')) closeModal();
    });

    // 切换搜索类型
    $modal.on('click', '.search-modal-tab', function () {
        $modal.find('.search-modal-tab').removeClass('active');
        $(this).addClass('active');
        searchType = $(this).data('type');
    });

    // 提交搜索
    $('#searchModalForm').on('submit', function (e) {
        e.preventDefault();
        var q = $.trim($input.val());
        if (!q) return;
        closeModal();
        var url = '?c=search&q=' + encodeURIComponent(q) + '&type=' + searchType;
        $.pjax({ url: url, container: '#main', fragment: '#main', timeout: 10000, scrollTo: false });
    });

    // PJAX 导航时自动关闭弹窗
    $(document).on('pjax:start', closeModal);
})();

// ============================================================
// 页头：滚动阴影 + 移动端菜单
// ============================================================
(function () {
    var $header = $('#siteHeader');
    var $menuBtn = $('#menuToggle');
    var $mobileNav = $('#mobileNav');
    var $backdrop = $('#mobileNavBackdrop');

    var mobileNavCloseTimer = null;
    var MOBILE_NAV_DRAWER_MS = 340;

    /** @param {boolean} open @param {boolean} [instant] PJAX 等场景立即收起、去掉占位类 */
    function setMobileDrawerOpen(open, instant) {
        if (!open && instant) {
            if (mobileNavCloseTimer) {
                clearTimeout(mobileNavCloseTimer);
                mobileNavCloseTimer = null;
            }
            $menuBtn.removeClass('active');
            $mobileNav.removeClass('open mobile-nav--shown');
            $backdrop.removeClass('is-open mobile-nav--shown').attr('aria-hidden', 'true');
            $('body').removeClass('mobile-nav-drawer-open');
            return;
        }
        $menuBtn.toggleClass('active', open);
        if (open) {
            $('body').addClass('mobile-nav-drawer-open');
            $backdrop.addClass('mobile-nav--shown');
            $mobileNav.addClass('mobile-nav--shown');
            if ($backdrop.length) {
                $backdrop.attr('aria-hidden', 'false');
            }
            if ($backdrop[0]) {
                $backdrop[0].offsetHeight;
            }
            if ($mobileNav[0]) {
                $mobileNav[0].offsetHeight;
            }
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    $mobileNav.addClass('open');
                    $backdrop.addClass('is-open');
                });
            });
        } else {
            $mobileNav.removeClass('open');
            $backdrop.removeClass('is-open').attr('aria-hidden', 'true');
            $('body').removeClass('mobile-nav-drawer-open');
            if (mobileNavCloseTimer) {
                clearTimeout(mobileNavCloseTimer);
            }
            mobileNavCloseTimer = setTimeout(function () {
                mobileNavCloseTimer = null;
                $backdrop.removeClass('mobile-nav--shown');
                $mobileNav.removeClass('mobile-nav--shown');
            }, MOBILE_NAV_DRAWER_MS);
        }
    }

    // 滚动超过 10px 后为页头添加阴影
    var ticking = false;
    window.addEventListener('scroll', function () {
        if (!ticking) {
            ticking = true;
            requestAnimationFrame(function () {
                $header.toggleClass('scrolled', window.scrollY > 10);
                ticking = false;
            });
        }
    });

    // 移动端菜单开关（右侧抽屉 + 遮罩）
    $menuBtn.on('click', function () {
        var isOpen = $menuBtn.hasClass('active');
        // 将要展开菜单时先关掉搜索层，避免与搜索弹层叠、横向溢出叠在一起
        if (!isOpen) {
            $('#searchModal').removeClass('active');
            $('body').removeClass('search-modal-open');
        }
        setMobileDrawerOpen(!isOpen);
    });

    $backdrop.on('click', function () {
        setMobileDrawerOpen(false);
    });

    $(document).on('keydown', function (e) {
        if ((e.key === 'Escape' || e.keyCode === 27) && $menuBtn.hasClass('active')) {
            setMobileDrawerOpen(false);
        }
    });

    // 移动端二级菜单折叠
    $(document).on('click', '.mobile-nav-toggle', function () {
        var $this = $(this);
        var $sub = $this.next('.mobile-nav-sub');
        var isOpen = $this.hasClass('open');
        // 先收起其他已展开的
        $('.mobile-nav-toggle.open').not($this).removeClass('open')
            .next('.mobile-nav-sub').removeClass('open');
        $this.toggleClass('open', !isOpen);
        $sub.toggleClass('open', !isOpen);
    });

    // PJAX 跳转时立即收起侧滑（不等 transition，避免换页后仍占位/挡点击）
    $(document).on('pjax:start', function () {
        setMobileDrawerOpen(false, true);
    });
})();
</script>

</body>
</html>
