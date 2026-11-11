<?php

declare(strict_types=1);

namespace App\Admin;

/**
 * 新后台框架壳（侧边栏 + 顶栏 + 内容区）。
 *
 * 对应旧后台的 admin/index.php + admin/view/index.php 组合：负责统一准备布局变量，
 * 再把子页面视图渲染进 #adminContent 内容区。所有需要整页布局的控制器都应调用
 * AdminShell::render()，不要再去 require 旧后台的框架壳 —— 后台目录最终会删除。
 */
final class AdminShell
{
    /**
     * 渲染带框架壳的完整页面。
     *
     * @param string               $contentView 内容区视图的绝对路径
     * @param array<string, mixed> $user        当前管理员；为空时从会话读取
     */
    public static function render(string $contentView, array $user = []): void
    {
        if (!is_file($contentView)) {
            throw new \RuntimeException('后台内容视图不存在：' . $contentView);
        }

        // 后台翻译函数 t() 与语言列表
        require_once EM_ROOT . '/include/lib/AdminLang.php';

        if ($user === []) {
            $user = (new \AuthService())->user() ?? [];
        }

        $siteName = (string) \Config::get('sitename', 'EMSHOP');
        $languages = (new \LanguageModel())->getEnabled();
        $emServerHardRestartPending = is_file(EM_ROOT . '/.server') && \WorkerHeartbeat::hostAlive();

        $adminContentView = $contentView;

        require dirname(__DIR__) . '/View/shell.php';
    }
}
