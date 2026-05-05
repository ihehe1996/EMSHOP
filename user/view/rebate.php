<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
?>
<div class="uc-page">
    <div class="uc-page-header">
        <h2 class="uc-page-title">我的推广</h2>
        <p class="uc-page-desc">推广链接、团队统计、佣金明细与提现</p>
    </div>

    <?php if (empty($rebateEnabled)): ?>
    <div style="padding:14px 16px;background:#fff7e6;border-left:4px solid #fa8c16;border-radius:6px;color:#666;margin-bottom:20px;font-size:13px;">
        <i class="fa fa-warning"></i> 平台管理员尚未启用返佣功能，以下链接/数据仅做展示
    </div>
    <?php endif; ?>

    <!-- 数据面板 -->
    <div class="uc-rebate-stats">
        <div class="uc-rebate-card uc-rebate-card--primary">
            <div class="uc-rebate-card__label"><i class="fa fa-lock"></i> 冻结佣金</div>
            <div class="uc-rebate-card__value"><?= htmlspecialchars($commissionFrozenDisplay) ?></div>
            <div class="uc-rebate-card__desc">订单完成后 <?= (int) $freezeDays ?> 天内不可提现</div>
        </div>
        <div class="uc-rebate-card uc-rebate-card--success">
            <div class="uc-rebate-card__label"><i class="fa fa-check-circle"></i> 可提现</div>
            <div class="uc-rebate-card__value"><?= htmlspecialchars($commissionAvailableDisplay) ?></div>
            <button type="button" class="uc-rebate-withdraw-btn" id="ucRebateWithdrawBtn">提现到余额</button>
        </div>
        <div class="uc-rebate-card">
            <div class="uc-rebate-card__label"><i class="fa fa-trophy"></i> 累计佣金</div>
            <div class="uc-rebate-card__value"><?= htmlspecialchars($totalEarnedDisplay) ?></div>
            <div class="uc-rebate-card__desc">含已提现 / 冻结 / 可提现</div>
        </div>
        <div class="uc-rebate-card">
            <div class="uc-rebate-card__label"><i class="fa fa-users"></i> 团队规模</div>
            <div class="uc-rebate-card__value"><?= (int) $teamCountNum ?></div>
            <div class="uc-rebate-card__desc">直推 <?= (int) $directCountNum ?> 人 · 含二级</div>
        </div>
    </div>

    <!-- 推广链接 -->
    <div class="uc-rebate-link">
        <div class="uc-rebate-link__title"><i class="fa fa-link"></i> 我的推广链接</div>
        <div class="uc-rebate-link__row">
            <span class="uc-rebate-link__key">邀请码：</span>
            <code class="uc-rebate-link__val uc-rebate-code"><?= htmlspecialchars($inviteCode) ?></code>
            <button type="button" class="uc-rebate-copy" data-copy="<?= htmlspecialchars($inviteCode) ?>">复制</button>
        </div>
        <div class="uc-rebate-link__row">
            <span class="uc-rebate-link__key">链接：</span>
            <code class="uc-rebate-link__val"><?= htmlspecialchars($inviteLink) ?></code>
            <button type="button" class="uc-rebate-copy" data-copy="<?= htmlspecialchars($inviteLink) ?>">复制</button>
        </div>
        <div class="uc-rebate-link__hint">
            用户通过此链接进入并注册后将成为您的直接下线；未注册的访客下单也会按归因计入您的佣金。
        </div>
    </div>

    <!-- 我的团队 -->
    <div class="uc-rebate-logs">
        <div class="uc-rebate-logs__head">
            <div class="uc-rebate-logs__title"><i class="fa fa-users"></i> 我的团队</div>
            <div class="uc-rebate-logs__tabs">
                <button type="button" class="uc-rebate-team-tab active" data-level="">全部</button>
                <button type="button" class="uc-rebate-team-tab" data-level="1">直推</button>
                <button type="button" class="uc-rebate-team-tab" data-level="2">二级</button>
            </div>
        </div>
        <table class="uc-rebate-log-table">
            <thead>
                <tr><th>成员</th><th>级别</th><th>加入时间</th></tr>
            </thead>
            <tbody id="ucRebateTeamList">
                <tr><td colspan="3" class="uc-rebate-log-empty">加载中...</td></tr>
            </tbody>
        </table>
        <div class="uc-rebate-log-more" id="ucRebateTeamMore" style="display:none;">
            <button type="button" id="ucRebateTeamMoreBtn">加载更多</button>
        </div>
    </div>

    <!-- 佣金明细 -->
    <div class="uc-rebate-logs">
        <div class="uc-rebate-logs__head">
            <div class="uc-rebate-logs__title"><i class="fa fa-list-alt"></i> 佣金明细</div>
            <div class="uc-rebate-logs__tabs">
                <button type="button" class="uc-rebate-log-tab active" data-status="">全部</button>
                <button type="button" class="uc-rebate-log-tab" data-status="frozen">冻结中</button>
                <button type="button" class="uc-rebate-log-tab" data-status="available">可提现</button>
                <button type="button" class="uc-rebate-log-tab" data-status="withdrawn">已提现</button>
                <button type="button" class="uc-rebate-log-tab" data-status="reverted">已倒扣</button>
            </div>
        </div>
        <table class="uc-rebate-log-table">
            <thead>
                <tr><th>订单号</th><th>级别</th><th>比例</th><th>利润</th><th>佣金</th><th>状态</th><th>时间</th></tr>
            </thead>
            <tbody id="ucRebateLogList">
                <tr><td colspan="7" class="uc-rebate-log-empty">加载中...</td></tr>
            </tbody>
        </table>
        <div class="uc-rebate-log-more" id="ucRebateLogMore" style="display:none;">
            <button type="button" id="ucRebateLogMoreBtn">加载更多</button>
        </div>
    </div>
