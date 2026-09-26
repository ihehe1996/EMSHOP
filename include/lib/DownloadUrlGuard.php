<?php

declare(strict_types=1);

/**
 * 下载地址白名单校验（应用商店 / 在线升级共用）。
 *
 * 为什么不能用字符串前缀比较：
 *   设 baseUrl = https://license.example.com，下面两个地址都以它开头，但真实 host 都不是它：
 *     https://license.example.com.attacker.com/x.zip   前缀相同，host 是 license.example.com.attacker.com
 *     https://license.example.com@attacker.com/x.zip   @ 之前是 userinfo，host 是 attacker.com
 *   一旦绕过，就能从任意外部主机下载 zip 并写入 content/plugin 或 content/template
 *   （web 可直接执行的目录），而后续流程还会 include 包内回调文件 —— 等于任意代码执行。
 *
 * 所以必须解析 URL 后比对 host，且拒绝 userinfo。
 *
 * 这里是**纯判定**（不输出、不退出），便于单测；页面层负责把 false 转成错误响应。
 */
final class DownloadUrlGuard
{
    /**
     * 该下载地址是否被允许。
     *
     * 规则：
     *   1) 必须是 https（明文链路可被替换，等于白名单失效）
     *   2) 不允许 userinfo（user:pass@host）—— 最容易骗过肉眼与前缀比较
     *   3) host 必须与 baseUrl 的 host **完全相等**（不做子域通配，避免 *.example.com 被滥用）
     *   4) 端口若显式给出，必须与 baseUrl 的端口一致（默认 443）
     */
    public static function isAllowed(string $url, string $baseUrl): bool
    {
        $u = parse_url($url);
        $b = parse_url($baseUrl);

        if (!is_array($u) || !is_array($b)) {
            return false;
        }

        if (strtolower((string) ($u['scheme'] ?? '')) !== 'https') {
            return false;
        }

        if (isset($u['user']) || isset($u['pass'])) {
            return false;
        }

        $host = strtolower((string) ($u['host'] ?? ''));
        $baseHost = strtolower((string) ($b['host'] ?? ''));
        if ($host === '' || $baseHost === '' || $host !== $baseHost) {
            return false;
        }

        // 端口：URL 显式指定时必须与基准一致
        if (isset($u['port'])) {
            $basePort = isset($b['port']) ? (int) $b['port'] : 443;
            if ((int) $u['port'] !== $basePort) {
                return false;
            }
        }

        return true;
    }

    /**
     * 出站请求的通用安全校验：必须是 http(s)，且目标不指向内网/保留地址。
     *
     * 用于「调用方指定地址、服务端主动请求」的场景（如订单发货回调地址），
     * 否则会被用来探测内网（盲 SSRF）：例如把地址填成
     *   http://127.0.0.1:6379/        探 Redis
     *   http://169.254.169.254/latest/meta-data/   读云元数据
     *
     * 注意：这是「请求前」的解析检查，无法完全防住 DNS Rebinding
     * （校验时解析到公网、真正请求时解析到内网）。要彻底解决需把请求固定到
     * 已校验的 IP（CURLOPT_RESOLVE）。当前实现已挡住绝大多数利用方式。
     */
    public static function isPublicHttpUrl(string $url): bool
    {
        $u = parse_url($url);
        if (!is_array($u)) {
            return false;
        }
        if (!in_array(strtolower((string) ($u['scheme'] ?? '')), ['http', 'https'], true)) {
            return false;
        }
        if (isset($u['user']) || isset($u['pass'])) {
            return false;
        }

        $host = trim((string) ($u['host'] ?? ''));
        if ($host === '') {
            return false;
        }

        // 字面量 IP：直接判定
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($host);
        }

        // 域名：解析出的所有地址都必须是公网地址（任一为内网即拒绝）
        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $r) {
                if (!empty($r['ip'])) $ips[] = (string) $r['ip'];
                if (!empty($r['ipv6'])) $ips[] = (string) $r['ipv6'];
            }
        }
        if ($ips === []) {
            $resolved = @gethostbyname($host);
            if (is_string($resolved) && $resolved !== $host && $resolved !== '') {
                $ips[] = $resolved;
            }
        }
        if ($ips === []) {
            // 解析不出来就不允许 —— 宁可拒绝，也不要让请求打到未知目标
            return false;
        }

        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 是否为公网可路由地址。
     *
     * NO_PRIV_RANGE 排除 10/8、172.16/12、192.168/16 与 IPv6 ULA；
     * NO_RES_RANGE 排除 0.0.0.0/8、127/8、169.254/16、240/4 等保留段。
     */
    private static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
