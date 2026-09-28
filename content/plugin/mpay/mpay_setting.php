<?php
defined('EM_ROOT') || exit('Access Denied');

function mpay_setting_value(Storage $storage, array $keys, string $default = ''): string
{
    foreach ($keys as $k) {
        $v = trim((string) ($storage->getValue($k) ?? ''));
        if ($v !== '') {
            return $v;
        }
    }
    return $default;
}

function mpay_setting_enabled(Storage $storage, string $code, bool $default = true): bool
{
    $raw = $storage->getValue($code . '_enabled');
    if ($raw === null || (string) $raw === '') {
        return $default;
    }
    return (string) $raw === '1';
}

function mpay_setting_mode(Storage $storage, string $code): string
{
    $mode = strtolower(mpay_setting_value($storage, [$code . '_create_mode', 'create_mode'], 'submit'));
    return in_array($mode, ['submit', 'mapi'], true) ? $mode : 'submit';
}

function plugin_setting_view(): void
{
    $storage = Storage::getInstance('mpay');
    $csrfToken = Csrf::token();
    $registerUrl = 'https://m.ynile.cn/';

    $channels = [
        'mpay_alipay' => ['label' => '支付宝', 'default_name' => '支付宝', 'image' => '/content/plugin/mpay/alipay.png'],
        'mpay_wxpay'  => ['label' => '微信支付', 'default_name' => '微信支付', 'image' => '/content/plugin/mpay/wxpay.png'],
        'mpay_qqpay'  => ['label' => 'QQ钱包', 'default_name' => 'QQ钱包', 'image' => '/content/plugin/mpay/qqpay.png'],
    ];
    ?>
    <style>
    .popup-content:has(.mp) { overflow: hidden; }

    .mp {
        --b: #2563eb;
        --b2: #1d4ed8;
        --b3: #eff4ff;
        --b4: #dbe6ff;
        --ink: #0f172a;
        --sub: #64748b;
        --line: rgba(15, 23, 42, 0.08);
        --soft: #f7f9fc;
        height: 100%;
        min-height: 0;
        display: flex;
        flex-direction: column;
        background:
            radial-gradient(900px 320px at 0% -20%, rgba(37, 99, 235, 0.16), transparent 55%),
            radial-gradient(700px 280px at 100% 0%, rgba(59, 130, 246, 0.10), transparent 50%),
            linear-gradient(180deg, #f8faff 0%, #eef3fb 100%);
        color: var(--ink);
        font-family: "PingFang SC", "Segoe UI", "Microsoft YaHei", sans-serif;
        font-size: 13px;
        line-height: 1.5;
        -webkit-font-smoothing: antialiased;
    }

    .mp *, .mp *::before, .mp *::after { box-sizing: border-box; }

    .mp-form {
        height: 100%;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }

    .mp-head {
        flex-shrink: 0;
        padding: 20px 20px 0;
    }

    .mp-title {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 16px;
    }

    .mp-title-l {
        display: flex;
        gap: 12px;
        align-items: center;
        min-width: 0;
    }

    .mp-logo {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        flex: 0 0 auto;
        object-fit: cover;
        display: block;
    }

    .mp-title h1 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        letter-spacing: 0.2px;
        color: var(--ink);
    }

    .mp-title p {
        margin: 3px 0 0;
        font-size: 12px;
        color: var(--sub);
    }

    .mp-linkbox {
        flex: 0 0 auto;
        max-width: 52%;
    }

    .mp-reg-btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 8px 14px 8px 12px;
        border-radius: 12px;
        text-decoration: none;
        color: #fff;
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.28);
        border: 1px solid rgba(255, 255, 255, 0.18);
        transition: transform .15s, box-shadow .15s, filter .15s;
        cursor: pointer;
        max-width: 100%;
    }

    .mp-reg-btn:hover {
        filter: brightness(1.06);
        box-shadow: 0 10px 22px rgba(37, 99, 235, 0.36);
        transform: translateY(-1px);
    }

    .mp-reg-btn:active {
        transform: translateY(1px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.24);
        filter: brightness(0.98);
    }

    .mp-reg-btn__icon {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.18);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
    }

    .mp-reg-btn__icon svg {
        width: 14px;
        height: 14px;
        display: block;
    }

    .mp-reg-btn__text {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 1px;
        min-width: 0;
        line-height: 1.25;
    }

    .mp-reg-btn__label {
        font-size: 11px;
        font-weight: 500;
        opacity: 0.88;
        letter-spacing: 0.2px;
    }

    .mp-reg-btn__cta {
        font-size: 13px;
        font-weight: 700;
        white-space: nowrap;
    }

    .mp-reg-btn__arrow {
        font-size: 14px;
        font-weight: 700;
        opacity: 0.9;
        margin-left: 2px;
        transition: transform .15s;
    }

    .mp-reg-btn:hover .mp-reg-btn__arrow {
        transform: translateX(3px);
    }

    .mp-nav {
        flex-shrink: 0;
        padding: 0 20px 14px;
    }

    .mp-tabs {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 4px;
        padding: 4px;
        border-radius: 14px;
        background: rgba(255,255,255,0.72);
        border: 1px solid rgba(15, 23, 42, 0.06);
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
        backdrop-filter: blur(8px);
    }

    .mp-tab {
        appearance: none;
        border: 0;
        background: transparent;
        border-radius: 10px;
        padding: 10px 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        color: var(--sub);
        font-size: 13px;
        font-weight: 500;
        transition: background .18s, color .18s, box-shadow .18s;
    }

    .mp-tab img {
        width: 16px;
        height: 16px;
        object-fit: contain;
    }

    .mp-tab:hover {
        color: var(--ink);
        background: rgba(37, 99, 235, 0.04);
    }

    .mp-tab.is-on {
        color: var(--b2);
        background: #fff;
        box-shadow: 0 2px 8px rgba(37, 99, 235, 0.12), 0 1px 2px rgba(15, 23, 42, 0.04);
        font-weight: 600;
    }

    .mp-scroll {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        padding: 0 20px 18px;
        scrollbar-width: thin;
        scrollbar-color: #c7d2e4 transparent;
    }

    .mp-scroll::-webkit-scrollbar { width: 6px; }
    .mp-scroll::-webkit-scrollbar-track { background: transparent; }
    .mp-scroll::-webkit-scrollbar-thumb {
        background: #c7d2e4;
        border-radius: 999px;
    }

    .mp-panel { display: none; animation: mpFade .22s ease; }
    .mp-panel.is-on { display: block; }

    @keyframes mpFade {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: none; }
    }

    .mp-card {
        background: rgba(255,255,255,0.92);
        border: 1px solid rgba(15, 23, 42, 0.06);
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
        padding: 16px;
        margin-bottom: 12px;
        backdrop-filter: blur(6px);
    }

    .mp-card:last-child { margin-bottom: 0; }

    .mp-card-hd {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .mp-card-hd h2,
    .mp-card > h2 {
        margin: 0;
        font-size: 14px;
        font-weight: 700;
        color: var(--ink);
        letter-spacing: 0.2px;
    }

    .mp-card-hd p,
    .mp-card > h2 + p {
        margin: 3px 0 0;
        font-size: 12px;
        color: var(--sub);
    }

    .mp-card > h2 + p { margin-bottom: 12px; }

    .mp-switch {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        user-select: none;
        flex-shrink: 0;
    }

    .mp-switch input { display: none; }

    .mp-switch i {
        width: 44px;
        height: 26px;
        border-radius: 999px;
        background: #d4dce8;
        position: relative;
        transition: background .2s;
        box-shadow: inset 0 1px 2px rgba(15,23,42,.08);
    }

    .mp-switch i::after {
        content: "";
        position: absolute;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #fff;
        top: 3px;
        left: 3px;
        transition: left .2s;
        box-shadow: 0 2px 6px rgba(15,23,42,.16);
    }

    .mp-switch input:checked + i {
        background: linear-gradient(90deg, #3b82f6, #2563eb);
    }

    .mp-switch input:checked + i::after { left: 21px; }

    .mp-switch b {
        min-width: 42px;
        font-size: 12px;
        font-weight: 600;
        color: var(--sub);
    }

    .mp-switch input:checked ~ b { color: var(--b); }

    .mp-modes {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .mp-mode {
        position: relative;
        display: block;
        padding: 14px;
        border-radius: 12px;
        border: 1px solid rgba(15, 23, 42, 0.08);
        background: var(--soft);
        cursor: pointer;
        transition: border-color .18s, background .18s, box-shadow .18s, transform .18s;
    }

    .mp-mode input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .mp-mode strong {
        display: block;
        font-size: 13px;
        font-weight: 700;
        color: var(--ink);
        margin-bottom: 4px;
    }

    .mp-mode em {
        display: block;
        font-style: normal;
        font-size: 11.5px;
        color: var(--sub);
        line-height: 1.45;
    }

    .mp-mode:hover {
        border-color: rgba(37, 99, 235, 0.28);
        transform: translateY(-1px);
    }

    .mp-mode.is-on {
        background: var(--b3);
        border-color: rgba(37, 99, 235, 0.45);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
    }

    .mp-mode.is-on strong { color: var(--b2); }

    .mp-grid {
        display: grid;
        gap: 12px;
    }

    .mp-field label {
        display: block;
        margin: 0 0 6px;
        font-size: 12px;
        font-weight: 600;
        color: #334155;
    }

    .mp-input {
        width: 100%;
        height: 42px;
        border: 1px solid rgba(15, 23, 42, 0.10);
        border-radius: 11px;
        background: #fff;
        padding: 0 13px;
        font-size: 13.5px;
        color: var(--ink);
        outline: none;
        transition: border-color .15s, box-shadow .15s, background .15s;
    }

    .mp-input::placeholder { color: #94a3b8; }

    .mp-input:hover {
        border-color: rgba(37, 99, 235, 0.28);
    }

    .mp-input:focus {
        border-color: var(--b);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        background: #fff;
    }

    .mp-secret { position: relative; }
    .mp-secret .mp-input { padding-right: 42px; }

    .mp-eye {
        position: absolute;
        right: 6px;
        top: 50%;
        transform: translateY(-50%);
        width: 30px;
        height: 30px;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: var(--sub);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .mp-eye:hover {
        color: var(--b);
        background: var(--b3);
    }

    .mp-hint {
        margin: 7px 0 0;
        font-size: 12px;
        color: var(--sub);
    }

    .mp-foot {
        flex-shrink: 0;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 14px 20px;
        background: rgba(255,255,255,0.82);
        border-top: 1px solid rgba(15, 23, 42, 0.06);
        backdrop-filter: blur(10px);
    }

    .mp-btn {
        height: 38px;
        min-width: 92px;
        padding: 0 18px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        border: 1px solid transparent;
        transition: transform .12s, box-shadow .15s, background .15s, border-color .15s, opacity .15s;
    }

    .mp-btn:active { transform: translateY(1px); }

    .mp-btn--ghost {
        background: #fff;
        border-color: rgba(15, 23, 42, 0.10);
        color: #475569;
    }

    .mp-btn--ghost:hover {
        border-color: rgba(37, 99, 235, 0.3);
        color: var(--b);
    }

    .mp-btn--solid {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: #fff;
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.28);
    }

    .mp-btn--solid:hover {
        box-shadow: 0 10px 22px rgba(37, 99, 235, 0.34);
    }

    .mp-btn--solid:disabled {
        opacity: 0.55;
        cursor: not-allowed;
        box-shadow: none;
    }

    @media (max-width: 560px) {
        .mp-title { flex-direction: column; }
        .mp-linkbox { max-width: 100%; }
        .mp-modes { grid-template-columns: 1fr; }
        .mp-head, .mp-nav, .mp-scroll, .mp-foot { padding-left: 14px; padding-right: 14px; }
        .mp-tabs { grid-template-columns: 1fr; }
    }
    </style>

    <div class="mp">
        <form id="mpayForm" class="mp-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="mp-head">
                <div class="mp-title">
                    <div class="mp-title-l">
                        <img class="mp-logo" src="/content/plugin/mpay/preview.jpg" alt="码支付" width="40" height="40">
                        <div>
                            <h1>官方码支付配置</h1>
                            <p>无需资质 注册即用 收款实时到账 资金无中转</p>
                        </div>
                    </div>
                    <div class="mp-linkbox">
                        <a class="mp-reg-btn"
                           href="<?= htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8') ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           title="打开官网注册页">
                            <span class="mp-reg-btn__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                    <polyline points="15 3 21 3 21 9"/>
                                    <line x1="10" y1="14" x2="21" y2="3"/>
                                </svg>
                            </span>
                            <span class="mp-reg-btn__text">
                                <span class="mp-reg-btn__label">m.ynile.cn</span>
                                <span class="mp-reg-btn__cta">点我跳转注册<span class="mp-reg-btn__arrow">→</span></span>
                            </span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="mp-nav">
                <div class="mp-tabs" role="tablist">
                    <?php $first = true; foreach ($channels as $code => $ch): ?>
                    <button type="button"
                        class="mp-tab <?= $first ? 'is-on' : '' ?>"
                        data-tab="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"
                        role="tab"
                        aria-selected="<?= $first ? 'true' : 'false' ?>">
                        <img src="<?= htmlspecialchars($ch['image'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                        <?= htmlspecialchars($ch['label'], ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <?php $first = false; endforeach; ?>
                </div>
            </div>

            <div class="mp-scroll">
                <?php $first = true; foreach ($channels as $code => $ch):
                    $enabled = mpay_setting_enabled($storage, $code, true);
                    $mode = mpay_setting_mode($storage, $code);
                    $displayName = mpay_setting_value($storage, [$code . '_name'], $ch['default_name']);
                    $merchantId = mpay_setting_value($storage, [$code . '_merchant_id', 'merchant_id']);
                    $secretKey = mpay_setting_value($storage, [$code . '_secret_key', 'secret_key']);
                ?>
                <div class="mp-panel <?= $first ? 'is-on' : '' ?>" id="mp-panel-<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" role="tabpanel">

                    <div class="mp-card">
                        <div class="mp-card-hd">
                            <div>
                                <h2>启用状态</h2>
                                <p>关闭后前台不展示该支付方式</p>
                            </div>
                            <label class="mp-switch">
                                <input type="checkbox" name="<?= $code ?>_enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                                <i></i>
                                <b><?= $enabled ? '已启用' : '已关闭' ?></b>
                            </label>
                        </div>
                    </div>

                    <div class="mp-card">
                        <h2>下单模式</h2>
                        <p>按业务场景选择跳转或接口下单</p>
                        <div class="mp-modes">
                            <label class="mp-mode <?= $mode === 'submit' ? 'is-on' : '' ?>">
                                <input type="radio" name="<?= $code ?>_create_mode" value="submit" <?= $mode === 'submit' ? 'checked' : '' ?>>
                                <strong>页面跳转</strong>
                                <em>submit.php · 浏览器跳转收银</em>
                            </label>
                            <label class="mp-mode <?= $mode === 'mapi' ? 'is-on' : '' ?>">
                                <input type="radio" name="<?= $code ?>_create_mode" value="mapi" <?= $mode === 'mapi' ? 'checked' : '' ?>>
                                <strong>后端接口</strong>
                                <em>mapi.php · 服务端获取支付参数</em>
                            </label>
                        </div>
                    </div>

                    <div class="mp-card">
                        <h2>商户凭证</h2>
                        <p>各通道独立配置，互不影响</p>
                        <div class="mp-grid">
                            <div class="mp-field">
                                <label>商户 ID</label>
                                <input type="text" class="mp-input" name="<?= $code ?>_merchant_id" value="<?= htmlspecialchars($merchantId, ENT_QUOTES, 'UTF-8') ?>" placeholder="注册后获取" autocomplete="off">
                            </div>
                            <div class="mp-field">
                                <label>商户密钥</label>
                                <div class="mp-secret">
                                    <input type="password" class="mp-input mp-secret-input" name="<?= $code ?>_secret_key" value="<?= htmlspecialchars($secretKey, ENT_QUOTES, 'UTF-8') ?>" placeholder="请输入商户密钥" autocomplete="off">
                                    <button type="button" class="mp-eye" title="显示/隐藏" aria-label="显示或隐藏密钥">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mp-card">
                        <h2>前台名称</h2>
                        <p>结账页展示给用户的名称</p>
                        <div class="mp-field">
                            <label>显示名称</label>
                            <input type="text" class="mp-input" name="<?= $code ?>_name" value="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars($ch['default_name'], ENT_QUOTES, 'UTF-8') ?>">
                            <div class="mp-hint">留空则显示「<?= htmlspecialchars($ch['default_name'], ENT_QUOTES, 'UTF-8') ?>」</div>
                        </div>
                    </div>

                </div>
                <?php $first = false; endforeach; ?>
            </div>

            <div class="mp-foot">
                <button type="button" class="mp-btn mp-btn--ghost" id="mpayCancelBtn">取消</button>
                <button type="button" class="mp-btn mp-btn--solid" id="mpaySaveBtn">保存配置</button>
            </div>
        </form>
    </div>

    <script>
    (function () {
        layui.use(['layer'], function () {
            var $ = layui.$;

            $('.mp-tab').on('click', function () {
                var tab = $(this).data('tab');
                $('.mp-tab').removeClass('is-on').attr('aria-selected', 'false');
                $(this).addClass('is-on').attr('aria-selected', 'true');
                $('.mp-panel').removeClass('is-on');
                $('#mp-panel-' + tab).addClass('is-on');
            });

            $('.mp-switch input').on('change', function () {
                $(this).closest('.mp-switch').find('b').text(this.checked ? '已启用' : '已关闭');
            });

            $('.mp-mode input').on('change', function () {
                var $group = $(this).closest('.mp-modes');
                $group.find('.mp-mode').removeClass('is-on');
                $(this).closest('.mp-mode').addClass('is-on');
            });

            $('.mp-eye').on('click', function () {
                var $input = $(this).siblings('.mp-secret-input');
                $input.attr('type', $input.attr('type') === 'password' ? 'text' : 'password');
            });

            $('#mpayCancelBtn').on('click', function () {
                parent.layer.close(parent.layer.getFrameIndex(window.name));
            });

            $('#mpaySaveBtn').on('click', function () {
                var $btn = $(this);
                $btn.prop('disabled', true).text('保存中...');
                $.ajax({
                    type: 'POST',
                    url: window.PLUGIN_SAVE_URL || '/admin/plugin.php',
                    data: $('#mpayForm').serialize() + '&_action=save_config&name=mpay',
                    dataType: 'json',
                    success: function (res) {
                        if (res.code === 0 || res.code === 200) {
                            if (res.data && res.data.csrf_token) {
                                $('#mpayForm input[name=csrf_token]').val(res.data.csrf_token);
                            }
                            parent.layer.msg('配置已保存');
                            parent.layer.close(parent.layer.getFrameIndex(window.name));
                            return;
                        }
                        layui.layer.msg(res.msg || '保存失败');
                        $btn.prop('disabled', false).text('保存配置');
                    },
                    error: function () {
                        layui.layer.msg('网络异常');
                        $btn.prop('disabled', false).text('保存配置');
                    }
                });
            });
        });
    })();
    </script>
    <?php
}

function plugin_setting(): void
{
    $csrf = (string) Input::post('csrf_token', '');
    if (!Csrf::validate($csrf)) {
        Response::error('请求已失效，请刷新页面后重试');
    }

    $storage = Storage::getInstance('mpay');
    $channels = ['mpay_alipay', 'mpay_wxpay', 'mpay_qqpay'];

    foreach ($channels as $code) {
        $enabled = Input::post($code . '_enabled', '') === '1' ? '1' : '0';
        $mode = strtolower(trim((string) Input::post($code . '_create_mode', 'submit')));
        if (!in_array($mode, ['submit', 'mapi'], true)) {
            $mode = 'submit';
        }

        $storage->setValue($code . '_enabled', $enabled);
        $storage->setValue($code . '_create_mode', $mode);
        $storage->setValue($code . '_name', trim((string) Input::post($code . '_name', '')));
        $storage->setValue($code . '_merchant_id', trim((string) Input::post($code . '_merchant_id', '')));
        $storage->setValue($code . '_secret_key', trim((string) Input::post($code . '_secret_key', '')));
    }

    Response::success('配置已保存', ['csrf_token' => Csrf::refresh()]);
}
