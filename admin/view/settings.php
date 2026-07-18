<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$esc = function (string $str) use (&$esc): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
};

// 读取所有配置
$cfg = Config::load();
$siteUrlConfigured = trim((string) ($cfg['site_url'] ?? '')) !== '';
$placeholderImg = defined('EM_CONFIG') && isset(EM_CONFIG['placeholder_img']) ? EM_CONFIG['placeholder_img'] : '';
$templateModel = class_exists('TemplateModel') ? new TemplateModel() : null;
$availableThemes = [];
if ($templateModel !== null) {
    foreach ($templateModel->scanTemplates() as $themeName => $themeInfo) {
        $availableThemes[$themeName] = (string) ($themeInfo['title'] ?: $themeName);
    }
}
if ($availableThemes === []) {
    $availableThemes = ['default' => '默认模板'];
}

// tab 配置
$tabs = [
    'base'        => ['label' => '基础设置',       'icon' => 'fa fa-gear'],
    'security'    => ['label' => '安全设置',       'icon' => 'fa fa-shield'],
    'seo'         => ['label' => 'SEO 设置',       'icon' => 'fa fa-search'],
    'user'        => ['label' => '用户设置',       'icon' => 'fa fa-users'],
    'shop'        => ['label' => '商城设置',       'icon' => 'fa fa-shopping-cart'],
    'guest_find'  => ['label' => '查单模式',       'icon' => 'fa fa-search'],
    'rebate'      => ['label' => '推广返佣',       'icon' => 'fa fa-share-alt'],
    'blog'        => ['label' => '博客设置',       'icon' => 'fa fa-pencil-square'],
    'mail'        => ['label' => '邮箱配置',       'icon' => 'fa fa-envelope'],
    'substation'  => ['label' => '分站配置',       'icon' => 'fa fa-sitemap'],
];
$needSiteUrlTip = (string) Input::get('_need_site_url', '') === '1' && !$siteUrlConfigured;

// 辅助：生成 input/select 控件
function formInput(string $name, string $value, string $placeholder = '', int $maxlength = 0): string {
    $max = $maxlength > 0 ? ' maxlength="' . $maxlength . '"' : '';
    $val = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $ph  = htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8');
    return '<input type="text" class="layui-input" name="' . $name . '" value="' . $val . '" placeholder="' . $ph . '"' . $max . '>';
}

/**
 * 带单位后缀的紧凑输入控件（替代 layui-input-suffix 在某些布局下错位的问题）。
 *   左侧数字输入，右侧灰色"分钟 / 元 / %"等单位胶囊紧贴边框。
 *
 * 用法：formInputWithSuffix('shop_order_expire_minutes', '30', '分钟', '60');
 */
function formInputWithSuffix(string $name, string $value, string $suffix, string $placeholder = '', string $type = 'text'): string {
    $val = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $ph  = htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8');
    $sfx = htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8');
    $t   = htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
    return '<div class="em-suffix-input">'
         .   '<input type="' . $t . '" class="em-suffix-input__field" name="' . $name . '" value="' . $val . '" placeholder="' . $ph . '">'
         .   '<span class="em-suffix-input__suffix">' . $sfx . '</span>'
         . '</div>';
}

function formTextarea(string $name, string $value, string $placeholder = '', int $rows = 4): string {
    $val = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $ph  = htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8');
    return '<textarea class="layui-textarea" name="' . $name . '" placeholder="' . $ph . '" rows="' . $rows . '" style="resize:vertical;min-height:80px;">' . $val . '</textarea>';
}

function formSwitch(string $name, string $value): string {
    $checked = $value === '1' || $value === 'true' ? ' checked' : '';
    return '<input type="checkbox" name="' . $name . '" value="1" lay-skin="switch" lay-text="开启|关闭" lay-filter="switch_' . $name . '"' . $checked . '>';
}

function formMoney(string $name, string $value, string $placeholder = ''): string {
    // 新版 BIGINT 存储（×1000000，至少7位纯数字），需除以 1000000 显示
    // 旧版十进制格式直接返回
    if ($value !== '' && preg_match('/^\d{7,}$/', $value)) {
        $value = bcdiv($value, '1000000', 2);
    }
    return formInput($name, $value, $placeholder);
}

function formSelect(string $name, array $options, string $selected = ''): string {
    $html = '<select name="' . $name . '" class="layui-input" style="width:200px;">';
    foreach ($options as $val => $label) {
        $sel = $val === $selected ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars((string) $val, ENT_QUOTES) . '"' . $sel . '>' . htmlspecialchars((string) $label) . '</option>';
    }
    $html .= '</select>';
    return $html;
}

// 单选框组（layui 原生 radio，支持 form.render('radio') 渲染）
function formRadio(string $name, array $options, string $selected = ''): string {
    $html = '';
    foreach ($options as $val => $label) {
        $checked = ((string) $val === $selected) ? ' checked' : '';
        $v = htmlspecialchars((string) $val, ENT_QUOTES);
        $t = htmlspecialchars((string) $label, ENT_QUOTES);
        $html .= '<input type="radio" name="' . $name . '" value="' . $v . '" title="' . $t . '"' . $checked . '>';
    }
    return $html;
}
?>
<style>
/* ====== 系统设置页作用域样式（只影响本页，不动全局 .admin-page）====== */
/* 整页透明：每个 tab 内部按业务再细分多个白底卡片，层次更清楚 */
.admin-page.admin-settings { background: transparent; box-shadow: none; padding: 0; }
.admin-settings__layout { min-height: auto; }
.admin-settings__panel  { padding: 0; }

/* form 外壳本身不再有白底，由内部 .admin-settings__block 承载 */
.admin-settings__form-wrap {
    background: transparent; border: none; padding: 0;
    max-width: none; box-shadow: none;
}

/* 通用分块卡片：每个 tab 内部按业务语义分成多块 */
.admin-settings__block {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 10px;
    padding: 18px 22px 6px;
    margin-bottom: 14px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.admin-settings__block:last-child { margin-bottom: 0; }
.admin-settings__block-title {
    font-size: 14px;
    font-weight: 600;
    color: #111827;
    margin: 0 0 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    align-items: center;
    gap: 8px;
}
.admin-settings__block-title i {
    color: #1e9fff;
    font-size: 14px;
    width: 16px;
    text-align: center;
}
.admin-settings .site-url-need-attention .layui-form-label {
    color: #dc2626;
    font-weight: 700;
}
.admin-settings .site-url-need-attention .layui-input {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.14);
}
.admin-settings .site-url-need-attention .layui-word-aux {
    color: #b91c1c;
}

