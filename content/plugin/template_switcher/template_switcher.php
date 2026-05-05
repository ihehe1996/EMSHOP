<?php
/**
Plugin Name: 前台模板切换
Version: 1.0.0
Plugin URL:
Description: 启用后在前台右下角显示模板切换悬浮按钮，支持通过 Cookie 覆盖当前终端模板，适合演示站切换不同模板效果。
Author: EMSHOP
Author URL:
Category: 功能扩展
*/

defined('EM_ROOT') || exit('access denied!');

function template_switcher_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function template_switcher_scope(): string
{
    $merchantId = class_exists('MerchantContext') ? MerchantContext::currentId() : 0;
    return $merchantId > 0 ? 'merchant_' . $merchantId : 'main';
}

function template_switcher_route(array $params): string
{
    return '/?' . http_build_query(array_merge(['plugin' => 'template_switcher'], $params), '', '&', PHP_QUERY_RFC3986);
}

function template_switcher_available_templates(string $scope): array
{
    $model = new TemplateModel();
    $rows = [];

    foreach ($model->scanTemplates() as $name => $info) {
        if (!$model->isInstalled($name, $scope)) {
            continue;
        }

        $rows[] = [
            'name'  => $name,
            'title' => (string) ($info['title'] ?? $name),
        ];
    }

    usort($rows, static function (array $a, array $b): int {
        return strcmp((string) $a['title'], (string) $b['title']);
    });

    return $rows;
}

