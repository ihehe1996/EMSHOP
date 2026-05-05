<?php

declare(strict_types=1);

/**
 * 插件数据模型(字符串启用列表版)。
 *
 * 设计原则:磁盘是真理,启用列表是字符串。
 *   - 元数据(title/version/author/category/icon/setting_file/...)全部 parseHeader 实时读
 *   - 主站启用列表 → em_config.enabled_plugins(逗号分隔 slug)
 *   - 商户启用列表 → em_merchant.enabled_plugins(每商户独立的逗号分隔 slug)
 *   - 商户已购清单 → em_app_purchase(由 AppPurchaseModel 维护,与启用解耦)
 *   - "已装"判定:主站 = 磁盘有目录;商户 = AppPurchaseModel::isPurchased
 *
 * scope 约定:
 *   'main'          → 主站作用域(走 Config::get/set)
 *   'merchant_{id}' → 商户 id 对应的独立作用域(走 em_merchant.enabled_plugins)
 *
 * 物理插件文件(content/plugin/xxx)在全站共享一份。
 */
final class PluginModel
{
    /**
     * 主站后台应用商店可见的所有分类(id => 名称)。
     * 单一来源(SSOT):admin/appstore.php 引用本常量。
     */
    public const MAIN_PLUGIN_CATEGORIES = [
        1  => '支付插件',
        2  => '商品类型',
        3  => '共享店铺',
        4  => '功能扩展',
        5  => '消息通知',
        6  => '系统美化',
        99 => '未归类',
    ];

    /**
     * 商户后台插件市场可见的分类(id => 名称)。
     */
    public const MERCHANT_PLUGIN_CATEGORIES = [
        1  => '功能扩展',
        2  => '消息通知',
        3  => '系统美化',
        99 => '未归类',
    ];

    /**
     * 系统级插件分类(id => 名称)。
     *
     * 这几个分类属于商城底层能力(支付通道/商品类型/共享店铺),由主站统一管理启停,
     * 商户站不能独立装/启停 —— init.php 加载商户 scope 时,会按主站启用名单中
     * category 命中本常量的部分自动注入,商户继承使用。
     *
     * id 与 MAIN_PLUGIN_CATEGORIES 的 category id 对齐。
     */
    public const SYSTEM_PLUGINS = [
        1 => '支付插件',
        2 => '商品类型',
        3 => '共享店铺',
    ];

    /** 主站启用列表的 config key */
    private const MAIN_ENABLED_KEY = 'enabled_plugins';

    private string $pluginRoot;
    private string $merchantTable;

    public function __construct()
    {
        $this->pluginRoot    = EM_ROOT . '/content/plugin';
        $this->merchantTable = Database::prefix() . 'merchant';
    }

    // -----------------------------------------------------------------
    // 磁盘:扫描 / 解析头部 / 路径辅助
    // -----------------------------------------------------------------

