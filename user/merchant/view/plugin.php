<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$plugins   = isset($plugins)   && is_array($plugins) ? $plugins : [];
$csrfToken = $csrfToken ?? Csrf::token();
?>
<style>
.mp-page { padding: 8px 4px 40px; background: unset; }

.mp-toolbar {
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 16px;
}
.mp-hint {
    margin-left: auto;
    font-size: 13px; color: #6b7280;
}
.mp-hint a { color: #6366f1; font-weight: 500; }

.mp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 14px;
}

.mp-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 16px;
    display: flex; gap: 14px; align-items: flex-start;
    transition: border-color .15s, box-shadow .15s;
}
.mp-card:hover { border-color: #c7d2fe; box-shadow: 0 4px 12px rgba(15,23,42,.06); }

.mp-card__icon {
    flex: 0 0 56px;
    width: 56px; height: 56px;
    border-radius: 12px;
    background: #f3f4f6;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
}
.mp-card__icon img { width: 100%; height: 100%; object-fit: cover; }
.mp-card__icon i   { color: #9ca3af; font-size: 22px; }

.mp-card__body { flex: 1; min-width: 0; }
.mp-card__head {
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 6px;
}
.mp-card__title { font-weight: 600; color: #0f172a; font-size: 14px; }
.mp-card__ver   { color: #94a3b8; font-size: 12px; font-family: Menlo,Consolas,monospace; }
.mp-card__desc {
    color: #6b7280; font-size: 12px; line-height: 1.55;
    margin-bottom: 10px;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden;
}
.mp-card__meta {
    display: flex; gap: 6px; flex-wrap: wrap;
    margin-bottom: 10px;
}

.mp-tag {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; font-size: 11px;
    border-radius: 4px; line-height: 18px;
}
.mp-tag--cat       { background: #f5f3ff; color: #7c3aed; }
.mp-tag--inherited { background: #fef3c7; color: #92400e; }
.mp-tag--enabled   { background: #d1fae5; color: #065f46; }
.mp-tag--disabled  { background: #fee2e2; color: #991b1b; }

.mp-card__actions {
    display: flex; gap: 6px;
}
.mp-card__actions .layui-btn {
    height: 28px; line-height: 20px; padding: 4px 12px;
    font-size: 12px; border-radius: 6px;
}

.mp-empty {
    background: #fff; border: 1px dashed #e5e7eb; border-radius: 12px;
    padding: 60px 20px; text-align: center;
    color: #9ca3af;
}
.mp-empty i { font-size: 36px; margin-bottom: 12px; display: block; }
.mp-empty a { color: #6366f1; font-weight: 500; }
</style>

<div class="mp-page">
    <div class="mp-toolbar">
        <h1 class="admin-page__title" style="margin:0;">插件管理</h1>
    </div>

    <?php if (count($plugins) === 0): ?>
        <div class="mp-empty">
            <i class="fa fa-puzzle-piece"></i>
            <div>你还没有已购买的插件</div>
        </div>
    <?php else: ?>
        <div class="mp-grid">
            <?php foreach ($plugins as $p):
                $isEnabled   = (int) ($p['is_enabled'] ?? 0) === 1;
                $hasSetting  = !empty($p['has_setting']);
                $name        = (string) ($p['name'] ?? '');
                $title       = (string) ($p['title'] ?? $name);
                $version     = (string) ($p['version'] ?? '');
                $category    = (string) ($p['category'] ?? '');
                $description = (string) ($p['description'] ?? '');
                $icon        = (string) ($p['icon'] ?? '');
            ?>
                <div class="mp-card" data-name="<?= htmlspecialchars($name, ENT_QUOTES) ?>">
                    <div class="mp-card__icon">
                        <?php if ($icon !== ''): ?>
                            <img src="<?= htmlspecialchars($icon, ENT_QUOTES) ?>" alt="">
                        <?php else: ?>
                            <i class="fa fa-puzzle-piece"></i>
                        <?php endif; ?>
                    </div>
                    <div class="mp-card__body">
                        <div class="mp-card__head">
                            <span class="mp-card__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></span>
                            <span class="mp-card__ver">v<?= htmlspecialchars($version ?: '1.0.0', ENT_QUOTES) ?></span>
                        </div>
                        <div class="mp-card__desc"><?= htmlspecialchars($description ?: '该插件未配置描述', ENT_QUOTES) ?></div>
                        <div class="mp-card__meta">
                            <?php if ($category !== ''): ?>
                                <span class="mp-tag mp-tag--cat"><i class="fa fa-tag"></i><?= htmlspecialchars($category, ENT_QUOTES) ?></span>
                            <?php endif; ?>
                            <?php if ($isEnabled): ?>
                                <span class="mp-tag mp-tag--enabled"><i class="fa fa-check-circle"></i>已启用</span>
                            <?php else: ?>
                                <span class="mp-tag mp-tag--disabled"><i class="fa fa-pause-circle"></i>已禁用</span>
                            <?php endif; ?>
                        </div>

                        <div class="mp-card__actions">
                            <?php if ($isEnabled): ?>
                                <button class="layui-btn layui-btn-warm" data-act="disable">禁用</button>
                            <?php else: ?>
                                <button class="layui-btn layui-btn-normal" data-act="enable">启用</button>
                            <?php endif; ?>
                            <?php if ($hasSetting): ?>
                                <button class="layui-btn layui-btn-primary" data-act="setting">配置</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
window.MP_CSRF = <?= json_encode($csrfToken) ?>;
layui.use(['layer'], function () {
    var $ = layui.jquery, layer = layui.layer;

    function postAction(action, name, cb) {
        $.post('/user/merchant/plugin.php', {
            _action:    action,
            csrf_token: window.MP_CSRF,
            name:       name
        }).done(function (res) {
            if (res.code === 200) {
                if (res.data && res.data.csrf_token) window.MP_CSRF = res.data.csrf_token;
                cb && cb(true, res);
            } else {
                layer.msg(res.msg || '操作失败', { icon: 2 });
                cb && cb(false, res);
            }
        }).fail(function () {
            layer.msg('网络异常', { icon: 2 });
            cb && cb(false);
        });
    }

    $(document).on('click', '.mp-card__actions [data-act]', function () {
        var $btn = $(this), act = $btn.data('act');
        var name = $btn.closest('.mp-card').data('name');
        if (!name) return;

        if (act === 'enable' || act === 'disable') {
            postAction(act, name, function (ok) {
                if (ok) {
                    layer.msg(act === 'enable' ? '已启用' : '已禁用', { icon: 1 });
                    setTimeout(function () { location.reload(); }, 600);
                }
            });
        } else if (act === 'setting') {
            layer.open({
                type: 2,
                title: '插件设置',
                area: [window.innerWidth >= 760 ? '720px' : '94%', window.innerHeight >= 720 ? '600px' : '88%'],
                shadeClose: true,
                content: '/user/merchant/plugin.php?_popup=1&name=' + encodeURIComponent(name)
            });
        }
    });
});
</script>
