<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 钩子服务（实例实现）。
 *
 * 有状态：进程内保存回调表、过滤器表与插件归属。兼容门面见 app/Facade/Hooks.php，
 * 门面持有单例，插件注册与执行语义与原来一致。
 *
 * 提供三种执行方式（参考 a 项目说明/挂载与钩子.php）：
 * 1. doAction($hook, ...$args)  - 插入式，遍历执行所有回调
 * 2. doOnceAction($hook, $input, &$ret) - 单次接管式，只执行第一个回调
 * 3. doMultiAction($hook, $input, &$ret) - 多插件协作式，遍历执行所有回调
 *
 * 注意：全局函数 addAction() / doAction() 等必须定义在全局命名空间的门面文件里，
 * 不能放在本命名空间，否则会变成 App\Support\addAction。
 */
final class Hooks
{
    /**
     * @var array<string, array<int, callable>>
     */
    private $hooks = [];

    /**
     * @var array<string, array<int, callable>>
     */
    private $filters = [];

    /**
     * 当前正在加载的插件 slug（由 init.php 在 include 插件主文件期间设置）。
     *
     * 用途：把 addAction 注册的回调归属到注册它的插件，见 getCallbacksForPlugin()。
     *
     * @var string|null
     */
    private $actingPlugin = null;

    /**
     * 每个钩子各回调的归属插件，下标与 $hooks[$hook] 一一对应。
     *
     * @var array<string, array<int, string|null>>
     */
    private $hookOwners = [];

    /**
     * 设置「当前正在加载哪个插件」。
     */
    public function setActingPlugin(?string $plugin): void
    {
        $this->actingPlugin = $plugin;
    }

    /**
     * 获取钩子回调列表。
     */
    public function getCallbacks(string $hook): array
    {
        return $this->hooks[$hook] ?? [];
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
    public function getCallbacksForPlugin(string $hook, string $plugin): array
    {
        $callbacks = $this->hooks[$hook] ?? [];
        if ($callbacks === []) {
            return [];
        }

        $owners = $this->hookOwners[$hook] ?? [];
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
     * 挂载一个钩子回调。
     */
    public function add(string $hook, callable $actionFunc): bool
    {
        if (!isset($this->hooks[$hook])) {
            $this->hooks[$hook] = [];
        }

        if (!in_array($actionFunc, $this->hooks[$hook], true)) {
            $this->hooks[$hook][] = $actionFunc;
            // 记录归属插件（与 $hooks[$hook] 下标严格对齐）。
            // 非插件上下文注册时记 null —— getCallbacksForPlugin() 会用「有没有
            // 任何归属信息」来决定是否启用过滤。
            $this->hookOwners[$hook][] = $this->actingPlugin;
        }

        return true;
    }

    /**
     * 执行指定钩子上的所有回调。
     * @param array<int, mixed> $args
     */
    public function run(string $hook, array $args = []): void
    {
        if (empty($this->hooks[$hook])) {
            return;
        }

        foreach ($this->hooks[$hook] as $function) {
            call_user_func_array($function, $args);
        }
    }

    /**
     * 挂载一个过滤器回调。
     */
    public function addFilterHook(string $hook, callable $filterFunc): bool
    {
        if (!isset($this->filters[$hook])) {
            $this->filters[$hook] = [];
        }

        if (!in_array($filterFunc, $this->filters[$hook], true)) {
            $this->filters[$hook][] = $filterFunc;
        }

        return true;
    }

    /**
     * 执行过滤器链，返回最终值。
     * @param mixed $value
     * @param array<int, mixed> $args
     * @return mixed
     */
    public function runFilter(string $hook, $value, array $args = [])
    {
        if (empty($this->filters[$hook])) {
            return $value;
        }

        foreach ($this->filters[$hook] as $function) {
            $value = call_user_func_array($function, array_merge([$value], $args));
        }

        return $value;
    }
}
