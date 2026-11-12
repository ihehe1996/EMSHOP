<?php

declare(strict_types=1);

namespace App\Admin;

/**
 * 新后台框架壳（侧边栏 + 顶栏 + 内容区）。
 *
 * 负责统一准备布局变量（侧边栏、顶栏、菜单状态），
 * 再把子页面视图渲染进 #adminContent 内容区。所有需要整页布局的控制器都应调用
 * 页面统一走 AdminShell::render()，不要再自己拼框架壳。
 */
final class AdminShell
{
    /**
     * 渲染带框架壳的完整页面。
     *
     * @param string               $contentView 内容区视图的绝对路径
     * @param array<string, mixed> $user        当前管理员；为空时从会话读取
     * @param array<string, mixed> $vars        控制器传给内容视图的局部变量
     */
    public static function render(string $contentView, array $user = [], array $vars = []): void
    {
        if (!is_file($contentView)) {
            throw new \RuntimeException('后台内容视图不存在：' . $contentView);
        }

        // 后台翻译函数 t() 与语言列表
        require_once EM_ROOT . '/app/helpers/admin_lang.php';

        if ($user === []) {
            $user = app(\App\Service\AuthService::class)->user() ?? [];
        }

        // 控制器局部变量原样带进内容视图（旧框架壳是同一作用域 require，视图一直依赖这些变量）
        extract($vars, EXTR_SKIP);

        $siteName = (string) \Config::get('sitename', 'EMSHOP');
        $languages = app(\App\Model\LanguageModel::class)->getEnabled();
        $emServerHardRestartPending = is_file(EM_ROOT . '/.server') && \WorkerHeartbeat::hostAlive();

        $adminContentView = $contentView;

        require __DIR__ . '/View/shell.php';
    }
}
