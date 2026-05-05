<?php

declare(strict_types=1);

/**
 * 站点正版授权服务。
 *
 * 存储模型：全部落 Config 表（一个实例一份激活状态）
 *   license_emkey         激活码（明文，方便重新绑定用）
 *   license_emkey_type    等级整数：1=VIP 2=SVIP 3=至尊
 *   license_main_host     主授权域名（中心服务返回归一化后的 host）
 *   license_alias_hosts   其它允许访问的域名 JSON 数组（纯本地概念，不通知中心服务）
 *
 * 行为约定：
 *   - 激活：调 /api/auth.php 成功 → 写 emkey / emkey_type / main_host 到 Config
 *   - 解绑：只清 main_host + emkey_type；emkey 和 alias 保留
 *   - isActivated 判据：main_host 已配置
 *   - 访问别名域名时，isActivated 仍返 true；所有中心服务调用的 host 参数一律用 main_host
 */
final class LicenseService
{
    public const LEVEL_NONE = 'none';
    public const LEVEL_VIP = 'vip';
    public const LEVEL_SVIP = 'svip';
    public const LEVEL_SUPREME = 'supreme';

    /** 等级权重（越高越优） */
    private const LEVEL_WEIGHT = [
        self::LEVEL_NONE => 0,
        self::LEVEL_VIP => 1,
        self::LEVEL_SVIP => 2,
        self::LEVEL_SUPREME => 3,
    ];

    /** 中心服务 type 数字 → 内部 level 字符串 */
    private const TYPE_TO_LEVEL = [
        1 => self::LEVEL_VIP,
        2 => self::LEVEL_SVIP,
        3 => self::LEVEL_SUPREME,
    ];

    /** 同时支持反查 */
    private const LEVEL_TO_TYPE = [
        self::LEVEL_VIP => 1,
        self::LEVEL_SVIP => 2,
        self::LEVEL_SUPREME => 3,
    ];

    /** 别名数量上限 */
    public const MAX_ALIAS_HOSTS = 10;

    /** 对外展示用的中文名 */
    public static function levelLabel(string $level): string
    {
        return [
            self::LEVEL_NONE => '未授权',
            self::LEVEL_VIP => 'VIP',
            self::LEVEL_SVIP => 'SVIP',
            self::LEVEL_SUPREME => '至尊',
        ][$level] ?? '未知';
    }

    /**
     * 当前生效的授权记录。未激活返 null。
     *
     * 返回形状（和老版本保持大致兼容）：
     *   [
     *     'license_code' => string, 'level' => string, 'level_label' => string,
     *     'bound_domain' => string,   // 即主授权域名
     *     'alias_hosts'  => string[], // 额外别名
     *   ]
     *
     * @return array<string, mixed>|null
     */
    public static function currentLicense(): ?array
    {
        $mainHost = (string) Config::get('license_main_host', '');
        if ($mainHost === '') {
            return null; // 未激活
        }
        // 如果当前请求域名既不是主也不在别名里，则不认可"本次访问是授权通过的"
        // 允许访问：main OR alias
        $req = self::currentDomain();
        $aliases = self::aliasHosts();
        $allow = ($req === '' || $req === 'localhost' || $req === $mainHost || in_array($req, $aliases, true));
        if (!$allow) {
            return null;
        }

        $emkey = (string) Config::get('license_emkey', '');
        $type = (int) Config::get('license_emkey_type', '0');
        $level = self::TYPE_TO_LEVEL[$type] ?? self::LEVEL_NONE;

        return [
            'license_code' => $emkey,
            'level'        => $level,
            'level_label'  => self::levelLabel($level),
            'bound_domain' => $mainHost, // 给老调用方保持字段名
            'alias_hosts'  => $aliases,
        ];
    }

    /**
     * 当前域名的有效等级。未激活 → 'none'。
     */
    public static function currentLevel(): string
    {
        $row = self::currentLicense();
        return $row !== null ? (string) $row['level'] : self::LEVEL_NONE;
    }

    /** 是否已激活（任意等级）。 */
    public static function isActivated(): bool
    {
        return self::currentLevel() !== self::LEVEL_NONE;
    }

    /** 等级是否满足（比较权重）。 */
    public static function hasLevel(string $required): bool
    {
        $cur = self::LEVEL_WEIGHT[self::currentLevel()] ?? 0;
        $need = self::LEVEL_WEIGHT[$required] ?? 0;
        return $cur >= $need;
    }

    /**
     * 激活：向中心服务提交激活码，成功后把 emkey / type / main_host 写进 Config。
     *
     * @return array{level:string, level_label:string, bound_domain:string}
     * @throws RuntimeException
     */
    public static function activate(string $licenseCode): array
    {
        $licenseCode = trim($licenseCode);
        if ($licenseCode === '') {
            throw new RuntimeException('请输入激活码');
        }

        $domain = self::currentDomain();
        $result = LicenseClient::activate($licenseCode, $domain);
        $level = (string) ($result['level'] ?? '');
        if (!isset(self::LEVEL_TO_TYPE[$level])) {
            throw new RuntimeException('服务端返回的等级无效：' . $level);
        }

        // 主授权域名以中心服务归一化后的为准（去协议、端口、尾斜杠）
        $mainHost = !empty($result['host']) ? (string) $result['host'] : $domain;

        Config::set('license_emkey', $licenseCode);
        Config::set('license_emkey_type', (string) self::LEVEL_TO_TYPE[$level]);
        Config::set('license_main_host', $mainHost);

        return [
            'level'        => $level,
            'level_label'  => self::levelLabel($level),
            'bound_domain' => $mainHost,
        ];
    }

