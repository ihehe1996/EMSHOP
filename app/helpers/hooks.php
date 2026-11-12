<?php

declare(strict_types=1);

/**
 * 钩子全局函数（addAction / doAction / addFilter / applyFilter 等）。
 *
 * 插件和模板直接调用这些全局函数，必须定义在全局命名空间；
 * 实现转发到 App\Facade\Hooks（全局短名 Hooks 由别名加载器注册）。
 */

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
    
