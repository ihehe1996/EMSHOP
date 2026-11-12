<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 应用商店 —— 购买弹窗（{入口}/appstore_buy）。
 *
 * 仅渲染独立弹窗框架 HTML；页面初始化后由 JS 调 {入口}/appstore 的接口。
 */
final class AppstoreBuyController extends BaseAdminController
{
    public function index(): void
    {
        $appId = (int) $this->input->get('id', 0);

        $tab = (string) $this->input->get('tab', 'main');
        if (!in_array($tab, ['main', 'merchant'], true)) {
            $tab = 'main';
        }

        $lines = $this->licenseClient->lines();
        $assetHost = $lines ? rtrim((string) $lines[0]['url'], '/') : '';

        $this->render('appstore_buy', [
            'appId'     => $appId,
            'tab'       => $tab,
            'assetHost' => $assetHost,
            'csrfToken' => $this->csrf->token(),
        ]);
    }
}
