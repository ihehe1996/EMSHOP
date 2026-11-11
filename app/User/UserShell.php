<?php

declare(strict_types=1);

namespace App\User;

/**
 * 用户中心框架壳（侧边栏 + 顶栏 + 内容区）。
 *
 * 对应原 user/index.php + user/view/index.php 的组合：统一准备布局变量，
 * 再把子页面视图渲染进 #userContent 内容区。
 */
final class UserShell
{
    /**
     * @param string               $contentView 内容区视图绝对路径
     * @param array<string, mixed> $user        当前前台用户
     * @param array<string, mixed> $vars        控制器传给内容视图的局部变量
     */
    public static function render(string $contentView, array $user, array $vars = []): void
    {
        if (!is_file($contentView)) {
            throw new \RuntimeException('用户中心视图不存在：' . $contentView);
        }

        $frontUser = $user;
        $siteName = (string) \Config::get('sitename', 'EMSHOP');
        $csrfToken = \Csrf::token();

        // 余额按访客当前展示币种换算（不带符号；view 里前面会拼 $currencySymbol）
        $displayMoney = \Currency::displayAmount((int) ($frontUser['money'] ?? 0), null, false);
        $currencySymbol = \Currency::visitorSymbol();

        // 控制器传入的同名变量优先（如首页沿用原来的主货币金额口径）
        extract($vars, EXTR_OVERWRITE);

        $userContentView = $contentView;

        require __DIR__ . '/View/shell.php';
    }
}
