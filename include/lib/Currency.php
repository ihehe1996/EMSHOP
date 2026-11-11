<?php

declare(strict_types=1);

/**
 * 货币（兼容门面）。
 *
 * 实际实现已迁到 App\Support\Currency 实例服务；门面保留 getInstance() 和全部
 * 静态辅助方法，同时代理实例方法，`Currency::displayAmount()` 与
 * `Currency::getInstance()->getPrimary()` 两种调用方式都不变。
 */
final class Currency
{
    private static ?Currency $instance = null;

    /** @var \App\Support\Currency */
    private $service;

    private function __construct()
    {
        if (!class_exists(\App\Support\Currency::class)) {
            $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
            require_once $root . '/app/Support/Currency.php';
        }
        $this->service = new \App\Support\Currency();
    }

    /**
     * 获取货币门面单例（原有 API）。
     */
    public static function getInstance(): Currency
    {
        if (self::$instance === null) {
            self::$instance = new Currency();
        }
        return self::$instance;
    }

    /**
     * 获取内部货币服务实例（供容器注册使用）。
     */
    public static function service(): \App\Support\Currency
    {
        return self::getInstance()->service;
    }

    // ====================== 实例 API ======================

    /**
     * 读取所有货币列表（按 sort_order 排序）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->service->all();
    }

    /**
     * 刷新缓存。
     *
     * @return array<int, array<string, mixed>>
     */
    public function reload(): array
    {
        return $this->service->reload();
    }

    /**
     * 根据货币代码获取一条记录。
     */
    public function getByCode(string $code): ?array
    {
        return $this->service->getByCode($code);
    }

    /**
     * 根据 ID 获取一条记录。
     */
    public function getById(int $id): ?array
    {
        return $this->service->getById($id);
    }

    /**
     * 获取"前台默认货币"。
     *
     * @return array<string, mixed>|null
     */
    public function getFrontendDefault(): ?array
    {
        return $this->service->getFrontendDefault();
    }

    /**
     * 设置"前台默认"。
     */
    public function setFrontendDefault(int $id): bool
    {
        return $this->service->setFrontendDefault($id);
    }

    /**
     * 获取主货币。
     *
     * @return array<string, mixed>|null
     */
    public function getPrimary(): ?array
    {
        return $this->service->getPrimary();
    }

    /**
     * 添加货币。
     */
    public function add(string $code, string $name, string $symbol, float $rate): bool
    {
        return $this->service->add($code, $name, $symbol, $rate);
    }

    /**
     * 更新货币。
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        return $this->service->update($id, $data);
    }

    /**
     * 删除货币（主货币不可删除）。
     */
    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }

    /**
     * 初始化主货币（只在表中没有主货币时生效）。
     */
    public function setPrimary(int $id): bool
    {
        return $this->service->setPrimary($id);
    }

    /**
     * 切换货币启用状态（主货币不可禁用）。
     */
    public function toggle(int $id): bool
    {
        return $this->service->toggle($id);
    }

    // ====================== 静态辅助 API ======================

    /**
     * 读取访客当前展示货币代码。
     */
    public static function visitorCode(): string
    {
        return self::getInstance()->service->visitorCode();
    }

    /**
     * 「1 主货币 = N 访客币」的换算因子。
     */
    public static function visitorFactor(): float
    {
        return self::getInstance()->service->visitorFactor();
    }

    /**
     * 访客当前展示币种的符号。
     */
    public static function visitorSymbol(): string
    {
        return self::getInstance()->service->visitorSymbol();
    }

    /**
     * 下单时写入订单的"展示货币快照"。
     *
     * @return array{0: string, 1: int}
     */
    public static function visitorSnapshot(): array
    {
        return self::getInstance()->service->visitorSnapshot();
    }

    /**
     * 把主货币原始值（×1000000）按目标货币换算并格式化。
     */
    public static function displayAmount(
        int $rawMainCents,
        ?string $targetCode = null,
        bool $withSymbol = true,
        int $decimals = 2
    ): string {
        return self::getInstance()->service->displayAmount($rawMainCents, $targetCode, $withSymbol, $decimals);
    }

    /**
     * 按"主货币浮点元"展示金额。
     */
    public static function displayMain(
        float $amountInMain,
        ?string $targetCode = null,
        bool $withSymbol = true,
        int $decimals = 2
    ): string {
        return self::getInstance()->service->displayMain($amountInMain, $targetCode, $withSymbol, $decimals);
    }

    /**
     * 渲染前台"货币切换器"下拉 HTML 片段。
     */
    public static function switcherHtml(string $extraClass = ''): string
    {
        return self::getInstance()->service->switcherHtml($extraClass);
    }

    /**
     * 按"冻结快照"展示订单金额。
     */
    public static function displaySnapshot(
        int $rawMainCents,
        string $frozenCode,
        int $frozenRate,
        bool $withSymbol = true,
        int $decimals = 2
    ): string {
        return self::getInstance()->service->displaySnapshot($rawMainCents, $frozenCode, $frozenRate, $withSymbol, $decimals);
    }
}
