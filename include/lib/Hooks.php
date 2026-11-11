<?php

declare(strict_types=1);

/**
 * 钩子基础实现（兼容门面）。
 *
 * 实际实现已迁到 App\Support\Hooks 实例服务；这里保留原有静态 API 和全局函数，
 * 现有调用点、插件和模板都不用改。
 *
 * 注意：addAction() / doAction() 等是**全局函数**，必须定义在这个全局命名空间的
 * 文件里；放进 App\Support 命名空间会变成 App\Support\addAction。
 */
final class Hooks
{
    /** @var \App\Support\Hooks|null */
    private static $service;

    /**
     * 获取内部钩子服务实例。
     */
    public static function instance(): \App\Support\Hooks
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\Hooks::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/Hooks.php';
            }
            self::$service = new \App\Support\Hooks();
        }

        return self::$service;
    }

    /**
     * 设置「当前正在加载哪个插件」。
     */
    public static function setActingPlugin(?string $plugin): void
    {
        self::instance()->setActingPlugin($plugin);
    }

    /**
     * 获取钩子回调列表。
     */
    public static function getCallbacks(string $hook): array
    {
        return self::instance()->getCallbacks($hook);
    }

    /**
     * 只取**指定插件**注册的回调。
     */
    public static function getCallbacksForPlugin(string $hook, string $plugin): array
    {
        return self::instance()->getCallbacksForPlugin($hook, $plugin);
    }

    /**
     * 初始化钩子运行环境并注册全局函数。
     */
    public static function boot(): void
    {
        if (!function_exists('addAction')) {
            /**
             * 挂载插件函数到钩子上。
             * @param string $hook 钩子名称
             * @param callable $actionFunc 回调函数
             */
            function addAction(string $hook, callable $actionFunc): bool
            {
                return Hooks::add($hook, $actionFunc);
            }
        }

        if (!function_exists('doAction')) {
            /**
             * 执行方式1（插入式）：遍历执行钩子上挂载的所有回调函数。
             *
             * @param string $hook 钩子名称
             * @param mixed ...$args 传递给回调的参数
             *
             * 使用 func_get_args() 获取所有参数，避免引用参数导致的
             * "Cannot pass parameter 2 by reference" 错误。
             * 示例：doAction('post_comment', $author, $email, $comment);
             */
            function doAction(string $hook, ...$args): void
            {
                $callbacks = Hooks::getCallbacks($hook);
                if (empty($callbacks)) return;

                foreach ($callbacks as $fn) {
                    call_user_func_array($fn, $args);
                }
            }
        }

        if (!function_exists('doOnceAction')) {
            /**
             * 执行方式2（单次接管式）：只执行钩子上挂载的第一个回调函数。
             * 用于需要独占处理的场景（如文件上传接管）。
             *
             * @param string $hook 钩子名称
             * @param mixed $input 输入数据
             * @param mixed &$ret 引用传递的结果输出
             */
            function doOnceAction(string $hook, $input, &$ret = null): void
            {
                $callbacks = Hooks::getCallbacks($hook);
                if (empty($callbacks[0])) return;
                $fn = $callbacks[0];
                $fn($input, $ret);
            }
        }

        if (!function_exists('doMultiAction')) {
            /**
             * 执行方式3（多插件协作式）：遍历执行钩子上挂载的所有回调函数。
             * 用于多个插件协作处理的场景（如发货流程）。
             *
             * @param string $hook 钩子名称
             * @param mixed $input 原始输入数据（所有插件共享，不被覆盖）
             * @param mixed &$ret 引用传递的结果（所有插件共享，可修改）
             */
            function doMultiAction(string $hook, $input, &$ret = null): void
            {
                $callbacks = Hooks::getCallbacks($hook);
                if (empty($callbacks)) return;

                foreach ($callbacks as $fn) {
                    $fn($input, $ret);
                }
            }
        }

        if (!function_exists('addFilter')) {
            /**
             * 挂载过滤器函数。
             */
            function addFilter(string $hook, callable $filterFunc): bool
            {
                return Hooks::addFilterHook($hook, $filterFunc);
            }
        }

        if (!function_exists('applyFilter')) {
            /**
             * 执行过滤器链，返回最终值。
             * @param mixed $value 初始值，将依次经过每个过滤器函数处理
             * @param mixed ...$extra 额外参数，将传递给每个过滤器函数
             * @return mixed
             */
            function applyFilter(string $hook, $value, ...$extra)
            {
                return Hooks::runFilter($hook, $value, $extra);
            }
        }
    }

    /**
     * 挂载一个钩子回调。
     */
    public static function add(string $hook, callable $actionFunc): bool
    {
        return self::instance()->add($hook, $actionFunc);
    }

    /**
     * 执行指定钩子上的所有回调。
     * @param array<int, mixed> $args
     */
    public static function run(string $hook, array $args = []): void
    {
        self::instance()->run($hook, $args);
    }

    /**
     * 挂载一个过滤器回调。
     */
    public static function addFilterHook(string $hook, callable $filterFunc): bool
    {
        return self::instance()->addFilterHook($hook, $filterFunc);
    }

    /**
     * 执行过滤器链，返回最终值。
     * @param mixed $value
     * @param array<int, mixed> $args
     * @return mixed
     */
    public static function runFilter(string $hook, $value, array $args = [])
    {
        return self::instance()->runFilter($hook, $value, $args);
    }
}
