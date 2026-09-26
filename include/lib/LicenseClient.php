<?php

declare(strict_types=1);

/**
 * 授权服务器 HTTP 客户端。
 *
 * 职责：把对授权服务器的所有网络调用集中在一处，业务层（LicenseService）只拿结构化结果。
 * 服务端地址列表来自 config.php 的 'license_urls'（本地可在 .env 用 LICENSE_URL_{N} 整体覆盖）。
 *
 * 约定（详见 a 系统文档/应用商店方案.md §8）：
 *   所有响应 JSON 统一格式：{ ok: bool, data?: ..., code?: string, msg?: string }
 *   成功拿 data；失败抛 RuntimeException（message = 服务端 msg 或默认文案）
 *
 * 超时：短请求 10s；批量校验 30s。
 * 网络错误全部统一 throw RuntimeException('授权服务器不可达')，调用方可据此降级（比如本地保守保留状态）。
 */
final class LicenseClient
{
    /**
     * 授权激活：把激活码绑到域名上（POST /api/open/v1/em/license/bind）。
     *
     * 请求参数（与解绑一致）：
     *   code    激活码
     *   domain  要绑定的域名
     *
     * 服务端响应 data：
     *   bound              true=本次绑定成功；false=这个码本来就绑在这个域名上，**同样算成功**
     *   code_masked        打码后的激活码（仅供展示，**不能**当 code 回传）
     *   domain             归一之后的授权域名
     *   license_type       档位数字：1=VIP / 2=SVIP / 3=至尊
     *   license_type_label 档位文本
     *
     * 已绑了别的域名 / 码不存在 / 已作废 → HTTP 400 + {code:400, message}，
     * 由 postForm() 抛出（不重试），message 会原样呈现给用户。
     *
     * 重试：绑定是幂等的（重复绑同一个域名返回 bound=false + 成功），
     * 所以和 unbind 一样开 3 次尝试，没有重复提交的副作用。
     *
     * @return array{bound?:bool, code_masked?:string, domain?:string, license_type?:string, license_type_label?:string}
     * @throws RuntimeException
     */
    public static function bind(string $code, string $domain): array
    {
        return self::postForm('api/open/v1/em/license/bind', [
            'code'   => $code,
            'domain' => $domain,
        ], 8, 3);
    }

