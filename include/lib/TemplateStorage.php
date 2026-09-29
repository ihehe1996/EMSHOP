<?php

declare(strict_types=1);

/**
 * 模板配置存储类。
 *
 * 配置存储在 em_options 表中,以 (type='template', title=模板名, merchant_id, name=配置键) 四元组区分。
 * 当前 scope 由请求入口写入的 $GLOBALS['__em_current_scope'] 决定:
 *   - 'main'           → merchant_id = 0
 *   - 'merchant_{id}'  → merchant_id = {id}
 * 对模板作者透明 —— 同一份 setting.php 在不同端调用时自动读写对应商户的行。
 */
final class TemplateStorage
{
    private string $template;
    private string $scope;
    private int    $merchantId;
    private array  $data = [];
    private bool   $loaded = false;

    /** @var array<string, self> */
    private static array $instances = [];

    /**
     * 获取指定模板的存储实例(按 template+scope 做单例)。
     * scope 优先从 $GLOBALS['__em_current_scope'] 读,缺省 'main'。
     */
    public static function getInstance(string $template): self
    {
        $scope = (string) ($GLOBALS['__em_current_scope'] ?? 'main');
        if ($scope === '') $scope = 'main';
        $key = $template . '@' . $scope;
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new self($template, $scope);
        }
        return self::$instances[$key];
    }

    private function __construct(string $template, string $scope)
    {
        $this->template   = $template;
        $this->scope      = $scope;
        $this->merchantId = self::parseMerchantId($scope);
    }

    /**
     * scope 字符串 → merchant_id。'main' / 非法值 → 0;'merchant_X' → X。
     */
    private static function parseMerchantId(string $scope): int
    {
        if (strncmp($scope, 'merchant_', 9) !== 0) return 0;
        $id = (int) substr($scope, 9);
        return $id > 0 ? $id : 0;
    }

    /**
     * 读取模板配置。
     */
    public function getValue(string $key, $default = null)
    {
        $this->ensureLoaded();
        if (array_key_exists($key, $this->data)) {
            return self::decodeValue($this->data[$key]);
        }
        return $default;
    }

    /**
     * 写入模板配置。
     */
    public function setValue(string $key, $value): bool
    {
        $this->ensureLoaded();

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $table = Database::prefix() . 'options';
        $sqlCheck = sprintf(
            'SELECT COUNT(*) AS cnt FROM `%s` WHERE `type` = ? AND `title` = ? AND `merchant_id` = ? AND `name` = ?',
            $table
        );
        $row = Database::fetchOne($sqlCheck, ['template', $this->template, $this->merchantId, $key]);
        $exists = $row !== null && (int) $row['cnt'] > 0;

        if ($exists) {
            $sql = sprintf(
                'UPDATE `%s` SET `content` = ? WHERE `type` = ? AND `title` = ? AND `merchant_id` = ? AND `name` = ?',
                $table
            );
            Database::execute($sql, [(string) $value, 'template', $this->template, $this->merchantId, $key]);
        } else {
            $sql = sprintf(
                'INSERT INTO `%s` (`type`, `title`, `merchant_id`, `name`, `content`) VALUES (?, ?, ?, ?, ?)',
                $table
            );
            Database::execute($sql, ['template', $this->template, $this->merchantId, $key, (string) $value]);
        }

        $this->data[$key] = (string) $value;
        return true;
    }

    /**
     * 删除指定模板配置(仅当前 merchant_id)。
     */
    public function deleteValue(string $key): bool
    {
        $table = Database::prefix() . 'options';
        $sql = sprintf(
            'DELETE FROM `%s` WHERE `type` = ? AND `title` = ? AND `merchant_id` = ? AND `name` = ?',
            $table
        );
        Database::execute($sql, ['template', $this->template, $this->merchantId, $key]);
        unset($this->data[$key]);
        return true;
    }

    /**
     * 删除模板下所有配置(仅当前 merchant_id,不影响其它商户)。
     */
    public function deleteAllName(string $confirm): bool
    {
        if ($confirm !== 'YES') {
            return false;
        }

        $table = Database::prefix() . 'options';
        $sql = sprintf('DELETE FROM `%s` WHERE `type` = ? AND `title` = ? AND `merchant_id` = ?', $table);
        Database::execute($sql, ['template', $this->template, $this->merchantId]);
        $this->data = [];
        return true;
    }

    /**
     * 批量获取本主题（当前 scope）的全部配置。
     *
     * 值按 getValue() 的同一规则解码（存 JSON 的会变回数组），
     * 所以调用方一次拿到就能直接当配置数组用，不必逐个 getValue。
     * 底层只查一次库（ensureLoaded），重复调用无额外开销。
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        $this->ensureLoaded();

        $out = [];
        foreach ($this->data as $key => $val) {
            $out[$key] = self::decodeValue($val);
        }
        return $out;
    }

    /**
     * 配置值的解码规则（getValue / getAll 共用）。
     *
     * 存进去时数组/对象会被 json_encode，读的时候能解回数组就解回数组，
     * 否则原样返回字符串。集中在这里，避免两处各写一遍 json_decode 而慢慢走偏。
     *
     * @param string $val 库里的原始字符串
     * @return mixed
     */
    private static function decodeValue(string $val)
    {
        $decoded = json_decode($val, true);
        if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
            return $decoded;
        }
        return $val;
    }

    /**
     * 确保配置已加载(按当前 merchant_id 过滤)。
     */
    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;
        $this->data = [];

        try {
            $table = Database::prefix() . 'options';
            $sql = sprintf(
                'SELECT `name`, `content` FROM `%s` WHERE `type` = ? AND `title` = ? AND `merchant_id` = ?',
                $table
            );
            $rows = Database::query($sql, ['template', $this->template, $this->merchantId]);

            foreach ($rows as $row) {
                $name = isset($row['name']) ? (string) $row['name'] : '';
                if ($name === '') {
                    continue;
                }
                $this->data[$name] = isset($row['content']) ? (string) $row['content'] : '';
            }
        } catch (Throwable $e) {
            $this->data = [];
        }
    }
}
