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
     * 授权激活（POST /api/auth.php）。
     *
     * 服务端响应 data：
     *   type: 1=VIP / 2=SVIP / 3=至尊
     *   host: 归一化后的域名（写入 em_site_license.bound_domain）
     *
     * 失败场景（msg 原样由服务端返回并抛给调用方）：
     *   激活码不能为空 / 域名不能为空 / 域名格式错误 / 激活码不存在 / 该激活码已被其他域名使用
     *
     * @return array{level:string, host:string, extra:array}
     * @throws RuntimeException
     */
    public static function activate(string $licenseCode, string $domain, string $adminEmail = ''): array
    {
        $data = self::postForm('api/auth.php', [
            'emkey' => $licenseCode,
            'host'  => $domain,
        ], 10);

        $typeMap = [1 => 'vip', 2 => 'svip', 3 => 'supreme'];
        $type = (int) ($data['type'] ?? 0);
        $level = $typeMap[$type] ?? '';

        if ($level === '') {
            throw new RuntimeException('授权服务器返回了未知等级（type=' . $type . '）');
        }

        return [
            'level' => $level,
            'host'  => (string) ($data['host'] ?? $domain),
            'extra' => $data,
        ];
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
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'emshop-' . EM_VERSION,
        ]);
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
     * 校验站点授权状态（POST /api/check.php）。
     *
     * 服务端只按 host 查记录；如果传了 emkey，要求 host 与 emkey **同时**匹配同一条记录。
     *
     * @param string $licenseCode 激活码
     * @param string $host        当前站点域名
     * @return array{level:string, type:int}
     * @throws LicenseRevokedException 服务端明确判定"未激活 / 激活码不存在"等（调用方应删除本地记录）
     * @throws RuntimeException 其它错误（网络不可达 / 格式异常等）——调用方应保守保留本地状态
     */
    public static function verify(string $licenseCode, string $host): array
    {
        try {
            $data = self::postForm('api/check.php', [
                'emkey' => $licenseCode,
                'host'  => $host,
            ], 10);
        } catch (RuntimeException $e) {
            // 服务端业务错误（非网络层）→ 转成专用异常，让调用方可按"撤销"处理
            $msg = $e->getMessage();
            if (self::isRevokedMessage($msg)) {
                throw new LicenseRevokedException($msg);
            }
            throw $e;
        }

        $typeMap = [1 => 'vip', 2 => 'svip', 3 => 'supreme'];
        $type = (int) ($data['type'] ?? 0);
        $level = $typeMap[$type] ?? '';
        if ($level === '') {
            // 服务端响应 200 但 type 异常 —— 视为未激活以避免误状态
            throw new LicenseRevokedException('授权服务器返回未知等级（type=' . $type . '）');
        }

        return ['level' => $level, 'type' => $type];
    }

    /**
     * 服务端错误 msg 是否表示"未激活"类业务错误。
     */
    private static function isRevokedMessage(string $msg): bool
    {
        // 匹配服务端在 check.php / auth.php 约定的几种 msg
        foreach (['未激活', '不存在', '已被其他域名'] as $kw) {
            if (mb_strpos($msg, $kw) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 解除域名授权（POST /api/unbind.php）。
     *
     * 服务端规则：host + emkey 双重匹配才清空记录的 host；匹配不到也按成功返回（幂等）。
     * 因此从本方法的角度：只有参数层错误（msg: 域名/激活码不能为空 / 域名格式错误）才算失败。
     *
     * @throws RuntimeException 参数错误 / 网络错误
     */
    public static function unbind(string $licenseCode, string $host): void
    {
        self::postForm('api/unbind.php', [
            'emkey' => $licenseCode,
            'host'  => $host,
        ], 10);
        // 成功时服务端返回 data=null；不关心返回内容，只要 postForm 没抛异常就是成功
    }

    /**
     * 后台首页聚合数据（POST /api/admin_index.php）。
     *
     * 一次性拉取四块：
     *   update[]  —— 高于客户端版本的升级记录（传 version 为空则不返回）
     *     每项字段：
     *       - version          版本号（如 "1.2.0"）
     *       - content          更新日志 HTML
     *       - update_time      发布时间（YYYY-MM-DD）
     *       - package_url      升级包下载 URL（.zip）     —— 在线升级功能依赖
     *       - package_size     包大小（字节，展示用）
     *       - package_sha256   包 SHA256 校验码（下载后比对）
     *       - min_from_version 最低可升级源版本（低于此版本禁止直升）
     *       - is_forced        是否强制更新（0/1，先预留）
     *   notice[]  —— 官方公告
     *   ad[]      —— 代理商广告（按 service_token 归属；查不到时服务端回退默认代理商）
     *   agent     —— 代理商联系方式 + 下载源
     *
     * @return array{update:array<int,array>, notice:array<int,array>, ad:array<int,array>, agent:array}
     * @throws RuntimeException
     */
    public static function adminIndex(string $licenseCode, string $host, string $version): array
    {
        $token = defined('SERVICE_TOKEN') ? SERVICE_TOKEN : '';
        $data = self::postForm('api/admin_index.php', [
            'emkey'         => $licenseCode,
            'host'          => $host,
            'version'       => $version,
            'service_token' => $token,
        ], 10);

        return [
            'update' => isset($data['update']) && is_array($data['update']) ? $data['update'] : [],
            'notice' => isset($data['notice']) && is_array($data['notice']) ? $data['notice'] : [],
            'ad'     => isset($data['ad'])     && is_array($data['ad'])     ? $data['ad']     : [],
            'agent'  => isset($data['agent'])  && is_array($data['agent'])  ? $data['agent']  : [],
        ];
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

    /**
     * 获取代理商配置（/api/agent_config.php）。
     *
     * 请求体携带 base.php 里定义的 SERVICE_TOKEN；未传 / 查不到时服务端会回退默认代理商。
     *
     * @return array 服务端返回的 data 整段（含 service_qq / buy_url[] / download_url[] 等）
     * @throws RuntimeException
     */
    public static function agentConfig(): array
    {
        $token = defined('SERVICE_TOKEN') ? SERVICE_TOKEN : '';
        return self::postForm('api/agent_config.php', ['service_token' => $token], 10);
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
     * 用于所有返回 {code:200, msg, data} 格式的现网接口（/api/auth.php、/api/agent_config.php 等）。
     *
     * @param array<string, mixed> $payload
     * @return array 返回 data（保证是数组）
     * @throws RuntimeException
     */
    private static function postForm(string $path, array $payload, int $timeout = 10): array
    {
        $url = self::baseUrl() . ltrim($path, '/');
        $body = http_build_query($payload);

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
            // 修复 SSL 错误 60
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false) {
            throw new RuntimeException('当前使用线路请求失败，请切换其他线路后重试，错误信息：' . $err);
        }

        $json = json_decode($resp, true);
        if (!is_array($json)) {
            throw new RuntimeException('响应格式异常（HTTP ' . $httpCode . '）');
        }
        if ((int) ($json['code'] ?? 0) !== 200) {
            throw new RuntimeException((string) ($json['msg'] ?? '请求失败'));
        }
        return is_array($json['data'] ?? null) ? $json['data'] : [];
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
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
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
