<?php

declare(strict_types=1);

require_once __DIR__ . '/global.php';

/**
 * 商户后台 —— 文件上传代理。
 *
 * 和主站 admin/upload.php 结构一致，仅把 adminRequireLogin 换成 merchantRequireLogin。
 * UploadService 内部会把文件存到 content/uploads/ 下，返回公共 URL。
 */
merchantRequireLogin();

if (!Request::isPost()) {
    Response::error('请求方式无效');
}

$csrf = (string) Input::post('csrf_token', '');
if (!Csrf::validate($csrf)) {
    Response::error('请求已失效，请刷新页面后重试');
}

if (empty($_FILES['file'])) {
    Response::error('请选择图片文件');
}

$uploader = new UploadService();
$context = (string) Input::post('context', 'default');
$result = $uploader->upload($_FILES['file'], ['jpg', 'jpeg', 'png', 'gif', 'webp'], $context);

// 商品图片上传钩子：插件可接管（图床/CDN）；商户上下文也复用同一条钩子
if ($context === 'goods_image') {
    $filtered = applyFilter('goods_image_upload', $result, ['file' => $_FILES['file']]);
    if ($filtered !== $result) {
        $result = $filtered;
    }
}

$csrfToken = Csrf::token();
Response::success('上传成功', [
    'csrf_token' => $csrfToken,
    'url' => $result['url'],
]);
