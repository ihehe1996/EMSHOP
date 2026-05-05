<?php
/**
 * 商品类型管理器
 * 负责商品类型的注册、钩子分发和类型切换
 *
 * @package EM\Core\Class
 */

if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

class GoodsTypeManager
{
    /**
     * @var array 已注册的商品类型
     */
    private static $types = [];

    /**
     * 注册商品类型
     *
     * @param string $type 类型标识（小写字母+下划线）
     * @param array $config 类型配置
     *   - name: 类型名称
     *   - description: 描述
     *   - icon: 图标路径
     *   - default: 是否为默认类型
     * @return bool
     */
    public static function registerType($type, $config)
    {
        if (empty($type) || !preg_match('/^[a-z][a-z0-9_]*$/', $type)) {
            return false;
        }

        self::$types[$type] = array_merge([
            'name' => $type,
            'description' => '',
            'icon' => '',
            'default' => false,
        ], $config);

        return true;
    }

    /**
     * 获取所有已注册的商品类型
     *
     * @return array
     */
    public static function getTypes()
    {
        return self::$types;
    }

    /**
     * 获取默认商品类型
     *
     * @return string|null
     */
    public static function getDefaultType()
    {
        foreach (self::$types as $type => $config) {
            if (!empty($config['default'])) {
                return $type;
            }
        }

        // 没有标记默认的，返回第一个
        $keys = array_keys(self::$types);
        return $keys[0] ?? null;
    }

    /**
     * 获取指定类型的配置
     *
     * @param string $type
     * @return array|null
     */
    public static function getTypeConfig($type)
    {
        return self::$types[$type] ?? null;
    }

    /**
     * 添加钩子（委托给 Hooks 类）
     *
     * @param string $hook 钩子名称
     * @param callable $callback 回调函数
     * @param int $priority 优先级（数字越小越先执行）
     */
    public static function addHook($hook, $callback, $priority = 10)
    {
        // 委托给 Hooks 类统一管理，优先级统一为 10
        Hooks::add($hook, $callback);
    }

    /**
     * 执行钩子（委托给 Hooks 类）
     * 注意：本方法将参数数组展开为独立参数传递给 Hooks::run()，
     * 因为 Hooks::run() 内部通过 call_user_func_array 执行。
     * 若需要引用传递支持，请使用全局函数 doAction()。
     *
     * @param string $hook 钩子名称
     * @param array $params 参数数组
     */
    public static function doAction($hook, $params = [])
    {
        Hooks::run($hook, $params);
    }

    /**
     * 执行过滤器钩子（委托给 Hooks 类）
     *
     * @param string $hook 钩子名称
     * @param mixed $value 待过滤的值
     * @param array $params 额外参数
     * @return mixed
     */
    public static function applyFilter($hook, $value, $params = [])
    {
        return Hooks::runFilter($hook, $value, $params);
    }

    /**
     * 触发商品类型的特定钩子
     *
     * @param string $goodsType 商品类型
     * @param string $hookSuffix 钩子后缀（如 create_form, save, render）
     * @param array $params 参数
     */
    public static function doTypeAction($goodsType, $hookSuffix, $params = [])
    {
        $hookName = "goods_type_{$goodsType}_{$hookSuffix}";
        Hooks::run($hookName, $params);
    }

    /**
     * 触发商品类型的特定过滤器钩子
     *
     * @param string $goodsType 商品类型
     * @param string $hookSuffix 钩子后缀
     * @param mixed $value 待过滤的值
     * @param array $params 额外参数
     * @return mixed
     */
    public static function applyTypeFilter($goodsType, $hookSuffix, $value, $params = [])
    {
        $hookName = "goods_type_{$goodsType}_{$hookSuffix}";
        return Hooks::runFilter($hookName, $value, $params);
    }

    /**
     * 切换商品类型
     *
     * @param int $goodsId 商品ID
     * @param string $oldType 旧类型
     * @param string $newType 新类型
     * @return array ['success' => bool, 'message' => string]
     */
    public static function switchType($goodsId, $oldType, $newType)
    {
        if ($oldType === $newType) {
            return ['success' => true, 'message' => '类型未变化'];
        }

        // 获取旧类型配置
        $oldConfig = self::getTypeConfig($oldType);
        $newConfig = self::getTypeConfig($newType);

        if (!$newConfig) {
            return ['success' => false, 'message' => '目标类型不存在'];
        }

        // 触发警告钩子（收集警告信息，通过引用传递）
        // 使用 call_user_func_array 直接调用，确保引用语义正确传递。
        // PHP 8.2 中 doAction() 的固定引用参数在某些上下文中有兼容性问题，
        // 而 call_user_func_array 能正确处理引用参数。
        $warnings = [];
        $callbacks = Hooks::getCallbacks("goods_type_{$oldType}_switch_warning");
        foreach ($callbacks as $fn) {
            call_user_func_array($fn, [&$warnings, $goodsId, $oldType, $newType]);
        }

        // 触发旧类型的清理钩子
        $hookName = "goods_type_{$oldType}_switch_from";
        Hooks::run($hookName, [$goodsId]);

        // 更新数据库中的类型
        $result = Database::update('goods', [
            'goods_type' => $newType,
            'updated_at' => date('Y-m-d H:i:s'),
        ], $goodsId);

        if (!$result) {
            return ['success' => false, 'message' => '更新数据库失败'];
        }

        // 触发新类型的初始化钩子
        $hookName = "goods_type_{$newType}_switch_to";
        Hooks::run($hookName, [$goodsId]);

        return [
            'success' => true,
            'message' => '类型切换成功',
            'warnings' => $warnings,
        ];
    }

    /**
     * 获取商品类型切换警告
     *
     * @param int $goodsId 商品ID
     * @param string $oldType 旧类型
     * @param string $newType 新类型
     * @return array 警告信息数组
     */
    public static function getSwitchWarnings($goodsId, $oldType, $newType)
    {
        $warnings = [];
        $callbacks = Hooks::getCallbacks("goods_type_{$oldType}_switch_warning");
        foreach ($callbacks as $fn) {
            call_user_func_array($fn, [&$warnings, $goodsId, $oldType, $newType]);
        }
        return $warnings;
    }
}

// 统一使用 Hooks 类的全局函数，不再重复定义
// 注意：GoodsTypeManager::addHook/doAction/applyFilter 是内部方法
// 全局的 addAction/doAction/applyFilter 由 Hooks::boot() 统一提供
// 此处无需重新定义，避免与 Hooks 类冲突
