<?php

declare(strict_types=1);

namespace App\Merchant\Controller;

/**
 * 商户后台未登记路由。
 */
final class NotFoundController extends MerchantBaseController
{
    public function index(): void
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>页面不存在</title></head><body style="margin:0;min-height:100vh;display:flex;'
            . 'align-items:center;justify-content:center;font-family:sans-serif;background:#f6f7fb;">'
            . '<div style="text-align:center;color:#374151;"><h1 style="font-size:22px;">页面不存在</h1>'
            . '<p style="color:#6b7280;">请从商户后台菜单重新进入。</p>'
            . '<a href="/user/merchant/home" style="color:#4f46e5;">返回商户后台</a></div></body></html>';
    }
}
