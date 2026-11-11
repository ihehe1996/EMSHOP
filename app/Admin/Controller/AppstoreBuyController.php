<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 应用商店 —— 购买弹窗（/admin/appstore_buy）。
 *
 * 仅渲染独立弹窗框架 HTML；页面初始化后由 JS 调 /admin/appstore.php 的接口
 * （appstore 仍为旧页面，等迁移时统一更换地址）。
 */
final class AppstoreBuyController extends BaseAdminController
{
    public function index(): void
    {
        $appId = (int) \Input::get('id', 0);

        $tab = (string) \Input::get('tab', 'main');
        if (!in_array($tab, ['main', 'merchant'], true)) {
            $tab = 'main';
        }

        $lines = \LicenseClient::lines();
        $assetHost = $lines ? rtrim((string) $lines[0]['url'], '/') : '';

        $this->render('appstore_buy', [
            'appId'     => $appId,
            'tab'       => $tab,
            'assetHost' => $assetHost,
            'csrfToken' => \Csrf::token(),
        ]);
    }
}
