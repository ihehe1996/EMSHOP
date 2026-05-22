<?php

declare(strict_types=1);

/**
 * 模板数据模型(配置启用版)。
 *
 * 设计原则:磁盘是真理,启用状态是配置值。
 *   - 元数据(title/version/author/template_url/description/preview/...)从 parseHeader 实时读
 *   - 主站 PC/Mobile 启用 → em_config.active_template_pc / active_template_mobile
 *   - 商户 PC/Mobile 启用 → em_merchant.active_template_pc / active_template_mobile
 *   - 模板配置 → TemplateStorage(em_options 表,与插件 Storage 同表不同 type)
 *   - "已装"判定:主站 = 磁盘有目录;商户 = AppPurchaseModel::isPurchased
 *
 * scope 约定:
 *   'main'          → 主站作用域
 *   'merchant_{id}' → 商户 id 对应的独立作用域
 * 物理文件(content/template/xxx)在全站共享一份,启用状态按 scope 隔离。
 */
final class TemplateModel
{
    /** 主站启用模板 config key */
    private const MAIN_ACTIVE_PC_KEY     = 'active_template_pc';
    private const MAIN_ACTIVE_MOBILE_KEY = 'active_template_mobile';
    private const COOKIE_PREFIX          = 'em_front_template_';

    private string $templateRoot;
    private string $merchantTable;

    public function __construct()
    {
        $this->templateRoot  = EM_ROOT . '/content/template';
        $this->merchantTable = Database::prefix() . 'merchant';
    }

    // -----------------------------------------------------------------
    // 磁盘:扫描 / 解析头部 / 路径辅助
    // -----------------------------------------------------------------

