<?php

declare(strict_types=1);

namespace App\Http;

/**
 * 带固定路径前缀的子应用路由基类。
 *
 * AdminRouter / UserRouter / MerchantRouter 共用这一套调度逻辑：
 *   前缀匹配 → 排除前缀 → 拒绝带点路径段 → 第一段查路由表 → 实例化控制器。
 *
 * 子类只声明前缀、排除前缀、路由表和 404 控制器，不再各自复制解析/分发代码。
 */
abstract class PrefixRouter
{
    /**
     * 子应用路径前缀，如 /admin、/user、/user/merchant。
     */
    abstract protected function prefix(): string;

    /**
     * 前缀下不被本路由接管的路径（例如用户中心排除 /user/merchant）。
     *
     * @return string[]
     */
    protected function excludedPrefixes(): array
    {
        return [];
    }

    /**
     * 路由表：第一段路径 => [控制器类, 动作方法]。空字符串代表前缀本身。
     *
     * @return array<string, array{0: class-string, 1: string}>
     */
    abstract protected function routes(): array;

    /**
     * 未登记路径落到哪个控制器。
     *
     * @return array{class: class-string, action: string}
     */
    abstract protected function notFoundRoute(): array;

    /**
     * 当前请求命中本路由时执行并返回 true；否则返回 false。
     *
     * 调用方（index.php）在返回 true 后直接 exit，不再进入前台 Dispatcher。
     */
    public function handleCurrentRequest(): bool
    {
        $path = static::currentPath();
        if ($path === null || !$this->isPath($path)) {
            return false;
        }

        $this->dispatch($path);

        return true;
    }

    /**
     * 判断站点内路径是否属于本路由：前缀匹配、不在排除列表、且不带扩展名。
     *
     * 带点的路径段（.php / .css / 文件名）一律不接管，交给 Web 服务器或前台路由。
     */
    public function isPath(string $path): bool
    {
        $path = '/' . trim($path, '/');
        $prefix = $this->prefix();
        if ($path !== $prefix && strpos($path, $prefix . '/') !== 0) {
            return false;
        }

        foreach ($this->excludedPrefixes() as $excluded) {
            $excluded = '/' . trim($excluded, '/');
            if ($path === $excluded || strpos($path, $excluded . '/') === 0) {
                return false;
            }
        }

        $rest = trim(substr($path, strlen($prefix)), '/');
        if ($rest === '') {
            return true;
        }

        foreach (explode('/', $rest) as $segment) {
            if ($segment === '' || strpos($segment, '.') !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * 解析路径；未登记的路由返回 null。
     *
     * @return array{class: class-string, action: string}|null
     */
    public function resolve(string $path): ?array
    {
        $path = '/' . trim($path, '/');
        if (!$this->isPath($path)) {
            return null;
        }

        $rest = trim(substr($path, strlen($this->prefix())), '/');
        $segments = $rest === '' ? [] : explode('/', $rest);
        $key = $segments[0] ?? '';
        $routes = $this->routes();
        if (!isset($routes[$key])) {
            return null;
        }

        return [
            'class'  => $routes[$key][0],
            'action' => $routes[$key][1],
        ];
    }

    /**
     * 执行路由；未登记的路径交给本子应用的 404 控制器（同样经过各自的登录守卫）。
     */
    public function dispatch(string $path): void
    {
        $route = $this->resolve($path) ?? $this->notFoundRoute();

        $class = $route['class'];
        $action = $route['action'];
        /** @var object $controller */
        $controller = \App\Core\Application::instance()->container()->make($class);
        $controller->{$action}();
    }

    /**
     * 从 REQUEST_URI 还原站点内路径，并剥离子目录安装前缀。
     *
     * 规则与前台 Dispatcher 的 pathInfoFromRequestUri() 保持一致。
     */
    public static function currentPath(): ?string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = (string) parse_url($uri, PHP_URL_PATH);
        if ($path === '') {
            return null;
        }

        $basePath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
        if ($basePath !== '' && $basePath !== '/') {
            if ($path === $basePath) {
                $path = '/';
            } elseif (strpos($path, $basePath . '/') === 0) {
                $path = substr($path, strlen($basePath));
            } else {
                return null;
            }
        }

        return $path;
    }
}
