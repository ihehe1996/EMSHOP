<style>
    .swg-hero {
        display: flex;
        gap: 14px;
        align-items: flex-start;
        padding: 18px 18px 16px;
        background: linear-gradient(135deg, #eef2ff 0%, #f0f9ff 100%);
        border: 1px solid #dbe7ff;
        border-radius: 14px;
        box-shadow: 0 8px 24px rgba(99, 102, 241, 0.08);
    }
    .swg-hero__icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: #6366f1;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex: 0 0 44px;
        box-shadow: 0 6px 14px rgba(99, 102, 241, 0.3);
    }
    .swg-hero__title {
        margin: 0 0 6px;
        font-size: 20px;
        font-weight: 700;
        color: #111827;
    }
    .swg-hero__desc {
        margin: 0;
        color: #374151;
        font-size: 14px;
    }

    .swg-grid {
        margin-top: 14px;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }
    .swg-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 14px 16px;
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
    }
    .swg-card--full {
        grid-column: 1 / -1;
    }
    .swg-card__title {
        margin: 0 0 10px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 16px;
        font-weight: 700;
        color: #111827;
    }
    .swg-card__title i {
        color: #6366f1;
    }

    .swg-steps {
        margin: 0;
        padding-left: 20px;
        color: #374151;
        font-size: 14px;
    }
    .swg-steps li {
        margin: 0 0 10px;
    }
    .swg-steps li:last-child {
        margin-bottom: 0;
    }

    .swg-cmd {
        margin-top: 8px;
        padding: 11px 12px;
        background: #0f172a;
        border-radius: 10px;
        color: #e2e8f0;
        font-family: "Consolas", "Courier New", monospace;
        font-size: 13px;
        overflow-x: auto;
        white-space: nowrap;
    }
    .swg-badges {
        margin-top: 10px;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .swg-badge {
        font-size: 12px;
        color: #4338ca;
        background: #eef2ff;
        border: 1px solid #dbeafe;
        border-radius: 999px;
        padding: 4px 10px;
    }
    .swg-warn {
        margin: 0;
        color: #374151;
        font-size: 14px;
    }
    .swg-warn strong {
        color: #111827;
    }
    .swg-tip {
        margin-top: 10px;
        border-left: 3px solid #22c55e;
        background: #f0fdf4;
        color: #166534;
        padding: 10px 12px;
        border-radius: 8px;
        font-size: 13px;
    }
    .swg-foot {
        margin-top: 14px;
        padding: 11px 12px;
        border-radius: 10px;
        background: #fff;
        border: 1px dashed #cbd5e1;
        color: #475569;
        font-size: 13px;
    }

    @media (max-width: 860px) {
        .swg-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<section class="swg-hero">
    <div class="swg-hero__icon"><i class="fa fa-exclamation-triangle"></i></div>
    <div>
        <h1 class="swg-hero__title">检测到 Swoole 服务未启动</h1>
        <p class="swg-hero__desc">
            本系统的自动发货与定时任务完全依赖 Swoole 常驻进程。为保证订单处理与计划任务正常执行，请先完成 Swoole 启动配置。
        </p>
    </div>
</section>

<section class="swg-grid">
    <article class="swg-card">
        <h2 class="swg-card__title"><i class="fa fa-cogs"></i> 宝塔面板快速配置（SuperVisord）</h2>
        <ol class="swg-steps">
            <li>进入宝塔面板，在应用商店搜索并安装 <strong>SuperVisord</strong>（进程守护管理器）。</li>
            <li>安装完成后，打开 SuperVisord，点击「设置」再点击「添加守护进程」。</li>
            <li>在添加窗口中填写：</li>
        </ol>
        <ul class="swg-steps">
            <li><strong>名称</strong>：可任意填写（例如：EMSHOP-Swoole）。</li>
            <li><strong>启动用户</strong>：选择 <strong>root</strong>。</li>
            <li><strong>运行目录</strong>：选择当前网站根目录。</li>
            <li><strong>启动命令</strong>：执行以下命令。</li>
        </ul>
        <div class="swg-cmd">php swoole/server.php start</div>
    </article>

    <article class="swg-card">
        <h2 class="swg-card__title"><i class="fa fa-bug"></i> 常见无法启动原因排查</h2>
        <p class="swg-warn">
            <strong>重点：</strong>启动命令使用的是服务器 <strong>CLI 命令行 PHP</strong>，并非网站设置中的 PHP 版本。
        </p>
        <ol class="swg-steps">
            <li>在宝塔「网站」页面，点击「添加站点」按钮旁边的「高级设置」。</li>
            <li>确认命令行 PHP 版本为 <strong>8.0 及以上</strong>。</li>
            <li>为该命令行 PHP 安装 <strong>Swoole 4.x</strong> 扩展（其他版本不兼容）。</li>
            <li>若同一台服务器部署了多个 EMSHOP，可能因 <strong>Swoole 端口占用</strong> 导致启动失败。请前往 EMSHOP 后台「基础设置」中修改 <strong>Swoole API 地址</strong> 的端口，确保每个站点端口唯一。</li>
        </ol>
        <div class="swg-badges">
            <span class="swg-badge">CLI PHP >= 8.0</span>
            <span class="swg-badge">Swoole 4.x</span>
            <span class="swg-badge">网站根目录执行</span>
        </div>
    </article>

    <article class="swg-card swg-card--full">
        <h2 class="swg-card__title"><i class="fa fa-terminal"></i> 指定 PHP 版本启动（推荐）</h2>
        <p class="swg-warn">如果不想更改默认的 <code>php</code> 指令版本，可以在守护进程命令中直接指定版本：</p>
        <div class="swg-cmd">php82 swoole/server.php start</div>
    </article>
</section>

<div class="swg-foot">
    完成配置后，返回首页点击「Swoole 监控」卡片中的刷新按钮，确认状态显示为「启动中」即可。
</div>