    /**
     * 解绑当前主授权域名。
     * 流程：远程解绑 → 只清 main_host + emkey_type；保留 emkey 和 alias_hosts。
     *
     * @throws RuntimeException
     */
    public static function unbind(): void
    {
        $emkey = (string) Config::get('license_emkey', '');
        $mainHost = (string) Config::get('license_main_host', '');
        if ($emkey === '' || $mainHost === '') {
            throw new RuntimeException('当前未激活，无需解绑');
        }

        // 远程解绑；网络失败直接抛，本地不动
        LicenseClient::unbind($emkey, $mainHost);

        Config::set('license_main_host', '');
        Config::set('license_emkey_type', '0');
    }

    /**
     * 周期性校验当前激活状态（进入 license 页或后台首页时触发）。
     *
     *  - 未激活 → 跳过
     *  - 中心服务判定未激活（LicenseRevokedException）→ 清 main_host + emkey_type，等同解绑
     *  - 网络异常 → 保守保留
     *  - 成功且等级有变 → 同步更新 emkey_type
     */
    public static function revalidateCurrent(): void
    {
        $mainHost = (string) Config::get('license_main_host', '');
        $emkey = (string) Config::get('license_emkey', '');
        if ($mainHost === '' || $emkey === '') {
            return;
        }
        try {
            $result = LicenseClient::verify($emkey, $mainHost);

            $newLevel = (string) ($result['level'] ?? '');
            if ($newLevel !== '' && isset(self::LEVEL_TO_TYPE[$newLevel])) {
                $cur = (int) Config::get('license_emkey_type', '0');
                $next = self::LEVEL_TO_TYPE[$newLevel];
                if ($cur !== $next) Config::set('license_emkey_type', (string) $next);
            }
        } catch (LicenseRevokedException $e) {
            // 服务端明确判定未激活 → 等同解绑（清 main_host + emkey_type）
            Config::set('license_main_host', '');
            Config::set('license_emkey_type', '0');
        } catch (Throwable $e) {
            // 网络异常 / 服务端 500 等 → 保守保留
        }
    }

    /**
     * 购买跳转 URL。
     *
     * @throws RuntimeException
     */
    public static function getBuyUrl(string $level, string $adminEmail = '', string $returnUrl = ''): string
    {
        if ($returnUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $returnUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/admin/license.php?from=buy';
        }
        return LicenseClient::getBuyUrl($level, self::currentDomain(), $adminEmail, $returnUrl);
    }

    /**
     * 给所有中心服务调用用的 "有效 host"：优先 Config 里配的主授权域名，没配则回退当前 HTTP_HOST。
     *
     * 用法示例：`LicenseClient::appStoreList(['host' => LicenseService::effectiveHost(), ...])`
     */
    public static function effectiveHost(): string
    {
        $main = (string) Config::get('license_main_host', '');
        return $main !== '' ? $main : self::currentDomain();
    }

    /**
     * 读取别名域名列表。
     *
     * @return array<int, string>
     */
    public static function aliasHosts(): array
    {
        $raw = (string) Config::get('license_alias_hosts', '');
        if ($raw === '') return [];
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return [];
        return array_values(array_filter(array_map('strval', $decoded), static fn(string $v): bool => $v !== ''));
    }

    /**
     * 保存别名域名列表（整个数组覆盖）。
     *
     * 规则：去空白 → 去空行 → 去重 → 剔除和主授权域名一样的 → 最多保留前 MAX_ALIAS_HOSTS 个。
     *
     * @param string|array<int, string> $input 可以是 textarea 原文（一行一个）或数组
     */
    public static function saveAliasHosts($input): array
    {
        if (is_array($input)) {
            $lines = array_map('strval', $input);
        } else {
            $lines = preg_split('/\r\n|\r|\n/', (string) $input) ?: [];
        }

        $mainHost = strtolower((string) Config::get('license_main_host', ''));
        $seen = [];
        $out = [];
        foreach ($lines as $raw) {
            $v = strtolower(trim((string) $raw));
            if ($v === '') continue;
            if ($mainHost !== '' && $v === $mainHost) continue; // 和主的重了 → 跳过
            if (isset($seen[$v])) continue;                     // 已有 → 跳过
            $seen[$v] = true;
            $out[] = $v;
            if (count($out) >= self::MAX_ALIAS_HOSTS) break;
        }
        Config::set('license_alias_hosts', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $out;
    }

    // ---------- 线路配置 ----------

    /** 所有可用的授权服务器线路。 */
    public static function getAllLines(): array
    {
        return LicenseClient::lines();
    }

    /** 当前生效的线路索引（默认 0）。 */
    public static function currentLineIndex(): int
    {
        $lines = self::getAllLines();
        if ($lines === []) return 0;
        $idx = (int) (Config::get('license_line_index') ?? 0);
        if ($idx < 0 || $idx >= count($lines)) $idx = 0;
        return $idx;
    }

    /** 切换当前线路。 @throws RuntimeException 索引越界 */
    public static function switchLine(int $idx): void
    {
        $lines = self::getAllLines();
        if ($idx < 0 || $idx >= count($lines)) {
            throw new RuntimeException('无效的线路索引');
        }
        Config::set('license_line_index', (string) $idx);
    }

    /** 拉取当前线路的代理商配置（售后联系方式、购买地址等）。 */
    public static function fetchAgentConfig(): array
    {
        return LicenseClient::agentConfig();
    }

    /** 取当前请求域名（去端口）。*/
    private static function currentDomain(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') return 'localhost';
        $p = strpos($host, ':');
        return strtolower($p === false ? $host : substr($host, 0, $p));
    }
}