    /**
     * 生成购买跳转 URL。
     *
     * 服务端允许两种响应：
     *   - 直接返回 302 Location
     *   - 返回 JSON { url: "https://..." }
     * 这里两种情况都支持。
     *
     * @throws RuntimeException
     */
    public static function getBuyUrl(string $level, string $domain, string $adminEmail = '', string $returnUrl = ''): string
    {
        $query = http_build_query([
            'level' => $level,
            'domain' => $domain,
            'scope' => 'main',
            'user_email' => $adminEmail,
            'return_url' => $returnUrl,
        ]);
        $url = self::baseUrl() . '/api/v1/buy/level?' . $query;

        // 走 HEAD / GET 看服务端给 302 还是 JSON —— 一次 GET 请求就够
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'emshop-' . EM_VERSION,
        ] + self::tlsOptions());
        $resp = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            throw new RuntimeException('授权服务器不可达，请切换其他线路后重试(；′⌒`)');
        }

        // 302 → 直接返回服务端提供的 URL
        if ($httpCode === 302 && preg_match('/^Location:\s*(\S+)/mi', $resp, $m)) {
            return trim($m[1]);
        }

        // 否则拆 body 解 JSON
        $pos = strpos($resp, "\r\n\r\n");
        $body = $pos === false ? $resp : substr($resp, $pos + 4);
        $json = json_decode($body, true);
        if (is_array($json) && !empty($json['ok']) && !empty($json['data']['url'])) {
            return (string) $json['data']['url'];
        }

        throw new RuntimeException('获取购买链接失败');
    }

    /**
     * 校验域名是否已授权（POST /api/open/v1/em/license/status）。
     *
     * 请求参数只有 domain —— 按域名判，不需要激活码。
     *
     * 服务端响应 data：
     *   authorized          true=这个域名已授权 / false=没授权
     *   domain              归一之后的域名
     *   license_type        档位数字：1=VIP / 2=SVIP / 3=至尊；没授权时是 null
     *   license_type_label  档位文本；没授权时是 null
     *
     * ⚠️ 没授权也是 HTTP 200 的**成功响应**，不是调用失败 —— 调用方要看 authorized
     * 这个布尔值决定清不清本地记录，别把它当异常处理。
     *
     * 重试：只读检查、幂等，开 3 次尝试（与 base-data / bind / unbind 一致）。
     *
     * @return array{authorized?:bool, domain?:string, license_type?:string|null, license_type_label?:string|null}
     * @throws RuntimeException 网络不可达 / 响应格式异常 / code != 200
     */
    public static function status(string $domain): array
    {
        return self::postForm('api/open/v1/em/license/status', [
            'domain' => $domain,
        ], 8, 3);
    }

    /**
     * 解除域名与授权码的绑定（POST /api/open/v1/em/license/unbind）。
     *
     * 请求参数：
     *   code    授权码
     *   domain  要解绑的授权域名
     *
     * 服务端响应 data：{unbound: bool, code_masked: string, domain: string}
     *   unbound=true  —— 本次解绑成功
     *   unbound=false —— 这个授权码本来就没绑域名，**同样算成功**（幂等）
     *
     * 域名和授权码不是同一条 / 码不存在 / 已作废 → HTTP 400 + {code:400, message}，
     * 由 postForm() 当业务错误抛出（不重试）。
     *
     * 重试：解绑是幂等的（重复清同一个绑定结果一样），所以放心开 3 次尝试，
     * 不会像下单那样有重复提交的副作用。超时/次数与 baseData() 保持一致。
     *
     * @return array{unbound?:bool, code_masked?:string, domain?:string} 服务端 data
     * @throws RuntimeException 参数错误 / 网络错误
     */
    public static function unbind(string $code, string $domain): array
    {
        return self::postForm('api/open/v1/em/license/unbind', [
            'code'   => $code,
            'domain' => $domain,
        ], 8, 3);
    }

    /**
     * 后台基础数据聚合（POST /api/open/v1/em/base-data）。
     *
     * 一次请求拿到授权状态 + 代理配置 + 公告 + 广告位 + 版本信息，是后台首页与
     * 代理商弹窗唯一的数据源（原 api/admin_index.php、api/agent_config.php 已退场）。
     *
     * 请求参数：
     *   identity  代理商身份标识（base.php 的 SERVICE_TOKEN）。匹配不到不算错误：
     *             服务端会回 identity_matched=false + used_fallback=true，并用站长那份配置兜底
     *   domain    本机正在跑的域名（可传完整地址，服务端归一成顶级域名后查授权）
     *   version   客户端当前版本号（形如 1.3.18，开头的 v 可有可无）
     *
     * 返回 data 的字段（原样交给 LicenseService::fetchBaseData() 归一化）：
     *   domain / authorized / license_type / license_type_label
     *   identity_matched / used_fallback
     *   has_new_version / latest_version / force_update / min_version / patch_package_url
     *   buy_links[] / download_links[]（[{name,url}]）
     *   contact{qq_service,qq_group,wechat_service,wechat_qr,tg_service,tg_group_url}
     *   announcements[] / ad_slots[]
     *
     * 注意：域名未授权、身份标识匹配不到都是 **成功响应**（HTTP 200 / code 200），
     * 分别看 authorized 与 identity_matched，不要当成调用失败。
     *
     * 开了 3 次尝试：线路被墙/抖动是偶发的，重试一次往往就过去了。这是只读接口，
     * 重试无副作用。单次超时取 8s（不是默认的 10s）是为了把最坏耗时压在
     * 3*8 + 1s 退避 ≈ 25s，低于常见的 PHP max_execution_time=30s；
     * 配套地，前端 loadDashIndex() 的 AJAX timeout 要 ≥ 这个预算（现为 40s）。
     *
     * @return array 服务端 data 整段
     * @throws RuntimeException 网络不可达 / 响应格式异常 / code != 200
     */
    public static function baseData(string $identity, string $domain, string $version): array
    {
        return self::postForm('api/open/v1/em/base-data', [
            'identity' => $identity,
            'domain'   => $domain,
            'version'  => $version,
        ], 8, 3);
    }

    /**
     * 应用商店 - 应用列表（POST /api/app_store.php）。
     *
     * 服务端分页，返回 { list, count, page, pageNum }。
     * 每项字段详见接口文档（name_cn / cover / vip_price / svip_price / my_price / is_free 等）。
     *
     * @param array{page?:int,pageNum?:int,type?:string,category_id?:int,keyword?:string,scope?:int,emkey?:string,host?:string} $params
     * @return array{list:array<int,array>,count:int,page:int,pageNum:int}
     * @throws RuntimeException
     */
    public static function appStoreList(array $params): array
    {
        $data = self::postForm('api/app_store.php', $params, 15);
        return [
            'list'    => is_array($data['list'] ?? null) ? array_values($data['list']) : [],
            'count'   => (int) ($data['count']   ?? 0),
            'page'    => (int) ($data['page']    ?? 1),
            'pageNum' => (int) ($data['pageNum'] ?? 10),
        ];
    }

    /**
     * 应用商店 - 已购买应用列表（POST /api/app_purchased_list.php）。
     *
     * 返回结构与 appStoreList 保持一致：{ list, count, page, pageNum }。
     *
     * @param array{page?:int,pageNum?:int,type?:string,category_id?:int,keyword?:string,scope?:int,emkey?:string,host?:string} $params
     * @return array{list:array<int,array>,count:int,page:int,pageNum:int}
     * @throws RuntimeException
     */
    public static function appPurchasedList(array $params): array
    {
        $data = self::postForm('api/app_purchased_list.php', $params, 15);
        return [
            'list'    => is_array($data['list'] ?? null) ? array_values($data['list']) : [],
            'count'   => (int) ($data['count']   ?? 0),
            'page'    => (int) ($data['page']    ?? 1),
            'pageNum' => (int) ($data['pageNum'] ?? 10),
        ];
    }

    /**
     * 验证站点已购买应用（POST /api/app_purchased.php）。
     *
     * 给定一批应用的 name_en，返回本站点（emkey + member_code）**已购买或免费可用**的子集。
     *
     * @param array<int,string> $appList    要验证的应用 name_en 数组
     * @param string            $memberCode 商户分站标识符；主站 = ''（服务端按 main_site 查）
     * @return array<int,string>            返回 appList 的子集；查不到的应用会被静默忽略
     * @throws RuntimeException             网络失败 / 授权码未设置 / 接口报错 都抛异常
     */
    public static function appPurchased(array $appList, string $memberCode, int $scope): array
    {
        if (!in_array($scope, [1, 2], true)) {
            throw new RuntimeException('非法的 scope（1=主站 / 2=商户）');
        }
        $emkey = '';
        $licenseRow = LicenseService::currentLicense();
        if ($licenseRow) {
            $emkey = (string) ($licenseRow['license_code'] ?? '');
        }
        if ($emkey === '') {
            throw new RuntimeException('当前站点未激活授权码');
        }
        // 去重 + 过滤空 / 非字符串
        $appList = array_values(array_unique(array_filter(
            array_map('strval', $appList),
            static fn(string $v): bool => $v !== ''
        )));
        if ($appList === []) return [];

        $data = self::postForm('api/app_purchased.php', [
            'emkey'       => $emkey,
            'member_code' => $memberCode,
            'app_list'    => $appList,
            'scope'       => $scope,
        ], 10);

        // 服务端 data 形如 ["default","tips","alipay"]
        if (!is_array($data)) return [];
        return array_values(array_filter(
            array_map('strval', $data),
            static fn(string $v): bool => $v !== ''
        ));
    }

    /**
     * 应用商店 - 分类列表（POST /api/app_categories.php）。
     *
     * 服务端按 scope（1=主站/2=商户）过滤 app.scope IN (0, :scope) 再统计 count，
     * 保证分类的数字只体现当前角色能看到的应用。
     * 每项结构：{ id, name, type, count }
     *   - id: 自定义分类数据库主键；系统分类固定为 0
     *   - type: 系统分类标识（all / template / plugin）；自定义分类为空字符串
     *
     * @param int $scope 1=主站 / 2=商户
     * @return array<int, array{id:int, name:string, type:string, count:int}>
     * @throws RuntimeException
     */
    public static function appCategories(int $scope): array
    {
        if (!in_array($scope, [1, 2], true)) {
            throw new RuntimeException('非法的 scope（1=主站 / 2=商户）');
        }
        $data = self::postForm('api/app_categories.php', ['scope' => $scope], 10);
        // postForm 返回的是 data 节；这里接口 data 本身就是数组列表
        return is_array($data) ? array_values($data) : [];
    }

    /**
     * 按 id 获取单个应用的详情（/api/app_detail.php），一次返回 app + pay_methods。
     *
     * 价格计算与 /api/app_store.php 完全一致：
     *   - 未传 emkey / 校验失败 → my_price = vip_price
     *   - VIP → vip_price、SVIP → svip_price、至尊 → 0；my_price <= 0 时 is_free = 1
     *
     * @param int    $appId      应用 id
     * @param string $emkey      激活码；空串时按 VIP 价返回
     * @param string $host       客户端 host（服务端会归一化）
     * @param int    $scope      客户端身份：1=主站 / 2=商户
     * @param string $memberCode 商户标识（主站传空串；商户传主用户 invite_code），用于精确定位购买记录
     * @return array ['app' => [...], 'pay_methods' => [...]]
     * @throws RuntimeException
     */
    public static function appDetail(int $appId, string $emkey, string $host, int $scope, string $memberCode = ''): array
    {
        return self::postForm('api/app_detail.php', [
            'app_id'      => $appId,
            'emkey'       => $emkey,
            'host'        => $host,
            'scope'       => $scope,
            'member_code' => $memberCode,
        ], 10);
    }

    /**
     * 按 name_en 批量查询最新版本（/api/app_latest_versions.php），用于本地已装应用的更新检测。
     *
     * 只返版本 / 下载地址相关字段，不含价格 / 描述等无关数据；比 /api/app_store.php 更轻。
     *
     * @param string[] $names 本地已装的 name_en 列表（最多 50 个，超出截断）
     * @param string   $type  'template' / 'plugin'（必填，避免跨类型同名歧义）
     * @return array<string, array{id:int, version:string, file_path:string, min_version:string}>
     *         以 name_en 为 key 的 map；没查到的 name 不出现在 map 里
     * @throws RuntimeException
     */
    public static function appLatestVersions(array $names, string $type): array
    {
        if (!in_array($type, ['template', 'plugin'], true)) return [];
        // 去重 + 过滤空 / 非字符串
        $names = array_values(array_unique(array_filter(
            array_map('strval', $names),
            static fn(string $v): bool => $v !== ''
        )));
        if ($names === []) return [];
        if (count($names) > 50) $names = array_slice($names, 0, 50);

        $data = self::postForm('api/app_latest_versions.php', [
            'names' => $names,
            'type'  => $type,
        ], 10);
        return is_array($data) ? $data : [];
    }

    /**
     * 应用商店 - 创建订单（POST /api/app_create_order.php）。
     *
     * 当前重构阶段仅要求传 emkey / app_id。服务端返回 data.out_trade_no。
     *
     * @return array{out_trade_no?:string}
     * @throws RuntimeException
     */
    public static function appCreateOrder(string $emkey, int $appId): array
    {
        return self::postForm('api/app_create_order.php', [
            'emkey'  => $emkey,
            'app_id' => $appId,
        ], 10);
    }

    /**
     * 为指定应用创建购买订单（/api/app_buy.php），返回可跳转的收银台 URL。
     *
     * 客户端拿到 data.pay_url 后直接跳转即可；订单状态由收银台 / 异步通知回填。
     *
     * @param string $emkey      授权码
     * @param string $host       站点域名（服务端会归一化）
     * @param int    $appId      要购买的应用 id
     * @param string $payMethod  支付方式 code（必须在 /api/pay_methods.php 启用列表内）
     * @param string $memberCode 商户标识；空串表示主站购买
     * @return array ['out_trade_no','amount','pay_method','pay_method_name','pay_url']
     * @throws RuntimeException
     */
    public static function appBuy(string $emkey, string $host, int $appId, string $payMethod, string $memberCode = ''): array
    {
        $res = self::postForm('api/app_buy.php', [
            'emkey'       => $emkey,
            'host'        => $host,
            'app_id'      => $appId,
            'pay_method'  => $payMethod,
            'member_code' => $memberCode,
        ], 10);

        return $res;
    }

    /**
     * 获取已启用的支付方式列表（/api/pay_methods.php）。
     *
     * 返回的每项只含 code / name 两个公开字段（不含密钥、钱包地址等敏感信息），
     * 供前端收银台动态渲染可选支付通道；下单时需把选中的 code 回传给下单接口。
     *
     * @return array<int, array{code:string, name:string}> 如 [{code:'alipay',name:'支付宝'}, ...]
     * @throws RuntimeException
     */
    public static function payMethods(): array
    {
        $data = self::postForm('api/pay_methods.php', [], 10);
        return is_array($data) ? array_values($data) : [];
    }

    // --------------------------------------------------------
    // 内部
    // --------------------------------------------------------

    /**
     * 返回当前生效的线路 URL（public 别名，方便外部模块拿到授权服务器域名拼相对地址用）。
     * 用于 UpdateService::resolvePackageUrl() 在服务端返回相对路径时补域名。
     */
    public static function currentBaseUrl(): string
    {
        return self::baseUrl();
    }

    /**
     * TLS 校验选项（三个请求入口共用）。
     *
     * 默认**开启**证书与主机名校验。此前 postForm() 为了绕过「SSL error 60」
     * （证书链问题）把校验整个关掉了，而这条通道传的是授权与升级元数据：
     * 关掉校验意味着链路中间人可以篡改响应、把升级包地址换成任意主机。
     *
     * 若你的授权服务器确实是自签证书或证书链不全、且暂时无法修好证书，
     * 可将配置项 license_insecure_tls 置为 '1' 显式降级 ——
     * 但请清楚这等于放弃该通道对中间人攻击的防护。
     *
     * @return array<int, mixed>
     */
    private static function tlsOptions(): array
    {
        if ((string) Config::get('license_insecure_tls', '0') === '1') {
            return [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ];
        }

        return [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ];
    }

    /**
     * 返回当前生效的线路 URL。
     *
     * 线路配置从 EM_CONFIG['license_urls'] 读取；
     * 当前索引从 Config('license_line_index') 读，未设置默认 0。
     */
    private static function baseUrl(): string
    {
        $lines = self::lines();
        if ($lines === []) {
            throw new RuntimeException('未配置授权服务器地址（请检查 config.php 的 license_urls 或 .env 中的 LICENSE_URL_{N}）');
        }
        $idx = (int) (Config::get('license_line_index') ?? 0);
        if ($idx < 0 || $idx >= count($lines)) $idx = 0;
        return $lines[$idx]['url'];
    }

    /**
     * 规范化 EM_CONFIG['license_urls'] → 统一为 [{'url','name'}] 格式。
     *
     * @return array<int, array{url:string, name:string}>
     */
    public static function lines(): array
    {
        $out = [];
        $rawLines = (defined('EM_CONFIG') && isset(EM_CONFIG['license_urls']) && is_array(EM_CONFIG['license_urls']))
            ? EM_CONFIG['license_urls']
            : [];
        foreach ($rawLines as $i => $row) {
            if (is_string($row) && $row !== '') {
                $out[] = ['url' => $row, 'name' => '线路 ' . ($i + 1)];
            } elseif (is_array($row) && !empty($row['url'])) {
                $out[] = [
                    'url'  => (string) $row['url'],
                    'name' => (string) ($row['name'] ?? ('线路 ' . ($i + 1))),
                ];
            }
        }
        return $out;
    }

    /**
     * 通用表单 POST → 解析 { code, msg, data } → 返回 data / 抛异常。
     *
     * 用于所有返回 {code:200, msg, data} 格式的现网接口（/api/open/v1/em/* 等）。
     * 错误文案字段两种都有：老接口用 msg，新接口（/api/open/v1/*）用 message，取到哪个用哪个。
     *
     * 重试（$maxAttempts > 1）：只针对**网络层失败**——连不上 / 超时 / 网关错误 / 被中间设备
     * 换成非 JSON 响应（线路被墙时的典型表现）。业务响应（能解出 JSON）一律不重试，
     * 因为调用方里有下单、购买、领取应用这类**重复提交有副作用**的接口。
     * 所以默认 1 次（不重试），只在明确的只读调用点上显式开启。
     *
     * 预算：总耗时约 $maxAttempts * $timeout + 退避，调用方要保证它落在
     * PHP max_execution_time 与前端 AJAX timeout 之内（见 baseData() 的取值说明）。
     *
     * @param array<string, mixed> $payload
     * @param int $maxAttempts 最多尝试次数（含首次）；1 = 不重试
     * @return array 返回 data（保证是数组）
     * @throws RuntimeException
     */
    private static function postForm(string $path, array $payload, int $timeout = 10, int $maxAttempts = 1): array
    {
        $url = self::baseUrl() . ltrim($path, '/');

        $body = http_build_query($payload);
        $maxAttempts = max(1, $maxAttempts);

        $lastError = '';
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            // 退避：抖动/丢包往往连着来几秒，隔一下再试比立刻重打成功率高
            if ($attempt === 2) usleep(300000);   // 300ms
            if ($attempt === 3) usleep(700000);   // 700ms

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                    'X-Em-Client: emshop-' . EM_VERSION,
                ],
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
            ] + self::tlsOptions());
            $resp = curl_exec($ch);
            $err = curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($resp === false) {
                $lastError = '当前使用线路请求失败，请切换其他线路后重试，错误信息：' . $err;
                continue;
            }

            $json = json_decode($resp, true);
            if (!is_array($json)) {
                // 4xx 是请求本身的问题，重试没意义；其余（5xx / 0）按线路故障重试
                if ($httpCode >= 400 && $httpCode < 500) {
                    throw new RuntimeException('响应格式异常（HTTP ' . $httpCode . '）');
                }
                $lastError = '响应格式异常（HTTP ' . $httpCode . '）';
                continue;
            }

            // 能解出业务响应 → 不管成功失败都不再重试（避免重复下单等副作用）
            if ((int) ($json['code'] ?? 0) !== 200) {
                throw new RuntimeException((string) ($json['msg'] ?? $json['message'] ?? '请求失败'));
            }
            return is_array($json['data'] ?? null) ? $json['data'] : [];
        }

        throw new RuntimeException($lastError !== '' ? $lastError : '请求失败');
    }

    /**
     * 通用 POST JSON → 解析 { ok, data, ... } → 返回 data / 抛异常。
     *
     * 保留给未来走 JSON 协议的接口使用（v2 verify 等）。
     *
     * @param array<string, mixed> $payload
     * @return mixed
     * @throws RuntimeException
     */
    private static function post(string $path, array $payload, int $timeout = 10)
    {
        $url = self::baseUrl() . $path;
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Em-Client: emshop-' . EM_VERSION,
            ],
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
        ] + self::tlsOptions());
        $resp = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            throw new RuntimeException('授权服务器不可达，请切换其他线路后重试');
        }

        $json = json_decode($resp, true);
        if (!is_array($json)) {
            throw new RuntimeException('授权服务器响应格式异常（HTTP ' . $httpCode . '）');
        }
        if (empty($json['ok'])) {
            $msg = (string) ($json['msg'] ?? '授权失败');
            $code = (string) ($json['code'] ?? '');
            throw new RuntimeException($msg . ($code !== '' ? "（{$code}）" : ''));
        }
        return $json['data'] ?? null;
    }

    // ============================================================================
    // 应用商店重构:按 audience 拆出的镜像方法(mainApp* / merchantApp*)
    //
    // 服务端将来计划把"主站货架"和"分站货架"拆成两组独立接口。当前服务端还没就绪,
    // 这一组方法暂时委托给老 appStoreList / appDetail / appBuy / appLatestVersions,
    // 通过 scope + audience 字段透传给服务端做兼容区分。
    //
    // 调用方迁移后,服务端拆接口只需改下面 8 个方法的 endpoint 即可,业务代码无感。
    //
    // 使用约定:
    //   - mainApp*     主站为自己采购(落 em_plugin / em_template scope='main')
    //   - merchantApp* 主站为分站采购(落 em_app_market;不再以分站身份直连服务端)
    //   - 两边 member_code 都为空 —— 始终是"主站站长身份"调,跟分站登录态无关
    // ============================================================================

    /**
     * 主站货架 · 应用列表。
     *
     * @param array{page?:int,pageNum?:int,type?:string,category_id?:int,keyword?:string,emkey?:string,host?:string} $params
     * @return array{list:array<int,array>,count:int,page:int,pageNum:int}
     */
    public static function mainAppList(array $params): array
    {
        $params['scope']    = 1;
        $params['audience'] = 'main';
        $params['member_code'] = '';
        return self::appStoreList($params);
    }

    /**
     * 分站货架 · 应用列表(主站后台为分站采购时拉)。
     *
     * @param array{page?:int,pageNum?:int,type?:string,category_id?:int,keyword?:string,emkey?:string,host?:string} $params
     * @return array{list:array<int,array>,count:int,page:int,pageNum:int}
     */
    public static function merchantAppList(array $params): array
    {
        $params['scope']    = 2;
        $params['audience'] = 'merchant';
        $params['member_code'] = '';
        return self::appStoreList($params);
    }

    /**
     * 主站货架 · 已购买应用列表。
     *
     * @param array{page?:int,pageNum?:int,type?:string,category_id?:int,keyword?:string,emkey?:string,host?:string} $params
     * @return array{list:array<int,array>,count:int,page:int,pageNum:int}
     */
    public static function mainAppPurchasedList(array $params): array
    {
        $params['scope'] = 1;
        return self::appPurchasedList($params);
    }

    /**
     * 分站货架 · 已购买应用列表（主站为分站采购视角）。
     *
     * @param array{page?:int,pageNum?:int,type?:string,category_id?:int,keyword?:string,emkey?:string,host?:string} $params
     * @return array{list:array<int,array>,count:int,page:int,pageNum:int}
     */
    public static function merchantAppPurchasedList(array $params): array
    {
        $params['scope'] = 2;
        return self::appPurchasedList($params);
    }

    /**
     * 主站货架 · 应用详情 + 支付方式。
     */
    public static function mainAppDetail(int $appId, string $emkey, string $host): array
    {
        return self::appDetail($appId, $emkey, $host, 1, '');
    }

    /**
     * 分站货架 · 应用详情 + 支付方式(主站为分站采购时弹窗一次拉齐)。
     */
    public static function merchantAppDetail(int $appId, string $emkey, string $host): array
    {
        return self::appDetail($appId, $emkey, $host, 2, '');
    }

    /**
     * 主站货架 · 创建购买订单。
     */
    public static function mainAppCreateOrder(string $emkey, int $appId): array
    {
        return self::appCreateOrder($emkey, $appId);
    }

    /**
     * 分站货架 · 创建购买订单。
     */
    public static function merchantAppCreateOrder(string $emkey, int $appId): array
    {
        return self::appCreateOrder($emkey, $appId);
    }

    /**
     * 主站货架 · 创建购买订单（兼容旧调用，后续移除）。
     */
    public static function mainAppBuy(string $emkey, string $host, int $appId, string $payMethod): array
    {
        return self::mainAppCreateOrder($emkey, $appId);
    }

    /**
     * 分站货架 · 创建购买订单（兼容旧调用，后续移除）。
     */
    public static function merchantAppBuy(string $emkey, string $host, int $appId, string $payMethod): array
    {
        return self::merchantAppCreateOrder($emkey, $appId);
    }

    /**
     * 主站货架 · 已装应用最新版本(用于本地已装更新检测)。
     */
    public static function mainAppLatestVersions(array $names, string $type): array
    {
        return self::appLatestVersions($names, $type);
    }

    /**
     * 分站货架 · 已上架应用最新版本(主站后台更新检测用)。
     */
    public static function merchantAppLatestVersions(array $names, string $type): array
    {
        return self::appLatestVersions($names, $type);
    }
}
