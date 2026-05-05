<?php

declare(strict_types=1);

/**
 * 货币模型。
 *
 * 数据表：em_currency
 *
 * ============================================================
 * 多币种设计（方案 A：单主货币 + 前台显示换算）
 * ============================================================
 * 数据库里所有金额（订单/余额/佣金/商品价）都按**主货币** ×1000000 存。
 * 其他货币仅用于前台展示换算（把主货币金额按 rate 换算成目标货币显示）。
 *
 * rate 约定：
 *     rate = 1 单位目标货币 = 多少个主货币
 *
 *   示例（主货币 = CNY）：
 *     CNY.rate = 1.00      （1 CNY = 1 CNY，主货币自己 rate 恒为 1）
 *     USD.rate = 7.23      （1 USD = 7.23 CNY）
 *     EUR.rate = 9.88      （1 EUR = 9.88 CNY）
 *
 * 换算公式（raw 为数据库存的主货币 ×1000000 值）：
 *     主货币数值   = raw / 1000000
 *     目标货币数值 = (raw / 1000000) / targetRate
 *
 * 数据库 rate 列也按 ×1000000 整数存（bigint），避免浮点精度问题。
 *
 * 主货币锁定：
 *   主货币一旦设定就不允许切换 —— 数据库里海量金额字段的语义都绑定了主货币，
 *   切换会造成所有历史数据漂移。setPrimary() 只在表中没有主货币时生效。
 * ============================================================
 */
final class Currency
{
    private static ?Currency $instance = null;

    /** @var array<int, array<string, mixed>> */
    private array $cache = [];

    private function __construct()
    {
    }

    public static function getInstance(): Currency
    {
        if (self::$instance === null) {
            self::$instance = new Currency();
        }
        return self::$instance;
    }

    /**
     * 读取所有货币列表（按 sort_order 排序）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        if ($this->cache === []) {
            $this->reload();
        }
        return $this->cache;
    }

    /**
     * 刷新缓存。
     *
     * @return array<int, array<string, mixed>>
     */
    public function reload(): array
    {
        $table = Database::prefix() . 'currency';
        $rows = Database::query("SELECT * FROM `{$table}` ORDER BY `sort_order` ASC, `id` ASC");
        $this->cache = $rows;
        return $this->cache;
    }

    /**
     * 根据货币代码获取一条记录。
     */
    public function getByCode(string $code): ?array
    {
        $table = Database::prefix() . 'currency';
        $row = Database::fetchOne(
            "SELECT * FROM `{$table}` WHERE `code` = ?",
            [strtoupper($code)]
        );
        return $row ?: null;
    }

    /**
     * 根据 ID 获取一条记录。
     */
    public function getById(int $id): ?array
    {
        $table = Database::prefix() . 'currency';
        $row = Database::fetchOne(
            "SELECT * FROM `{$table}` WHERE `id` = ?",
            [$id]
        );
        return $row ?: null;
    }

    /**
     * 获取主货币。
     */
    /**
     * 获取"前台默认货币"（is_frontend_default=1 且 enabled=1 的唯一一条）。
     * 访客首次进站、cookie 里没选过时用它作为展示默认；没设时 null → 由调用方回退主货币。
     *
     * @return array<string, mixed>|null
     */
    public function getFrontendDefault(): ?array
    {
        $table = Database::prefix() . 'currency';
        return Database::fetchOne(
            "SELECT * FROM `{$table}` WHERE `is_frontend_default` = 1 AND `enabled` = 1 LIMIT 1"
        );
    }

