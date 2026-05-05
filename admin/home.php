<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * 后台控制台首页。
 *
 * 流程：
 *   - 鉴权 + 授权状态核对（revalidateCurrent）
 *   - 渲染由 view/home.php 自查统计数据（解耦 controller，无论 PJAX 还是默认入口都能直接渲染）
 *
 * 入口说明：
 *   /admin/home.php         本文件 → PJAX 加载 / 直接访问都走这里
 *   /admin/index.php        后台入口 → 默认 $adminContentView = view/home.php，同样能渲染
 */
adminRequireLogin();
$user = $adminUser;
$siteName = Config::get('sitename', 'EMSHOP');

/**
 * AJAX：拉取首页需要的中心服务数据（版本 / 公告 / 广告 / 代理商联系方式）
 * 走独立 action 是为了页面不阻塞 —— 页面先渲染框架，JS 再异步拉这块
 */
if ((string) Input::get('_action', '') === 'admin_index_data') {
    // 每次都实时拉（不做本地缓存；接口不通则返回空结构让前端展示失败态）
    try {
        $row = LicenseService::currentLicense();
        $emkey = $row ? (string) ($row['license_code'] ?? '') : '';
        $host  = LicenseService::effectiveHost();
        $data = LicenseClient::adminIndex($emkey, $host, EM_VERSION);
    } catch (Throwable $e) {
        $data = ['update' => [], 'notice' => [], 'ad' => [], 'agent' => []];
    }

    // 过滤过期项
    $now = time();
    $notice = [];
    foreach ($data['notice'] ?? [] as $n) {
        $exp = (int) ($n['expire_time'] ?? 0);
        if ($exp > 0 && $exp < $now) continue;
        $notice[] = [
            'content'  => (string) ($n['content'] ?? ''),
            'link_url' => (string) ($n['link_url'] ?? '#'),
        ];
    }
    $ad = [];
    foreach ($data['ad'] ?? [] as $a) {
        $exp = (int) ($a['expire_time'] ?? 0);
        if ($exp > 0 && $exp < $now) continue;
        $ad[] = [
            'content'  => (string) ($a['content'] ?? ''),
            'link_url' => (string) ($a['link_url'] ?? '#'),
            'is_top'   => (int) ($a['is_top']  ?? 0) === 1,
            'is_bold'  => (int) ($a['is_bold'] ?? 0) === 1,
        ];
    }
    usort($ad, static fn($a, $b) => ($b['is_top'] ? 1 : 0) - ($a['is_top'] ? 1 : 0));

    // 整理更新列表：按 version 降序，保留所有高于当前版本的记录，供前端逐条展示
    // 新字段（package_url / package_sha256 / package_size / min_from_version / is_forced）
    // 供在线升级向导使用；未提供这些字段的条目前端会降级为"只显示日志"
    $updates = [];
    foreach ($data['update'] ?? [] as $u) {
        if (!isset($u['version'])) continue;
        $updates[] = [
            'version'          => (string) ($u['version'] ?? ''),
            'content'          => (string) ($u['content'] ?? ''),
            'update_time'      => (string) ($u['update_time'] ?? ''),
            'package_url'      => (string) ($u['package_url'] ?? ''),
            'package_size'     => (int)    ($u['package_size'] ?? 0),
            'package_sha256'   => (string) ($u['package_sha256'] ?? ''),
            'min_from_version' => (string) ($u['min_from_version'] ?? ''),
            'is_forced'        => (int)    ($u['is_forced'] ?? 0),
        ];
    }
    usort($updates, static fn($a, $b) => version_compare($b['version'], $a['version']));

    $agent = $data['agent'] ?? [];

    // 规范化下载源列表：过滤掉空 url，每项保留 {name, url}
    $downloadUrls = [];
    foreach ($agent['download_url'] ?? [] as $d) {
        if (!is_array($d)) continue;
        $url = trim((string) ($d['url'] ?? ''));
        if ($url === '') continue;
        $downloadUrls[] = [
            'name' => (string) ($d['name'] ?? $url),
            'url'  => $url,
        ];
    }

    Response::success('', [
        'agent' => [
            'service_qq'   => (string) ($agent['service_qq']   ?? ''),
            'qq_group'     => (string) ($agent['qq_group']     ?? ''),
            'tg_group_url' => (string) ($agent['tg_group_url'] ?? ''),
            'download_url' => $downloadUrls,
        ],
        'notice' => $notice,
        'ad'     => $ad,
        'updates' => $updates,
        'current_version' => EM_VERSION,
    ]);
}

