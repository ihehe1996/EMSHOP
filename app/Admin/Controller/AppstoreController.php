<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 应用商店（/admin/appstore）。
 *
 * 浏览、购买、下载、安装 / 更新官方应用、模板与插件。
 *   GET  _action=list        列表（layui table 分页）
 *   GET  _action=app_detail  单个应用详情 + 支付方式（购买弹窗）
 *   POST _action=app_buy / app_pay_qrcode / app_order_status  购买与站内扫码支付
 *   POST _action=install / update                            下载安装与更新
 *
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class AppstoreController extends BaseAdminController
{
    public function index(): void
    {
        $siteName = (string) $this->config->get('sitename', 'EMSHOP');
        $csrfToken = $this->csrf->token();

        // 应用商店的数据拉取必须带官方账号身份：没登录就锁页、不让拉
        $officialAccount  = app(\App\Service\LicenseService::class)->officialAccount();
        $officialLoggedIn = $officialAccount !== [];
        // 分类清单 SSOT 在 \PluginModel::MAIN_PLUGIN_CATEGORIES / MERCHANT_PLUGIN_CATEGORIES，
        // 两个视图直接引用那两个常量渲染 tab（"全部" / "未归类" / "已购买" 硬编码在视图里）。
        // tab 带的是**服务端**的分类 id，服务端改了分类表就要同步改常量。

        // AJAX：为指定应用创建购买订单（/api/open/v1/em/order）
        //
        // 一次拿到订单信息 + 收款页地址 + 可用支付通道，前端弹窗让用户挑通道，
        // 再把通道 id 拼到 pay_url 上（?channel={id}）跳收银台。
        if ($this->request->isPost() && (string) $this->input->post('_action', '') === 'app_buy') {
            if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
                $this->response->error('请求已失效，请刷新页面后重试');
            }
            $appId = (int) $this->input->post('app_id', 0);
            if ($appId <= 0) $this->response->error('应用ID不能为空');

            // 下单必须带授权码（应用列表不传也能看，买东西必须有）；未激活直接拦截，
            // 免得服务端兜底报一个看不懂的错误
            $licenseRow = app(\App\Service\LicenseService::class)->currentLicense();
            $emkey = $licenseRow ? (string) ($licenseRow['license_code'] ?? '') : '';
            if ($emkey === '') {
                $this->response->error('请先激活正版授权');
            }
            $tab = (string) $this->input->post('tab', 'main');
            if (!in_array($tab, ['main', 'merchant'], true)) $tab = 'main';

            // 有绑定过主授权域名就用它，否则回退当前 HTTP_HOST（与 app-list 一致）
            $host = app(\App\Service\LicenseService::class)->effectiveHost();
            try {
                // tab=merchant 仍保留分支，便于后续在服务端做差异化策略
                $data = $tab === 'merchant'
                    ? $this->licenseClient->merchantAppCreateOrder($emkey, $host, $appId)
                    : $this->licenseClient->mainAppCreateOrder($emkey, $host, $appId);

                $orderNo = trim((string) ($data['order_no'] ?? ''));
                if ($orderNo === '') {
                    $this->response->error('订单创建失败：未返回订单号');
                }

                // 收款页与通道 logo 都是授权服务器上的相对路径，这里统一补成绝对地址，
                // 前端拿到即可直接跳转 / 直接渲染
                $data['pay_url'] = $this->absolutizeRemoteUrl((string) ($data['pay_url'] ?? ''));
                if ($data['pay_url'] === '') {
                    $this->response->error('订单创建失败：未返回收款页地址');
                }
                if (!is_array($data['channels'] ?? null)) {
                    $data['channels'] = [];
                }
                foreach ($data['channels'] as &$channel) {
                    if (is_array($channel)) {
                        $channel['logo'] = $this->absolutizeRemoteUrl((string) ($channel['logo'] ?? ''));
                    }
                }
                unset($channel);

                $data['csrf_token'] = $this->csrf->refresh();
                $data['tab']        = $tab;
                $this->response->success('订单已创建', $data);
            } catch (\Throwable $e) {
                $this->response->error($e->getMessage());
            }
        }

        // AJAX：站内扫码支付 —— 取这一单的支付宝当面付二维码
        if ($this->request->isPost() && (string) $this->input->post('_action', '') === 'app_pay_qrcode') {
            if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
                $this->response->error('请求已失效，请刷新页面后重试');
            }
            $orderNo = trim((string) $this->input->post('order_no', ''));
            if ($orderNo === '') {
                $this->response->error('订单号不能为空');
            }
            $channelId = (int) $this->input->post('channel_id', 0);
            try {
                $data = $this->licenseClient->appOrderQrcode($orderNo, $channelId);
                $data['csrf_token'] = $this->csrf->refresh();
                $this->response->success('', $data);
            } catch (\Throwable $e) {
                $this->response->error($e->getMessage());
            }
        }

        // AJAX：站内扫码支付 —— 轮询支付结果（服务端会主动问一次支付宝）
        if ($this->request->isPost() && (string) $this->input->post('_action', '') === 'app_order_status') {
            if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
                $this->response->error('请求已失效，请刷新页面后重试');
            }
            $orderNo = trim((string) $this->input->post('order_no', ''));
            if ($orderNo === '') {
                $this->response->error('订单号不能为空');
            }
            try {
                // 轮询很频繁，不轮换 CSRF（否则前端拿到的 token 会和并发的请求互相打脸）
                $this->response->success('', $this->licenseClient->appOrderStatus($orderNo));
            } catch (\Throwable $e) {
                $this->response->error($e->getMessage());
            }
        }

        // AJAX：按 id 拉取单个应用详情 + 支付方式（/api/app_detail.php），购买弹窗一次拉齐
        if ((string) $this->input->get('_action', '') === 'app_detail') {
            if (!$officialLoggedIn) {
                $this->response->error('请先登录官方账号后再使用应用商店');
            }

            $appId = (int) $this->input->get('id', 0);
            if ($appId <= 0) {
                $this->response->error('应用ID不能为空');
            }
            // 复用列表接口的身份注入逻辑（未激活时 emkey 为空，服务端会回退 VIP 价）
            $licenseRow = app(\App\Service\LicenseService::class)->currentLicense();
            $emkey = $licenseRow ? (string) ($licenseRow['license_code'] ?? '') : '';
            $host  = app(\App\Service\LicenseService::class)->effectiveHost();
            $tab   = (string) $this->input->get('tab', 'main');
            if (!in_array($tab, ['main', 'merchant'], true)) $tab = 'main';
            try {
                // 阶段 8:走拆分后的 mainAppDetail / merchantAppDetail
                $officialToken = app(\App\Service\LicenseService::class)->officialAccountToken();
                $data = $tab === 'merchant'
                    ? $this->licenseClient->merchantAppDetail($appId, $emkey, $host, $officialToken)
                    : $this->licenseClient->mainAppDetail($appId, $emkey, $host, $officialToken);
                $this->response->success('', $data);
            } catch (\Throwable $e) {
                $this->response->error($e->getMessage());
            }
        }

        // AJAX：拉取应用列表（/api/open/v1/em/app-list）。由 layui table 分页驱动；失败返回错误不挂页
        //
        // tab=main     → scope=main,主站自己用,合并 em_plugin/em_template 已装状态
        // tab=merchant → scope=branch,主站为分站采购,合并 em_app_market 已上架状态
        // list_mode=purchased → 走同一条 app-list，带 purchased=1 让上游按已支付订单过滤
        if ((string) $this->input->get('_action', '') === 'list') {
            if (!$officialLoggedIn) {
                $this->response->error('请先登录官方账号后再使用应用商店');
            }

            app(\App\Service\LicenseService::class)->revalidateCurrent(); // 获取最新授权状态

            $officialToken = app(\App\Service\LicenseService::class)->officialAccountToken();
            $accountName   = (string) ($officialAccount['username'] ?? '');
            // 取当前激活码和域名用于服务端计算 my_price（未激活时 emkey 为空，服务端会按 VIP 价返回）
            $licenseRow = app(\App\Service\LicenseService::class)->currentLicense();
            $emkey = $licenseRow ? (string) ($licenseRow['license_code'] ?? '') : '';
            // 有绑定过主授权域名就用它，否则回退当前 HTTP_HOST（适配未激活场景）
            $host  = app(\App\Service\LicenseService::class)->effectiveHost();

            $tab = (string) $this->input->get('tab', 'main');
            if (!in_array($tab, ['main', 'merchant'], true)) $tab = 'main';
            $listMode = (string) $this->input->get('list_mode', '');
            if (!in_array($listMode, ['', 'purchased'], true)) $listMode = '';
            $isPurchasedList = ($listMode === 'purchased');

            // app-list 参数：domain + code + per_page（服务端上限 50）；
            // 「已购买」tab 也走同一条接口，只是多带 purchased=1
            $page  = max(1, (int) $this->input->get('page', 1));
            $limit = max(1, (int) $this->input->get('limit', $isPurchasedList ? 10 : 20));
            $common = [
                'page'    => $page,
                'type'    => (string) $this->input->get('type', ''),
                'keyword' => (string) $this->input->get('keyword', ''),
            ];
            // category_id 必须"客户端传了才带"，不能在服务端补 0：
            //   「全部」  → 客户端不传 → 请求里也不能出现 category_id，否则服务端会当成"只看未归类"
            //   「未归类」→ 客户端传 0  → 原样带上
            // 所以取默认值 null 来区分"没传"和"传了 0"，不要用 (int) 兜底。
            $categoryId = $this->input->get('category_id', null);
            if ($categoryId !== null && $categoryId !== '') {
                $common['category_id'] = (int) $categoryId;
            }
            $params = $common + [
                'per_page' => min(50, $limit),
                'domain'   => $host,
                'code'     => $emkey,
                'source'   => 'admin',
                'account'  => $accountName,
            ];

            /* 已购买：让上游按订单里的已支付应用过滤 */
            if ($isPurchasedList) {
                $params['purchased'] = 1;
            }

            // 已购买列表依赖 emkey；未激活时直接返回空页，避免打断页面
            if ($isPurchasedList && $emkey === '') {
                $this->response->success('', [
                    'license'    => [],
                    'categories' => [],
                    'meta'       => ['page' => $page, 'per_page' => $limit, 'total' => 0, 'last_page' => 0],
                    'data'       => [],
                ]);
            }

            try {
                // scope 由镜像方法注入：mainAppList → 'main' / merchantAppList → 'branch'
                $result = $tab === 'merchant'
                    ? $this->licenseClient->merchantAppList($params, $officialToken)
                    : $this->licenseClient->mainAppList($params, $officialToken);

                // 这次列表是带着本地激活码去问的：服务端明确回未授权 → 清空本地授权
                // （码不存在 / 已作废 / 域名对不上都是这个值，口径与 license/status 一致）
                // 前提是**确实带了码**：没带码时服务端照样回 false，那不是「码失效」，
                // 不能拿它清本地（未激活时 local 就没有码，走不到这个分支）
                if ($emkey !== '' && ($result['license']['authorized'] ?? null) === false) {
                    app(\App\Service\LicenseService::class)->clearLocalAuthorization();
                }

                if ($tab === 'main') {
                    // tab=main:主站自用,合并已装状态
                    //   插件:磁盘有目录 = 已装(version 走 parseHeader);em_plugin 表已废弃,启用列表在 em_config
                    //   模板:仍按 em_template 表(scope='main')
                    $installedPlugins = [];
                    foreach ((new \App\Model\PluginModel())->scanPlugins() as $slug => $info) {
                        $installedPlugins[$slug] = (string) ($info['version'] ?? '');
                    }
                    $installedThemes = [];
                    foreach ((new \App\Model\TemplateModel())->scanTemplates() as $slug => $info) {
                        $installedThemes[$slug] = (string) ($info['version'] ?? '');
                    }
                    // 主站应用商店只标记是否已安装;installed_version 不再注入,is_installed 仅用于显示"已安装"灰按钮
                    foreach ($result['data'] as &$app) {
                        $slug = (string) ($app['name_en'] ?? '');
                        $type = (string) ($app['type'] ?? '');
                        $map = $type === 'template' ? $installedThemes : $installedPlugins;
                        $app['is_installed'] = ($slug !== '' && isset($map[$slug])) ? 1 : 0;
                    }
                    unset($app);
                } else {
                    // tab=merchant:主站为分站采购,应用商店只需要合并是否已上架
                    $marketModel = new \App\Model\AppMarketModel();
                    foreach ($result['data'] as &$app) {
                        $slug = (string) ($app['name_en'] ?? '');
                        $type = (string) ($app['type'] ?? '');
                        $market = $slug !== '' ? $marketModel->findByAppCode($slug, $type) : null;
                        $app['is_in_market'] = $market !== null ? 1 : 0;
                        // 兼容前端 is_installed 字段:tab=merchant 下 is_installed=1 表示"已上架"
                        $app['is_installed'] = $app['is_in_market'];
                    }
                    unset($app);
                }

                $this->response->success('', $result);
            } catch (\Throwable $e) {
                $this->response->error($e->getMessage(), []);
            }
        }

        // 更新应用：下载远端 zip → 覆盖 content/plugin|template/{name}/
        if ($this->request->isPost() && (string) $this->input->post('_action', '') === 'update') {
            try {
                if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
                    $this->response->error('请求已失效，请刷新页面后重试');
                }
                $name = trim((string) $this->input->post('name', ''));
                $type = (string) $this->input->post('type', 'plugin');
                $filePath = trim((string) $this->input->post('file_path', ''));
                $version = trim((string) $this->input->post('version', ''));
                if ($name === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) $this->response->error('非法应用名');
                if (!in_array($type, ['plugin', 'template'], true)) $this->response->error('未知应用类型');
                if ($filePath === '') $this->response->error('EM官方未提供下载地址');
                $targetRoot = $type === 'template' ? EM_ROOT . '/content/template' : EM_ROOT . '/content/plugin';
                $targetDir = $targetRoot . '/' . $name;
                if (!is_dir($targetDir)) $this->response->error('应用尚未安装，无法更新');

                $tmpRoot = EM_ROOT . '/content/uploads/.appstore_tmp';
                $this->requireWritablePath($tmpRoot);
                $this->requireWritablePath($targetDir);

                $downloadUrl = $this->resolveDownloadUrl($filePath);

                if (!is_dir($tmpRoot)) @mkdir($tmpRoot, 0755, true);
                $tmpZip = $tmpRoot . '/zip_u_' . uniqid() . '.zip';
                $dl = $this->downloadPackage($downloadUrl, $tmpZip);
                if (!$dl['ok'] || $dl['http'] !== 200 || filesize($tmpZip) < 16) {
                    @unlink($tmpZip);
                    if($dl['error'] == 'Recv failure: Connection was reset'){
                        $this->response->error('下载失败，服务端线路被墙，请重试~');
                    }
                    $this->response->error('下载失败：' . ($dl['error'] !== '' ? $dl['error'] : 'HTTP ' . $dl['http']));
                }

                if (!class_exists('ZipArchive')) {
                    @unlink($tmpZip);
                    $this->response->error('PHP ZipArchive 扩展未启用');
                }
                $zip = new \ZipArchive();
                if ($zip->open($tmpZip) !== true) {
                    @unlink($tmpZip);
                    $this->response->error('zip 打开失败');
                }
                $extractTmp = $tmpRoot . '/x_u_' . uniqid();
                @mkdir($extractTmp, 0755, true);
                if (!$zip->extractTo($extractTmp)) {
                    $zip->close();
                    @unlink($tmpZip);
                    $this->response->error('解压失败');
                }
                $zip->close();
                @unlink($tmpZip);

                $rmTree = static function (string $path) use (&$rmTree): bool {
                    if (!file_exists($path)) return true;
                    if (!is_dir($path)) return @unlink($path);
                    foreach (scandir($path) ?: [] as $item) {
                        if ($item === '.' || $item === '..') continue;
                        $rmTree($path . DIRECTORY_SEPARATOR . $item);
                    }
                    return @rmdir($path);
                };
                $copyTree = static function (string $from, string $to) use (&$copyTree): bool {
                    if (!is_dir($from)) return @copy($from, $to);
                    if (!is_dir($to) && !@mkdir($to, 0755, true)) return false;
                    foreach (scandir($from) ?: [] as $item) {
                        if ($item === '.' || $item === '..') continue;
                        if (!$copyTree($from . DIRECTORY_SEPARATOR . $item, $to . DIRECTORY_SEPARATOR . $item)) return false;
                    }
                    return true;
                };

                $children = array_values(array_filter(scandir($extractTmp) ?: [], static function ($item): bool { return $item !== '.' && $item !== '..'; }));
                $srcRoot = $extractTmp;
                if (count($children) === 1 && is_dir($extractTmp . '/' . $children[0])) $srcRoot = $extractTmp . '/' . $children[0];

                if (!$rmTree($targetDir)) {
                    $rmTree($extractTmp);
                    $this->response->error('清理旧版本失败，请检查目录权限');
                }
                if (!@mkdir($targetDir, 0755, true)) {
                    $rmTree($extractTmp);
                    $this->response->error('创建目标目录失败，请检查目录权限');
                }
                if (!$copyTree($srcRoot, $targetDir)) {
                    $rmTree($extractTmp);
                    $this->response->error('写入新版本文件失败，请检查目录权限');
                }
                $rmTree($extractTmp);

                if ($type === 'plugin' && !is_file($targetDir . '/' . $name . '.php')) $this->response->error('更新后插件主文件缺失：' . $name . '.php');
                if ($type === 'template' && !is_file($targetDir . '/header.php')) $this->response->error('更新后模板 header.php 缺失');

                // 插件更新回调：用于版本升级后的数据迁移/兼容处理。
                if ($type === 'plugin') {
                    $callbackFile = $targetDir . '/' . $name . '_callback.php';
                    if (is_file($callbackFile)) {
                        include_once $callbackFile;
                        if (function_exists('callback_update')) {
                            call_user_func('callback_update');
                        }
                    }
                }

                $this->response->success('更新完成：' . $name . ($version !== '' ? (' v' . $version) : ''), ['csrf_token' => $this->csrf->refresh()]);
            } catch (\Throwable $e) {
                $this->response->error('更新异常：' . $e->getMessage());
            }
        }

        // 安装应用：下载远端 zip → 解压到 content/plugin|template/{name}/ → 注册到本地
        if ($this->request->isPost() && (string) $this->input->post('_action', '') === 'install') {
            try {
                if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
                    $this->response->error('请求已失效，请刷新页面后重试');
                }

                $name    = trim((string) $this->input->post('name', ''));
                $type    = (string) $this->input->post('type', 'plugin');
                // 应用包地址来自 app-list 的 package_url（可能是相对路径）
                $packageUrl = trim((string) $this->input->post('package_url', ''));
                // tab=main      → 主站自用,装到 content/plugin|template/{name}/(磁盘=装,无 DB 行)
                // tab=merchant  → 主站为分站采购,下载解压共用,注册落 em_app_market(走 MainAppPurchaseService)
                $tab = (string) $this->input->post('tab', 'main');
                if (!in_array($tab, ['main', 'merchant'], true)) $tab = 'main';

                if ($name === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
                    $this->response->error('非法应用名');
                }
                if (!in_array($type, ['plugin', 'template'], true)) {
                    $this->response->error('未知应用类型');
                }

                // 应用要求的主程序最低版本：本地太低就别装，装上也是坏的
                $minVersion = trim((string) $this->input->post('min_version', ''));
                if ($minVersion !== '' && defined('EM_VERSION') && version_compare((string) EM_VERSION, $minVersion, '<')) {
                    $this->response->error('该应用要求 EMSHOP ' . $minVersion . ' 及以上，当前版本 ' . EM_VERSION . ' 过低，请先升级主程序');
                }

                $targetRoot = $type === 'template' ? EM_ROOT . '/content/template' : EM_ROOT . '/content/plugin';
                $targetDir  = $targetRoot . '/' . $name;

                // 本地快捷安装：目录已在磁盘上 → 跳过下载，直接走注册流程。
                //   路线 B 后磁盘文件全站共享一份：别的 scope 先装过就会命中这条分支；
                //   当前 scope 此时只在 DB 里新增一行记录即可，不碰物理文件。
                $localAlreadyExists = is_dir($targetDir);

                // 非本地快捷安装时才要求应用包地址（目录已在磁盘上的走本地快捷安装，不用下载）
                $downloadUrl = '';
                if (!$localAlreadyExists) {
                    $downloadUrl = $this->resolveDownloadUrl($packageUrl);
                }

                // 本地快捷安装：目录已存在，跳过下载/解压，直接进入 REGISTER
                // 非快捷场景才执行下载解压
                if (!$localAlreadyExists) {
                $tmpRoot = EM_ROOT . '/content/uploads/.appstore_tmp';
                $this->requireWritablePath($tmpRoot);
                $this->requireWritablePath($targetDir);

                // 下载 zip 到项目内临时目录（避免 Windows 下跨盘 rename 失败）
                if (!is_dir($tmpRoot)) @mkdir($tmpRoot, 0755, true);
                $tmpZip = $tmpRoot . '/zip_' . uniqid() . '.zip';
                $dl = $this->downloadPackage($downloadUrl, $tmpZip);
                if (!$dl['ok'] || $dl['http'] !== 200 || filesize($tmpZip) < 16) {
                    @unlink($tmpZip);
                    if($dl['error'] == 'Recv failure: Connection was reset'){
                        $this->response->error('下载失败，服务端线路被墙，请重试~');
                    }
                    $this->response->error('下载失败：' . ($dl['error'] !== '' ? $dl['error'] : 'HTTP ' . $dl['http']));
                }

                // 解压：若 zip 顶层只有一个目录（通常等于 name），则把它内部内容铺平到 targetDir
                if (!class_exists('ZipArchive')) {
                    @unlink($tmpZip);
                    $this->response->error('PHP ZipArchive 扩展未启用');
                }
                $zip = new \ZipArchive();
                if ($zip->open($tmpZip) !== true) {
                    @unlink($tmpZip);
                    $this->response->error('zip 打开失败');
                }

                // 探测顶层
                $topLevel = [];
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry = $zip->getNameIndex($i);
                    $slashPos = strpos($entry, '/');
                    $top = $slashPos !== false ? substr($entry, 0, $slashPos) : $entry;
                    if ($top !== '') $topLevel[$top] = true;
                }
                $topList = array_keys($topLevel);

                // 删除临时目录时复用
                $rmTree = static function (string $path) use (&$rmTree): bool {
                    if (!file_exists($path)) return true;
                    if (!is_dir($path)) return @unlink($path);
                    foreach (scandir($path) ?: [] as $item) {
                        if ($item === '.' || $item === '..') continue;
                        $rmTree($path . DIRECTORY_SEPARATOR . $item);
                    }
                    return @rmdir($path);
                };
                if (!is_dir($targetRoot)) @mkdir($targetRoot, 0755, true);

                if (count($topList) === 1 && strpos($topList[0], '.') === false) {
                    // zip 被 wrap 在单一顶层目录下：解压到同盘临时目录再移动
                    $extractTmp = $tmpRoot . '/x_' . uniqid();
                    @mkdir($extractTmp, 0755, true);
                    if (!$zip->extractTo($extractTmp)) {
                        $zip->close();
                        @unlink($tmpZip);
                        $rmTree($extractTmp);
                        $this->response->error('解压失败');
                    }
                    $zip->close();

                    // 优先 rename（同盘）；失败则递归拷贝 + 删除（跨盘兜底）
                    $src = $extractTmp . '/' . $topList[0];
                    $moved = @rename($src, $targetDir);
                    if (!$moved) {
                        $copyTree = static function (string $from, string $to) use (&$copyTree): bool {
                            if (!is_dir($from)) return @copy($from, $to);
                            if (!is_dir($to) && !@mkdir($to, 0755, true)) return false;
                            foreach (scandir($from) ?: [] as $item) {
                                if ($item === '.' || $item === '..') continue;
                                if (!$copyTree($from . DIRECTORY_SEPARATOR . $item, $to . DIRECTORY_SEPARATOR . $item)) return false;
                            }
                            return true;
                        };
                        $moved = $copyTree($src, $targetDir);
                    }
                    $rmTree($extractTmp);
                    if (!$moved) {
                        @unlink($tmpZip);
                        $this->response->error('移动解压文件到目标目录失败（请检查 content/plugin 或 content/template 目录写权限）');
                    }
                } else {
                    @mkdir($targetDir, 0755, true);
                    if (!$zip->extractTo($targetDir)) {
                        $zip->close();
                        @unlink($tmpZip);
                        $this->response->error('解压失败');
                    }
                    $zip->close();
                }
                @unlink($tmpZip);
                } // end if (!$localAlreadyExists) —— 下载/解压块到此结束

                // 注册到本地数据库
                if ($tab === 'merchant') {
                    // 已上架的应用不允许再次安装
                    $existingMarket = (new \App\Model\AppMarketModel())->findByAppCode($name, $type);
                    if ($existingMarket !== null) {
                        $this->response->error('应用已安装，无需重复安装');
                    }

                    // tab=merchant:主站为分站采购 → 落 em_app_market + 写流水(走 MainAppPurchaseService)
                    // 元数据从磁盘 header 读(物理文件主站采购时已经下好/解压好);售价默认等于成本价,主站可在
                    // 分站市场管理页(/admin/merchant_market.php)修改
                    $title = $name; $version = ''; $category = ''; $cover = ''; $description = '';
                    if ($type === 'plugin') {
                        $mainFile = $targetDir . '/' . $name . '.php';
                        $headerInfo = is_file($mainFile) ? (new \App\Model\PluginModel())->parseHeader($mainFile) : null;
                        if ($headerInfo) {
                            $title       = (string) ($headerInfo['title']       ?: $name);
                            $version     = (string) ($headerInfo['version']     ?? '');
                            $category    = (string) ($headerInfo['category']    ?? '');
                            $description = (string) ($headerInfo['description'] ?? '');
                        }
                        if (is_file($targetDir . '/icon.png'))      $cover = '/content/plugin/' . $name . '/icon.png';
                        elseif (is_file($targetDir . '/icon.gif'))  $cover = '/content/plugin/' . $name . '/icon.gif';
                    } else {
                        $scanned = (new \App\Model\TemplateModel())->scanTemplates();
                        if (isset($scanned[$name])) {
                            $tInfo = $scanned[$name];
                            $title       = (string) ($tInfo['title']       ?: $name);
                            $version     = (string) ($tInfo['version']     ?? '');
                            $description = (string) ($tInfo['description'] ?? '');
                            $cover       = (string) ($tInfo['preview']     ?? '');
                        }
                    }

                    $costPerUnit = max(0, (int) $this->input->post('cost_per_unit', 0));
                    $service = new \App\Service\MainAppPurchaseService();
                    $result = $service->registerPurchase([
                        'app_code'        => $name,
                        'type'            => $type,
                        'cost_per_unit'   => $costPerUnit,
                        'remote_app_id'   => ((int) $this->input->post('remote_app_id', 0)) ?: null,
                        'title'           => $title,
                        'version'         => $version,
                        'category'        => $category,
                        'cover'           => $cover,
                        'description'     => $description,
                        // upsert 时 retail_price 仅在"新建 market 行"时生效
                        'retail_price'    => $costPerUnit,
                        'remote_order_no' => (string) $this->input->post('remote_order_no', ''),
                        'remark'          => '主站首次采购',
                    ]);
                    $this->response->success(
                        '已为分站采购上架',
                        ['csrf_token' => $this->csrf->refresh(), 'market_id' => $result['market_id'], 'log_id' => $result['log_id']]
                    );
                } elseif ($type === 'plugin') {
                    // 磁盘 = 装,不再写 DB 行 —— 插件管理页 enable 时会触发 callback_init
                    // 这里只校验磁盘文件就绪
                    if (!is_file($targetDir . '/' . $name . '.php')) {
                        $this->response->error('插件主文件缺失：' . $name . '.php');
                    }
                    $this->response->success(
                        '插件已安装,请到插件管理页启用',
                        ['csrf_token' => $this->csrf->refresh()]
                    );
                } else {
                    // 模板:磁盘 = 装,同样不写 DB —— activate_pc / activate_mobile 时 lazy-create
                    if (!is_file($targetDir . '/header.php')) {
                        $this->response->error('模板 header.php 缺失');
                    }
                    $this->response->success(
                        '模板已安装,请到模板管理页启用',
                        ['csrf_token' => $this->csrf->refresh()]
                    );
                }
            } catch (\Throwable $e) {
                $this->response->error('安装异常：' . $e->getMessage());
            }
        }

        // 主站 / 分站应用商店物理拆分：tab=merchant 走分站视图，默认 / main 走主站视图
        $appstoreTab = (string) $this->input->get('tab', 'main');
        if (!in_array($appstoreTab, ['main', 'merchant'], true)) $appstoreTab = 'main';
        $appstoreView = $appstoreTab === 'merchant'
            ? dirname(__DIR__) . '/View/appstore_merchant.php'
            : dirname(__DIR__) . '/View/appstore.php';

        if ($this->request->isPjax()) {
            require $appstoreView;
            return;
        }

        \App\Admin\AdminShell::render($appstoreView, $this->user(), get_defined_vars());
    }

    /**
     * 断言应用包下载地址合法，否则返回错误响应。
     *
     * 判定在 \DownloadUrlGuard::isAllowed()（纯函数）：不允许 userinfo、host + 端口必须与
     * **授权线路**完全一致（按 \LicenseClient::lines() 逐一比对）。
     *
     * 显式放行 http，便于后续再挂一条 http 备用线路时无需改判定（与升级包下载同一口径）。
     */
    private function assertDownloadUrl(string $url): void
    {
        foreach ($this->licenseClient->lines() as $line) {
            $base = rtrim((string) ($line['url'] ?? ''), '/');
            if ($base !== '' && \DownloadUrlGuard::isAllowed($url, $base, true)) {
                return;
            }
        }
        $this->response->error('下载地址非法（必须与授权服务器同一域名）');
    }

    /**
     * 把服务端给的应用包地址规范成绝对 URL（安装 / 更新两个动作共用）。
     *
     * 相对路径（如 /api/open/v1/em/app/4/download）补上**当前生效线路**的域名 ——
     * 这个地址本来就是那条线路返回的；绝对地址则走白名单校验。
     * 非法时直接输出错误响应并结束请求。
     */
    private function resolveDownloadUrl(string $url): string
    {
        if ($url === '') {
            $this->response->error('EM官方未提供下载地址');
        }
        if (stripos($url, 'http://') === 0 || stripos($url, 'https://') === 0) {
            $this->assertDownloadUrl($url);
            return $url;
        }
        if (!$this->licenseClient->lines()) {
            $this->response->error('未配置授权服务器地址');
        }
        return rtrim($this->licenseClient->currentBaseUrl(), '/') . '/' . ltrim($url, '/');
    }

    /**
     * 下载应用包时提交给授权服务器的身份参数。
     *
     * 应用包地址本身不含任何身份信息，授权服务器据此判断下载方是谁、有没有资格下这个包：
     *   domain  绑定的主授权域名；没绑过则回退当前 HTTP_HOST（与 app-list 同口径）
     *   code    本地激活码；未激活时为空串，服务端会按未授权处理
     *
     * 这两个值一律在服务端自己取，**不接受前端传入** —— code 是密钥，让浏览器决定
     * 等于把授权判定交给客户端。
     *
     * @return array{domain:string,code:string}
     */
    private function downloadAuth(): array
    {
        $licenseRow = app(\App\Service\LicenseService::class)->currentLicense();
        return [
            'domain' => app(\App\Service\LicenseService::class)->effectiveHost(),
            'code'   => $licenseRow ? (string) ($licenseRow['license_code'] ?? '') : '',
        ];
    }

    /**
     * 流式下载应用包到本地文件（安装 / 更新共用）。
     *
     * 身份参数以查询串携带（服务端的下载路由只接受 GET，不接受 POST body），形如
     *   {线路}/api/open/v1/em/app/{id}/download?domain={域名}&code={授权码}
     * 其余与升级包下载同口径：不跟随重定向（否则前面的 host 白名单可被授权主机的 302
     * 绕过），主机名白名单由 $this->resolveDownloadUrl() 在调用前把关。
     *
     * @return array{ok:bool,http:int,error:string}
     */
    private function downloadPackage(string $downloadUrl, string $targetFile): array
    {
        $fp = fopen($targetFile, 'wb');
        if ($fp === false) {
            return ['ok' => false, 'http' => 0, 'error' => '无法创建临时文件'];
        }

        // 包地址本身可能已经带参数（如 ?v=2），所以分隔符要看情况用 ? 还是 &
        $downloadUrl .= (strpos($downloadUrl, '?') === false ? '?' : '&')
            . http_build_query($this->downloadAuth());

        $ch = curl_init($downloadUrl);
        curl_setopt_array($ch, [
            CURLOPT_FILE            => $fp,
            // 不跟随重定向：否则第一次 host 校验就形同虚设 —— 授权主机可以 302 到任意地址，
            // 而 TLS 校验与 host 白名单都不会作用于跳转后的目标
            CURLOPT_FOLLOWLOCATION  => false,
            CURLOPT_TIMEOUT         => 120,
            CURLOPT_CONNECTTIMEOUT  => 10,
            CURLOPT_USERAGENT       => 'emshop-' . EM_VERSION,
            // 证书校验默认开启；自签线路由 config 的 license_insecure_tls=1 显式降级
        ] + $this->licenseClient->tlsOptions());
        $ok = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        return ['ok' => (bool) $ok, 'http' => $httpCode, 'error' => (string) $curlErr];
    }

    /**
     * 把服务端返回的相对地址补成绝对 URL（收款页地址、支付通道 logo 共用）。
     *
     * 绝对地址原样返回；相对地址补**授权服务器**的域名 —— 与服务端 app-list /
     * 应用包地址同一口径。不要用视图里的 APPSTORE_ASSET_HOST，那个是给展示类
     * 资源（封面图）用的。
     */
    private function absolutizeRemoteUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if (stripos($url, 'http://') === 0 || stripos($url, 'https://') === 0) return $url;
        if (!$this->licenseClient->lines()) return $url;
        return rtrim($this->licenseClient->currentBaseUrl(), '/') . '/' . ltrim($url, '/');
    }

    private function requireWritablePath(string $path): void
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        if ($path === '') {
            $this->response->error('网站目录权限不足，请设置网站目录权限为755');
        }
        if (is_file($path)) {
            $path = dirname($path);
            $path = rtrim($path, DIRECTORY_SEPARATOR);
        }

        $probe = $path;
        for ($i = 0; $i < 128 && $probe !== '' && !is_dir($probe); $i++) {
            $parent = dirname($probe);
            if ($parent === $probe) {
                break;
            }
            $probe = $parent;
        }

        if (!is_dir($probe) || !is_writable($probe)) {
            $this->response->error('网站目录权限不足，请设置网站目录权限为755');
        }
    }
}
