<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 后台清缓存（/admin/cache）。
 *
 * POST _action=clear_cache 清空缓存。
 */
final class CacheController extends BaseAdminController
{
    public function index(): void
    {
        if (!$this->request->isPost()) {
            $this->response->error('请求方式不正确');
        }

        if (!$this->csrf->validate((string) $this->input->post('csrf_token', ''))) {
            $this->response->error('请求已失效，请刷新页面后重试');
        }

        $this->cache->clear();
        $this->response->success('缓存已清空');
    }
}
