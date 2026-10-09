<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

/**
 * 账号页外壳（登录 / 注册共用）——**完整 HTML 文档**。
 *
 * 这两页不走主题：不套 header/footer、不加载主题 style.css、也不依赖主题 module.php，
 * 所以本文件自带 <head>、样式与脚本。样式见 /content/static/css/auth.css。
 *
 * 用法（见 login.php / register.php）：
 *   用输出缓冲把表单塞进 $auth_form_html，再 include 本文件。
 *
 * 需要的变量：
 *   $auth_title       卡片大标题
 *   $auth_desc        大标题下面那句说明
 *   $auth_form_html   表单区 HTML（各页自己拼）
 *   $auth_footer_html 卡片下方那行链接（可空）
 *   $auth_headline    左侧品牌面大标题（可空，默认「登录你的账号」）
 *   $auth_blurb       左侧品牌面说明（可空）
 *   $auth_pill        卡片顶上那个角色药丸文字（可空，默认「用户入口」）
 */

$authTitle      = (string) ($auth_title ?? '');
$authDesc       = (string) ($auth_desc ?? '');
$authFormHtml   = (string) ($auth_form_html ?? '');
$authFooterHtml = (string) ($auth_footer_html ?? '');
$authHeadline   = (string) ($auth_headline ?? '登录你的账号');
$authBlurb      = (string) ($auth_blurb ?? '参与活动、领取授权码，都在这里。');
$authPill       = (string) ($auth_pill ?? '用户入口');

// 站点名 / 品牌：与主题 header 取同一份配置（换主题不影响这两页）
$authSiteName = (string) ($site_name ?? Config::get('sitename', 'EMSHOP'));
$authLogoType = (string) Config::get('site_logo_type', 'text');
$authLogo     = (string) Config::get('site_logo', '');

$authPageTitle = trim((string) ($page_title ?? '')) !== ''
    ? (string) $page_title . ' - ' . $authSiteName
    : $authSiteName;

/** 品牌标：配了图片用图片，否则用站点名（与主题 header 的行为一致） */
$authBrandHtml = ($authLogoType === 'image' && $authLogo !== '')
    ? '<img src="' . htmlspecialchars($authLogo) . '" alt="' . htmlspecialchars($authSiteName) . '">'
    : '<span class="auth-brand-name">' . htmlspecialchars($authSiteName) . '</span>';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($authPageTitle) ?></title>
<link rel="icon" href="<?= htmlspecialchars(site_favicon_href()) ?>">
<link rel="stylesheet" href="/content/static/lib/font-awesome-4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="/content/static/lib/layui-v2.13.5/layui/css/layui.css">
<!-- 全站统一提示（EmToast）样式：必须排在 layui.css 之后才能盖掉它的默认白底 -->
<link rel="stylesheet" href="/content/static/css/em-toast.css">
<!-- 账号页专属样式：自给自足，不依赖主题 style.css -->
<link rel="stylesheet" href="/content/static/css/auth.css">
<script src="/content/static/lib/jquery.min.3.5.1.js"></script>
<script src="/content/static/lib/layui-v2.13.5/layui/layui.js"></script>
<!-- 全站统一提示（EmToast）：账号页的弹出提示都走它，和后台保持一致 -->
<script src="/content/static/js/em-toast.js"></script>
</head>
<body>
<div class="auth-screen">

    <!-- 这几页没有站点导航，留一个回首页的出口 -->
    <a class="auth-back" href="<?= htmlspecialchars(url_home()) ?>"><span aria-hidden="true">&larr;</span> 返回首页</a>

    <div class="auth-wrap">
        <div class="auth-grid">

            <!-- 左：品牌面（窄屏隐藏） -->
            <div class="auth-brand">
                <div class="auth-brand-logo"><?= $authBrandHtml ?></div>
                <p class="auth-eyebrow">USER ACCESS</p>
                <h1 class="auth-headline"><?= htmlspecialchars($authHeadline) ?></h1>
                <div class="auth-bar"></div>
                <p class="auth-blurb"><?= htmlspecialchars($authBlurb) ?></p>
                <!-- 同心圆：与项目其它入口区分的纹理，也当装饰 -->
                <div class="auth-motif">
                    <svg viewBox="0 0 120 120" width="112" height="112" aria-hidden="true">
                        <g fill="none" stroke="#2563eb" stroke-opacity="0.35">
                            <circle cx="60" cy="60" r="54"></circle>
                            <circle cx="60" cy="60" r="40"></circle>
                            <circle cx="60" cy="60" r="26"></circle>
                        </g>
                        <circle cx="60" cy="60" r="7" fill="#2563eb"></circle>
                    </svg>
                </div>
            </div>

            <!-- 右：表单卡片 -->
            <div>
                <div class="auth-card">
                    <div class="auth-card-logo"><?= $authBrandHtml ?></div>

                    <span class="auth-pill"><?= htmlspecialchars($authPill) ?></span>
                    <h2 class="auth-title"><?= htmlspecialchars($authTitle) ?></h2>
                    <p class="auth-desc"><?= htmlspecialchars($authDesc) ?></p>

                    <?= $authFormHtml ?>
                </div>

                <?php if ($authFooterHtml !== ''): ?>
                <div class="auth-foot"><?= $authFooterHtml ?></div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>
</body>
</html>
