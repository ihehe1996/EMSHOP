<?php

declare(strict_types=1);

namespace App\Admin\Controller;

/**
 * 页面编辑弹窗（/admin/page_edit）。
 *
 * 动作：_action=save 保存；?_popup=1 渲染编辑弹窗。
 * 与旧 admin/page_edit.php 行为一致（主站后台不展示/操作商户页面）。
 */
final class PageEditController extends BaseAdminController
{
    public function index(): void
    {
        $action = (string) \Input::get('_action', '');
        if ($action === '') {
            $action = (string) \Input::post('_action', '');
        }

        if ($action === 'save') {
            $this->handleSave();
            return;
        }

        $id = (int) \Input::get('id', 0);
        $pageRow = $id ? \PageModel::getById($id) : null;
        if ($pageRow && (int) $pageRow['merchant_id'] !== 0) {
            $pageRow = null; // 主站后台不展示商户页面
        }

        if ((string) \Input::get('_popup', '') === '1') {
            $this->render('popup/page_edit', ['pageRow' => $pageRow]);
        }
    }

    /**
     * 保存页面。
     */
    private function handleSave(): void
    {
        $csrf = (string) \Input::post('csrf_token', '');
        if (!\Csrf::validate($csrf)) {
            \Response::error('请求已失效，请刷新页面后重试');
        }

        $id = (int) \Input::post('id', 0);
        $title = trim((string) \Input::post('title', ''));
        $slug = trim((string) \Input::post('slug', ''));
        $content = (string) \Input::post('content', '');
        $status = (int) \Input::post('status', 1);
        $templateName = trim((string) \Input::post('template_name', ''));
        $sort = (int) \Input::post('sort', 100);
        $seoTitle = trim((string) \Input::post('seo_title', ''));
        $seoKeywords = trim((string) \Input::post('seo_keywords', ''));
        $seoDescription = trim((string) \Input::post('seo_description', ''));

        // ---------- 校验 ----------
        if ($title === '') {
            \Response::error('页面标题不能为空');
        }
        // slug 为空则自动从标题生成（只保留 a-z / 0-9 / 中划线）
        if ($slug === '') {
            $slug = strtolower($title);
            $slug = preg_replace('/[^a-z0-9\-]+/u', '-', $slug);
            $slug = trim($slug, '-');
            if ($slug === '') {
                $slug = 'page-' . time();
            }
        } else {
            // slug 只允许字母数字/中划线/下划线
            if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $slug)) {
                \Response::error('URL 别名只能包含字母、数字、中划线和下划线');
            }
            $slug = strtolower($slug);
        }
        // 保留字黑名单（避免和系统路由冲突）
        $reserved = ['admin', 'user', 'blog', 'goods', 'order', 'login', 'logout', 'api', 'install', 'content', 'p'];
        if (in_array($slug, $reserved, true)) {
            \Response::error('URL 别名 "' . $slug . '" 是系统保留字，请换一个');
        }
        if (\PageModel::slugExists($slug, $id, 0)) {
            \Response::error('URL 别名 "' . $slug . '" 已被占用');
        }
        // template_name 只允许小写字母数字中划线下划线
        if ($templateName !== '' && !preg_match('/^[a-z0-9_\-]+$/', $templateName)) {
            \Response::error('模板名只能包含小写字母、数字、中划线和下划线');
        }

        $data = [
            'title'           => $title,
            'slug'            => $slug,
            'content'         => $content,
            'status'          => $status === 1 ? 1 : 0,
            'template_name'   => $templateName,
            'sort'            => $sort,
            'seo_title'       => $seoTitle,
            'seo_keywords'    => $seoKeywords,
            'seo_description' => $seoDescription,
        ];

        try {
            if ($id) {
                $existing = \PageModel::getById($id);
                if (!$existing) {
                    \Response::error('页面不存在');
                }
                if ((int) $existing['merchant_id'] !== 0) {
                    \Response::error('无权操作商户页面');
                }
                \PageModel::update($id, $data);
                $pageId = $id;
            } else {
                $data['merchant_id'] = 0; // 主站后台创建的页面固定归主站
                $pageId = \PageModel::create($data);
            }
            if (!$pageId) {
                \Response::error('保存失败');
            }
        } catch (\Throwable $e) {
            \Response::error('保存失败：' . $e->getMessage());
        }

        \Response::success('保存成功', ['id' => $pageId, 'csrf_token' => \Csrf::refresh()]);
    }
}