/* 底部操作栏：单独一块，按钮左对齐 */
.admin-settings__actions-block {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 10px;
    padding: 14px 22px;
    margin-top: 14px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

/* 邮箱示例说明 —— 保留独立样式 */
.admin-mail-help {
    background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px;
    padding: 12px 16px;
    color: #4b5563; font-size: 12.5px; line-height: 1.85;
}
.admin-mail-help b { color: #111827; font-weight: 600; }
.admin-mail-help__title {
    color: #111827; font-weight: 600; font-size: 13px;
    margin-bottom: 6px;
    display: inline-flex; align-items: center; gap: 5px;
}
.admin-mail-help__title i { color: #6366f1; }

/* 带单位后缀的紧凑输入：替代 layui-input-suffix（在某些布局下错位）。
   左侧数字 input + 右侧灰色单位胶囊紧贴边框，悬停/聚焦时整体变蓝。 */
.em-suffix-input {
    display: inline-flex; align-items: stretch;
    height: 38px; max-width: 220px;
    border: 1px solid #d4d7dc; border-radius: 6px;
    background: #fff; overflow: hidden;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.em-suffix-input:hover { border-color: #b8bdc4; }
.em-suffix-input:focus-within {
    border-color: #5b8def;
    box-shadow: 0 0 0 3px rgba(91, 141, 239, 0.12);
}
.em-suffix-input__field {
    flex: 1; min-width: 0;
    padding: 0 12px;
    border: 0; outline: none;
    font-size: 13.5px; color: #1f2937;
    background: transparent;
    /* 隐藏 number 上下箭头，让宽度更紧凑 */
    -moz-appearance: textfield;
}
.em-suffix-input__field::-webkit-outer-spin-button,
.em-suffix-input__field::-webkit-inner-spin-button {
    -webkit-appearance: none; margin: 0;
}
.em-suffix-input__suffix {
    display: inline-flex; align-items: center; padding: 0 14px;
    background: #f3f4f6; color: #6b7280;
    font-size: 13px; font-weight: 500;
    border-left: 1px solid #e5e7eb;
    user-select: none;
}
.em-suffix-input:focus-within .em-suffix-input__suffix {
    background: #eef2ff; color: #4338ca; border-left-color: #c7d2fe;
}

/* checkbox group：卡片式胶囊按钮，勾选后蓝色高亮
   配合 input[lay-ignore] 让 layui 跳过 form.render('checkbox') 包装，
   这样我们的卡片样式才能落到原生 input 上，不被 layui 的 .layui-form-checkbox margin 干扰 */
.em-checkbox-group { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 2px; }
.em-checkbox {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 8px 14px; border: 1px solid #e5e7eb; border-radius: 8px;
    background: #fff; cursor: pointer; user-select: none;
    transition: all .15s; font-size: 13px; color: #4b5563;
}
.em-checkbox:hover { border-color: #c7d2fe; color: #4338ca; }
.em-checkbox input[type="checkbox"] { width: 14px; height: 14px; cursor: pointer; accent-color: #4e6ef2; }
.em-checkbox:has(input:checked) { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; font-weight: 500; }
.em-checkbox span i { margin-right: 4px; color: #9ca3af; }
.em-checkbox:has(input:checked) span i { color: #4338ca; }

/* 店铺公告富文本 */
#shopAnnouncementTextarea { max-width: 920px; }
</style>

<div class="admin-page admin-settings">
    <h1 class="admin-page__title">系统设置</h1>

    <div class="admin-settings__layout">
        <!-- 左侧选项卡导航（复用 em-tabs 样式） -->
        <div class="em-tabs em-tabs--scroll" id="settingsTabs">
            <?php foreach ($tabs as $key => $tab): ?>
            <a href="/admin/settings.php?action=<?php echo $key; ?>"
               class="em-tabs__item <?php echo $currentTab === $key ? 'is-active' : ''; ?>"
               data-tab="<?php echo $key; ?>" data-pjax="#adminContent">
                <i class="<?php echo $tab['icon']; ?>"></i>
                <span><?php echo $tab['label']; ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- 右侧表单区域 -->
        <div class="admin-settings__panel">

            <?php /* ==================== 基础设置 ==================== */ ?>
            <?php if ($currentTab === 'base'): ?>
            <div class="admin-settings__form-wrap" id="tab-base">
                <form class="layui-form admin-settings__form" id="form-base" autocomplete="off">
                    <input type="hidden" name="_tab" value="base">
                    <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">

                    <!-- 站点信息块：名称 / Logo / 备案 / 版权 -->
                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-globe"></i>站点信息</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">站点开启</label>
                            <div class="layui-input-block">
                                <?php $siteEnabled = $cfg['site_enabled'] ?? '1'; ?>
                                <input type="radio" name="site_enabled" value="1" title="开启" <?php echo $siteEnabled === '1' ? 'checked' : ''; ?>>
                                <input type="radio" name="site_enabled" value="0" title="关闭" <?php echo $siteEnabled === '0' ? 'checked' : ''; ?>>
                            </div>
                            <div class="layui-form-mid layui-word-aux">站点开启|关闭（用于升级等临时关闭），关闭后前端会弹窗显示站点升级中，请稍后访问</div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">站点名称</label>
                            <div class="layui-input-block">
                                <?php echo formInput('sitename', $cfg['sitename'] ?? '', '站点名称'); ?>
                            </div>
                        </div>
                        <div class="layui-form-item<?php echo $needSiteUrlTip ? ' site-url-need-attention' : ''; ?>" id="siteUrlFormItem">
                            <label class="layui-form-label">站点地址</label>
                            <div class="layui-input-block">
                                <input type="text" class="layui-input" id="siteUrlInput" name="site_url" value="<?php echo $esc((string) ($cfg['site_url'] ?? '')); ?>" placeholder="如：https://example.com">
                            </div>
                            <div class="layui-form-mid layui-word-aux">
                                请填写可公网访问的主站 URL（必须含 http:// 或 https://），用于回调地址生成等跨站对接场景。
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">网站 ICO</label>
                            <div class="layui-input-block">
                                <?php
                                $faviconVal = (string) ($cfg['site_favicon'] ?? '');
                                $faviconPreview = $faviconVal !== '' ? $faviconVal : '/favicon.ico';
                                ?>
                                <div class="admin-profile__avatar-field" id="faviconField" data-placeholder="/favicon.ico">
                                    <img src="<?php echo $esc($faviconPreview); ?>" alt="Favicon" id="faviconPreview" width="32" height="32" style="width:32px;height:32px;object-fit:contain;border-radius:6px;background:#f1f5f9;border:1px solid #e2e8f0;" layer-src="<?php echo $esc($faviconPreview); ?>" onerror="this.src='<?php echo $esc($placeholderImg ?: '/favicon.ico'); ?>';this.onerror=null;">
                                    <input type="text" class="admin-profile__avatar-url" id="faviconUrl" name="site_favicon" maxlength="500" placeholder="图标 URL，留空则使用网站根目录 favicon.ico" value="<?php echo $esc($faviconVal); ?>">
                                    <div class="admin-profile__avatar-btns">
                                        <button type="button" class="layui-btn layui-btn-xs" id="faviconUploadBtn" title="上传"><i class="fa fa-upload"></i></button>
                                        <button type="button" class="layui-btn layui-btn-xs layui-btn-normal" id="faviconPickBtn" title="选择"><i class="fa fa-image"></i></button>
                                        <button type="button" class="layui-btn layui-btn-xs layui-btn-danger" id="faviconClearBtn" title="清除"><i class="fa fa-times"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="layui-form-mid layui-word-aux">浏览器标签页图标；支持 ICO、PNG 等，建议 32×32；留空使用根路径 /favicon.ico</div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">网站 Logo</label>
                            <div class="layui-input-block">
                                <div class="admin-profile__avatar-field" id="logoField" data-placeholder="<?php echo $esc($placeholderImg); ?>">
                                    <img src="<?php echo !empty($cfg['site_logo']) ? $esc($cfg['site_logo']) : $placeholderImg; ?>" alt="网站 Logo" id="logoPreview" layer-src="<?php echo !empty($cfg['site_logo']) ? $esc($cfg['site_logo']) : $esc($placeholderImg); ?>" onerror="this.src='<?php echo $esc($placeholderImg); ?>';this.onerror=null;">
                                    <input type="text" class="admin-profile__avatar-url" id="logoUrl" name="site_logo" maxlength="500" placeholder="Logo 图片 URL，可上传或选择" value="<?php echo $esc($cfg['site_logo'] ?? ''); ?>">
                                    <div class="admin-profile__avatar-btns">
                                        <button type="button" class="layui-btn layui-btn-xs" id="logoUploadBtn" title="上传"><i class="fa fa-upload"></i></button>
                                        <button type="button" class="layui-btn layui-btn-xs layui-btn-normal" id="logoPickBtn" title="选择"><i class="fa fa-image"></i></button>
                                        <button type="button" class="layui-btn layui-btn-xs layui-btn-danger" id="logoClearBtn" title="清除"><i class="fa fa-times"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="layui-form-mid layui-word-aux">建议尺寸 200x60，支持 JPG/PNG/GIF/WebP</div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">Logo 显示</label>
                            <div class="layui-input-block">
                                <?php $logoType = $cfg['site_logo_type'] ?? 'text'; ?>
                                <input type="radio" name="site_logo_type" value="text" title="显示网站标题" <?php echo $logoType === 'text' ? 'checked' : ''; ?>>
                                <input type="radio" name="site_logo_type" value="image" title="显示 Logo 图片" <?php echo $logoType === 'image' ? 'checked' : ''; ?>>
                            </div>
                            <div class="layui-form-mid layui-word-aux">前台页头 Logo 位置显示网站标题文字还是 Logo 图片</div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">ICP 备案号</label>
                            <div class="layui-input-block">
                                <?php echo formInput('site_icp', $cfg['site_icp'] ?? '', '如：京ICP备XXXXXXXX号-1'); ?>
                            </div>
                        </div>
                    </div>

                    <!-- 站点行为块：首页入口 / 时区 / 统计 -->
                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-sliders"></i>站点行为</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">首页入口</label>
                            <div class="layui-input-block">
                                <select class="layui-input" name="homepage_mode">
                                    <?php
                                    $hmodes = [
                                        'mall'       => '商城首页',
                                        'goods_list' => '商品列表页',
                                        'blog'       => '博客首页',
                                    ];
                                    $currentHm = $cfg['homepage_mode'] ?? 'mall';
                                    foreach ($hmodes as $val => $label) {
                                        $sel = $currentHm === $val ? ' selected' : '';
                                        echo '<option value="' . $esc($val) . '"' . $sel . '>' . $esc($label) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">服务器时区</label>
                            <div class="layui-input-block">
                                <select class="layui-input" name="site_timezone">
                                    <?php
                                    $tzones = [
                                        'Asia/Shanghai'  => '中国标准时间 (UTC+8)',
                                        'Asia/Tokyo'      => '日本标准时间 (UTC+9)',
                                        'Asia/Seoul'      => '韩国标准时间 (UTC+9)',
                                        'America/New_York' => '美国东部时间 (UTC-5)',
                                        'America/Los_Angeles' => '美国太平洋时间 (UTC-8)',
                                        'Europe/London'   => '格林尼治时间 (UTC+0)',
                                        'Europe/Paris'    => '欧洲中部时间 (UTC+1)',
                                    ];
                                    $currentTz = $cfg['site_timezone'] ?? 'Asia/Shanghai';
                                    foreach ($tzones as $val => $label) {
                                        $sel = $currentTz === $val ? ' selected' : '';
                                        echo '<option value="' . $esc($val) . '"' . $sel . '>' . $esc($label) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">统计代码</label>
                            <div class="layui-input-block">
                                <?php echo formTextarea('site_statistical_code', $cfg['site_statistical_code'] ?? '', '粘贴第三方统计代码（如百度统计、Google Analytics 等）'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="admin-settings__actions-block">
                        <button type="submit" class="em-btn em-save-btn"><i class="fa fa-check"></i>保存设置</button>
                    </div>
                </form>
            </div> 
            <?php endif; ?> 

            <?php /* ==================== 安全设置 ==================== */ ?>
            <?php if ($currentTab === 'security'): ?>
            <div class="admin-settings__form-wrap" id="tab-security">
                <form class="layui-form admin-settings__form" id="form-security" autocomplete="off">
                    <input type="hidden" name="_tab" value="security">
                    <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">

                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-lock"></i>后台入口</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">安全入口</label>
                            <div class="layui-input-block">
                                <?php echo formInput('admin_entry_key', $cfg['admin_entry_key'] ?? '', '如：emshop（留空关闭）', 32); ?>
                            </div>
                            <div class="layui-form-mid layui-word-aux" style="line-height:1.8;">
                                后台面板管理入口，设置后只能通过指定安全入口登录后台面板，如：<code>/admin/sign.php?s=<b id="sec_preview">emshop</b></code><br>
                                <span style="color:#f59e0b;">⚠ 留空表示关闭此功能，任何人访问 /admin/sign.php 都能看到登录页。</span><br>
                                <span style="color:#9ca3af;">支持字母/数字/下划线/短横线，长度 1~32；请妥善保管并收藏带参数的 URL，忘记后需要通过 SSH 或数据库修改配置。</span>
                            </div>
                        </div>
                    </div>

                    <div class="admin-settings__actions-block">
                        <button type="submit" class="em-btn em-save-btn"><i class="fa fa-check"></i>保存设置</button>
                    </div>
                </form>
                <script>
                // 实时预览完整 URL 示例
                (function(){
                    var $ = layui.$;
                    function refresh(){
                        var v = ($('input[name=admin_entry_key]').val() || '').trim();
                        $('#sec_preview').text(v || 'emshop');
                    }
                    $(document).on('input.admSettings', 'input[name=admin_entry_key]', refresh);
                    refresh();
                })();
                </script>
            </div>
            <?php endif; ?>

            <?php /* ==================== SEO 设置 ==================== */ ?>
            <?php if ($currentTab === 'seo'): ?>
            <div class="admin-settings__form-wrap" id="tab-seo">
                <form class="layui-form admin-settings__form" id="form-seo" autocomplete="off">
                    <input type="hidden" name="_tab" value="seo">
                    <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">

                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-link"></i>链接格式</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">链接格式</label>
                            <div class="layui-input-block">
                                <?php $urlFormat = $cfg['url_format'] ?? 'default'; ?>
                                <input type="radio" name="url_format" value="default" title="默认格式" <?= $urlFormat === 'default' ? 'checked' : '' ?>>
                                <input type="radio" name="url_format" value="file"    title="文件格式" <?= $urlFormat === 'file' ? 'checked' : '' ?>>
                                <input type="radio" name="url_format" value="dir1"    title="目录格式①" <?= $urlFormat === 'dir1' ? 'checked' : '' ?>>
                                <input type="radio" name="url_format" value="dir2"    title="目录格式②" <?= $urlFormat === 'dir2' ? 'checked' : '' ?>>
                            </div>
                            <div class="layui-form-mid layui-word-aux" style="line-height:1.9;">
                                <strong>默认格式：</strong> <code>/?post=1</code> · <code>/?blog=1</code> · <code>/?c=goods_list</code><br>
                                <strong>文件格式：</strong> <code>/post-1.html</code> · <code>/blog-1.html</code> · <code>/post-list.html</code><br>
                                <strong>目录格式①：</strong> <code>/post/1</code> · <code>/blog/1</code> · <code>/post/list</code><br>
                                <strong>目录格式②：</strong> <code>/buy/1</code> · <code>/blog/1</code> · <code>/buy/list</code><br>
                                <span style="color:#f59e0b;">⚠ 文件格式 / 目录格式需要启用服务器 rewrite。详见 <code>install/rewrite/</code> 目录提供的 <code>.htaccess</code> 和 Nginx 片段。</span>
                            </div>
                        </div>
                    </div>

                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-search"></i>SEO 元信息</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">浏览器标题</label>
                            <div class="layui-input-block">
                                <?php echo formInput('seo_title', $cfg['seo_title'] ?? '', '如：EMSHOP 官方商城 · 精品虚拟商品自动发货'); ?>
                            </div>
                            <div class="layui-form-mid layui-word-aux">首页 &lt;title&gt; 显示内容；列表/详情页会附加当前页面名称</div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">站点关键词</label>
                            <div class="layui-input-block">
                                <?php echo formInput('seo_keywords', $cfg['seo_keywords'] ?? '', '用英文逗号分隔，如：卡密,自动发货,虚拟商品'); ?>
                            </div>
                            <div class="layui-form-mid layui-word-aux">写入所有页面的 &lt;meta name="keywords"&gt;</div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">站点描述</label>
                            <div class="layui-input-block">
                                <?php echo formTextarea('seo_description', $cfg['seo_description'] ?? '', '用于 <meta name="description">，建议 70-150 字'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="admin-settings__actions-block">
                        <button type="submit" class="em-btn em-save-btn"><i class="fa fa-check"></i>保存设置</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php /* ==================== 用户设置 ==================== */ ?>
            <?php if ($currentTab === 'user'): ?>
            <div class="admin-settings__form-wrap" id="tab-user">
                <form class="layui-form admin-settings__form" id="form-user" autocomplete="off">
                    <input type="hidden" name="_tab" value="user">
                    <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">

                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-user-plus"></i>登录与注册</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">开放登录</label>
                            <div class="layui-input-block">
                                <?php echo formSwitch('user_login', $cfg['user_login'] ?? '1'); ?>
                                <div class="layui-form-mid layui-word-aux">关闭后前台不显示登录入口，也无法登录</div>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">开放注册</label>
                            <div class="layui-input-block">
                                <?php echo formSwitch('user_register', $cfg['user_register'] ?? '1'); ?>
                                <div class="layui-form-mid layui-word-aux">关闭后仅管理员可创建账号</div>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">注册邮箱验证</label>
                            <div class="layui-input-block">
                                <?php echo formSwitch('user_verify_email', $cfg['user_verify_email'] ?? '0'); ?>
                                <div class="layui-form-mid layui-word-aux">开启后注册需验证邮箱才能登录</div>
                            </div>
                        </div>
                    </div>

                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-star-o"></i>经验与资源</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">经验名称</label>
                            <div class="layui-input-block">
                                <?php echo formInput('user_credit_name', $cfg['user_credit_name'] ?? '经验', ''); ?>
                                <div class="layui-form-mid layui-word-aux">前台展示用名称</div>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">注册初始经验</label>
                            <div class="layui-input-block">
                                <?php echo formInput('user_credit_initial', $cfg['user_credit_initial'] ?? '0', ''); ?>
                                <div class="layui-form-mid layui-word-aux">用户注册成功后赠送的整数经验值，0 表示不赠送</div>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">消费赠经验</label>
                            <div class="layui-input-block">
                                <?php echo formInput('user_exp_per_yuan', $cfg['user_exp_per_yuan'] ?? '0', ''); ?>
                                <div class="layui-form-mid layui-word-aux">订单完成时，每实付 1 元（整元）赠送的经验值，0 表示不赠送；与累计消费一并结算</div>
                            </div>
                        </div>
                    </div>

                    <div class="admin-settings__actions-block">
                        <button type="submit" class="em-btn em-save-btn"><i class="fa fa-check"></i>保存设置</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php /* ==================== 商城设置 ==================== */ ?>
            <?php if ($currentTab === 'shop'): ?>
            <div class="admin-settings__form-wrap" id="tab-shop">
                <form class="layui-form admin-settings__form" id="form-shop" autocomplete="off">
                    <input type="hidden" name="_tab" value="shop">
                    <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">

                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-credit-card"></i>余额支付</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">余额购买</label>
                            <div class="layui-input-block">
                                <?php echo formSwitch('shop_balance_enabled', $cfg['shop_balance_enabled'] ?? '1'); ?>
                            </div>
                            <div class="layui-form-mid layui-word-aux">启用后，用户可使用账户余额支付订单</div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">游客余额购买</label>
                            <div class="layui-input-block">
                                <?php echo formSwitch('shop_guest_balance_enabled', $cfg['shop_guest_balance_enabled'] ?? '1'); ?>
                            </div>
                            <div class="layui-form-mid layui-word-aux">启用后，未登录用户也可看到余额支付选项</div>
                        </div>
                    </div>

                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-money"></i>充值额度</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">单次最低充值</label>
                            <div class="layui-input-block">
                                <?php echo formMoney('shop_min_recharge', $cfg['shop_min_recharge'] ?? '1000000', ''); ?>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">单次最高充值</label>
                            <div class="layui-input-block">
                                <?php echo formMoney('shop_max_recharge', $cfg['shop_max_recharge'] ?? '1000000000000', ''); ?>
                            </div>
                        </div>
                    </div>

                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-bank"></i>提现额度</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">最低提现额</label>
                            <div class="layui-input-block">
                                <?php echo formMoney('shop_withdraw_min', $cfg['shop_withdraw_min'] ?? '10000000', ''); ?>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">最高提现额</label>
                            <div class="layui-input-block">
                                <?php echo formMoney('shop_withdraw_max', $cfg['shop_withdraw_max'] ?? '5000000000', ''); ?>
                            </div>
                        </div>
                    </div>

                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-shopping-cart"></i>订单与优惠</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">订单超时时间</label>
                            <div class="layui-input-block">
                                <?php echo formInputWithSuffix('shop_order_expire_minutes', $cfg['shop_order_expire_minutes'] ?? '30', '分钟', '30', 'number'); ?>
                                <div class="layui-form-mid layui-word-aux">订单创建后未支付自动关闭的时间</div>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">启用优惠券</label>
                            <div class="layui-input-block">
                                <?php echo formSwitch('shop_enable_coupon', $cfg['shop_enable_coupon'] ?? '1'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-cubes"></i>库存与销量</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">显示库存</label>
                            <div class="layui-input-block">
                                <?php echo formSwitch('shop_display_stock', $cfg['shop_display_stock'] ?? '1'); ?>
                            </div>
                            <div class="layui-form-mid layui-word-aux">关闭后，前台将隐藏商品库存相关展示</div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">显示销量</label>
                            <div class="layui-input-block">
                                <?php echo formSwitch('shop_display_sales', $cfg['shop_display_sales'] ?? '1'); ?>
                            </div>
                            <div class="layui-form-mid layui-word-aux">关闭后，前台将隐藏商品销量相关展示</div>
                        </div>
                    </div>

                    <!-- 店铺公告（TinyMCE 富文本，会显示在前台） -->
                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-bullhorn"></i>店铺公告</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">公告内容</label>
                            <div class="layui-input-block">
                                <textarea name="shop_announcement" id="shopAnnouncementTextarea" class="layui-textarea"><?= htmlspecialchars((string) ($cfg['shop_announcement'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                <div class="layui-form-mid layui-word-aux">富文本 HTML，会按下方"显示位置"在前台展示</div>
                            </div>
                        </div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">显示位置</label>
                            <div class="layui-input-block">
                                <?php
                                    // 首次未配置时默认勾选"商城首页" —— 让用户写完公告就能直接看到效果
                                    $posRaw = $cfg['shop_announcement_positions'] ?? null;
                                    if ($posRaw === null) {
                                        $posList = ['home'];
                                    } else {
                                        $posList = array_filter(array_map('trim', explode(',', (string) $posRaw)));
                                    }
                                ?>
                                <div class="em-checkbox-group">
                                    <!-- lay-ignore：禁止 layui form.render('checkbox') 把原生 input 包装成 .layui-form-checkbox，
                                         否则 layui 会给伪 checkbox 加 margin-top:10px 造成胶囊跟 label 错位 -->
                                    <label class="em-checkbox">
                                        <input type="checkbox" name="shop_announcement_positions[]" value="home" lay-ignore <?= in_array('home', $posList, true) ? 'checked' : '' ?>>
                                        <span><i class="fa fa-home"></i> 商城首页</span>
                                    </label>
                                    <label class="em-checkbox">
                                        <input type="checkbox" name="shop_announcement_positions[]" value="goods_list" lay-ignore <?= in_array('goods_list', $posList, true) ? 'checked' : '' ?>>
                                        <span><i class="fa fa-th"></i> 商品列表页</span>
                                    </label>
                                </div>
                                <div class="layui-form-mid layui-word-aux">不勾选则不在前台展示公告</div>
                            </div>
                        </div>
                    </div>

                    <div class="admin-settings__actions-block">
                        <button type="submit" class="em-btn em-save-btn"><i class="fa fa-check"></i>保存设置</button>
                    </div>
                </form>
            </div> 
 
            <!-- 店铺公告 TinyMCE 初始化 -->
            <script>
            (function () {
                if (typeof window.emTinymceInit !== 'function') {
                    return;
                }
                window.emTinymceInit({
                    context: 'announce_image',
                    editors: [
                        {
                            selector: '#shopAnnouncementTextarea',
                            height: 300,
                            placeholder: '在这里输入店铺公告，支持图文混排…'
                        }
                    ]
                }).catch(function () {});
            })();
            </script>
            <?php endif; ?>

            <?php /* ==================== 查单模式（独立选项卡：游客查单配置） ==================== */ ?>
            <?php if ($currentTab === 'guest_find'): ?>
            <?php
            // 开关显示值兜底：两者都未启用时，订单密码显示为开启（保持与 GuestFindModel::isPasswordEnabled 一致的语义）
            $cfgContactOn = ($cfg['guest_find_contact_enabled'] ?? '0') === '1';
            $cfgPasswordOn = ($cfg['guest_find_password_enabled'] ?? '0') === '1';
            if (!$cfgContactOn && !$cfgPasswordOn) {
                $cfgPasswordOn = true;
            }
            $contactSwitchValue = $cfgContactOn ? '1' : '0';
            $passwordSwitchValue = $cfgPasswordOn ? '1' : '0';
            ?>
            <div class="admin-settings__form-wrap" id="tab-guest_find">
                <form class="layui-form admin-settings__form" id="form-guest_find" autocomplete="off">
                    <input type="hidden" name="_tab" value="guest_find">
                    <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">

                    <!-- 联系方式查单：蓝色强调 -->
                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-phone"></i>联系方式查单</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">启用开关</label>
                            <div class="layui-input-block">
                                <?php echo formSwitch('guest_find_contact_enabled', $contactSwitchValue); ?>
                            </div>
                            <div class="layui-form-mid layui-word-aux">启用后，用户可通过联系方式（任意/手机/邮箱/QQ）查询订单</div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">联系方式类型</label>
                            <div class="layui-input-block">
                                <?php echo formRadio('guest_find_contact_type', GuestFindModel::getContactTypeOptions(), $cfg['guest_find_contact_type'] ?? 'any'); ?>
                            </div>
                            <div class="layui-form-mid layui-word-aux">"任意"表示不限制类型；选定具体类型后将做格式校验</div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">下单页占位提示</label>
                            <div class="layui-input-block">
                                <?php echo formInput('guest_find_contact_checkout_placeholder', $cfg['guest_find_contact_checkout_placeholder'] ?? '请输入您的联系方式', ''); ?>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">查单页占位提示</label>
                            <div class="layui-input-block">
                                <?php echo formInput('guest_find_contact_lookup_placeholder', $cfg['guest_find_contact_lookup_placeholder'] ?? '请输入您的联系方式', ''); ?>
                            </div>
                        </div>
                    </div>

                    <!-- 订单密码查单：绿色强调 -->
                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-key"></i>订单密码查单</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">启用开关</label>
                            <div class="layui-input-block">
                                <?php echo formSwitch('guest_find_password_enabled', $passwordSwitchValue); ?>
                            </div>
                            <div class="layui-form-mid layui-word-aux">启用后，用户可通过订单密码查询订单</div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">下单页占位提示</label>
                            <div class="layui-input-block">
                                <?php echo formInput('guest_find_password_checkout_placeholder', $cfg['guest_find_password_checkout_placeholder'] ?? '请设置您的订单密码', ''); ?>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">查单页占位提示</label>
                            <div class="layui-input-block">
                                <?php echo formInput('guest_find_password_lookup_placeholder', $cfg['guest_find_password_lookup_placeholder'] ?? '请设置您的订单密码', ''); ?>
                            </div>
                        </div>
                    </div>

                    <div class="admin-settings__actions-block">
                        <button type="submit" class="em-btn em-save-btn"><i class="fa fa-check"></i>保存设置</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php /* ==================== 推广返佣 ==================== */ ?>
            <?php if ($currentTab === 'rebate'): ?>
            <div class="admin-settings__form-wrap" id="tab-rebate">
                <form class="layui-form admin-settings__form" id="form-rebate" autocomplete="off">
                    <input type="hidden" name="_tab" value="rebate">
                    <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">

                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-power-off"></i>总开关</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">总开关</label>
                            <div class="layui-input-block">
                                <?php echo formSwitch('shop_enable_rebate', $cfg['shop_enable_rebate'] ?? '0'); ?>
                            </div>
                            <div class="layui-form-mid layui-word-aux">关闭时不记录归因、不生成佣金。开启后才对新订单生效</div>
                        </div>
                    </div>

                    <?php
                    // 佣金比例以「百分比」展示/录入，底层仍按万分位存储（5 → 500）。
                    // 把整型万分位格式化成干净的百分数字符串：500 → "5"、550 → "5.5"、0 → "0"。
                    $rateToPct = static function ($v): string {
                        $s = number_format(((int) $v) / 100, 2, '.', '');
                        return rtrim(rtrim($s, '0'), '.') ?: '0';
                    };
                    ?>
                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-percent"></i>佣金比例</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">一级佣金比例</label>
                            <div class="layui-input-block">
                                <div class="layui-input-wrap">
                                    <?php echo formInput('rebate_level1_rate', $rateToPct($cfg['rebate_level1_rate'] ?? 0), ''); ?>
                                    <div class="layui-input-suffix">%</div>
                                </div>
                            </div>
                            <div class="layui-form-mid layui-word-aux">填 5 表示 5%，支持两位小数（5.25 → 5.25%）；仅在商品/分类未单独设置时生效</div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">二级佣金比例</label>
                            <div class="layui-input-block">
                                <div class="layui-input-wrap">
                                    <?php echo formInput('rebate_level2_rate', $rateToPct($cfg['rebate_level2_rate'] ?? 0), ''); ?>
                                    <div class="layui-input-suffix">%</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-calculator"></i>计算与冻结</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">计算方式</label>
                            <div class="layui-input-block">
                                <?php $calcMode = $cfg['rebate_calculate_mode'] ?? 'amount'; ?>
                                <input type="radio" name="rebate_calculate_mode" value="amount"
                                       title="按订单金额" <?= $calcMode === 'amount' ? 'checked' : '' ?>>
                                <input type="radio" name="rebate_calculate_mode" value="profit"
                                       title="按订单利润" <?= $calcMode === 'profit' ? 'checked' : '' ?>>
                            </div>
                            <div class="layui-form-mid layui-word-aux">
                                <strong>按订单金额</strong>：用户实付金额 × 佣金比例<br>
                                <strong>按订单利润</strong>：（实付金额 − 成本价合计） × 佣金比例；成本为 0 的商品不计佣
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">冷却天数</label>
                            <div class="layui-input-block">
                                <div class="layui-input-wrap">
                                    <?php echo formInput('rebate_freeze_days', $cfg['rebate_freeze_days'] ?? '7', ''); ?>
                                    <div class="layui-input-suffix">天</div>
                                </div>
                            </div>
                            <div class="layui-form-mid layui-word-aux">订单完成后佣金先冻结 N 天，之后才能提现；退款倒扣不受冷却期约束</div>
                        </div>
                    </div>

                    <div class="admin-settings__actions-block">
                        <button type="submit" class="em-btn em-save-btn"><i class="fa fa-check"></i>保存设置</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php /* ==================== 博客设置 ==================== */ ?>
            <?php if ($currentTab === 'blog'): ?>
            <div class="admin-settings__form-wrap" id="tab-blog">
                <form class="layui-form admin-settings__form" id="form-blog" autocomplete="off">
                    <input type="hidden" name="_tab" value="blog">
                    <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">

                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-list"></i>内容显示</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">每页文章数</label>
                            <div class="layui-input-block">
                                <?php echo formInput('blog_article_per_page', $cfg['blog_article_per_page'] ?? '10', ''); ?>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">评论需审核</label>
                            <div class="layui-input-block">
                                <?php echo formSwitch('blog_comment_need_verify', $cfg['blog_comment_need_verify'] ?? '1'); ?>
                                <div class="layui-form-mid layui-word-aux">开启后新评论需管理员审核后显示</div>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">显示作者</label>
                            <div class="layui-input-block">
                                <?php echo formSwitch('blog_show_author', $cfg['blog_show_author'] ?? '1'); ?>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">显示阅读量</label>
                            <div class="layui-input-block">
                                <?php echo formSwitch('blog_show_views', $cfg['blog_show_views'] ?? '1'); ?>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">启用 RSS</label>
                            <div class="layui-input-block">
                                <?php echo formSwitch('blog_rss_enabled', $cfg['blog_rss_enabled'] ?? '0'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-search"></i>SEO 元信息</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">SEO 标题</label>
                            <div class="layui-input-block">
                                <?php echo formInput('blog_seo_title', $cfg['blog_seo_title'] ?? '', '博客首页 SEO 标题'); ?>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">SEO 关键字</label>
                            <div class="layui-input-block">
                                <?php echo formInput('blog_seo_keywords', $cfg['blog_seo_keywords'] ?? '', '关键字1, 关键字2'); ?>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">SEO 描述</label>
                            <div class="layui-input-block">
                                <?php echo formTextarea('blog_seo_description', $cfg['blog_seo_description'] ?? '', '博客描述'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="admin-settings__actions-block">
                        <button type="submit" class="em-btn em-save-btn"><i class="fa fa-check"></i>保存设置</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php /* ==================== 邮箱配置 ==================== */ ?>
            <?php if ($currentTab === 'mail'): ?>
            <div class="admin-settings__form-wrap" id="tab-mail">
                <form class="layui-form admin-settings__form" id="form-mail" autocomplete="off">
                    <input type="hidden" name="_tab" value="mail">
                    <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">

                    <!-- SMTP 配置块 -->
                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-envelope"></i>SMTP 配置</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">发送人邮箱</label>
                            <div class="layui-input-block">
                                <?php echo formInput('mail_from_address', $cfg['mail_from_address'] ?? '', ''); ?>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">SMTP 服务器</label>
                            <div class="layui-input-block">
                                <?php echo formInput('mail_host', $cfg['mail_host'] ?? '', ''); ?>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">SMTP 密码</label>
                            <div class="layui-input-block">
                                <input type="text" class="layui-input" name="mail_password" value="<?php echo $esc($cfg['mail_password'] ?? ''); ?>" autocomplete="new-password">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">端口</label>
                            <div class="layui-input-block">
                                <?php echo formInput('mail_port', $cfg['mail_port'] ?? '465', '465'); ?>
                                <div class="layui-form-mid layui-word-aux" style="padding:6px 0 0;">
                                    465：SSL 协议（如 QQ 邮箱、网易邮箱等）；587：STARTTLS 协议（如 Outlook 邮箱）
                                </div>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">发送人名称（选填，建议填写站点名称）</label>
                            <div class="layui-input-block">
                                <?php echo formInput('mail_from_name', $cfg['mail_from_name'] ?? '', ''); ?>
                            </div>
                        </div>
                    </div>

                    <!-- 示例说明块 -->
                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-info-circle"></i>以 QQ 邮箱配置为例</div>

                        <div class="admin-mail-help">
                            <div>
                                <b>发送人邮箱</b>：你的 QQ 邮箱<br>
                                <b>SMTP 密码</b>：见 QQ 邮箱顶部「设置 → 账户 → 开启 IMAP/SMTP 服务 → 生成授权码」，授权码即为 SMTP 密码（不是 QQ 登录密码）<br>
                                <b>发送人名称</b>：你的姓名或站点名称<br>
                                <b>SMTP 服务器</b>：smtp.qq.com<br>
                                <b>端口</b>：465
                            </div>
                        </div>
                    </div>

                    <!-- 操作栏：保存 / 测试 / 重置 -->
                    <div class="admin-settings__actions-block">
                        <button type="submit" class="em-btn em-save-btn"><i class="fa fa-check"></i>保存设置</button>
                        <button type="button" class="em-btn em-green-btn" id="mailSendTestBtn"><i class="fa fa-paper-plane"></i>发送测试</button>
                        <button type="button" class="em-btn em-reset-btn" id="mailResetBtn"><i class="fa fa-undo"></i>重置</button>
                    </div>
                </form>
            </div>

            <script>
            // 直接加载时 layui.js 在本文件更下方，top-level 引用 layui 会报 ReferenceError；
            // 挂到 $(ready) 里等所有 <script> 解析完再跑（PJAX 路径下也安全）
            $(function () {
    // PJAX 防重复绑定：清掉本页历史 .admSettings handler，避免事件成倍触发
    $(document).off('.admSettings');
    $(window).off('.admSettings');

            if (typeof layui === 'undefined') return;
            layui.use(['layer', 'form'], function () {
                var $ = layui.$, layer = layui.layer, form = layui.form;

                // 重置：把表单恢复到服务器当前保存的值（直接 reload 当前页更稳；避免自己一项项清空）
                $('#mailResetBtn').on('click', function () {
                    layer.confirm('确定重置为已保存的配置吗？当前未保存的修改会丢失。', function (idx) {
                        layer.close(idx);
                        // 用 pjax 重新加载当前 tab，等价于丢弃 form 里所有未提交改动
                        if ($.pjax) $.pjax({ url: '/admin/settings.php?action=mail', container: '#adminContent' });
                        else location.href = '/admin/settings.php?action=mail';
                    });
                });

                // 发送测试：把当前表单的 SMTP 字段作为 URL query 带给 iframe 弹窗，
                // 弹窗不会从 DB 读，而是用这里传过去的"当前填写"值测发（方便未保存先验证）
                $('#mailSendTestBtn').on('click', function () {
                    var payload = {
                        from_email: $('#form-mail [name="mail_from_address"]').val() || '',
                        from_name : $('#form-mail [name="mail_from_name"]').val()    || '',
                        host      : $('#form-mail [name="mail_host"]').val()         || '',
                        password  : $('#form-mail [name="mail_password"]').val()     || '',
                        port      : $('#form-mail [name="mail_port"]').val()         || '',
                    };
                    var qs = $.param(payload);
                    layer.open({
                        type: 2,
                        title: '发送测试邮件',
                        skin: 'admin-modal',
                        shadeClose: false,
                        area: [window.innerWidth >= 640 ? '500px' : '94%', '320px'],
                        content: '/admin/settings.php?_popup=smtp_test&' + qs
                    });
                });
            });
            });
            </script>

            <?php endif; ?>

            <?php /* ==================== 商户（分站）配置 ==================== */ ?>
            <?php if ($currentTab === 'substation'): ?>
            <div class="admin-settings__form-wrap" id="tab-substation">
                <form class="layui-form admin-settings__form" id="form-substation" autocomplete="off">
                    <input type="hidden" name="_tab" value="substation">
                    <input type="hidden" name="csrf_token" value="<?php echo $esc($csrfToken); ?>">

                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-power-off"></i>全站开关</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">启用商户</label>
                            <div class="layui-input-block">
                                <?php echo formSwitch('substation_enabled', $cfg['substation_enabled'] ?? '0'); ?>
                            </div>
                            <div class="layui-form-mid layui-word-aux">关闭后所有商户前台入口失效，已有店铺不可访问</div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">允许自助开通</label>
                            <div class="layui-input-block">
                                <?php echo formSwitch('merchant_enable_self_open', $cfg['merchant_enable_self_open'] ?? '0'); ?>
                            </div>
                            <div class="layui-form-mid layui-word-aux">关闭后用户只能通过管理员手动开通；开通价由商户等级决定</div>
                        </div>
                    </div>

                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-sitemap"></i>识别 / 访问</div>

                        <div class="layui-form-item">
                            <label class="layui-form-label">主站根域名</label>
                            <div class="layui-input-block">
                                <?php echo formInput('main_domain', $cfg['main_domain'] ?? '', '如 example.com（无 http:// 前缀）'); ?>
                            </div>
                            <div class="layui-form-mid layui-word-aux">启用二级域名访问时必填，用于从 Host 头解析商户 subdomain</div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">默认模板</label>
                            <div class="layui-input-block">
                                <select class="layui-input" name="merchant_default_theme">
                                    <?php
                                    $currentTheme = $cfg['merchant_default_theme'] ?? ($cfg['substation_default_theme'] ?? 'default');
                                    foreach ($availableThemes as $val => $label) {
                                        $sel = $currentTheme === $val ? ' selected' : '';
                                        echo '<option value="' . $esc($val) . '"' . $sel . '>' . $esc($label) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="layui-form-mid layui-word-aux">新开通商户的初始模板（v1 所有商户共用主站模板，此项尚未生效）</div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">自定义域名引导</label>
                            <div class="layui-input-block">
                                <textarea class="layui-textarea" name="merchant_custom_domain_tip"
                                          placeholder="展示给商户的域名解析说明（CNAME 配置步骤等）" rows="3"><?php echo $esc($cfg['merchant_custom_domain_tip'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="admin-settings__block">
                        <div class="admin-settings__block-title"><i class="fa fa-info-circle"></i>迁移提示</div>

                        <div class="layui-form-item">
                            <div class="layui-input-block" style="color:#6b7280;font-size:13px;line-height:1.7;padding:4px 0 10px;">
                                开通费用、允许功能（自定义域名 / 模板 / 独立收款 等）、手续费率 等规则均已迁移到
                                <a href="/admin/merchant_level.php" data-pjax="#adminContent" style="color:#1890ff;">商户等级</a> 中按等级独立配置。
                            </div>
                        </div>
                    </div>

                    <div class="admin-settings__actions-block">
                        <button type="submit" class="em-btn em-save-btn"><i class="fa fa-check"></i>保存设置</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
    // —— 通用提示（layui 可用时用 layer.msg；否则 fallback 到 alert，避免报错）
    function settingsMsg(text) {
        if (typeof layer !== 'undefined' && typeof layer.msg === 'function') {
            layer.msg(text);
        } else if (typeof layui !== 'undefined' && layui.layer && typeof layui.layer.msg === 'function') {
            layui.layer.msg(text);
        } else {
            alert(text);
        }
    }

    // 横向 tab：首屏把当前激活项滚进可视区
    (function () {
        var tabs = document.getElementById('settingsTabs');
        if (!tabs) return;
        var active = tabs.querySelector('.em-tabs__item.is-active');
        if (active && active.scrollIntoView) {
            active.scrollIntoView({ inline: 'center', block: 'nearest' });
        }
    })();

<?php if ($needSiteUrlTip): ?>
    settingsMsg('请先配置站点地址（site_url）');
    (function () {
        var input = document.getElementById('siteUrlInput');
        if (!input) return;
        var item = document.getElementById('siteUrlFormItem');
        if (item && typeof item.scrollIntoView === 'function') {
            item.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        setTimeout(function () { input.focus(); }, 150);
    })();
<?php endif; ?>

    // —— 提交处理器先于 layui 初始化注册，避免 layui 未就绪时表单按默认方式 GET 提交
    // PJAX 反复导航到本页时 .off + .on 保证只绑定一次
    $(document).off('submit.settingsSubmit').on('submit.settingsSubmit', '.admin-settings__form-wrap form', function (e) {
        e.preventDefault();
        var $form = $(this);
        if ($form.data('saving')) return false;
        $form.data('saving', true);

        var $btn = $form.find('button[type="submit"]');
        // em-save-btn 按钮走图标切换模式（文字不变），其它按钮保留原有"改文字"模式
        var isEmSaveBtn = $btn.hasClass('em-save-btn');
        if (isEmSaveBtn) {
            $btn.prop('disabled', true).addClass('is-loading')
                .find('i').attr('class', 'fa fa-refresh admin-spin');
        } else {
            $btn.prop('disabled', true).text('保存中...');
        }

        window.emTinymceSave();

        $.ajax({
            url: '/admin/settings.php',
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.code === 200) {
                    settingsMsg('保存成功');
                    if (res.data && res.data.csrf_token) {
                        $form.find('input[name="csrf_token"]').val(res.data.csrf_token);
                    }
                } else {
                    settingsMsg(res.msg || '保存失败');
                }
            },
            error: function () {
                settingsMsg('网络错误，请重试');
            },
            complete: function () {
                if (isEmSaveBtn) {
                    $btn.prop('disabled', false).removeClass('is-loading')
                        .find('i').attr('class', 'fa fa-check');
                } else {
                    $btn.prop('disabled', false).text('保存设置');
                }
                $form.data('saving', false);
            }
        });
        return false;
    });

    // —— layui / viewer 依赖就绪后再做 form 渲染、裁剪、上传、预览等逻辑
    // 本文件 include 在 #adminContent 中段，layui.js / viewer.min.js 都在 body 末尾同步加载；
    // 用 $(ready) 等 DOMContentLoaded 保证两者都已定义（否则 typeof layui === 'undefined' 会整块跳过，
    // 初次直刷链接时 Logo 的 click/upload/pick/clear 全不绑定）
    $(function () {
    if (typeof layui === 'undefined') return;
    layui.use(['layer', 'form', 'upload'], function () {
        var layer = layui.layer;
        var form = layui.form;
        var upload = layui.upload;

        form.render('select');
        form.render('checkbox');
        form.render('switch');
        form.render('radio');

        // ============================================================
        // 常量
        // ============================================================
        var CROP_AREA_CROP = [window.innerWidth >= 800 ? '500px' : '95%', window.innerHeight >= 700 ? '580px' : '80%'];
        var CROP_AREA_PICK = [window.innerWidth >= 800 ? '700px' : '95%', window.innerHeight >= 500 ? '500px' : '80%'];

        // ============================================================
        // Logo 图片上传
        // ============================================================
        var cropperInstance = null;
        var logoField = {
            previewId: 'logoPreview',
            urlId: 'logoUrl',
            clearBtnId: 'logoClearBtn',
            pickBtnId: 'logoPickBtn',
            uploadBtnId: 'logoUploadBtn',
            fieldId: 'logoField',
            aspectRatio: 200 / 60,
            cropWidth: 200,
            cropHeight: 60,
            context: 'site_logo'
        };
        var faviconField = {
            previewId: 'faviconPreview',
            urlId: 'faviconUrl',
            clearBtnId: 'faviconClearBtn',
            pickBtnId: 'faviconPickBtn',
            uploadBtnId: 'faviconUploadBtn',
            fieldId: 'faviconField',
            context: 'site_favicon'
        };

        function updateFieldPreview(cfg, url) {
            var $preview = $('#' + cfg.previewId);
            var $url = $('#' + cfg.urlId);
            var $field = $('#' + cfg.fieldId);
            var placeholder = $field.data('placeholder') || '';
            if (url) {
                $preview.attr('src', url);
                $preview.attr('layer-src', url);
                $url.val(url);
            } else {
                $preview.attr('src', placeholder);
                $preview.attr('layer-src', placeholder);
                $url.val('');
            }
        }

        function openCropperForField(cfg, imgSrc, isFile) {
            var $cropperWrap = $('<div id="cropperWrap" class="cropper-wrap"></div>');
            $cropperWrap.html('<div class="cropper-container">'
                + '<img id="cropperImg">'
                + '</div>'
                + '<div class="cropper-tip">'
                + '<p>拖动裁剪框调整图片范围，确认后点击"保存"</p>'
                + '</div>');
            $('body').append($cropperWrap);

            var $cropperImg = $('#cropperImg');
            $cropperImg.attr('src', imgSrc);

            layer.open({
                type: 1,
                title: '裁剪网站 Logo',
                skin: 'admin-modal',
                maxmin: true,
                area: CROP_AREA_CROP,
                shadeClose: false,
                content: $cropperWrap,
                btn: ['保存', '使用原图', '取消'],
                success: function () {
                    var img = $cropperImg[0];
                    cropperInstance = new Cropper(img, {
                        aspectRatio: cfg.aspectRatio,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 0.8,
                        guides: true,
                        center: true,
                        highlight: false,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                        toggleDragModeOnDblclick: false
                    });
                },
                yes: function (index) {
                    var canvas = cropperInstance.getCroppedCanvas({
                        width: cfg.cropWidth,
                        height: cfg.cropHeight,
                        imageSmoothingEnabled: true,
                        imageSmoothingQuality: 'high'
                    });
                    canvas.toBlob(function (blob) {
                        var formData = new FormData();
                        formData.append('file', blob, 'logo.jpg');
                        formData.append('csrf_token', $('input[name="csrf_token"]').val());
                        formData.append('context', cfg.context);

                        $.ajax({
                            url: '/admin/upload.php',
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            dataType: 'json',
                            success: function (res) {
                                if (res.code === 0 || res.code === 200) {
                                    updateFieldPreview(cfg, res.data.url);
                                    $('input[name="csrf_token"]').val(res.data.csrf_token);
                                    layer.msg(res.msg || '上传成功');
                                } else {
                                    layer.msg(res.msg || '上传失败');
                                }
                            },
                            error: function () {
                                layer.msg('上传失败，请重试');
                            }
                        });
                        layer.close(index);
                    }, 'image/jpeg', 0.9);
                },
                btn2: function (index) {
                    // 上传原图（不裁剪）的公共逻辑
                    function uploadOrigBlob(blob) {
                        var formData = new FormData();
                        formData.append('file', blob, 'logo_orig.jpg');
                        formData.append('csrf_token', $('input[name="csrf_token"]').val());
                        formData.append('context', cfg.context);
                        $.ajax({
                            url: '/admin/upload.php',
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            dataType: 'json',
                            success: function (res) {
                                if (res.code === 0 || res.code === 200) {
                                    updateFieldPreview(cfg, res.data.url);
                                    $('input[name="csrf_token"]').val(res.data.csrf_token);
                                    layer.msg('上传成功');
                                } else {
                                    layer.msg(res.msg || '上传失败');
                                }
                            },
                            error: function () {
                                layer.msg('上传失败，请重试');
                            }
                        });
                        layer.close(index);
                    }

                    if (isFile) {
                        // 本地文件上传：将 data URL 转为 Blob
                        var parts = imgSrc.split(',');
                        var mime = parts[0].match(/:(.*?);/)[1];
                        var raw = atob(parts[1]);
                        var arr = new Uint8Array(raw.length);
                        for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
                        uploadOrigBlob(new Blob([arr], {type: mime}));
                    } else {
                        // URL 图片：通过 XHR 获取 Blob
                        var xhr = new XMLHttpRequest();
                        xhr.open('GET', imgSrc, true);
                        xhr.responseType = 'blob';
                        xhr.onload = function () {
                            if (xhr.status === 200) {
                                uploadOrigBlob(xhr.response);
                            }
                        };
                        xhr.send();
                    }
                    return false;
                },
                end: function () {
                    if (cropperInstance) {
                        cropperInstance.destroy();
                        cropperInstance = null;
                    }
                    $cropperWrap.remove();
                }
            });
        }

        $('#' + logoField.urlId).on('input', function () {
            var url = $(this).val();
            var $preview = $('#' + logoField.previewId);
            var placeholder = $(this).closest('.admin-profile__avatar-field').data('placeholder') || '';
            var finalUrl = url || placeholder;
            $preview.attr('src', finalUrl);
            $preview.attr('layer-src', finalUrl);
        });

        // 点击 Logo 预览图 → viewer.js 放大（和用户列表/商品列表图片预览同款）
        $('#' + logoField.previewId).on('click', function (e) {
            e.stopPropagation();
            var src = $(this).attr('layer-src') || $(this).attr('src');
            if (!src) return;
            var $container = $('<div style="display:none;"><img src="' + src + '" alt="网站 Logo"></div>');
            $('body').append($container);
            var viewer = new Viewer($container[0], {
                navbar: false,
                title: false,
                toolbar: true,
                hidden: function () {
                    viewer.destroy();
                    $container.remove();
                }
            });
            viewer.show();
        });

        $('#' + logoField.clearBtnId).on('click', function () {
            updateFieldPreview(logoField, '');
        });

        $('#' + logoField.pickBtnId).on('click', function () {
            var csrfToken = $('input[name="csrf_token"]').val();
            layer.open({
                type: 2,
                title: '选择图片',
                skin: 'admin-modal',
                maxmin: true,
                area: CROP_AREA_PICK,
                shadeClose: false,
                content: '/admin/media.php?_csrf=' + encodeURIComponent(csrfToken),
                btn: ['确定', '取消'],
                yes: function (index, layero) {
                    var win = layero.find('iframe')[0].contentWindow;
                    var url = win.selectMedia();
                    if (!url) {
                        layer.msg('请先选择一张图片');
                        return;
                    }
                    layer.close(index);
                    openCropperForField(logoField, url, false);
                }
            });
        });

        var $fileInput = $('<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">');
        $('body').append($fileInput);

        $fileInput.on('change', function () {
            var file = this.files[0];
            if (file) {
                if (!file.type.match(/image\/(jpeg|png|gif|webp)/i)) {
                    layer.msg('仅支持 JPG、PNG、GIF、WebP 格式');
                    $(this).val('');
                    return;
                }
                if (file.size > 10 * 1024 * 1024) {
                    layer.msg('图片大小不能超过 10MB');
                    $(this).val('');
                    return;
                }
                var reader = new FileReader();
                reader.onload = function (e) {
                    openCropperForField(logoField, e.target.result, true);
                };
                reader.readAsDataURL(file);
                $(this).val('');
            }
        });

        $('#' + logoField.uploadBtnId).on('click', function () {
            $fileInput.trigger('click');
        });

        // ============================================================
        // 网站 ICO（直传 + 媒体库，不走裁剪）
        // ============================================================
        $('#' + faviconField.urlId).on('input', function () {
            var url = $(this).val();
            var $preview = $('#' + faviconField.previewId);
            var placeholder = $(this).closest('.admin-profile__avatar-field').data('placeholder') || '/favicon.ico';
            var finalUrl = url || placeholder;
            $preview.attr('src', finalUrl);
            $preview.attr('layer-src', finalUrl);
        });
        $('#' + faviconField.previewId).on('click', function (e) {
            e.stopPropagation();
            var src = $(this).attr('layer-src') || $(this).attr('src');
            if (!src) return;
            var $container = $('<div style="display:none;"><img src="' + src + '" alt="网站 ICO"></div>');
            $('body').append($container);
            var viewer = new Viewer($container[0], {
                navbar: false,
                title: false,
                toolbar: true,
                hidden: function () {
                    viewer.destroy();
                    $container.remove();
                }
            });
            viewer.show();
        });
        $('#' + faviconField.clearBtnId).on('click', function () {
            updateFieldPreview(faviconField, '');
        });
        $('#' + faviconField.pickBtnId).on('click', function () {
            var csrfToken = $('input[name="csrf_token"]').val();
            layer.open({
                type: 2,
                title: '选择图片',
                skin: 'admin-modal',
                maxmin: true,
                area: CROP_AREA_PICK,
                shadeClose: false,
                content: '/admin/media.php?_csrf=' + encodeURIComponent(csrfToken),
                btn: ['确定', '取消'],
                yes: function (index, layero) {
                    var win = layero.find('iframe')[0].contentWindow;
                    var url = win.selectMedia();
                    if (!url) {
                        layer.msg('请先选择一张图片');
                        return;
                    }
                    layer.close(index);
                    updateFieldPreview(faviconField, url);
                }
            });
        });
        var $faviconFileInput = $('<input type="file" accept="image/jpeg,image/png,image/gif,image/webp,.ico,image/x-icon" style="display:none;">');
        $('body').append($faviconFileInput);
        $faviconFileInput.on('change', function () {
            var file = this.files[0];
            if (!file) return;
            var okExt = /\.(ico|jpe?g|png|gif|webp)$/i.test(file.name);
            var okMime = /image\/(jpeg|png|gif|webp|x-icon)/i.test(file.type || '');
            if (!okExt && !okMime) {
                layer.msg('仅支持 ICO、JPG、PNG、GIF、WebP 格式');
                $(this).val('');
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                layer.msg('文件大小不能超过 2MB');
                $(this).val('');
                return;
            }
            var fd = new FormData();
            fd.append('file', file);
            fd.append('csrf_token', $('input[name="csrf_token"]').val());
            fd.append('context', 'site_favicon');
            $.ajax({
                url: '/admin/upload.php',
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (res) {
                    if (res.code === 0 || res.code === 200) {
                        updateFieldPreview(faviconField, res.data.url);
                        $('input[name="csrf_token"]').val(res.data.csrf_token);
                        layer.msg('上传成功');
                    } else {
                        layer.msg(res.msg || '上传失败');
                    }
                },
                error: function () {
                    layer.msg('上传失败，请重试');
                }
            });
            $(this).val('');
        });
        $('#' + faviconField.uploadBtnId).on('click', function () {
            $faviconFileInput.trigger('click');
        });
    });
    }); // end $(ready) + layui.use
</script>
