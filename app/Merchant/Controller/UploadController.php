<?php

declare(strict_types=1);

namespace App\Merchant\Controller;

/**
 * 商户后台 - 文件上传（/user/merchant/upload）。
 */
final class UploadController extends MerchantBaseController
{
    public function index(): void
    {
        $frontUser = $this->user();
        $currentMerchant = $this->merchant();
        $merchantLevel = $this->level();
        if (!$this->request->isPost()) {
            $this->response->error('请求方式无效');
        }

        $csrf = (string) $this->input->post('csrf_token', '');
        if (!$this->csrf->validate($csrf)) {
            $this->response->error('请求已失效，请刷新页面后重试');
        }

        if (empty($_FILES['file'])) {
            $this->response->error('请选择图片文件');
        }

        $uploader = new \App\Service\UploadService();
        $context = (string) $this->input->post('context', 'default');

        try {
            $result = $uploader->upload($_FILES['file'], ['jpg', 'jpeg', 'png', 'gif', 'webp'], $context);

            // 商品图片上传钩子：插件可接管（图床/CDN）；商户上下文也复用同一条钩子
            if ($context === 'goods_image') {
                $filtered = applyFilter('goods_image_upload', $result, ['file' => $_FILES['file']]);
                if ($filtered !== $result) {
                    $result = $filtered;
                }
            }
        } catch (RuntimeException $e) {
            $this->response->error($e->getMessage());
        }

        $csrfToken = $this->csrf->token();
        $this->response->success('上传成功', [
            'csrf_token' => $csrfToken,
            'url' => $result['url'],
        ]);
    }
}
