<?php

declare(strict_types=1);

require_once __DIR__ . '/global.php';

/**
 * 应用商店控制器。
 *
 * 提供官方应用/模板/插件的浏览、下载与安装功能。
 */
adminRequireLogin();
$user = $adminUser;
$siteName = Config::get('sitename', 'EMSHOP');
$csrfToken = Csrf::token();





// 应用商店分类清单 SSOT 在 PluginModel 常量;在此引用确保跨页面口径一致
// 改清单只需改 PluginModel::MAIN_PLUGIN_CATEGORIES / MERCHANT_PLUGIN_CATEGORIES
$main_plugin_category     = PluginModel::MAIN_PLUGIN_CATEGORIES;
$merchant_plugin_category = PluginModel::MERCHANT_PLUGIN_CATEGORIES;


// 分类清单不再走服务端 —— 由 view 直接渲染(基于 PluginModel::MAIN_PLUGIN_CATEGORIES 常量 + 硬编码 "全部" / "模板主题")

// AJAX：拉取已启用的支付方式（/api/pay_methods.php）。兼容旧购买弹窗
if ((string) Input::get('_action', '') === 'pay_methods') {
    try {
        Response::success('', ['list' => LicenseClient::payMethods()]);
    } catch (Throwable $e) {
        Response::error($e->getMessage(), ['list' => []]);
    }
}

// AJAX：为指定应用创建订单（/api/app_create_order.php）
// 当前重构阶段仅传 emkey + app_id；前端先提示“订单已创建”
if (Request::isPost() && (string) Input::post('_action', '') === 'app_buy') {
    if (!Csrf::validate((string) Input::post('csrf_token', ''))) {
        Response::error('请求已失效，请刷新页面后重试');
    }
    $appId = (int) Input::post('app_id', 0);
    if ($appId <= 0) Response::error('应用ID不能为空');

    // emkey 从当前激活状态取；未激活直接拦截，防止服务端兜底报错
    $licenseRow = LicenseService::currentLicense();
    $emkey = $licenseRow ? (string) ($licenseRow['license_code'] ?? '') : '';
    if ($emkey === '') {
        Response::error('请先激活正版授权');
    }
    $tab = (string) Input::post('tab', 'main');
    if (!in_array($tab, ['main', 'merchant'], true)) $tab = 'main';
    try {
        // tab=merchant 仍保留分支，便于后续在服务端做差异化策略
        $data = $tab === 'merchant'
            ? LicenseClient::merchantAppCreateOrder($emkey, $appId)
            : LicenseClient::mainAppCreateOrder($emkey, $appId);
        $outTradeNo = trim((string) ($data['out_trade_no'] ?? ''));
        if ($outTradeNo === '') {
            Response::error('订单创建失败：未返回订单号');
        }
        Response::success('订单已创建', [
            'out_trade_no' => $outTradeNo,
            'amount'       => (string) ($data['amount'] ?? ''),
            'subject'      => (string) ($data['subject'] ?? ''),
            'payment'      => (string) ($data['payment'] ?? ''),
            'csrf_token'   => Csrf::refresh(),
            'tab'          => $tab,
        ]);
    } catch (Throwable $e) {
        Response::error($e->getMessage());
    }
}

// AJAX：按 id 拉取单个应用详情 + 支付方式（/api/app_detail.php），购买弹窗一次拉齐
if ((string) Input::get('_action', '') === 'app_detail') {
    $appId = (int) Input::get('id', 0);
    if ($appId <= 0) {
        Response::error('应用ID不能为空');
    }
    // 复用列表接口的身份注入逻辑（未激活时 emkey 为空，服务端会回退 VIP 价）
    $licenseRow = LicenseService::currentLicense();
    $emkey = $licenseRow ? (string) ($licenseRow['license_code'] ?? '') : '';
    $host  = LicenseService::effectiveHost();
    $tab   = (string) Input::get('tab', 'main');
    if (!in_array($tab, ['main', 'merchant'], true)) $tab = 'main';
    try {
        // 阶段 8:走拆分后的 mainAppDetail / merchantAppDetail
        $data = $tab === 'merchant'
            ? LicenseClient::merchantAppDetail($appId, $emkey, $host)
            : LicenseClient::mainAppDetail($appId, $emkey, $host);
        Response::success('', $data);
    } catch (Throwable $e) {
        Response::error($e->getMessage());
    }
}

