<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

adminRequireLogin();

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

// 商品图片上传钩子：允许插件接管上传结果（如图床/CDN）
if ($context === 'goods_image') {
    $filtered = applyFilter('goods_image_upload', $result, ['file' => $_FILES['file']]);
    if ($filtered !== $result) {
        $result = $filtered;
    }
}

// 不再在上传后刷新 token，避免用户在同一页面多次上传时 token 失效
// token 在 validate() 时通过宽限期机制兼容旧 token
$csrfToken = Csrf::token();
Response::success('上传成功', [
    'csrf_token' => $csrfToken,
    'url' => $result['url'],
]);