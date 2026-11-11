<?php
if (!defined('EM_ROOT')) {
    exit('Access Denied');
}
$availableTemplates = isset($availableTemplates) && is_array($availableTemplates) ? $availableTemplates : [];
$activeThemePc      = $activeThemePc     ?? '';
$activeThemeMobile  = $activeThemeMobile ?? '';
$csrfToken          = $csrfToken         ?? Csrf::token();

/**
 * 模板预览图统一拼成绝对路径(scanTemplates 通常返回相对文件名)。
 */
$previewUrl = static function (string $name, string $preview): string {
    $preview = trim($preview);
    if ($preview === '') return '';
    if (preg_match('/^https?:\/\//i', $preview)) return $preview;
    if (str_starts_with($preview, '/'))         return $preview;
    return '/content/template/' . $name . '/' . ltrim($preview, '/');
};
?>
<style>
.mt-page { padding: 8px 4px 40px; background: unset; }

.mt-toolbar {
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 16px;
}
.mt-hint { margin-left: auto; font-size: 13px; color: #6b7280; }
.mt-hint a { color: #6366f1; font-weight: 500; }

.mt-active-bar {
    display: flex; gap: 14px; flex-wrap: wrap;
    background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 10px;
    padding: 12px 16px; margin-bottom: 16px;
}
.mt-active-bar__item {
    font-size: 13px; color: #0c4a6e;
    display: inline-flex; align-items: center; gap: 6px;
}
.mt-active-bar__item b { color: #075985; font-weight: 600; }
.mt-active-bar__item--empty { color: #94a3b8; }

.mt-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.mt-card {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
    overflow: hidden;
    transition: border-color .15s, box-shadow .15s;
    display: flex; flex-direction: column;
}
.mt-card:hover { border-color: #c7d2fe; box-shadow: 0 4px 12px rgba(15,23,42,.06); }

.mt-card__preview {
    aspect-ratio: 16 / 10;
    background: #f3f4f6;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
}
.mt-card__preview img { width: 100%; height: 100%; object-fit: cover; }
.mt-card__preview i   { color: #cbd5e1; font-size: 36px; }

.mt-card__body { padding: 14px 16px 16px; flex: 1; display: flex; flex-direction: column; }

.mt-card__head {
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 6px;
}
.mt-card__title { font-weight: 600; color: #0f172a; font-size: 14px; flex: 1; }
.mt-card__ver   { color: #94a3b8; font-size: 12px; font-family: Menlo,Consolas,monospace; }

.mt-card__desc {
    color: #6b7280; font-size: 12px; line-height: 1.55;
    margin-bottom: 10px;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden;
    flex: 1;
}

.mt-card__meta {
    display: flex; gap: 6px; flex-wrap: wrap;
    margin-bottom: 12px;
}

.mt-tag {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; font-size: 11px;
    border-radius: 4px; line-height: 18px;
}
.mt-tag--system   { background: #ecfeff; color: #0891b2; }
.mt-tag--owned    { background: #f5f3ff; color: #7c3aed; }
.mt-tag--pc       { background: #d1fae5; color: #065f46; }
.mt-tag--mobile   { background: #fef3c7; color: #92400e; }

.mt-card__actions {
    display: flex; gap: 6px; flex-wrap: wrap;
}
.mt-card__actions .layui-btn {
    height: 28px; line-height: 20px; padding: 4px 12px;
    font-size: 12px; border-radius: 6px;
}

.mt-empty {
    background: #fff; border: 1px dashed #e5e7eb; border-radius: 12px;
    padding: 60px 20px; text-align: center; color: #9ca3af;
}
.mt-empty i { font-size: 36px; margin-bottom: 12px; display: block; }
.mt-empty a { color: #6366f1; font-weight: 500; }
</style>

<div class="mt-page">
    <div class="mt-toolbar">
        <h1 class="admin-page__title" style="margin:0;">模板管理</h1>
    </div>

    <!-- 当前启用提示 -->
    <div class="mt-active-bar">
        <div class="mt-active-bar__item">
            <i class="fa fa-desktop"></i>
            <span>PC 端:</span>
            <?php if ($activeThemePc !== ''): ?>
                <b><?= htmlspecialchars($activeThemePc, ENT_QUOTES) ?></b>
            <?php else: ?>
                <span class="mt-active-bar__item--empty">未启用</span>
            <?php endif; ?>
        </div>
        <div class="mt-active-bar__item">
            <i class="fa fa-mobile"></i>
            <span>手机端:</span>
            <?php if ($activeThemeMobile !== ''): ?>
                <b><?= htmlspecialchars($activeThemeMobile, ENT_QUOTES) ?></b>
            <?php else: ?>
                <span class="mt-active-bar__item--empty">未启用</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (count($availableTemplates) === 0): ?>
        <div class="mt-empty">
            <i class="fa fa-paint-brush"></i>
            <div>你还没有任何可用的模板</div>
        </div>
    <?php else: ?>
        <div class="mt-grid">
            <?php foreach ($availableTemplates as $name => $t):
                $title       = (string) ($t['title'] ?? $name);
                $version     = (string) ($t['version'] ?? '');
                $description = (string) ($t['description'] ?? '');
                $preview     = $previewUrl($name, (string) ($t['preview'] ?? ''));
                $isSystem    = !empty($t['is_system']);
                $isPurchased = !empty($t['is_purchased']);
                $activePc    = !empty($t['is_active_pc']);
                $activeMo    = !empty($t['is_active_mobile']);
                $hasSetting  = !empty($t['has_setting']);
            ?>
                <div class="mt-card" data-name="<?= htmlspecialchars($name, ENT_QUOTES) ?>">
                    <div class="mt-card__preview">
                        <?php if ($preview !== ''): ?>
                            <img src="<?= htmlspecialchars($preview, ENT_QUOTES) ?>" alt="">
                        <?php else: ?>
                            <i class="fa fa-paint-brush"></i>
                        <?php endif; ?>
                    </div>
                    <div class="mt-card__body">
                        <div class="mt-card__head">
                            <span class="mt-card__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></span>
                            <span class="mt-card__ver">v<?= htmlspecialchars($version ?: '1.0.0', ENT_QUOTES) ?></span>
                        </div>
                        <div class="mt-card__desc"><?= htmlspecialchars($description ?: '该模板未配置描述', ENT_QUOTES) ?></div>

                        <div class="mt-card__meta">
                            <?php if ($isSystem): ?>
                                <span class="mt-tag mt-tag--system"><i class="fa fa-shield"></i>系统模板</span>
                            <?php elseif ($isPurchased): ?>
                                <span class="mt-tag mt-tag--owned"><i class="fa fa-check"></i>已购买</span>
                            <?php endif; ?>
                            <?php if ($activePc): ?>
                                <span class="mt-tag mt-tag--pc"><i class="fa fa-desktop"></i>PC 启用中</span>
                            <?php endif; ?>
                            <?php if ($activeMo): ?>
                                <span class="mt-tag mt-tag--mobile"><i class="fa fa-mobile"></i>手机端启用中</span>
                            <?php endif; ?>
                        </div>

                        <div class="mt-card__actions">
                            <?php if ($activePc): ?>
                                <button class="layui-btn layui-btn-primary" data-act="activate_pc">取消 PC</button>
                            <?php else: ?>
                                <button class="layui-btn layui-btn-normal" data-act="activate_pc">启用 PC</button>
                            <?php endif; ?>

                            <?php if ($activeMo): ?>
                                <button class="layui-btn layui-btn-primary" data-act="activate_mobile">取消手机</button>
                            <?php else: ?>
                                <button class="layui-btn" style="background:#f59e0b;color:#fff;" data-act="activate_mobile">启用手机</button>
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
window.MT_CSRF = <?= json_encode($csrfToken) ?>;
layui.use(['layer'], function () {
    var $ = layui.jquery, layer = layui.layer;

    function postAction(action, name, cb) {
        $.post('/user/merchant/template.php', {
            _action:    action,
            csrf_token: window.MT_CSRF,
            name:       name
        }).done(function (res) {
            if (res.code === 200) {
                if (res.data && res.data.csrf_token) window.MT_CSRF = res.data.csrf_token;
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

    $(document).on('click', '.mt-card__actions [data-act]', function () {
        var $btn = $(this), act = $btn.data('act');
        var name = $btn.closest('.mt-card').data('name');
        if (!name) return;

        if (act === 'activate_pc' || act === 'activate_mobile') {
            postAction(act, name, function (ok, res) {
                if (ok) {
                    layer.msg(res.msg || '操作完成', { icon: 1 });
                    setTimeout(function () { location.reload(); }, 600);
                }
            });
        } else if (act === 'setting') {
            layer.open({
                type: 2,
                title: '模板设置',
                area: [window.innerWidth >= 760 ? '720px' : '94%', window.innerHeight >= 720 ? '600px' : '88%'],
                shadeClose: true,
                content: '/user/merchant/template.php?_popup=1&name=' + encodeURIComponent(name)
            });
        }
    });
});
</script>