// AJAX：拉取应用列表（/api/app_store.php）。由 layui table 分页驱动；失败返回空列表不挂页
//
// tab=main     → 拉服务端"主站货架"(scope=1),主站自己用,合并 em_plugin/em_template 已装状态
// tab=merchant → 拉服务端"分站货架"(scope=2),主站为分站采购,合并 em_app_market 已上架状态
// list_mode=purchased → 拉服务端"已购买应用"(app_purchased_list.php)，携带 emkey + scope
if ((string) Input::get('_action', '') === 'list') {
    LicenseService::revalidateCurrent(); // 获取最新授权状态
    // 取当前激活码和域名用于服务端计算 my_price（未激活时 emkey 为空，服务端会按 VIP 价返回）
    $licenseRow = LicenseService::currentLicense();
    $emkey = $licenseRow ? (string) ($licenseRow['license_code'] ?? '') : '';
    // 有绑定过主授权域名就用它，否则回退当前 HTTP_HOST（适配未激活场景）
    $host  = LicenseService::effectiveHost();

    $tab = (string) Input::get('tab', 'main');
    if (!in_array($tab, ['main', 'merchant'], true)) $tab = 'main';
    $listMode = (string) Input::get('list_mode', '');
    if (!in_array($listMode, ['', 'purchased'], true)) $listMode = '';


    $params = [
        'page'        => max(1, (int) Input::get('page', 1)),
        'pageNum'     => min(100, max(1, (int) Input::get('limit', 10))),
        'type'        => (string) Input::get('type', ''),
        'category_id' => (int) Input::get('category_id', 0),
        'keyword'     => (string) Input::get('keyword', ''),
        'emkey'       => $emkey,
        'host'        => $host,
    ];
    // 已购买列表依赖 emkey；未激活时直接返回空列表，避免打断页面
    if ($listMode === 'purchased' && $emkey === '') {
        Response::success('', [
            'list'    => [],
            'count'   => 0,
            'page'    => (int) $params['page'],
            'pageNum' => (int) $params['pageNum'],
        ]);
    }
    try {
        $isPurchasedList = $listMode === 'purchased';
        if ($isPurchasedList) {
            $result = $tab === 'merchant'
                ? LicenseClient::merchantAppPurchasedList($params)
                : LicenseClient::mainAppPurchasedList($params);
        } else {
            // 阶段 8:走拆分后的 mainAppList / merchantAppList(scope/audience/member_code 由方法内部固定)
            $result = $tab === 'merchant' ? LicenseClient::merchantAppList($params) : LicenseClient::mainAppList($params);
        }
        if ($tab === 'main') {
            // tab=main:主站自用,合并已装状态
            //   插件:磁盘有目录 = 已装(version 走 parseHeader);em_plugin 表已废弃,启用列表在 em_config
            //   模板:仍按 em_template 表(scope='main')
            $installedPlugins = [];
            foreach ((new PluginModel())->scanPlugins() as $slug => $info) {
                $installedPlugins[$slug] = (string) ($info['version'] ?? '');
            }
            $installedThemes = [];
            foreach ((new TemplateModel())->scanTemplates() as $slug => $info) {
                $installedThemes[$slug] = (string) ($info['version'] ?? '');
            }
            // 主站应用商店只标记是否已安装;installed_version 不再注入,is_installed 仅用于显示"已安装"灰按钮
            foreach ($result['list'] as &$app) {
                $slug = (string) ($app['name_en'] ?? '');
                $type = (string) ($app['type'] ?? '');
                $map = $type === 'template' ? $installedThemes : $installedPlugins;
                $app['is_installed'] = ($slug !== '' && isset($map[$slug])) ? 1 : 0;
            }
            unset($app);
        } else {
            // tab=merchant:主站为分站采购,应用商店只需要合并是否已上架
            $marketModel = new AppMarketModel();
            foreach ($result['list'] as &$app) {
                $slug = (string) ($app['name_en'] ?? '');
                $type = (string) ($app['type'] ?? '');
                $market = $slug !== '' ? $marketModel->findByAppCode($slug, $type) : null;
                $app['is_in_market'] = $market !== null ? 1 : 0;
                // 兼容前端 is_installed 字段:tab=merchant 下 is_installed=1 表示"已上架"
                $app['is_installed'] = $app['is_in_market'];
            }
            unset($app);
        }

        Response::success('', $result);
    } catch (Throwable $e) {
        Response::error($e->getMessage(), []);
    }
}