    /**
     * 扫 content/template 目录,返回磁盘上的模板 + parseHeader 元数据。
     *
     * @return array<string, array<string, mixed>>
     */
    public function scanTemplates(): array
    {
        $templates = [];
        if (!is_dir($this->templateRoot)) return $templates;

        $entries = scandir($this->templateRoot) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $dir = $this->templateRoot . '/' . $entry;
            if (!is_dir($dir)) continue;
            $headerFile = $dir . '/header.php';
            if (!is_file($headerFile)) continue;

            $header = $this->parseHeader($headerFile);
            if ($header === null) continue;

            $header['name']          = $entry;
            $header['header_file']   = 'header.php';
            $header['callback_file'] = is_file($dir . '/callback.php') ? 'callback.php' : '';
            $header['plugin_file']   = is_file($dir . '/plugin.php') ? 'plugin.php' : '';
            $header['preview']       = is_file($dir . '/preview.jpg') ? '/content/template/' . $entry . '/preview.jpg' : '';
            $templates[$entry] = $header;
        }
        return $templates;
    }

    /**
     * 解析模板 header.php 中的头注释。
     *
     * @return array<string, mixed>|null
     */
    public function parseHeader(string $headerFile): ?array
    {
        if (!is_file($headerFile) || !is_readable($headerFile)) return null;
        $fp = fopen($headerFile, 'r');
        if ($fp === false) return null;

        $header = [
            'name' => '', 'title' => '', 'version' => '1.0.0',
            'author' => '', 'author_url' => '',
            'template_url' => '', 'description' => '',
            'preview' => '',
        ];

        $inComment = false;
        while (($line = fgets($fp)) !== false) {
            $line = rtrim($line);
            if (preg_match('/^\s*\/\*\s*$/', $line) || preg_match('/^\s*\/\*\*\s*$/', $line)) {
                $inComment = true; continue;
            }
            if ($inComment && preg_match('/^\s*\*\/\s*$/', $line)) { $inComment = false; continue; }

            if ($inComment) {
                $line = preg_replace('/^\s*\*\s*/', '', $line);
                $line = trim((string) $line);

                if (preg_match('/^Template\s+Name:\s*(.+)$/i', $line, $m)) { $header['title'] = trim($m[1]); continue; }
                if (preg_match('/^Version:\s*(.+)$/i', $line, $m))         { $header['version'] = trim($m[1]); continue; }
                if (preg_match('/^Template\s+Url:\s*(.+)$/i', $line, $m))  { $header['template_url'] = trim($m[1]); continue; }
                if (preg_match('/^Description:\s*(.+)$/i', $line, $m))     { $header['description'] = trim($m[1]); continue; }
                if (preg_match('/^Author:\s*(.+)$/i', $line, $m))          { $header['author'] = trim($m[1]); continue; }
                if (preg_match('/^Author\s+Url:\s*(.+)$/i', $line, $m))    { $header['author_url'] = trim($m[1]); continue; }
            }
        }
        fclose($fp);

        if ($header['title'] === '') return null;
        return $header;
    }

    /**
     * 磁盘上是否存在该模板目录 + header.php。
     */
    public function existsOnDisk(string $name): bool
    {
        if ($name === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $name)) return false;
        $dir = $this->templateRoot . '/' . $name;
        return is_dir($dir) && is_file($dir . '/header.php');
    }

    // -----------------------------------------------------------------
    // 列表 API
    // -----------------------------------------------------------------

    /**
     * 列表 API:扫磁盘 + 标注启用状态(给 admin/template.php 用)。
     *
     * 返回每条 = 磁盘 header + is_active_pc / is_active_mobile / has_setting。
     *
     * @return array<string, array<string, mixed>>
     */
    public function scanWithStatus(string $scope): array
    {
        $disk = $this->scanTemplates();
        if ($disk === []) return [];

        // 一次取出当前 scope 的 PC/Mobile 启用名,避免每张卡单独查
        $activePc     = $this->getActiveTheme('pc', $scope);
        $activeMobile = $this->getActiveTheme('mobile', $scope);

        foreach ($disk as $name => &$info) {
            $info['is_active_pc']     = ($activePc !== '' && $activePc === $name);
            $info['is_active_mobile'] = ($activeMobile !== '' && $activeMobile === $name);
            $info['has_setting']      = $this->hasSettingFile($name);
        }
        unset($info);
        return $disk;
    }

    // -----------------------------------------------------------------
    // 状态判断
    // -----------------------------------------------------------------

    /**
     * 该模板在指定 scope 下是否"已安装"。
     *
     * 主站:磁盘有目录视为已装;
     * 商户:em_app_purchase 有 (merchant_id, name, 'template') 记录视为已装。
     */
    public function isInstalled(string $name, string $scope): bool
    {
        if ($scope === 'main') {
            return $this->existsOnDisk($name);
        }
        $merchantId = $this->parseMerchantScope($scope);
        if ($merchantId <= 0) return false;
        return (new AppPurchaseModel())->isPurchased($merchantId, $name, 'template');
    }

    // -----------------------------------------------------------------
    // 启用 / 切换(PC / 手机端)
    // -----------------------------------------------------------------

    /**
     * 取指定 scope + 终端当前启用的模板名(没启用返回空字符串)。
     */
    public function getActiveTheme(string $client, string $scope): string
    {
        if ($scope === 'main') {
            return (string) Config::get($this->getConfigKeyFor($client), '');
        }
        $merchantId = $this->parseMerchantScope($scope);
        if ($merchantId <= 0) return '';
        $col = $this->getMerchantColumnFor($client);
        $row = Database::fetchOne(
            'SELECT `' . $col . '` AS v FROM `' . $this->merchantTable . '` WHERE `id` = ? LIMIT 1',
            [$merchantId]
        );
        return $row !== null ? (string) ($row['v'] ?? '') : '';
    }

    /**
     * 获取当前请求最终应使用的模板。
     *
     * 优先级：Cookie 指定模板 > 后台启用模板 > 商户站回退主站同终端启用模板。
     * Cookie 命中但模板不存在 / 当前 scope 未安装时，自动忽略并回退后台设置。
     */
    public function getEffectiveTheme(string $client, string $scope): string
    {
        $cookieTheme = $this->getCookieOverrideTheme($client, $scope);
        if ($cookieTheme !== '') {
            return $cookieTheme;
        }
        $active = $this->getActiveTheme($client, $scope);
        if ($active !== '') {
            return $active;
        }

        return $this->resolveMerchantFallbackTheme($client, $scope);
    }

    /**
     * 读取当前 scope + 终端的前台模板覆盖 Cookie。
     */
    public function getCookieOverrideTheme(string $client, string $scope): string
    {
        $cookieName = self::buildCookieName($client, $scope);
        $name = trim((string) ($_COOKIE[$cookieName] ?? ''));
        if (!$this->isCookieThemeAllowed($name, $scope)) {
            return '';
        }
        return $name;
    }

    /**
     * 写入前台模板覆盖 Cookie。
     */
    public function setCookieOverrideTheme(string $client, string $scope, string $name, int $ttl = 31536000): bool
    {
        $name = trim($name);
        if (!$this->isCookieThemeAllowed($name, $scope)) {
            return false;
        }

        $cookieName = self::buildCookieName($client, $scope);
        $expires = time() + max(60, $ttl);
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        if (PHP_VERSION_ID >= 70300) {
            setcookie($cookieName, $name, [
                'expires'  => $expires,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie($cookieName, $name, $expires, '/');
        }

        $_COOKIE[$cookieName] = $name;
        return true;
    }

    /**
     * 清除前台模板覆盖 Cookie，回退到后台启用模板。
     */
    public function clearCookieOverrideTheme(string $client, string $scope): void
    {
        $cookieName = self::buildCookieName($client, $scope);
        $expires = time() - 3600;
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        if (PHP_VERSION_ID >= 70300) {
            setcookie($cookieName, '', [
                'expires'  => $expires,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie($cookieName, '', $expires, '/');
        }

        unset($_COOKIE[$cookieName]);
    }

    /**
     * 把模板设为该 scope + 终端的当前启用模板。同终端只允许一个启用,赋值即覆盖。
     */
    public function setActiveTheme(string $client, string $name, string $scope): bool
    {
        if ($scope === 'main') {
            Config::set($this->getConfigKeyFor($client), $name);
            return true;
        }
        $merchantId = $this->parseMerchantScope($scope);
        if ($merchantId <= 0) return false;
        $col = $this->getMerchantColumnFor($client);
        Database::execute(
            'UPDATE `' . $this->merchantTable . '` SET `' . $col . '` = ? WHERE `id` = ? LIMIT 1',
            [$name, $merchantId]
        );
        return true;
    }

    /**
     * 清除该 scope + 终端的启用状态(写入空字符串,等同"未启用任何模板")。
     */
    public function clearActiveTheme(string $client, string $scope): bool
    {
        return $this->setActiveTheme($client, '', $scope);
    }

    /**
     * 该模板是否在指定 scope + 终端启用。
     */
    public function isActive(string $name, string $client, string $scope): bool
    {
        $active = $this->getActiveTheme($client, $scope);
        return $active !== '' && $active === $name;
    }

    // -----------------------------------------------------------------
    // 文件路径辅助
    // -----------------------------------------------------------------

    public function hasSettingFile(string $name): bool
    {
        return is_file($this->templateRoot . '/' . $name . '/setting.php');
    }

    public function getSettingFilePath(string $name): string
    {
        return $this->templateRoot . '/' . $name . '/setting.php';
    }

    public function getCallbackFilePath(string $name): string
    {
        return $this->templateRoot . '/' . $name . '/callback.php';
    }

    public function getPluginFilePath(string $name): string
    {
        return $this->templateRoot . '/' . $name . '/plugin.php';
    }

    public function getPreviewUrl(string $name): string
    {
        return is_file($this->templateRoot . '/' . $name . '/preview.jpg')
            ? '/content/template/' . $name . '/preview.jpg'
            : '';
    }

    /**
     * 根据当前请求识别终端类型。
     */
    public static function detectClientFromRequest(): string
    {
        $device = trim((string) ($_GET['device'] ?? ''));
        if ($device === 'mobile' || $device === 'pc') {
            return $device;
        }

        $agent = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        foreach (['mobile', 'android', 'iphone', 'ipad', 'ipod', 'windows phone'] as $keyword) {
            if ($agent !== '' && strpos($agent, $keyword) !== false) {
                return 'mobile';
            }
        }

        return 'pc';
    }

    /**
     * 构建当前 scope + 终端的模板覆盖 Cookie 名。
     */
    public static function buildCookieName(string $client, string $scope): string
    {
        $scope = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $scope) ?: 'main';
        return self::COOKIE_PREFIX . $scope . '_' . $client;
    }

    // -----------------------------------------------------------------
    // 内部辅助
    // -----------------------------------------------------------------

    /**
     * 商户站未配置 PC/Mobile 启用模板时，回退主站同终端已启用的模板（磁盘须存在）。
     */
    private function resolveMerchantFallbackTheme(string $client, string $scope): string
    {
        if ($this->parseMerchantScope($scope) <= 0) {
            return '';
        }
        $mainTheme = trim($this->getActiveTheme($client, 'main'));
        if ($mainTheme === '' || !$this->existsOnDisk($mainTheme)) {
            return '';
        }
        return $mainTheme;
    }

    /**
     * 'merchant_42' → 42;非商户 scope 返回 0。
     */
    private function parseMerchantScope(string $scope): int
    {
        if (strncmp($scope, 'merchant_', 9) !== 0) return 0;
        $id = (int) substr($scope, 9);
        return $id > 0 ? $id : 0;
    }

    /**
     * client → 主站 config key。
     */
    private function getConfigKeyFor(string $client): string
    {
        if ($client === 'pc')     return self::MAIN_ACTIVE_PC_KEY;
        if ($client === 'mobile') return self::MAIN_ACTIVE_MOBILE_KEY;
        throw new RuntimeException('未知终端类型: ' . $client);
    }

    /**
     * client → em_merchant 列名。
     */
    private function getMerchantColumnFor(string $client): string
    {
        if ($client === 'pc')     return 'active_template_pc';
        if ($client === 'mobile') return 'active_template_mobile';
        throw new RuntimeException('未知终端类型: ' . $client);
    }

    /**
     * Cookie 覆盖模板必须是合法名字、磁盘存在，且在当前 scope 下已安装。
     */
    private function isCookieThemeAllowed(string $name, string $scope): bool
    {
        if ($name === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $name)) {
            return false;
        }
        if (!$this->existsOnDisk($name)) {
            return false;
        }
        return $this->isInstalled($name, $scope);
    }
}
