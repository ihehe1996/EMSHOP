<?php

declare(strict_types=1);

namespace App\User\Controller;

/**
 * 用户中心 - 概览首页（/user/home）。
 *
 * 数据仍由视图里的 template 查询取，控制器只负责金额口径与布局。
 */
final class HomeController extends UserBaseController
{
    public function index(): void
    {
        $user = $this->user();

        // 沿用用户中心首页的口径：余额按主货币直接换算，不带访客币种
        $displayMoney = '0.00';
        if (!empty($user['money'])) {
            $displayMoney = bcdiv((string) $user['money'], '1000000', 2);
        }

        if ($this->request->isPjax()) {
            echo '<div id="userContent" class="uc-content">';
            $this->render('home', [
                'frontUser'      => $user,
                'displayMoney'   => $displayMoney,
                'currencySymbol' => $this->currency->visitorSymbol(),
                'csrfToken'      => $this->csrf->token(),
            ]);
            echo '</div>';
            return;
        }

        \App\User\UserShell::render(dirname(__DIR__) . '/View/home.php', $user, [
            'displayMoney' => $displayMoney,
        ]);
    }
}