// 更新应用：下载远端 zip → 覆盖 content/plugin|template/{name}/
if (Request::isPost() && (string) Input::post('_action', '') === 'update') {
    try {
        if (!Csrf::validate((string) Input::post('csrf_token', ''))) {
            Response::error('请求已失效，请刷新页面后重试');
        }
        $name = trim((string) Input::post('name', ''));
        $type = (string) Input::post('type', 'plugin');
        $filePath = trim((string) Input::post('file_path', ''));
        $version = trim((string) Input::post('version', ''));
        if ($name === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) Response::error('非法应用名');
        if (!in_array($type, ['plugin', 'template'], true)) Response::error('未知应用类型');
        if ($filePath === '') Response::error('缺少下载地址');
        $targetRoot = $type === 'template' ? EM_ROOT . '/content/template' : EM_ROOT . '/content/plugin';
        $targetDir = $targetRoot . '/' . $name;
        if (!is_dir($targetDir)) Response::error('应用尚未安装，无法更新');

        $lines = LicenseClient::lines();
        if (!$lines) Response::error('未配置授权服务器地址');
        $baseHost = rtrim((string) $lines[0]['url'], '/');
        $downloadUrl = '';
        if (stripos($filePath, 'http://') === 0 || stripos($filePath, 'https://') === 0) {
            if (strpos($filePath, $baseHost) !== 0) Response::error('下载地址非法（host 不匹配）');
            $downloadUrl = $filePath;
        } else {
            $downloadUrl = $baseHost . '/' . ltrim($filePath, '/');
        }

        $tmpRoot = EM_ROOT . '/content/uploads/.appstore_tmp';
        if (!is_dir($tmpRoot)) @mkdir($tmpRoot, 0755, true);
        $tmpZip = $tmpRoot . '/zip_u_' . uniqid() . '.zip';
        $fp = fopen($tmpZip, 'wb');
        $ch = curl_init($downloadUrl);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'emshop-' . EM_VERSION,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $ok = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        if (!$ok || $httpCode !== 200 || filesize($tmpZip) < 16) {
            @unlink($tmpZip);
            Response::error('下载失败：' . ($curlErr !== '' ? $curlErr : 'HTTP ' . $httpCode));
        }

        if (!class_exists('ZipArchive')) {
            @unlink($tmpZip);
            Response::error('PHP ZipArchive 扩展未启用');
        }
        $zip = new ZipArchive();
        if ($zip->open($tmpZip) !== true) {
            @unlink($tmpZip);
            Response::error('zip 打开失败');
        }
        $extractTmp = $tmpRoot . '/x_u_' . uniqid();
        @mkdir($extractTmp, 0755, true);
        if (!$zip->extractTo($extractTmp)) {
            $zip->close();
            @unlink($tmpZip);
            Response::error('解压失败');
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
            Response::error('清理旧版本失败，请检查目录权限');
        }
        if (!@mkdir($targetDir, 0755, true)) {
            $rmTree($extractTmp);
            Response::error('创建目标目录失败，请检查目录权限');
        }
        if (!$copyTree($srcRoot, $targetDir)) {
            $rmTree($extractTmp);
            Response::error('写入新版本文件失败，请检查目录权限');
        }
        $rmTree($extractTmp);

        if ($type === 'plugin' && !is_file($targetDir . '/' . $name . '.php')) Response::error('更新后插件主文件缺失：' . $name . '.php');
        if ($type === 'template' && !is_file($targetDir . '/header.php')) Response::error('更新后模板 header.php 缺失');
        Response::success('更新完成：' . $name . ($version !== '' ? (' v' . $version) : ''), ['csrf_token' => Csrf::refresh()]);
    } catch (Throwable $e) {
        Response::error('更新异常：' . $e->getMessage());
    }
}

