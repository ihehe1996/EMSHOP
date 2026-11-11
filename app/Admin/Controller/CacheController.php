<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 后台清缓存（/admin/cache）。
 *
 * 对应旧入口 /admin/index.php 的 POST _action=clear_cache。
 */
final class CacheController extends BaseAdminController
{
    public function index(): void
    {
        if (!\Request::isPost()) {
            \Response::error('请求方式不正确');
        }

        if (!\Csrf::validate((string) \Input::post('csrf_token', ''))) {
            \Response::error('请求已失效，请刷新页面后重试');
        }

        \Cache::clear();
        \Response::success('缓存已清空');
    }
}
