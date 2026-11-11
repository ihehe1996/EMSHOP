<?php

declare(strict_types=1);

namespace App\Merchant;

/**
 * 商户后台框架壳。
 *
 * 对应原 user/merchant/global.php 的 merchantRenderPage()：Pjax 返回 #merchantContent 片段，
 * 普通请求套 user/merchant/view/index.php 同款布局（现已迁到 app/Merchant/View/shell.php）。
 */
final class MerchantShell
{
    /**
     * @param string                   $contentView 内容区视图绝对路径
     * @param array<string, mixed>     $user        当前前台用户
     * @param array<string, mixed>     $merchant    当前商户
     * @param array<string, mixed>|null $level       商户等级
     * @param array<string, mixed>     $vars        传给内容视图的局部变量
     */
    public static function render(
        string $contentView,
        array $user,
        array $merchant,
        ?array $level,
        array $vars = []
    ): void {
        if (!is_file($contentView)) {
            throw new \RuntimeException('商户后台视图不存在：' . $contentView);
        }

        $frontUser = $user;
        $currentMerchant = $merchant;
        $merchantLevel = $level;

        $siteName = (string) (\Config::get('sitename') ?? 'EMSHOP');
        $currencySymbol = \Currency::visitorSymbol();

        $shopBalanceRaw = (int) ($frontUser['shop_balance'] ?? 0);
        $uc = [
            'siteName'       => $siteName,
            'currencySymbol' => $currencySymbol,
            'displayMoney'   => \Currency::displayAmount((int) ($frontUser['money'] ?? 0)),
            'shopBalance'    => \Currency::displayAmount($shopBalanceRaw),
            'shopBalanceRaw' => $shopBalanceRaw,
        ];

        extract($vars, EXTR_SKIP);

        // Pjax：只输出内容区片段
        if (\Request::isPjax()) {
            echo '<div id="merchantContent" class="mc-content">';
            require $contentView;
            echo '</div>';
            return;
        }

        $merchantContentView = $contentView;

        require __DIR__ . '/View/shell.php';
    }
}
