<?php

declare(strict_types=1);

/**
 * 钩子基础实现。
 *
 * 提供三种执行方式（参考 a 项目说明/挂载与钩子.php）：
 * 1. doAction($hook, ...$args)  - 插入式，遍历执行所有回调
 * 2. doOnceAction($hook, $input, &$ret) - 单次接管式，只执行第一个回调
 * 3. doMultiAction($hook, $input, &$ret) - 多插件协作式，遍历执行所有回调
 */
final class Hooks
{
    /**
     * @var array<string, array<int, callable>>
     */
    private static $hooks = [];

    /**
     * @var array<string, array<int, callable>>
     */
    private static $filters = [];

    /**
     * 当前正在加载的插件 slug（由 init.php 在 include 插件主文件期间设置）。
     *
     * 用途：把 addAction 注册的回调归属到注册它的插件，见 getCallbacksForPlugin()。
     *
     * @var string|null
     */
    private static $actingPlugin = null;

    /**
     * 每个钩子各回调的归属插件，下标与 $hooks[$hook] 一一对应。
     *
     * @var array<string, array<int, string|null>>
     */
    private static $hookOwners = [];

    /**
     * 设置「当前正在加载哪个插件」。
     */
    public static function setActingPlugin(?string $plugin): void
    {
        self::$actingPlugin = $plugin;
    }

    /**
     * 获取钩子回调列表。
     */
    public static function getCallbacks(string $hook): array
    {
        return self::$hooks[$hook] ?? [];
    }

    /**
     * 只取**指定插件**注册的回调。
     *
     * 事件订阅 worker 靠它把「本插件自己的回调」与其它订阅方区分开：
     * 同一个事件若有 N 个插件订阅，每个订阅方都有自己的 worker 与独立游标，
     * 用 getCallbacks() 会让每个 worker 都把 N 个回调各调一遍 —— 等于每个插件
     * 被重复执行 N 次（用户收到 N 份重复邮件/推送），也违背了「每个插件的代码
     * 只在自己进程里跑」的设计初衷。
     *
     * 兼容性：若该钩子的回调**全部**没有归属信息（核心自身注册的、或在插件加载
     * 窗口之外注册的），则退化为返回全部回调 —— 宁可重复一次，也不要静默不发。
     *
     * @return array<int, callable>
     */
    public static function getCallbacksForPlugin(string $hook, string $plugin): array
    {
        $callbacks = self::$hooks[$hook] ?? [];
        if ($callbacks === []) {
            return [];
        }

        $owners = self::$hookOwners[$hook] ?? [];
        $hasAnyOwner = false;
        foreach ($owners as $owner) {
            if ($owner !== null && $owner !== '') {
                $hasAnyOwner = true;
                break;
            }
        }
        if (!$hasAnyOwner) {
            return $callbacks;
        }

        $out = [];
        foreach ($callbacks as $i => $fn) {
            if (($owners[$i] ?? null) === $plugin) {
                $out[] = $fn;
            }
        }

        return $out;
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
        if (!isset(self::$hooks[$hook])) {
            self::$hooks[$hook] = [];
        }

        if (!in_array($actionFunc, self::$hooks[$hook], true)) {
            self::$hooks[$hook][] = $actionFunc;
            // 记录归属插件（与 $hooks[$hook] 下标严格对齐）。
            // 非插件上下文注册时记 null —— getCallbacksForPlugin() 会用「有没有
            // 任何归属信息」来决定是否启用过滤。
            self::$hookOwners[$hook][] = self::$actingPlugin;
        }

        return true;
    }

    /**
     * 执行指定钩子上的所有回调。
     * @param array<int, mixed> $args
     */
    public static function run(string $hook, array $args = []): void
    {
        if (empty(self::$hooks[$hook])) {
            return;
        }

        foreach (self::$hooks[$hook] as $function) {
            call_user_func_array($function, $args);
        }
    }

    /**
     * 挂载一个过滤器回调。
     */
    public static function addFilterHook(string $hook, callable $filterFunc): bool
    {
        if (!isset(self::$filters[$hook])) {
            self::$filters[$hook] = [];
        }

        if (!in_array($filterFunc, self::$filters[$hook], true)) {
            self::$filters[$hook][] = $filterFunc;
        }

        return true;
    }

    /**
     * 执行过滤器链，返回最终值。
     * @param mixed $value
     * @param array<int, mixed> $args
     * @return mixed
     */
    public static function runFilter(string $hook, $value, array $args = [])
    {
        if (empty(self::$filters[$hook])) {
            return $value;
        }

        foreach (self::$filters[$hook] as $function) {
            $value = call_user_func_array($function, array_merge([$value], $args));
        }

        return $value;
    }
}
