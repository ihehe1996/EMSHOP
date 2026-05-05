<?php
/**
 * 子神模板 - 通用底部（PJAX / 搜索 / 菜单脚本与 default 一致）
 */
?>
</div><!-- #main -->

<footer class="site-footer zs-footer">
    <div class="zs-footer__wave" aria-hidden="true"></div>
    <div class="wrapper zs-footer__inner">
        <div class="zs-footer__brand">
            <span class="zs-footer__sigil" aria-hidden="true">✦</span>
            <div class="zs-footer__titles">
                <span class="zs-footer__name"><?= htmlspecialchars($site_name ?? 'EMSHOP') ?></span>
            </div>
        </div>
        <nav class="zs-footer__links" aria-label="底部导航">
            <?= $nav_footer_html ?? '' ?>
        </nav>
        <div class="site-footer__copy zs-footer__copy">
            <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($site_name ?? 'EMSHOP') ?></span>
        </div>
        <?php if (!empty($site_icp)): ?>
        <div class="site-footer__icp zs-footer__icp">
            <?php $_icpHref = 'https://beian.miit.gov.cn/'; ?>
            <a href="<?= htmlspecialchars($_icpHref) ?>" target="_blank" rel="nofollow noopener">
                <?= htmlspecialchars($site_icp) ?>
            </a>
        </div>
        <?php endif; ?>
    </div>
</footer>

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
 * 系统区块（home/goods/blog）优先用 nav_id + 仅一级菜单项，避免与下拉子链、多入口 pathname 冲突。
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

        // 系统导航：与 PHP 的 $navId 一致，只匹配一级「首页 / 商城 / 博客」入口（含下拉父链）
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

        // 自定义页面 /p/{slug} 等：与 module.php 写入的 data-nav-path + 当前 path 一致（避免仅靠浏览器解析 href 与 PHP 不一致）
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

        // 自定义页等：完整 URL 唯一命中
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

        // pathname 唯一命中（避免多个 ?c= 同属 /index.php 时误亮多项）
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

    // 进度条：开始
    $(document).on('pjax:send', function () {
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
        $bar.removeClass('running');
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

    // 清理进度条
    $(document).on('pjax:end', function () {
        setTimeout(function () {
            $bar.removeClass('running done');
        }, 200);
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
        $modal.addClass('active');
        setTimeout(function () { $input.focus(); }, 100);
    });

    // 关闭弹窗
    function closeModal() { $modal.removeClass('active'); }
    $modal.find('.search-modal-mask').on('click', closeModal);
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
// 桌面端：主导航二级菜单 / 用户下拉 — 延迟收起（与 default 体验一致，避免移入子菜单途中误关）
// ============================================================
(function () {
    if (!window.jQuery) return;
    var $ = window.jQuery;
    var delayMs = 220;
    function clearTimer($box) {
        var t = $box.data('zsMenuTimer');
        if (t) clearTimeout(t);
        $box.removeData('zsMenuTimer');
    }
    function scheduleClose($box) {
        clearTimer($box);
        $box.data('zsMenuTimer', setTimeout(function () {
            $box.removeClass('zs-menu-hover');
            $box.removeData('zsMenuTimer');
        }, delayMs));
    }
    $(document).on('mouseenter', '.theme-zishen #siteHeader .nav-dropdown, .theme-zishen #siteHeader .header-user-dropdown', function () {
        clearTimer($(this));
        $(this).addClass('zs-menu-hover');
    });
    $(document).on('mouseleave', '.theme-zishen #siteHeader .nav-dropdown, .theme-zishen #siteHeader .header-user-dropdown', function () {
        scheduleClose($(this));
    });
})();

// ============================================================
// 页头：滚动阴影 + 移动端菜单
// ============================================================
(function () {
    var $header = $('#siteHeader');
    var $menuBtn = $('#menuToggle');
    var $mobileNav = $('#mobileNav');

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

    // 移动端菜单开关
    $menuBtn.on('click', function () {
        var isOpen = $menuBtn.hasClass('active');
        $menuBtn.toggleClass('active', !isOpen);
        $mobileNav.toggleClass('open', !isOpen);
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

    // PJAX 跳转时收起移动端菜单
    $(document).on('pjax:start', function () {
        $menuBtn.removeClass('active');
        $mobileNav.removeClass('open');
    });
})();
</script>

</body>
</html>
