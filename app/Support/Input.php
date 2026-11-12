<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 输入接收服务（实例实现）。
 *
 * 无状态：每次调用直接读 $_GET / $_POST。兼容门面见 app/Facade/Input.php。
 */
final class Input
{
    /**
     * 获取 POST 参数。
     *
     * @param mixed $default
     * @return mixed
     */
    public function post(string $key, $default = '')
    {
        return $this->value($_POST, $key, $default);
    }

    /**
     * 获取 GET 参数。
     *
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = '')
    {
        return $this->value($_GET, $key, $default);
    }

    /**
     * 获取全部 POST 数据。
     *
     * @return array<string, mixed>
     */
    public function allPost(): array
    {
        return $this->normalize($_POST);
    }

    /**
     * 获取全部 GET 数据。
     *
     * @return array<string, mixed>
     */
    public function allGet(): array
    {
        return $this->normalize($_GET);
    }

    /**
     * @param array<string, mixed> $source
     * @param mixed $default
     * @return mixed
     */
    private function value(array $source, string $key, $default)
    {
        if (!array_key_exists($key, $source)) {
            return $default;
        }

        return $this->clean($source[$key]);
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function normalize(array $source): array
    {
        $data = [];
        foreach ($source as $key => $value) {
            $data[$key] = $this->clean($value);
        }

        return $data;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function clean($value)
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_array($value)) {
            $cleaned = [];
            foreach ($value as $key => $item) {
                $cleaned[$key] = $this->clean($item);
            }
            return $cleaned;
        }

        return $value;
    }

    /**
     * 获取 POST 字符串参数（自动去首尾空白）。
     */
    public function postStr(string $key, string $default = ''): string
    {
        return (string) $this->post($key, $default);
    }

    /**
     * 获取 GET 字符串参数（自动去首尾空白）。
     */
    public function getStr(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }

    /**
     * 获取 POST 整数参数。
     */
    public function postInt(string $key, int $default = 0): int
    {
        return (int) $this->post($key, $default);
    }

    /**
     * 获取 GET 整数参数。
     */
    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    // 以下为兼容别名
    public function postStrVar(string $key, string $default = ''): string
    {
        return $this->postStr($key, $default);
    }

    public function getStrVar(string $key, string $default = ''): string
    {
        return $this->getStr($key, $default);
    }
}
