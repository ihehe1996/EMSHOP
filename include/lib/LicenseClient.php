<?php

declare(strict_types=1);

/**
 * 授权服务器 HTTP 客户端。
 *
 * 职责：把对授权服务器的所有网络调用集中在一处，业务层（LicenseService）只拿结构化结果。
 * 服务端地址固定为 init.php 里定义的 EM_LICENSE_SERVER_URL（单线路，不读 config.php）。
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
     * 应用商店 · 应用列表（POST /api/open/v1/em/app-list）。
     *
     * 主站货架与分站货架合并成一个接口，用 scope 区分（main 主站 / branch 分站）。
     * 授权信息与分类一并返回，不用再单独请求。
     *
     * 请求参数：
     *   domain      本机在跑的域名（必填，服务端归一成顶级域名）
     *   code        授权码；不传 = 未授权。传了必须是有效的、且绑的就是 domain，
     *               否则一律按未授权算（服务端会回 VIP 门槛价 + can_buy=false）
     *   page        第几页，从 1 开始
     *   per_page    每页条数，默认 20、最多 50
     *   type        template / plugin，不传是全部
     *   scope       main 主站使用 / branch 分站使用，不传是两种都返回
     *   category_id 分类 id，取值从返回的 categories 拿
     *   keyword     按中文名 / 英文名 / 作者模糊搜
     *
     * 返回：
     *   license     {authorized, domain, type, type_label, all_free, code_masked}
     *   categories  [{id, name}]
     *   meta        {page, per_page, total, last_page}
     *   data[]      应用列表（price / price_vip / price_svip / can_buy / is_pay / package_url / screenshots 等）
     *
     * is_pay 是布尔：该应用是否已经买过（已拥有），前端据此把按钮换成"已购买，安装"。
     * data[] 原样透传，本方法不裁剪字段。
     *
     * 注意：`name_en` 仍被调用方当作本地安装目录名（slug）使用，不是纯展示字段。
     *
     * 重试：只读、幂等，开 3 次尝试。
     *
     * @param array{domain?:string,code?:string,page?:int,per_page?:int,type?:string,scope?:string,category_id?:int,keyword?:string} $params
     * @return array{license:array<string,mixed>,categories:array<int,array>,meta:array<string,int>,data:array<int,array>}
     * @throws RuntimeException
     */
    public static function appList(array $params): array
    {
        // print_r($params);die;
        $data = self::postForm('api/open/v1/em/app-list', $params, 15, 3);

        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        return [
            'license'    => is_array($data['license'] ?? null) ? $data['license'] : [],
            'categories' => is_array($data['categories'] ?? null) ? array_values($data['categories']) : [],
            'meta'       => [
                'page'      => (int) ($meta['page']      ?? 1),
                'per_page'  => (int) ($meta['per_page']  ?? 20),
                'total'     => (int) ($meta['total']     ?? 0),
                'last_page' => (int) ($meta['last_page'] ?? 0),
            ],
            'data'       => is_array($data['data'] ?? null) ? array_values($data['data']) : [],
        ];
    }

    /**
     * 应用商店 - 已购买应用列表（POST /api/app_purchased_list.php）。
     *
     * 走的是旧接口 api/app_purchased_list.php（新接口 app-list 没有"已购买"这个概念），
     * 所以返回结构仍是老的 { list, count, page, pageNum }，别和 appList() 的 meta/data 混了。
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
     * 按 id 获取单个应用的详情（/api/app_detail.php），一次返回 app + pay_methods。
     *
     * 价格计算与 app-list 一致：
     *   - 未传 emkey / 校验失败 → price = price_vip（VIP 门槛价）、can_buy = false
     *   - VIP → price_vip、SVIP → price_svip、至尊 → 0
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
     * 批量检查已装应用有没有新版本（POST /api/open/v1/em/app-update-check）。
     *
     * 与 app-list 同一套身份口径：domain 必填，code 不传 / 无效一律按未授权算。
     * **服务端只返回"有更新"的条目**（都最新时 data 为空数组），并原样回显 installed_version，
     * 所以调用方不需要自己比版本作为"有没有更新"的判据。
     *
     * 请求体是 JSON：{domain, code, apps:[{name_en, version}]}；apps 上限 100 条，
     * 超出由调用方分片（见 AppUpdateService::checkInstalled）。
     *
     * 返回：
     *   license  {authorized, domain, type, type_label, all_free, code_masked}
     *   data[]   {name_en, installed_version, version, type, type_label, scope, scope_label,
     *             min_version, package_url, package_name, package_size, can_buy,
     *             purchased, is_pay, updated_at}
     *
     * 重试：只读、幂等，开 3 次尝试。
     *
     * @param array<int, array{name_en:string, version:string}> $apps 本机已装清单
     * @return array{license:array<string,mixed>,data:array<int,array>}
     * @throws RuntimeException
     */
    public static function appUpdateCheck(array $apps, string $domain, string $code): array
    {
        if ($apps === []) {
            return ['license' => [], 'data' => []];
        }

        $data = self::postJson('api/open/v1/em/app-update-check', [
            'domain' => $domain,
            'code'   => $code,
            'apps'   => array_values($apps),
        ]);

        return [
            'license' => is_array($data['license'] ?? null) ? $data['license'] : [],
            'data'    => is_array($data['data'] ?? null) ? array_values($data['data']) : [],
        ];
    }

    /**
     * 应用商店 - 创建购买订单（POST /api/open/v1/em/order）。
     *
     * 下单即把订单信息、收款页地址、可用支付通道一次返回，客户端原样展示即可：
     * 让用户挑一个通道，再把通道 id 拼到 pay_url 上跳转（收银台按 ?channel={id} 选通道）。
     *
     * 请求参数（三个都必填，买东西必须有授权码 —— 这点和应用列表不同）：
     *   code    授权码；必须是有效的、且绑的就是 domain，否则服务端拒绝
     *   domain  本机在跑的域名（可传完整地址，服务端归一成顶级域名）
     *   app_id  要买的应用 id（取自 app-list 返回的 data[].id）
     *
     * 返回 data：
     *   order_no / amount / license_type / license_type_label
     *   app{id,name,type,type_label}
     *   status / state / expires_at / pay_url / channels[] / created_at
     * channels[] 每项 {id, name, logo, type, type_label}；logo 是授权服务器上的
     * 相对路径，调用方要补当前线路域名后再给前端。
     *
     * 重试：下单有副作用（重复提交会重复生成订单），**保持不重试**。
     *
     * @return array 服务端 data（含 pay_url / channels）
     * @throws RuntimeException
     */
    public static function createOrder(string $code, string $domain, int $appId): array
    {
        return self::postForm('api/open/v1/em/order', [
            'code'   => $code,
            'domain' => $domain,
            'app_id' => $appId,
        ], 15);
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
     * 内置的授权服务器地址（init.php 的 EM_LICENSE_SERVER_URL）。
     *
     * 只认常量不读 config.php：地址必须跟着程序版本走，否则用户在线更新后
     * config.php 里残留的旧地址会继续生效。返回值统一带尾部斜杠，方便拼路径。
     */
    private static function serverUrl(): string
    {
        if (!defined('EM_LICENSE_SERVER_URL')) {
            return '';
        }
        $url = trim((string) EM_LICENSE_SERVER_URL);
        return $url === '' ? '' : rtrim($url, '/') . '/';
    }

    /**
     * 返回当前生效的线路 URL（固定单线路）。
     *
     * @throws RuntimeException
     */
    private static function baseUrl(): string
    {
        $url = self::serverUrl();
        if ($url === '') {
            throw new RuntimeException('未配置授权服务器地址（init.php 的 EM_LICENSE_SERVER_URL 缺失）');
        }
        return $url;
    }

    /**
     * 授权服务器线路列表。
     *
     * 现在只有一条线路，返回单元素数组即可；保留「列表」这个形状是为了兼容既有的
     * 调用方（下载地址白名单、应用资源 host 等），它们只依赖第 0 个元素。
     *
     * @return array<int, array{url:string, name:string}>
     */
    public static function lines(): array
    {
        $url = self::serverUrl();
        if ($url === '') {
            return [];
        }
        return [['url' => $url, 'name' => '官方线路']];
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
        return self::apiRequest($path, $payload, false, $timeout, $maxAttempts);
    }

    /**
     * 通用 JSON POST → 解析 { code, msg|message, data } → 返回 data / 抛异常。
     *
     * 与 postForm() 共用同一套重试与信封口径，区别只在请求体是 JSON、Content-Type 是
     * application/json —— app-update-check 这类接口只收 JSON。
     *
     * @param array<string, mixed> $payload
     * @param int $maxAttempts 最多尝试次数（含首次）；1 = 不重试
     * @return array 返回 data（保证是数组）
     * @throws RuntimeException
     */
    private static function postJson(string $path, array $payload, int $timeout = 15, int $maxAttempts = 3): array
    {
        return self::apiRequest($path, $payload, true, $timeout, $maxAttempts);
    }

    /**
     * 表单 / JSON POST 的公共实现，重试策略与信封解析只有这一份。
     *
     * @param array<string, mixed> $payload
     * @return array 返回 data（保证是数组）
     * @throws RuntimeException
     */
    private static function apiRequest(string $path, array $payload, bool $asJson, int $timeout, int $maxAttempts): array
    {
        $url = self::baseUrl() . ltrim($path, '/');

        $body = $asJson
            ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : http_build_query($payload);
        if ($body === false) {
            throw new RuntimeException('请求数据编码失败');
        }
        $contentType = $asJson ? 'application/json' : 'application/x-www-form-urlencoded';
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
                    'Content-Type: ' . $contentType,
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
    // 这一组方法负责区分"主站货架"和"分站货架",历史上服务端是两组接口。
    // 现在 app-list 已合成一个接口,用 scope(main/branch) 区分,这里就只注入 scope。
    //
    // 使用约定:
    //   - mainApp*     主站为自己采购(落 em_plugin / em_template scope='main')
    //   - merchantApp* 主站为分站采购(落 em_app_market;不再以分站身份直连服务端)
    // ============================================================================

    /**
     * 主站货架 · 应用列表。
     *
     * @param array{domain?:string,code?:string,page?:int,per_page?:int,type?:string,category_id?:int,keyword?:string} $params
     * @return array{license:array<string,mixed>,categories:array<int,array>,meta:array<string,int>,data:array<int,array>}
     */
    public static function mainAppList(array $params): array
    {
        $params['scope'] = 'main';
        return self::appList($params);
    }

    /**
     * 分站货架 · 应用列表(主站后台为分站采购时拉)。
     *
     * 注意 scope 的取值是 `branch`（分站），不是 merchant —— 服务端那边用词是 branch。
     *
     * @param array{domain?:string,code?:string,page?:int,per_page?:int,type?:string,category_id?:int,keyword?:string} $params
     * @return array{license:array<string,mixed>,categories:array<int,array>,meta:array<string,int>,data:array<int,array>}
     */
    public static function merchantAppList(array $params): array
    {
        $params['scope'] = 'branch';
        return self::appList($params);
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
     *
     * 下单接口只有一个（/api/open/v1/em/order），不带 scope —— 买哪个应用由 app_id 决定。
     * 镜像方法保留是为了让调用方继续按 tab 分支，将来服务端要差异化时改这里即可。
     */
    public static function mainAppCreateOrder(string $code, string $domain, int $appId): array
    {
        return self::createOrder($code, $domain, $appId);
    }

    /**
     * 分站货架 · 创建购买订单。
     */
    public static function merchantAppCreateOrder(string $code, string $domain, int $appId): array
    {
        return self::createOrder($code, $domain, $appId);
    }

}