</div>

<script>
(function () {
    $(document).off('.ucRebate');

    var logPage = 1, logStatus = '';
    var teamPage = 1, teamLevel = '';

    function esc(s){ if (!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function loadLogs(reset) {
        if (reset) { logPage = 1; $('#ucRebateLogList').html('<tr><td colspan="7" class="uc-rebate-log-empty">加载中...</td></tr>'); }
        $.get('/?c=rebate&a=logs', { page: logPage, status: logStatus }, function (res) {
            if (res.code !== 200) { layui.layer.msg(res.msg || '加载失败'); return; }
            var list = (res.data && res.data.data) || [];
            if (reset) $('#ucRebateLogList').empty();
            if (list.length === 0 && logPage === 1) {
                $('#ucRebateLogList').html('<tr><td colspan="7" class="uc-rebate-log-empty">暂无记录</td></tr>');
                $('#ucRebateLogMore').hide();
                return;
            }
            var statusMap = {
                frozen: ['冻结中', '#fa8c16'], available: ['可提现', '#52c41a'],
                withdrawn: ['已提现', '#1890ff'], reverted: ['已倒扣', '#999']
            };
            var html = '';
            list.forEach(function (r) {
                var st = statusMap[r.status] || [r.status, '#999'];
                html += '<tr>';
                html += '<td><code>' + esc(r.order_no) + '</code></td>';
                html += '<td>L' + r.level + '</td>';
                html += '<td>' + (parseInt(r.rate)/100).toFixed(2) + '%</td>';
                html += '<td>' + r.basis_amount_display + '</td>';
                html += '<td><strong style="color:#fa5252;">+' + r.amount_display + '</strong></td>';
                html += '<td style="color:' + st[1] + ';">' + st[0] + '</td>';
                html += '<td>' + String(r.created_at).substring(0, 16) + '</td>';
                html += '</tr>';
            });
            $('#ucRebateLogList').append(html);

            var total = (res.data && res.data.total) || 0;
            if ($('#ucRebateLogList tr').length >= total) $('#ucRebateLogMore').hide();
            else $('#ucRebateLogMore').show();
        }, 'json');
    }

    $(document).on('click.ucRebate', '.uc-rebate-copy', function () {
        var txt = String($(this).data('copy') || '');
        if (!txt) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(txt).then(function(){ layui.layer.msg('已复制'); }, fb);
        } else fb();
        function fb(){
            var $i = $('<input style="position:fixed;top:-100px;">').val(txt).appendTo('body').select();
            try { document.execCommand('copy'); layui.layer.msg('已复制'); } catch(e){}
            $i.remove();
        }
    });

    $(document).on('click.ucRebate', '#ucRebateWithdrawBtn', function () {
        layui.layer.prompt({ title: '请输入提现金额（元）', formType: 0 }, function (value, idx) {
            layui.layer.close(idx);
            if (!value || isNaN(value) || parseFloat(value) <= 0) { layui.layer.msg('金额无效'); return; }
            $.post('/?c=rebate&a=withdraw', { amount: value }, function (res) {
                if (res.code === 200) { layui.layer.msg('提现成功，已到账余额'); setTimeout(function(){ location.reload(); }, 800); }
                else { layui.layer.msg(res.msg || '提现失败'); }
            }, 'json');
        });
    });

    $(document).on('click.ucRebate', '.uc-rebate-log-tab', function () {
        $('.uc-rebate-log-tab').removeClass('active');
        $(this).addClass('active');
        logStatus = $(this).data('status') || '';
        loadLogs(true);
    });
    $(document).on('click.ucRebate', '#ucRebateLogMoreBtn', function () {
        logPage += 1; loadLogs(false);
    });

    // ---- 团队列表 ----
    function loadTeam(reset) {
        if (reset) { teamPage = 1; $('#ucRebateTeamList').html('<tr><td colspan="3" class="uc-rebate-log-empty">加载中...</td></tr>'); }
        $.get('/?c=rebate&a=team', { page: teamPage, level: teamLevel }, function (res) {
            if (res.code !== 200) { layui.layer.msg(res.msg || '加载失败'); return; }
            var list = (res.data && res.data.data) || [];
            if (reset) $('#ucRebateTeamList').empty();
            if (list.length === 0 && teamPage === 1) {
                $('#ucRebateTeamList').html('<tr><td colspan="3" class="uc-rebate-log-empty">暂无团队成员</td></tr>');
                $('#ucRebateTeamMore').hide();
                return;
            }
            var avatarStyle = 'width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;';
            var html = '';
            list.forEach(function (r) {
                var levelLabel = r.team_level === 1 ? '直推' : '二级';
                var levelColor = r.team_level === 1 ? '#fa5252' : '#1890ff';
                // 有头像用 img，没有用灰底带 icon 的 fallback（和个人中心头像规则保持一致）
                var avatarHtml = r.avatar
                    ? '<img src="' + esc(r.avatar) + '" style="' + avatarStyle + 'object-fit:cover;background:#f2f3f5;" onerror="this.outerHTML=\'<span style=&quot;' + avatarStyle + 'background:#f2f3f5;color:#999;&quot;><i class=&quot;fa fa-user&quot;></i></span>\'">'
                    : '<span style="' + avatarStyle + 'background:#f2f3f5;color:#999;"><i class="fa fa-user"></i></span>';
                html += '<tr>';
                html += '<td><div style="display:flex;align-items:center;gap:10px;">' + avatarHtml + '<span>' + esc(r.display_name) + '</span></div></td>';
                html += '<td><span style="color:' + levelColor + ';">L' + r.team_level + ' ' + levelLabel + '</span></td>';
                html += '<td>' + String(r.created_at || '').substring(0, 16) + '</td>';
                html += '</tr>';
            });
            $('#ucRebateTeamList').append(html);

            var total = (res.data && res.data.total) || 0;
            if ($('#ucRebateTeamList tr').length >= total) $('#ucRebateTeamMore').hide();
            else $('#ucRebateTeamMore').show();
        }, 'json');
    }

    $(document).on('click.ucRebate', '.uc-rebate-team-tab', function () {
        $('.uc-rebate-team-tab').removeClass('active');
        $(this).addClass('active');
        teamLevel = String($(this).data('level') || '');
        loadTeam(true);
    });
    $(document).on('click.ucRebate', '#ucRebateTeamMoreBtn', function () {
        teamPage += 1; loadTeam(false);
    });

    loadLogs(true);
    loadTeam(true);
})();
</script>