    /**
     * 设置"前台默认"：先把其他所有货币置 0，再把指定 id 置 1。事务里做以保唯一性。
     * 被禁用的货币不允许设为前台默认（UX 上避免"默认但不可见"的怪状态）。
     */
    public function setFrontendDefault(int $id): bool
    {
        $item = $this->getById($id);
        if ($item === null) return false;
        if ((int) ($item['enabled'] ?? 1) !== 1) {
            throw new RuntimeException('该货币已禁用，请先启用再设为前台默认');
        }
        $table = Database::prefix() . 'currency';
        Database::begin();
        try {
            Database::execute("UPDATE `{$table}` SET `is_frontend_default` = 0, `updated_at` = ? WHERE `is_frontend_default` = 1", [time()]);
            Database::execute("UPDATE `{$table}` SET `is_frontend_default` = 1, `updated_at` = ? WHERE `id` = ?", [time(), $id]);
            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
        $this->reload();
        return true;
    }

    public function getPrimary(): ?array
    {
        $table = Database::prefix() . 'currency';
        $row = Database::fetchOne(
            "SELECT * FROM `{$table}` WHERE `is_primary` = 1 LIMIT 1"
        );
        return $row ?: null;
    }

    /**
     * 添加货币。
     */
    public function add(string $code, string $name, string $symbol, float $rate): bool
    {
        $code = strtoupper(trim($code));
        if ($code === '' || !preg_match('/^[A-Z]{3}$/', $code)) {
            return false;
        }
        if ($name === '') {
            return false;
        }
        if ($rate < 0) {
            return false;
        }

        $table = Database::prefix() . 'currency';
        if ($this->getByCode($code) !== null) {
            return false;
        }

        // 获取最大 sort_order
        $maxSort = Database::fetchOne("SELECT MAX(`sort_order`) AS m FROM `{$table}`");
        $sortOrder = ((int) ($maxSort['m'] ?? 0)) + 1;

        $now = time();
        $affected = Database::execute(
            "INSERT INTO `{$table}` (`code`, `name`, `symbol`, `rate`, `is_primary`, `enabled`, `sort_order`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, 0, 1, ?, ?, ?)",
            [$code, $name, $symbol, (int) round($rate * 1000000), $sortOrder, $now, $now]
        );

        if ($affected > 0) {
            $this->reload();
        }

        return $affected > 0;
    }

    /**
     * 更新货币。
     *
     * @param int $id 货币记录 ID
     * @param array<string, mixed> $data 要更新的字段 ['name' => ..., 'symbol' => ..., 'rate' => ...]
     */
    public function update(int $id, array $data): bool
    {
        $item = $this->getById($id);
        if ($item === null) {
            return false;
        }

        $table = Database::prefix() . 'currency';
        $fields = [];
        $params = [];

        if (array_key_exists('name', $data)) {
            $fields[] = '`name` = ?';
            $params[] = trim((string) $data['name']);
        }
        if (array_key_exists('symbol', $data)) {
            $fields[] = '`symbol` = ?';
            $params[] = trim((string) $data['symbol']);
        }
        if (array_key_exists('rate', $data)) {
            $fields[] = '`rate` = ?';
            $params[] = max(0, (int) round((float) $data['rate'] * 1000000));
        }
        if (array_key_exists('enabled', $data)) {
            $fields[] = '`enabled` = ?';
            $params[] = (int) $data['enabled'] === 1 ? 1 : 0;
        }
        // is_primary 不通过 update() 处理，必须走 setPrimary()

        if (empty($fields)) {
            return false;
        }

        $fields[] = '`updated_at` = ?';
        $params[] = time();
        $params[] = $id;

        $affected = Database::execute(
            "UPDATE `{$table}` SET " . implode(', ', $fields) . " WHERE `id` = ?",
            $params
        );

        if ($affected >= 0) {
            $this->reload();
        }

        return true;
    }

    /**
     * 删除货币（主货币不可删除）。
     */
    public function delete(int $id): bool
    {
        $item = $this->getById($id);
        if ($item === null) {
            return false;
        }
        if ((int) $item['is_primary'] === 1) {
            return false;
        }

        $table = Database::prefix() . 'currency';
        $affected = Database::execute("DELETE FROM `{$table}` WHERE `id` = ?", [$id]);

        if ($affected > 0) {
            $this->reload();
        }

        return $affected > 0;
    }

    /**
     * 初始化主货币。
     *
     * ⚠️ 主货币一旦设定就不允许再切换（数据库里所有金额都以主货币 ×1000000 存储，
     *    切换会导致所有历史数据语义漂移）。
     * 只在表中没有主货币时才会生效；否则返回 false。
     *
     * @param int $id 货币记录 ID
     */
    public function setPrimary(int $id): bool
    {
        $item = $this->getById($id);
        if ($item === null) {
            return false;
        }

        // 已存在主货币且不是自身 → 拒绝切换
        $current = $this->getPrimary();
        if ($current !== null && (int) $current['id'] !== $id) {
            return false;
        }

        $table = Database::prefix() . 'currency';
        $now = time();

        // 把目标货币标记为主货币；主货币的 rate 恒为 1（1000000）
        $affected = Database::execute(
            "UPDATE `{$table}` SET `is_primary` = 1, `rate` = 1000000, `updated_at` = ? WHERE `id` = ?",
            [$now, $id]
        );

        $this->reload();
        return $affected > 0;
    }

    /**
     * 切换货币启用状态（主货币不可禁用）。
     */
    public function toggle(int $id): bool
    {
        $item = $this->getById($id);
        if ($item === null) {
            return false;
        }
        if ((int) $item['is_primary'] === 1) {
            return false;
        }
        $currentEnabled = (int) ($item['enabled'] ?? 1) === 1;
        // 如果是当前"前台默认"且要改为禁用 → 拒绝（避免默认货币不可见的怪状态，管理员需先切默认再禁用）
        if ($currentEnabled && (int) ($item['is_frontend_default'] ?? 0) === 1) {
            throw new RuntimeException('该货币是前台默认货币，请先把默认切换到其他货币再禁用');
        }

        $table = Database::prefix() . 'currency';
        $newEnabled = $currentEnabled ? 0 : 1;
        $affected = Database::execute(
            "UPDATE `{$table}` SET `enabled` = ?, `updated_at` = ? WHERE `id` = ?",
            [$newEnabled, time(), $id]
        );

        if ($affected >= 0) {
            $this->reload();
        }
        return true;
    }

    /**
     * 读取访客当前展示货币代码。
     *
     * 解析顺序（上到下）：
     *   1) Cookie `em_currency`（用户手动切换过）
     *   2) DB 里 is_frontend_default=1 且启用的货币（管理员在后台设的前台默认）
     *   3) 主货币（最终兜底，任何时候都不为空）
     *
     * 返回一定是"存在且启用"的货币代码。
     */
    public static function visitorCode(): string
    {
        $self = self::getInstance();
        $primary = $self->getPrimary();
        $primaryCode = $primary ? (string) $primary['code'] : '';

        // 1) Cookie：用户自己选过的优先
        if (!empty($_COOKIE['em_currency'])) {
            $code = strtoupper(trim((string) $_COOKIE['em_currency']));
            if (preg_match('/^[A-Z]{3}$/', $code)) {
                $row = $self->getByCode($code);
                if ($row !== null && (int) ($row['enabled'] ?? 1) === 1) {
                    return $code;
                }
            }
        }
        // 2) 前台默认（后台配置；允许和主货币不一致 —— 记账基准 vs 展示默认解耦）
        $fd = $self->getFrontendDefault();
        if ($fd !== null) {
            return (string) $fd['code'];
        }
        // 3) 主货币兜底
        return $primaryCode;
    }

    /**
     * 拿到访客当前展示币种的符号。未配置 / 未命中时兜底主货币符号，再兜底 ¥。
     *
     * 用途：模板 / JS 里需要拼 "$12.34" 时用这个取 "$"；和 displayAmount() 语义一致，都是"按访客当前币种展示"。
     * 只是符号本身，不带数值 —— 涉及金额建议直接用 displayAmount / displayMain，它们返回带符号的完整字符串。
     */
    public static function visitorSymbol(): string
    {
        $self = self::getInstance();
        $code = self::visitorCode();
        $row = $code !== '' ? $self->getByCode($code) : null;
        $symbol = $row ? (string) ($row['symbol'] ?? '') : '';
        if ($symbol === '') {
            $primary = $self->getPrimary();
            $symbol = $primary ? (string) ($primary['symbol'] ?? '¥') : '¥';
        }
        return $symbol !== '' ? $symbol : '¥';
    }

    /**
     * 拿到下单时要写入订单的"展示货币快照"。
     * 返回 [code, rate]：code 空串 / rate 0 表示主货币，订单展示就按主货币走。
     *
     * @return array{0: string, 1: int}
     */
    public static function visitorSnapshot(): array
    {
        $self = self::getInstance();
        $primary = $self->getPrimary();
        $primaryCode = $primary ? (string) $primary['code'] : '';

        $code = self::visitorCode();
        // 访客币种 = 主货币 → 不用存快照，默认空串 + 0 省空间 + 清晰表达"无快照"
        if ($code === '' || $code === $primaryCode) {
            return ['', 0];
        }
        $row = $self->getByCode($code);
        if ($row === null) {
            return ['', 0];
        }
        return [$code, (int) $row['rate']];
    }

    /**
     * 把数据库里的主货币原始值（×1000000）按目标货币换算并格式化成展示字符串。
     *
     * @param int         $rawMainCents 数据库 BIGINT 字段值（主货币 ×1000000）
     * @param string|null $targetCode   目标货币代码；null = 走 visitorCode() 的回退链；空串 = 主货币
     * @param bool        $withSymbol   是否带货币符号（默认 true）
     * @param int         $decimals     小数位数（默认 2）
     */
    public static function displayAmount(
        int $rawMainCents,
        ?string $targetCode = null,
        bool $withSymbol = true,
        int $decimals = 2
    ): string {
        $self = self::getInstance();
        $primary = $self->getPrimary();
        if ($primary === null) {
            return number_format($rawMainCents / 1000000, $decimals, '.', '');
        }

        // 目标货币：null = 读访客当前选择；空串 = 强制主货币
        if ($targetCode === null) {
            $targetCode = self::visitorCode();
        }

        $target = null;
        if ($targetCode !== '') {
            $target = $self->getByCode($targetCode);
            if ($target !== null && (int) ($target['enabled'] ?? 1) !== 1) {
                $target = null;  // 已禁用 → 回退主货币
            }
        }
        if ($target === null) {
            $target = $primary;
        }

        $amountMain = $rawMainCents / 1000000;
        $targetRate = (int) ($target['rate'] ?? 0);
        // rate = 1 目标货币 = 多少主货币；目标货币数值 = 主货币数值 / targetRate
        $amountTarget = ($targetRate > 0) ? ($amountMain / ($targetRate / 1000000)) : $amountMain;

        $num = number_format($amountTarget, $decimals, '.', '');
        if (!$withSymbol) {
            return $num;
        }
        return ((string) ($target['symbol'] ?? '')) . $num;
    }

    /**
     * 按"主货币浮点元"展示金额（和 displayAmount 行为一致，但接口贴合模板现状）。
     *
     * 现有前台模板里金额早已是 `moneyFromDb()` 除过 ×1000000 的浮点元值，
     * 用 displayAmount 还得先乘回去再传 BIGINT，绕一圈。直接收元值更 DRY。
     *
     * @param float       $amountInMain 主货币金额（元，例如 12.34）
     * @param string|null $targetCode   目标货币；null = 读 visitorCode；空串 = 强制主货币
     * @param bool        $withSymbol   是否带货币符号（默认 true）
     * @param int         $decimals     小数位（默认 2）
     */
    public static function displayMain(
        float $amountInMain,
        ?string $targetCode = null,
        bool $withSymbol = true,
        int $decimals = 2
    ): string {
        // 复用 displayAmount：把元值还原成 BIGINT raw（×1000000，用 round 避免浮点精度丢失）
        $rawMainCents = (int) round($amountInMain * 1000000);
        return self::displayAmount($rawMainCents, $targetCode, $withSymbol, $decimals);
    }

    /**
     * 渲染前台"货币切换器"下拉 HTML 片段。
     *
     * 只输出已启用的货币；主货币永远在列表里；当前选中项用 visitorCode() 决定。
     * 下拉切换时 onchange 自动提交到 /user/currency_switch.php 写 cookie + 302 回当前页。
     *
     * 主题中直接回显即可：
     *   <?= Currency::switcherHtml() ?>
     *   或自定义外层 class：  <?= Currency::switcherHtml('em-currency-switcher--footer') ?>
     *
     * 整个组件不依赖 JS 框架，也不带样式，主题可自己覆盖外层 class。
     */
    public static function switcherHtml(string $extraClass = ''): string
    {
        $self = self::getInstance();
        $items = $self->all();
        if (empty($items)) {
            return '';
        }

        $current = self::visitorCode();
        $esc = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        $opts = '';
        foreach ($items as $row) {
            if ((int) ($row['enabled'] ?? 1) !== 1) {
                continue;
            }
            $code = (string) $row['code'];
            $sel = ($code === $current) ? ' selected' : '';
            // 显示格式：USD ($)
            $sym = (string) ($row['symbol'] ?? '');
            $label = $esc($code) . ($sym !== '' ? ' (' . $esc($sym) . ')' : '');
            $opts .= '<option value="' . $esc($code) . '"' . $sel . '>' . $label . '</option>';
        }

        $cls = 'em-currency-switcher' . ($extraClass !== '' ? ' ' . $esc($extraClass) : '');
        return '<form action="/user/currency_switch.php" method="post" class="' . $cls . '">'
             . '<select name="code" onchange="this.form.submit()">' . $opts . '</select>'
             . '<noscript><button type="submit">切换</button></noscript>'
             . '</form>';
    }

    /**
     * 按"冻结快照"展示订单金额 —— 用订单表上 display_currency_code + display_rate 快照，
     * 不受当前汇率变动影响。
     *
     * @param int    $rawMainCents 主货币 ×1000000 原始值
     * @param string $frozenCode   订单 display_currency_code（空串 = 按主货币展示）
     * @param int    $frozenRate   订单 display_rate（×1000000，0 = 按主货币展示）
     */
    public static function displaySnapshot(
        int $rawMainCents,
        string $frozenCode,
        int $frozenRate,
        bool $withSymbol = true,
        int $decimals = 2
    ): string {
        $self = self::getInstance();
        $primary = $self->getPrimary();

        // 快照为空 / 主货币 → 按主货币展示
        if ($frozenCode === '' || $frozenRate <= 0) {
            if ($primary === null) {
                return number_format($rawMainCents / 1000000, $decimals, '.', '');
            }
            $num = number_format($rawMainCents / 1000000, $decimals, '.', '');
            return $withSymbol ? ((string) ($primary['symbol'] ?? '')) . $num : $num;
        }

        $amountMain = $rawMainCents / 1000000;
        $amountTarget = $amountMain / ($frozenRate / 1000000);
        $num = number_format($amountTarget, $decimals, '.', '');
        if (!$withSymbol) {
            return $num;
        }
        // 符号从 currency 表查；查不到就用代码字符串兜底
        $row = $self->getByCode($frozenCode);
        $sym = $row ? (string) ($row['symbol'] ?? '') : '';
        return ($sym !== '' ? $sym : $frozenCode . ' ') . $num;
    }
}
