<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 图片上传接口（/admin/upload）。
 *
 * 仅接受 POST；写 em_attachment 并落盘，必须校验 CSRF。
 * context=goods_image 时触发 goods_image_upload 钩子，允许插件接管上传结果。
 */
final class UploadController extends BaseAdminController
{
    public function index(): void
    {
        if (!\Request::isPost()) {
            \Response::error('请求方式无效');
        }

        // 图片上传会写 em_attachment 并落盘，属于写操作，必须校验 CSRF。
        if (!\Csrf::validate((string) \Input::post('csrf_token', ''))) {
            \Response::error('请求已失效，请刷新页面后重试');
        }

        if (empty($_FILES['file'])) {
            \Response::error('请选择图片文件');
        }

        $uploader = new \UploadService();
        $context = (string) \Input::post('context', 'default');
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if ($context === 'site_favicon') {
            $allowed[] = 'ico';
        }

        try {
            $result = $uploader->upload($_FILES['file'], $allowed, $context);

            // 商品图片上传钩子：允许插件接管上传结果（如图床/CDN）
            if ($context === 'goods_image') {
                $filtered = \applyFilter('goods_image_upload', $result, ['file' => $_FILES['file']]);
                if ($filtered !== $result) {
                    $result = $filtered;
                }
            }
        } catch (\RuntimeException $e) {
            \Response::error($e->getMessage());
        }

        // 不再在上传后刷新 token，避免用户在同一页面多次上传时 token 失效
        $csrfToken = \Csrf::token();
        \Response::success('上传成功', [
            'csrf_token' => $csrfToken,
            'url' => $result['url'],
        ]);
    }
}
