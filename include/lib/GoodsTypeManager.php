<?php
/**
 * 商品类型管理器（兼容门面）
 *
 * 实际实现已迁到 App\Support\GoodsTypeManager 实例服务；这里保留原有静态 API，
 * 插件、控制器和后台页面的调用点都不用改。
 *
 * @package EM\Core\Class
 */

if (!defined('EM_ROOT')) {
    exit('Access Denied');
}

class GoodsTypeManager
{
    /** @var \App\Support\GoodsTypeManager|null */
    private static $service;

    /**
     * 获取内部商品类型服务实例。
     */
    public static function instance(): \App\Support\GoodsTypeManager
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\GoodsTypeManager::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/GoodsTypeManager.php';
            }
            self::$service = new \App\Support\GoodsTypeManager();
        }

        return self::$service;
    }

    /**
     * 注册商品类型
     *
     * @param string $type 类型标识（小写字母+下划线）
     * @param array $config 类型配置
     * @return bool
     */
    public static function registerType($type, $config)
    {
        return self::instance()->registerType($type, $config);
    }

    /**
     * 获取所有已注册的商品类型
     *
     * @return array
     */
    public static function getTypes()
    {
        return self::instance()->getTypes();
    }

    /**
     * 获取默认商品类型
     *
     * @return string|null
     */
    public static function getDefaultType()
    {
        return self::instance()->getDefaultType();
    }

    /**
     * 获取指定类型的配置
     *
     * @param string $type
     * @return array|null
     */
    public static function getTypeConfig($type)
    {
        return self::instance()->getTypeConfig($type);
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
        self::instance()->addHook($hook, $callback, $priority);
    }

    /**
     * 执行钩子（委托给 Hooks 类）
     *
     * @param string $hook 钩子名称
     * @param array $params 参数数组
     */
    public static function doAction($hook, $params = [])
    {
        self::instance()->doAction($hook, $params);
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
        return self::instance()->applyFilter($hook, $value, $params);
    }

    /**
     * 触发商品类型的特定钩子
     *
     * @param string $goodsType 商品类型
     * @param string $hookSuffix 钩子后缀
     * @param array $params 参数
     */
    public static function doTypeAction($goodsType, $hookSuffix, $params = [])
    {
        self::instance()->doTypeAction($goodsType, $hookSuffix, $params);
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
        return self::instance()->applyTypeFilter($goodsType, $hookSuffix, $value, $params);
    }

    /**
     * 切换商品类型
     *
     * @param int $goodsId 商品ID
     * @param string $oldType 旧类型
     * @param string $newType 新类型
     * @return array
     */
    public static function switchType($goodsId, $oldType, $newType)
    {
        return self::instance()->switchType($goodsId, $oldType, $newType);
    }

    /**
     * 获取商品类型切换警告
     *
     * @param int $goodsId 商品ID
     * @param string $oldType 旧类型
     * @param string $newType 新类型
     * @return array
     */
    public static function getSwitchWarnings($goodsId, $oldType, $newType)
    {
        return self::instance()->getSwitchWarnings($goodsId, $oldType, $newType);
    }
}

// 统一使用 Hooks 类的全局函数，不再重复定义
// 全局的 addAction/doAction/applyFilter 由 Hooks::boot() 统一提供
