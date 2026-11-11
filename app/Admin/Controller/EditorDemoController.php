<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 富文本编辑器演示页（/admin/editor_demo）。
 *
 * 演示 WangEditor v5 功能；POST 仅返回内容长度（演示模式）。
 * 整页请求由 AdminShell 渲染（app/Admin/View/shell.php）。
 */
final class EditorDemoController extends BaseAdminController
{
    public function index(): void
    {
        if (\Request::isPost()) {
            try {
                $csrf = (string) \Input::post('csrf_token', '');
                if (!\Csrf::validate($csrf)) {
                    \Response::error('请求已失效，请刷新页面后重试');
                }

                $content = \Input::post('content', '');
                if ($content === '') {
                    \Response::error('内容不能为空');
                }

                $csrfToken = \Csrf::refresh();
                \Response::success('内容保存成功（演示模式，仅返回内容长度）', [
                    'csrf_token' => $csrfToken,
                    'content_length' => mb_strlen((string) $content),
                ]);
            } catch (\Throwable $e) {
                \Response::error('系统繁忙，请稍后再试');
            }
        }

        if (\Request::isPjax()) {
            echo '<div id="adminContent" class="admin-content">';
            $this->render('editor_demo');
            echo '</div>';
            return;
        }

        \App\Admin\AdminShell::render(dirname(__DIR__) . '/View/editor_demo.php', $this->user(), get_defined_vars());
    }
}
