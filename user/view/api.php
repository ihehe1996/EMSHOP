<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
?>
<div class="uc-page">
    <div class="uc-page-header">
        <h2 class="uc-page-title">API对接</h2>
        <p class="uc-page-desc">用于第三方系统对接的接口凭证信息</p>
    </div>

    <div class="uc-form-card">
        <!-- 接口地址 -->
        <div class="uc-form-group">
            <label class="uc-form-label">接口地址</label>
            <div class="uc-form-control">
                <div class="uc-api-field">
                    <input type="text" class="uc-input uc-input--readonly" id="apiUrl" value="<?= htmlspecialchars($apiUrl) ?>" readonly>
                    <button type="button" class="uc-btn uc-btn--copy" data-copy="apiUrl" title="复制"><i class="fa fa-copy"></i></button>
                </div>
            </div>
        </div>

        <!-- APPID -->
        <div class="uc-form-group">
            <label class="uc-form-label">APPID</label>
            <div class="uc-form-control">
                <div class="uc-api-field">
                    <input type="text" class="uc-input uc-input--readonly" id="apiAppId" value="<?= htmlspecialchars($appId) ?>" readonly>
                    <button type="button" class="uc-btn uc-btn--copy" data-copy="apiAppId" title="复制"><i class="fa fa-copy"></i></button>
                </div>
            </div>
        </div>

        <!-- SECRET -->
        <div class="uc-form-group">
            <label class="uc-form-label">SECRET</label>
            <div class="uc-form-control">
                <div class="uc-api-field">
                    <input type="text" class="uc-input uc-input--readonly" id="apiSecret" value="<?= htmlspecialchars($secret ?? '') ?>" readonly placeholder="尚未生成">
                    <?php if (empty($secret)): ?>
                    <button type="button" class="uc-btn uc-btn--generate" id="secretBtn"><i class="fa fa-key"></i> 生成</button>
                    <?php else: ?>
                    <button type="button" class="uc-btn uc-btn--copy" data-copy="apiSecret" title="复制"><i class="fa fa-copy"></i></button>
                    <button type="button" class="uc-btn uc-btn--generate uc-btn--reset" id="secretBtn"><i class="fa fa-refresh"></i> 重置</button>
                    <?php endif; ?>
                </div>
                <?php if (!empty($secret)): ?>
                <div class="uc-form-hint">重置后旧密钥立即失效，请及时更新对接方配置</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 全部复制 -->
        <div class="uc-form-group">
            <label class="uc-form-label"></label>
            <div class="uc-form-control">
                <button type="button" class="uc-btn uc-btn--primary" id="copyAllBtn"><i class="fa fa-clipboard"></i> 全部复制</button>
            </div>
        </div>
    </div>

    <div class="uc-form-card" style="margin-top:16px;">
        <div class="uc-form-group">
            <label class="uc-form-label">下单接口</label>
            <div class="uc-form-control">
                <div class="uc-api-field">
                    <input type="text" class="uc-input uc-input--readonly" id="apiCreateOrderUrl" value="<?= htmlspecialchars($apiUrl . '?c=api&act=create_order') ?>" readonly>
                    <button type="button" class="uc-btn uc-btn--copy" data-copy="apiCreateOrderUrl" title="复制"><i class="fa fa-copy"></i></button>
                </div>
            </div>
        </div>
        <div class="uc-form-group">
            <label class="uc-form-label">查单接口</label>
            <div class="uc-form-control">
                <div class="uc-api-field">
                    <input type="text" class="uc-input uc-input--readonly" id="apiQueryOrderUrl" value="<?= htmlspecialchars($apiUrl . '?c=api&act=query_order') ?>" readonly>
                    <button type="button" class="uc-btn uc-btn--copy" data-copy="apiQueryOrderUrl" title="复制"><i class="fa fa-copy"></i></button>
                </div>
            </div>
        </div>
        <div class="uc-form-group">
            <label class="uc-form-label">商品列表接口</label>
            <div class="uc-form-control">
                <div class="uc-api-field">
                    <input type="text" class="uc-input uc-input--readonly" id="apiGoodsListUrl" value="<?= htmlspecialchars($apiUrl . '?c=api&act=goods_list') ?>" readonly>
                    <button type="button" class="uc-btn uc-btn--copy" data-copy="apiGoodsListUrl" title="复制"><i class="fa fa-copy"></i></button>
                </div>
                <div class="uc-form-hint" style="margin-top:8px;line-height:1.8;">
                    不分页；请求须发在<strong>与前台一致的域名</strong>上。传 <code>goods_ids</code> 拉明细时，会附带简介、详情、多图、标签名、起购/限购、附加选项等字段（供 EMSHOP 对接导入）。详见接口注释。
                </div>
            </div>
        </div>
        <div class="uc-form-group">
            <label class="uc-form-label">签名规则</label>
            <div class="uc-form-control">
                <div class="uc-form-hint" style="line-height:1.8;">
                    1. 参与签名参数按参数名升序排序，排除 <code>sign</code> / <code>sign_type</code> / 空值。<br>
                    2. 拼接为 <code>key=value&key2=value2...</code> 后，末尾追加 <code>SECRET</code>。<br>
                    3. 计算 <code>md5(拼接串 + SECRET)</code>，转小写得到 <code>sign</code>。<br>
                    4. 必传鉴权参数：<code>appid</code>、<code>timestamp</code>、<code>sign</code>（可选 <code>sign_type=MD5</code>）。
                </div>
                <div class="uc-form-hint" style="margin-top:8px;line-height:1.8;">
                    下单接口将<strong>直接扣除对接账号余额</strong>完成支付，不走第三方支付收银台，不需要传 <code>payment_code</code>。
                </div>
                <div class="uc-form-hint" style="margin-top:8px;line-height:1.8;">
                    对接同系统时，可在下单请求中传 <code>delivery_callback_url</code>。上游发货后会向该地址推送 <code>delivery_content</code>（建议带业务令牌参数做鉴权）。
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var csrfToken = <?= json_encode($csrfToken) ?>;

    // 复制到剪贴板
    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                layui.layer.msg('已复制');
            }).catch(function () {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    }

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;left:-9999px;';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            layui.layer.msg('已复制');
        } catch (e) {
            layui.layer.msg('复制失败，请手动复制');
        }
        document.body.removeChild(ta);
    }

    // 解绑旧事件，防止 PJAX 重复绑定
    $(document).off('.ucApi');

    // 单项复制
    $(document).on('click.ucApi', '.uc-btn--copy', function () {
        var inputId = $(this).data('copy');
        var val = $('#' + inputId).val();
        if (!val) {
            layui.layer.msg('内容为空');
            return;
        }
        copyText(val);
    });

    // 全部复制
    $(document).on('click.ucApi', '#copyAllBtn', function () {
        var url = $('#apiUrl').val();
        var appId = $('#apiAppId').val();
        var secret = $('#apiSecret').val() || '（未生成）';
        var text = '接口地址：' + url + '\nAPPID：' + appId + '\nSECRET：' + secret;
        copyText(text);
    });

    // 生成 / 重置 SECRET
    $(document).on('click.ucApi', '#secretBtn', function () {
        var btn = $(this);
        var isReset = btn.hasClass('uc-btn--reset');

        function doGenerate() {
            var origHtml = btn.html();
            btn.addClass('is-loading').html('<i class="fa fa-spinner fa-spin"></i> 请稍候');
            $.post('/user/api.php', {
                action: 'generate_secret',
                csrf_token: csrfToken
            }, function (res) {
                btn.removeClass('is-loading').html(origHtml);
                if (res.code === 200) {
                    if (res.data && res.data.csrf_token) {
                        csrfToken = res.data.csrf_token;
                        window.userCsrfToken = csrfToken;
                    }
                    // 更新输入框
                    $('#apiSecret').val(res.data.secret);
                    layui.layer.msg(isReset ? '已重置' : '生成成功');

                    // 切换按钮状态：生成 → 复制 + 重置
                    var $field = btn.closest('.uc-api-field');
                    $field.find('.uc-btn--copy[data-copy="apiSecret"]').remove();
                    btn.before('<button type="button" class="uc-btn uc-btn--copy" data-copy="apiSecret" title="复制"><i class="fa fa-copy"></i></button>');
                    btn.html('<i class="fa fa-refresh"></i> 重置').addClass('uc-btn--reset');

                    // 添加提示
                    var $control = $field.closest('.uc-form-control');
                    if (!$control.find('.uc-form-hint').length) {
                        $control.append('<div class="uc-form-hint">重置后旧密钥立即失效，请及时更新对接方配置</div>');
                    }
                } else {
                    layui.layer.msg(res.msg || '操作失败');
                }
            }, 'json').fail(function () {
                btn.removeClass('is-loading').html(origHtml);
                layui.layer.msg('网络异常');
            });
        }

        if (isReset) {
            layui.layer.confirm('重置后旧密钥立即失效，确定重置吗？', function (idx) {
                layui.layer.close(idx);
                doGenerate();
            });
        } else {
            doGenerate();
        }
    });
})();
</script>