/**
 * AJAX：销售趋势数据（按日期范围聚合）
 * 前端 dashTrendFilter 点击触发，切换图表数据
 *
 * range 支持：
 *   today / yesterday         —— 按小时聚合（24 点）
 *   week / 7d / month / 30d   —— 按天聚合
 *   6m / year / 12m           —— 按月聚合
 */
if ((string) Input::get('_action', '') === 'trend') {
    $range = (string) Input::get('range', '7d');
    $prefix = Database::prefix();
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
            $endTs   = strtotime($base . ' 23:59:59');
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
            $endTs   = strtotime($today . ' 23:59:59');
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
            $endTs   = strtotime($today . ' 23:59:59');
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
            $endTs   = strtotime($today . ' 23:59:59');
            for ($i = 29; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-{$i} days"));
                $labels[] = date('m-d', strtotime($d));
                $bucketDates[] = $d;
            }
            break;

        case '6m': // 最近半年（6 个月，按月聚合）
            $granularity = 'month';
            $startTs = strtotime('-5 months', strtotime(date('Y-m-01') . ' 00:00:00'));
            $endTs   = strtotime($today . ' 23:59:59');
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
            $endTs   = strtotime($thisYear . '-12-31 23:59:59');
            for ($m = 1; $m <= 12; $m++) {
                $monthKey = sprintf('%d-%02d', $thisYear, $m);
                $labels[] = $monthKey;
                $bucketDates[] = $monthKey;
            }
            break;

        case '12m': // 最近 12 个月
            $granularity = 'month';
            $startTs = strtotime('-11 months', strtotime(date('Y-m-01') . ' 00:00:00'));
            $endTs   = strtotime($today . ' 23:59:59');
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
            $endTs   = strtotime($today . ' 23:59:59');
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
        $rows = Database::query($sql, ['completed', date('Y-m-d H:i:s', $startTs), date('Y-m-d H:i:s', $endTs)]);
        $index = [];
        foreach ($rows as $r) $index[(string) $r['bucket']] = $r;
    } catch (Throwable $e) {
        $index = [];
    }

    $revenueSeries = [];
    $orderSeries   = [];
    foreach ($bucketDates as $key) {
        $hit = $index[$key] ?? null;
        $revenueSeries[] = $hit ? round(((int) $hit['revenue']) / 1000000, 2) : 0;
        $orderSeries[]   = $hit ? (int) $hit['orders'] : 0;
    }

    // 生成副标题说明用的时间范围文案
    $rangeLabels = [
        'today' => '今日', 'yesterday' => '昨日',
        'week'  => '本周', '7d' => '最近 7 天',
        'month' => '本月', '30d' => '最近 30 天',
        '6m'    => '最近半年', 'year' => '本年', '12m' => '最近 1 年',
    ];

    Response::success('', [
        'range'    => $range,
        'range_label' => $rangeLabels[$range] ?? $range,
        'labels'   => $labels,
        'revenue'  => $revenueSeries,
        'orders'   => $orderSeries,
    ]);
}

/**
 * AJAX：测当前官方线路的延迟（ms）
 * 通过一次轻量级中心接口（agentConfig，无需激活码）调用来估算往返耗时
 */
if ((string) Input::get('_action', '') === 'ping_line') {
    $start = microtime(true);
    try {
        LicenseClient::agentConfig();
        $ms = (int) round((microtime(true) - $start) * 1000);
        Response::success('', ['latency_ms' => $ms]);
    } catch (Throwable $e) {
        // 失败用 -1 表示不可达，前端据此展示"无法连接"
        Response::success('', ['latency_ms' => -1, 'error' => $e->getMessage()]);
    }
}

// 进入后台首页时跟服务端核对一次授权状态
LicenseService::revalidateCurrent();

if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/home.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/home.php';
    require __DIR__ . '/index.php';
}