// 安装应用：下载远端 zip → 解压到 content/plugin|template/{name}/ → 注册到本地
if (Request::isPost() && (string) Input::post('_action', '') === 'install') {
    try {
        if (!Csrf::validate((string) Input::post('csrf_token', ''))) {
            Response::error('请求已失效，请刷新页面后重试');
        }

        $name    = trim((string) Input::post('name', ''));
        $type    = (string) Input::post('type', 'plugin');
        $filePath = trim((string) Input::post('file_path', ''));
        // tab=main      → 主站自用,装到 content/plugin|template/{name}/(磁盘=装,无 DB 行)
        // tab=merchant  → 主站为分站采购,下载解压共用,注册落 em_app_market(走 MainAppPurchaseService)
        $tab = (string) Input::post('tab', 'main');
        if (!in_array($tab, ['main', 'merchant'], true)) $tab = 'main';

        if ($name === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
            Response::error('非法应用名');
        }
        if (!in_array($type, ['plugin', 'template'], true)) {
            Response::error('未知应用类型');
        }

        $targetRoot = $type === 'template' ? EM_ROOT . '/content/template' : EM_ROOT . '/content/plugin';
        $targetDir  = $targetRoot . '/' . $name;

        // 本地快捷安装：目录已在磁盘上 → 跳过下载，直接走注册流程。
        //   路线 B 后磁盘文件全站共享一份：别的 scope 先装过就会命中这条分支；
        //   当前 scope 此时只在 DB 里新增一行记录即可，不碰物理文件。
        $localAlreadyExists = is_dir($targetDir);

        // 非本地快捷安装时才要求 file_path
        if (!$localAlreadyExists && $filePath === '') {
            Response::error('缺少下载地址');
        }

        // 统一把 file_path 规范为绝对 URL；始终基于 license_urls[0]，只允许这个主 host
        $downloadUrl = '';
        if (!$localAlreadyExists) {
            $lines = LicenseClient::lines();
            if (!$lines) Response::error('未配置授权服务器地址');
            $baseHost = rtrim($lines[0]['url'], '/');
            if (stripos($filePath, 'http://') === 0 || stripos($filePath, 'https://') === 0) {
                if (strpos($filePath, $baseHost) !== 0) {
                    Response::error('下载地址非法（host 不匹配）');
                }
                $downloadUrl = $filePath;
            } else {
                $downloadUrl = $baseHost . '/' . ltrim($filePath, '/');
            }
        }

        // 本地快捷安装：目录已存在，跳过下载/解压，直接进入 REGISTER
        // 非快捷场景才执行下载解压
        if (!$localAlreadyExists) {
        // 下载 zip 到项目内临时目录（避免 Windows 下跨盘 rename 失败）
        $tmpRoot = EM_ROOT . '/content/uploads/.appstore_tmp';
        if (!is_dir($tmpRoot)) @mkdir($tmpRoot, 0755, true);
        $tmpZip = $tmpRoot . '/zip_' . uniqid() . '.zip';
        $fp = fopen($tmpZip, 'wb');
        $ch = curl_init($downloadUrl);
        curl_setopt_array($ch, [
            CURLOPT_FILE            => $fp,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_TIMEOUT         => 120,
            CURLOPT_CONNECTTIMEOUT  => 10,
            CURLOPT_USERAGENT       => 'emshop-' . EM_VERSION,
            // 修复 SSL 错误 60
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $ok = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if (!$ok || $httpCode !== 200 || filesize($tmpZip) < 16) {
            @unlink($tmpZip);
            Response::error('下载失败：' . ($curlErr !== '' ? $curlErr : 'HTTP ' . $httpCode));
        }

        // 解压：若 zip 顶层只有一个目录（通常等于 name），则把它内部内容铺平到 targetDir
        if (!class_exists('ZipArchive')) {
            @unlink($tmpZip);
            Response::error('PHP ZipArchive 扩展未启用');
        }
        $zip = new ZipArchive();
        if ($zip->open($tmpZip) !== true) {
            @unlink($tmpZip);
            Response::error('zip 打开失败');
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
                Response::error('解压失败');
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
                Response::error('移动解压文件到目标目录失败（请检查 content/plugin 或 content/template 目录写权限）');
            }
        } else {
            @mkdir($targetDir, 0755, true);
            if (!$zip->extractTo($targetDir)) {
                $zip->close();
                @unlink($tmpZip);
                Response::error('解压失败');
            }
            $zip->close();
        }
        @unlink($tmpZip);
        } // end if (!$localAlreadyExists) —— 下载/解压块到此结束

        // 注册到本地数据库
        if ($tab === 'merchant') {
            // 已上架的应用不允许再次安装
            $existingMarket = (new AppMarketModel())->findByAppCode($name, $type);
            if ($existingMarket !== null) {
                Response::error('应用已安装，无需重复安装');
            }

            // tab=merchant:主站为分站采购 → 落 em_app_market + 写流水(走 MainAppPurchaseService)
            // 元数据从磁盘 header 读(物理文件主站采购时已经下好/解压好);售价默认等于成本价,主站可在
            // 分站市场管理页(/admin/merchant_market.php)修改
            $title = $name; $version = ''; $category = ''; $cover = ''; $description = '';
            if ($type === 'plugin') {
                $mainFile = $targetDir . '/' . $name . '.php';
                $headerInfo = is_file($mainFile) ? (new PluginModel())->parseHeader($mainFile) : null;
                if ($headerInfo) {
                    $title       = (string) ($headerInfo['title']       ?: $name);
                    $version     = (string) ($headerInfo['version']     ?? '');
                    $category    = (string) ($headerInfo['category']    ?? '');
                    $description = (string) ($headerInfo['description'] ?? '');
                }
                if (is_file($targetDir . '/icon.png'))      $cover = '/content/plugin/' . $name . '/icon.png';
                elseif (is_file($targetDir . '/icon.gif'))  $cover = '/content/plugin/' . $name . '/icon.gif';
            } else {
                $scanned = (new TemplateModel())->scanTemplates();
                if (isset($scanned[$name])) {
                    $tInfo = $scanned[$name];
                    $title       = (string) ($tInfo['title']       ?: $name);
                    $version     = (string) ($tInfo['version']     ?? '');
                    $description = (string) ($tInfo['description'] ?? '');
                    $cover       = (string) ($tInfo['preview']     ?? '');
                }
            }

            $costPerUnit = max(0, (int) Input::post('cost_per_unit', 0));
            $service = new MainAppPurchaseService();
            $result = $service->registerPurchase([
                'app_code'        => $name,
                'type'            => $type,
                'cost_per_unit'   => $costPerUnit,
                'remote_app_id'   => ((int) Input::post('remote_app_id', 0)) ?: null,
                'title'           => $title,
                'version'         => $version,
                'category'        => $category,
                'cover'           => $cover,
                'description'     => $description,
                // upsert 时 retail_price 仅在"新建 market 行"时生效
                'retail_price'    => $costPerUnit,
                'remote_order_no' => (string) Input::post('remote_order_no', ''),
                'remark'          => '主站首次采购',
            ]);
            Response::success(
                '已为分站采购上架',
                ['csrf_token' => Csrf::refresh(), 'market_id' => $result['market_id'], 'log_id' => $result['log_id']]
            );
        } elseif ($type === 'plugin') {
            // 磁盘 = 装,不再写 DB 行 —— enable 时会 lazy-create + 触发 callback_init
            // 这里只校验磁盘文件就绪
            if (!is_file($targetDir . '/' . $name . '.php')) {
                Response::error('插件主文件缺失：' . $name . '.php');
            }
            Response::success(
                '插件已安装,请到插件管理页启用',
                ['csrf_token' => Csrf::refresh()]
            );
        } else {
            // 模板:磁盘 = 装,同样不写 DB —— activate_pc / activate_mobile 时 lazy-create
            if (!is_file($targetDir . '/header.php')) {
                Response::error('模板 header.php 缺失');
            }
            Response::success(
                '模板已安装,请到模板管理页启用',
                ['csrf_token' => Csrf::refresh()]
            );
        }
    } catch (Throwable $e) {
        Response::error('安装异常：' . $e->getMessage());
    }
}

// 主站 / 分站 应用商店物理拆分:tab=merchant 走分站 view,默认/main 走主站 view
$appstoreTab = (string) Input::get('tab', 'main');
if (!in_array($appstoreTab, ['main', 'merchant'], true)) $appstoreTab = 'main';
$appstoreView = $appstoreTab === 'merchant'
    ? __DIR__ . '/view/appstore_merchant.php'
    : __DIR__ . '/view/appstore.php';

if (Request::isPjax()) {
    include $appstoreView;
} else {
    $adminContentView = $appstoreView;
    require __DIR__ . '/index.php';
}
