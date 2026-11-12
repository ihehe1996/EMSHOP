<?php
/**
 * 前台未授权横幅（由 View 在 <body> 之后注入）。
 *
 * 只在 LicenseService::isActivated() === false 时输出；隐藏状态由 Cookie
 * `em_license_notice` 决定，见 View::licenseBannerHtml()：
 *   - today:YYYY-MM-DD  当日有效，次日零点失效
 *   - session           浏览器会话内有效，关浏览器后重新出现
 *
 * 样式 / 脚本全部内联，不依赖主题 CSS / JS，任何主题都能用。
 */
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

$emBannerDate = date('Y-m-d');
/* 当日隐藏：Cookie 到期时间取服务器时区的明天零点 */
$emBannerExpire = strtotime('tomorrow');


$emBannerTexts = [
    'title' => "\u{8BE5}\u{7AD9}\u{70B9}\u{6682}\u{672A}\u{6FC0}\u{6D3B}\u{6B63}\u{7248}\u{6388}\u{6743}",
    'today' => "\u{4ECA}\u{65E5}\u{4E0D}\u{518D}\u{663E}\u{793A}",
    'ok'    => "\u{6211}\u{77E5}\u{9053}\u{4E86}",
];
?>
<div class="em-license-banner" id="emLicenseBanner" role="alert">
    <div class="em-license-banner__inner">
        <span class="em-license-banner__main">
            <i class="fa fa-exclamation-circle em-license-banner__icon" aria-hidden="true"></i>
            <span class="em-license-banner__text"><?= htmlspecialchars($emBannerTexts['title'], ENT_QUOTES, 'UTF-8') ?></span>
        </span>
        <span class="em-license-banner__actions">
            <button type="button" class="em-license-banner__btn em-license-banner__btn--ghost"
                    data-em-license-action="today"><?= htmlspecialchars($emBannerTexts['today'], ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" class="em-license-banner__btn"
                    data-em-license-action="ok"><?= htmlspecialchars($emBannerTexts['ok'], ENT_QUOTES, 'UTF-8') ?></button>
        </span>
    </div>
</div>
<style>
.em-license-banner {
    position: relative;
    z-index: 9999;
    width: 100%;
    box-sizing: border-box;
    background:
        radial-gradient(720px 120px at 50% -40%, rgba(99, 102, 241, 0.16), transparent 70%),
        linear-gradient(180deg, #f9faff 0%, #eef2ff 100%);
    border-bottom: 1px solid #dbe3ff;
    box-shadow: 0 2px 10px rgba(79, 70, 229, 0.06);
    color: #334155;
    font-size: 13px;
    line-height: 1.5;
}
.em-license-banner__inner {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 8px 20px;
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
    padding: 9px 16px;
    box-sizing: border-box;
    text-align: center;
}
.em-license-banner__main {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    min-width: 0;
    line-height: 20px;
}
.em-license-banner__icon {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 20px;
    color: #4f46e5;
    font-size: 15px;
    line-height: 1;
    margin-top: 4.3px;
}
.em-license-banner__text {
    font-size: 13px;
    font-weight: 500;
    color: #334155;
    line-height: 20px;
    letter-spacing: 0.2px;
}
.em-license-banner__actions {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    flex-shrink: 0;
}
.em-license-banner__btn {
    height: auto;
    padding: 2px 0;
    border: 0;
    border-radius: 4px;
    background: none;
    box-shadow: none;
    color: #4f46e5;
    font-family: inherit;
    font-size: 12.5px;
    font-weight: 500;
    cursor: pointer;
    transition: color 0.15s ease;
}
.em-license-banner__btn:hover {
    color: #3730a3;
    text-decoration: underline;
    text-underline-offset: 3px;
}
.em-license-banner__btn--ghost {
    background: none;
    color: #64748b;
    font-weight: 400;
}
.em-license-banner__btn--ghost:hover {
    background: none;
    color: #334155;
}
.em-license-banner.is-hiding {
    opacity: 0;
    transition: opacity 0.2s ease;
}
@media (max-width: 640px) {
    .em-license-banner__inner {
        gap: 8px;
        padding: 10px 12px;
    }
}
</style>
<script>
(function () {
    var banner = document.getElementById('emLicenseBanner');
    if (!banner) return;

    var COOKIE = 'em_license_notice';
    var hideDate = <?= json_encode($emBannerDate, JSON_UNESCAPED_SLASHES) ?>;
    var nextMidnight = <?= (int) $emBannerExpire ?>;

    banner.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-em-license-action]') : null;
        if (!btn) return;

        var action = btn.getAttribute('data-em-license-action');
        if (action === 'today') {
            /* 当日有效：到服务器时区的明天零点自动失效 */
            var expires = new Date(nextMidnight * 1000).toUTCString();
            document.cookie = COOKIE + '=today:' + hideDate + '; expires=' + expires + '; path=/; SameSite=Lax';
        } else {
            /* 本次会话内隐藏：会话 Cookie，关掉浏览器后重新出现 */
            document.cookie = COOKIE + '=session; path=/; SameSite=Lax';
        }

        banner.classList.add('is-hiding');
        setTimeout(function () {
            if (banner.parentNode) banner.parentNode.removeChild(banner);
        }, 200);
    });
})();
</script>
