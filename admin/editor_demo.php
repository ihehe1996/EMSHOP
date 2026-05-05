<?php

declare(strict_types=1);

require __DIR__ . '/global.php';

/**
 * 富文本编辑器演示页面。
 *
 * 演示 WangEditor v5 的各项功能。
 */
adminRequireLogin();
$user = $adminUser;
$siteName = Config::get('sitename', 'EMSHOP');

if (Request::isPost()) {
    try {
        $csrf = (string) Input::post('csrf_token', '');
        if (!Csrf::validate($csrf)) {
            Response::error('请求已失效，请刷新页面后重试');
        }

        $content = Input::post('content', '');
        if ($content === '') {
            Response::error('内容不能为空');
        }

        $csrfToken = Csrf::refresh();
        Response::success('内容保存成功（演示模式，仅返回内容长度）', [
            'csrf_token' => $csrfToken,
            'content_length' => mb_strlen($content),
        ]);
    } catch (Throwable $e) {
        Response::error('系统繁忙，请稍后再试');
    }
}

$csrfToken = Csrf::token();

if (Request::isPjax()) {
    echo '<div id="adminContent" class="admin-content">';
    include __DIR__ . '/view/editor_demo.php';
    echo '</div>';
} else {
    $adminContentView = __DIR__ . '/view/editor_demo.php';
    require __DIR__ . '/index.php';
}