function template_switcher_render_main_inner_assets(string $phase): void
{
    static $didStyle = false;
    static $didScript = false;

    if ($phase === 'style' && !$didStyle) {
        echo <<<HTML
<style id="em-template-switcher-style">
.em-template-switcher{
    position:fixed;
    left:50%;
    bottom:20px;
    transform:translateX(-50%);
    z-index:10020;
}
.em-template-switcher__toggle{
    display:flex;
    align-items:center;
    gap:8px;
    border:0;
    border-radius:5px;
    padding:12px 16px;
    background:linear-gradient(135deg,#2563eb,#1d4ed8);
    color:#fff;
    font-size:14px;
    font-weight:600;
    cursor:pointer;
    user-select:none;
    box-shadow:0 14px 34px rgba(37,99,235,.28);
}
.em-template-switcher__toggle-icon{
    width:32px;
    height:32px;
    border-radius:5px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    background:#fff;
    overflow:hidden;
    flex-shrink:0;
}
.em-template-switcher__toggle-icon img{
    width:22px;
    height:22px;
    object-fit:contain;
    display:block;
}
.em-template-switcher__panel{
    position:absolute;
    left:50%;
    bottom:58px;
    transform:translateX(-50%);
    width:320px;
    max-height:min(70vh, 520px);
    overflow:auto;
    border:1px solid rgba(15,23,42,.08);
    border-radius:16px;
    background:#fff;
    box-shadow:0 24px 60px rgba(15,23,42,.18);
    padding:12px;
    display:none;
}
.em-template-switcher.is-open .em-template-switcher__panel{
    display:block;
}
.em-template-switcher__panel-head{
    padding:4px 4px 10px;
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
}
.em-template-switcher__panel-head-main{
    min-width:0;
    flex:1;
}
.em-template-switcher__replay-guide{
    flex-shrink:0;
    border:0;
    background:transparent;
    color:#2563eb;
    font-size:12px;
    font-weight:600;
    cursor:pointer;
    padding:2px 0;
    line-height:1.2;
    white-space:nowrap;
}
.em-template-switcher__replay-guide:hover{
    text-decoration:underline;
}
.em-template-switcher__title{
    font-size:15px;
    font-weight:700;
    color:#0f172a;
}
.em-template-switcher__meta{
    margin-top:4px;
    font-size:12px;
    color:#64748b;
}
.em-template-switcher__item{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:12px;
    border-radius:12px;
    text-decoration:none;
    color:#0f172a;
    width:100%;
    border:0;
    background:#fff;
    text-align:left;
    cursor:pointer;
    transition:background .15s ease,color .15s ease,transform .15s ease;
}
.em-template-switcher__item:hover{
    background:#f8fafc;
    transform:translateY(-1px);
}
.em-template-switcher__item.is-current{
    background:#eff6ff;
}
.em-template-switcher__item--reset{
    margin-bottom:6px;
    background:#f8fafc;
}
.em-template-switcher__item-main{
    min-width:0;
}
.em-template-switcher__item-title{
    display:block;
    font-size:14px;
    font-weight:600;
}
.em-template-switcher__item-desc{
    display:block;
    margin-top:3px;
    font-size:12px;
    color:#64748b;
    word-break:break-all;
}
.em-template-switcher__badges{
    display:flex;
    flex-direction:column;
    gap:6px;
    align-items:flex-end;
    flex-shrink:0;
}
.em-template-switcher__badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:60px;
    padding:4px 8px;
    border-radius:999px;
    background:#e2e8f0;
    color:#334155;
    font-size:11px;
    line-height:1;
}
.em-template-switcher__badge--accent{
    background:#2563eb;
    color:#fff;
}
.em-template-switcher--guide{
    /* 演示引导需要压过站内其它 fixed/sticky 层（仍低于浏览器原生弹层） */
    z-index:2147483646;
}
.em-template-switcher--guide .em-template-switcher__toggle{
    box-shadow:0 14px 34px rgba(37,99,235,.28), 0 0 0 0 rgba(37,99,235,.55);
    animation:emTplSwitchPulse 1.6s ease-out infinite;
}
@keyframes emTplSwitchPulse{
    0%{box-shadow:0 14px 34px rgba(37,99,235,.28), 0 0 0 0 rgba(37,99,235,.45);}
    70%{box-shadow:0 14px 34px rgba(37,99,235,.28), 0 0 0 14px rgba(37,99,235,0);}
    100%{box-shadow:0 14px 34px rgba(37,99,235,.28), 0 0 0 0 rgba(37,99,235,0);}
}
.em-template-switcher__guide-backdrop{
    position:fixed;
    inset:0;
    /* 低于切换条，避免挡住「展开列表 / 拖动」操作；仅作暗角提示 */
    z-index:2147483000;
    background:rgba(15,23,42,.42);
    pointer-events:none;
}
.em-template-switcher__guide-tip{
    position:fixed;
    z-index:2147483645;
    max-width:min(360px, calc(100vw - 32px));
    padding:12px 14px;
    border-radius:12px;
    background:#fff;
    color:#0f172a;
    font-size:13px;
    line-height:1.55;
    box-shadow:0 18px 44px rgba(15,23,42,.22);
    border:1px solid rgba(15,23,42,.08);
    pointer-events:auto;
}
.em-template-switcher__guide-tip strong{
    font-weight:700;
}
.em-template-switcher__guide-actions{
    margin-top:12px;
    display:flex;
    justify-content:flex-end;
}
.em-template-switcher__guide-ok{
    border:0;
    border-radius:8px;
    padding:8px 14px;
    font-size:13px;
    font-weight:600;
    cursor:pointer;
    color:#fff;
    background:linear-gradient(135deg,#2563eb,#1d4ed8);
    box-shadow:0 8px 18px rgba(37,99,235,.25);
}
.em-template-switcher__guide-ok:hover{
    filter:brightness(1.03);
}
@media (max-width:640px){
    .em-template-switcher{
        left:50%;
        bottom:12px;
    }
    .em-template-switcher__toggle{
        padding:10px 14px;
    }
    .em-template-switcher__panel{
        width:min(320px, calc(100vw - 24px));
        bottom:54px;
    }
}
</style>
HTML;
        $didStyle = true;

        return;
    }

    if ($phase !== 'script' || $didScript) {
        return;
    }

    // 整页运行时 style + boot 在同一请求尾部各跑一次；
    // PJAX 片段里也必须带 boot（否则仅靠首次整页下发的脚本无法在后续 PJAX 更新后重新挂载）。
    if (!Request::isPjax()) {
        if (!empty($GLOBALS['__em_tpl_switch_main_boot_emitted'])) {
            return;
        }
        $GLOBALS['__em_tpl_switch_main_boot_emitted'] = true;
    }

    echo <<<HTML
<script>
(function () {
    window.__EM_TEMPLATE_SWITCHER_BOOT = function bootstrapTemplateSwitcherOnce() {
        if (bootstrapTemplateSwitcherOnce._instance) {
            bootstrapTemplateSwitcherOnce._instance.destroy();
            bootstrapTemplateSwitcherOnce._instance = null;
        }

        var root = document.getElementById('emTemplateSwitcher');
        var toggle = document.getElementById('emTemplateSwitcherToggle');
        if (!root || !toggle) return;

        var storageKey = 'emTemplateSwitcherPos';
        var guideStorageKey = 'em_template_switcher_guide_v1';
        var dragState = null;
        var skipClick = false;
        var guideActive = false;
        var guideBackdrop = null;
        var guideTip = null;

        function viewportWidth() {
            return window.innerWidth || document.documentElement.clientWidth || 0;
        }

        function viewportHeight() {
            return window.innerHeight || document.documentElement.clientHeight || 0;
        }

        function clamp(num, min, max) {
            return Math.min(Math.max(num, min), max);
        }

        function applyPosition(left, top) {
            var maxLeft = Math.max(12, viewportWidth() - root.offsetWidth - 12);
            var maxTop = Math.max(12, viewportHeight() - root.offsetHeight - 12);
            root.style.left = clamp(left, 12, maxLeft) + 'px';
            root.style.top = clamp(top, 12, maxTop) + 'px';
            root.style.bottom = 'auto';
            root.style.transform = 'none';
        }

        function savePosition() {
            if (!window.localStorage) return;
            var left = parseFloat(root.style.left || '');
            var top = parseFloat(root.style.top || '');
            if (isNaN(left) || isNaN(top)) return;
            try {
                localStorage.setItem(storageKey, JSON.stringify({ left: left, top: top }));
            } catch (e) {}
        }

        function restorePosition() {
            if (!window.localStorage) return;
            try {
                var raw = localStorage.getItem(storageKey);
                if (!raw) return;
                var pos = JSON.parse(raw);
                if (!pos || typeof pos.left !== 'number' || typeof pos.top !== 'number') return;
                applyPosition(pos.left, pos.top);
            } catch (e) {}
        }

        function pointFromEvent(event) {
            if (event.touches && event.touches[0]) {
                return { x: event.touches[0].clientX, y: event.touches[0].clientY };
            }
            if (event.changedTouches && event.changedTouches[0]) {
                return { x: event.changedTouches[0].clientX, y: event.changedTouches[0].clientY };
            }
            return { x: event.clientX, y: event.clientY };
        }

        function handleMove(event) {
            if (!dragState) return;

            var point = pointFromEvent(event);
            var dx = point.x - dragState.startX;
            var dy = point.y - dragState.startY;
            if (!dragState.moved && (Math.abs(dx) > 4 || Math.abs(dy) > 4)) {
                dragState.moved = true;
                root.classList.remove('is-open');
            }
            if (!dragState.moved) return;

            if (event.cancelable) event.preventDefault();
            applyPosition(dragState.originLeft + dx, dragState.originTop + dy);
        }

        function handleUp() {
            if (!dragState) return;
            if (dragState.moved) {
                skipClick = true;
                savePosition();
                setTimeout(function () { skipClick = false; }, 0);
            }
            dragState = null;
            document.removeEventListener('mousemove', handleMove);
            document.removeEventListener('mouseup', handleUp);
            document.removeEventListener('touchmove', handleMove);
            document.removeEventListener('touchend', handleUp);
        }

        restorePosition();

        function placeGuideTip() {
            if (!guideTip || !root) return;
            var rect = root.getBoundingClientRect();
            var tipRect = guideTip.getBoundingClientRect();
            var left = rect.left + rect.width / 2 - tipRect.width / 2;
            var top = rect.top - tipRect.height - 14;
            var vw = viewportWidth();
            var vh = viewportHeight();
            left = clamp(left, 12, Math.max(12, vw - tipRect.width - 12));
            if (top < 12) {
                top = Math.min(vh - tipRect.height - 12, rect.bottom + 14);
            }
            guideTip.style.left = Math.round(left) + 'px';
            guideTip.style.top = Math.round(top) + 'px';
        }

        function endGuide() {
            if (!guideActive) return;
            guideActive = false;
            root.classList.remove('em-template-switcher--guide');

            if (guideBackdrop && guideBackdrop.parentNode) {
                guideBackdrop.parentNode.removeChild(guideBackdrop);
            }
            guideBackdrop = null;

            if (guideTip && guideTip.parentNode) {
                guideTip.parentNode.removeChild(guideTip);
            }
            guideTip = null;

            window.removeEventListener('resize', placeGuideTip);
            window.removeEventListener('scroll', placeGuideTip, true);
        }

        function acknowledgeGuide() {
            if (!window.localStorage) {
                endGuide();
                return;
            }
            try {
                localStorage.setItem(guideStorageKey, '1');
            } catch (e) {}
            endGuide();
        }

        function restartTemplateSwitcherGuide() {
            if (!window.localStorage) {
                window.location.reload();
                return;
            }
            try {
                localStorage.removeItem(guideStorageKey);
            } catch (e) {}
            window.location.reload();
        }

        function startFirstVisitGuide() {
            if (guideActive) return;
            if (!window.localStorage) return;

            try {
                if (localStorage.getItem(guideStorageKey) === '1') {
                    return;
                }
            } catch (e) {
                return;
            }

            guideActive = true;
            root.classList.remove('is-open');
            root.classList.add('em-template-switcher--guide');

            guideBackdrop = document.createElement('div');
            guideBackdrop.className = 'em-template-switcher__guide-backdrop';
            guideBackdrop.setAttribute('data-em-tpl-switch-guide', '1');
            document.body.appendChild(guideBackdrop);

            guideTip = document.createElement('div');
            guideTip.className = 'em-template-switcher__guide-tip';
            guideTip.innerHTML = ''
                + '<strong>演示站提示</strong>：点这个按钮可以切换模板；也可以按住按钮拖动位置。'
                + '<div class="em-template-switcher__guide-actions">'
                + '<button type="button" class="em-template-switcher__guide-ok" id="emTemplateSwitcherGuideOk">我知道了</button>'
                + '</div>';
            document.body.appendChild(guideTip);

            var okBtn = document.getElementById('emTemplateSwitcherGuideOk');
            if (okBtn) {
                okBtn.addEventListener('click', function (event) {
                    if (event.cancelable) {
                        event.preventDefault();
                    }
                    event.stopPropagation();
                    acknowledgeGuide();
                });
            }

            placeGuideTip();
            window.addEventListener('resize', placeGuideTip);
            window.addEventListener('scroll', placeGuideTip, true);
        }

        function onToggleMouseDown(event) {
            var rect = root.getBoundingClientRect();
            dragState = {
                startX: event.clientX,
                startY: event.clientY,
                originLeft: rect.left,
                originTop: rect.top,
                moved: false
            };
            document.addEventListener('mousemove', handleMove);
            document.addEventListener('mouseup', handleUp);
        }

        function onToggleTouchStart(event) {
            var point = pointFromEvent(event);
            var rect = root.getBoundingClientRect();
            dragState = {
                startX: point.x,
                startY: point.y,
                originLeft: rect.left,
                originTop: rect.top,
                moved: false
            };
            document.addEventListener('touchmove', handleMove, { passive: false });
            document.addEventListener('touchend', handleUp);
        }

        function onToggleClick(event) {
            event.preventDefault();
            event.stopPropagation();
            if (skipClick) return;
            root.classList.toggle('is-open');
        }

        function onDocumentClick(event) {
            var t = event.target;
            if (guideTip && guideTip.contains(t)) {
                return;
            }
            if (!root.contains(t)) {
                root.classList.remove('is-open');
            }
            if (guideActive) {
                return;
            }
        }

        function onRootClick(event) {
            if (event.target.closest('.em-template-switcher__replay-guide')) {
                if (event.cancelable) {
                    event.preventDefault();
                }
                event.stopPropagation();
                restartTemplateSwitcherGuide();
                return;
            }

            var button = event.target.closest('.em-template-switcher__item[data-url]');
            if (!button) return;

            event.preventDefault();
            event.stopPropagation();

            var url = button.getAttribute('data-url') || '';
            if (url) {
                window.location.href = url;
            }
        }

        function onWindowResize() {
            var left = parseFloat(root.style.left || '');
            var top = parseFloat(root.style.top || '');
            if (!isNaN(left) && !isNaN(top)) {
                applyPosition(left, top);
                savePosition();
            }
        }

        toggle.addEventListener('mousedown', onToggleMouseDown);
        toggle.addEventListener('touchstart', onToggleTouchStart, { passive: true });
        toggle.addEventListener('click', onToggleClick);
        document.addEventListener('click', onDocumentClick);
        root.addEventListener('click', onRootClick);
        window.addEventListener('resize', onWindowResize);

        startFirstVisitGuide();

        bootstrapTemplateSwitcherOnce._instance = {
            destroy: function () {
                endGuide();
                toggle.removeEventListener('mousedown', onToggleMouseDown);
                toggle.removeEventListener('touchstart', onToggleTouchStart);
                toggle.removeEventListener('click', onToggleClick);
                document.removeEventListener('click', onDocumentClick);
                root.removeEventListener('click', onRootClick);
                window.removeEventListener('resize', onWindowResize);
                handleUp();
            }
        };
    };
})();
</script>
HTML;

    $didScript = true;
}

function template_switcher_render_main_inner(): void
{
    if (php_sapi_name() === 'cli') {
        return;
    }

    $scope = template_switcher_scope();
    $client = TemplateModel::detectClientFromRequest();
    $model = new TemplateModel();
    $templates = template_switcher_available_templates($scope);
    if (count($templates) <= 1) {
        return;
    }

    $currentTheme = $model->getEffectiveTheme($client, $scope);
    $cookieTheme = $model->getCookieOverrideTheme($client, $scope);
    $backendTheme = $model->getActiveTheme($client, $scope);
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    if ($requestUri === '' || strpos($requestUri, "\n") !== false || strpos($requestUri, "\r") !== false) {
        $requestUri = '/';
    }

    $buttonLabel = '模板';
    foreach ($templates as $row) {
        if ((string) $row['name'] === $currentTheme) {
            $buttonLabel = (string) $row['title'];
            break;
        }
    }

    template_switcher_render_main_inner_assets('style');

    echo '<div class="em-template-switcher" id="emTemplateSwitcher">';
    echo '<button type="button" class="em-template-switcher__toggle" id="emTemplateSwitcherToggle">';
    echo '<span class="em-template-switcher__toggle-icon" aria-hidden="true">'
        . '<img src="/content/static/img/logo.png" alt="">'
        . '</span>';
    echo '<span class="em-template-switcher__toggle-text">切换模板：' . template_switcher_h($buttonLabel) . '</span>';
    echo '</button>';

    echo '<div class="em-template-switcher__panel" id="emTemplateSwitcherPanel">';
    echo '<div class="em-template-switcher__panel-head">';
    echo '<div class="em-template-switcher__panel-head-main">';
    echo '<div class="em-template-switcher__title">模板切换</div>';
    echo '<div class="em-template-switcher__meta">' . template_switcher_h(strtoupper($client)) . ' · ' . template_switcher_h($scope) . '</div>';
    echo '</div>';
    echo '<button type="button" class="em-template-switcher__replay-guide" title="重新显示首次引导">重新引导</button>';
    echo '</div>';

    if ($cookieTheme !== '') {
        echo '<button type="button" class="em-template-switcher__item em-template-switcher__item--reset" data-url="'
            . template_switcher_h(template_switcher_route([
                'reset' => '1',
                'redirect' => $requestUri,
            ]))
            . '">';
        echo '<span class="em-template-switcher__item-main">';
        echo '<span class="em-template-switcher__item-title">跟随后台默认模板</span>';
        echo '<span class="em-template-switcher__item-desc">清除当前演示 Cookie，恢复后台启用模板</span>';
        echo '</span>';
        echo '</button>';
    }

    foreach ($templates as $row) {
        $name = (string) $row['name'];
        $title = (string) $row['title'];
        $isCurrent = ($name === $currentTheme);
        $isBackend = ($name === $backendTheme);
        $isCookie = ($cookieTheme !== '' && $name === $cookieTheme);

        echo '<button type="button" class="em-template-switcher__item' . ($isCurrent ? ' is-current' : '') . '" data-url="'
            . template_switcher_h(template_switcher_route([
                'template' => $name,
                'redirect' => $requestUri,
            ]))
            . '">';
        echo '<span class="em-template-switcher__item-main">';
        echo '<span class="em-template-switcher__item-title">' . template_switcher_h($title) . '</span>';
        echo '<span class="em-template-switcher__item-desc">' . template_switcher_h($name) . '</span>';
        echo '</span>';
        echo '<span class="em-template-switcher__badges">';
        if ($isBackend) {
            echo '<span class="em-template-switcher__badge">后台默认</span>';
        }
        if ($isCookie) {
            echo '<span class="em-template-switcher__badge em-template-switcher__badge--accent">演示中</span>';
        } elseif ($isCurrent) {
            echo '<span class="em-template-switcher__badge em-template-switcher__badge--accent">当前</span>';
        }
        echo '</span>';
        echo '</button>';
    }

    echo '</div>';
    echo '</div>';

    template_switcher_render_main_inner_assets('script');
    echo '<script>window.__EM_TEMPLATE_SWITCHER_BOOT && window.__EM_TEMPLATE_SWITCHER_BOOT();</script>';
}

addAction('front_footer', 'template_switcher_render_main_inner');
addAction('front_pjax_main_inner', 'template_switcher_render_main_inner');
