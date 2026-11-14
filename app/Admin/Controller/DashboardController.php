<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 后台控制台首页（{入口}/home）。
 *
 * 流程：
 *   - 鉴权；授权状态核对由首页的 admin_index_data 请求异步触发
 *   - 渲染由 View/home.php 自查统计数据（解耦 controller）
 *
 * AJAX：_action=admin_index_data / trend
 * 弹窗：popup=rewrite / server_guide、_popup=server_restart
 *
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class DashboardController extends BaseAdminController
{
    /** 首页路由 */
    private const HOME_PATH = EM_ADMIN_PATH . '/home';

    public function index(): void
    {
        $siteUrl = trim((string) ($this->config->get('site_url') ?? ''));

        if ($siteUrl === '') {
            if ((string) $this->input->get('_action', '') !== '') {
                $this->response->error('请先在基础设置中配置站点地址（site_url）');
            }
            $this->response->redirect(EM_ADMIN_PATH . '/settings?action=base&_need_site_url=1');
        }

        $action = (string) $this->input->get('_action', '');
        if ($action === 'admin_index_data') {
            $this->outputIndexData();
            return;
        }
        if ($action === 'trend') {
            $this->outputTrend();
            return;
        }

        if ((string) $this->input->get('popup', '') === 'rewrite') {
            $this->render('popup/rewrite');
            return;
        }
        if ((string) $this->input->get('popup', '') === 'server_guide') {
            $this->render('popup/server_guide');
            return;
        }
        if ((string) $this->input->get('_popup', '') === 'server_restart') {
            $this->render('popup/server_restart');
            return;
        }

        $this->renderHome();
    }

    /**
     * AJAX：拉取首页需要的官方服务数据（授权状态 / 版本 / 公告 / 广告位 / 代理商联系方式）。
     */
    private function outputIndexData(): void
    {
        // 先释放 session 文件锁：本请求要等上游，不解锁同页面请求会排队
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // 授权状态核对挪到这里异步做
        app(\App\Service\LicenseService::class)->revalidateCurrent();

        // 每次都实时拉（接口不通则返回失败态，latency_ms = -1）
        $start = microtime(true);
        $data = null;
        $error = '';
        try {
            $data = app(\App\Service\LicenseService::class)->fetchBaseData();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        $latency = $data !== null ? (int) round((microtime(true) - $start) * 1000) : -1;

        // 已装插件 / 模板的新版本检测（与插件管理、模板管理页同一套逻辑）。
        // 中心不可达时 AppUpdateService 内部静默降级为空数组。
        $appUpdates = $this->collectAppUpdates();

        // 这里的 authorized / license_type 只作为展示数据回给前端，不用来改本地授权状态
        $this->response->success('', ($data ?? [
            'authorized'       => null,
            'license_type'     => '',
            'license_label'    => '',
            'identity_matched' => false,
            'used_fallback'    => false,
            'update'           => [
                'has_new' => false, 'version' => '', 'force' => false,
                'min_version' => '', 'package_url' => '', 'changelog' => '',
            ],
            'announcements'    => [],
            'ad_slots'         => [],
            'buy_links'        => [],
            'download_links'   => [],
            'contact'          => [],
        ]) + [
            'latency_ms'      => $latency,
            'error'           => $error,
            'current_version' => EM_VERSION,
            'app_updates'     => $appUpdates,
            'update_dry_run'  => \App\Service\UpdateService::isApplyDryRun(),
        ]);
    }

    /**
     * 检测已装插件 / 模板是否有新版本（后台首页弹窗用）。
     *
     * 复用插件管理页 / 模板管理页的 AppUpdateService::checkInstalled()：
     * 只保留确实版本更高、且中心给了下载地址的条目 —— 未授权时中心只回"有新版本"
     * 但 package_url 为空，那种条目点更新必然失败，不能列出来。
     * 中心不可达 / 未激活时静默降级为空，不影响首页其它数据。
     *
     * @return array{plugins: list<array<string,string>>, templates: list<array<string,string>>}
     */
    private function collectAppUpdates(): array
    {
        $out = ['plugins' => [], 'templates' => []];

        // 未激活：中心只会回「未授权、无下载地址」，两次请求纯属浪费
        $licenseRow = app(\App\Service\LicenseService::class)->currentLicense();
        if (!$licenseRow || (string) ($licenseRow['license_code'] ?? '') === '') {
            return $out;
        }

        try {
            $plugins = app(\App\Model\PluginModel::class)->scanWithStatus('main');
            // 首页的检测是"顺便"，单次上游超时压到 8 秒，避免拖垮首页数据
            $updates = app(\App\Service\AppUpdateService::class)->checkInstalled($plugins, 'plugin', 8);
            foreach ($updates as $slug => $row) {
                $current = (string) ($plugins[$slug]['version'] ?? '1.0.0');
                $latest = (string) ($row['version'] ?? '');
                if ($latest === '' || !version_compare($latest, $current, '>')) {
                    continue;
                }
                $out['plugins'][] = [
                    'name'           => (string) $slug,
                    'title'          => (string) ($plugins[$slug]['title'] ?? $slug),
                    'version'        => $current,
                    'latest_version' => $latest,
                    'file_path'      => (string) ($row['file_path'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            // 检测失败不影响首页其它数据
        }

        try {
            $templates = app(\App\Model\TemplateModel::class)->scanTemplates();
            $updates = app(\App\Service\AppUpdateService::class)->checkInstalled($templates, 'template', 8);
            foreach ($updates as $slug => $row) {
                $current = (string) ($templates[$slug]['version'] ?? '1.0.0');
                $latest = (string) ($row['version'] ?? '');
                if ($latest === '' || !version_compare($latest, $current, '>')) {
                    continue;
                }
                $out['templates'][] = [
                    'name'           => (string) $slug,
                    'title'          => (string) ($templates[$slug]['title'] ?? $slug),
                    'version'        => $current,
                    'latest_version' => $latest,
                    'file_path'      => (string) ($row['file_path'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            // 同上
        }

        return $out;
    }

    /**
     * AJAX：销售趋势数据（按日期范围聚合）。
     */
    private function outputTrend(): void
    {
        // 只读本地库：先释放 session 锁
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $range = (string) $this->input->get('range', '7d');
        $prefix = $this->db->prefix();
        $orderTable = $prefix . 'order';

        // 1. 根据 range 确定起止时间 + 聚合粒度 + 时间桶 label 列表
        $now = time();
        $today = date('Y-m-d', $now);

        $granularity = 'day';   // hour / day / month
        $labels = [];           // x 轴标签
        $bucketDates = [];      // 精确到粒度的 key，用于 ORDER GROUP BY 结果回填

        switch ($range) {
            case 'today':
            case 'yesterday':
                $granularity = 'hour';
                $base = $range === 'today' ? $today : date('Y-m-d', strtotime('-1 day'));
                $startTs = strtotime($base . ' 00:00:00');
                $endTs = strtotime($base . ' 23:59:59');
                for ($h = 0; $h < 24; $h++) {
                    $labels[] = sprintf('%02d:00', $h);
                    $bucketDates[] = $base . ' ' . sprintf('%02d', $h);  // YYYY-MM-DD HH
                }
                break;

            case 'week': // 本周（周一 ~ 今日）
                $granularity = 'day';
                $dow = (int) date('N', $now); // 1=周一
                $monday = date('Y-m-d', strtotime('-' . ($dow - 1) . ' days'));
                $startTs = strtotime($monday . ' 00:00:00');
                $endTs = strtotime($today . ' 23:59:59');
                for ($i = 0; $i < $dow; $i++) {
                    $d = date('Y-m-d', strtotime($monday . ' +' . $i . ' days'));
                    $labels[] = date('m-d', strtotime($d));
                    $bucketDates[] = $d;
                }
                break;

            case 'month': // 本月 1 号到今天
                $granularity = 'day';
                $firstDay = date('Y-m-01', $now);
                $startTs = strtotime($firstDay . ' 00:00:00');
                $endTs = strtotime($today . ' 23:59:59');
                $dayOfMonth = (int) date('j', $now);
                for ($i = 0; $i < $dayOfMonth; $i++) {
                    $d = date('Y-m-d', strtotime($firstDay . ' +' . $i . ' days'));
                    $labels[] = date('m-d', strtotime($d));
                    $bucketDates[] = $d;
                }
                break;

            case '30d':
                $granularity = 'day';
                $startTs = strtotime('-29 days', strtotime($today . ' 00:00:00'));
                $endTs = strtotime($today . ' 23:59:59');
                for ($i = 29; $i >= 0; $i--) {
                    $d = date('Y-m-d', strtotime("-{$i} days"));
                    $labels[] = date('m-d', strtotime($d));
                    $bucketDates[] = $d;
                }
                break;

            case '6m': // 最近半年（6 个月，按月聚合）
                $granularity = 'month';
                $startTs = strtotime('-5 months', strtotime(date('Y-m-01') . ' 00:00:00'));
                $endTs = strtotime($today . ' 23:59:59');
                for ($i = 5; $i >= 0; $i--) {
                    $m = date('Y-m', strtotime("-{$i} months"));
                    $labels[] = $m;
                    $bucketDates[] = $m;
                }
                break;

            case 'year': // 本年 Jan-Dec
                $granularity = 'month';
                $thisYear = (int) date('Y', $now);
                $startTs = strtotime($thisYear . '-01-01 00:00:00');
                $endTs = strtotime($thisYear . '-12-31 23:59:59');
                for ($m = 1; $m <= 12; $m++) {
                    $monthKey = sprintf('%d-%02d', $thisYear, $m);
                    $labels[] = $monthKey;
                    $bucketDates[] = $monthKey;
                }
                break;

            case '12m': // 最近 12 个月
                $granularity = 'month';
                $startTs = strtotime('-11 months', strtotime(date('Y-m-01') . ' 00:00:00'));
                $endTs = strtotime($today . ' 23:59:59');
                for ($i = 11; $i >= 0; $i--) {
                    $m = date('Y-m', strtotime("-{$i} months"));
                    $labels[] = $m;
                    $bucketDates[] = $m;
                }
                break;

            case '7d':
            default:
                $range = '7d';
                $granularity = 'day';
                $startTs = strtotime('-6 days', strtotime($today . ' 00:00:00'));
                $endTs = strtotime($today . ' 23:59:59');
                for ($i = 6; $i >= 0; $i--) {
                    $d = date('Y-m-d', strtotime("-{$i} days"));
                    $labels[] = date('m-d', strtotime($d));
                    $bucketDates[] = $d;
                }
                break;
        }

        // 2. 按粒度聚合查询已完成订单
        try {
            if ($granularity === 'hour') {
                $sql = "SELECT DATE_FORMAT(`complete_time`, '%Y-%m-%d %H') AS bucket,
                               COALESCE(SUM(`pay_amount`), 0) AS revenue,
                               COUNT(*) AS orders
                        FROM `{$orderTable}`
                        WHERE `status` = ? AND `complete_time` >= ? AND `complete_time` <= ?
                        GROUP BY bucket";
            } elseif ($granularity === 'month') {
                $sql = "SELECT DATE_FORMAT(`complete_time`, '%Y-%m') AS bucket,
                               COALESCE(SUM(`pay_amount`), 0) AS revenue,
                               COUNT(*) AS orders
                        FROM `{$orderTable}`
                        WHERE `status` = ? AND `complete_time` >= ? AND `complete_time` <= ?
                        GROUP BY bucket";
            } else { // day
                $sql = "SELECT DATE(`complete_time`) AS bucket,
                               COALESCE(SUM(`pay_amount`), 0) AS revenue,
                               COUNT(*) AS orders
                        FROM `{$orderTable}`
                        WHERE `status` = ? AND `complete_time` >= ? AND `complete_time` <= ?
                        GROUP BY bucket";
            }
            $rows = $this->db->query($sql, ['completed', date('Y-m-d H:i:s', $startTs), date('Y-m-d H:i:s', $endTs)]);
            $index = [];
            foreach ($rows as $r) {
                $index[(string) $r['bucket']] = $r;
            }
        } catch (\Throwable $e) {
            $index = [];
        }

        $revenueSeries = [];
        $orderSeries = [];
        foreach ($bucketDates as $key) {
            $hit = $index[$key] ?? null;
            $revenueSeries[] = $hit ? round(((int) $hit['revenue']) / 1000000, 2) : 0;
            $orderSeries[] = $hit ? (int) $hit['orders'] : 0;
        }

        // 生成副标题说明用的时间范围文案
        $rangeLabels = [
            'today' => '今日', 'yesterday' => '昨日',
            'week' => '本周', '7d' => '最近 7 天',
            'month' => '本月', '30d' => '最近 30 天',
            '6m' => '最近半年', 'year' => '本年', '12m' => '最近 1 年',
        ];

        $this->response->success('', [
            'range' => $range,
            'range_label' => $rangeLabels[$range] ?? $range,
            'labels' => $labels,
            'revenue' => $revenueSeries,
            'orders' => $orderSeries,
        ]);
    }

    /**
     * 列表页：Pjax 输出片段，直接访问套后台框架壳。
     */
    private function renderHome(): void
    {
        if ($this->request->isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('home');
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/home.php', $this->user(), get_defined_vars());
    }
}
