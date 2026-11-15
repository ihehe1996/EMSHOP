<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
// 视图内取 CSRF：控制器（整页 / PJAX 两个分支）都没传这个变量，
// 直接引用会在 <script> 里输出 Warning，导致前端整段脚本语法错误。
$csrfToken = $csrfToken ?? \Csrf::token();
?>
<style>
/* 模板管理页面样式：沿用插件管理的卡片风格，并增加 PC / 手机双端状态展示。 */
/* 和控制台（home.php）一致：去掉 .admin-page 默认白底，让卡片浮在灰底画布上 */
.admin-page-template { padding: 8px 4px 40px; background: unset; }
.template-filter-bar { display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:16px; }
.template-appstore-btn { height:32px; padding:0 14px; border:1px solid rgba(99,102,241,.3); border-radius:8px; background:rgba(99,102,241,.06); font-size:13px; font-weight:500; color:#6366f1; cursor:pointer; display:flex; align-items:center; gap:6px; transition:all .15s; }
.template-appstore-btn:hover { background:rgba(99,102,241,.12); border-color:rgba(99,102,241,.5); }

/* ===== 骨架屏 ===== */
.template-skeleton-icon { width:56px; height:56px; border-radius:10px; background:#f0f0f0; flex-shrink:0; }
.template-skeleton-line { height:12px; background:#f0f0f0; border-radius:6px; }
.template-skeleton-line--short { width:60%; }
@keyframes skeleton-pulse { 0%,100%{opacity:1;} 50%{opacity:.5;} }

/* ===== 卡片网格 ===== */
.template-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:16px;}
@media (min-width:1400px) { .template-grid { grid-template-columns:repeat(4, 1fr); } }

/* ===== 模板卡片 ===== */
.template-card { background:#fff; border:1px solid #e8e8ec; border-radius:14px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 2px 12px rgba(0,0,0,.08); transition:border-color .2s,box-shadow .2s,transform .2s; }
.template-card:hover { border-color:#a5b4fc; box-shadow:0 4px 20px rgba(99,102,241,.1); transform:translateY(-1px); }
.template-card__header { display:flex; gap:12px; padding:16px 16px 12px; align-items:center; }
.template-card__icon-wrap { flex-shrink:0; cursor:pointer; }
.template-card__icon, .template-card__icon--default { width:56px; height:56px; border-radius:10px; object-fit:cover; }
.template-card__icon--default { background:linear-gradient(135deg, rgba(99,102,241,.1), rgba(129,140,248,.08)); display:flex; align-items:center; justify-content:center; color:#4f46e5; font-size:24px; }
.template-card__info { flex:1; min-width:0; }
.template-card__title { font-size:15px; font-weight:600; color:#111827; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.template-card__meta { display:flex; align-items:center; gap:6px; margin-top:4px; flex-wrap:wrap; }
.template-card__version { font-size:12px; color:#9ca3af; }
.template-card__status { display:inline-flex; align-items:center; font-size:12px; font-weight:500; padding:2px 8px; border-radius:5px; }
.template-card__status--pc { background:rgba(59,130,246,.1); color:#2563eb; }
.template-card__status--mobile { background:rgba(59,130,246,.1); color:#2563eb; }
.template-card__status--installed { background:rgba(5,150,105,.1); color:#059669; }
.template-card__status--new { background:rgba(0,0,0,.05); color:#6b7280; }
.template-card__status--both { background:rgba(99,102,241,.12); color:#4f46e5; }
.template-card__body { padding:0 16px 8px; flex:1; }
.template-card__author { display:inline-flex; align-items:center; gap:5px; font-size:12px; color:#9ca3af; margin-bottom:8px; }
.template-card__author a { color:#4f46e5; text-decoration:none; }
.template-card__author a:hover { text-decoration:underline; }
.template-card__desc { font-size:13px; color:#6b7280; line-height:1.6; }
.template-card__footer { display:flex; align-items:center; gap:8px; padding:12px 16px; padding-top:8px; border-top:1px solid #f5f5f7; flex-wrap:wrap; position:relative; z-index:1; }
.template-card__actions-left { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.template-card__actions-left--install { width:100%; justify-content:flex-end; }
.template-card__actions-right { margin-left:auto; display:flex; align-items:center; }
.template-card__action { height:32px; padding:0 12px; border:none; border-radius:7px; font-size:12px; font-weight:500; cursor:pointer; display:inline-flex; align-items:center; gap:5px; transition:all .15s; }
.template-card__action--install { background:rgba(99,102,241,.1); color:#4f46e5; }
.template-card__action--install:hover { background:rgba(99,102,241,.18); }
.template-card__action--pc { background:rgba(0,0,0,.04); color:#6b7280; }
.template-card__action--pc:hover { background:rgba(59,130,246,.08); color:#2563eb; }
.template-card__action--pc.is-active { background:rgba(59,130,246,.08); color:#2563eb; }
.template-card__action--mobile { background:rgba(0,0,0,.04); color:#6b7280; }
.template-card__action--mobile:hover { background:rgba(59,130,246,.08); color:#2563eb; }
.template-card__action--mobile.is-active { background:rgba(59,130,246,.08); color:#2563eb; }
.template-card__action--settings { background:rgba(245,158,11,.1); color:#d97706; }
.template-card__action--settings:hover { background:rgba(245,158,11,.18); color:#b45309; }
/* 有新版本时用实色橙按钮提示更新，与应用商店里"更新 vX.X.X"同一视觉语言 */
.template-card__action--update { background:#f59e0b; color:#fff; }
.template-card__action--update:hover { background:#d97706; color:#fff; }
/* 有新版本但站点未授权：外观与「更新」按钮完全一致，点开后弹出激活提示 */
.template-card__action--update-locked { background:#f59e0b; color:#fff; }
.template-card__action--update-locked:hover { background:#d97706; color:#fff; }
.template-card__action--disabled { background:rgba(0,0,0,.04)!important; color:#c9cdd4!important; cursor:not-allowed!important; }
.template-card__more { flex-shrink:0; position:relative; }
.template-card__more-btn { width:28px; height:28px; border:none; background:transparent; border-radius:6px; cursor:pointer; color:#d1d5db; font-size:16px; display:flex; align-items:center; justify-content:center; transition:all .15s; }
.template-card__more-btn:hover { background:#f5f5f7; color:#6b7280; }
.template-card__more-dropdown { position:absolute; top:calc(100% + 4px); right:0; background:#fff; border:1px solid #e8e8ec; border-radius:8px; box-shadow:0 4px 16px rgba(0,0,0,.1); min-width:130px; z-index:200; overflow:hidden; display:none; }
.template-card__more-dropdown--open { display:block; }
.template-card__more-item { display:flex; align-items:center; gap:8px; width:100%; border:none; background:transparent; padding:9px 12px; font-size:13px; color:#374151; text-align:left; cursor:pointer; transition:background .1s; }
.template-card__more-item:hover { background:#f5f5f7; }
.template-card__more-item--danger { color:#dc2626; }
.template-card__more-item--danger:hover { background:rgba(220,38,38,.04); }
.template-empty { text-align:center; padding:60px 20px; color:#9ca3af; display:none; }
.template-empty__icon { width:72px; height:72px; border-radius:20px; background:linear-gradient(135deg, rgba(99,102,241,.08), rgba(129,140,248,.05)); display:flex; align-items:center; justify-content:center; margin:0 auto 20px; font-size:32px; color:#a5b4fc; }
.fa-mobile{ font-size:17px; }

/* ===== 版本检查提示：页面顶部居中的「灵动岛」 ===== */
.em-island {
    position: fixed;
    top: 14px;
    /* 左右都设 0 + margin auto 居中：不用 transform，避免首帧画在错误位置再跳过去 */
    left: 0;
    right: 0;
    margin: 0 auto;
    width: fit-content;
    z-index: 19000000;
    display: flex;
    align-items: center;
    gap: 10px;
    max-width: calc(100vw - 24px);
    padding: 7px 12px 7px 8px;
    border-radius: 5px;
    background: rgba(15, 23, 42, 0.96);
    color: #fff;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.3), 0 2px 6px rgba(15, 23, 42, 0.18);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    animation: emIslandIn 0.2s ease-out;
}
.em-island[hidden] { display: none; }
@keyframes emIslandIn {
    from { opacity: 0; }
    to   { opacity: 1; }
}
.em-island__icon {
    flex-shrink: 0;
    width: 26px; height: 26px;
    border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 12px;
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.85);
}
.em-island__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.em-island__title { font-size: 12.5px; font-weight: 600; line-height: 1.35; color: #fff; }
.em-island.is-checking .em-island__icon { color: #a5b4fc; }
.em-island.is-new .em-island__icon { background: rgba(245, 158, 11, 0.22); color: #fbbf24; }
.em-island.is-done .em-island__icon { background: rgba(16, 185, 129, 0.22); color: #34d399; }
.em-island.is-error .em-island__icon { background: rgba(239, 68, 68, 0.22); color: #f87171; }
@media (max-width: 640px) {
    .em-island { top: 10px; max-width: calc(100vw - 20px); padding-right: 10px; }
}

/* ===== 未激活提示弹窗（点击「更新」时） ===== */
.update-lock { position: relative; padding: 30px 24px 24px; text-align: center; }
.update-lock__close {
    position: absolute; top: 12px; right: 12px;
    width: 26px; height: 26px; padding: 0;
    border: 0; border-radius: 50%;
    background: #f3f4f6; color: #9ca3af;
    font-size: 16px; line-height: 1;
    display: inline-flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.update-lock__close:hover { background: #e5e7eb; color: #4b5563; }
.update-lock__icon {
    width: 52px; height: 52px; margin: 0 auto 14px;
    border-radius: 15px;
    background: linear-gradient(135deg, #fffbeb, #fef3c7);
    color: #d97706; font-size: 22px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: inset 0 0 0 1px rgba(217, 119, 6, 0.15);
}
.update-lock__title { font-size: 17px; font-weight: 700; color: #111827; letter-spacing: 0.2px; }
.update-lock__sub { margin-top: 8px; font-size: 13px; color: #6b7280; line-height: 1.7; }
.update-lock__actions { display: flex; justify-content: center; gap: 10px; margin-top: 22px; }
.update-lock__btn {
    height: 38px; padding: 0 20px;
    border: 1px solid #e5e7eb; border-radius: 9px;
    background: #fff; color: #4b5563;
    font-family: inherit; font-size: 13px; font-weight: 500;
    white-space: nowrap;
    cursor: pointer; transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.update-lock__btn:not(.update-lock__btn--primary):hover { background: #f9fafb; border-color: #d1d5db; }
.update-lock__btn--primary {
    border: none;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #fff;
    box-shadow: 0 4px 12px rgba(217, 119, 6, 0.25);
}
.update-lock__btn--primary:hover {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #fff;
    filter: brightness(1.05);
}
@media (max-width: 640px) {
    .update-lock { padding: 26px 16px 18px; }
    .update-lock__actions { gap: 8px; }
    .update-lock__actions .update-lock__btn { flex: 1; padding: 0 10px; }
}

/* ===== 激活弹窗（与首页/应用商店同款视觉） ===== */
.lic-activate { padding: 22px 22px 18px; box-sizing: border-box; }
.lic-activate__hero { text-align: center; margin-bottom: 20px; }
.lic-activate__icon {
    width: 52px; height: 52px; margin: 0 auto 12px;
    border-radius: 15px;
    background: linear-gradient(135deg, #eef2ff, #e0e7ff);
    color: #6366f1; font-size: 22px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: inset 0 0 0 1px rgba(99, 102, 241, 0.12);
}
.lic-activate__title { font-size: 17px; font-weight: 700; color: #0f172a; }
.lic-activate__sub { margin-top: 5px; font-size: 12.5px; color: #6b7280; line-height: 1.6; }
.lic-activate__label { display: block; font-size: 12px; color: #6b7280; margin-bottom: 8px; }
.lic-activate__field { position: relative; }
.lic-activate__field > i {
    position: absolute; left: 14px; top: 50%;
    transform: translateY(-50%);
    color: #9ca3af; font-size: 13px; pointer-events: none;
}
.lic-activate__input {
    width: 100%; height: 46px;
    padding: 0 118px 0 38px;
    border: 1.5px solid #e5e7eb; border-radius: 11px;
    background: #fafbfc;
    font-family: 'JetBrains Mono', Consolas, Monaco, monospace;
    font-size: 13px; letter-spacing: 0.5px;
    outline: none; box-sizing: border-box;
    transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
}
.lic-activate__input:focus { border-color: #6366f1; background: #fff; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12); }
.lic-activate__inline-btn {
    position: absolute; top: 6px; right: 6px; bottom: 6px;
    padding: 0 16px;
    border: 0; border-radius: 8px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #fff; font-size: 13px; font-weight: 600; white-space: nowrap;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(79, 70, 229, 0.26);
    transition: filter 0.15s;
}
.lic-activate__inline-btn:hover { filter: brightness(1.06); }
.lic-activate__inline-btn:disabled { opacity: 0.65; cursor: not-allowed; box-shadow: none; }
.lic-activate__note {
    margin-top: 14px; padding: 8px 12px;
    display: flex; align-items: center; justify-content: center; gap: 6px;
    font-size: 11.5px; color: #9ca3af;
    background: #f8fafc;
    border: 1px dashed #e5e7eb;
    border-radius: 8px;
}
@media (max-width: 640px) {
    .lic-activate { padding: 18px 16px 14px; }
    .lic-activate__input { padding-right: 106px; }
}
</style>

<div class="admin-page admin-page-template">
    <h1 class="admin-page__title">模板管理</h1>

    <div class="template-filter-bar">
        <a class="em-btn em-reset-btn" id="templateRefreshBtn"><i class="fa fa-refresh"></i>刷新</a>
        <button class="template-appstore-btn" id="appstoreBtn" type="button"><i class="fa fa-shopping-basket"></i> 去逛逛应用商店</button>
    </div>

<div id="templateWrapper" style="display:none;">
        <div class="template-grid" id="templateSkeleton">
            <div class="template-card" style="animation:skeleton-pulse 1.5s ease-in-out infinite;">
                <div class="template-card__header">
                    <div class="template-card__icon-wrap">
                        <div class="template-skeleton-icon"></div>
                    </div>
                    <div class="template-card__info">
                        <div class="template-skeleton-line template-skeleton-line--short" style="height:15px;margin-bottom:6px;"></div>
                        <div class="template-card__meta">
                            <div class="template-skeleton-line" style="width:60px;height:20px;"></div>
                        </div>
                    </div>
                </div>
                <div class="template-card__body">
                    <div class="template-skeleton-line" style="width:40%;height:12px;margin-bottom:10px;"></div>
                    <div class="template-skeleton-line" style="width:100%;height:13px;margin-bottom:6px;"></div>
                    <div class="template-skeleton-line" style="width:75%;height:13px;"></div>
                </div>
                <div class="template-card__footer">
                    <div class="template-skeleton-line" style="width:80px;height:32px;border-radius:7px;"></div>
                    <div class="template-skeleton-line" style="width:80px;height:32px;border-radius:7px;"></div>
                </div>
            </div>
            <div class="template-card" style="animation:skeleton-pulse 1.5s ease-in-out infinite;animation-delay:.2s;">
                <div class="template-card__header">
                    <div class="template-card__icon-wrap">
                        <div class="template-skeleton-icon"></div>
                    </div>
                    <div class="template-card__info">
                        <div class="template-skeleton-line template-skeleton-line--short" style="height:15px;margin-bottom:6px;"></div>
                        <div class="template-card__meta">
                            <div class="template-skeleton-line" style="width:60px;height:20px;"></div>
                        </div>
                    </div>
                </div>
                <div class="template-card__body">
                    <div class="template-skeleton-line" style="width:40%;height:12px;margin-bottom:10px;"></div>
                    <div class="template-skeleton-line" style="width:100%;height:13px;margin-bottom:6px;"></div>
                    <div class="template-skeleton-line" style="width:75%;height:13px;"></div>
                </div>
                <div class="template-card__footer">
                    <div class="template-skeleton-line" style="width:80px;height:32px;border-radius:7px;"></div>
                    <div class="template-skeleton-line" style="width:80px;height:32px;border-radius:7px;"></div>
                </div>
            </div>
            <div class="template-card" style="animation:skeleton-pulse 1.5s ease-in-out infinite;animation-delay:.4s;">
                <div class="template-card__header">
                    <div class="template-card__icon-wrap">
                        <div class="template-skeleton-icon"></div>
                    </div>
                    <div class="template-card__info">
                        <div class="template-skeleton-line template-skeleton-line--short" style="height:15px;margin-bottom:6px;"></div>
                        <div class="template-card__meta">
                            <div class="template-skeleton-line" style="width:60px;height:20px;"></div>
                        </div>
                    </div>
                </div>
                <div class="template-card__body">
                    <div class="template-skeleton-line" style="width:40%;height:12px;margin-bottom:10px;"></div>
                    <div class="template-skeleton-line" style="width:100%;height:13px;margin-bottom:6px;"></div>
                    <div class="template-skeleton-line" style="width:75%;height:13px;"></div>
                </div>
                <div class="template-card__footer">
                    <div class="template-skeleton-line" style="width:80px;height:32px;border-radius:7px;"></div>
                    <div class="template-skeleton-line" style="width:80px;height:32px;border-radius:7px;"></div>
                </div>
            </div>
            <div class="template-card" style="animation:skeleton-pulse 1.5s ease-in-out infinite;animation-delay:.6s;">
                <div class="template-card__header">
                    <div class="template-card__icon-wrap">
                        <div class="template-skeleton-icon"></div>
                    </div>
                    <div class="template-card__info">
                        <div class="template-skeleton-line template-skeleton-line--short" style="height:15px;margin-bottom:6px;"></div>
                        <div class="template-card__meta">
                            <div class="template-skeleton-line" style="width:60px;height:20px;"></div>
                        </div>
                    </div>
                </div>
                <div class="template-card__body">
                    <div class="template-skeleton-line" style="width:40%;height:12px;margin-bottom:10px;"></div>
                    <div class="template-skeleton-line" style="width:100%;height:13px;margin-bottom:6px;"></div>
                    <div class="template-skeleton-line" style="width:75%;height:13px;"></div>
                </div>
                <div class="template-card__footer">
                    <div class="template-skeleton-line" style="width:80px;height:32px;border-radius:7px;"></div>
                    <div class="template-skeleton-line" style="width:80px;height:32px;border-radius:7px;"></div>
                </div>
            </div>
        </div>

        <!-- 中心服务不可达时的红色告警条；有内容才显示 -->
        <div id="templateLicenseAlert" style="display:none;padding:12px 16px; margin: 0 15px; margin-bottom:16px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:6px;font-size:13px;">
            <i class="fa fa-exclamation-triangle"></i> <span id="templateLicenseAlertMsg"></span>
        </div>

        <div class="template-grid" id="templateGrid"></div>

        <!-- 空：本地磁盘/中心服务校验后无任何已授权模板 -->
        <div class="template-empty" id="templateEmpty">
            <div class="template-empty__icon"><i class="fa fa-paint-brush"></i></div>
            <div>暂无可用模板</div>
            <div style="margin-top:8px;line-height:1.7;">
                请在 <a href="<?= EM_ADMIN_PATH ?>/appstore" data-pjax="#adminContent" style="color:#4f46e5;">应用商店</a> 购买后安装，<br>
                或把本地开发的模板放入 <code>content/template/</code>，并在 <code>header.php</code> 顶部加 <code>Custom: true</code>
            </div>
        </div>
    </div>
</div>

<!-- 灵动岛放在 .admin-page 之外：pageIn 动画的 transform 会临时成为 fixed 的包含块，
     放里面会导致先按内容区居中、动画结束再跳到视口居中 -->
<div class="em-island" id="templateUpdateStatus" hidden>
    <span class="em-island__icon"><i class="fa fa-spinner fa-spin" id="templateUpdateStatusIcon"></i></span>
    <span class="em-island__body">
        <span class="em-island__title" id="templateUpdateStatusText">正在检查更新…</span>
    </span>
</div>

<script>
'use strict';

var csrfToken = <?php echo json_encode($csrfToken); ?>;
var allTemplates = [];
var loading = false;
var actionLock = false;
var openLayers = {};
var updateChecking = false;
var updateChecked = false;               // 本轮页面是否已检查过
var updateMap = {};                      // 模板目录名 => {version, file_path}

function escHtml(str) {
    if (str == null) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function showLoading() {
    $('#templateWrapper').show();
    $('#templateSkeleton').show();
    $('#templateGrid, #templateEmpty').hide();
}

function showGrid() {
    $('#templateSkeleton, #templateEmpty').hide();
    $('#templateGrid').show();
}

function showEmpty() {
    $('#templateSkeleton, #templateGrid').hide();
    $('#templateEmpty').show();
}

function renderTemplates(items) {
    var $grid = $('#templateGrid');
    $grid.empty();

    if (!items.length) {
        showEmpty();
        return;
    }

    showGrid();
    items.forEach(function (item) {
        $grid.append(buildCard(item));
    });
}

// 有新版本但站点未授权：点卡片上的「更新」时弹出提示，引导去激活
function openLicenseRequired(version) {
    var v = String(version || '');
    var idx = layui.layer.open({
        type: 1,
        title: false,
        closeBtn: 0,
        skin: 'admin-modal',
        area: [window.innerWidth >= 640 ? '420px' : '92%', 'auto'],
        shadeClose: true,
        content:
            '<div class="update-lock">' +
                '<button type="button" class="update-lock__close" data-role="close" aria-label="关闭">&times;</button>' +
                '<div class="update-lock__icon"><i class="fa fa-lock"></i></div>' +
                '<div class="update-lock__title">检测到新版本' + (v !== '' ? ' v' + escHtml(v) : '') + '</div>' +
                '<div class="update-lock__sub">当前站点尚未激活，激活后才能下载并更新模板。</div>' +
                '<div class="update-lock__actions">' +
                    '<button type="button" class="update-lock__btn" data-role="later">我知道了</button>' +
                    '<button type="button" class="update-lock__btn update-lock__btn--primary" data-role="activate">立即激活</button>' +
                '</div>' +
            '</div>',
        success: function (layero) {
            layero.find('[data-role="close"], [data-role="later"]').on('click', function () {
                layui.layer.close(idx);
            });
            layero.find('[data-role="activate"]').on('click', function () {
                layui.layer.close(idx);
                openActivatePopup();
            });
        }
    });
}

// 激活弹窗（与后台首页 / 应用商店同一套交互）
function openActivatePopup() {
    layui.layer.open({
        type: 1,
        title: '立即激活',
        skin: 'admin-modal',
        area: [window.innerWidth >= 640 ? '460px' : '94%', 'auto'],
        shadeClose: false,
        content:
            '<div class="lic-activate">' +
                '<div class="lic-activate__hero">' +
                    '<div class="lic-activate__icon"><i class="fa fa-shield"></i></div>' +
                    '<div class="lic-activate__title">激活正版 EMSHOP</div>' +
                    '<div class="lic-activate__sub">解锁专属模板 · 付费插件 · 应用商店 · 优先技术支持</div>' +
                '</div>' +
                '<label class="lic-activate__label" for="templateLicenseCode">授权许可证（License Key）</label>' +
                '<div class="lic-activate__field">' +
                    '<i class="fa fa-key"></i>' +
                    '<input type="text" id="templateLicenseCode" class="lic-activate__input" placeholder="请输入您的激活码" maxlength="64" autocomplete="off" spellcheck="false">' +
                    '<button type="button" class="lic-activate__inline-btn" data-role="submit">立即激活</button>' +
                '</div>' +
                '<div class="lic-activate__note"><i class="fa fa-lock"></i> 激活码与当前域名绑定，请妥善保管</div>' +
            '</div>',
        success: function (layero) {
            var $input = layero.find('.lic-activate__input');
            var $btn = layero.find('[data-role="submit"]');

            function submit() {
                var code = $.trim($input.val() || '');
                if (code === '') { $input.focus(); layui.layer.msg('请输入激活码'); return; }

                var orig = $btn.html();
                $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> 激活中');
                $.ajax({
                    url: '<?= EM_ADMIN_PATH ?>/official',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _action: 'activate',
                        license_code: code,
                        csrf_token: window.adminCsrfToken || ''
                    }
                }).done(function (res) {
                    if (res && res.code === 200) {
                        if (res.data && res.data.csrf_token) window.adminCsrfToken = res.data.csrf_token;
                        layui.layer.msg('激活成功：' + escHtml((res.data && res.data.level_label) || ''));
                        setTimeout(function () { location.reload(); }, 900);
                        return;
                    }
                    layui.layer.msg((res && res.msg) || '激活失败');
                    $btn.prop('disabled', false).html(orig);
                }).fail(function () {
                    layui.layer.msg('网络异常');
                    $btn.prop('disabled', false).html(orig);
                });
            }

            $btn.on('click', submit);
            $input.on('keydown', function (e) {
                if (e.key === 'Enter') submit();
            }).focus();
        }
    });
}

function buildCard(item) {
    // return '';
    var previewHtml = item.preview
        ? '<img src="' + escHtml(item.preview) + '" class="template-card__icon" alt="">'
        : '<div class="template-card__icon--default"><i class="fa fa-paint-brush"></i></div>';

    var authorHtml = '';
    if (item.author) {
        authorHtml = item.author_url
            ? '<span class="template-card__author"><i class="fa fa-user-o"></i><a href="' + escHtml(item.author_url) + '" target="_blank">' + escHtml(item.author) + '</a></span>'
            : '<span class="template-card__author"><i class="fa fa-user-o"></i>' + escHtml(item.author) + '</span>';
    }

    var installStatusHtml = item.is_installed
        ? '<span class="template-card__status template-card__status--installed">已安装</span>'
        : '<span class="template-card__status template-card__status--new">未安装</span>';

    // 右侧按钮：有新版本时优先展示"更新 vX.X.X"（覆盖配置按钮）；无新版本则按原逻辑展示"配置"
    var rightBtn;
    if (item.has_update && item.latest_version) {
        if (item.latest_file_path) {
            rightBtn = '<button class="template-card__action template-card__action--update" '
                     +   'data-action="update" '
                     +   'data-name="' + escHtml(item.name) + '" '
                     +   'data-file-path="' + escHtml(item.latest_file_path) + '" '
                     +   'data-version="' + escHtml(item.latest_version) + '" '
                     +   'type="button"><i class="fa fa-cloud-download"></i> 更新 v' + escHtml(item.latest_version) + '</button>';
        } else {
            // 未授权：中心只回了版本号、没给下载地址
            rightBtn = '<span class="template-card__action template-card__action--update-locked" '
                     +   'data-action="needLicense" '
                     +   'data-name="' + escHtml(item.name) + '" '
                     +   'data-version="' + escHtml(item.latest_version) + '" '
                     +   '><i class="fa fa-cloud-download"></i> 更新 v' + escHtml(item.latest_version) + '</span>';
        }
    } else {
        rightBtn = item.setting_file
            ? '<button class="template-card__action template-card__action--settings" data-action="settings" data-name="' + escHtml(item.name) + '" type="button"><i class="fa fa-gear"></i> 配置</button>'
            : '<button class="template-card__action template-card__action--settings template-card__action--disabled" type="button" disabled data-tip="该模板无配置项"><i class="fa fa-gear"></i> 配置</button>';
    }

    var footerHtml = '';
    if (!item.is_installed) {
        footerHtml = '';
    } else {
        var pcClass = item.is_active_pc ? ' is-active' : '';
        var mobileClass = item.is_active_mobile ? ' is-active' : '';
        footerHtml = '<div class="template-card__actions-left">'
            + '<button class="template-card__action template-card__action--pc' + pcClass + '" data-action="activate_pc" data-name="' + escHtml(item.name) + '"><i class="fa fa-desktop"></i> PC启用</button>'
            + '<button class="template-card__action template-card__action--mobile' + mobileClass + '" data-action="activate_mobile" data-name="' + escHtml(item.name) + '"><i class="fa fa-mobile"></i> 手机启用</button>'
            + '</div>'
            + '<div class="template-card__actions-right">' + rightBtn + '</div>';
    }

    var html = '';
    html += '<div class="template-card" data-name="' + escHtml(item.name) + '">';
    html += '  <div class="template-card__header">';
    html += '      <div class="template-card__icon-wrap">' + previewHtml + '</div>';
    html += '      <div class="template-card__info">';
    html += '          <div class="template-card__title">' + escHtml(item.title || item.name) + '</div>';
    html += '          <div class="template-card__meta"><span class="template-card__version">v' + escHtml(item.version || '1.0.0') + '</span>' + installStatusHtml + '</div>';
    html += '      </div>';
    html += '      <div class="template-card__more">';
    html += '          <button class="template-card__more-btn"><i class="fa fa-ellipsis-h"></i></button>';
    html += '          <div class="template-card__more-dropdown">';
    if (item.is_installed) {
        html += '              <button class="template-card__more-item" data-action="uninstall" data-name="' + escHtml(item.name) + '"><i class="fa fa-trash"></i> 卸载</button>';
    }
    html += '          </div>';
    html += '      </div>';
    html += '  </div>';
    html += '  <div class="template-card__body">';
    html +=        authorHtml;
    html += '      <div class="template-card__desc">' + escHtml(item.description || '暂无描述') + '</div>';
    html += '  </div>';
    html += '  <div class="template-card__footer">';
    html +=        footerHtml;
    html += '  </div>';
    html += '</div>';
    return html;
}

// 只在检查期间显示「正在检查更新…」，检查结束立刻收起（不展示结果）
function showUpdateChecking() {
    $('#templateUpdateStatus')
        .removeAttr('hidden')
        .removeClass('is-done is-new is-error')
        .addClass('is-checking');
    $('#templateUpdateStatusIcon').attr('class', 'fa fa-spinner fa-spin');
    $('#templateUpdateStatusText').text('正在检查更新…');
}

function hideUpdateStatus() {
    $('#templateUpdateStatus').attr('hidden', true);
}

// 把检查结果合并进列表数据（rerender=false 时只改字段不重绘）
function applyUpdateMap(rerender) {
    var count = 0;
    allTemplates.forEach(function (item) {
        var u = updateMap[item.name];
        if (u && u.version) {
            item.has_update = true;
            item.latest_version = u.version;
            item.latest_file_path = u.file_path || '';
            count++;
        } else {
            item.has_update = false;
            item.latest_version = '';
            item.latest_file_path = '';
        }
    });
    if (rerender !== false) {
        renderTemplates(allTemplates);
    }
    return count;
}

// 单独请求中心查更新：结果只回填卡片；灵动岛只在请求期间显示，结束就收起
function checkUpdates() {
    if (updateChecking) return;
    if (!allTemplates.length) { hideUpdateStatus(); return; }   // 没有模板就不用查
    updateChecking = true;
    showUpdateChecking();

    $.ajax({
        url: '<?= EM_ADMIN_PATH ?>/template?_action=check_updates',
        type: 'GET',
        dataType: 'json',
        success: function (res) {
            if (!res || (res.code !== 0 && res.code !== 200)) return;
            csrfToken = res.csrf_token || csrfToken;
            if (res.error) return;
            updateMap = res.data || {};
            applyUpdateMap();
        },
        complete: function () {
            updateChecking = false;
            updateChecked = true;
            hideUpdateStatus();
        }
    });
}

function loadTemplates() {
    if (loading) return;
    loading = true;
    $('#templateEmpty, #templateLicenseAlert').hide();
    showLoading();

    $.ajax({
        url: '<?= EM_ADMIN_PATH ?>/template?_action=list',
        type: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.code === 0 || res.code === 200) {
                csrfToken = res.csrf_token || csrfToken;
                allTemplates = res.data || [];
                // 先用上一次的检查结果补字段（避免刷新后角标短暂消失），再单独查更新
                applyUpdateMap(false);
                renderTemplates(allTemplates);
                checkUpdates();
            } else {
                layui.layer.msg(res.msg || '加载失败', {icon: 2});
                showEmpty();
            }
        },
        error: function () {
            layui.layer.msg('加载模板列表失败', {icon: 2});
            showEmpty();
        },
        complete: function () {
            loading = false;
        }
    });
}

function doRequest(action, name, extraData, done) {
    if (actionLock) return;
    actionLock = true;
    var postData = {csrf_token: csrfToken, _action: action, name: name};
    if (extraData) {
        for (var k in extraData) postData[k] = extraData[k];
    }
    $.ajax({
        url: '<?= EM_ADMIN_PATH ?>/template',
        type: 'POST',
        dataType: 'json',
        data: postData,
        success: function (res) {
            if (res.code === 0 || res.code === 200) {
                csrfToken = (res.data && res.data.csrf_token) ? res.data.csrf_token : csrfToken;
                layui.layer.msg(res.msg || '操作成功', {icon: 1});
                loadTemplates();
            } else {
                layui.layer.msg(res.msg || '操作失败', {icon: 2});
            }
            if (done) done();
        },
        error: function () {
            layui.layer.msg('网络异常', {icon: 2});
            if (done) done();
        },
        complete: function () {
            actionLock = false;
        }
    });
}

/**
 * 模板更新：复用应用商店的 update action（{入口}/appstore）
 * 该 action 会下载最新 zip → 解压覆盖 content/template/{name} → 更新 em_template.version
 * 注意：appstore.php 的 update 接口成功时返回新的 csrf_token,需同步到本地 csrfToken 避免后续 template.php 的 POST 失效
 */
function doUpdateTemplate(name, filePath, version) {
    if (actionLock) return;
    actionLock = true;
    var loadingIdx = layui.layer.load(2, { shade: [0.3, '#000'] });
    $.ajax({
        url: '<?= EM_ADMIN_PATH ?>/appstore',
        type: 'POST',
        dataType: 'json',
        data: {
            csrf_token: csrfToken,
            _action:    'update',
            name:       name,
            type:       'template',
            file_path:  filePath,
            version:    version
        },
        success: function (res) {
            layui.layer.close(loadingIdx);
            if (res && (res.code === 0 || res.code === 200)) {
                if (res.data && res.data.csrf_token) {
                    csrfToken = res.data.csrf_token;
                }
                layui.layer.msg('模板已更新至 v' + emEsc(version));
                loadTemplates();
            } else {
                layui.layer.msg((res && res.msg) || '更新失败');
            }
        },
        error: function () {
            layui.layer.close(loadingIdx);
            layui.layer.msg('网络异常');
        },
        complete: function () {
            actionLock = false;
        }
    });
}

function doActivateRequest(action, name) {
    if (actionLock) return;
    actionLock = true;
    var $btn = $('.template-card__action[data-action="' + action + '"][data-name="' + name + '"]');
    var isPc = (action === 'activate_pc');
    var targetKey = isPc ? 'is_active_pc' : 'is_active_mobile';
    var $card = $btn.closest('.template-card');

    // 乐观更新：先切换 UI 状态
    var wasActive = $btn.hasClass('is-active');
    var willBeActive = !wasActive;

    if (willBeActive) {
        $btn.addClass('is-active');
    } else {
        $btn.removeClass('is-active');
    }

    // 如果是激活操作，UI 上先把同类型已激活的其他按钮也切换状态
    if (willBeActive) {
        $('.template-card__action[data-action="' + action + '"].is-active').not($btn).each(function () {
            var $other = $(this);
            if ($other.data('name') !== name) {
                $other.removeClass('is-active');
                $other.html('<i class="fa fa-' + (isPc ? 'desktop' : 'mobile') + '"></i> ' + (isPc ? 'PC启用' : '手机启用'));
            }
        });
    }

    $.ajax({
        url: '<?= EM_ADMIN_PATH ?>/template',
        type: 'POST',
        dataType: 'json',
        data: {csrf_token: csrfToken, _action: action, name: name},
        success: function (res) {
            if (res.code === 0 || res.code === 200) {
                csrfToken = (res.data && res.data.csrf_token) ? res.data.csrf_token : csrfToken;
                layui.layer.msg(res.msg || '操作成功');
                // 更新内存中的数据
                allTemplates.forEach(function (t) {
                    if (willBeActive) {
                        t[targetKey] = false;
                    }
                });
                var template = allTemplates.find(function (t) { return t.name === name; });
                if (template) {
                    template[targetKey] = willBeActive;
                }
            } else {
                layui.layer.msg(res.msg || '操作失败', {icon: 2});
                // 失败时还原 UI
                if (wasActive) {
                    $btn.addClass('is-active');
                } else {
                    $btn.removeClass('is-active');
                }
            }
        },
        error: function () {
            layui.layer.msg('网络异常', {icon: 2});
            // 失败时还原 UI
            if (wasActive) {
                $btn.addClass('is-active');
            } else {
                $btn.removeClass('is-active');
            }
        },
        complete: function () {
            actionLock = false;
        }
    });
}

function openSettings(name) {
    var layerId = 'template_settings_' + name;
    if (openLayers[layerId]) {
        try { layui.layer.restore(openLayers[layerId]); } catch (e) {}
        return;
    }
    layui.layer.open({
        type: 2,
        title: '模板设置',
        skin: 'admin-modal',
        maxmin: true,
        area: [window.innerWidth >= 800 ? '600px' : '95%', window.innerHeight >= 600 ? '75%' : '90%'],
        shadeClose: true,
        content: '<?= EM_ADMIN_PATH ?>/template?_popup=1&name=' + encodeURIComponent(name),
        id: layerId,
        success: function (layero, index) {
            openLayers[layerId] = index;
        },
        end: function () {
            delete openLayers[layerId];
        }
    });
}

// ===== 事件绑定（使用 off/on 确保 PJAX 切换后不重复绑定）=====
$(function () {
    layui.use('layer', function () {
        // 刷新
        $(document).off('click.templateRefresh').on('click.templateRefresh', '#templateRefreshBtn', function () { loadTemplates(); });

        // 应用商店按钮点击
        $(document).off('click.appstoreBtn').on('click.appstoreBtn', '#appstoreBtn', function () {
            $.pjax({ url: '<?= EM_ADMIN_PATH ?>/appstore', container: '#adminContent' });
        });

        // 三点菜单
        $(document).off('click.moreBtn').on('click.moreBtn', '.template-card__more-btn', function (e) {
            e.stopPropagation();
            var $dropdown = $(this).siblings('.template-card__more-dropdown');
            $('.template-card__more-dropdown').not($dropdown).removeClass('template-card__more-dropdown--open');
            $dropdown.toggleClass('template-card__more-dropdown--open');
        });

        // 点击其他地方关闭菜单
        $(document).off('click.closeDropdown').on('click.closeDropdown', function () {
            $('.template-card__more-dropdown').removeClass('template-card__more-dropdown--open');
        });

        // tips 提示
        $(document).off('mouseenter.moreTip mouseenter.moreTip mouseenter.moreTip').on('mouseenter.moreTip', '[data-tip]', function () {
            layui.layer.tips($(this).data('tip'), this, {tips: [1, '#555'], time: 0, closeBtn: true});
        }).off('mouseleave.moreTip').on('mouseleave.moreTip', '[data-tip]', function () {
            layui.layer.closeAll('tips');
        });

        // 图片点击放大
        $(document).off('click.imgPreview').on('click.imgPreview', '.template-card__icon-wrap img', function () {
            var src = $(this).attr('src');
            if (!src) return;
            layui.layer.photos({ photos: { title: '', id: 0, start: 0, data: [{alt: '模板预览', pid: 0, src: src}] }, anim: 5 });
        });

        // 卡片操作按钮（footer 区域的按钮）
        $(document).off('click.cardAction').on('click.cardAction', '.template-card__action', function (e) {
            e.stopPropagation();
            var $btn = $(this);
            var action = $btn.data('action');
            var name = $btn.data('name');
            if (!action || !name) return;
            if (action === 'needLicense') {
                openLicenseRequired($btn.data('version'));
                return;
            }
            if (action === 'settings') {
                openSettings(name);
                return;
            }
            if (action === 'activate_pc' || action === 'activate_mobile') {
                doActivateRequest(action, name);
                return;
            }
            if (action === 'update') {
                // 走应用商店的 update 流程（复用下载+解压+注册逻辑）
                doUpdateTemplate(name, $btn.data('file-path') || '', String($btn.data('version') || ''));
                return;
            }
            doRequest(action, name);
        });

        // 三点菜单下拉项（uninstall / delete 等）
        $(document).off('click.moreItem').on('click.moreItem', '.template-card__more-item', function (e) {
            e.stopPropagation();
            var action = $(this).data('action');
            var name = $(this).data('name');
            if (!action || !name) return;
            if (action === 'uninstall') {
                layui.layer.confirm('卸载将清除模板的配置数据并删除磁盘文件，无法恢复。确认卸载？', {icon: 3, title: '卸载确认', skin: 'admin-modal'}, function (idx) {
                    doRequest(action, name, null, function () { layui.layer.close(idx); });
                });
                return;
            }
            doRequest(action, name);
        });

        // 初始加载
        loadTemplates();
    });
});
</script>
