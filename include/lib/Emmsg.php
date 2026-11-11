<?php

/**
 * 统一消息页面（兼容门面）
 *
 * 实际实现已迁到 App\Support\Emmsg 实例服务；这里保留原有静态 API，
 * Database 等底层失败路径的调用点都不用改。
 */
class Emmsg
{
    /** @var \App\Support\Emmsg|null */
    private static $service;

    /**
     * 获取内部消息页服务实例。
     */
    public static function instance(): \App\Support\Emmsg
    {
        if (self::$service === null) {
            if (!class_exists(\App\Support\Emmsg::class)) {
                $root = defined('EM_ROOT') ? EM_ROOT : dirname(__DIR__, 2);
                require_once $root . '/app/Support/Emmsg.php';
            }
            self::$service = new \App\Support\Emmsg();
        }

        return self::$service;
    }

    /**
     * 输出友好错误页面并终止程序
     *
     * @param string $msg 自定义错误提示
     * @param \Exception|string|null $e 异常对象或错误详情字符串
     */
    public static function error(string $msg, $e = null): void
    {
        self::instance()->error($msg, $e);
    }

    /**
     * 输出操作指引/提示页面并终止程序
     *
     * @param string $msg 提示正文（告诉用户该做什么）
     * @param string $title 标题，默认「温馨提示」
     * @param string|null $detail 补充说明（步骤、注意事项等，可选）
     * @param array<int, array{label: string, onclick?: string, href?: string, icon?: string, primary?: bool}>|null $actions 自定义按钮；null 时使用默认「返回上一页」
     */
    public static function info(string $msg, string $title = '温馨提示', ?string $detail = null, ?array $actions = null): void
    {
        self::instance()->info($msg, $title, $detail, $actions);
    }
}