    /**
     * 扫描插件目录,返回磁盘上的所有插件 + parseHeader 元数据。
     *
     * 磁盘文件全站共享,不按 scope 隔离;由启用列表决定"在哪个 scope 下启用"。
     *
     * @return array<string, array<string, mixed>> 以 name 为 key
     */
    public function scanPlugins(): array
    {
        $plugins = [];
        if (!is_dir($this->pluginRoot)) return $plugins;

        $entries = scandir($this->pluginRoot) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $dir = $this->pluginRoot . '/' . $entry;
            if (!is_dir($dir)) continue;
            $mainFile = $dir . '/' . $entry . '.php';
            if (!is_file($mainFile)) continue;

            $header = $this->parseHeader($mainFile);
            if ($header === null) continue;

            $header['name']      = $entry;
            $header['main_file'] = $entry . '.php';
            // 顺手探测 setting/show/icon/preview(这些信息让调用方不必重复扫盘)
            if (is_file($dir . '/' . $entry . '_setting.php')) $header['setting_file'] = $entry . '_setting.php';
            if (is_file($dir . '/' . $entry . '_show.php'))    $header['show_file']    = $entry . '_show.php';
            if (is_file($dir . '/icon.png'))                   $header['icon']         = '/content/plugin/' . $entry . '/icon.png';
            elseif (is_file($dir . '/icon.gif'))               $header['icon']         = '/content/plugin/' . $entry . '/icon.gif';
            if (is_file($dir . '/preview.jpg'))                $header['preview']      = '/content/plugin/' . $entry . '/preview.jpg';

            $plugins[$entry] = $header;
        }
        return $plugins;
    }

    /**
     * 解析插件主文件头部注释。
     *
     * @return array<string, mixed>|null
     */
    public function parseHeader(string $pluginFile): ?array
    {
        if (!is_file($pluginFile) || !is_readable($pluginFile)) return null;
        $fp = fopen($pluginFile, 'r');
        if ($fp === false) return null;

        $header = [
            'name' => '', 'title' => '', 'version' => '1.0.0',
            'author' => '', 'author_url' => '',
            'description' => '', 'category' => '',
            'icon' => '', 'preview' => '',
            'setting_file' => '', 'show_file' => '',
        ];

        $inComment = false;
        while (($line = fgets($fp)) !== false) {
            $line = rtrim($line);
            if (preg_match('/^\s*\/\*\*\s*$/', $line)) { $inComment = true; continue; }
            if ($inComment && preg_match('/^\s*\*\/\s*$/', $line)) { $inComment = false; continue; }

            if ($inComment) {
                $line = preg_replace('/^\s*\*\s*/', '', $line);
                $line = trim($line);

                if (preg_match('/^Plugin\s+Name:\s*(.+)$/i', $line, $m)) { $header['title'] = trim($m[1]); continue; }
                if (preg_match('/^Version:\s*(.+)$/i', $line, $m))      { $header['version'] = trim($m[1]); continue; }
                if (preg_match('/^Plugin\s+URL:\s*(.+)$/i', $line, $m)) { $header['author_url'] = trim($m[1]); continue; }
                if (preg_match('/^Description:\s*(.+)$/i', $line, $m))  { $header['description'] = trim($m[1]); continue; }
                if (preg_match('/^Author:\s*(.+)$/i', $line, $m))       { $header['author'] = trim($m[1]); continue; }
                if (preg_match('/^Author\s+URL:\s*(.+)$/i', $line, $m)) { $header['author_url'] = trim($m[1]); continue; }
                if (preg_match('/^Category:\s*(.+)$/i', $line, $m))     { $header['category'] = trim($m[1]); continue; }
                // @标签写法兼容
                if (preg_match('/^@(?:PluginName|Title)\s+(.+)$/i', $line, $m)) {
                    if ($header['title'] === '') $header['title'] = trim($m[1]); continue;
                }
                if (preg_match('/^@Version\s+(.+)$/i', $line, $m))     { $header['version'] = trim($m[1]); continue; }
                if (preg_match('/^@Author(?:URL)?\s+(.+)$/i', $line, $m)) {
                    if (stripos($line, '@AuthorURL') === 0) $header['author_url'] = trim($m[1]);
                    else                                    $header['author']     = trim($m[1]);
                    continue;
                }
                if (preg_match('/^@Description\s+(.+)$/i', $line, $m)) { $header['description'] = trim($m[1]); continue; }
                if (preg_match('/^@Category\s+(.+)$/i', $line, $m))    { $header['category'] = trim($m[1]); continue; }
            }

            if (!$inComment && preg_match('/^\s*<\?php\s/', $line)) break;
        }
        fclose($fp);

        if ($header['title'] === '' && $header['name'] === '') return null;
        return $header;
    }

    /**
     * 磁盘上是否存在该插件目录 + 主文件。
     */
    public function existsOnDisk(string $name): bool
    {
        if ($name === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) return false;
        $dir = $this->pluginRoot . '/' . $name;
        return is_dir($dir) && is_file($dir . '/' . $name . '.php');
    }

    // -----------------------------------------------------------------
    // 启用列表存取(scope 维度)
    // -----------------------------------------------------------------

    /**
     * 取指定 scope 下已启用的插件名列表。
     *
     * @return array<int, string>
     */
    public function getEnabledNames(string $scope): array
    {
        $raw = $this->readEnabledRaw($scope);
        if ($raw === '') return [];
        $names = array_map('trim', explode(',', $raw));
        return array_values(array_filter($names, static fn ($n) => $n !== ''));
    }

    /**
     * 读启用列表的原始字符串(给内部用)。
     */
    private function readEnabledRaw(string $scope): string
    {
        if ($scope === 'main') {
            return (string) Config::get(self::MAIN_ENABLED_KEY, '');
        }
        $merchantId = $this->parseMerchantScope($scope);
        if ($merchantId <= 0) return '';
        $row = Database::fetchOne(
            'SELECT `enabled_plugins` FROM `' . $this->merchantTable . '` WHERE `id` = ? LIMIT 1',
            [$merchantId]
        );
        return $row !== null ? (string) ($row['enabled_plugins'] ?? '') : '';
    }

    /**
     * 写启用列表。自动去重,保持插入顺序,排除空字符串。
     *
     * @param array<int, string> $names
     */
    private function writeEnabledNames(string $scope, array $names): void
    {
        // 去重并清白
        $clean = [];
        foreach ($names as $n) {
            $n = trim((string) $n);
            if ($n === '') continue;
            $clean[$n] = true;
        }
        $value = implode(',', array_keys($clean));

        if ($scope === 'main') {
            Config::set(self::MAIN_ENABLED_KEY, $value);
            return;
        }
        $merchantId = $this->parseMerchantScope($scope);
        if ($merchantId <= 0) {
            throw new InvalidArgumentException('非法 scope: ' . $scope);
        }
        Database::execute(
            'UPDATE `' . $this->merchantTable . '` SET `enabled_plugins` = ? WHERE `id` = ? LIMIT 1',
            [$value, $merchantId]
        );
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

    // -----------------------------------------------------------------
    // 启停 / 状态
    // -----------------------------------------------------------------

    /**
     * 启用插件:加入 scope 的 enabled 列表(已启用则 no-op)。
     *
     * 主站:磁盘有就能启用(lazy);
     * 商户:调用方要先确保商户已购(AppPurchaseModel::isPurchased),本方法不再校验,
     *       磁盘缺失才返回 false。
     */
    public function enable(string $name, string $scope): bool
    {
        if (!$this->existsOnDisk($name)) return false;

        $names = $this->getEnabledNames($scope);
        if (in_array($name, $names, true)) return true; // 已启用,no-op
        $names[] = $name;
        $this->writeEnabledNames($scope, $names);
        return true;
    }

    /**
     * 禁用插件:从 scope 的 enabled 列表剔除(本来就不在则 no-op)。
     */
    public function disable(string $name, string $scope): bool
    {
        $names = $this->getEnabledNames($scope);
        if (!in_array($name, $names, true)) return true; // 本来就不在
        $names = array_values(array_filter($names, static fn ($n) => $n !== $name));
        $this->writeEnabledNames($scope, $names);
        return true;
    }

    /**
     * 卸载插件:语义上等同于 disable —— 把 slug 从 enabled 列表剔除。
     *
     * 与 disable 的区别在调用方上下文:
     *   - disable:暂停使用,callback_rm 不跑、Storage 不清,随时可再启用
     *   - uninstall:调用方在前后会调插件 callback_rm + 清 Storage,清理插件私有数据
     *
     * 本方法只负责修改启用列表;callback_rm / Storage 清理由 admin/plugin.php 处理。
     */
    public function uninstall(string $name, string $scope): bool
    {
        return $this->disable($name, $scope);
    }

    /**
     * 该插件在指定 scope 下是否启用(在 enabled 列表里)。
     */
    public function isEnabled(string $name, string $scope): bool
    {
        return in_array($name, $this->getEnabledNames($scope), true);
    }

    /**
     * 该插件在指定 scope 下是否"已安装"。
     *
     * 主站:磁盘有目录视为已装;
     * 商户:em_app_purchase 有 (merchant_id, name, 'plugin') 记录视为已装。
     */
    public function isInstalled(string $name, string $scope): bool
    {
        if ($scope === 'main') {
            return $this->existsOnDisk($name);
        }
        $merchantId = $this->parseMerchantScope($scope);
        if ($merchantId <= 0) return false;
        return (new AppPurchaseModel())->isPurchased($merchantId, $name, 'plugin');
    }

    // -----------------------------------------------------------------
    // 列表 API
    // -----------------------------------------------------------------

    /**
     * 列表 API:扫磁盘 + 标注启用状态(给 admin/plugin.php 用)。
     *
     * 返回每条 = 磁盘 header + is_enabled。
     *
     * @return array<string, array<string, mixed>>
     */
    public function scanWithStatus(string $scope): array
    {
        $disk = $this->scanPlugins();
        if ($disk === []) return [];

        $enabledSet = array_flip($this->getEnabledNames($scope));
        foreach ($disk as $name => &$info) {
            $info['is_enabled'] = isset($enabledSet[$name]);
        }
        unset($info);
        return $disk;
    }

    // -----------------------------------------------------------------
    // 启用名单 / runtime / 按分类筛(走 parseHeader)
    // -----------------------------------------------------------------

    /**
     * init.php 用的运行时加载名单。
     *
     * 主站:返回 scope='main' 启用的所有插件
     * 商户:返回 scope='merchant_X' 启用的 + 主站启用且 header.category 命中 SYSTEM_PLUGINS 的
     *
     * SYSTEM_PLUGINS 过滤靠 parseHeader(磁盘元数据);
     * SYSTEM_PLUGINS 插件通常仅 3-5 个,parseHeader 开销可忽略。
     *
     * @return array<int, string>
     */
    public function getRuntimeNames(string $scope): array
    {
        if ($scope === 'main') {
            return $this->getEnabledNames('main');
        }

        $merchantEnabled = $this->getEnabledNames($scope);
        $mainEnabled     = $this->getEnabledNames('main');
        $systemInherited = [];
        foreach ($mainEnabled as $n) {
            $header = $this->parseHeader($this->pluginRoot . '/' . $n . '/' . $n . '.php');
            // 主站启用的插件中,category 命中 SYSTEM_PLUGINS 的部分由商户继承使用
            if ($header && in_array((string) ($header['category'] ?? ''), self::SYSTEM_PLUGINS, true)) {
                $systemInherited[] = $n;
            }
        }
        return array_values(array_unique(array_merge($merchantEnabled, $systemInherited)));
    }

    /**
     * 取指定 scope + 指定分类下的启用插件(给 PaymentService 等用)。
     *
     * 不依赖 DB.category(已删),改为 PHP 侧过滤 parseHeader 的 category 字段。
     *
     * @return array<int, array{name:string, main_file:string}>
     */
    public function getEnabledByCategory(string $category, string $scope): array
    {
        $enabled = $this->getEnabledNames($scope);
        $result = [];
        foreach ($enabled as $n) {
            $header = $this->parseHeader($this->pluginRoot . '/' . $n . '/' . $n . '.php');
            if ($header && (string) ($header['category'] ?? '') === $category) {
                $result[] = ['name' => $n, 'main_file' => $n . '.php'];
            }
        }
        return $result;
    }

}
